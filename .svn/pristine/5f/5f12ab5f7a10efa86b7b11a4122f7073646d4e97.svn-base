<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php";


if (!in_array("openai_gpt" ,$plugins) || !in_array("openai_image_edit", $plugins))
    {
    exit("The OpenAI GPT and OpenAI Image Editing plugins must be enabled and configured.");
    }

// Find image
$ref=getval("ref",0,true);
$access=get_resource_access($ref);
$edit_access=get_edit_access($ref);

if ($access!=0)
    {
    // They shouldn't arrive here as the link wouldn't be available.
    exit("Access denied");
    }

function curlprogress($resource,$download_size, $downloaded, $upload_size, $uploaded)
    {
    // Give an estimate of completion based on the % of upload. There is also the DALL-E 2 processing time but the bulk of the time seems to be the upload due to using PNG for images.
    global $lang;
    if ($uploaded>0)
        {
        $percent=floor(($uploaded/$upload_size)*100);
        $percent*=7;$percent+=10; // It lags behind a lot, after experimentation, this gives a more reasonable estimate of completion time.
        if ($percent>=100)
            {
            set_processing_message($lang["openai_image_edit__completing"]);
            }
        else
            {
            set_processing_message($lang["openai_image_edit__sending"] . " (" . $percent . "%)");
            }
        }
    }
    
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    

    set_processing_message($lang["openai_image_edit__preparing_images"]);

    $maskData = getval('mask','');    // Base64 encoded mask from the frontend
    $mode = getval('mode',''); 
    $prompt = getval('prompt','');

    // Decode the mask data from base64
    list($type, $maskData) = explode(';', $maskData);
    list(, $maskData)      = explode(',', $maskData);
    $maskData = base64_decode($maskData);

    if ($mode=="white" || $mode=="black")
        {
        $mask=imagecreatefromstring($maskData);

        // Get the width and height of the image
        $width = imagesx($mask);
        $height = imagesy($mask);

        // Create a new true color image with the same dimensions
        $newBackground = imagecreatetruecolor($width, $height);

        // Fill the new image with colour
        $shade=255;
        if ($mode=="black") {$shade=0;}
        $fill = imagecolorallocate($newBackground, $shade, $shade, $shade);
        imagefill($newBackground, 0, 0, $fill);

        // Copy the original image onto the white background
        // This will replace transparent areas with white
        imagecopy($newBackground, $mask, 0, 0, 0, 0, $width, $height);

        // Start output buffering to capture the image data in memory
        ob_start();
        imagepng($newBackground);
        $imagedata = ob_get_clean();

        // Free up memory
        imagedestroy($mask);
        imagedestroy($newBackground);

        // Return the image data as JSON with base64 encoding
        header('Content-Type: application/json');
        echo json_encode(["image_base64" => base64_encode($imagedata)]);
        exit();
        }

    if ($mode=="clone")
        {
        $mask=imagecreatefromstring($maskData);

        // Get the width and height of the image
        $width = imagesx($mask);
        $height = imagesy($mask);

        // Create a new true color image with the same dimensions
        $image = imagecreatefromstring($maskData);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Clone parts of the surrounding image by moving it in an ever decreasing box
        for ($offset=300;$offset>0;$offset-=30)
            {
            imagecopy($image, $mask, -$offset, 0, 0, 0, $width, $height);
            imagecopy($image, $mask, 0, -$offset, 0, 0, $width, $height);
            imagecopy($image, $mask, $offset, 0, 0, 0, $width, $height);
            imagecopy($image, $mask, 0, $offset, 0, 0, $width, $height);
            }
        imagecopy($image, $mask, 0, 0, 0, 0, $width, $height);

        // Start output buffering to capture the image data in memory
        ob_start();
        imagepng($image);
        $imagedata = ob_get_clean();

        // Free up memory
        imagedestroy($mask);
        imagedestroy($image);

        // Return the image data as JSON with base64 encoding
        header('Content-Type: application/json');
        echo json_encode(["image_base64" => base64_encode($imagedata)]);
        exit();
        }


    // Prepare the OpenAI API request using multipart/form-data
    if ($mode=="edit")
        {
        $url = 'https://api.openai.com/v1/images/edits';
        $model = "dall-e-2";
        $content_type="multipart/form-data";
        }
    if ($mode=="variation")
        {
        $url = 'https://api.openai.com/v1/images/variations';
        $model = "dall-e-2";
        $content_type="multipart/form-data";
        }
    if ($mode=="generate")
        {
        $url = 'https://api.openai.com/v1/images/generations';
        $model = "dall-e-3";
        $content_type="application/json";
        }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'curlprogress');
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $openai_gpt_api_key",
        "Content-Type: " . $content_type
    ]);


    // Improve the mask by replacing non transparent areas with black. This significantly reduces the time to send the mask to OpenAI as the compression is much better.
    $mask=imagecreatefromstring($maskData);
    // Set blending mode off to preserve transparency
    imagealphablending($mask, false);
    imagesavealpha($mask, true);

    // Get image dimensions
    $width = imagesx($mask);
    $height = imagesy($mask);

    // Loop through each pixel
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            // Get the color and alpha of the current pixel
            $rgba = imagecolorat($mask, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;

            // Set the pixel to black with the same alpha
            $black = imagecolorallocatealpha($mask, 0, 0, 0, $alpha);
            imagesetpixel($mask, $x, $y, $black);
        }
    }

    // Prepare the data array using CURLFile for both image and mask
    $data = [
        'model' => $model,  // Specify model (if applicable)
        'n' => 1,
        'size' => '1024x1024'
    ];
    $tmp_dir = get_temp_dir(false, 'openai_image_edit');

    $tmp_image = $tmp_dir . DIRECTORY_SEPARATOR . "{$userref}_{$ref}_{$mode}.png";
    if (($mode=="edit" || $mode=="variation") && imagepng(imagecreatefromstring($maskData), $tmp_image)) {
        $data['image'] = new CURLFile($tmp_image, 'image/png');
    }

    if ($mode=="edit" || $mode=="generate")
        {
        $data['prompt'] = $prompt;
        }

    $maskDataSimplified = $tmp_dir . DIRECTORY_SEPARATOR . "{$userref}_{$ref}_{$mode}_maskDataSimplified.png";
    if ($mode=="edit" && imagepng($mask, $maskDataSimplified))
        {
        $data['mask'] = new CURLFile($maskDataSimplified, 'image/png');
        }

    if ($mode=="generate")
        {
        $data=json_encode($data);
        }

    // Attach the form data
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    // Execute the request and get the response
    $response = curl_exec($ch);

    // Check for errors in the cURL request
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    } else {
        $json=json_decode($response,true);
        $url=$json["data"][0]["url"] ?? "";
        if ($url!="")
            {
            daily_stat("OpenAI Image Edit", $userref, 1);
            header('Content-Type: application/json');
            echo json_encode(["image_base64"=>base64_encode(file_get_contents($url))]);
            }
        else
            {
            echo $response;
            }

        try_unlink($tmp_image);
        try_unlink($maskDataSimplified);
    }

    curl_close($ch);
    exit();
}

include "../../../include/header.php";
?>

<div class="BasicsBox">
    <?php
    renderBreadcrumbs([
        [
            "title" => $lang["backtoview"],
            "href"  => generateURL($baseurl_short . "pages/view.php", ["ref" => $ref])
        ], 
        [
            "title" => $lang["openai_image_edit__edit_with_ai"],
            "help"  => "plugins/openai_image_edit"
        ]
    ]);
    ?>
</div>
<img id="image" src="get_png.php?ref=<?php echo (int) $ref ?>" alt="" hidden>
<div id="canvas-container" class="canvas-container" style="position: relative;visibility:hidden;">
    <canvas id="canvas"></canvas>
    <canvas id="overlayCanvas" style="position: absolute; top: 0; left: 0; pointer-events: none;"></canvas>
</div>

<div id="toolbox" class="toolbox openai-image-edit" style="visibility:hidden;">
<div id="tools">
<label for="editMode"><?php echo escape($lang["openai_image_edit__mode"]) ?></label><br>
<select id="editMode">
    <option value="edit"><?php echo escape($lang["openai_image_edit__mode_edit"]); ?></option>
    <option value="variation"><?php echo escape($lang["openai_image_edit__mode_variation"]); ?></option>
    <option value="generate"><?php echo escape($lang["openai_image_edit__mode_generate"]); ?></option>
    <option value="white"><?php echo escape($lang["openai_image_edit__mode_white"]); ?></option>
    <option value="black"><?php echo escape($lang["openai_image_edit__mode_black"]); ?></option>
    <option value="clone"><?php echo escape($lang["openai_image_edit__mode_clone"]); ?></option>
</select>
<br><br>
<label for="penSize"><?php echo escape($lang["openai_image_edit__pensize"]); ?></label><br>
<input type="range" id="penSize" min="10" max="200" value="75">
<br><br>
<label for="prompt"><?php echo escape($lang["openai_image_edit__prompt"]); ?></label><br>
<textarea id="prompt" rows="5" required placeholder="Prompt for regeneration">Complete image as appropriate</textarea>
<br>
<button id="clearBtn" onclick="window.location.reload();"><?php echo escape($lang["openai_image_edit__reset"]); ?></button>
<button id="submitBtn"><?php echo escape($lang["openai_image_edit__generate"]); ?></button>
<br><br><br>


<div id="downloadOptions" style="visibility: hidden;">
<label for="downloadType"><?php echo escape($lang["openai_image_edit__exportoptions"]); ?></label><br>
<select id="downloadType">
    <option value="image/jpeg">JPEG</option>
    <option value="image/png">PNG</option>
    <option value="image/webp">WEBP</option>
</select>
<br>
<select id="downloadAction">
    <option value="download"><?php echo escape($lang["openai_image_edit__download"]); ?></option>
<?php if ($edit_access) { ?><option value="alternative"><?php echo escape($lang["openai_image_edit__alternative"]); ?></option><?php } ?>
<?php if (checkperm("c")) { ?><option value="new"><?php echo escape($lang["openai_image_edit__new"]); ?></option><?php } ?>

</select>
<br>
<button id="downloadBtn"><?php echo escape($lang["openai_image_edit__export"]); ?></button>
</div>

</div>
</div>

<script>
CentralSpaceShowProcessing();
submit_url='<?php echo generateURL($baseurl . "/plugins/openai_image_edit/pages/edit.php",[],["ref"=>$ref]); ?>';
alternative_url='<?php echo generateURL($baseurl . "/plugins/openai_image_edit/pages/save_alternative.php",[],["ref"=>$ref]); ?>';
save_new_url='<?php echo generateURL($baseurl . "/plugins/openai_image_edit/pages/save_new.php",[] ,["ref"=>$ref]); ?>';
view_url='<?php echo generateURL($baseurl . "/pages/view.php",[],["ref"=>$ref]); ?>';
view_new_url='<?php echo $baseurl ?>/pages/view.php?ref=';
csrf_pair={<?php echo generateAjaxToken("openai_image_edit"); ?>};
defaultLoadingMessage=<?php echo json_encode($lang["openai_image_edit__preparing_images"]) ?>;
</script>
<script src="../js/edit.js"></script>
<?php
include "../../../include/footer.php";

