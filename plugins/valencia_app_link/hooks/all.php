<?php
/**
 * When a DAM page is opened with ?c= or ?r= (standard ResourceSpace links),
 * offer / try to open the Valencia DAM macOS app.
 */
function HookValencia_app_linkAllAdditionalheaderjs()
{
    $collection = null;
    $resource = null;

    if (isset($_GET['c']) && ctype_digit((string) $_GET['c']) && (int) $_GET['c'] > 0) {
        $collection = (int) $_GET['c'];
    } elseif (isset($_GET['collection']) && ctype_digit((string) $_GET['collection']) && (int) $_GET['collection'] > 0) {
        $collection = (int) $_GET['collection'];
    }

    if (isset($_GET['r']) && ctype_digit((string) $_GET['r']) && (int) $_GET['r'] > 0) {
        $resource = (int) $_GET['r'];
    } elseif (isset($_GET['ref']) && ctype_digit((string) $_GET['ref']) && (int) $_GET['ref'] > 0) {
        $resource = (int) $_GET['ref'];
    } elseif (isset($_GET['resource']) && ctype_digit((string) $_GET['resource']) && (int) $_GET['resource'] > 0) {
        $resource = (int) $_GET['resource'];
    }

    if ($collection === null && $resource === null) {
        return;
    }

    if ($collection !== null) {
        $appURL = 'valenciadam://collection/' . $collection;
        $label = 'Kollektion #' . $collection;
    } else {
        $appURL = 'valenciadam://asset/' . $resource;
        $label = 'Asset #' . $resource;
    }

    $appJSON = json_encode($appURL, JSON_UNESCAPED_SLASHES);
    $labelEsc = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $appAttr = htmlspecialchars($appURL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ?>
<style>
#vd-app-link-banner {
  position: sticky; top: 0; z-index: 10000;
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 10px 16px; background: #1a1a1a; color: #fff;
  font: 14px/1.3 system-ui, -apple-system, sans-serif;
}
#vd-app-link-banner a.vd-open {
  background: #3b82f6; color: #fff; text-decoration: none;
  padding: 6px 12px; border-radius: 6px; font-weight: 600; white-space: nowrap;
}
#vd-app-link-banner a.vd-open:hover { background: #2563eb; }
#vd-app-link-banner button.vd-dismiss {
  background: transparent; border: 0; color: #aaa; cursor: pointer; font-size: 18px; line-height: 1;
}
</style>
<script>
(function () {
  var appURL = <?php echo $appJSON; ?>;
  var key = "vdAppLinkDismiss:" + appURL;
  try {
    if (sessionStorage.getItem(key) === "1") { return; }
  } catch (e) {}

  function openApp() {
    window.location.href = appURL;
  }

  // Clicking the https link in Slack/Mail counts as a gesture — try opening the app.
  setTimeout(openApp, 120);

  function showBanner() {
    if (document.getElementById("vd-app-link-banner")) { return; }
    var bar = document.createElement("div");
    bar.id = "vd-app-link-banner";
    bar.innerHTML =
      "<span>In der Valencia DAM Mac-App öffnen: <strong><?php echo $labelEsc; ?></strong></span>" +
      "<span style=\"display:flex;align-items:center;gap:10px\">" +
      "<a class=\"vd-open\" href=\"<?php echo $appAttr; ?>\">App öffnen</a>" +
      "<button type=\"button\" class=\"vd-dismiss\" aria-label=\"Schliessen\">×</button>" +
      "</span>";
    document.body.insertBefore(bar, document.body.firstChild);
    bar.querySelector(".vd-dismiss").addEventListener("click", function () {
      try { sessionStorage.setItem(key, "1"); } catch (e) {}
      bar.remove();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", showBanner);
  } else {
    showBanner();
  }
})();
</script>
    <?php
}
