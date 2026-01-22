<?php
include "../../../include/boot.php";
include_once "../../../include/authenticate.php";

$ref = getval("ref", "");
$resource = getval("resource", "");

# Check access
if (!consentmanager_check_write($resource)) {
    exit("Access denied");
} # Should never arrive at this page without edit access

$url_params = array(
    'ref'        => $resource,
    'search'     => getval('search', ''),
    'order_by'   => getval('order_by', ''),
    'collection' => getval('collection', ''),
    'offset'     => getval('offset', 0),
    'restypes'   => getval('restypes', ''),
    'archive'    => getval('archive', '')
);

$redirect_url = generateURL($baseurl_short . "/pages/view.php", $url_params);

if (getval("submitted", "") != "" && enforcePostRequest(false)) {
    consentmanager_unlink_consent($ref, $resource);
    redirect($redirect_url);
}

include "../../../include/header.php";
?>

<div class="BasicsBox">
    <p>
        <a href="<?php echo $redirect_url ?>"  onClick="return CentralSpaceLoad(this,true);">
            <?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresourceview"]); ?>
        </a>
    </p>

    <h1><?php echo escape($lang["unlink_consent"]); ?></h1>

    <form method="post" action="<?php echo $baseurl_short?>plugins/consentmanager/pages/unlink.php" onSubmit="return CentralSpacePost(this,true);">
        <input type=hidden name="submitted" value="true">
        <input type=hidden name="ref" value="<?php echo (int) $ref; ?>">
        <input type=hidden name="resource" value="<?php echo (int) $resource; ?>">
        <?php generateFormToken("consentmanager_unlink"); ?>

        <div class="Question">
            <label><?php echo escape($lang["resourceid"]); ?></label>
            <div class="Fixed"><?php echo escape($resource)?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["consent_id"]); ?></label>
            <div class="Fixed"><?php echo escape($ref)?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">
            <input name="unlink" type="submit" value="<?php echo escape($lang["action-unlink"]); ?>" />
        </div>
    </form>
</div>

<?php
include "../../../include/footer.php";
?>
