<?php

function HookDirect_LinkResource_ShareAdditionalshares() {
    global $lang, $api_resource_path_expiry_hours,$ref,$userref;

    $urls="";

    $resources=get_resource_all_image_sizes($ref);
    foreach ($resources as $resource)
    {
    $url=$resource["url"];
    $accesskey = generate_temp_download_key($userref, $ref, $resource["size_code"]=="original"?"":$resource["size_code"]);
    if ($accesskey !== "") {
            $url.= "&access_key={$accesskey}";
    }
    $urls .= '<tr><td>'
        . '<a style=\'display:block;width:300px;\' target="_blank" href="' . sanitise_url($url) . '">'
        . strtoupper(escape($resource["size_code"]))
        . " ({$resource["width"]}x{$resource["height"]}, {$resource["filesize"]})"
        . '</a> </td><td>'
        . '<button type="button" class="copy-url" data-url="'
        . sanitise_url($url)
        . '">' . escape($lang["direct_link_copy"]) . '</button>'
        . '</td></tr>';
        
    }
    ?>
    <h2><?php echo escape($lang["direct_link_urls"]); ?></h2>
    <p><?php echo escape($lang["direct_link_expires"]) . offset_user_local_timezone("+{$api_resource_path_expiry_hours} hours",'j F Y \a\t H:i'); ?></p>

    <table><?php echo $urls; ?></table> <?php /* Already sanitised as URLs from above function */ ?>

    <script>
    document.addEventListener("click", async function (event) {
        const button = event.target.closest(".copy-url");

        if (!button) {
            return;
        }

        try {
            await navigator.clipboard.writeText(button.dataset.url);

            const originalText = button.textContent;
            button.textContent = "<?php echo escape($lang["direct_link_copied"]); ?>";

            setTimeout(() => {
                button.textContent = originalText;
            }, 1500);
        } catch (error) {
            alert("<?php echo escape($lang["direct_link_copy_error"]); ?>");
        }
    });
    </script>
    <?php
}
