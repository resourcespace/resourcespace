<?php
include "../include/boot.php";
include "../include/authenticate.php";

$ref = getval("ref", "", true);
// Fetch resource data
$resource = get_resource_data($ref);

if ($resource === false) {
    exit($lang['resourcenotfound']);
}

// fetch the current search
$search = getval("search", "");
$order_by = getval("order_by", "relevance");
$offset = getval("offset", 0, true);
$restypes = getval("restypes", "");

if (strpos($search, "!") !== false) {
    $restypes = "";
}

$archive = getval("archive", 0, true);
$modal = (getval("modal", "") == "true");
$default_sort_direction = "DESC";

if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}

$sort = getval("sort", $default_sort_direction);
$minaccess = get_resource_access($ref);

// Check if sharing permitted
if (!can_share_resource($ref, $minaccess) || is_anonymous_user()) {
    exit($lang["error-permissiondenied"]);
}

$user_select_internal = checkperm("noex") ||  intval($user_dl_limit) > 0;
$errors = "";

if (getval("save", "") != "" && enforcePostRequest(getval("ajax", false))) {
    // Build a new list and insert
    $users = getval("users", "");
    $message = getval("message", "");
    $access = getval("access", "");
    $add_internal_access = (getval("grant_internal_access", "") != "");
    $expires = getval("expires", "");
    $group = getval("usergroup", "");
    $sharepwd = getval('sharepassword', '');
    $list_recipients = getval("list_recipients", "");

    if ($list_recipients == "") {
        $list_recipients = false;
    } else {
        $list_recipients = true;
    }

    $user_email = "";
    $from_name = $userfullname;

    if (getval("ccme", false)) {
        $cc = $useremail;
    } else {
        $cc = "";
    }

    enforceSharePassword($sharepwd);

    // Email single resource
    $errors = email_resource($ref, i18n_get_translated($resource["field" . $view_title_field]), $userfullname, $users, $message, $access, $expires, $sharepwd, $user_email, $from_name, $cc, $list_recipients, $add_internal_access, $minaccess, $group);

    if ($errors == "") {
        // Log this
        daily_stat("E-mailed resource", $ref);
        if (!hook("replaceresourceemailredirect")) {
            $params = get_search_params();
            $params["text"]     = "resource_email";
            $params["resource"] = $ref;
            redirect(generateURL($baseurl_short . "pages/done.php", $params));
        }
    }
}

$default_url_qs = [
    'search' => $search,
    'offset' => $offset,
    'order_by' => $order_by,
    'sort' => $sort,
    'archive' => $archive,
];

include "../include/header.php";
?>

<div class="BasicsBox">
    <p>
        <a onclick="return <?php echo $modal ? 'ModalLoad' : 'CentralSpaceLoad';?>(this,true);" href="<?php
            echo generateURL("{$baseurl_short}pages/resource_share.php", $default_url_qs, ['ref' => $ref]);
        ?>">
            <?php echo LINK_CARET_BACK . escape($lang["backtoshareresource"]); ?>
        </a>
    </p>

    <h1><?php echo escape($lang["emailresourcetitle"])?></h1>

    <p>
        <?php
        echo escape(text("introtext"));
        render_help_link("user/sharing-resources");
        ?>
    </p>

    <form method=post id="resourceform" action="<?php echo generateURL("{$baseurl_short}pages/resource_email.php", $default_url_qs); ?>">
        <input type=hidden name=ref value="<?php echo escape($ref)?>">
        <?php generateFormToken("resourceform"); ?>

        <div class="Question">
            <label><?php echo escape($lang["resourcetitle"])?></label>
            <div class="Fixed"><?php echo escape(i18n_get_translated($resource["field" . $view_title_field]))?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["resourceid"])?></label>
            <div class="Fixed"><?php echo escape($resource["ref"]); ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="message"><?php echo escape($lang["message"])?></label>
            <textarea class="stdwidth" rows=6 cols=50 name="message" id="message"></textarea>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="users">
                <?php echo strip_tags_and_attributes($user_select_internal ? $lang["emailtousers_internal"] : $lang["emailtousers"]); ?>
            </label>
            <?php include "../include/user_select.php"; ?>
            <div class="clearerleft"></div>
            <?php if ($errors != "") { ?>
                <div class="FormError">!! <?php echo escape($errors)?> !!</div>
            <?php } ?>
        </div>

        <?php if ($list_recipients) { ?>
            <div class="Question">
                <label for="list_recipients"><?php echo escape($lang["list-recipients-label"]); ?></label>
                <input type=checkbox id="list_recipients" name="list_recipients">
                <div class="clearerleft"></div>
            </div>
        <?php } ?>

        <?php
        if ($minaccess == 0) {
            $resourcedata = get_resource_data($ref, true);

            if (get_edit_access($ref, $resource['archive'], $resource)) { ?>
                <div class="Question">
                    <label for="grant_internal_access"><?php echo escape($lang["internal_share_grant_access"]) ?></label>
                    <input type=checkbox id="grant_internal_access" name="grant_internal_access" onclick="if(this.checked){jQuery('#question_internal_access').slideDown();}else{jQuery('#question_internal_access').slideUp()};">
                    <div class="clearerleft"></div>
                </div>
                <?php
            }
        }

        if (!$user_select_internal) {
            ?>
            <h2 class="CollapsibleSectionHead collapsed"><?php echo escape($lang['external_shares_options']);?></h2>
            <div class="CollapsibleSection" id="ExternalShareOptionsSection">
                <p><?php echo escape($lang['email_shares_options_summary']);?></p>
                <?php render_share_options(); ?>
            </div>
            <?php
        }

        if ($cc_me && $useremail != "") {
            ?>
            <div class="Question">
                <label for="ccme"><?php echo escape(str_replace("%emailaddress", $useremail, $lang["cc-emailaddress"])); ?></label>
                <input type=checkbox checked id="ccme" name="ccme">
                <div class="clearerleft"></div>
            </div>
            <?php
        }

        hook("additionalemailfield");
        ?>

        <div class="QuestionSubmit">        
            <input
                name="save"
                type="submit"
                onclick="<?php
                if ($share_password_required) {
                    echo 'if (!enforceSharePassword(\'' . escape($lang['share-password-not-set']) . '\')) { return false; }; ';
                } ?>"
                value="<?php echo escape($lang["emailresourcetitle"])?>"
            />
        </div>
    </form>
</div>

<script>
    jQuery('document').ready(function() {
        registerCollapsibleSections(true)
    });
</script>

<?php
include "../include/footer.php";
