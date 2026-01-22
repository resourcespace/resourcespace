<?php
include "../../../include/boot.php";
include_once "../../../include/authenticate.php";

if (!checkperm("a") && !checkperm("cm")) {
    exit("Access denied");
} # Should never arrive at this page without admin access

$ref = getval("ref", 0, true);
$collection = trim(str_replace("!collection", "", getval("collection", "")));
$unlink = (getval("unlink", "") != ""); # Unlink mode

if (getval("submitted", "") != "" && enforcePostRequest(false)) {
    consentmanager_batch_link_unlink($ref, $collection, $unlink);

    $url_params = array(
        'ref'        => $ref,
        'search'     => getval('search', ''),
        'order_by'   => getval('order_by', ''),
        'collection' => getval('collection', ''),
        'offset'     => getval('offset', 0),
        'restypes'   => getval('restypes', ''),
        'archive'    => getval('archive', '')
    );
    $redirect_url = generateURL($baseurl_short . "/plugins/consentmanager/pages/edit.php", $url_params);
    redirect($redirect_url);
}

include "../../../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo escape($unlink ? $lang["unlinkconsent"] : $lang["linkconsent"]); ?></h1>

    <form method="post" action="<?php echo $baseurl_short?>plugins/consentmanager/pages/batch.php" onSubmit="return CentralSpacePost(this,true);">
        <input type=hidden name="submitted" value="true">
        <input type=hidden name="collection" value="<?php echo $collection?>">
        <input type=hidden name="unlink" value="<?php echo $unlink ? "true" : ""; ?>">
        <?php generateFormToken("consentmanager_batch"); ?>

        <div class="Question"><label><?php echo escape($lang["consent_id"]); ?></label>
            <select name="ref">
                <option value=""><?php echo escape($lang["select"]); ?></option>
                <?php
                if ($unlink) {
                    // Show only relevant consent records (actually linked)
                    $consents = consentmanager_get_all_consents_by_collection($collection);
                } else {
                    // Show all consent records for linking
                    $consents = consentmanager_get_all_consents();
                }
                foreach ($consents as $consent) { ?>
                    <option value="<?php echo $consent["ref"]; ?>"><?php echo $consent["ref"]; ?> - <?php echo $consent["name"]; ?></option>
                <?php } ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">        
            <input name="batch" type="submit" value="<?php echo escape($lang["save"]); ?>" />
        </div>
    </form>
</div>

<?php
include "../../../include/footer.php";
?>
