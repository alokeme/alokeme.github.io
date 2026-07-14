<?php
return [
    'database' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=moj_judicial_decisions;charset=utf8mb4',
        'username' => 'root',
        'password' => '',
    ],
    'scraper' => [
        'base_url' => 'https://laws.moj.gov.sa/ar/JudicialDecisionsList/%d',
        'start_page' => 1,
        'end_page' => 1,
        'delay_seconds' => 2,
        'timeout_seconds' => 30,
        'user_agent' => 'Mozilla/5.0 (compatible; MOJDecisionArchiver/1.0; +https://example.com)',
        'item_selectors' => [
            '//*[contains(@class,"decision") or contains(@class,"card") or contains(@class,"list-item") or contains(@class,"item")]',
            '//article',
            '//li[.//a]',
        ],
    ],
];
