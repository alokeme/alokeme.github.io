# MOJ Judicial System

منظومة PHP 8.3 تقليدية بدون Framework لاستيراد وبحث وتحليل الأحكام القضائية من بوابة وزارة العدل السعودية عبر `https://laws-gateway.moj.gov.sa/apis/legislations/v1`.

## التثبيت

```bash
composer install
mysql -u root -p < sql/schema.sql
```

حدّث `config/config.php` أو متغيرات البيئة `DB_DSN`, `DB_USER`, `DB_PASS`, `APP_URL`, `APP_KEY`.

## Cron Jobs

```bash
php cron/import.php 0
php cron/backup.php
php cron/cleanup.php
```

## API

- `GET api/search.php?q=...`
- `GET api/decision.php?id=1`
- `GET api/statistics.php`
- `POST api/import.php` مع CSRF.

## المجلدات

- `classes/`: خدمات OOP بنمط PSR-12 وNamespaces.
- `public/`: واجهات Bootstrap وAJAX.
- `sql/schema.sql`: الجداول والفهارس وFullText والـViews والـStored Procedures.
- `cron/`: الاستيراد والتحديث والنسخ الاحتياطي والتنظيف.

## الأمان

PDO Prepared Statements، CSRF، حماية الجلسات، CSP، XSS escaping، Password Hashing، وسجلات Monolog.
