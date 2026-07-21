<?php
declare(strict_types=1);

/*
 * مثبت أدوات الخريطة — دون أي صور.
 * يضيف: ملء الشاشة + تغيير الخريطة الأساس.
 * ارفع هذا الملف إلى جذر tourism_platform ثم افتحه من المتصفح.
 */

const INSTALL_KEY = 'MAP-2026-7F9B2C4D';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function page(string $title, string $body, bool $success = false): void
{
    $color = $success ? '#087443' : '#17345f';
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<style>body{margin:0;background:#f4f7fb;font-family:Tahoma,Arial,sans-serif;color:#17233b}.box{max-width:760px;margin:55px auto;padding:28px;background:#fff;border:1px solid #dbe3ef;border-radius:20px;box-shadow:0 14px 40px rgba(15,23,42,.09)}h1{margin-top:0;color:' . $color . '}p,li{line-height:1.9}.field{margin:18px 0}input{width:100%;box-sizing:border-box;padding:13px;border:1px solid #ccd7e7;border-radius:12px;font:inherit}button{border:0;border-radius:12px;background:#1769d2;color:#fff;padding:13px 22px;font:inherit;font-weight:700;cursor:pointer}.ok{padding:13px;border-radius:12px;background:#e9f8f0;color:#087443}.error{padding:13px;border-radius:12px;background:#fff0f2;color:#a6173f}code{direction:ltr;display:inline-block;background:#eef3fa;padding:3px 8px;border-radius:7px}</style></head><body><main class="box">';
    echo '<h1>' . h($title) . '</h1>' . $body . '</main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    page(
        'تثبيت أدوات الخريطة',
        '<p>ينشئ هذا الملف أدوات تكبير الخريطة بملء الشاشة وتغيير الخريطة الأساس. لا يضيف أي صورة.</p>' .
        '<form method="post"><div class="field"><label>رمز التثبيت</label><input type="password" name="key" required></div><button type="submit">تنفيذ التحديث</button></form>' .
        '<p>رمز التثبيت: <code>MAP-2026-7F9B2C4D</code></p>'
    );
}

$key = (string)($_POST['key'] ?? '');
if (!hash_equals(INSTALL_KEY, $key)) {
    page('تعذر التثبيت', '<div class="error">رمز التثبيت غير صحيح.</div>');
}

$root = __DIR__;
$indexPath = $root . '/index.php';
$jsDir = $root . '/assets/js';
$cssDir = $root . '/assets/css';
$jsPath = $jsDir . '/map-controls.js';
$cssPath = $cssDir . '/map-controls.css';

if (!is_file($indexPath)) {
    page('تعذر التثبيت', '<div class="error">لم يتم العثور على <code>index.php</code>. ضع المثبت في جذر مجلد tourism_platform.</div>');
}

if (!is_dir($jsDir) && !mkdir($jsDir, 0755, true) && !is_dir($jsDir)) {
    page('تعذر التثبيت', '<div class="error">تعذر إنشاء مجلد assets/js.</div>');
}

if (!is_dir($cssDir) && !mkdir($cssDir, 0755, true) && !is_dir($cssDir)) {
    page('تعذر التثبيت', '<div class="error">تعذر إنشاء مجلد assets/css.</div>');
}

$js = <<<'JS'
(function () {
  'use strict';

  var STORAGE_KEY = 'tourism_map_basemap_v1';

  function text(ar, en) {
    var lang = document.documentElement.lang || (window.APP && window.APP.lang) || 'ar';
    return lang === 'en' ? en : ar;
  }

  function waitForLeaflet() {
    if (!window.L || typeof window.L.map !== 'function') {
      window.setTimeout(waitForLeaflet, 80);
      return;
    }

    if (window.L.map.__tourismMapWrapped) {
      return;
    }

    var originalMapFactory = window.L.map;

    function wrappedMapFactory() {
      var map = originalMapFactory.apply(window.L, arguments);
      window.setTimeout(function () {
        installControls(map);
      }, 350);
      return map;
    }

    Object.keys(originalMapFactory).forEach(function (key) {
      try {
        wrappedMapFactory[key] = originalMapFactory[key];
      } catch (error) {}
    });

    wrappedMapFactory.__tourismMapWrapped = true;
    window.L.map = wrappedMapFactory;
  }

  function installControls(map) {
    if (!map || map.__tourismControlsInstalled) {
      return;
    }

    var container = map.getContainer && map.getContainer();
    if (!container || container.id !== 'map') {
      return;
    }

    map.__tourismControlsInstalled = true;

    var currentLayer = null;
    map.eachLayer(function (layer) {
      if (!currentLayer && layer instanceof window.L.TileLayer) {
        currentLayer = layer;
      }
    });

    if (!currentLayer) {
      currentLayer = window.L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap contributors'
        }
      ).addTo(map);
    }

    var layers = {
      streets: {
        label: text('الشوارع', 'Streets'),
        layer: currentLayer
      },
      light: {
        label: text('فاتحة', 'Light'),
        layer: window.L.tileLayer(
          'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
          {
            subdomains: 'abcd',
            maxZoom: 20,
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
          }
        )
      },
      dark: {
        label: text('داكنة', 'Dark'),
        layer: window.L.tileLayer(
          'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
          {
            subdomains: 'abcd',
            maxZoom: 20,
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
          }
        )
      },
      satellite: {
        label: text('أقمار صناعية', 'Satellite'),
        layer: window.L.tileLayer(
          'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
          {
            maxZoom: 19,
            attribution: 'Tiles &copy; Esri'
          }
        )
      }
    };

    var activeKey = 'streets';

    function setBaseLayer(key) {
      if (!layers[key]) {
        key = 'streets';
      }

      Object.keys(layers).forEach(function (itemKey) {
        var itemLayer = layers[itemKey].layer;
        if (map.hasLayer(itemLayer)) {
          map.removeLayer(itemLayer);
        }
      });

      layers[key].layer.addTo(map);
      activeKey = key;

      try {
        localStorage.setItem(STORAGE_KEY, key);
      } catch (error) {}

      window.setTimeout(function () {
        map.invalidateSize();
      }, 80);
    }

    var BaseMapControl = window.L.Control.extend({
      options: { position: 'topright' },
      onAdd: function () {
        var wrapper = window.L.DomUtil.create('div', 'tourism-basemap-control leaflet-bar');
        var select = window.L.DomUtil.create('select', 'tourism-basemap-select', wrapper);
        select.setAttribute('aria-label', text('تغيير الخريطة الأساس', 'Change base map'));
        select.title = text('تغيير الخريطة الأساس', 'Change base map');

        Object.keys(layers).forEach(function (key) {
          var option = document.createElement('option');
          option.value = key;
          option.textContent = layers[key].label;
          select.appendChild(option);
        });

        try {
          var saved = localStorage.getItem(STORAGE_KEY);
          if (saved && layers[saved]) {
            select.value = saved;
            window.setTimeout(function () {
              setBaseLayer(saved);
            }, 30);
          }
        } catch (error) {}

        window.L.DomEvent.disableClickPropagation(wrapper);
        window.L.DomEvent.disableScrollPropagation(wrapper);
        select.addEventListener('change', function () {
          setBaseLayer(select.value);
        });

        return wrapper;
      }
    });

    map.addControl(new BaseMapControl());

    var panel = container.closest('.map-panel') || container.parentElement || container;

    function fallbackEnter(button) {
      panel.classList.add('tourism-map-fullscreen');
      document.body.classList.add('tourism-map-fullscreen-active');
      button.classList.add('is-active');
      button.textContent = '×';
      button.title = text('إغلاق ملء الشاشة', 'Exit fullscreen');
      window.setTimeout(function () { map.invalidateSize(); }, 180);
    }

    function fallbackExit(button) {
      panel.classList.remove('tourism-map-fullscreen');
      document.body.classList.remove('tourism-map-fullscreen-active');
      button.classList.remove('is-active');
      button.textContent = '⛶';
      button.title = text('تكبير الخريطة', 'Fullscreen map');
      window.setTimeout(function () { map.invalidateSize(); }, 180);
    }

    var FullscreenControl = window.L.Control.extend({
      options: { position: 'topleft' },
      onAdd: function () {
        var wrapper = window.L.DomUtil.create('div', 'tourism-fullscreen-control leaflet-bar');
        var button = window.L.DomUtil.create('button', 'tourism-map-control-button', wrapper);
        button.type = 'button';
        button.textContent = '⛶';
        button.title = text('تكبير الخريطة', 'Fullscreen map');
        button.setAttribute('aria-label', button.title);

        window.L.DomEvent.disableClickPropagation(wrapper);
        window.L.DomEvent.disableScrollPropagation(wrapper);

        button.addEventListener('click', function (event) {
          event.preventDefault();
          event.stopPropagation();

          var fallbackActive = panel.classList.contains('tourism-map-fullscreen');
          var nativeActive = document.fullscreenElement === panel;

          if (fallbackActive) {
            fallbackExit(button);
            return;
          }

          if (nativeActive && document.exitFullscreen) {
            document.exitFullscreen();
            return;
          }

          if (panel.requestFullscreen) {
            panel.requestFullscreen().catch(function () {
              fallbackEnter(button);
            });
          } else {
            fallbackEnter(button);
          }
        });

        document.addEventListener('fullscreenchange', function () {
          var active = document.fullscreenElement === panel;
          button.classList.toggle('is-active', active);
          button.textContent = active ? '×' : '⛶';
          button.title = active ? text('إغلاق ملء الشاشة', 'Exit fullscreen') : text('تكبير الخريطة', 'Fullscreen map');
          window.setTimeout(function () { map.invalidateSize(); }, 180);
        });

        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape' && panel.classList.contains('tourism-map-fullscreen')) {
            fallbackExit(button);
          }
        });

        return wrapper;
      }
    });

    map.addControl(new FullscreenControl());
  }

  waitForLeaflet();
})();
JS;

$css = <<<'CSS'
.tourism-map-control-button{display:flex;align-items:center;justify-content:center;width:36px;height:36px;padding:0;border:0;background:#fff;color:#12345b;cursor:pointer;font:700 23px/1 Arial,sans-serif}.tourism-map-control-button:hover,.tourism-map-control-button:focus-visible{background:#eef5ff;color:#1769d2;outline:0}.tourism-map-control-button.is-active{background:#1769d2;color:#fff}.tourism-fullscreen-control,.tourism-basemap-control{border:0!important;border-radius:10px!important;box-shadow:0 3px 14px rgba(15,23,42,.18)!important}.tourism-basemap-select{display:block;min-width:130px;height:38px;padding:0 10px;border:0;border-radius:10px;background:#fff;color:#17345f;font:700 12px Tahoma,Arial,sans-serif;cursor:pointer;outline:0}.map-panel:fullscreen{width:100vw!important;height:100vh!important;min-height:100vh!important;background:#fff!important;z-index:2147483000!important}.map-panel:fullscreen #map{width:100%!important;height:100%!important;min-height:100%!important}.tourism-map-fullscreen{position:fixed!important;inset:0!important;display:block!important;width:100vw!important;max-width:none!important;height:100dvh!important;min-height:100dvh!important;margin:0!important;padding:0!important;border:0!important;background:#fff!important;z-index:2147483000!important}.tourism-map-fullscreen #map{width:100%!important;height:100%!important;min-height:100dvh!important}body.tourism-map-fullscreen-active{overflow:hidden!important;touch-action:none}.tourism-map-fullscreen .leaflet-control-container,.map-panel:fullscreen .leaflet-control-container{position:relative;z-index:10000}@media(max-width:850px){.tourism-map-control-button{width:42px;height:42px;font-size:25px}.tourism-basemap-select{min-width:116px;height:42px;font-size:11px}.tourism-map-fullscreen,.map-panel:fullscreen{height:100dvh!important;min-height:100dvh!important}}
CSS;

if (file_put_contents($jsPath, $js, LOCK_EX) === false) {
    page('تعذر التثبيت', '<div class="error">تعذر كتابة ملف assets/js/map-controls.js.</div>');
}

if (file_put_contents($cssPath, $css, LOCK_EX) === false) {
    page('تعذر التثبيت', '<div class="error">تعذر كتابة ملف assets/css/map-controls.css.</div>');
}

$index = file_get_contents($indexPath);
if ($index === false) {
    page('تعذر التثبيت', '<div class="error">تعذر قراءة index.php.</div>');
}

$backupPath = $indexPath . '.backup_map_controls_' . date('Ymd_His');
if (!copy($indexPath, $backupPath)) {
    page('تعذر التثبيت', '<div class="error">تعذر إنشاء نسخة احتياطية من index.php.</div>');
}

$cssMarker = '<!-- TOURISM_MAP_CONTROLS_CSS -->';
$cssInclude = <<<'HTML'
<!-- TOURISM_MAP_CONTROLS_CSS -->
<link rel="stylesheet" href="assets/css/map-controls.css?v=<?= is_file(__DIR__ . '/assets/css/map-controls.css') ? filemtime(__DIR__ . '/assets/css/map-controls.css') : time() ?>">
HTML;

if (strpos($index, $cssMarker) === false) {
    $headEnd = stripos($index, '</head>');
    if ($headEnd === false) {
        page('تعذر التثبيت', '<div class="error">لم يتم العثور على وسم &lt;/head&gt; داخل index.php.</div>');
    }
    $index = substr($index, 0, $headEnd) . $cssInclude . "\n" . substr($index, $headEnd);
}

$jsMarker = '<!-- TOURISM_MAP_CONTROLS_JS -->';
$jsInclude = <<<'HTML'
<!-- TOURISM_MAP_CONTROLS_JS -->
<script src="assets/js/map-controls.js?v=<?= is_file(__DIR__ . '/assets/js/map-controls.js') ? filemtime(__DIR__ . '/assets/js/map-controls.js') : time() ?>"></script>
HTML;

if (strpos($index, $jsMarker) === false) {
    $appPosition = stripos($index, 'app.js');
    if ($appPosition === false) {
        page('تعذر التثبيت', '<div class="error">لم يتم العثور على استدعاء app.js داخل index.php.</div>');
    }

    $beforeApp = substr($index, 0, $appPosition);
    $scriptStart = strripos($beforeApp, '<script');
    if ($scriptStart === false) {
        page('تعذر التثبيت', '<div class="error">تعذر تحديد موضع استدعاء app.js داخل index.php.</div>');
    }

    $index = substr($index, 0, $scriptStart) . $jsInclude . "\n" . substr($index, $scriptStart);
}

if (file_put_contents($indexPath, $index, LOCK_EX) === false) {
    copy($backupPath, $indexPath);
    page('تعذر التثبيت', '<div class="error">تعذر تحديث index.php، وتمت استعادة النسخة السابقة.</div>');
}

page(
    'اكتمل التثبيت',
    '<div class="ok">تم إنشاء ملفات التحكم بالخريطة وتحديث index.php بنجاح.</div>' .
    '<ul><li>زر تكبير الخريطة بملء الشاشة.</li><li>اختيار الشوارع أو الخريطة الفاتحة أو الداكنة أو الأقمار الصناعية.</li><li>دعم الجوال والأجهزة اللوحية.</li><li>لم تتم إضافة أي صورة.</li></ul>' .
    '<p>النسخة الاحتياطية: <code>' . h(basename($backupPath)) . '</code></p>' .
    '<p><strong>احذف ملف install_map_controls.php بعد التأكد من عمل التحديث.</strong></p>',
    true
);
