# سحب قرارات وزارة العدل إلى MySQL

هذا المثال يوفّر سكربت PHP وقاعدة بيانات MySQL لأرشفة البيانات المنشورة في صفحة:

`https://laws.moj.gov.sa/ar/JudicialDecisionsList/1`

> تنبيه: التزم بشروط استخدام الموقع، ولا ترفع معدل الطلبات. غيّر `delay_seconds` عند تشغيل نطاق صفحات كبير.

## المتطلبات

- PHP 8.0 أو أحدث.
- إضافات PHP: `curl`, `dom`, `pdo_mysql`, `mbstring`.
- MySQL 5.7 أو أحدث، أو MariaDB متوافق.

## التجهيز

1. أنشئ قاعدة البيانات والجداول:

   ```bash
   mysql -u root -p < moj-scraper/schema.sql
   ```

2. انسخ ملف الإعدادات وعدّل بيانات الاتصال:

   ```bash
   cp moj-scraper/config.example.php moj-scraper/config.php
   ```

3. حدد نطاق الصفحات داخل `config.php`:

   ```php
   'start_page' => 1,
   'end_page' => 10,
   ```

4. شغّل السكربت:

   ```bash
   php moj-scraper/scrape_moj_decisions.php moj-scraper/config.php
   ```

## ملاحظات تخص الموقع

الصفحة قد تتغير بنيتها أو قد تعرض البيانات عبر JavaScript. لذلك يحتوي السكربت على قائمة `item_selectors` في ملف الإعدادات لتعديل محددات XPath دون تغيير الكود الأساسي. إذا أصبحت الصفحة تعتمد كليًا على API خلفي، استخدم تبويب Network في المتصفح لمعرفة endpoint الرسمي ثم عدّل دالة الجلب بما يتوافق معه.
