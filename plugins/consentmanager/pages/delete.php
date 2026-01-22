<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php";

$ref = getval("ref", "");

# Check access
if (!consentmanager_check_read()) {
    exit("Access denied");
} # Should never arrive at this page without admin access

$url_params = array(
    'ref'        => $ref,
    'search'     => getval('search', ''),
    'order_by'   => getval('order_by', ''),
    'collection' => getval('collection', ''),
    'offset'     => getval('offset', 0),
    'restypes'   => getval('restypes', ''),
    'archive'    => getval('archive', '')
);
$redirect_url = generateURL($baseurl_short . "/plugins/consentmanager/pages/list.php", $url_params);

if (getval("submitted", "") != "" && enforcePostRequest(false)) {
    consentmanager_delete_consent($ref);
    redirect($redirect_url);
}

include "../../../include/header.php";
?>

<div class="BasicsBox">
    <p>
        <a href="<?php echo $redirect_url ?>" onClick="return CentralSpaceLoad(this,true);">
            <?php echo LINK_CARET_BACK . escape($lang["back"]); ?>
        </a>
    </p>

    <h1><?php echo escape($lang["delete_consent"]); ?></h1>

    <form method="post" action="<?php echo $baseurl_short?>plugins/consentmanager/pages/delete.php" onSubmit="return CentralSpacePost(this,true);">
        <input type=hidden name="submitted" value="true">
        <input type=hidden name="ref" value="<?php echo escape($ref); ?>">
        <?php generateFormToken("consentmanager_delete"); ?>

        <div class="Question">
            <label><?php echo escape($lang["consent_id"]); ?></label>
            <div class="Fixed"><?php echo escape($ref)?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">        
            <input name="delete" type="submit" value="<?php echo escape($lang["action-delete"]); ?>" />
        </div>
    </form>
</div>

<?php
include "../../../include/footer.php";
?>
