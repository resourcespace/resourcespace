<?php
include '../include/boot.php';

$k   = getval('k', '');
$ref = getval('ref', '', true);

// External access support (authenticate only if no key provided, or if invalid access key provided)
if ('' == $k || !check_access_key($ref, $k)) {
    include '../include/authenticate.php';
}

include_once '../include/pdf_functions.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;
ob_start();

$resource = get_resource_data($ref);

// fetch the current search (for finding similar matches)
$search   = getval('search', '');
$order_by = getval('order_by', 'relevance');
$offset   = getval('offset', 0, true);
$restypes = getval('restypes', '');

if (strpos($search, '!') !== false) {
    $restypes = '';
}

$archive      = getval('archive', 0, true);
$default_sort_direction = 'DESC';

if (substr($order_by, 0, 5) == 'field') {
    $default_sort_direction = 'ASC';
}

$sort               = getval('sort', $default_sort_direction);
$metadata           = get_resource_field_data($ref, false, true, null, getval('k', '') != '');
$filename           = $ref;
$download           = getval('download', '') != '';
$download_file_type = getval('fileType_option', '');
$language           = getval('language', 'en');
$language           = resolve_pdf_language();
$curpos             = getval("curpos", "");
$modal              = (getval("modal", "") == "true");
$data_only          = 'true' === trim(getval('data_only', ''));
$pdf_template       = getval('pdf_template', '');

$urlparams = array(
    'resource' => $ref,
    'ref' => $ref,
    'search' => $search,
    'order_by' => $order_by,
    'offset' => $offset,
    'restypes' => $restypes,
    'archive' => $archive,
    'default_sort_direction' => $default_sort_direction,
    'sort' => $sort,
    'curpos' => $curpos,
    "modal" => ($modal ? "true" : "")
);

// Process text file download
if ($download && $download_file_type == 'text') {
    header("Content-type: application/octet-stream");
    header("Content-disposition: attachment; filename=" . $lang["metadata"] . "_" . $filename . ".txt");

    foreach ($metadata as $metadata_entry) { // Go through each entry
        if (!empty($metadata_entry['value'])) {
            // This is the field title - the function got this by joining to the resource_type_field in the sql query
            echo $metadata_entry['title'] . ': ';
            // This is the value for the field from the resource_data table
            echo tidylist(i18n_get_translated($metadata_entry['value'])) . "\r\n";
        }
    }

    ob_flush();
    exit();
}

// Process PDF file download
if ($download && $download_file_type === 'pdf') {
    $logo_src_path = $baseurl . '/gfx/titles/logo.png';
    $PDF_filename = $lang['metadata'] . '_' . $filename . '.pdf';
    $content = '';
    ?>
    <!-- Start structure of PDF file in HTML -->
    <page backtop="25mm" backbottom="10mm" backleft="5mm" backright="5mm">
        <page_header>
            <table cellspacing="0" style="width: 95%;margin-left:15px;">
                <tr>
                    <td style="width: 75%;"><h1><?php echo $applicationname; ?></h1></td>
                    <td style="width: 25%;" align=right>
                        <img style="height: 50px; max-width: 100%" src="<?php echo $logo_src_path; ?>" alt="Logo" >
                    </td>
                </tr>
            </table>
        </page_header>
        <page_footer>
            <table style="width: 100%;">
                <tr>
                    <td style="text-align: left; width: 33%"><?php echo $PDF_filename; ?></td>
                    <td style="text-align: center; width: 34%">page [[page_cu]]/[[page_nb]]</td>
                    <td style="text-align: right; width: 33%"><?php echo date('d/m/Y'); ?></td>
                </tr>
            </table>
        </page_footer>
        <!-- Real content starts here -->
        <h2><?php echo escape($lang['metadata-pdf-title'] . ' ' . $ref); ?></h2>
        <table style="width: 90%;" align="center" cellspacing="15">
            <tbody>
                <?php
                foreach ($metadata as $metadata_entry) {
                    $metadatavalue = trim(tidylist(i18n_get_translated($metadata_entry['value'])));
                    if (!empty($metadatavalue)) {
                        ?>
                        <tr>
                            <td valign="top" style="text-align: left;"><b><?php echo $metadata_entry['title']; ?></b></td>
                            <td style="width: 2%;"></td>
                            <td style="width: 70%; text-align: left;"><?php echo $metadatavalue; ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </page>
    <!-- End of structure of PDF file in HTML -->
    <?php
    $content = ob_get_clean();
    $html2pdf = new Html2Pdf('P', 'A4', $language);
    $html2pdf->WriteHTML($content);
    $html2pdf->Output($PDF_filename);
}

/*
Data only PDFs generation
These PDFs will be based on templates found on the server which will be interpreted and then rendered
*/
if ($download && $data_only) {
    $pdf_template_path = get_pdf_template_path($resource['resource_type'], $pdf_template);
    $PDF_filename      = 'data_only_resource_' . $ref . '.pdf';

    // Go through fields and decide which ones we add to the template
    $placeholders = array(
        'resource_type_name' => get_resource_type_name($resource['resource_type'])
    );

    foreach ($metadata as $metadata_field) {
        $metadata_field_value = trim(tidylist(i18n_get_translated($metadata_field['value'])));

        // Skip if empty
        if ('' == $metadata_field_value) {
            continue;
        }

        $placeholders['metadatafield-' . $metadata_field['ref'] . ':title'] = $metadata_field['title'];
        $placeholders['metadatafield-' . $metadata_field['ref'] . ':value'] = $metadata_field_value;
    }

    if (!generate_pdf($pdf_template_path, $PDF_filename, $placeholders)) {
        $GLOBALs["onload_message"] = array(
            'title' => 'Warning',
            'message' => 'ResourceSpace could not generate PDF for data only type!'
        );
    }
}

include "../include/header.php";
?>

<body>
    <div class="BasicsBox">
        <?php
        if (getval("context", false) == 'Modal') {
            $previous_page_modal = true;
        } else {
            $previous_page_modal = false;
        }

        if (!$modal) { ?>
            <p>
                <a href="<?php echo generateURL($baseurl_short . "pages/view.php", $urlparams);?>" onclick="return CentralSpaceLoad(this,true);">
                    <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
                </a>
            </p>
        <?php } elseif ($previous_page_modal) { ?>
            <p>
                <a href="<?php echo generateURL($baseurl_short . "pages/view.php", $urlparams);?>" onclick="return ModalLoad(this,true);">
                    <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
                </a>
            </p>
        <?php } ?>

        <h1><?php echo escape($lang["downloadingmetadata"]); ?></h1>

        <p>
            <?php
            echo escape($lang["file-contains-metadata"]);
            render_help_link("user/resource-tools");
            ?>
        </p>

        <form id="metadataDownloadForm" name="metadataDownloadForm" method=post action="<?php echo $baseurl_short; ?>pages/metadata_download.php">
            <?php generateFormToken("metadataDownloadForm"); ?>
            <input name="ref" type="hidden" value="<?php echo $ref; ?>">
            <div class="Question" id="fileType">
                <label for="fileType_option">Download file type</label>
                <select id="fileType_option" class="stdwidth" name="fileType_option">
                    <option value="">Please select...</option>
                    <option value="text">Text</option>
                    <option value="pdf">PDF</option>
                </select>
                <div class="clearerleft"></div>
            </div>

            <div class="QuestionSubmit">
                <input name="download" type="submit" value="<?php echo escape($lang['download']); ?>" />
            </div>
        </form>
    </div>
</body>

<?php
include "../include/footer.php";
?>
