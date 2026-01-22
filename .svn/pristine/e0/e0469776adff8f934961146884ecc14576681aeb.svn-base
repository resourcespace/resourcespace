<?php

include "include/boot.php";
debug("[index.php] Reached index page...");

if (getval("rp", "") != "") {
    # quick redirect to reset password
    $rp = getval("rp", "");
    $topurl = "pages/user/user_change_password.php?rp=" . $rp;
    redirect($topurl);
}

# External access support (authenticate only if no key provided, or if invalid access key provided)
$k = getval('k', '');
if ('' == $k || (!check_access_key_collection(getval('c', ''), $k) && !check_access_key(getval('r', ''), $k))) {
    debug("[index.php] External access support, include authenticate.php next.");
    include 'include/authenticate.php';
}

$topurl = "pages/home.php?login=true";
if ($use_theme_as_home) {
    $topurl = "pages/collections_featured.php";
}
if ($use_recent_as_home) {
    $topurl = "pages/search.php?search=" . urlencode("!last" . $recent_search_quantity);
}

$c = trim(getval("c", ""));
if ($c != "") {
    $collection = get_collection($c);
    if ($collection === false) {
        exit($lang["error-collectionnotfound"]);
    }

    $topurl = "pages/search.php?search=" . urlencode("!collection" . $c) . "&k=" . $k;
    $collection_resources = get_collection_resources($c);

    if ($collection["type"] == COLLECTION_TYPE_FEATURED) {
        $collection["has_resources"] = (is_array($collection_resources) && !empty($collection_resources) ? 1 : 0);
    }

    if (is_featured_collection_category($collection)) {
        $topurl = "pages/collections_featured.php?parent={$c}&k={$k}";
    } elseif (is_array($collection_resources) && count($collection_resources) > 0 && $feedback_resource_select && $collection["request_feedback"]) {
        $topurl = "pages/collection_feedback.php?collection={$c}&k={$k}";
    }
}

if (getval("r", "") != "") {
    # quick redirect to a resource (from e-mails)
    $r = (int) getval("r", "");
    $topurl = "pages/view.php?ref=" . $r . "&k=" . $k;
}

if (getval("u", "") != "") {
    # quick redirect to a user (from e-mails)
    $u = getval("u", "");
    $topurl = "pages/team/team_user_edit.php?ref=" . $u;
}

if (getval("q", "") != "") {
    # quick redirect to a request (from e-mails)
    $q = getval("q", "");
    $topurl = "pages/team/team_request_edit.php?ref=" . $q;
}

if (getval('ur', '') != '') {
    # quick redirect to periodic report unsubscriptions.
    $ur = getval('ur', '', true);
    $unsubscribe_user = getval('user', '', true);
    $topurl = 'pages/team/team_report.php?unsubscribe=' . $ur . ($unsubscribe_user !== '' ? '&user=' . $unsubscribe_user : '');
}

if (getval('dr', '') != '') {
    # quick redirect to periodic report deletion.
    $dr = getval('dr', '');
    $topurl = 'pages/team/team_report.php?delete=' . $dr;
}

if (getval("upload", "") != "") {
    # Redirect to upload page
    $topurl = get_upload_url($c, $k);
}

# Redirect.
redirect($topurl);
