<?php
declare(strict_types=1);

/**
 * مثبت إصلاح قسم عروض وأسعار الإقامة
 * يوضع في جذر tourism_platform ثم يفتح من المتصفح.
 * لا يتضمن أي صور ولا يعدل قاعدة البيانات.
 */

const INSTALL_KEY = 'OFFERS-2026-B7F4';
const VERSION_TAG = '20260721-3';

header('Content-Type: text/html; charset=utf-8');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function writeFileSafe(string $path, string $content): void
{
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('تعذر إنشاء المجلد: ' . $directory);
    }

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('تعذر كتابة الملف: ' . $path);
    }
}

function injectBefore(string $html, string $closingTag, string $injection): string
{
    $position = strripos($html, $closingTag);

    if ($position === false) {
        return $html . "\n" . $injection . "\n";
    }

    return substr($html, 0, $position)
        . "\n"
        . $injection
        . "\n"
        . substr($html, $position);
}

$apiContent = <<<'PHP_API'
<?php
declare(strict_types=1);

ob_start();

function offersJson(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function tableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables '
        . 'WHERE table_schema = DATABASE() AND table_name = ?'
    );

    $statement->execute([$table]);

    return (int)$statement->fetchColumn() > 0;
}

function tableColumns(PDO $pdo, string $table): array
{
    $statement = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns '
        . 'WHERE table_schema = DATABASE() AND table_name = ?'
    );

    $statement->execute([$table]);

    $columns = [];

    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $column) {
        $columns[(string)$column] = true;
    }

    return $columns;
}

function firstColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) {
            return $candidate;
        }
    }

    return null;
}

function identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function columnExpression(
    string $alias,
    ?string $column,
    string $fallback = 'NULL'
): string {
    return $column !== null
        ? $alias . '.' . identifier($column)
        : $fallback;
}

function fetchOffersFromTable(
    PDO $pdo,
    string $table,
    string $country,
    int $limit,
    array &$diagnostics
): array {
    if (!tableExists($pdo, $table)) {
        return [];
    }

    $propertyColumns = tableColumns($pdo, 'properties');
    $offerColumns = tableColumns($pdo, $table);

    $propertyId = firstColumn($propertyColumns, ['id']);
    $offerPropertyId = firstColumn($offerColumns, ['property_id', 'hotel_id', 'accommodation_id']);

    if ($propertyId === null || $offerPropertyId === null) {
        $diagnostics[] = $table . ': لا يوجد عمود ربط مناسب.';
        return [];
    }

    $offerId = firstColumn($offerColumns, ['id']);
    $platformId = firstColumn($offerColumns, ['platform_id', 'booking_platform_id']);
    $priceColumn = firstColumn($offerColumns, [
        'displayed_price',
        'price',
        'amount',
        'minimum_price',
        'min_price',
        'price_from'
    ]);
    $currencyColumn = firstColumn($offerColumns, ['currency', 'currency_code']);
    $statusColumn = firstColumn($offerColumns, ['status', 'link_status', 'offer_status']);
    $preferredColumn = firstColumn($offerColumns, ['is_preferred', 'preferred']);
    $updatedColumn = firstColumn($offerColumns, ['fetched_at', 'updated_at', 'created_at']);

    $urlColumns = [];

    foreach ([
        'affiliate_url',
        'booking_url',
        'url',
        'deeplink_url',
        'external_url'
    ] as $candidate) {
        if (isset($offerColumns[$candidate])) {
            $urlColumns[] = $candidate;
        }
    }

    if (!$urlColumns && $priceColumn === null) {
        $diagnostics[] = $table . ': لا يوجد رابط أو سعر.';
        return [];
    }

    $urlParts = [];

    foreach ($urlColumns as $urlColumn) {
        $urlParts[] = "NULLIF(TRIM(o." . identifier($urlColumn) . "), '')";
    }

    $urlExpression = $urlParts
        ? 'COALESCE(' . implode(', ', $urlParts) . ')'
        : 'NULL';

    $nameAr = firstColumn($propertyColumns, ['name_ar', 'arabic_name', 'name']);
    $nameEn = firstColumn($propertyColumns, ['name_en', 'english_name']);
    $addressAr = firstColumn($propertyColumns, ['address_ar', 'arabic_address', 'address']);
    $addressEn = firstColumn($propertyColumns, ['address_en', 'english_address']);
    $imageUrl = firstColumn($propertyColumns, ['image_url', 'primary_image_url', 'photo_url']);
    $phone = firstColumn($propertyColumns, ['primary_phone', 'phone', 'telephone']);
    $reviewScore = firstColumn($propertyColumns, ['review_score', 'rating', 'google_rating']);
    $reviewCount = firstColumn($propertyColumns, ['review_count', 'reviews_count', 'user_ratings_total']);
    $latitude = firstColumn($propertyColumns, ['latitude', 'lat']);
    $longitude = firstColumn($propertyColumns, ['longitude', 'lng', 'lon']);
    $countryColumn = firstColumn($propertyColumns, ['country_code', 'country']);
    $propertyStatus = firstColumn($propertyColumns, ['operational_status', 'status']);

    $platformJoin = '';
    $platformNameExpression = "'منصة حجز'";
    $platformCodeExpression = "''";

    if (
        $platformId !== null &&
        tableExists($pdo, 'booking_platforms')
    ) {
        $platformColumns = tableColumns($pdo, 'booking_platforms');
        $platformPrimaryId = firstColumn($platformColumns, ['id']);

        if ($platformPrimaryId !== null) {
            $platformJoin = '\nLEFT JOIN booking_platforms bp '
                . 'ON bp.' . identifier($platformPrimaryId)
                . ' = o.' . identifier($platformId);

            $platformName = firstColumn($platformColumns, [
                'platform_name',
                'name_ar',
                'name',
                'display_name'
            ]);
            $platformCode = firstColumn($platformColumns, ['platform_code', 'code', 'slug']);

            $platformNameExpression = columnExpression('bp', $platformName, "'منصة حجز'");
            $platformCodeExpression = columnExpression('bp', $platformCode, "''");
        }
    }

    $where = [];
    $parameters = [];

    if ($countryColumn !== null) {
        $where[] = 'p.' . identifier($countryColumn) . ' = :country';
        $parameters['country'] = $country;
    }

    if ($propertyStatus !== null) {
        $statusExpression = 'LOWER(COALESCE(p.' . identifier($propertyStatus) . ", 'active'))";
        $where[] = $statusExpression . " NOT IN ('inactive','disabled','deleted','closed')";
    }

    if ($statusColumn !== null) {
        $offerStatusExpression = 'LOWER(COALESCE(o.' . identifier($statusColumn) . ", 'active'))";
        $where[] = $offerStatusExpression . " NOT IN ('inactive','disabled','deleted','rejected')";
    }

    if ($urlParts) {
        $where[] = '(' . implode(' OR ', array_map(
            static fn(string $part): string => $part . ' IS NOT NULL',
            $urlParts
        )) . ')';
    } elseif ($priceColumn !== null) {
        $where[] = 'o.' . identifier($priceColumn) . ' IS NOT NULL';
        $where[] = 'o.' . identifier($priceColumn) . ' > 0';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $priceExpression = columnExpression('o', $priceColumn);
    $currencyExpression = columnExpression('o', $currencyColumn);
    $preferredExpression = columnExpression('o', $preferredColumn, '0');
    $updatedExpression = columnExpression('o', $updatedColumn);
    $offerIdExpression = columnExpression('o', $offerId, '0');

    $sql = "
        SELECT
            p." . identifier($propertyId) . " AS id,
            " . columnExpression('p', $nameAr, "''") . " AS name_ar,
            " . columnExpression('p', $nameEn, "''") . " AS name_en,
            " . columnExpression('p', $addressAr, "''") . " AS address_ar,
            " . columnExpression('p', $addressEn, "''") . " AS address_en,
            " . columnExpression('p', $imageUrl) . " AS image_url,
            " . columnExpression('p', $phone) . " AS primary_phone,
            " . columnExpression('p', $reviewScore) . " AS review_score,
            " . columnExpression('p', $reviewCount, '0') . " AS review_count,
            " . columnExpression('p', $latitude) . " AS latitude,
            " . columnExpression('p', $longitude) . " AS longitude,
            {$offerIdExpression} AS booking_link_id,
            {$priceExpression} AS displayed_price,
            {$currencyExpression} AS currency,
            {$urlExpression} AS outbound_url,
            {$platformNameExpression} AS platform_name,
            {$platformCodeExpression} AS platform_code,
            {$preferredExpression} AS is_preferred,
            {$updatedExpression} AS link_updated_at,
            CASE
                WHEN {$priceExpression} IS NOT NULL
                 AND {$priceExpression} > 0
                THEN 1 ELSE 0
            END AS has_real_price
        FROM " . identifier($table) . " o
        INNER JOIN properties p
            ON p." . identifier($propertyId)
            . ' = o.' . identifier($offerPropertyId)
            . $platformJoin . "
        {$whereSql}
        ORDER BY
            has_real_price DESC,
            is_preferred DESC,
            link_updated_at DESC,
            review_score DESC,
            review_count DESC,
            id DESC
        LIMIT {$limit}
    ";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

try {
    require __DIR__ . '/../includes/bootstrap.php';

    if (!function_exists('db')) {
        throw new RuntimeException('الدالة db() غير متاحة.');
    }

    $pdo = db();

    if (!tableExists($pdo, 'properties')) {
        offersJson([
            'ok' => true,
            'items' => [],
            'count' => 0,
        ]);
    }

    $country = strtoupper(
        preg_replace('/[^A-Z]/', '', (string)($_GET['country'] ?? 'SA'))
    );

    if (!preg_match('/^[A-Z]{2}$/', $country)) {
        $country = 'SA';
    }

    $diagnostics = [];
    $items = [];

    foreach (['property_booking_links', 'property_booking_offers'] as $sourceTable) {
        try {
            $sourceItems = fetchOffersFromTable(
                $pdo,
                $sourceTable,
                $country,
                24,
                $diagnostics
            );

            foreach ($sourceItems as $item) {
                $dedupeKey = (string)($item['id'] ?? '')
                    . '|'
                    . (string)($item['platform_code'] ?? '')
                    . '|'
                    . (string)($item['outbound_url'] ?? '');

                $items[$dedupeKey] = $item;
            }
        } catch (Throwable $sourceException) {
            $diagnostics[] = $sourceTable . ': ' . $sourceException->getMessage();
        }
    }

    $items = array_values($items);

    usort($items, static function (array $a, array $b): int {
        $priceCompare = (int)($b['has_real_price'] ?? 0)
            <=> (int)($a['has_real_price'] ?? 0);

        if ($priceCompare !== 0) {
            return $priceCompare;
        }

        return (float)($b['review_score'] ?? 0)
            <=> (float)($a['review_score'] ?? 0);
    });

    $items = array_slice($items, 0, 12);

    foreach ($items as &$item) {
        $item['id'] = (int)($item['id'] ?? 0);
        $item['booking_link_id'] = (int)($item['booking_link_id'] ?? 0);
        $item['has_real_price'] = (int)($item['has_real_price'] ?? 0);
        $item['is_preferred'] = (int)($item['is_preferred'] ?? 0);

        if (
            $item['displayed_price'] === null ||
            (float)$item['displayed_price'] <= 0
        ) {
            $item['displayed_price'] = null;
            $item['has_real_price'] = 0;
        }
    }

    unset($item);

    $response = [
        'ok' => true,
        'items' => $items,
        'count' => count($items),
    ];

    if ((string)($_GET['debug'] ?? '') === '1') {
        $response['diagnostics'] = $diagnostics;
    }

    offersJson($response);
} catch (Throwable $exception) {
    error_log(
        'Home offers repair API: '
        . $exception->getMessage()
        . ' in '
        . $exception->getFile()
        . ':'
        . $exception->getLine()
    );

    offersJson([
        'ok' => false,
        'items' => [],
        'count' => 0,
        'message' => 'تعذر تحميل خيارات الحجز حالياً.',
        'error_code' => 'OFFERS_REPAIR_API_FAILED',
    ], 500);
}
PHP_API;

$jsContent = <<<'JS'
(function () {
  'use strict';

  const state = {
    loading: false,
    installed: false,
    observer: null
  };

  function language() {
    return (
      window.APP?.lang ||
      document.documentElement.lang ||
      'ar'
    ).toLowerCase().startsWith('en')
      ? 'en'
      : 'ar';
  }

  function t(arabic, english) {
    return language() === 'en' ? english : arabic;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (character) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      }[character];
    });
  }

  function baseUrl() {
    if (window.APP?.base) {
      return String(window.APP.base).replace(/\/$/, '');
    }

    const path = window.location.pathname;

    if (/\/[^/]+\.php$/i.test(path)) {
      return path.replace(/\/[^/]+\.php$/i, '');
    }

    return path.replace(/\/$/, '');
  }

  function findElements() {
    const box =
      document.getElementById('homeOffers') ||
      document.querySelector('[data-home-offers]') ||
      document.querySelector('.home-offers-section .offers-strip') ||
      document.querySelector('.home-offers-section .offers-grid');

    if (!box) {
      return null;
    }

    const section =
      box.closest('.home-offers-section') ||
      box.closest('section') ||
      box.parentElement;

    return { box, section };
  }

  function clearSkeletons(root) {
    if (!root) {
      return;
    }

    root
      .querySelectorAll(
        '.skeleton, .offer-skeleton, [data-skeleton], .skeleton-card'
      )
      .forEach(function (element) {
        element.remove();
      });
  }

  function propertyName(item) {
    if (language() === 'ar') {
      return item.name_ar || item.name_en || t('مكان إقامة', 'Accommodation');
    }

    return item.name_en || item.name_ar || 'Accommodation';
  }

  function propertyAddress(item) {
    if (language() === 'ar') {
      return item.address_ar || item.address_en || '';
    }

    return item.address_en || item.address_ar || '';
  }

  function render(items, elements) {
    const { box, section } = elements;

    clearSkeletons(section);

    if (!items.length) {
      box.innerHTML = '';
      section.hidden = true;
      section.classList.add('ts-offers-empty');
      return;
    }

    section.hidden = false;
    section.classList.remove('ts-offers-empty');
    section.classList.add('ts-offers-ready');

    const heading = section.querySelector('h1, h2, h3');
    const description = section.querySelector('.section-heading p, .offers-description');

    if (heading) {
      heading.textContent = t(
        'خيارات الحجز والأسعار المتاحة',
        'Booking options and available rates'
      );
    }

    if (description) {
      description.textContent = t(
        'يظهر السعر الإرشادي عند توفره، ويُستكمل التحقق والحجز لدى المنصة الخارجية.',
        'Indicative rates appear when available; final verification and booking take place on the external platform.'
      );
    }

    box.classList.add('ts-offers-grid');

    box.innerHTML = items.map(function (item) {
      const hasPrice =
        Number(item.has_real_price) === 1 &&
        Number(item.displayed_price) > 0;

      const priceText = hasPrice
        ? `${escapeHtml(item.displayed_price)} ${escapeHtml(item.currency || '')}`
        : t('تحقق من السعر لدى المنصة', 'Check price on the platform');

      const detailsUrl =
        `${baseUrl()}/property.php?id=${Number(item.id)}` +
        `&lang=${encodeURIComponent(language())}`;

      let platformUrl = String(item.outbound_url || '').trim();

      if (!platformUrl && Number(item.booking_link_id) > 0) {
        platformUrl =
          `${baseUrl()}/go.php?link_id=${Number(item.booking_link_id)}`;
      }

      const imageHtml = item.image_url
        ? `<img class="ts-offer-image" loading="lazy" src="${escapeHtml(item.image_url)}" alt="${escapeHtml(propertyName(item))}">`
        : '';

      const platformButton = platformUrl
        ? `<a class="ts-offer-button ts-offer-button-primary" href="${escapeHtml(platformUrl)}" target="_blank" rel="nofollow sponsored noopener">${t('منصة الحجز', 'Booking platform')}</a>`
        : '';

      return `
        <article class="ts-offer-card">
          ${imageHtml}
          <div class="ts-offer-content">
            <div class="ts-offer-platform">
              ${escapeHtml(item.platform_name || item.platform_code || t('منصة حجز', 'Booking platform'))}
            </div>
            <h3>${escapeHtml(propertyName(item))}</h3>
            ${propertyAddress(item) ? `<p>${escapeHtml(propertyAddress(item))}</p>` : ''}
            <div class="ts-offer-price ${hasPrice ? 'has-price' : 'check-price'}">
              ${priceText}
            </div>
            <small>
              ${hasPrice
                ? t('السعر إرشادي وقد يتغير حسب التاريخ والتوفر.', 'Indicative rate; it may change by date and availability.')
                : t('يظهر السعر النهائي لدى منصة الحجز الخارجية.', 'The final rate is shown on the external booking platform.')}
            </small>
            <div class="ts-offer-actions">
              <a class="ts-offer-button" href="${escapeHtml(detailsUrl)}">${t('التفاصيل', 'Details')}</a>
              ${platformButton}
            </div>
          </div>
        </article>
      `;
    }).join('');

    box.dataset.tsOffersReady = '1';
  }

  async function loadOffers(force) {
    const elements = findElements();

    if (!elements || state.loading) {
      return;
    }

    if (!force && elements.box.dataset.tsOffersReady === '1') {
      return;
    }

    state.loading = true;
    clearSkeletons(elements.section);

    const country =
      document.getElementById('country')?.value ||
      window.APP?.defaultCountry ||
      'SA';

    try {
      const response = await fetch(
        `${baseUrl()}/api/home_offers.php?country=${encodeURIComponent(country)}&_=${Date.now()}`,
        {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { Accept: 'application/json' }
        }
      );

      const raw = await response.text();

      if (!raw.trim()) {
        throw new Error('Empty API response');
      }

      const data = JSON.parse(raw);

      if (!response.ok || data.ok === false) {
        throw new Error(data.message || `HTTP ${response.status}`);
      }

      render(Array.isArray(data.items) ? data.items : [], elements);
    } catch (error) {
      console.error('Offers repair:', error);
      clearSkeletons(elements.section);
      elements.box.innerHTML = '';
      elements.section.hidden = true;
    } finally {
      state.loading = false;
    }
  }

  function install() {
    const elements = findElements();

    if (!elements) {
      return false;
    }

    if (state.installed) {
      return true;
    }

    state.installed = true;

    clearSkeletons(elements.section);
    loadOffers(true);

    const country = document.getElementById('country');

    country?.addEventListener('change', function () {
      elements.box.dataset.tsOffersReady = '0';
      loadOffers(true);
    });

    state.observer = new MutationObserver(function () {
      const current = findElements();

      if (!current) {
        return;
      }

      const hasRepairCards = Boolean(current.box.querySelector('.ts-offer-card'));
      const hasSkeletons = Boolean(
        current.section.querySelector(
          '.skeleton, .offer-skeleton, [data-skeleton], .skeleton-card'
        )
      );

      if (!hasRepairCards && hasSkeletons) {
        clearSkeletons(current.section);
        current.box.dataset.tsOffersReady = '0';
        window.setTimeout(function () {
          loadOffers(true);
        }, 80);
      }
    });

    state.observer.observe(elements.section, {
      childList: true,
      subtree: true
    });

    return true;
  }

  function start() {
    if (install()) {
      return;
    }

    let attempts = 0;
    const timer = window.setInterval(function () {
      attempts += 1;

      if (install() || attempts >= 20) {
        window.clearInterval(timer);
      }
    }, 300);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
JS;

$cssContent = <<<'CSS'
.home-offers-section .skeleton,
.home-offers-section .offer-skeleton,
.home-offers-section [data-skeleton],
.home-offers-section .skeleton-card {
    display: none !important;
}

.home-offers-section[hidden],
.home-offers-section.ts-offers-empty {
    display: none !important;
}

.ts-offers-grid {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    width: 100%;
}

.ts-offer-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
}

.ts-offer-image {
    display: block;
    width: 100%;
    height: 170px;
    object-fit: cover;
    background: #eef3f8;
}

.ts-offer-content {
    padding: 15px;
}

.ts-offer-platform {
    margin-bottom: 6px;
    color: #1769d2;
    font-size: 12px;
    font-weight: 800;
}

.ts-offer-card h3 {
    margin: 0;
    color: #102448;
    font-size: 17px;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.ts-offer-card p {
    margin: 7px 0 0;
    color: #667085;
    font-size: 12px;
    line-height: 1.55;
    overflow-wrap: anywhere;
}

.ts-offer-price {
    margin-top: 12px;
    font-size: 17px;
    font-weight: 900;
}

.ts-offer-price.has-price {
    color: #08753d;
}

.ts-offer-price.check-price {
    color: #1769d2;
}

.ts-offer-card small {
    display: block;
    margin-top: 5px;
    color: #667085;
    line-height: 1.55;
}

.ts-offer-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 13px;
}

.ts-offer-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 7px 13px;
    border: 1px solid #1769d2;
    border-radius: 10px;
    background: #fff;
    color: #1769d2 !important;
    text-decoration: none !important;
    font-size: 12px;
    font-weight: 800;
}

.ts-offer-button-primary {
    background: #1769d2;
    color: #fff !important;
}

@media (max-width: 850px) {
    .ts-offers-grid {
        display: flex !important;
        gap: 12px;
        overflow-x: auto;
        padding-bottom: 8px;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
    }

    .ts-offer-card {
        flex: 0 0 min(82vw, 330px);
        scroll-snap-align: start;
    }
}

@media (max-width: 520px) {
    .ts-offer-card {
        flex-basis: 88vw;
    }

    .ts-offer-actions {
        display: grid;
        grid-template-columns: 1fr;
    }

    .ts-offer-button {
        width: 100%;
    }
}
CSS;

$root = __DIR__;
$message = '';
$error = '';
$installedFiles = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $providedKey = trim((string)($_POST['install_key'] ?? ''));

        if (!hash_equals(INSTALL_KEY, $providedKey)) {
            throw new RuntimeException('رمز التثبيت غير صحيح.');
        }

        $indexPath = $root . '/index.php';

        if (!is_file($indexPath)) {
            throw new RuntimeException(
                'ضع ملف التثبيت في جذر tourism_platform بجانب index.php.'
            );
        }

        $targets = [
            $root . '/api/home_offers.php' => $apiContent,
            $root . '/assets/js/home-offers-fix.js' => $jsContent,
            $root . '/assets/css/home-offers-fix.css' => $cssContent,
        ];

        foreach ($targets as $path => $content) {
            if (is_file($path)) {
                $backup = $path . '.bak_' . date('Ymd_His');

                if (!copy($path, $backup)) {
                    throw new RuntimeException('تعذر إنشاء نسخة احتياطية من: ' . $path);
                }
            }

            writeFileSafe($path, $content);
            $installedFiles[] = str_replace($root . '/', '', $path);
        }

        $indexContent = file_get_contents($indexPath);

        if ($indexContent === false) {
            throw new RuntimeException('تعذر قراءة index.php.');
        }

        $indexBackup = $indexPath . '.bak_offers_' . date('Ymd_His');

        if (!copy($indexPath, $indexBackup)) {
            throw new RuntimeException('تعذر إنشاء نسخة احتياطية من index.php.');
        }

        $cssTag = '<link rel="stylesheet" href="assets/css/home-offers-fix.css?v=' . VERSION_TAG . '">';
        $jsTag = '<script src="assets/js/home-offers-fix.js?v=' . VERSION_TAG . '" defer></script>';

        if (strpos($indexContent, 'assets/css/home-offers-fix.css') === false) {
            $indexContent = injectBefore($indexContent, '</head>', $cssTag);
        }

        if (strpos($indexContent, 'assets/js/home-offers-fix.js') === false) {
            $indexContent = injectBefore($indexContent, '</body>', $jsTag);
        }

        writeFileSafe($indexPath, $indexContent);
        $installedFiles[] = 'index.php';

        $message = 'تم تثبيت إصلاح العروض بنجاح.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تثبيت إصلاح العروض</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            background: #eef4f8;
            color: #102448;
            font-family: Tahoma, Arial, sans-serif;
        }
        .card {
            width: min(620px, 100%);
            padding: 26px;
            border: 1px solid #dce5ef;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 15px 50px rgba(15, 23, 42, .1);
        }
        h1 { margin-top: 0; font-size: 24px; }
        p { line-height: 1.8; }
        input, button {
            width: 100%;
            min-height: 48px;
            border-radius: 11px;
            font: inherit;
        }
        input {
            padding: 10px 13px;
            border: 1px solid #cad6e3;
        }
        button {
            margin-top: 12px;
            border: 0;
            background: #1769d2;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }
        .success, .error {
            padding: 13px;
            border-radius: 11px;
            line-height: 1.7;
        }
        .success { background: #e9f9ef; color: #086c39; }
        .error { background: #fff0f1; color: #a61b35; }
        code {
            direction: ltr;
            display: inline-block;
            padding: 2px 7px;
            border-radius: 6px;
            background: #eef3f8;
        }
        ul { line-height: 1.9; }
    </style>
</head>
<body>
<div class="card">
    <h1>إصلاح قسم خيارات الحجز والأسعار</h1>

    <?php if ($message !== ''): ?>
        <div class="success">
            <strong><?= h($message) ?></strong>
            <ul>
                <?php foreach ($installedFiles as $file): ?>
                    <li><code><?= h($file) ?></code></li>
                <?php endforeach; ?>
            </ul>
            <p>
                افتح الموقع في نافذة خاصة. ثم اختبر API عبر:<br>
                <code>api/home_offers.php?country=SA&amp;debug=1</code>
            </p>
            <p><strong>احذف ملف التثبيت بعد التأكد من نجاح الموقع.</strong></p>
        </div>
    <?php else: ?>
        <?php if ($error !== ''): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <p>
            هذا التثبيت يزيل المستطيلات الرمادية، ويعرض روابط منصات الحجز
            حتى عند عدم توفر سعر، ويخفي القسم تلقائياً إذا لم توجد بيانات.
            لا يتضمن صوراً ولا يعدل قاعدة البيانات.
        </p>

        <form method="post">
            <label for="install_key">رمز التثبيت</label>
            <input
                id="install_key"
                name="install_key"
                type="text"
                required
                autocomplete="off"
                placeholder="أدخل رمز التثبيت"
            >
            <button type="submit">تثبيت الإصلاح</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
