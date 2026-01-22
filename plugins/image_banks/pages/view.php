<?php

use ImageBanks\NoProvider;

use function ImageBanks\getProviders;
use function ImageBanks\getProviderSelectInstance;
use function ImageBanks\providersCheckedAndActive;
use function ImageBanks\render_provider_search_result_link;
use function ImageBanks\render_view_metadata_item_narrow;

$rs_root = dirname(__DIR__, 3);
include_once "{$rs_root}/include/boot.php";
include_once "{$rs_root}/include/authenticate.php";

/** @var string Remote Provider ID */
$id = getval('id', '');

[$providers] = getProviders($image_banks_loaded_providers);
$providers_select_list = providersCheckedAndActive($providers);
$image_bank_provider_id = (int) getval('image_bank_provider_id', 0, true);

if ($image_bank_provider_id === 0)
    {
    error_alert($lang['image_banks_provider_id_required'], false);
    exit();
    }
elseif (!array_key_exists($image_bank_provider_id, $providers_select_list))
    {
    error_alert($lang['image_banks_provider_not_found'], false);
    exit();
    }

$provider = getProviderSelectInstance($providers, $image_bank_provider_id);
if ($provider instanceof NoProvider)
    {
    error_alert($lang['image_banks_provider_not_found'], false);
    exit();
    }
$provider_name = $providers_select_list[$image_bank_provider_id] ?? $provider->getName();
$record = $provider->findById($id);
$resource_download_options_table = $provider->getResourceDownloadOptionsTable($id);

$preview_title = $record->getTitle();
if ($preview_title == "") {
    $preview_title = $lang["fullscreenpreview"];
}
include_once "{$rs_root}/include/header.php";
?>
<div class="RecordBox">
    <div class="RecordPanel RecordPanelLarge">
        <div class="RecordHeader">
            <div class="backtoresults">
                <a href="#" onclick="ModalClose();" class="closeLink icon-x" title="<?php echo escape($lang['close']); ?>"></a>
            </div>
            <h1><?php echo escape($record->getTitle()); ?></h1>
        </div>

        <?php render_top_page_error_style($error ?? ''); ?>
        <div class="RecordResource">
            <div id="previewimagewrapper">
                <?php
                render_provider_search_result_link(
                    $record,
                    function() use ($record, $preview_title)
                        {
                        ?>
                        <img
                            id="previewimage"
                            class="Picture"
                            src="<?php echo $record->getPreviewUrl(); ?>"
                            alt="<?php echo escape($preview_title); ?>"
                            galleryimg="no">
                        <?php
                        },
                    [
                        'class' => ['enterLink'],
                        'title' => str_replace('%PROVIDER', $provider_name, $lang["image_banks_view_on_provider_system"]),
                        'force_provider_url' => true,
                    ]
                );
                ?>
            </div>

        <?php
        if ($record->getOriginalFileUrl() !== null)
            {
            ?>
            <div id="RecordDownloadTabContainer" class="RecordDownload">
                <div id="RecordDownloadTabButtons" class="TabBar">
                    <div id="DownloadsTabButton" class="Tab TabSelected">
                        <a role="link" aria-disabled="true">
                            <?php echo escape($lang["resourcetools"]); ?>
                        </a>
                    </div>
                </div>
                <div class="RecordDownloadSpace" id="DownloadsTab">
                    <table id="ResourceDownloadOptions" cellpadding="0" cellspacing="0">
                        <tr id="ResourceDownloadOptionsHeader">
                            <td><?php echo escape($lang["fileinformation"]); ?></td>
                        <?php
                        foreach ($resource_download_options_table['header'] as $column)
                            {
                            ?>
                            <td><?php echo escape($column); ?></td>
                            <?php
                            }
                            ?>
                            <td class="textcenter"><?php echo escape($lang["options"]); ?></td>
                        </tr>
                        <tr class="DownloadDBlend" id="DownloadBox0" style="pointer-events: auto;">
                        <?php
                        foreach ($resource_download_options_table['data'] as $i => $row_val)
                            {
                            if ($i === 0)
                                {
                                ?>
                                <td class="DownloadFileName">
                                    <h2><?php echo escape($row_val); ?></h2>
                                </td>
                                <?php
                                continue;
                                }
                            echo strip_tags_and_attributes($row_val, ['td', 'p'], ['class']);
                            }
                            ?>

                            <td class="DownloadButton">
                                <a 
                                    id="downloadlink"
                                    href="<?php echo escape($record->getOriginalFileUrl()); ?>"
                                    onclick="downloadImageBankFile(this);"><?php echo escape($lang["action-download"]); ?></a>
                            </td>
                        </tr>
                    </table>

                    <div class="RecordTools">
                        <ul id="ResourceToolsContainer">
                            <li>
                                <a href="<?php echo escape($record->getOriginalFileUrl()); ?>"
                                onclick="createNewResource(event, this);">
                                    <i class="icon-copy"></i>&nbsp;<?php echo escape($lang["image_banks_create_new_resource"]); ?>
                                </a>
                            </li>

                        </ul>
                    </div>
                </div>
            </div><!-- End of RecordDownloadTabContainer -->
            <?php
            }
            ?>

            <div id="Panel1" class="ViewPanel">
                <div id="Titles1" class="ViewPanelTitles">
                    <div class="Title Selected" panel="Metadata"><?php echo escape($lang['resourcedetails']); ?></div>
                </div>
            </div>
            <div id="Metadata">
                <div class="NonMetadataProperties">
                    <?php
                    echo render_view_metadata_item_narrow($lang['image_banks_image_bank_source'], $provider_name);
                    echo render_view_metadata_item_narrow($lang['resourceid'], $id);
                    foreach ($provider->getImageNonMetadataProperties($id) as $label => $value)
                        {
                        echo render_view_metadata_item_narrow($label, $value);
                        }
                    ?>
                    <div class="clearerleft"></div>
                </div><!-- End of NonMetadataProperties -->
                <div class="TabbedPanel" id="tab0-2">
                    <div>
                        <?php
                        foreach ($provider->getImageMetadata($id) as $label => $value)
                            {
                            echo render_view_metadata_item_narrow($label, $value);
                            }
                        ?>
                        <div class="clearerleft"></div>
                    </div>
                    <div class="clearerleft"></div>
                </div>
            </div><!-- End of Metadata -->
        </div>
    </div>
</div>
<?php
include_once dirname(__DIR__) . '/include/image_banks_javascript.php';
include_once "{$rs_root}/include/footer.php";
