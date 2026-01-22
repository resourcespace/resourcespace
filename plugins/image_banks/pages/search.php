<?php

use ImageBanks\NoProvider;

use function ImageBanks\getProviders;
use function ImageBanks\getProviderSelectInstance;
use function ImageBanks\providersCheckedAndActive;
use function ImageBanks\render_provider_search_result_link;

$rs_root = dirname(__DIR__, 3);
include_once "{$rs_root}/include/boot.php";
include_once "{$rs_root}/include/authenticate.php";

$search = getval("search", "");
$image_bank_provider_id = (int) getval("image_bank_provider_id", 0, true);
$per_page = (int) getval("per_page", get_per_page_cookie(), false, 'is_positive_int_loose');
$order_by = getval('order_by', '', false);
$search_params = array(
    "search"                 => $search,
    "image_bank_provider_id" => $image_bank_provider_id,
    "search_image_banks"     => true,
    "order_by"               => $order_by,
    "per_page"               => $per_page
);

// Paging functionality
$url = generateURL("{$baseurl_short}pages/search.php", $search_params);
$offset = (int) getval("offset", 0, false, 'is_positive_int_loose');
set_per_page_cookie($per_page);
$curpage = floor($offset / $per_page) + 1;
// End of Paging functionality


[$providers] = getProviders($image_banks_loaded_providers);
$providers_select_list = providersCheckedAndActive($providers);

$provider = new NoProvider($lang, get_temp_dir(false, 'ImageBanks-NoProvider'));
$results = $provider->search($search, $per_page, $curpage);
if($image_bank_provider_id === 0)
    {
    $results->setError($lang['image_banks_provider_id_required']);
    }
elseif(!array_key_exists($image_bank_provider_id, $providers_select_list))
    {
    $results->setError($lang['image_banks_provider_not_found']);
    }

// Try selecting a Provider (or its instance) and perform the requested search
if ($results->getError() === '' && $providers_select_list !== [])
    {
    $provider = getProviderSelectInstance($providers, $image_bank_provider_id);
    $provider_name = $providers_select_list[$image_bank_provider_id] ?? $provider->getName();
    $results = $provider->search($search, $per_page, $curpage, $search_params);

    // On the off chance something else went terribly wrong (ie. code bug), let user know we couldn't find the Provider
    if ($provider instanceof NoProvider)
        {
        $results->setError($lang['image_banks_provider_not_found']);
        }
    }

$results_error = $results->getError();
$results_warning = $results->getWarning();
$totalpages = ceil($results->total / $per_page);

include_once "{$rs_root}/include/header.php";
?>
<div class="BasicsBox">
    <div class="TopInpageNav">
        <div class="TopInpageNavLeft">
            <div id="SearchResultFound" class="InpageNavLeftBlock">
                <span class="Selected"><?php echo number_format($results->total); ?></span> <?php echo escape($lang["youfoundresults"]); ?>
            </div>
            <div class="InpageNavLeftBlock AlignLeftBlockText">
                <span class="Selected"><?php echo escape($lang["image_banks_image_bank"]); ?>: </span><?php echo escape($provider_name); ?>
            </div>
            <?php
            $sort_options = $provider->getSortOptions();
            if ($sort_options !== []) {
            ?>
            <div id="searchSortOrderContainer" class="InpageNavLeftBlock ">
                <select
                    name="order_by"
                    onchange="CentralSpaceLoad(this.value, true);"
                    aria-label="<?php echo escape($lang["sortorder"]); ?>"
                >
                    <?php
                    foreach ($sort_options as $sort_by => $name) {
                        echo render_dropdown_option(
                            generateURL("{$baseurl_short}pages/search.php", $search_params, ['order_by' => $sort_by]),
                            $name,
                            [],
                            $order_by === $sort_by ? ' selected' : ''
                        );
                    }
                    ?>
                </select>
            </div>
            <?php
            }
            ?>
            <div class="InpageNavLeftBlock">
                <select
                    name="per_page"
                    onchange="CentralSpaceLoad(this.value, true);"
                    aria-label="<?php echo escape($lang['perpage']); ?>"
                >
                    <?php
                    foreach ($results_display_array as $results_display_per_page) {
                        echo render_dropdown_option(
                            generateURL(
                                "{$baseurl_short}pages/search.php",
                                $search_params,
                                ['per_page' => $results_display_per_page]
                            ),
                            str_replace('?', $results_display_per_page, $lang['perpage_option']),
                            [],
                            $results_display_per_page === $per_page ? ' selected' : ''
                        );
                    }
                    ?>
                </select>
            </div>
            <?php
            if($results_warning !== "")
                {
                ?>
                <div class="InpageNavLeftBlock AlignLeftBlockText WarningBox">
                    <span class="Selected RedText"><?php echo escape($lang["image_banks_warning"]); ?></span><span><?php echo escape($results_warning); ?></span>
                </div>
                <?php
                }
                ?>
        </div>
        <div class="TopInpageNavRight">
            <?php pager(false); ?>
        </div>
        <div class="clearerleft"></div>
    </div>
</div>
<div id="CentralSpaceResources">
<?php
if($results_error !== "")
    {
    ?>
    <div id="CentralSpaceResources">
        <div class="BasicsBox"> 
            <div class="NoFind">
                <p><?php echo escape($lang["searchnomatches"]); ?></p>
                <p><?php echo escape($results_error); ?></p>
            </div>
        </div>
    </div>
    <?php
    }


foreach($results as $result)
    {
    $title = $result->getTitle();
    $image_data = array(
        "ref"          => $result->getId(),
        "thumb_width"  => $result->getPreviewWidth(),
        "thumb_height" => $result->getPreviewHeight(),
        "field{$view_title_field}" => $title,
    );

    $title_link_text  = function() use ($title, $search_results_title_trim)
        {
        echo tidy_trim(tidylist(strip_tags_and_attributes($title)), $search_results_title_trim);
        };
    ?>
    <div class="ResourcePanel ImageBanksResourcePanel">
        <?php
        render_provider_search_result_link(
            $result,
            fn() => render_resource_image($image_data, $result->getPreviewUrl(), "thumbs"),
            [
                'class' => ['ImageWrapper'],
                'title' => $title,
            ]
        );
        ?>
        <div class="ResourcePanelInfo">
            <?php render_provider_search_result_link($result, $title_link_text, ['title' => $title]); ?>
        </div>
        <div class="clearer"></div>

        <?php if ($result->getOriginalFileUrl()!="") { ?>
        <div class="ResourcePanelIcons">
            <a href="<?php echo escape($result->getOriginalFileUrl()); ?>"
               class="icon-download"
               title="Download resource"
               data-id="<?php echo escape($result->getId()); ?>"
               onclick="downloadImageBankFile(this);"></a>

        <?php
        if(checkperm("c") || checkperm("d"))
            {
            ?>
            <a href="<?php echo escape($result->getOriginalFileUrl()); ?>"
               class="icon-copy"
               title="<?php echo escape($lang["image_banks_create_new_resource"]); ?>"
               onclick="createNewResource(event, this);"></a>
            <?php
            }
            ?>
            <div class="clearer"></div>
        </div>
        <?php } ?>
    </div>
    <?php
    }
    ?>
</div>
<?php
include_once dirname(__DIR__) . '/include/image_banks_javascript.php';
include_once "{$rs_root}/include/footer.php";
