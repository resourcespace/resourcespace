<?php
include_once 'render_functions.php';

# Store key variables to revert later so that we don't interfere with values that still need to be processed by search.php
$stored_restypes = (isset($restypes) ? $restypes : '');
$stored_search = (isset($search) ? $search : '');
$stored_quicksearch = (isset($quicksearch) ? $quicksearch : '');
$stored_category_tree_add_parents = $category_tree_add_parents;

$ssearchhiddenfields = isset($_COOKIE['ssearchhiddenfields']) ? $_COOKIE['ssearchhiddenfields'] : "";
$ssearchhiddenfieldsarray = explode(',', $ssearchhiddenfields);

if ($simple_search_reset_after_search) {
    $restypes    = '';
    $search      = '';
    $quicksearch = '';
} else {
    # pull values from cookies if necessary, for non-search pages where this info hasn't been submitted
    if (!isset($restypes)) {
        $restypes = isset($_COOKIE['restypes']) ? $_COOKIE['restypes'] : "";
    }
    if (!isset($search) || false !== strpos($search, '!')) {
        $quicksearch = (isset($_COOKIE['search']) ? $_COOKIE['search'] : '');
    } else {
        $quicksearch = $search;
    }
}

$origsearch = $quicksearch;

if ($basic_simple_search) {
    $restypes    = '';
}

if ($hide_search_resource_types) {
    $restypes = '';
}

if (!isset($internal_share_access)) {
    // Set a flag for logged in users if $external_share_view_as_internal is set and logged on user is accessing an external share
    $internal_share_access = internal_share_access();
}

# Load the basic search fields, so we know which to strip from the search string
$fields = get_simple_search_fields();
$simple_fields = array();

for ($n = 0; $n < count($fields); $n++) {
    $simple_fields[] = $fields[$n]["name"];
}

# Also strip date related fields.
$simple_fields[] = "basicyear";
$simple_fields[] = "basicmonth";
$simple_fields[] = "basicday";

# Check for fields with the same short name and add to an array used for deduplication.
$f = array();
$duplicate_fields = array();

for ($n = 0; $n < count($fields); $n++) {
    if (in_array($fields[$n]["name"], $f)) {
        $duplicate_fields[] = $fields[$n]["name"];
    }
    $f[] = $fields[$n]["name"];
}

# Process all keywords, putting set fieldname/value pairs into an associative array ready for setting later.
# Also build a quicksearch string.
$quicksearch    = refine_searchstring($quicksearch);

if (preg_match('/^[^-][^\\s]+$/', $quicksearch)) {
    $keywords   = [$quicksearch];
} else {
    $keywords   = split_keywords($quicksearch, false, false, false, false, true);
}

$set_fields     = array();
$simple         = array();
$searched_nodes = array();
$initial_tags = array();

# Check if any negative node searches in any of the keywords,
# if there is bypass the node expansion/text replacement/OR syntax generation
$negative_node_search_check = preg_match("/@{2}!/", $quicksearch);

for ($n = 0; $n < count($keywords); $n++) {
    if (trim($keywords[$n]) != "") {
        $quoted_string = (substr($keywords[$n], 0, 1) == "\""  || substr($keywords[$n], 0, 2) == "-\"" ) && substr($keywords[$n], -1, 1) == "\"";
        if (!$quoted_string && strpos($keywords[$n], ":") !== false && substr($keywords[$n], 0, 11) != "!properties") {
            $s = explode(":", $keywords[$n]);
            if (isset($set_fields[$s[0]])) {
                $set_fields[$s[0]] .= " " . $s[1];
            } else {
                $set_fields[$s[0]] = $s[1];
                $i = $n + 1;
                while (
                    $i < count($keywords)
                    && strpos($keywords[$i], ":") === false
                    && strpos($keywords[$i], NODE_TOKEN_PREFIX) === false
                ) {
                    $set_fields[$s[0]] .= " " . $keywords[$i];
                    $i++;
                }
                $n = $i - 1;
            }
            if (!in_array($s[0], $simple_fields)) {
                $simple[] = trim($keywords[$n]);
                $initial_tags[] = trim($keywords[$n]);
            }
        }

        // Nodes search
        elseif (strpos($keywords[$n], NODE_TOKEN_PREFIX) !== false  && 0 === $negative_node_search_check) {
            $nodes = resolve_nodes_from_string($keywords[$n]);
            foreach ($nodes as $node) {
                $searched_nodes[] = $node;
            }

            $searched_nodes = array_unique($searched_nodes);
            $simpletext_count = count($simple);
            $initial_tag_count = count($initial_tags);
            foreach ($searched_nodes as $searched_node_index => $searched_node) {
                $node = array();

                if (!get_node($searched_node, $node)) {
                    continue;
                }

                $field_index = array_search($node['resource_type_field'], array_column($fields, 'ref'));

                if (false === $field_index) { // Node is not from a simple search field
                    $fieldsearchterm = rebuild_specific_field_search_from_node($node);

                    if (strpos(" ", $fieldsearchterm) !== false) {
                        $fieldsearchterm = "\"" . $fieldsearchterm . "\"";
                    }

                    if (!isset($all_fields)) {
                        $all_fields = get_resource_type_fields();
                    }

                    $all_fields_index = array_search($node['resource_type_field'], array_column($all_fields, 'ref'));
                    $field_name = $all_fields[$all_fields_index]["name"];
                    if (isset($last_field_name) && $last_field_name == $field_name && (($all_fields[$all_fields_index]["type"] == FIELD_TYPE_CHECK_BOX_LIST && !$checkbox_and) ||  ($all_fields[$all_fields_index]["type"] == FIELD_TYPE_DYNAMIC_KEYWORDS_LIST))) {
                        // Append in order to construct the field:value1;value2 syntax used for an OR search in the same field
                        $fieldsearchterm = substr($fieldsearchterm, strpos($fieldsearchterm, ":") + 1);

                        if (!isset($simple[$simpletext_count])) {
                            $simple[$simpletext_count] = "";
                        }
                        $simple[$simpletext_count] .= ";" . $fieldsearchterm;
                        if (!isset($initial_tags[$initial_tag_count])) {
                            $initial_tags[$initial_tag_count] = "";
                        }
                        $initial_tags[$initial_tag_count] .= ";" . $fieldsearchterm;

                        unset($searched_nodes[$searched_node_index]);
                    } else {
                        $simple[$simpletext_count] = $fieldsearchterm;
                        $initial_tags[] = $fieldsearchterm;

                        unset($searched_nodes[$searched_node_index]);
                    }

                    // Store the field name so we can check for ORs on same field
                    $last_field_name = $field_name;
                    continue;
                }

                $searched_field = $fields[$field_index];

                // We already have a field on search bar so remove this keyword from search box
                if ($searched_field['simple_search']) {
                    $quicksearch = str_replace(NODE_TOKEN_PREFIX . $searched_node, '', $quicksearch);
                }
            }
        } else {
            # Plain text (non field) search.
            $simple[] = trim($keywords[$n]);
            $initial_tags[] = trim($keywords[$n]);
        }
    }
}

# Set the text search box to the stripped value.
$simple = array_unique($simple);
$initial_tags = array_unique($initial_tags);
$quicksearch = join(" ", trim_array($simple));

# Set the predefined date fields
$found_year = "";
if (isset($set_fields["basicyear"])) {
    $found_year = $set_fields["basicyear"];
}

$found_month = "";
if (isset($set_fields["basicmonth"])) {
    $found_month = $set_fields["basicmonth"];
}

$found_day = "";
if (isset($set_fields["basicday"])) {
    $found_day = $set_fields["basicday"];
}

$selected_search_tab = getval("selected_search_tab", "search");
?>

<script>
    var categoryTreeChecksArray = [];
</script>

<div id="SearchBox">
    <div id="SearchBarTabsContainer">
        <a href="#" onclick="selectSearchBarTab('search');">
            <div class="SearchBarTab SearchTab <?php echo ($selected_search_tab === "search") ? "SearchBarTabSelected" : ""; ?>">
                <i class="icon-search "></i>
                <?php echo escape($lang["searchbutton"]); ?>
            </div>
        </a>
        <?php if ($browse_bar) { ?>
            <a href="#" onclick="selectSearchBarTab('browse');" >
                <div class="SearchBarTab BrowseTab <?php echo ($selected_search_tab === "browse") ? "SearchBarTabSelected" : ""; ?>">
                    <i class="icon-list "></i>
                    <?php echo escape($lang["browse_bar_text"]); ?>
                </div>
            </a>
        <?php } ?>
    </div>

    <?php if (checkperm("s") && (!isset($k) || $k == "" || $internal_share_access)) { ?>
        <div id="SearchBoxPanel">
            <div class="SearchSpace" id="searchspace"> 
                <form
                    id="simple_search_form"
                    method="post"
                    action="<?php echo $baseurl; ?>/pages/search.php"
                    onsubmit="return CentralSpacePost(this,true);"
                >
                    <?php generateFormToken("simple_search_form"); ?>
                    <input 
                        id="ssearchbox"
                        name="search"
                        type="text"
                        class="SearchWidth"
                        value="<?php echo escape(stripslashes($quicksearch)); ?>"
                        placeholder="<?php echo escape($lang["searchbutton"]); ?>"
                        aria-label="<?php echo escape($lang["simplesearch"]); ?>"
                    >
                    <input
                        id="ssearchhiddenfields"
                        name="ssearchhiddenfields"
                        type="hidden"
                        value="<?php echo escape($ssearchhiddenfields); ?>"
                    >
                    <button
                        class="icon-search search-icon"
                        type="submit"
                        alt="<?php echo escape($lang['searchbutton']); ?>"
                        title="<?php echo escape($lang['searchbutton']); ?>"
                    ></button>

                    <script>
                        <?php
                        $autocomplete_src = '';
                        if ($autocomplete_search) {
                            $autocomplete_src = "{$baseurl}/pages/ajax/autocomplete_search.php";
                        }

                        if ($simple_search_pills_view) {
                            // $initial_tags is used for reloading search bar so that the tags will remain the same otherwise separate tags can become one big tag
                            $initial_tags = (isset($initial_tags) ? $initial_tags : array());
                            ?>
                            jQuery('#ssearchbox').tagEditor({
                                'initialTags': <?php echo json_encode($initial_tags); ?>,
                                'delimiter': '<?php echo TAG_EDITOR_DELIMITER; ?>',
                                'forceLowercase': false,
                                'autocomplete': {
                                    'source': '<?php echo $autocomplete_src; ?>',
                                    'minLength:': 3,
                                },
                                onChange: function(field, editor, tags) {
                                    jQuery(document).keyup(function(event) {
                                        if (event.key == 'Enter' && event.which === 13) {
                                            document.getElementById("searchbutton").click();
                                        }
                                    });
                                }
                            });

                            // Decide when to add tags:
                            // if space addTag
                            // if "word" then addTag
                            // don't do anything if open " but not closed
                            jQuery('ul.tag-editor').keydown(function(e) {
                                var key = e.keyCode || e.which;
                                var add_tag_flag = false;

                                // Get new tag value which is not yet finished/ rendered as a pill
                                var existing_tags = jQuery('#ssearchbox').tagEditor('getTags')[0].tags;
                                var all_tags = jQuery('.tag-editor-tag:not(.deleted)', this).map(function(i, e) {
                                    var val = jQuery.trim(jQuery(this).hasClass('active') ? jQuery(this).find('input').val() : jQuery(e).text());

                                    if (val) {
                                        return val;
                                    }
                                }).get();

                                var new_tag = (array_diff(existing_tags, all_tags)[0] || '');

                                // Find how many double quotes we have in our tag
                                // 1 => spaces are allowed
                                // 2 => add tag
                                var double_quotes_occurences = (new_tag.match(/"/g) || []).length;

                                // 32 is keyCode for " " (spacebar)
                                if (key == 32 && double_quotes_occurences == 0) {
                                    add_tag_flag = true;
                                }
                                // 50 is keyCode for " (double quotes)
                                else if (key == 50 && double_quotes_occurences == 2) {
                                    add_tag_flag = true;
                                }

                                if (add_tag_flag) {
                                    jQuery('#ssearchbox').tagEditor('addTag', new_tag);
                                }

                                return;
                            });

                            <?php
                        } else {
                            ?>
                            jQuery(document).ready(function () {
                                jQuery('#ssearchbox').autocomplete({
                                    source: "<?php echo $autocomplete_src; ?>",
                                    minLength: 3,
                                });
                                
                                <?php if (!$basic_simple_search) { ?>
                                    // Ensure any previously hidden search fields remain hidden
                                    SimpleSearchFieldsHideOrShow();
                                <?php } ?>
                            });
                            <?php
                        }
                        ?>
                    </script>

                    <?php
                    $types = get_resource_types("", true, false, true);

                    $simpleSearchFieldsAreHidden = hook("simplesearchfieldsarehidden");
                    hook("aftersearchbox");

                    if (!$basic_simple_search && !$hide_search_resource_types) {
                        # More than 5 types? Always display the 'select all' option.
                        if (count($types) > 5) {
                            $searchbar_selectall = true;
                        }
                        ?>

                        <input type="hidden" name="resetrestypes" value="yes">
                        <div id="searchbarrt" <?php echo $simpleSearchFieldsAreHidden ? 'style="display:none;"' : ''; ?>>
                            <?php if ($searchbar_selectall) { ?>
                                <script type="text/javascript"> 
                                    function resetTickAll() {
                                        var checkcount = 0;
                                        // set tickall to false, then check if it should be set to true.
                                        jQuery('#rttickallres').prop('checked',false);

                                        var tickboxes = jQuery('#simple_search_form .tickbox');
                                            jQuery(tickboxes).each(function (elem) {
                                                if (tickboxes[elem].checked) {
                                                    checkcount = checkcount + 1;
                                                }
                                            });

                                        if (checkcount == tickboxes.length) { 
                                            jQuery('#rttickallres').prop('checked',true);
                                        }    
                                    }

                                    function resetTickAllColl() {
                                        var checkcount = 0;
                                        // set tickall to false, then check if it should be set to true.
                                        jQuery('#rttickallcoll').prop('checked',false);
                                        var tickboxes = jQuery('#simple_search_form .tickboxcoll');
                                            jQuery(tickboxes).each(function (elem) {
                                                if (tickboxes[elem].checked) {
                                                    checkcount = checkcount + 1;
                                                }
                                            });

                                        if (checkcount == tickboxes.length) {
                                            jQuery('#rttickallcoll').prop('checked',true);
                                        }
                                    }
                                </script>

                                <div class="tick">
                                    <input
                                        type='checkbox'
                                        id='rttickallres'
                                        name='rttickallres'
                                        checked
                                        onclick='jQuery("#simple_search_form .tickbox").each(function(index,Element) {jQuery(Element).prop("checked",(jQuery("#rttickallres").prop("checked")));}); SimpleSearchFieldsHideOrShow(true);'
                                    />
                                        &nbsp;
                                        <?php echo escape($lang['allresourcessearchbar']); ?>
                                </div>
                                <?php
                            }

                            $rt = explode(",", @$restypes);
                            $clear_function = "SetCookie('search','');SetCookie('restypes','');SetCookie('ssearchhiddenfields','');SetCookie('saved_offset','');SetCookie('saved_archive','');";
                            hook('clearsearchcookies');

                            # Render resource type checkbox inputs
                            for ($n = 0; $n < count($types); $n++) {
                                if (in_array($types[$n]['ref'], $hide_resource_types)) {
                                    continue;
                                }

                                $tickBoxClass = "tick";
                                $inputBoxClass = "tickbox";
                                $resetTickAllCall = "";
                                $clear_function .= "jQuery('#TickBox" . $types[$n]["ref"] . "').prop('checked',true);";

                                if ($searchbar_selectall) {
                                    $tickBoxClass .= " tickindent";
                                    $resetTickAllCall .= "resetTickAll();";
                                    $clear_function .= "resetTickAll();";
                                }
                                ?>

                                <div class="<?php echo $tickBoxClass; ?>">
                                    <input
                                        class="<?php echo $inputBoxClass; ?>"
                                        id="TickBox<?php echo (int) $types[$n]["ref"]; ?>" 
                                        type="checkbox"
                                        value="yes"
                                        name="resource<?php echo (int) $types[$n]["ref"]; ?>"  
                                        <?php if (((count($rt) == 1) && ($rt[0] == "")) || ($restypes == "Global") || (in_array($types[$n]["ref"], $rt))) { ?>
                                            checked="checked"
                                        <?php } ?> 
                                        onclick="SimpleSearchFieldsHideOrShow(true);<?php echo $resetTickAllCall; ?>"
                                    >
                                    <label for="TickBox<?php echo (int) $types[$n]["ref"]; ?>">
                                        <i class="icon-<?php echo escape($types[$n]["icon"] != "" ? $types[$n]["icon"] : LUCIDE_EXTENSIONS["default"]); ?>"></i>    
                                        <?php echo "&nbsp;" . escape($types[$n]["name"]); ?>
                                    </label>
                                </div>

                                <?php
                            }
                            # End of rendering for resource type checkbox inputs
                            ?>
                            
                            <div class="spacer"></div>
                            <?php if ($searchbar_selectall && $search_includes_themes) { ?>
                                <div class="tick">
                                    <input
                                        type='checkbox'
                                        id='rttickallcoll'
                                        name='rttickallcoll'
                                        checked
                                        onclick='jQuery("#simple_search_form .tickboxcoll").each(function(index,Element) {jQuery(Element).prop("checked",(jQuery("#rttickallcoll").prop("checked")));}); SimpleSearchFieldsHideOrShow(true);'
                                    />
                                        &nbsp;
                                        <?php echo escape($lang['allcollectionssearchbar']); ?>
                                </div>
                                <?php
                            }
                            
                            if ($clear_button_unchecks_collections) {
                                $colcheck = "false";
                            } else {
                                $colcheck = "true";
                            }

                            if ($search_includes_themes) { ?>
                                <div class="tick <?php echo $searchbar_selectall ? 'tickindent' : ''; ?>">
                                    <input
                                        class="tickboxcoll"
                                        id="TickBoxFeaturedCollections"
                                        type="checkbox"
                                        name="resourceFeaturedCollections"
                                        value="yes"
                                        <?php if (((count($rt) == 1) && ($rt[0] == "")) || (in_array("FeaturedCollections", $rt))) { ?>
                                            checked="checked"
                                        <?php } ?>
                                        onClick="SimpleSearchFieldsHideOrShow(true);<?php echo $searchbar_selectall ? 'resetTickAllColl();' : ''; ?>"
                                    />
                                    <label for="TickBoxFeaturedCollections">&nbsp;<?php echo escape($lang["findcollectionthemes"]); ?></label>
                                </div>
                                <?php
                                $clear_function .= "jQuery('#TickBoxFeaturedCollections').prop('checked'," . $colcheck . ");";

                                if ($searchbar_selectall) {
                                    $clear_function .= "resetTickAllColl();";
                                }
                            }
                    } elseif ($restypes == '') {
                        # We still need a way to pass restypes based on simple search settings or things like search crumbs will be incorrect
                        if ($search_includes_resources) {
                            for ($t = 0; $t < count($types); $t++) {
                                $restypes .= ($restypes == '' ? '' : ',') . $types[$t]['ref'];
                            }
                        }

                        if ($search_includes_themes) {
                            $restypes .= ($restypes == '' ? '' : ',') . "FeaturedCollections";
                        }
                        ?>

                        <input type="hidden" name="restypes" id="restypes" value="<?php echo escape($restypes); ?>" />
                        <?php
                    }

                    if ($searchbar_selectall) {
                        ?>
                        <script type="text/javascript">resetTickAll();resetTickAllColl();</script>
                        <?php
                    }

                    if (!$basic_simple_search && !$hide_search_resource_types) {
                        ?>
                        </div>
                        <?php
                    }

                    hook("searchfiltertop");

                    $searchbuttons = "<div class=\"SearchItem\" id=\"simplesearchbuttons\">";

                    $cleardate = "";
                    if ($simple_search_date) {
                        $cleardate .= " document.getElementById('basicyear').value='';document.getElementById('basicmonth').value='';" ;
                    }

                    if ($searchbyday && $simple_search_date) {
                        $cleardate .= "document.getElementById('basicday').value='';";
                    }

                    if (!$basic_simple_search) {
                        $searchbuttons .= "<input name=\"Clear\" id=\"clearbutton\" class=\"searchbutton\" type=\"button\" value=\"" . escape($lang['clearbutton']) . "\" onClick=\"unsetCookie('search_form_submit','" . $baseurl_short . "');";

                        if ($simple_search_pills_view) {
                            $searchbuttons .= "removeSearchTagInputPills(jQuery('#ssearchbox'));";
                        }

                        # Clear the standard fields
                        $searchbuttons .= "document.getElementById('ssearchbox').value='';" . $cleardate;

                        if ($resourceid_simple_search) {
                            $searchbuttons .= " document.getElementById('searchresourceid').value='';";
                        }

                        $searchbuttons .= "ResetTicks();SimpleSearchFieldsHideOrShow();\"/>";
                    } else {
                        if (!$simple_search_pills_view) {
                            $searchbuttons .= '<input name="Clear" id="clearbutton" class="searchbutton" type="button" value="' . escape($lang['clearbutton']) . '" onClick=" document.getElementById(\'ssearchbox\').value=\'\';"/>';
                        } else {
                            $searchbuttons .= '<input name="Clear" id="clearbutton" class="searchbutton" type="button" value="' . escape($lang['clearbutton']) . '" onClick="removeSearchTagInputPills(jQuery(\'#ssearchbox\'));" />';
                        }
                    }

                    $searchbuttons .= "<input name=\"Submit\" id=\"searchbutton\" class=\"searchbutton\" type=\"submit\" value=\"" . escape($lang['searchbutton']) . "\" onclick=\"SimpleSearchFieldsHideOrShow();\" />";
                    $searchbuttons .= '<input type="button" id="Rssearchexpand" class="searchbutton" style="display:none;" value="' . escape($lang['responsive_more']) . '">';
                    $searchbuttons .= "</div>";

                    if (!$basic_simple_search) {
                        // Include simple search items (if any)
                        global $clear_function, $simple_search_show_dynamic_as_dropdown;

                        $optionfields = array();
                        $rendered_names = array();
                        $rendered_refs = array();
                        $has_value = array();

                        for ($n = 0; $n < count($fields); $n++) {
                            $render = true;
                            # Render duplicate fields only once.
                            if (in_array($fields[$n]["name"], $duplicate_fields) && in_array($fields[$n]["name"], $rendered_names)) {
                                $render = false;
                            }

                            if ($render) {
                                $rendered_names[] = $fields[$n]["name"];

                                # Fetch current value
                                $value = '';

                                if (isset($set_fields[$fields[$n]["name"]])) {
                                    $value = $set_fields[$fields[$n]["name"]];
                                }

                                $fields[$n]['value'] = $value;

                                if ($value !== '') {
                                    $has_value[] = $fields[$n]['ref'];
                                }

                                render_search_field($fields[$n], $fields, $value, false, 'SearchWidth', true, array(), $searched_nodes, false, $simpleSearchFieldsAreHidden);
                            }
                        }
                        ?>

                        <script type="text/javascript">
                            function FilterBasicSearchOptions(clickedfield,resourcetypes) {
                                if (typeof resourcetypes !== 'undefined' && resourcetypes != 0) {
                                    resourcetypes = resourcetypes.toString().split(",");
                                    // When selecting resource type specific fields, automatically untick all other resource types, because selecting something from this field will never produce resources from the other resource types.
                                    allselected = false;

                                    if (jQuery('#rttickallres').prop('checked')) {
                                        allselected = true;
                                        // Always untick the Tick All box
                                        if (jQuery('#rttickallres')) {
                                            jQuery('#rttickallres').prop('checked', false);
                                        }
                                    }

                                    <?php for ($n = 0; $n < count($types); $n++) { ?>
                                        if (resourcetypes.indexOf('<?php echo (int) $types[$n]["ref"]; ?>') == -1) {
                                            jQuery("#TickBox<?php echo (int) $types[$n]["ref"]; ?>").prop('checked', false);
                                        } else if (allselected) {
                                            jQuery("#TickBox<?php echo (int) $types[$n]["ref"]; ?>").prop('checked', true);
                                        }
                                    <?php } ?>

                                    // Hide any fields now no longer relevant.  
                                    SimpleSearchFieldsHideOrShow(false);
                                }
                            }

                            function SimpleSearchFieldsHideOrShow(resetvalues) {
                                // ImageBank is selection has already dealt with hiding of elements, so just reset the searchfields
                                if (jQuery("#SearchImageBanks :selected").text().length > 0)  { 
                                    SimpleSearchFieldsResetValues(true); // true = include globals
                                    return; 
                                }

                                if (resetvalues) {
                                    console.debug("Resetting values");
                                    SimpleSearchFieldsResetValues(false); // false = exclude globals
                                }

                                var ssearchhiddenfields = [];
                                ssearchhiddenfields.length = 0;
                                document.getElementById('ssearchhiddenfields').value='';

                                <?php
                                # Show or hide each searchfield depending on whether the resource type for this field is selected
                                # Exclude global fields
                                for ($n = 0; $n < count($fields); $n++) {
                                    # Duplicate fields are skipped
                                    # Fields subjected to display conditioning are skipped
                                    if (
                                        !in_array($fields[$n]["name"], $duplicate_fields)
                                        && (
                                            empty($simple_search_display_condition)
                                            || (
                                                !empty($simple_search_display_condition)
                                                && !in_array($fields[$n]['ref'], $simple_search_display_condition)
                                            )
                                        )
                                        && $fields[$n]["global"] != 1
                                    ) {
                                        // Process resource type checkboxes, whether checked or unchecked
                                        $hideconditions =  [];
                                        $showconditions =  [];
                                        $notypeconditions = [];
                                        // Check if resource types are valid for field
                                        $validrestypes = explode(",", (string)$fields[$n]["resource_types"]);
                                        $invalidrestypes = array_diff(array_column($types, "ref"), array_merge($hide_resource_types, $validrestypes));

                                        // Don't hide if any of the valid resource types are checked AND none of the invalid types are checked
                                        foreach ($validrestypes as $validrestype) {
                                            $showconditions[] = "jQuery('#TickBox" . (int) $validrestype . "').prop('checked') == false";
                                        }

                                        foreach ($invalidrestypes as $invalidrestype) {
                                            $hideconditions[] = "jQuery('#TickBox" . (int) $invalidrestype . "').prop('checked')";
                                        }

                                        foreach (array_diff(array_column($types, "ref"), $hide_resource_types) as $displayedrestype) {
                                            // Check to field if no resource types are selected
                                            $notypeconditions[] = "jQuery('#TickBox" . (int) $displayedrestype . "').prop('checked') == false";
                                        }

                                        $hidecondition = " if ((" .  implode(" && ", $showconditions) . ") " . (count($hideconditions) > 0 ? "|| " : "") . implode(" || ", $hideconditions) . " || (" . implode(" && ", $notypeconditions) . ")) {";
                                        echo "// Start of hide field code\n" . $hidecondition;
                                        ?>

                                        // Process unchecked element
                                        ssearchfieldname = 'simplesearch_<?php echo $fields[$n]["ref"]; ?>';
                                        document.getElementById(ssearchfieldname).style.display = 'none';

                                        // Search field is hidden, so add it to the list of hidden search field names for use when searchbar is redisplayed
                                        ssearchhiddenfields.push(ssearchfieldname)

                                        // Also deselect it.
                                        <?php
                                        switch ($fields[$n]['type']) {
                                            case FIELD_TYPE_DATE_AND_OPTIONAL_TIME:
                                            case FIELD_TYPE_EXPIRY_DATE:
                                            case FIELD_TYPE_DATE:
                                                ?>
                                                document.getElementById('field_<?php echo escape($fields[$n]["ref"]) ?>-y').value = '';
                                                document.getElementById('field_<?php echo escape($fields[$n]["ref"]) ?>-m').value = '';
                                                <?php
                                                if ($searchbyday) {
                                                    ?>
                                                    document.getElementById('field_<?php echo escape($fields[$n]["ref"]) ?>-d').value = '';
                                                    <?php
                                                }
                                                break;
                                            case FIELD_TYPE_CATEGORY_TREE:
                                                ?>
                                                document.getElementById('field_<?php echo escape($fields[$n]["name"]) ?>').value = '';
                                                <?php
                                                break;
                                            case FIELD_TYPE_CHECK_BOX_LIST:
                                            case FIELD_TYPE_DROP_DOWN_LIST:
                                            case FIELD_TYPE_RADIO_BUTTONS:
                                                ?>
                                                jQuery('select[name="nodes_searched[<?php echo $fields[$n]["ref"]; ?>]"]').val('');
                                                <?php
                                                break;
                                            default:
                                                if ($fields[$n]['field_constraint'] == 1) { ?>
                                                    document.getElementById('field_<?php echo escape($fields[$n]["name"]) ?>').value = '';  
                                                <?php } else { ?>
                                                    document.getElementById('field_<?php echo escape($fields[$n]["ref"]) ?>').value = '';
                                                <?php }
                                        }
                                        ?>
                                    } else {
                                        // Process checked element
                                        <?php
                                        if (in_array($fields[$n]['type'], array(2,3)) || ($fields[$n]["type"] == 9 && $simple_search_show_dynamic_as_dropdown)) {
                                            ?>
                                            document.getElementById('field_<?php echo $fields[$n]["ref"]; ?>').disabled=false;
                                            <?php
                                        }
                                        ?>

                                        ssearchfieldname = 'simplesearch_<?php echo $fields[$n]["ref"]; ?>';
                                        document.getElementById(ssearchfieldname).style.display = '';

                                        // Search field is no longer hidden, so remove it from the list of hidden search field names for use when searchbar is redisplayed
                                        ssindex = ssearchhiddenfields.indexOf(ssearchfieldname);
                                        if (ssindex > -1)  {
                                            ssearchhiddenfield.splice(ssindex, 1);
                                        }
                                    }
                                    <?php
                                }
                            }
                            ?>

                            // Save the hidden field names for use when searchbar is redisplayed
                            ssearchhiddenfieldsstring = ssearchhiddenfields.join(',');
                            document.getElementById('ssearchhiddenfields').value = ssearchhiddenfieldsstring;
                            SetCookie('ssearchhiddenfields', ssearchhiddenfieldsstring);
                            console.debug("SETCOOKIE SSEARCHHIDDENFIELDS=" + ssearchhiddenfieldsstring);
                            }

                            function SimpleSearchFieldsResetValues(includeglobals) {
                                <?php
                                # Reset the data in each of the searchfields including global
                                for ($n = 0; $n < count($fields); $n++) {
                                    if ($fields[$n]["global"] == 1) {
                                        $resetcondition = " if (includeglobals) {";
                                    } else {
                                        $resetconditions =  [];
                                        $showconditions =  [];
                                        // Check if resource types are valid for field
                                        $validrestypes = explode(",", (string)$fields[$n]["resource_types"]);
                                        $invalidrestypes = array_diff(array_column($types, "ref"), array_merge($hide_resource_types, $validrestypes));

                                        // Don't reset if any of the valid resource types are checked AND none of the invalid types are checked
                                        foreach ($validrestypes as $validrestype) {
                                            $showconditions[] = "jQuery('#TickBox" . (int) $validrestype . "').prop('checked') == false";
                                        }

                                        foreach ($invalidrestypes as $invalidrestype) {
                                            $resetconditions[] = "jQuery('#TickBox" . (int) $invalidrestype . "').prop('checked')";
                                        }
                                        $resetcondition = " if ((" .  implode(" && ", $showconditions) . ") " . (count($resetconditions) > 0 ? "|| " : "")  . implode(" || ", $resetconditions) . ") {";
                                    }

                                    echo "// Start of reset field code\n" . $resetcondition;

                                    # Duplicate fields are skipped
                                    # Fields subjected to display conditioning are skipped
                                    if (
                                        !in_array($fields[$n]["name"], $duplicate_fields)
                                        && (
                                            empty($simple_search_display_condition)
                                            || (
                                                !empty($simple_search_display_condition)
                                                && !in_array($fields[$n]['ref'], $simple_search_display_condition)
                                            )
                                        )
                                    ) {
                                        switch ($fields[$n]['type']) {
                                            case FIELD_TYPE_CATEGORY_TREE:
                                                ?>
                                                var ref = <?php echo escape($fields[$n]["ref"]) ?>;
                                                jQuery('#search_tree_' + ref).jstree({
                                                    'core' : {
                                                        'themes' : {
                                                            'name' : 'default-dark',
                                                            'icons': false
                                                        }
                                                    }
                                                }).deselect_all();

                                                /* Remove the hidden inputs */
                                                var elements = document.getElementsByName('nodes_searched[' + ref + ']');
                                                while (elements[0]) {
                                                    elements[0].parentNode.removeChild(elements[0]);
                                                }

                                                /* Update status box */
                                                var node_statusbox = document.getElementById('nodes_searched_' + ref + '_statusbox');
                                                while (node_statusbox.lastChild) {
                                                    node_statusbox.removeChild(node_statusbox.lastChild);
                                                }
                                                
                                                jQuery('.search_tree_' + ref + '_nodes').remove();
                                                <?php
                                                break;
                                            case FIELD_TYPE_DATE_AND_OPTIONAL_TIME:
                                            case FIELD_TYPE_EXPIRY_DATE:
                                            case FIELD_TYPE_DATE:
                                                ?>
                                                document.getElementById('field_<?php echo escape($fields[$n]["ref"]) ?>-y').value='';
                                                document.getElementById('field_<?php echo escape($fields[$n]["ref"]) ?>-m').value='';
                                                <?php
                                                if ($searchbyday) {
                                                    ?>
                                                    document.getElementById('field_<?php echo escape($fields[$n]["ref"]) ?>-d').value='';
                                                    <?php
                                                }
                                                break;
                                            case FIELD_TYPE_CHECK_BOX_LIST:
                                            case FIELD_TYPE_DROP_DOWN_LIST:
                                            case FIELD_TYPE_RADIO_BUTTONS:
                                                ?>
                                                console.debug("Clearing field <?php echo $fields[$n]["ref"]; ?>"); 
                                                jQuery('select[name="nodes_searched[<?php echo $fields[$n]["ref"]; ?>]"]').val('');
                                                <?php
                                                break;
                                            default:
                                                if ($fields[$n]['field_constraint'] == 1) { ?>
                                                    document.getElementById('field_<?php echo escape($fields[$n]["name"]) ?>').value = '';  
                                                <?php } else { ?>
                                                    document.getElementById('field_<?php echo escape($fields[$n]["ref"]) ?>').value = '';
                                                <?php }
                                        }
                                    }
                                    
                                    echo "} // End of reset field condition\n";
                                }
                                ?>
                            }
                        </script>
        
                        <div id="basicdate" class="SearchItem" <?php echo $simpleSearchFieldsAreHidden ? 'style="display:none;"' : ''; ?>>
                            <?php if ($simple_search_date) {
                                echo escape($lang["bydate"]);
                                ?>
                                <br />
                                <select id="basicyear" name="basicyear" class="SearchWidthHalf" title="<?php echo escape($lang['year']); ?>" aria-label="<?php echo escape($lang['year']); ?>">
                                    <option selected="selected" value=""><?php echo escape($lang["anyyear"]); ?></option>
                                    <?php
                                    $y = date("Y");
                                    $y += $maxyear_extends_current;
                                    for ($n = $y; $n >= $minyear; $n--) {
                                        ?>
                                        <option <?php echo ($n == $found_year) ? 'selected' : ''; ?>><?php echo $n; ?></option>
                                        <?php
                                    }
                                    ?>
                                </select> 
                
                                <?php if ($searchbyday) { ?>
                                    <br />
                                <?php } ?>
                
                                <select id="basicmonth" name="basicmonth" class="SearchWidthHalf SearchWidthRight" title="<?php echo escape($lang['month']);?>" aria-label="<?php echo escape($lang['month']); ?>">
                                    <option selected="selected" value=""><?php echo escape($lang["anymonth"]) ?></option>
                                    <?php
                                    for ($n = 1; $n <= 12; $n++) {
                                        $m = str_pad($n, 2, "0", STR_PAD_LEFT);
                                        ?>
                                        <option <?php echo ($n == $found_month) ? 'selected' : ''; ?> value="<?php echo $m; ?>">
                                            <?php echo escape($lang["months_list"][$n - 1]); ?>
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                
                                <?php if ($searchbyday) { ?>
                                    <select id="basicday" name="basicday" class="SearchWidthHalf" title="<?php echo escape($lang['day']);?>">
                                        <option selected="selected" value=""><?php echo escape($lang["anyday"]); ?></option>
                                        <?php
                                        for ($n = 1; $n <= 31; $n++) {
                                            $m = str_pad($n, 2, "0", STR_PAD_LEFT);
                                            ?>
                                            <option <?php echo ($n == $found_day) ? 'selected' : ''; ?> value="<?php echo $m; ?>"><?php echo $m; ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                <?php }
                            }
                            
                            if (isset($resourceid_simple_search) && $resourceid_simple_search) {
                                ?>
                                <div class="SearchItem">
                                    <?php echo escape($lang["resourceid"]); ?>
                                    <br />
                                    <input id="searchresourceid" name="searchresourceid" type="text" class="SearchWidth" value="" />
                                </div>
                                <?php
                            }
                            ?>
                        </div>

                        <script type="text/javascript">
                            function ResetTicks() {
                                <?php echo $clear_function; ?>
                            }
                        </script>
        
                        <?php
                    }
                    
                    echo $searchbuttons;
                    ?>
                </form>
                <br />

                <?php
                hook("searchbarbeforebottomlinks");

                if (!$disable_geocoding) {
                    ?>
                    <p>
                        <i aria-hidden="true" class="icon-globe "></i>
                        <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl; ?>/pages/geo_search.php">
                            <?php echo escape($lang["geographicsearch"]); ?>
                        </a>
                    </p>
                    <?php
                }
                
                if (!$advancedsearch_disabled) {
                    ?>
                    <p>
                        <i aria-hidden="true" class="icon-zoom-in "></i>
                        <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl; ?>/pages/search_advanced.php">
                            <?php echo escape($lang["gotoadvancedsearch"]); ?>
                        </a>
                    </p>
                    <?php
                }
                
                hook("searchbarafterbuttons");
                
                if ($view_new_material) {
                    ?>
                    <p>
                        <i aria-hidden="true" class="icon-clock"></i>
                        <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl; ?>/pages/search.php?search=<?php echo urlencode("!last" . $recent_search_quantity); ?>">
                            <?php echo escape($lang["viewnewmaterial"]); ?>
                        </a>
                    </p>
                    <?php
                }
                ?>
            </div>

            <?php if ($show_anonymous_login_panel && isset($anonymous_login) && (isset($username)) && ($username == $anonymous_login)) {
                # For anonymous access, display the login panel ?>
                <div id="LoginBoxPanel" class="LoginBoxPanel">
                    <div class="SearchSpace">
                        <h2><?php echo escape($lang["login"]); ?></h2>
                        <form id="anonymous_login_form" method="post" action="<?php echo $baseurl; ?>/login.php">
                            <div class="SearchItem">
                                <?php echo escape($lang["username"]); ?>
                                <br/>
                                <input type="text" name="username" id="name" class="SearchWidth" />
                            </div>
                            <div class="SearchItem">
                                <?php echo escape($lang["password"]); ?>
                                <br/>
                                <input type="password" name="password" id="password" class="SearchWidth" />
                            </div>
                            <div class="SearchItem">
                                <input name="Submit" type="submit" value="<?php echo escape($lang["login"]); ?>" />
                            </div>
                        </form>

                        <?php if ($allow_account_request) { ?>
                            <p>
                                <br/>
                                <a href="<?php echo $baseurl_short; ?>pages/user_request.php">
                                    <?php echo LINK_CARET . escape($lang["nopassword"]); ?>
                                </a>
                            </p>
                        <?php }

                        if ($allow_password_reset) { ?>
                            <p>
                                <a href="<?php echo $baseurl_short; ?>pages/user_password.php">
                                    <?php echo LINK_CARET . escape($lang["forgottenpassword"]); ?>
                                </a>
                            </p>
                        <?php } ?>

                        <br/>
                        <?php hook("loginformlink") ?>
                    </div>
                </div>
            <?php }
            
            if (($research_request) && (!isset($k) || $k == "") && (checkperm("q"))) { ?>
                <div id="ResearchBoxPanel">
                    <div class="SearchSpace">
                        <h2><?php echo escape($lang["researchrequest"]); ?></h2>
                        <p><?php echo escape(text("researchrequest")); ?></p>
                        <div class="HorizontalWhiteNav">
                            <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl; ?>/pages/research_request.php">
                                <?php echo LINK_CARET . escape($lang["researchrequestservice"]); ?>
                            </a>
                        </div>
                    </div>
                    <br />
                </div>
            <?php }
            
            if ($show_powered_by_logo && (get_header_image() != $baseurl . '/gfx/titles/title-black.svg')) { ?>
                <div class="PoweredByPanel">
                    <a href="https://www.resourcespace.com" target="_blank">
                        <span><?php echo escape($lang["powered_by"]); ?></span>
                        <img src="<?php echo $baseurl; ?>/gfx/titles/title.svg" alt="<?php echo escape($lang['powered_by_resourcespace']); ?>">
                    </a>
                </div>
            <?php } ?>
        </div>
    <?php }
    
    if ($browse_bar && checkperm("s") === true) {
        render_browse_bar();
    } ?>
</div>

<?php
if ($simple_search_pills_view) {
    ?>
    <script>
        jQuery(document).ready(function () {
            // For responsive mode we cannot use tag editor. For some reason it doesn't work. I think it has something to do with
            // jQuery UI/ layout but not sure what exactly.
            if (750 > jQuery(window).width()) {
                jQuery('#ssearchbox').tagEditor('destroy');
            }
        });
    </script>
    <?php
}

global $selected_search_tab;

if ($selected_search_tab == "browse") {
    ?>
    <script>
        jQuery(document).ready(function () {
            selectSearchBarTab('browse');
        });
    </script>
    <?php
}

# Restore original values that may have been affected by processsing so the search page still draws correctly with the current search.
$restypes = $stored_restypes;
$search = $stored_search;
$quicksearch = $stored_quicksearch;
$category_tree_add_parents = $stored_category_tree_add_parents;
