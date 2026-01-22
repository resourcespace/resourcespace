<?php

include_once "../include/boot.php";
include "../include/authenticate.php";

if (!checkperm("s")) {
    exit("Permission denied.");
}

$selected_archive_states = array();

$archivechoices = getval(
    "archive",
    getval(
        "saved_archive",
        false,
        false,
        // Regex below is to force any strings to be a csv list of digits
        fn ($v) => is_array($v) || is_int_loose($v) || preg_match("~^\\-*\\d+[,\\-*\\d+]*$~", $v)
    ),
    false,
    fn ($v) => is_array($v) || is_int_loose($v) || preg_match("~^\\-*\\d+[,\\-*\\d+]*$~", $v)
);

if ($archivechoices !== false) {
    $search_all_workflow_states = false;
} else {
    $archivechoices = get_default_search_states();
}

if (!is_array($archivechoices)) {
    $archivechoices = explode(",", $archivechoices);
}

foreach ($archivechoices as $archivechoice) {
    if (is_numeric($archivechoice)) {
        $selected_archive_states[] = $archivechoice;
    }
}

$archive = implode(",", $selected_archive_states);
$archiveonly = count(array_diff($selected_archive_states, array(1,2))) == 0;

# Selectedtypes is a list of (resource type) checkboxes which are checked
# Selectedtypes can also contain FeaturedCollections if $search_includes_themes = true
$selectedtypes = get_selectedtypes();

$access = getval("access", null, true);
rs_setcookie("access", $access, 0, "{$baseurl_short}pages/", "", false, false);

# Disable auto-save function, only applicable to edit form. Some fields pick up on this value when rendering then fail to work.
$edit_autosave = false;

if (getval("submitted", "") == "yes" && getval("resetform", "") == "") {
    $restypes = "";
    reset($_POST);
    foreach ($_POST as $key => $value) {
        if (substr($key, 0, 12) == "resourcetype") {
            if ($restypes != "") {
                $restypes .= ",";
            }
            $restypes .= substr($key, 12);
        }

        if ($key == "hiddenfields") {
            $hiddenfields = $value;
        }
    }
    rs_setcookie('restypes', $restypes, 0, "", "", false, false);

    if ($hide_search_resource_types) {
        $restypes = '';
    }

    # advanced search - build a search query and redirect
    $fields = array_merge(get_advanced_search_fields(false, $hiddenfields), get_advanced_search_collection_fields(false, $hiddenfields));

    # Build a search query from the search form
    $search = search_form_to_search_query($fields);
    $search = refine_searchstring($search);

    if (getval("countonly", "") != "") {
        # Only show the results (this will appear in an iframe)
        if (substr($restypes, 0, 19) != "FeaturedCollections") {
            $result = do_search($search, $restypes, "relevance", $archive, [0,0], "", false, DEPRECATED_STARSEARCH, false, false, "", false, false, true, false, false, $access);
        } else {
            $order_by = $default_collection_sort;
            $sort = "DESC";
            $result = do_collections_search($search, $restypes, $archive, $order_by, $sort);
        }
        if (is_array($result)) {
            $count = $result["total"] ?? count($result);
        } else {
            $count = 0;
        }

        ?>
        <html>
        <script type="text/javascript">
            function populate_view_buttons(content) {
                var inputs = parent.document.getElementsByClassName('dosearch');

                for (var i = 0; i < inputs.length; i++) {
                    if (typeof inputs[i] !== 'undefined') {
                        inputs[i].value = content;
                    }
                }

                console.debug("Finished updating result count");
                var updatingcount = false;
            }

            <?php if ($count == 0) { ?>
                populate_view_buttons("<?php echo escape($lang["nomatchingresults"]); ?>");
            <?php } else { ?>
                populate_view_buttons("<?php echo escape($lang["view"] . " " . number_format($count) . " " . $lang["matchingresults"]); ?>");
            <?php } ?>
        </script>
        </html>
        <?php
        exit();
    } else {
        # Log this
        daily_stat("Advanced search", $userref);

        redirect(
            generateURL(
                "{$baseurl_short}pages/search.php",
                array(
                    "search" => $search,
                    "archive" => $archive,
                    "restypes" => $restypes,
                    "access" => $access,
                )
            )
        );
    }
}

# Reconstruct a values array based on the search keyword, so we can pre-populate the form from the current search
$search = isset($_COOKIE["search"]) ? $_COOKIE["search"] : "";
$keywords = split_keywords($search, false, false, false, false, true);
$allwords = "";
$found_year = "";
$found_month = "";
$found_day = "";
$found_start_date = "";
$found_end_date = "";
$searched_nodes = array();
$full_text_search = "";

foreach ($advanced_search_properties as $advanced_search_property => $code) {
    $$advanced_search_property = "";
}

$values = array();

if (getval("resetform", "") != "") {
    $found_year = "";
    $found_month = "";
    $found_day = "";
    $found_start_date = "";
    $found_end_date = "";
    $allwords = "";
    $full_text_search = "";
    $restypes = get_search_default_restypes();
    rs_setcookie('restypes', implode(",", $restypes), 0, "", "", false, false);
    $selected_archive_states = array(0);
    rs_setcookie("search", "", 0, "", "", false, false);
    rs_setcookie("saved_archive", "", 0, "", "", false, false);

    $access = null;
    rs_setcookie("access", "", 0, "{$baseurl_short}pages/", "", false, false);
} else {
    if (getval("restypes", "") == "") {
        $restypes = get_search_default_restypes();
    } else {
        $restypes = explode(",", getval("restypes", ""));
    }

    for ($n = 0; $n < count($keywords); $n++) {
        $keyword = trim($keywords[$n]);
        $quoted_string = (substr($keyword, 0, 1) == "\""  || substr($keyword, 0, 2) == "-\"" ) && substr($keyword, -1, 1) == "\"";

        if (strpos($keyword, ":") !== false && substr($keyword, 0, 1) != "!") {
            if ((substr($keyword, 0, 1) == "\""  || substr($keyword, 0, 2) == "-\"" ) && substr($keyword, -1, 1) == "\"") {
                $nk = explode(":", substr($keyword, 1, -1));

                if ($nk[0] == FULLTEXT_SEARCH_PREFIX) {
                    $name = trim($nk[0]);
                    $full_text_search = str_replace(FULLTEXT_SEARCH_QUOTES_PLACEHOLDER, "\"", $nk[1]);
                } else {
                    $name = trim($nk[0]);
                    $keyword = "\"" . trim($nk[1]) . "\"";
                }
            } else {
                $nk = explode(":", $keyword);
                $name = trim($nk[0]);
                $keyword = trim($nk[1]);
            }

            if ($name == "basicday") {
                $found_day = $keyword;
            }

            if ($name == "basicmonth") {
                $found_month = $keyword;
            }

            if ($name == "basicyear") {
                $found_year = $keyword;
            }

            if ($name == "startdate") {
                $found_start_date = $keyword;
            }

            if ($name == "enddate") {
                $found_end_date = $keyword;
            }

            if (isset($values[$name])) {
                $values[$name] .= " " . $keyword;
            } else {
                $values[$name] = $keyword;
            }
        } elseif (substr($keyword, 0, 11) == "!properties") {
            $properties = explode(";", substr($keyword, 11));
            $propertyfields = array_flip($advanced_search_properties);

            foreach ($properties as $property) {
                $propertycheck = explode(":", $property);
                $propertyname = isset($propertycheck[0]) ? $propertycheck[0] : "";
                $propertyval = isset($propertycheck[1]) ? $propertycheck[1] : "";

                if ($propertyval != "" && isset($propertyfields[$propertyname])) {
                    $fieldname = $propertyfields[$propertyname];
                    $$fieldname = $propertyval;
                }
            }
        } elseif (strpos($keyword, NODE_TOKEN_PREFIX) !== false) {
            // Nodes search
            $nodes = resolve_nodes_from_string($keyword);

            foreach ($nodes as $node) {
                $searched_nodes[] = $node;
            }
        } else {
            if ($allwords == "") {
                $allwords = $keyword;
            } else {
                $allwords .= ", " . $keyword;
            }
        }
    }

    $allwords = str_replace(', ', ' ', $allwords);
}

include "../include/header.php";
?>

<script type="text/javascript">
    var resTypes=Array();

    <?php
    $types = get_resource_types();
    for ($n = 0; $n < count($types); $n++) {
        if (!in_array($types[$n]["ref"], $hide_resource_types)) {
            echo "resTypes[" .  $n  . "]=" . $types[$n]["ref"] . ";";
        }
    }
    ?>

    function displayValidFields() {
        // Determine which fields should be displayed for the selected resource types and considering any field display conditions.
        const conditionalquestions = document.getElementsByClassName("ConditionalVisibility");
        let fieldvalidfortypes = [];
        let add_type_specific_header = false;

        for (let conditionalquestion of conditionalquestions) {
            fieldvalidfortypes = conditionalquestion.dataset.for_resource_types.split(",");
            hidefield = false;

            for (let selectedtype of selectedtypes) {
                if (fieldvalidfortypes[0] !== "Global" && hidefield === false && fieldvalidfortypes.indexOf(selectedtype) === -1) {
                    // Question resource type must be a selected resource type. e.g. types: 3,4 question: 3 
                    hidefield = true;
                }
            }

            if (selectedtypes.length === 0 && fieldvalidfortypes[0] !== "Global") {
                // Don't show any resource type specific fields if no resource types are set.
                hidefield = true;
            }

            if (hidefield === true) {
                // Field isn't valid for the selected resource types so hide.
                document.getElementById(conditionalquestion.id).style.display = "none";
                console.debug("hide " + conditionalquestion.id);
                // Record the decision
                document.getElementById(conditionalquestion.id).dataset.resource_type_ok = "0";
            } else {
                // Record the decision
                document.getElementById(conditionalquestion.id).dataset.resource_type_ok = "1";
                if (conditionalquestion.dataset.has_display_condition === '1') {
                    // Display condition applies for field so check it to decide if field is shown.
                    let conditiontocheck = 'checkSearchDisplayCondition' + conditionalquestion.dataset.question_field_ref;
                    window[conditiontocheck]();
                    console.debug("display condition check for " + conditionalquestion.id);
                } else {
                    // No display condition and field valid for resource types selected so show.
                    document.getElementById(conditionalquestion.id).style.display = "";
                    console.debug("show " + conditionalquestion.id);
                }

                if (fieldvalidfortypes[0] !== "Global") {
                    // Field either displayed or can be displayed, so add header so field can be visible.
                    add_type_specific_header = true;
                }
            }
        }

        // Determine if resource type specific fields section should be displayed.
        if (add_type_specific_header) {
            jQuery('#AdvancedSearchRestypeSectionHead').show();
            if (getCookie('AdvancedSearchRestypeSection') != "collapsed") {
                jQuery('#AdvancedSearchRestypeSection').show();
            }
        } else {
            jQuery('#AdvancedSearchRestypeSectionHead').hide();
            jQuery('#AdvancedSearchRestypeSection').hide();
        }
    }

    function advSearchShowHideSection(name,show) {
        // Show or hide sections
        console.debug('advSearchShowHide(' + name + ',' + show + ');');
        name = DOMPurify.sanitize(name);

        if (show) {
            jQuery('#AdvancedSearch' + name + 'SectionHead').show();
            if (getCookie('AdvancedSearch' + name + 'Section') != "collapsed") {
                jQuery('#AdvancedSearch' + name + 'Section').show();
            }
        } else {
            jQuery('#AdvancedSearch' + name + 'SectionHead').hide();
            jQuery('#AdvancedSearch' + name + 'Section').hide();
        }
    }

    jQuery(document).ready(function() {
        selectedtypes = ['<?php echo implode("','", array_filter($selectedtypes, fn($v) => (is_int_loose($v) || $v == "Global"))) ?>'];

        if (selectedtypes[0] === "") {
            selectedtypes.shift();
        }

        if (jQuery('input[name$="FeaturedCollections"]').is(":checked")) {
            console.debug("Showing fields for FeaturedCollections");
            // Check Featured Collections
            selectedtypes = ["Collections"];
            jQuery('#SearchFeaturedCollectionsCheckbox').prop('checked',true);

            advSearchShowHideSection('Global',false);
            advSearchShowHideSection('FeaturedCollections',true);
            advSearchShowHideSection('Resource',false);
            advSearchShowHideSection('Media',false);

            jQuery('#AdvancedSearchFeaturedCollectionsSectionHead').show();
            jQuery('#AdvancedSearchFeaturedCollectionsSection').show();
        }

        displayValidFields();

        jQuery('.SearchTypeCheckbox').change(function() {
            id = (this.name).substr(12);

            // Process checkbox change from unchecked to checked
            if (jQuery(this).is(":checked")) {
                if (id == "Global") {
                    // Global checked
                    selectedtypes = ["Global"];
                    // Global has been checked, check all other checkboxes
                    jQuery('.SearchTypeItemCheckbox').prop('checked',true);
                    //Uncheck Featured Collections
                    jQuery('#SearchFeaturedCollectionsCheckbox').prop('checked',false);

                    advSearchShowHideSection('Global',true);
                    advSearchShowHideSection('',false);
                    advSearchShowHideSection('FeaturedCollections',false);
                    advSearchShowHideSection('Resource',true);
                    advSearchShowHideSection('Media',true);
                    displayValidFields();
                } else if (id == "FeaturedCollections") {
                    console.debug("Showing fields for FeaturedCollections");
                    //Uncheck All checkboxes
                    jQuery('.SearchTypeCheckbox').prop('checked',false);
                    // Check Featured Collections
                    selectedtypes = ["Collections"];
                    jQuery('#SearchFeaturedCollectionsCheckbox').prop('checked',true);

                    advSearchShowHideSection('Global',false);
                    advSearchShowHideSection('FeaturedCollections',true);
                    advSearchShowHideSection('Resource',false);
                    advSearchShowHideSection('Media',false);

                    jQuery('#AdvancedSearchFeaturedCollectionsSectionHead').show();
                    jQuery('#AdvancedSearchFeaturedCollectionsSection').show();
                    displayValidFields();
                } else {
                    // Standard resource type checked
                    selectedtypes = jQuery.grep(selectedtypes, function(value) {return value != "FeaturedCollections";});

                    if (selectedtypes.length == resTypes.length) {
                        selectedtypes = ["Global"];
                    }

                    selectedtypes.push(id);
                    console.debug("Showing fields for selected types: " + selectedtypes);
                    jQuery('#SearchGlobal').prop('checked',false);
                    jQuery('#SearchFeaturedCollectionsCheckbox').prop('checked',false);
                    jQuery('#AdvancedSearchFeaturedCollectionsSectionHead').hide();
                    jQuery('#AdvancedSearchFeaturedCollectionsSection').hide();
                    advSearchShowHideSection('Global',true);
                    advSearchShowHideSection('FeaturedCollections',false);
                    advSearchShowHideSection('Resource',true);
                    advSearchShowHideSection('Media',true);
                    displayValidFields();
                }
            } else { // Process checkbox change from checked to unchecked
                if (id == "Global") {
                    // Global unchecked
                    selectedtypes = jQuery.grep(selectedtypes, function(value) {return value != "Global";});
                    console.debug("Showing fields for selected types");
                    selectedtypes=[];
                    jQuery('.SearchTypeItemCheckbox').prop('checked',false);
                } else if (id == "FeaturedCollections") {
                    advSearchShowHideSection('Global',true);
                    advSearchShowHideSection('FeaturedCollections',false);
                    advSearchShowHideSection('Resource',true);
                    advSearchShowHideSection('Media',true);
                    displayValidFields();
                } else {
                    // Standard resource type unchecked
                    advSearchShowHideSection('Global',true);
                    jQuery('#SearchGlobal').prop('checked',false);
                    advSearchShowHideSection('Resource',true);
                    advSearchShowHideSection('Media',true);
                    advSearchShowHideSection('FeaturedCollections',false);

                    if (selectedtypes == "Global") {
                        // Need to set all other types and unset this one
                        selectedtypes = resTypes;
                    }

                    selectedtypes = jQuery.grep(selectedtypes, function(value) {return value != id;});
                    displayValidFields();
                }
            }

            // End of checkbox change processing; update cookie with checkbox values
            SetCookie("advanced_search_section", selectedtypes);
            UpdateResultCount();
        }); // End of SearchTypeCheckbox change function call

        jQuery('.CollapsibleSectionHead').click(function() {
            cur = jQuery(this).next();
            cur_id = cur.attr("id");
            if (cur.is(':visible')) {
                SetCookie(cur_id, "collapsed");
                jQuery(this).removeClass('expanded');
                jQuery(this).addClass('collapsed');
            } else {
                SetCookie(cur_id, "expanded")
                jQuery(this).addClass('expanded');
                jQuery(this).removeClass('collapsed');
            }

            cur.slideToggle();

            return false;
        }).each(function() {
            cur_id = jQuery(this).next().attr("id");
            if (getCookie(cur_id) == "collapsed") {
                jQuery(this).next().hide();
                jQuery(this).addClass('collapsed');
            }
            else jQuery(this).addClass('expanded');
        });
    });
</script>

<iframe src="blank.html" name="resultcount" id="resultcount" style="visibility:hidden;float:right;" width=1 height=1></iframe>

<div class="BasicsBox">
    <h1><?php echo escape($archiveonly ? $lang["archiveonlysearch"] : $lang["advancedsearch"]); ?></h1>
    <p class="tight">
        <?php
        echo strip_paragraph_tags(strip_tags_and_attributes(text("introtext")));
        render_help_link("user/advanced-search");
        ?>
    </p>
    <form method="post" id="advancedform" action="<?php echo $baseurl ?>/pages/search_advanced.php">
        <?php generateFormToken("advancedform"); ?>
        <input type="hidden" name="submitted" id="submitted" value="yes">
        <input type="hidden" name="countonly" id="countonly" value="">

        <script type="text/javascript">
            var categoryTreeChecksArray = [];
            var updatingcount = false;

            function UpdateResultCount() {
                if (updatingcount) {
                    console.debug("Blocked from updating result count - search in progress");
                    return false;
                }

                var updatingcount=true;
                console.debug("Updating result count");
                // set the target of the form to be the result count iframe and submit
                document.getElementById("advancedform").target = "resultcount";
                document.getElementById("countonly").value = "yes";
                jQuery("#advancedform").submit();
                document.getElementById("advancedform").target = "";
                document.getElementById("countonly").value = "";
            }

            jQuery(document).ready(function() {
                jQuery('#advancedform').submit(function() {
                    var inputs = jQuery('#advancedform :input');
                    var hiddenfields = Array();

                    inputs.each(function() {
                        if (jQuery(this).parent().is(":hidden")) hiddenfields.push((this.name).substr(6));
                        });
                    jQuery("#hiddenfields").val(hiddenfields.toString());
                });
            });
        </script>

        <?php
        if ($advanced_search_buttons_top) {
            render_advanced_search_buttons();
        }

        if ($search_includes_resources && !hook("advsearchrestypes") && !$hide_search_resource_types) { ?>
            <div class="Question">
                <label><?php echo escape($lang["search-mode"]) ?></label>

                <?php
                $wrap = 0;
                $checked = false;

                if (
                    (!empty($selectedtypes[0]) && in_array("Global", $selectedtypes))
                    || (in_array("Global", $restypes) && empty($selectedtypes[0]))
                ) {
                    $checked = true;
                }
                ?>
                
                <table>
                    <tr>
                        <td valign=middle>
                            <input type=checkbox class="SearchTypeCheckbox" id="SearchGlobal" name="resourcetypeGlobal" value="yes" <?php echo $checked ? " checked" : ''; ?>>
                        </td>
                        <td valign=middle>
                            <?php echo escape($lang["resources-all-types"]) ; ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        </td>
                        <?php
                        $hiddentypes = array();
                        for ($n = 0; $n < count($types); $n++) {
                            if (in_array($types[$n]['ref'], $hide_resource_types)) {
                                continue;
                            }

                            $wrap++;

                            if ($wrap > 4) {
                                $wrap = 1;?></tr><tr><?php
                            }

                            $checked = false;
                            if (
                                (!empty($selectedtypes[0]) && (in_array("Global", $selectedtypes) || in_array($types[$n]["ref"], $selectedtypes)))
                                || (in_array("Global", $restypes) || in_array($types[$n]["ref"], $restypes)) && empty($selectedtypes[0])
                            ) {
                                $checked = true;
                            }
                            ?>
                            
                            <td valign=middle>
                                <input
                                    type=checkbox
                                    class="SearchTypeCheckbox SearchTypeItemCheckbox"
                                    name="resourcetype<?php echo $types[$n]["ref"]; ?>"
                                    value="yes"
                                    <?php if ($checked) { ?>
                                        checked
                                    <?php } else {
                                        $hiddentypes[] = $types[$n]["ref"];
                                    } ?>
                                >
                            </td>
                            <td valign=middle>
                                <?php echo escape($types[$n]["name"])?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            </td>
                            <?php
                        }

                        if ($search_includes_themes) {
                            ?>
                            </tr>
                            <tr>
                                <td>&nbsp;</td>
                            </tr>
                            <tr>
                                <td valign=middle>
                                    <input type=checkbox id="SearchFeaturedCollectionsCheckbox" class="SearchTypeCheckbox" name="resourcetypeFeaturedCollections" value="yes" <?php echo (in_array("FeaturedCollections", $restypes)) ? " checked" : ''; ?>>
                                </td>
                                <td valign=middle>
                                    <?php print $lang["themes"]; ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                </td>
                            <?php
                        }
                        ?>
                    </tr>
                </table>
                <div class="clearerleft"></div>
            </div>
            <?php
        }
        ?>

        <!-- Search across all fields -->
        <input type="hidden" id="hiddenfields" name="hiddenfields" value="">

        <div class="Question">
            <label for="allfields"><?php echo escape($lang["allfields"]) ?></label>
            <input class="SearchWidth" type=text name="allfields" id="allfields" value="<?php echo escape($allwords)?>" onchange="UpdateResultCount();">
            <div class="clearerleft"></div>
        </div>

        <h1
            class="AdvancedSectionHead CollapsibleSectionHead"
            id="AdvancedSearchGlobalSectionHead"
            <?php if (in_array("FeaturedCollections", $selectedtypes)) { ?>
                style="display: none;"
            <?php } ?>
        >
            <?php echo escape($lang["resourcetype-global_fields"]); ?>
        </h1>

        <div
            class="AdvancedSection"
            id="AdvancedSearchGlobalSection"
            <?php if (in_array("FeaturedCollections", $selectedtypes)) { ?>
                style="display: none;"
            <?php } ?>
        >
            <!-- Search for resource ID(s) -->
            <div class="Question">
                <label for="resourceids"><?php echo escape($lang["resourceids"]) ?></label>
                <input class="SearchWidth" type=text name="resourceids" id="resourceids" value="<?php echo escape(getval("resourceids", "")) ?>" onchange="UpdateResultCount();">
                <div class="clearerleft"></div>
            </div>

            <?php if (!$daterange_search) { ?>
                <div class="Question">
                    <label><?php echo escape($lang["bydate"]) ?></label>
                    <select name="basicyear" class="SearchWidth" style="width:120px;" onchange="UpdateResultCount();">
                        <option value=""><?php echo escape($lang["anyyear"]) ?></option>
                        <?php
                        $y = date("Y");
                        $y += $maxyear_extends_current;

                        for ($n = $y; $n >= $minyear; $n--) {
                            ?>
                            <option <?php echo ($n == $found_year) ? " selected" : ''; ?>><?php echo $n?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <select name="basicmonth" class="SearchWidth" style="width:120px;" onchange="UpdateResultCount();">
                        <option value=""><?php echo escape($lang["anymonth"]) ?></option>
                        <?php
                        for ($n = 1; $n <= 12; $n++) {
                            $m = str_pad($n, 2, "0", STR_PAD_LEFT);
                            ?>
                            <option <?php echo ($n == $found_month) ? " selected" : ''; ?> value="<?php echo $m; ?>">
                                <?php echo escape($lang["months_list"][$n - 1]); ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                    <select name="basicday" class="SearchWidth" style="width:120px;" onchange="UpdateResultCount();">
                        <option value=""><?php echo escape($lang["anyday"]) ?></option>
                        <?php
                        for ($n = 1; $n <= 31; $n++) {
                            $m = str_pad($n, 2, "0", STR_PAD_LEFT);
                            ?>
                            <option <?php echo ($n == $found_day) ? " selected" : ''; ?> value="<?php echo $m; ?>">
                                <?php echo $m?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                    <div class="clearerleft"></div>
                </div>
            <?php }

            # Fetch fields
            $fields = get_advanced_search_fields($archiveonly);
            $showndivide = false;

            # Preload resource types
            $rtypes     = array_column(get_resource_types(), 'ref');
            $all_rtypes = array_column(get_resource_types('', true, true), 'ref');

            $no_access_rtypes = array_values(array_diff($all_rtypes, $rtypes));
            $rtypecount = count($rtypes);

            // Order by resource types for displaying in sections
            $fieldorders = [];
            $fieldglobal = [];

            foreach ($fields as $key => $field) {
                $fieldorders[$field["ref"]]   = $field["order_by"];
                $field_rtypes_with_access = array_diff(explode(',', $field['resource_types'] ?? ''), $no_access_rtypes);
                $fieldglobal[$field["ref"]] = $fields[$key]['global'] = ($field["global"] == 1 || count($field_rtypes_with_access) == $rtypecount) ? 1 : 0;
            }

            array_multisort($fieldglobal, SORT_DESC, $fieldorders, SORT_ASC, $fields);

            for ($n = 0; $n < count($fields); $n++) {
                # Show a dividing header for resource type specific fields
                $field_rtypes_with_access = array_diff(explode(',', $fields[$n]['resource_types'] ?? ''), $no_access_rtypes);
                if (($fields[$n]["global"] != 1 && count($field_rtypes_with_access) != $rtypecount) && !$showndivide) {
                    $showndivide = true;
                    ?>
                    </div>
                    <h1
                        class="AdvancedSectionHead CollapsibleSectionHead"
                        id="AdvancedSearchRestypeSectionHead"
                        <?php if (in_array("Global", $restypes)) { ?>
                            style="display: none;"
                        <?php } ?>
                    >
                        <?php echo escape($lang["typespecific"]); ?>
                    </h1>
                    <div
                        class="AdvancedSection"
                        id="AdvancedSearchRestypeSection"
                        <?php if (in_array("Global", $restypes)) { ?>
                            style="display: none;"
                        <?php } ?>
                    >
                    <?php
                }

                # Work out a default value
                if (array_key_exists($fields[$n]["name"], $values)) {
                    $value = $values[$fields[$n]["name"]];
                } else {
                    $value = "";
                }

                # Clearbutton means resetform
                $resetform = getval("resetform", "");

                if ($resetform != "") {
                    $value = "";
                }

                # Render this field
                render_search_field($fields[$n], $fields, $value, true, 'SearchWidth', false, array(), $searched_nodes, $resetform);
            }
            debug("QUESTION NEXT n=" . $n);
            ?>

        </div>

        <!-- Resource section-->
        <h1 class="AdvancedSectionHead CollapsibleSectionHead ResourceSectionHead" id="AdvancedSearchResourceSectionHead">
            <?php echo escape($lang["advancedsearch_resource_section"]); ?>
        </h1>

        <div class="AdvancedSection ResourceSection" id="AdvancedSearchResourceSection">
            <!-- Full text search (uses built in MySQL indexing) -->
            <div class="Question">
                <label for="<?php echo FULLTEXT_SEARCH_PREFIX; ?>"><?php echo escape($lang["search_full_text"]); ?></label>
                <input class="SearchWidth" type=text name="<?php echo FULLTEXT_SEARCH_PREFIX; ?>" id="<?php echo FULLTEXT_SEARCH_PREFIX; ?>" value="<?php echo escape($full_text_search); ?>" onchange="UpdateResultCount();">
                <div class="clearerleft"></div>
            </div>

            <?php
            if ($advanced_search_archive_select) {
                // Create an array for the archive states
                $available_archive_states = array();
                $all_archive_states = array_merge(range(-2, 3), $additional_archive_states);

                foreach ($all_archive_states as $archive_state_ref) {
                    if (!checkperm("z" . $archive_state_ref)) {
                        $available_archive_states[$archive_state_ref] = (isset($lang["status" . $archive_state_ref])) ? $lang["status" . $archive_state_ref] : $archive_state_ref;
                    }
                }
                ?>

                <div class="Question" id="question_archive">
                    <label><?php echo escape($lang["status"]) ?></label>
                    <table cellpadding=2 cellspacing=0>
                        <?php foreach ($available_archive_states as $archive_state => $state_name) { ?>
                            <tr>
                                <td width="1">
                                    <input
                                        type="checkbox"
                                        name="archive[]"
                                        value="<?php echo $archive_state; ?>"
                                        onchange="UpdateResultCount();"
                                        <?php if (in_array($archive_state, $selected_archive_states)) { ?>
                                            checked
                                        <?php } ?>
                                    >
                                </td>
                                <td><?php echo escape(i18n_get_translated($state_name)); ?>&nbsp;</td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>
                <div class="clearerleft"></div>
                <?php
            } else {
                ?>
                <input type="hidden" name="archive" value="<?php echo escape($archive)?>">
                <?php
            }

            if (checkperm("v")) {
                render_question_div("search_advanced_access_question", function () use ($lang, $access) {
                    ?>
                    <label for="search_advanced_access"><?php echo escape($lang["access"]); ?></label>
                    <select id="search_advanced_access" class="SearchWidth" name="access" onchange="UpdateResultCount();">
                        <option><?php echo escape($lang["all"]) ; ?></option>
                        <?php
                        foreach (range(0, 2) as $access_level) {
                            $label = escape($lang["access{$access_level}"]);
                            $extra_attributes = (!is_null($access) && $access_level == $access ? " selected" : "");
                            echo render_dropdown_option($access_level, $label, array(), $extra_attributes);
                        }
                        ?>
                    </select>
                    <?php
                });
            }

            if ($advanced_search_contributed_by && !is_anonymous_user()) {
                ?>
                <div class="Question">
                    <label><?php echo escape($lang["contributedby"]) ; ?></label>

                    <?php
                    $single_user_select_field_value = $properties_contributor;
                    $single_user_select_field_id = 'properties_contributor';
                    $single_user_select_field_onchange = 'UpdateResultCount();';
                    $userselectclass = "searchWidth";
                    include "../include/user_select.php";
                    ?>

                    <script>
                        jQuery('#properties_contributor').change(function() {
                            UpdateResultCount();
                        });
                    </script>

                    <?php
                    unset($single_user_select_field_value);
                    unset($single_user_select_field_id);
                    unset($single_user_select_field_onchange);
                    ?>
                </div>
                <?php
            }
            ?>
        </div>

        <?php if ($search_includes_themes) { ?>
            <h1
                class="AdvancedSectionHead CollapsibleSectionHead"
                id="AdvancedSearchFeaturedCollectionsSectionHead"
                <?php if (!in_array("FeaturedCollections", $selectedtypes)) { ?>
                    style="display: none;"
                <?php } ?>
            >
                <?php echo escape($lang["themes"]) ; ?>
            </h1>

            <div
                class="AdvancedSection"
                id="AdvancedSearchFeaturedCollectionsSection"
                <?php if (!in_array("FeaturedCollections", $selectedtypes)) { ?>
                    style="display: none;"
                <?php } ?>
            >
                <?php
                // Questions may have already be rendered for earlier sections
                // If so then note the starting number for any questions yet to be rendered in subsequent secions
                $start_n = $n;
                $fields = array_merge($fields, get_advanced_search_collection_fields());

                for ($n = $start_n; $n < count($fields); $n++) {
                    # Work out a default value
                    if (array_key_exists($fields[$n]["name"], $values)) {
                        $value = $values[$fields[$n]["name"]];
                    } else {
                        $value = "";
                    }

                    # Clearbutton means resetform
                    $resetform = getval("resetform", "");
                    if ($resetform != "") {
                        $value = "";
                    }
                    # Render this field
                    render_search_field($fields[$n], $fields, $value, true, "SearchWidth", false, array(), $searched_nodes, $resetform);
                }
                ?>
            </div>
            <?php
        }

        if ($advanced_search_media_section) {
            ?>
            <h1 class="AdvancedSectionHead CollapsibleSectionHead" id="AdvancedSearchMediaSectionHead">
                <?php echo escape($lang["media"]) ; ?>
            </h1>
            <div class="AdvancedSection" id="AdvancedSearchMediaSection">
                <?php
                render_split_text_question($lang["pixel_height"], array('media_heightmin' => $lang['from'],'media_heightmax' => $lang['to']), $lang["pixels"], true, " class=\"stdWidth\" OnChange=\"UpdateResultCount();\"", array('media_heightmin' => $media_heightmin,'media_heightmax' => $media_heightmax));

                render_split_text_question($lang["pixel_width"], array('media_widthmin' => $lang['from'],'media_widthmax' => $lang['to']), $lang["pixels"], true, " class=\"stdWidth\" OnChange=\"UpdateResultCount();\"", array('media_widthmin' => $media_widthmin,'media_widthmax' => $media_widthmax));

                render_split_text_question($lang["filesize"], array('media_filesizemin' => $lang['from'],'media_filesizemax' => $lang['to']), $lang["megabyte-symbol"], false, " class=\"stdWidth\" OnChange=\"UpdateResultCount();\"", array('media_filesizemin' => $media_filesizemin,'media_filesizemax' => $media_filesizemax));

                render_text_question($lang["file_extension_label"], "media_fileextension", "", false, " class=\"SearchWidth\" OnChange=\"UpdateResultCount();\"", $media_fileextension);

                render_dropdown_question($lang["previewimage"], "properties_haspreviewimage", array("" => "","1" => $lang["yes"],"0" => $lang["no"]), $properties_haspreviewimage, " class=\"SearchWidth\" OnChange=\"UpdateResultCount();\"");

                render_dropdown_question(
                    $lang["orientation"],
                    "properties_orientation",
                    array(
                        ""          => "",
                        "portrait"  => $lang["portrait"],
                        "landscape" => $lang["landscape"],
                        "square"    => $lang["square"]
                    ),
                    $properties_orientation,
                    "class=\"SearchWidth\" onchange=\"UpdateResultCount();\""
                );
                ?>
            </div><!-- End of AdvancedSearchMediaSection -->
            <?php
        }

        render_advanced_search_buttons();
        // show result count as it stands ?>
    </form>
</div> <!-- BasicsBox -->

<?php
if ($archive !== 0) {
    ?>
    <script>
        jQuery(document).ready(function() {
            jQuery("input").keypress(function(event) {
                if (event.which == 13) {
                    event.preventDefault();
                    jQuery("#advancedform").submit();
                }
            });
        });
    </script>
    <?php
}

include "../include/footer.php";
