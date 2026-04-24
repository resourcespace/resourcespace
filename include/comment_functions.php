<?php

include_once __DIR__ . '/annotation_functions.php';

/**
 * Write comments to the database, also deals with hiding and flagging comments
 *
 * @return void
 */
function comments_submit()
{
    global $username, $anonymous_login, $userref, $regex_email, $comments_max_characters, $lang, $email_notify, $comments_email_notification_address;

    if (
        $username == $anonymous_login
        && (getval("fullname", "") == ""
        || preg_match("/{$regex_email}/", getval("email", "")) === false)
    ) {
        return;
    }

    $comment_to_hide = getval("comment_to_hide", 0, true);

    if ($comment_to_hide != 0) {
        hide_delete_comment($comment_to_hide);
        return;
    }

    $comment_flag_ref = getval("comment_flag_ref", 0, true);

    // --- process flag request

    if ($comment_flag_ref != 0) {
        $comment_flag_reason = getval("comment_flag_reason", "");
        $comment_flag_url = getval("comment_flag_url", "");

        if ($comment_flag_reason == "" || $comment_flag_url == "") {
            return;
        }

        # the following line can be simplified using strstr (with before_needle boolean) but not supported < PHP 5.3.0
        if (!strpos($comment_flag_url, "#") === false) {
            $comment_flag_url = substr($comment_flag_url, 0, strpos($comment_flag_url, "#") - 1);
        }

        $comment_flag_url .= "#comment{$comment_flag_ref}";     // add comment anchor to end of URL

        $comment_body = ps_query("select body from comment where ref=?", array("i",$comment_flag_ref));
        $comment_body = (!empty($comment_body[0]['body'])) ? $comment_body[0]['body'] : "";

        if ($comment_body == "") {
            return;
        }

        $email_subject = (text("comments_flag_notification_email_subject") != "") ?
            text("comments_flag_notification_email_subject") : $lang['comments_flag-email-default-subject'];

        $email_body = (text("comments_flag_notification_email_body") != "") ?
            text("comments_flag_notification_email_body") : $lang['comments_flag-email-default-body'];

        $email_body .=  "\r\n\r\n\"{$comment_body}\"";
        $email_body .= "\r\n\r\n{$comment_flag_url}";
        $email_body .= "\r\n\r\n{$lang['comments_flag-email-flagged-by']} {$username}";
        $email_body .= "\r\n\r\n{$lang['comments_flag-email-flagged-reason']} \"{$comment_flag_reason}\"";

        $email_to = (
                empty($comments_email_notification_address)

                // (preg_match ("/{$regex_email}/", $comments_email_notification_address) === false)        // TODO: make this regex better
            ) ? $email_notify : $comments_email_notification_address;

        rs_setcookie("comment{$comment_flag_ref}flagged", "true");
        $_POST["comment{$comment_flag_ref}flagged"] = "true";  // we set this so that the subsequent getval() function will pick up this comment flagged in the show comments function (headers have already been sent before cookie set)

        send_mail($email_to, $email_subject, $email_body);
        return;
    }

    // --- process comment submission

    // we don't want to insert an empty comment or an orphan
    if (
        (getval("body", "") == "")
        || (
            (getval("collection_ref", "") == "")
            && (getval("resource_ref", "", false, is_positive_int_loose(...)) == "")
            && (getval("ref_parent", "") == "")
        )
    ) {
        return;
    }

    if ($username == $anonymous_login) {  // anonymous user
        $sql_fields = "fullname, email, website_url";
        $sql_values = array(
            "s", getval("fullname", "") ,
            "s", getval("email", ""),
            "s", getval("website_url", "")
        );
    } else {
        $sql_fields = "user_ref";
        $sql_values = array("i", (int)$userref);
    }

    if(
        $GLOBALS['annotate_enabled']
        && $GLOBALS['annotate_text_adds_comment']
        && ($annotation_ref = getval('annotation_ref', 0, false, is_positive_int_loose(...)))
        && $annotation_ref > 0
    ) {
        $sql_fields .= ', annotation';
        $sql_values[] = 'i';
        $sql_values[] = $annotation_ref;
    }

    $body = getval("body", "");
    if (strlen($body) > $comments_max_characters) {
        $body = substr($body, 0, $comments_max_characters); // just in case not caught in submit form
    }

    $parent_ref =  getval("ref_parent", 0, true);
    $collection_ref =  getval("collection_ref", 0, true);
    $resource_ref =  getval("resource_ref", 0, true);

    $sql_values_prepend = array(
        "i", ($parent_ref == 0 ? null : (int)$parent_ref),
        "i", ($collection_ref == 0 ? null : (int)$collection_ref),
        "i", ($resource_ref == 0 ? null : (int)$resource_ref)
    );

    $sql_values = array_merge($sql_values_prepend, $sql_values, array("s",$body));

    ps_query("insert into comment (ref_parent, collection_ref, resource_ref, {$sql_fields}, body) values (" . ps_param_insert(count($sql_values) / 2) . ")", $sql_values);

    // Notify anyone tagged.
    comments_notify_tagged($body, $userref, $resource_ref, $collection_ref);
}

/**
 *  Check all comments that are children of the comment ref provided. If there is a branch made up entirely of hidden comments then remove the branch.
 *
 *  @param  int $ref    Ref of the comment that is being deleted.
 *
 *  @return int         Number of child comments that are not hidden.
 */
function clean_comment_tree($ref)
{
    $all_comments = ps_query("SELECT " . columns_in("comment") . " FROM comment WHERE ref_parent = ?", ['i', $ref]);
    $remaining = 0;

    if (count($all_comments) > 0) {
        foreach ($all_comments as $comment) {
            $remaining += clean_comment_tree($comment['ref']);
            if ($remaining == 0 && $comment['hide'] == 1) {
                ps_query("DELETE FROM comment WHERE ref = ?", ['i', $comment['ref']]);
            }
        }
    }

    $remaining += ps_value("SELECT count(*) as `value` FROM comment WHERE ref_parent = ? and hide = 0", ['i', $ref], 0);

    if ($remaining == 0) {
        ps_query("DELETE FROM comment WHERE hide = 1 and ref = ?", ['i', $ref]);
    }

    return $remaining;
}

/**
 * Find the root of a comment tree that the ref provided is a part of
 *
 * @param   int     $ref    ref of a comment
 *
 * @return  int|null        ref of the root comment or null if the comment tree has been completely removed / the comment being checked has already been deleted.
 */
function find_root_comment($ref)
{
    $comment = ps_query('SELECT ref, ref_parent FROM comment WHERE ref = ?', ['i', $ref]);

    if (is_array($comment) && !empty($comment)) {
        $comment = $comment[0];

        if (!empty($comment['ref_parent'])) {
            return find_root_comment($comment['ref_parent']);
        }
        return $comment['ref'];
    }
    return null;
}

/**
 * Parse a comment and replace and add links to any user, resource and collection tags
 *
 * @param  string  $text                 The input text e.g. the body of the comment
 *
 */
function comments_tags_to_links($text): string
{
    global $baseurl_short;
    $text = preg_replace('/@(\S+)/s', '<a href="[BASEURLSHORT]pages/user/user_profile.php?username=$1">@$1</a>', $text);

    $text = preg_replace('/r([0-9]{1,})/si', '<a href="[BASEURLSHORT]?r=$1">r$1</a>', $text); # r12345 to resource link

    $text = preg_replace('/c([0-9]{1,})/si', '<a href="[BASEURLSHORT]?c=$1">c$1</a>', $text); # c12345 to collection link

    $text = str_replace("[BASEURLSHORT]", $baseurl_short, $text); // Replacing this earlier can cause issues
    return $text;
}

/**
 * Display all comments for a resource or collection
 *
 * @param  integer $ref                 The reference of the resource or collection
 * @param  boolean $bcollection_mode    false == show comments for resources, true == show comments for collection
 *
 * @return void
 */
function comments_show($ref, $bcollection_mode = false)
{
    if (!is_numeric($ref)) {
        return false;
    }

    global $baseurl_short, $username, $anonymous_login, $lang, $comments_max_characters, $comments_flat_view, $regex_email, $comments_show_anonymous_email_address;

    $anonymous_mode = (empty($username) || $username == $anonymous_login);     // show extra fields if commenting anonymously

    $collection_ref = ($bcollection_mode) ? $ref : "";
    $resource_ref = ($bcollection_mode) ? "" : $ref;

    $collection_mode = $bcollection_mode ? "collection_mode=true" : "";

        // pass this JS function the "this" from the submit button in a form to post it via AJAX call, then refresh the "comments_container"

        if (!$anonymous_mode) {
            echo '<script src="' . $baseurl_short . 'js/tagging.js"></script>';
        }

        echo<<<EOT

        <script type="text/javascript">

            var regexEmail = new RegExp ("{$regex_email}");

            function validateAnonymousComment(obj) {
                return (
                    regexEmail.test (String(obj.email.value).trim()) &&
                    String(obj.fullname.value).trim() != "" &&
                    validateComment(obj)
                )
            }

            function validateComment(obj) {
                return (String(obj.body.value).trim() != "");
            }

            function validateAnonymousFlag(obj) {
                return (
                    regexEmail.test (String(obj.email.value).trim()) &&
                    String(obj.fullname.value).trim() != "" &&
                    validateFlag(obj)
                )
            }

            function validateFlag(obj) {
                return (String(obj.comment_flag_reason.value).trim() != "");
            }

            function submitForm(obj) {
                jQuery.post(
                    '{$baseurl_short}pages/ajax/comments_handler.php?ref={$ref}&collection_mode={$collection_mode}',
                    jQuery(obj).serialize(),
                    function(data)
                    {
                    jQuery('#comments_container').replaceWith(data);
                    }
                );
            }
        </script>

        <div id="comments_container">
        <div id="comment_form" class="comment_form_container">
            <form class="comment_form" action="javascript:void(0);" method="">
EOT;
        generateFormToken("comment_form");
        hook("beforecommentbody");
        $api_native_csrf_gu = generate_csrf_data_for_api_native_authmode('get_users');
        if (!$anonymous_mode) {
            $onkeyup = 'onkeyup="TaggingProcess(this)';
            $placeholder_text_escaped = escape($lang['comments_body-placeholder']);
        } else {
            $onkeyup = '';
            $placeholder_text_escaped = escape($lang['comments_body-placeholder-anon']);
        }
        echo <<<EOT
                <input id="comment_form_collection_ref" type="hidden" name="collection_ref" value="{$collection_ref}"></input>
                <input id="comment_form_resource_ref" type="hidden" name="resource_ref" value="{$resource_ref}"></input>
                <textarea class="CommentFormBody" id="comment_form_body" name="body" maxlength="{$comments_max_characters}" placeholder="{$placeholder_text_escaped}" {$onkeyup}" {$api_native_csrf_gu}></textarea>

EOT;

        if ($anonymous_mode) {
            echo <<<EOT
                <br />
                <input class="CommentFormFullname" id="comment_form_fullname" type="text" name="fullname" placeholder="{$lang['comments_fullname-placeholder']}"></input>
                <input class="CommentFormEmail" id="comment_form_email" type="text" name="email" placeholder="{$lang['comments_email-placeholder']}"></input>
                <input class="CommentFormWebsiteURL" id="comment_form_website_url" type="text" name="website_url" placeholder="{$lang['comments_website-url-placeholder']}"></input>

EOT;
        }

        $validateFunction = $anonymous_mode ? "if (validateAnonymousComment(this.parentNode))" : "if (validateComment(this.parentNode))";

        echo<<<EOT
                <br />
                <input class="CommentFormSubmit" type="submit" value="{$lang['comments_submit-button-label']}" onClick="{$validateFunction} { submitForm(this.parentNode) } else { alert ('{$lang['comments_validation-fields-failed']}'); } ;"></input>
            </form>
        </div> 	<!-- end of comment_form -->

EOT;

    $found_comments = get_comments_by_ref($ref, $bcollection_mode);

    foreach ($found_comments as $comment) {
            $thisRef = $comment['ref'];

            echo "<div class='CommentEntry' id='comment{$thisRef}' style='margin-left: " . $comment['level'] * 50 . "px;'>"; // indent for levels - this will always be zero if config $comments_flat_view=true

            # ----- Information line
            hook("beforecommentinfo", "all", array("ref" => $comment["ref"]));

            echo "<div class='CommentEntryInfoContainer'>";
            echo "<div class='CommentEntryInfo'>";
        if ($comment['profile_image'] != "" && $anonymous_mode != true) {
            echo "<div><img src='" . get_profile_image("", $comment['profile_image']) . "' id='CommentProfileImage'></div>";
        }
            echo "<div class='CommentEntryInfoCommenter'>";


        if (empty($comment['name'])) {
            $comment['name'] = $comment['username'];
        }

        if ($anonymous_mode) {
            echo "<div class='CommentEntryInfoCommenterName'>" . escape($comment['name']) . "</div>";
        } else {
            echo "<a href='" . $baseurl_short . "pages/user/user_profile.php?username=" . escape((string)$comment['username']) . "'><div class='CommentEntryInfoCommenterName'>" . escape($comment['name']) . "</div></a>";
        }

        if ($comments_show_anonymous_email_address && !empty($comment['email'])) {
            echo "<div class='CommentEntryInfoCommenterEmail'>" . escape($comment['email']) . "</div>";
        }
        if (!empty($comment['website_url'])) {
            echo "<div class='CommentEntryInfoCommenterWebsite'>" . escape($comment['website_url']) . "</div>";
        }

            echo "</div>";

            ?>
            <div class='CommentEntryInfoDetails'><?php
                echo date("D", strtotime($comment["created"])) . ' ' . nicedate($comment["created"], true, true, true);

                if ($comment['annotation'] > 0) {
                    echo ' | ';
                    ?>
                    <i class="icon-square-pen" aria-hidden="true" title="<?php echo escape($lang['annotate_annotation_label']); ?>"></i>
                    <?php
                }
            ?></div>
            <?php
            echo "</div>";  // end of CommentEntryInfoLine
            echo "</div>";  // end CommentEntryInfoContainer

            echo "<div class='CommentBody'>";
        if ($comment['hide']) {
            if (text("comments_removal_message") != "") {
                    echo escape(text("comments_removal_message"));
            } else {
                    echo "[" . escape($lang["deleted"]) . "]";
            }
        } else {
            echo comments_tags_to_links(escape($comment['body']));
        }
            echo "</div>";

            # ----- Form area

            $validateFunction = $anonymous_mode ? "if (validateAnonymousFlag(this.parentNode))" : "if (validateFlag(this.parentNode))";

        if (!getval("comment{$thisRef}flagged", "")) {
            echo<<<EOT

                    <div id="CommentFlagContainer{$thisRef}" style="display: none;">
                        <form class="comment_form" action="javascript:void(0);" method="">
                            <input type="hidden" name="comment_flag_ref" value="{$thisRef}"></input>
                            <input type="hidden" name="comment_flag_url" value=""></input>

EOT;
            hook("beforecommentflagreason");
            generateFormToken("comment_form");
            echo <<<EOT
                    <textarea class="CommentFlagReason" maxlength="{$comments_max_characters}" name="comment_flag_reason" placeholder="{$lang['comments_flag-reason-placeholder']}"></textarea><br />
EOT;

            if ($anonymous_mode) { ?>
                    <input class="CommentFlagFullname"
                        id="comment_flag_fullname"
                        type="text"
                        name="fullname" 
                        placeholder="<?php echo escape($lang['comments_fullname-placeholder']); ?>">
                    </input>
                    <input class="CommentFlagEmail"
                        id="comment_flag_email"
                        type="text"
                        name="email"
                        placeholder="<?php echo escape($lang['comments_email-placeholder']); ?>">
                    </input>
                    <br />
            <?php }

            echo<<<EOT
                            <input class="CommentFlagSubmit" type="submit" value="{$lang['comments_submit-button-label']}" onClick="comment_flag_url.value=document.URL; {$validateFunction} { submitForm(this.parentNode); } else { alert ('{$lang['comments_validation-fields-failed']}') }"></input>
                        </form>
                    </div>
EOT;
        }

        if (!$comment['hide']) {
            $respond_button_id = "comment_respond_button_" . $thisRef;
            $respond_div_id = "comment_respond_" . $thisRef;

            echo "<div id='{$respond_button_id}' class='CommentRespond'>";      // start respond div
            echo "<a href='javascript:void(0)' onClick='
                    jQuery(\"#comment_form\").clone().attr(\"id\",\"{$respond_div_id}\").css(\"margin-left\",\"" . (($comment['level'] + 1) * 50) . 'px")' . ".insertAfter(\"#comment$thisRef\");
                    jQuery(\"<input>\").attr({type: \"hidden\", name: \"ref_parent\", value: \"$thisRef\"}).appendTo(\"#{$respond_div_id} .comment_form\");
                    jQuery(\"#{$respond_button_id} a\").removeAttr(\"onclick\");
                '>" . '<i aria-hidden="true" class="icon-reply"></i>&nbsp;' . $lang['comments_respond-to-this-comment'] . "</a>";
            echo "</div>";      // end respond

            echo "<div class='CommentEntryInfoFlag'>";
            if (getval("comment{$thisRef}flagged", "")) {
                echo '<div class="CommentFlagged"><i aria-hidden="true" class="icon-flag">&nbsp;</i>'
                    . escape($lang['comments_flag-has-been-flagged'])
                    . '</div>';
            } else {
                echo<<<EOT
                    <div class="CommentFlag">
                        <a href="javascript:void(0)" onclick="jQuery('#CommentFlagContainer{$thisRef}').toggle('fast');" ><i aria-hidden="true" class="icon-flag">&nbsp;</i>{$lang['comments_flag-this-comment']}</a>
                    </div>
EOT;
            }

            if (checkPerm("o")) {
                ?>
                    <form class="comment_removal_form">
                    <?php generateFormToken("comment_removal_form"); ?>
                        <input type="hidden" name="comment_to_hide" value="<?php echo escape($thisRef); ?>"></input>
                        <input type="hidden" name="resource_ref" value="<?php echo escape($ref); ?>"></input>
                        <a href="javascript:void(0)" onclick="if (confirm ('<?php echo escape($lang['comments_hide-comment-text-confirm']); ?>')) submitForm(this.parentNode);"><?php echo '<i aria-hidden="true" class="icon-trash-2"></i>&nbsp;' . $lang['comments_hide-comment-text-link']; ?></a>
                    </form>
                    <?php
            }

            echo "</div>";      // end of CommentEntryInfoFlag
        }

            echo "</div>";      // end of CommentEntry
    }
    echo "</div>";  // end of comments_container
}

/**
 * Notify anyone tagged when a new comment is posted
 *
 * @param  string $comment       The comment body
 * @param  integer $from_user    Who posted the comment
 * @param  integer $resource     If commenting on a resource, the resource ID
 * @param  integer $collection   If commenting on a collection, the collection ID
 *
 * @return void
 */
function comments_notify_tagged($comment, $from_user, $resource = null, $collection = null)
{
    // Find tagged users.
    $success = preg_match_all("/@.*? /", $comment . " ", $tagged, PREG_PATTERN_ORDER);
    if (!$success) {
        return true;
    } // Nothing to do, return out.
    foreach ($tagged[0] as $tag) {
        $tag = substr($tag, 1);
        $tag = trim($tag); // Get just the username.
        $user = get_user_by_username($tag); // Find the matching user ID
        // No match but there's an underscore? Try replacing the underscore with a space and search again. Spaces are swapped to underscores when tagging.
        if ($user === false) {
            $user = get_user_by_username(str_replace("_", " ", $tag));
        }

        if ($user > 0) {
            // Notify them.

            // Build a URL based on whether this is a resource or collection
            global $baseurl,$userref,$lang;
            $url = $baseurl . "/?" . (is_null($resource) ? "c" : "r") . "=" . (is_null($resource) ? $collection : $resource);

            // Send the message.
            message_add(array($user), $lang["tagged_notification"] . " " . $comment, $url, $userref);
        }
    }
    return true;
}


/**
 * Return comments for a resource or collection. There are two options for the output:
 *   1. A flat list of comments ordered by creation date, newest first. Config $comments_flat_view = true
 *   2. A tree view of comments, top level ordered most recent first with lower levels also most recent first while
 *      respecting a hierarchy of nested comments. Config $comments_flat_view = false (default)
 * User permissions are also checked to ensure users can view comments. $comments_responses_max_level limits the
 * number of levels returned in the comments tree (only applies when $comments_flat_view = false).
 *
 * @param  int    $ref               Resource or collection ID.
 * @param  bool   $collection_mode   True if the $ref provided is a collection ID. False if $ref providing resource ID (default)
 * 
 * @return array  Array of comments sorted per $comments_flat_view. Empty array if user doesn't have permission to
 *                to view comments.
 */
function get_comments_by_ref(int $ref, bool $collection_mode = false): array
{
    if (get_resource_access($ref) === RESOURCE_ACCESS_CONFIDENTIAL) {
        return array();
    }

    global $lang, $comments_flat_view, $comments_responses_max_level;

    $sql_columns = "c.ref, c.ref_parent, c.annotation, c.hide, c.created, c.body, c.website_url, c.email, u.username, u.ref AS 'user_ref', u.profile_image, parent.created AS 'responseToDateTime',";
    $sql_columns .= " IFNULL(IFNULL(c.fullname, u.fullname), ?) AS 'name', IFNULL(IFNULL(parent.fullname, uparent.fullname), ?) AS 'responseToName'";
    $sql_columns_values = array('s', $lang['comments_anonymous-user'], 's', $lang['comments_anonymous-user']);

    $sql_from = "comment c LEFT JOIN (user u) ON (c.user_ref = u.ref) LEFT JOIN (comment parent) ON (c.ref_parent = parent.ref) LEFT JOIN (user uparent) ON (parent.user_ref = uparent.ref)";

    // first level will look for either collection or resource comments
    $sql_where = $collection_mode ? "c.collection_ref = ?" : "c.resource_ref = ?";
    $sql_values_where = array("i", $ref);

    if ($comments_flat_view) {
        return ps_query("SELECT {$sql_columns}, 0 AS 'level' FROM {$sql_from} WHERE {$sql_where} ORDER BY c.created DESC", array_merge($sql_columns_values, $sql_values_where));
    }

    $comments_cte = "WITH RECURSIVE tree AS (
        SELECT
            {$sql_columns},
            0 AS 'level',
            CAST(CONCAT(LPAD(9999999999 - UNIX_TIMESTAMP(c.created), 10, 0), '-', LPAD(c.ref, 10, 0)) AS CHAR(220)) AS `path`
        FROM {$sql_from}
        WHERE {$sql_where} AND c.ref_parent IS NULL
        UNION ALL
        SELECT
            {$sql_columns},
            tree.`level` + 1,
            CONCAT(tree.path, '/', LPAD(9999999999 - UNIX_TIMESTAMP(c.created), 10, 0), '', LPAD(c.ref, 10, 0))
        FROM {$sql_from}
        JOIN tree ON c.ref_parent = tree.ref
        WHERE tree.`level` + 1 < ?
    )

    SELECT ref, ref_parent, annotation, hide, created, body, website_url, email, username, user_ref, profile_image, responseToDateTime, `name`, responseToName, `level`
    FROM tree ORDER BY `path` ASC, ref ASC;";

    return ps_query($comments_cte, array_merge($sql_columns_values, $sql_values_where, $sql_columns_values, array('i', $comments_responses_max_level)));
}


/**
 * Hide or delete a comment or annotation. Comments will be hidden where they have comments below them.
 * If the comment has no comments below it will be deleted. Trees with all hidden comments will also be deleted.
 * Option to disable deletion / hiding of comments created as annotations e.g. to prevent API use.
 *
 * @param  int    $comment_to_hide     Reference of the comment to be deleted / hidden.
 * @param  bool   $allow_annotations   Should comments created by annotations be deleted / hidden.
 * 
 * @return bool   True if successful else false.
 */
function hide_delete_comment(int $comment_to_hide, $allow_annotations = true): bool
{
    if (!checkPerm("o")) {
        return false;
    }

    $root = find_root_comment($comment_to_hide);
    $linked_annotation = getAnnotation(ps_value(
        'SELECT annotation AS `value` FROM `comment` WHERE ref = ?',
        ['i', $comment_to_hide],
        0
    ));

    if ($linked_annotation !== [] && !$allow_annotations) {
        return false;
    }

    // $request_ctx originates from pages/ajax/annotations.php
    if ($linked_annotation !== [] && deleteAnnotation($linked_annotation, $GLOBALS['request_ctx'] ?? [])) {
        $comment_update_extra_cols = ', annotation = null';
    } else {
        $comment_update_extra_cols = '';
    }

    // Does this comment have any child comments?
    if (ps_value("SELECT ref AS value FROM comment WHERE ref_parent = ?", array("i",$comment_to_hide), '') != '') {
        ps_query("UPDATE `comment` SET hide = 1{$comment_update_extra_cols} WHERE ref = ?", ['i', $comment_to_hide]);
    } else {
        ps_query("DELETE FROM comment WHERE ref = ?", array("i",$comment_to_hide));
    }
    if (!is_null($root)) {
        clean_comment_tree($root);
    }

    return true;
}