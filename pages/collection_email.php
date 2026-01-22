<?php
include "../include/boot.php";
include "../include/authenticate.php";

$collection_url = getval("collection", "");
$find           = getval('find', '');
$offset         = getval("offset", 0, true);
$order_by       = getval("order_by", "");
$sort           = getval("sort", "");
$search         = getval("search", "");
$ref            = getval("ref", 0, true);

// Share options
$expires        = getval("expires", "");
$access         = getval("access", -1, true);
$group          = getval("usergroup", 0, true);
$sharepwd       = getval('sharepassword', '');

$collection = get_collection($ref);
if ($collection === false) {
    error_alert($lang["error-collectionnotfound"], true, 403);
    exit();
}

if ($collection["type"] == COLLECTION_TYPE_FEATURED) {
    $collection_resources = get_collection_resources($collection["ref"]);
    $collection["has_resources"] = (is_array($collection_resources) && !empty($collection_resources) ? 1 : 0);
}

// Check access controls
if (!collection_readable($ref)) {
    exit($lang["no_access_to_collection"]);
} elseif (
    $collection["type"] == COLLECTION_TYPE_FEATURED
    && !featured_collection_check_access_control((int) $collection["ref"])
    && !allow_featured_collection_share($collection)
) {
    error_alert($lang["error-permissiondenied"], true, 403);
    exit();
}
if (!$allow_share || checkperm("b") || is_anonymous_user()) {
    error_alert($lang["error-permissiondenied"], true, 403);
    exit();
}

$themeshare = false;
$themename = "";
$subthemes = (getval("subthemes", "true") != "false");

if (is_featured_collection_category($collection)) {
    $themeshare = true;
    $themename = i18n_get_translated($collection["name"]);

    // Check this is not an empty FC category
    if (empty(get_featured_collection_resources($collection, array("limit" => 1)))) {
        error_alert($lang["cannotshareemptythemecategory"], true, 403);
        exit();
    }

    // Further checks at collection-resource level. Recurse through category's sub FCs
    if ($subthemes) {
        $sub_fcs = get_featured_collection_categ_sub_fcs($collection);
    } else {
        $sub_fcs = get_featured_collections($collection["ref"], array());
        $sub_fcs = array_filter($sub_fcs, function ($fc) {
            return !is_featured_collection_category($fc);
        });
        $sub_fcs = array_values(array_column($sub_fcs, "ref"));
    }

    $collection["sub_fcs"] = $sub_fcs;
    $collectionstates = false;
    $sub_fcs_resources_states = array();
    $sub_fcs_resources_minaccess = array();

    foreach ($collection["sub_fcs"] as $sub_fc) {
        // Check all featured collections contain only active resources
        $collectionstates = is_collection_approved($sub_fc);
        if (!$collection_allow_not_approved_share && $collectionstates === false) {
            break;
        } elseif (is_array($collectionstates)) {
            $sub_fcs_resources_states = array_unique(array_merge($sub_fcs_resources_states, $collectionstates));
        }

        // Check minimum access is restricted or lower and sharing of restricted resources is not allowed
        $sub_fcs_resources_minaccess[] = collection_min_access($sub_fc);
    }
    $collectionstates = (!empty($sub_fcs_resources_states) ? $sub_fcs_resources_states : $collectionstates);

    if (!empty($sub_fcs_resources_minaccess)) {
        $minaccess = max(array_unique($sub_fcs_resources_minaccess));
    }
}

$collectionstates = (isset($collectionstates) ? $collectionstates : is_collection_approved($ref));

if (!$collection_allow_not_approved_share && !$collectionstates) {
    $show_error = true;
    $error = $lang["notapprovedsharecollection"];
}

# Minimum access is restricted or lower and sharing of restricted resources is not allowed. The user cannot share this collection.
$minaccess = (isset($minaccess) ? $minaccess : collection_min_access($ref));

if (!$restricted_share && $minaccess >= RESOURCE_ACCESS_RESTRICTED) {
    $show_error = true;
    $error = $lang["restrictedsharecollection"];
}

if (isset($show_error)) { ?>
    <script type="text/javascript">
        alert('<?php echo escape($error); ?>');
        history.go(-1);
    </script>
    <?php
    exit();
}

$internal_share_only = checkperm("noex") || (isset($user_dl_limit) && intval($user_dl_limit) > 0);

// Legacy way of working when sharing a FC category. It relies on a list of collections
$ref = ($themeshare ? join(",", array_merge(array($collection["ref"]), $collection["sub_fcs"])) : $ref);

$errors = "";
if (getval("save", "") != "" && enforcePostRequest(getval("ajax", false))) {
    # Email / share collection
    # Build a new list and insert
    $users = getval("users", "");
    $message = getval("message", "");
    $add_internal_access = (getval("grant_internal_access", "") != "");
    $feedback = getval("request_feedback", "");

    if ($feedback == "") {
        $feedback = false;
    } else {
        $feedback = true;
    }

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

    $errors = email_collection($ref, i18n_get_collection_name($collection), $userfullname, $users, $message, $feedback, $access, $expires, $user_email, $from_name, $cc, $themeshare, $themename, "?parent=" . $collection["ref"], $list_recipients, $add_internal_access, $group, $sharepwd);

    if ($errors == "") {
        # Log this
        // fix for bomb on multiple collections, daily stat object ref must be a single number.
        $crefs = explode(",", $ref);
        foreach ($crefs as $cref) {
            daily_stat("E-mailed collection", $cref);
        }
        if (!hook("replacecollectionemailredirect")) {
            redirect($baseurl_short . "pages/done.php?text=collection_email");
        }
    }
}

include "../include/header.php";
?>

<div class="BasicsBox">
    <h1>
        <?php if ($themeshare) {
            echo escape($lang["email_theme_category"]);
        } else {
            echo escape($lang["emailcollectiontitle"]);
        } ?>
    </h1>

    <?php
    $link_array = array(
        "ref"           =>  $collection["ref"],
        "search"        =>  $search,
        "offset"        =>  $offset,
        "order_by"      =>  $order_by,
        "sort"          =>  $sort,
        "collection"    =>  $collection_url,
        "find"          =>  $find,
        "k"             =>  $k
    );

    $link_back = generateURL($baseurl . "/pages/collection_share.php", $link_array);
    ?>

    <p>
        <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $link_back ?>">
            <?php echo LINK_CARET_BACK . escape($lang["backtosharecollection"]); ?>
        </a>
    </p>

    <p>
        <?php
        if ($themeshare && text("introtextthemeshare") != "") {
            echo escape(text("introtextthemeshare"));
        } else {
            echo strip_paragraph_tags(strip_tags_and_attributes(text("introtext")));
        }
        render_help_link("user/sharing-resources");
        ?>
    </p>

    <form
        name="collectionform"
        method=post
        id="collectionform"
        action="<?php echo $baseurl_short?>pages/collection_email.php?catshare=<?php echo $themeshare ? 'true' : 'false'; ?>"
    >
        <input type=hidden name=redirect id=redirect value=yes>
        <input type=hidden name=ref id="ref" value="<?php echo escape($collection["ref"]); ?>">

        <?php
        generateFormToken("collectionform");

        if ($themeshare) {
            ?>
            <div class="Question">
                <label for="subthemes"><?php echo escape($lang["share_theme_category_subcategories"]); ?></label>
                <input type="checkbox" id="subthemes" name="subthemes" value="true" <?php echo $subthemes ? "checked" : ""; ?>>
                <div class="clearerleft"></div>
            </div>
            <?php
        } else {
            ?> 
            <div class="Question">
                <label>
                    <?php if ($themeshare) {
                        echo escape($lang["themes"]);
                    } else {
                        echo escape($lang["collectionname"]);
                    } ?>
                </label>
                <div class="Fixed">
                    <?php
                    if (!$themeshare) {
                        echo i18n_get_collection_name($collection);
                    } else { ##  this select copied from collections.php
                        ?>      
                        <select
                            name="collection"
                            multiple
                            size="10"
                            class="stdwidth MultiSelect"
                            style="height:100%;" 
                            onchange="document.getElementById('ref').value = getSelected(this);"
                        >
                            <?php
                            $list = get_user_collections($userref);
                            $found = false;

                            for ($n = 0; $n < count($list); $n++) { ?> 
                                <option
                                    value="<?php echo $list[$n]["ref"]; ?>"
                                    <?php if ($ref == $list[$n]["ref"]) { ?>
                                        selected
                                        <?php
                                        $found = true;
                                    } ?>
                                >
                                    <?php echo i18n_get_collection_name($list[$n]); ?>
                                </option>
                                <?php
                            }

                            if (!$found) {
                                # Add this one at the end, it can't be found
                                $notfound = get_collection($ref);
                                if ($notfound !== false) {
                                    ?>
                                    <option value="<?php echo urlencode($ref) ?>" selected><?php echo $notfound["name"]; ?></option>
                                    <?php
                                }
                            }
                            ?>
                        </select> 
                        <?php
                    } ?>
                </div>
                <div class="clearerleft"></div>
            </div>
            <?php
        }
        ?>

        <div class="Question">
            <label for="message"><?php echo escape($lang["message"]); ?></label>
            <textarea class="stdwidth" rows=6 cols=50 name="message" id="message"></textarea>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="users">
                <?php echo strip_tags_and_attributes($internal_share_only ? $lang["emailtousers_internal"] : $lang["emailtousers"]); ?>
            </label>
            <?php
            $userstring = getval("users", "");
            include "../include/user_select.php";
            ?>
            <div class="clearerleft"></div>
            <?php if ($errors != "") { ?>
                <div class="FormError">!! <?php echo $errors?> !!</div>
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
        $allow_edit = allow_multi_edit($ref);
        if ($allow_edit) { ?>
            <div class="Question">
                <label for="grant_internal_access"><?php echo escape($lang["internal_share_grant_access"]); ?></label>
                <input type=checkbox id="grant_internal_access" name="grant_internal_access" onclick="if(this.checked){jQuery('#question_internal_access').slideDown();}else{jQuery('#question_internal_access').slideUp()};">
                <div class="clearerleft"></div>
            </div>  
            <?php
        }

        if (!$internal_share_only) {
            $shareoptions = array(
                "password"          => $sharepwd != "",
                "editaccesslevel"   => $access,
                "editexpiration"    => $expires,
                "editgroup"         => $group,
                );
            render_share_options($shareoptions);
        }

        if ($collection["user"] == $userref) { # Collection owner can request feedback.
            ?>
            <div class="Question">
                <label for="request_feedback"><?php echo strip_tags_and_attributes($lang["requestfeedback"]); ?></label>
                <input type=checkbox id="request_feedback" name="request_feedback" value="yes">
                <div class="clearerleft"></div>
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
                value="<?php
                if ($themeshare) {
                    echo escape($lang["email_theme_category"]);
                } else {
                    echo escape($lang["emailcollectiontitle"]);
                } ?>"
            />
        </div>
    </form>
</div>

<?php include "../include/footer.php";
?>
