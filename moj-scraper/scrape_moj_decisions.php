<?php

$configPath = $argv[1] ?? __DIR__ . '/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Copy config.example.php to config.php, update database credentials, then run again.\n");
    exit(1);
}

$config = require $configPath;
$pdo = new PDO(
    $config['database']['dsn'],
    $config['database']['username'],
    $config['database']['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]
);

$startPage = (int) $config['scraper']['start_page'];
$endPage = (int) $config['scraper']['end_page'];

for ($page = $startPage; $page <= $endPage; $page++) {
    $url = sprintf($config['scraper']['base_url'], $page);
    echo "Fetching page {$page}: {$url}\n";

    $html = fetchHtml($url, $config['scraper']);
    $decisions = parseDecisions($html, $url, $page, $config['scraper']['item_selectors']);

    foreach ($decisions as $decision) {
        upsertDecision($pdo, $decision);
    }

    echo "Saved " . count($decisions) . " decision rows from page {$page}.\n";

    if ($page < $endPage) {
        sleep((int) $config['scraper']['delay_seconds']);
    }
}

function fetchHtml(string $url, array $settings): string
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => (int) $settings['timeout_seconds'],
        CURLOPT_USERAGENT => $settings['user_agent'],
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ar,en;q=0.8',
        ],
    ]);

    $html = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($html === false || $statusCode >= 400) {
        throw new RuntimeException("Unable to fetch {$url}; HTTP {$statusCode}; {$error}");
    }

    return $html;
}

function parseDecisions(string $html, string $sourceUrl, int $pageNumber, array $itemSelectors): array
{
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $xpath = new DOMXPath($document);
    $nodes = [];
    foreach ($itemSelectors as $selector) {
        $matches = $xpath->query($selector);
        if ($matches !== false && $matches->length > 0) {
            foreach ($matches as $match) {
                $nodes[] = $match;
            }
            break;
        }
    }

    if ($nodes === []) {
        $body = $xpath->query('//body')->item(0);
        $nodes = $body ? [$body] : [$document->documentElement];
    }

    $decisions = [];
    foreach ($nodes as $node) {
        $rawText = normalizeText($node->textContent ?? '');
        if ($rawText === '') {
            continue;
        }

        $detailUrl = firstLink($xpath, $node, $sourceUrl);
        $decision = [
            'source_url' => $sourceUrl,
            'page_number' => $pageNumber,
            'title' => firstLine($rawText),
            'decision_number' => matchArabicField($rawText, '/(?:رقم\s*(?:الحكم|القرار|القضية)\s*[:：]?\s*)([^\n\r]+)/u'),
            'decision_date' => matchArabicField($rawText, '/(?:تاريخ\s*(?:الحكم|القرار)?\s*[:：]?\s*)([^\n\r]+)/u'),
            'court' => matchArabicField($rawText, '/(?:المحكمة\s*[:：]?\s*)([^\n\r]+)/u'),
            'category' => matchArabicField($rawText, '/(?:التصنيف|الموضوع|نوع\s*الدعوى)\s*[:：]?\s*([^\n\r]+)/u'),
            'summary' => mb_substr($rawText, 0, 1500, 'UTF-8'),
            'detail_url' => $detailUrl,
            'raw_text' => $rawText,
            'content_hash' => hash('sha256', $sourceUrl . '|' . $rawText . '|' . ($detailUrl ?? '')),
        ];
        $decisions[] = $decision;
    }

    return $decisions;
}

function normalizeText(string $text): string
{
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\R\s*/u', "\n", $text) ?? $text;
    return trim($text);
}

function firstLine(string $text): ?string
{
    $lines = preg_split('/\R/u', $text) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            return mb_substr($line, 0, 500, 'UTF-8');
        }
    }
    return null;
}

function matchArabicField(string $text, string $pattern): ?string
{
    if (preg_match($pattern, $text, $matches) !== 1) {
        return null;
    }

    return mb_substr(trim($matches[1]), 0, 255, 'UTF-8');
}

function firstLink(DOMXPath $xpath, DOMNode $node, string $sourceUrl): ?string
{
    $link = $xpath->query('.//a[@href]', $node)->item(0);
    if (!$link instanceof DOMElement) {
        return null;
    }

    return absoluteUrl($link->getAttribute('href'), $sourceUrl);
}

function absoluteUrl(string $href, string $baseUrl): string
{
    if (preg_match('/^https?:\/\//i', $href) === 1) {
        return $href;
    }

    $parts = parse_url($baseUrl);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    if (str_starts_with($href, '/')) {
        return $scheme . '://' . $host . $href;
    }

    $path = isset($parts['path']) ? rtrim(dirname($parts['path']), '/') : '';
    return $scheme . '://' . $host . $path . '/' . ltrim($href, '/');
}

function upsertDecision(PDO $pdo, array $decision): void
{
    $sql = 'INSERT INTO judicial_decisions
        (source_url, page_number, title, decision_number, decision_date, court, category, summary, detail_url, raw_text, content_hash)
        VALUES
        (:source_url, :page_number, :title, :decision_number, :decision_date, :court, :category, :summary, :detail_url, :raw_text, :content_hash)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            decision_number = VALUES(decision_number),
            decision_date = VALUES(decision_date),
            court = VALUES(court),
            category = VALUES(category),
            summary = VALUES(summary),
            detail_url = VALUES(detail_url),
            raw_text = VALUES(raw_text),
            updated_at = CURRENT_TIMESTAMP';

    $statement = $pdo->prepare($sql);
    $statement->execute($decision);
}
