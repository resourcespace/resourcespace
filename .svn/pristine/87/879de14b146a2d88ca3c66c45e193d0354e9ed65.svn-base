<?php

include_once "../../../include/boot.php";
include_once "../../../include/authenticate.php";
include_once "../../../include/header.php";

$ref        = getval("ref",0,true);
$search     = getval("search","");
$offset     = getval("offset",0,true);
$order_by   = getval("order_by","");
$sort       = getval("sort","");
$k          = getval("k","");

// Test for presence of rsvg-convert and warn if not installed.
$rsvg_version = join("\n", run_external("rsvg-convert -v"));
$rsvg_installed = (strpos($rsvg_version, "version") !== false);

$resource_data = get_resource_data($ref);

// Load SVG source
$svg_path = get_resource_path($ref, true, "", false, "svg");
$svg_source = file_get_contents($svg_path);

# Fetch parameters
$e = 0;
$params = array();
while ($s = strpos($svg_source, "[", $e)) {
    $e = strpos($svg_source, "]", $s);
    if ($e === false) {
        break;
    }
    $params[] = substr($svg_source, $s + 1, $e - $s - 1);
}
?>

<div class="BasicsBox">
    <?php if (count($params) == 0) {
        exit(escape($lang["stencilvg-no_parameters_found"]));
    } ?>

    <div class="stencilvg-svgouter">
        <p>
            <a href="#" onClick="ZoomSVG(10); return false;">
                <i class="icon-zoom-in lucide-xl"></i>
            </a>
            <a href="#" onClick="ZoomSVG(-10); return false;">
                <i class="icon-zoom-out lucide-xl"></i>
            </a>
        </p>
        <div class="stencilvg-svg" id="svgpreview"></div>
    </div>
    <h1><?php echo escape($lang["stencilvg-go"]); ?></h1>

    <?php foreach ($params as $param) { ?>
        <p>
            <?php echo escape($param); ?>
            <br />
            <input type="text"
                class="stdwidth"
                name="<?php echo base64_encode($param); ?>"
                id="<?php echo base64_encode($param); ?>"
                value="<?php echo escape($param); ?>"
                onKeyUp="UpdateSVG();"
                onChange="UpdateSVG();"
            />
        </p>
    <?php } ?>

    <p>
        <input type="radio" name="filetype" value="SVG" id="filetype-svg" checked />
        <label for="filetype-svg">SVG</label>
        <?php foreach ($stencilvg_rsvg_supported_output_formats as $format) { ?>
            <input type="radio"
                name="filetype"
                value="<?php echo escape($format); ?>"
                id="filetype-<?php echo escape($format); ?>"
                <?php if (!$rsvg_installed) { ?>
                    disabled
                <?php } ?>
            />
            <label for="filetype-<?php echo escape($format); ?>"><?php echo escape(strtoupper($format)); ?></label>
        <?php } ?>
    </p>

    <p>
        <button onclick="PrintSVG();"><?php echo escape($lang["stencilvg-print"]); ?></button>
        <button onclick="DownloadSVG(0);"><?php echo escape($lang["stencilvg-download"]); ?></button>
        <button onclick="DownloadSVG(1);"><?php echo escape($lang["stencilvg-save_as_new_resource"]); ?></button>
    </p>

    <script>
    var svg_source = <?php echo json_encode($svg_source); ?>;
    var svg_new;
    var svg_zoom=70;

    function UpdateSVG() {
        svg_new = svg_source;

        <?php
        // For each parameter, find and replace it in the SVG source.
        foreach ($params as $param) { ?>
            var value = document.getElementById('<?php echo base64_encode($param); ?>').value;
            value = value.split("__").join("<br />");
            svg_new = svg_new.split('[<?php echo escape($param); ?>]').join(value);
        <?php } ?>
        console.log(svg_new);
        document.getElementById('svgpreview').innerHTML = svg_new;
    }

    function PrintSVG() {
        var printsvg = window.open();
        printsvg.document.body.innerHTML = svg_new;
        printsvg.print();
        printsvg.close();
    }

    function ZoomSVG(zoom) {
        svg_zoom += zoom;
        if (svg_zoom > 100) {
            svg_zoom = 100;
        }

        if (svg_zoom < 10) {
            svg_zoom = 10;
        }
        jQuery("svg").css('width', svg_zoom + '%');
    }

    function DownloadSVG(save) {
        document.getElementById('downloadsvg_save').value = save;
        document.getElementById('downloadsvg_svg').value = svg_new;
        document.getElementById('downloadsvg_filename').value = <?php echo json_encode(safe_file_name($resource_data["field" . $view_title_field]) . ".svg"); ?>;
        document.getElementById('downloadsvg_filetype').value = jQuery('input[name="filetype"]:checked').val();
        document.getElementById('downloadsvg').submit();
    }

    // Start up
    UpdateSVG();
    </script>

    <form id="downloadsvg" action="download_svg.php" method="post">
        <?php generateFormToken("downloadsvg"); ?>
        <input type="hidden" id="downloadsvg_filename" name="filename" />
        <input type="hidden" id="downloadsvg_svg" name="svg" />
        <input type="hidden" id="downloadsvg_filetype" name="filetype" />
        <input type="hidden" id="downloadsvg_save" name="save" />
    </form>

    <?php
    // Display RSVG status.
    if (!$rsvg_installed) {
        echo "<p>" . escape($lang["stencilvg-rsvg-not-installed"]) . "</p>";
    } ?>

</div> <!-- End of BasicsBox -->
<?php
include_once "../../../include/footer.php";
