<?php
include __DIR__ . "/../../../include/boot.php";
include __DIR__ . "/../../../include/authenticate.php";

$is_admin = checkperm("t");
$search = getval("search", "");

if ($search != "") {
    redirect(generateURL("/pages/search.php", ["search" => "!clipsearch " . $search]));
}

$clip_image_base64 = getval("clip_image_base64", "");

if ($clip_image_base64 != "") {
    // Search by image. First get the image.

    $binary = base64_decode($clip_image_base64);
    $tmpfile = tempnam(sys_get_temp_dir(), 'clipimg_') . '.jpg';
    file_put_contents($tmpfile, $binary);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $clip_service_url . "/vector");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'db' => $mysql_db,
        'image' => new CURLFile($tmpfile, 'image/jpeg', 'clip.jpg')
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    unlink($tmpfile);

    if ($http_code != 200) {
        echo "CLIP vector request failed (HTTP $http_code)\n";
        exit;
    }

    $vector = json_decode($response, true);
    $vector = array_map('floatval', $vector); // ensure float values
    $blob = pack('f*', ...$vector);

    // Add this to the vectors list as a user generated vector.
    ps_query(
        "INSERT INTO resource_clip_vector (user, vector_blob, is_text) VALUES (?, ?, false)",
        ['i', $userref, 's', $blob]
    ); // Note the blob must be inserted as 's' type as ps_query() does not correctly handle 'b' yet (send_long_data() is needed)
    $record = sql_insert_id();
    redirect(generateURL($baseurl . "/pages/search.php", ["search" => "!clipspecific" . $record]));
}

include __DIR__ . "/../../../include/header.php";
?>

<div class="BasicsBox BasicsBoxPadded">
    <h1><?php echo escape($lang["clip-ai-smart-search"]); ?></h1>

    <div class="BasicsBoxCard">
        <form method=post id="clipformnatural" action="<?php echo $baseurl ?>/plugins/clip/pages/search.php" onsubmit="CentralSpacePost(this);return false;">
            <?php generateFormToken("clipform"); ?>   
            <h2><?php echo escape($lang["clip-natural-language-search"]); ?></h2>
            <p><?php echo escape($lang["clip-natural-language-search-help"]); ?></p>
            <input type="text" name="search">&nbsp;<input type="submit" name="search" value="<?php echo escape($lang["searchbutton"]); ?>"> 
        </form>
    </div>

    <div class="BasicsBoxCard">
        <form method=post id="clipform" action="<?php echo $baseurl ?>/plugins/clip/pages/search.php" onsubmit="CentralSpacePost(this);return false;">
            <?php generateFormToken("clipform"); ?>   
            <h2><?php echo escape($lang["clip-search-upload-image"]); ?></h2>
            <input type="file" id="clip-upload" accept="image/*" required> <!-- no 'name' - intentionally, so it does not get uploaded (the rescaled version alone does) -->
            <input type="hidden" name="clip_image_base64" id="clip-image-hidden">
        </form>
    </div>

    <script>
        document.getElementById('clip-upload').addEventListener('change', function (event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            const img = new Image();

            reader.onload = function (e) {
                img.src = e.target.result;
            };

            img.onload = function () {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const size = 256;

                canvas.width = size;
                canvas.height = size;

                // Aspect-ratio aware centre crop
                const aspect = img.width / img.height;
                let sx, sy, sw, sh;

                if (aspect > 1) {
                    sw = img.height;
                    sh = img.height;
                    sx = (img.width - sw) / 2;
                    sy = 0;
                } else {
                    sw = img.width;
                    sh = img.width;
                    sx = 0;
                    sy = (img.height - sh) / 2;
                }

                ctx.drawImage(img, sx, sy, sw, sh, 0, 0, size, size);

                const dataUrl = canvas.toDataURL("image/jpeg", 0.9);
                const base64 = dataUrl.split(',')[1];

                document.getElementById('clip-image-hidden').value = base64;

                // Auto-submit the form
                document.getElementById('clipform').submit();
            };

            reader.readAsDataURL(file);
        });
    </script>

    <?php
    if (checkperm("a") && $clip_enable_full_duplicate_search) {
        $duplicate_url = generateURL("{$baseurl}/pages/search.php", array("search" => "!clipduplicates"));
        ?>
        <div class="BasicsBoxCard">
            <h2><?php echo escape($lang["clip-duplicate-images"]); ?></h2>
            <a href="<?php echo $duplicate_url ?>" onclick="return CentralSpaceLoad(this,true);">
                <i class="icon-search"></i>&nbsp;<?php echo escape($lang["clip-duplicate-images-all"]); ?>
            </a>
        </div>
        <?php
    } ?>
</div>

<?php
include __DIR__ . "/../../../include/footer.php";
