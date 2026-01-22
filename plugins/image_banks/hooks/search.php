<?php
function HookImage_banksSearchSearchaftersearchcookie()
    {
    $search_image_banks = filter_var(getval("search_image_banks", false), FILTER_VALIDATE_BOOLEAN);
    $image_bank_provider_id = getval("image_bank_provider_id", 0, true);
    $saved_offset = getval("saved_offset", 0, true);
    $offset = getval("offset", $saved_offset, true);
    $posting = filter_var(getval("posting", false), FILTER_VALIDATE_BOOLEAN);

    rs_setcookie("image_bank_provider_id", $image_bank_provider_id,0,"","",false,false);

    // Allow pixabay search using saved params using the Search results link that appears in the header links
    // This is not passing anything in the params other than ajax argument
    parse_str($_SERVER['QUERY_STRING'], $qs_params);
    debug("image_banks_search_hook: \$_SERVER['QUERY_STRING'] = {$_SERVER['QUERY_STRING']}");
    if(
        $image_bank_provider_id > 0
        && (
                // Search results (for image bank)
                count($qs_params) == 2 && !isset($qs_params['search'])
                // User searched external IB providers from simple search
                || $posting
                // Normal search in IB (e.g changing per page or going through pages)
                || (count($qs_params) > 2 && $search_image_banks)
            )
    )
        {
        $search_image_banks = true;
        }

    debug("image_banks_search_hook: ");
    debug("image_banks_search_hook: \$offset = {$offset}");
    debug("image_banks_search_hook: \$image_bank_provider_id = {$image_bank_provider_id}");
    debug("image_banks_search_hook: count(\$qs_params) == 2 ==> " . (count($qs_params) == 2 ? 'true': 'false'));
    debug("image_banks_search_hook: \$posting = " . ($posting ? "true" : "false"));
    debug("image_banks_search_hook: (count(\$qs_params) > 2 && \$search_image_banks) ==> " . ((count($qs_params) > 2 && $search_image_banks) ? 'true': 'false'));
    debug("image_banks_search_hook: ===============================");
    debug("image_banks_search_hook: \$search_image_banks = " . ($search_image_banks ? "true" : "false"));

    if(!$search_image_banks)
        {
        return;
        }

    global $baseurl_short, $searchparams;

    redirect(
        generateURL(
            "{$baseurl_short}plugins/image_banks/pages/search.php",
            $searchparams,
            array(
                "image_bank_provider_id" => $image_bank_provider_id,
            )
        )
    );
    }