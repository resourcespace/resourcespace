<?php
/**
 * Valencia DAM macOS — HTTPS landing page for internal app deep links.
 *
 * Install: copy this folder to plugins/valencia_app_link/ on the DAM server.
 * Enable plugin under Admin → Plugins (optional; file is also reachable directly).
 *
 * Examples:
 *   https://dam.example.com/plugins/valencia_app_link/link.php?c=123
 *   https://dam.example.com/plugins/valencia_app_link/link.php?r=456
 */

$collection = null;
$resource = null;

if (isset($_GET['c']) && ctype_digit((string) $_GET['c'])) {
    $collection = (int) $_GET['c'];
} elseif (isset($_GET['collection']) && ctype_digit((string) $_GET['collection'])) {
    $collection = (int) $_GET['collection'];
}

if (isset($_GET['r']) && ctype_digit((string) $_GET['r'])) {
    $resource = (int) $_GET['r'];
} elseif (isset($_GET['ref']) && ctype_digit((string) $_GET['ref'])) {
    $resource = (int) $_GET['ref'];
} elseif (isset($_GET['resource']) && ctype_digit((string) $_GET['resource'])) {
    $resource = (int) $_GET['resource'];
}

$appURL = null;
$label = 'Valencia DAM';
$webFallback = null;

if ($collection !== null && $collection > 0) {
    $appURL = 'valenciadam://collection/' . $collection;
    $label = 'Kollektion #' . $collection;
    // Best-effort RS collection view (may vary by install).
    $webFallback = '../../pages/search.php?search=' . rawurlencode('!collection' . $collection);
} elseif ($resource !== null && $resource > 0) {
    $appURL = 'valenciadam://asset/' . $resource;
    $label = 'Asset #' . $resource;
    $webFallback = '../../pages/view.php?ref=' . $resource;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

if ($appURL === null) {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Ungültiger Link</title></head>';
    echo '<body style="font-family:system-ui;padding:2rem"><h1>Ungültiger Link</h1>';
    echo '<p>Es fehlt eine Kollektions- oder Asset-ID.</p></body></html>';
    exit;
}

$appURLAttr = htmlspecialchars($appURL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$labelEsc = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$webEsc = $webFallback !== null ? htmlspecialchars($webFallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $labelEsc; ?> – Valencia DAM</title>
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 2.5rem 1.5rem;
           background: #f4f5f7; color: #1a1a1a; }
    .card { max-width: 420px; margin: 0 auto; background: #fff; border-radius: 12px;
            padding: 1.75rem; box-shadow: 0 8px 28px rgba(0,0,0,.08); }
    h1 { font-size: 1.15rem; margin: 0 0 .5rem; }
    p { margin: 0 0 1rem; color: #555; line-height: 1.45; font-size: .95rem; }
    a.btn { display: inline-block; background: #1a5cff; color: #fff; text-decoration: none;
            padding: .7rem 1.1rem; border-radius: 8px; font-weight: 600; }
    a.btn:hover { background: #0d4ae0; }
    a.sec { display: inline-block; margin-top: .85rem; color: #1a5cff; font-size: .9rem; }
  </style>
  <script>
    // Try opening the macOS app immediately (works after user gesture / from many messengers).
    (function () {
      var target = <?php echo json_encode($appURL, JSON_UNESCAPED_SLASHES); ?>;
      window.setTimeout(function () { window.location.href = target; }, 80);
    })();
  </script>
</head>
<body>
  <div class="card">
    <h1><?php echo $labelEsc; ?></h1>
    <p>Dieser Link öffnet die Valencia DAM Mac-App. Falls nichts passiert, App installieren und den Button tippen.</p>
    <p><a class="btn" href="<?php echo $appURLAttr; ?>">In Valencia DAM öffnen</a></p>
    <?php if ($webEsc !== '') { ?>
      <a class="sec" href="<?php echo $webEsc; ?>">Stattdessen im Browser öffnen</a>
    <?php } ?>
  </div>
</body>
</html>
