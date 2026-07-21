<?php
declare(strict_types=1);

/**
 * مثبت زر ملء الشاشة وتغيير الخريطة الأساس.
 * يوضع في جذر tourism_platform ثم يفتح من المتصفح.
 * لا يتضمن صوراً ولا يعدل قاعدة البيانات.
 */

const INSTALL_KEY = 'MAPFULL-2026-91A7';
const VERSION_TAG = '20260721-1';

$root = __DIR__;
$messages = [];
$error = null;
$installed = false;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $submittedKey = (string)($_POST['install_key'] ?? '');

        if (!hash_equals(INSTALL_KEY, $submittedKey)) {
            throw new RuntimeException('رمز التثبيت غير صحيح.');
        }

        $indexPath = $root . '/index.php';
        $jsDir = $root . '/assets/js';
        $cssDir = $root . '/assets/css';
        $jsPath = $jsDir . '/map-controls.js';
        $cssPath = $cssDir . '/map-controls.css';

        if (!is_file($indexPath)) {
            throw new RuntimeException(
                'لم يتم العثور على index.php. ضع المثبت داخل مجلد tourism_platform.'
            );
        }

        foreach ([$jsDir, $cssDir] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('تعذر إنشاء المجلد: ' . $directory);
            }
        }

        $javascript = <<<'JS'
(function () {
  'use strict';

  if (!window.L || typeof window.L.map !== 'function') {
    console.error('Map controls: Leaflet is not loaded before map-controls.js');
    return;
  }

  const L = window.L;
  const originalMapFactory = L.map;

  function tr(arabic, english) {
    const language = String(
      document.documentElement.lang ||
      window.APP?.lang ||
      'ar'
    ).toLowerCase();

    return language.startsWith('en') ? english : arabic;
  }

  function findCurrentTileLayer(map) {
    let currentLayer = null;

    map.eachLayer(function (layer) {
      if (!currentLayer && layer instanceof L.TileLayer) {
        currentLayer = layer;
      }
    });

    return currentLayer;
  }

  function installBaseLayers(map) {
    const currentLayer = findCurrentTileLayer(map);

    const streets = currentLayer || L.tileLayer(
      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }
    );

    if (!currentLayer) {
      streets.addTo(map);
    }

    const light = L.tileLayer(
      'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
      {
        subdomains: 'abcd',
        maxZoom: 20,
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
      }
    );

    const dark = L.tileLayer(
      'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
      {
        subdomains: 'abcd',
        maxZoom: 20,
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
      }
    );

    const satellite = L.tileLayer(
      'https://server.arcgisonline.com/ArcGIS/rest/services/' +
      'World_Imagery/MapServer/tile/{z}/{y}/{x}',
      {
        maxZoom: 19,
        attribution: 'Tiles &copy; Esri'
      }
    );

    const baseLayers = {};
    baseLayers[tr('الشوارع', 'Streets')] = streets;
    baseLayers[tr('فاتحة', 'Light')] = light;
    baseLayers[tr('داكنة', 'Dark')] = dark;
    baseLayers[tr('أقمار صناعية', 'Satellite')] = satellite;

    L.control.layers(
      baseLayers,
      {},
      {
        position: 'topright',
        collapsed: true
      }
    ).addTo(map);
  }

  function setButtonState(button, active) {
    button.classList.toggle('is-active', active);
    button.textContent = active ? '×' : '⛶';
    button.title = active
      ? tr('إغلاق ملء الشاشة', 'Exit fullscreen')
      : tr('ملء الشاشة', 'Fullscreen');
    button.setAttribute('aria-label', button.title);
  }

  function enterFallback(map, target, button) {
    target.classList.add('tourism-map-fullscreen');
    document.documentElement.classList.add('tourism-map-lock');
    document.body.classList.add('tourism-map-lock');
    setButtonState(button, true);

    window.setTimeout(function () {
      map.invalidateSize();
    }, 180);
  }

  function exitFallback(map, target, button) {
    target.classList.remove('tourism-map-fullscreen');
    document.documentElement.classList.remove('tourism-map-lock');
    document.body.classList.remove('tourism-map-lock');
    setButtonState(button, false);

    window.setTimeout(function () {
      map.invalidateSize();
    }, 180);
  }

  function installFullscreen(map) {
    const mapElement = map.getContainer();
    const target = mapElement.closest('.map-panel') || mapElement;

    const FullscreenControl = L.Control.extend({
      options: {
        position: 'topleft'
      },

      onAdd: function () {
        const wrapper = L.DomUtil.create(
          'div',
          'leaflet-bar tourism-fullscreen-control'
        );

        const button = L.DomUtil.create(
          'button',
          'tourism-fullscreen-button',
          wrapper
        );

        button.type = 'button';
        setButtonState(button, false);

        L.DomEvent.disableClickPropagation(wrapper);
        L.DomEvent.disableScrollPropagation(wrapper);

        L.DomEvent.on(button, 'click', function (event) {
          L.DomEvent.preventDefault(event);
          L.DomEvent.stopPropagation(event);

          const fallbackActive = target.classList.contains(
            'tourism-map-fullscreen'
          );

          const nativeActive = document.fullscreenElement === target;

          if (fallbackActive) {
            exitFallback(map, target, button);
            return;
          }

          if (nativeActive) {
            document.exitFullscreen().catch(function () {
              exitFallback(map, target, button);
            });
            return;
          }

          if (typeof target.requestFullscreen === 'function') {
            target.requestFullscreen()
              .then(function () {
                setButtonState(button, true);
                window.setTimeout(function () {
                  map.invalidateSize();
                }, 180);
              })
              .catch(function () {
                enterFallback(map, target, button);
              });
          } else {
            enterFallback(map, target, button);
          }
        });

        document.addEventListener('fullscreenchange', function () {
          const active = document.fullscreenElement === target;
          setButtonState(button, active);

          if (!active) {
            document.documentElement.classList.remove('tourism-map-lock');
            document.body.classList.remove('tourism-map-lock');
          }

          window.setTimeout(function () {
            map.invalidateSize();
          }, 180);
        });

        document.addEventListener('keydown', function (event) {
          if (
            event.key === 'Escape' &&
            target.classList.contains('tourism-map-fullscreen')
          ) {
            exitFallback(map, target, button);
          }
        });

        return wrapper;
      }
    });

    map.addControl(new FullscreenControl());
  }

  function installControls(map) {
    if (!map || map._tourismControlsInstalled) {
      return;
    }

    const mapElement = map.getContainer();

    if (!mapElement || mapElement.id !== 'map') {
      return;
    }

    map._tourismControlsInstalled = true;

    window.setTimeout(function () {
      installBaseLayers(map);
      installFullscreen(map);
      map.invalidateSize();
    }, 450);
  }

  L.map = function () {
    const map = originalMapFactory.apply(L, arguments);
    installControls(map);
    return map;
  };
})();
JS;

        $css = <<<'CSS'
.tourism-fullscreen-control {
    border: 0 !important;
    box-shadow: 0 3px 14px rgba(15, 23, 42, 0.22) !important;
}

.tourism-fullscreen-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;
    border: 0;
    background: #fff;
    color: #16365f;
    cursor: pointer;
    font-family: Arial, sans-serif;
    font-size: 23px;
    font-weight: 800;
    line-height: 1;
}

.tourism-fullscreen-button:hover,
.tourism-fullscreen-button:focus-visible {
    background: #eef5ff;
    color: #1769d2;
    outline: none;
}

.tourism-fullscreen-button.is-active {
    background: #1769d2;
    color: #fff;
}

.leaflet-control-layers {
    border: 0 !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.2) !important;
}

.leaflet-control-layers-toggle {
    width: 38px !important;
    height: 38px !important;
    background-size: 22px 22px !important;
}

.leaflet-control-layers-expanded {
    min-width: 170px;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.98);
    color: #14294a;
    font-family: inherit;
    font-size: 13px;
}

[dir="rtl"] .leaflet-control-layers-expanded {
    direction: rtl;
    text-align: right;
}

.leaflet-control-layers label {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 7px 0;
    cursor: pointer;
}

.map-panel:fullscreen,
#map:fullscreen {
    display: block !important;
    width: 100vw !important;
    height: 100vh !important;
    min-height: 100vh !important;
    margin: 0 !important;
    background: #fff;
}

.map-panel:fullscreen #map {
    width: 100% !important;
    height: 100% !important;
    min-height: 100vh !important;
}

.tourism-map-fullscreen {
    position: fixed !important;
    inset: 0 !important;
    display: block !important;
    width: 100vw !important;
    max-width: none !important;
    height: 100dvh !important;
    min-height: 100dvh !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: #fff !important;
    z-index: 2147483000 !important;
}

.tourism-map-fullscreen #map,
#map.tourism-map-fullscreen {
    width: 100% !important;
    height: 100% !important;
    min-height: 100dvh !important;
}

html.tourism-map-lock,
body.tourism-map-lock {
    overflow: hidden !important;
}

@media (max-width: 850px) {
    .tourism-fullscreen-button {
        width: 42px;
        height: 42px;
        font-size: 26px;
    }

    .leaflet-control-layers-toggle {
        width: 42px !important;
        height: 42px !important;
    }

    .leaflet-control-layers-expanded {
        max-width: calc(100vw - 36px);
        font-size: 14px;
    }
}
CSS;

        if (file_put_contents($jsPath, $javascript) === false) {
            throw new RuntimeException('تعذر كتابة assets/js/map-controls.js');
        }

        if (file_put_contents($cssPath, $css) === false) {
            throw new RuntimeException('تعذر كتابة assets/css/map-controls.css');
        }

        $index = file_get_contents($indexPath);

        if ($index === false) {
            throw new RuntimeException('تعذر قراءة index.php');
        }

        $backupPath = $indexPath . '.backup-map-' . date('Ymd-His');

        if (!copy($indexPath, $backupPath)) {
            throw new RuntimeException('تعذر إنشاء نسخة احتياطية من index.php');
        }

        $cssTag = "\n<link rel=\"stylesheet\" href=\"<?= e(asset_url('css/map-controls.css')) ?>?v=" . VERSION_TAG . "\">\n";
        $jsTag = "\n<script src=\"<?= e(asset_url('js/map-controls.js')) ?>?v=" . VERSION_TAG . "\"></script>\n";

        if (strpos($index, 'css/map-controls.css') === false) {
            $updated = preg_replace('/<\/head>/i', $cssTag . '</head>', $index, 1, $countCss);

            if (!is_string($updated) || $countCss !== 1) {
                throw new RuntimeException('تعذر إضافة ملف تنسيق الخريطة داخل index.php');
            }

            $index = $updated;
        }

        if (strpos($index, 'js/map-controls.js') === false) {
            $pattern = '/(<script\b[^>]*src=["\'][^"\']*app\.js[^"\']*["\'][^>]*>\s*<\/script>)/i';
            $updated = preg_replace($pattern, $jsTag . '$1', $index, 1, $countJs);

            if (!is_string($updated) || $countJs !== 1) {
                throw new RuntimeException(
                    'تعذر العثور على استدعاء app.js. لم يتم تعديل index.php لتجنب تعطيل الموقع.'
                );
            }

            $index = $updated;
        }

        if (file_put_contents($indexPath, $index) === false) {
            throw new RuntimeException('تعذر حفظ index.php بعد التعديل');
        }

        $messages[] = 'تم إنشاء assets/js/map-controls.js';
        $messages[] = 'تم إنشاء assets/css/map-controls.css';
        $messages[] = 'تم ربط الملفات داخل index.php قبل app.js';
        $messages[] = 'تم إنشاء نسخة احتياطية: ' . basename($backupPath);
        $installed = true;
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
    <title>تثبيت أدوات الخريطة</title>
    <style>
        body{margin:0;background:#eef4f8;color:#102443;font-family:Tahoma,Arial,sans-serif}
        main{width:min(720px,calc(100% - 28px));margin:50px auto;background:#fff;border:1px solid #dce5ef;border-radius:18px;padding:26px;box-shadow:0 16px 45px rgba(15,23,42,.1)}
        h1{margin-top:0;font-size:25px}.note{color:#5d6f85;line-height:1.9}.field{margin:20px 0}label{display:block;margin-bottom:8px;font-weight:700}input{width:100%;box-sizing:border-box;padding:13px;border:1px solid #cbd7e5;border-radius:10px;font-size:16px}button{width:100%;padding:14px;border:0;border-radius:11px;background:#1769d2;color:#fff;font-weight:800;font-size:16px;cursor:pointer}.success,.error{padding:15px;border-radius:12px;line-height:1.8;margin-bottom:18px}.success{background:#eaf8ef;color:#146c36}.error{background:#fff0f0;color:#a61b1b}ul{margin:8px 0}.code{direction:ltr;text-align:left;background:#f3f6fa;border-radius:8px;padding:10px;font-family:Consolas,monospace;overflow:auto}
    </style>
</head>
<body>
<main>
    <h1>تثبيت زر ملء الشاشة وطبقات الخريطة</h1>
    <p class="note">
        يضيف زر <strong>⛶</strong> أعلى يسار الخريطة، ويضيف قائمة تغيير الخريطة
        أعلى يمينها. لا يتضمن صوراً ولا يعدل قاعدة البيانات.
    </p>

    <?php if ($error !== null): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($installed): ?>
        <div class="success">
            <strong>تم التثبيت بنجاح.</strong>
            <ul>
                <?php foreach ($messages as $message): ?>
                    <li><?= h($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <p class="note">افتح الموقع في نافذة خاصة، ثم أظهر الخريطة. سيظهر زر ⛶ أعلى يسارها.</p>
    <?php else: ?>
        <form method="post">
            <div class="field">
                <label for="install_key">رمز التثبيت</label>
                <input id="install_key" name="install_key" required autocomplete="off">
            </div>
            <button type="submit">تثبيت أدوات الخريطة</button>
        </form>
    <?php endif; ?>

    <p class="note">بعد نجاح التثبيت احذف هذا الملف من الخادم.</p>
</main>
</body>
</html>
