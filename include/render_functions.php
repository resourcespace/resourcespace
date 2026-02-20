<?php
/**
* Functions used to render HTML & Javascript
*
*/


/**
* Renders the HTML for the provided $field for inclusion in a search form, for example the
* advanced search page. Standard field titles are translated using $lang.  Custom field titles are i18n translated.
*
* $field    the field being rendered as an associative array of field data, i.e. one row from the resource_type_field table.
* $fields   the array of fields data, i.e. multiple rows from the resource_type_field table.
* $name     the input name to use in the form (post name)
* $value    the default value to set for this field, if any
* $reset    is non-blank if the caller requires the field to be reset
* @param array $searched_nodes Array of all the searched nodes previously
*/
function render_search_field($field,$fields,$value="",$autoupdate=false,$class="stdwidth",$forsearchbar=false,$limit_keywords=array(), 
                             $searched_nodes = array(), $reset="",$simpleSearchFieldsAreHidden=false)
    {
    node_field_options_override($field);

    global $auto_order_checkbox, $auto_order_checkbox_case_insensitive, $lang, $category_tree_open, $minyear, $daterange_search, $searchbyday, 
        $is_search, $values, $n, $simple_search_show_dynamic_as_dropdown, $clear_function, $simple_search_display_condition, $autocomplete_search, 
        $baseurl, $fields, $baseurl_short, $extrafooterhtml,$FIXED_LIST_FIELD_TYPES, $maxyear_extends_current, $DATE_FIELD_TYPES;

    # Certain edit_fields/x.php functions check for bulk edit which must be defined as false prior to rendering the search field  
    $multiple=false;
    $field['ref'] = escape($field['ref']);
?>
<!-- RENDERING FIELD=<?php echo $field['ref'] . " " . escape($field['name']);?> -->
<?php

    // set this to zero since this does not apply to collections
    if (!isset($field['field_constraint'])){$field['field_constraint']=0;}

    $name="field_" . ($forsearchbar ? escape($field["name"]) : $field["ref"]);
    $id="field_" . $field["ref"];

    # An array of conditions spanning all governed fields and all governing fields
    $scriptconditions=array();

    # Assume the field being rendered should be displayed
    $displaycondition=true;

    # If the field being rendered has a display condition
    #  For advanced search, always check the display condition
    #  For simple search, only check the display condition if the field is included in the simple_search_display_condition config array 
    if ( $field["display_condition"]!="" 
    && ( !$forsearchbar || ($forsearchbar && !empty($simple_search_display_condition) && in_array($field['ref'],$simple_search_display_condition)) ) )
        {
        # Split the display condition of the field being rendered into an array of tests (if there are more than one, they are separated by a ";")
        # Each test is in the form governing field = governing field value 
        #   If the field being rendered is itself a governing field then "On Change" code must be generated for the governing field
        #   If the field being rendered is a governed field then "Checking" code must be generated for each governing field
        $s=explode(";",$field["display_condition"]);
        $condref=0;
        foreach ($s as $condition) # Check each individual test
            {
            # Assume that the current test does not need to be checked
            $displayconditioncheck=false;
            $s=explode("=",$condition);

            # Process each field to see if it is being referenced in the current test
            if (!is_array($fields))
                {
                return false;
                }
            for ($cf=0;$cf<count($fields);$cf++) # Check each field to see if it is a governing field whose value needs to be checked
                {
                # If the field being processed is referenced in the current test, then it is a governing field 
                if ($s[0]==$fields[$cf]["name"]) 
                    {
                    # The script conditions array contains an entry for each governing field
                    $scriptconditions[$condref]["field"]               = $fields[$cf]["ref"];  # governing field
                    $scriptconditions[$condref]["governedfield"]       = $field["ref"];  # governed field

                    $scriptconditions[$condref]["name"]                = $fields[$cf]["name"];
                    $scriptconditions[$condref]['type']                = $fields[$cf]['type'];
                    $scriptconditions[$condref]['display_as_dropdown'] = $fields[$cf]['display_as_dropdown'];
                    # Get the node references of the governing field
                    $scriptconditionnodes = get_nodes($fields[$cf]['ref'], null, (FIELD_TYPE_CATEGORY_TREE == $fields[$cf]['type'] ? true : false));

                    $checkvalues=$s[1];
                    # Prepare an array of values present in the test
                    $validvalues=explode("|",strtoupper($checkvalues));
                    $validvalues = array_map("i18n_get_translated", $validvalues);
                    $scriptconditions[$condref]['valid'] = array();
                    $scriptconditions[$condref]['validtext'] = array();
                    foreach($validvalues as $validvalue)
                        {
                        # The validtext array is for checking input values instead of their corresponding node references
                        $scriptconditions[$condref]['validtext'][] = strtolower($validvalue);

                        # Convert the value name into a node entry if it is a valid node within the governing field
                        $found_validvalue = get_node_by_name($scriptconditionnodes, $validvalue);

                        # If there is a node which corresponds to that value name then append its node reference to a list of valid nodes
                        if(0 != count($found_validvalue))
                            {
                            $scriptconditions[$condref]['valid'][] = (string)$found_validvalue['ref'];

                            # Is the node present in search result list of nodes
                            if(in_array($found_validvalue['ref'],$searched_nodes))
                                {
                                # Value being tested is present in the searched nodes array
                                $displayconditioncheck = true;
                                }
                            }
                        }

                    # Suppress this field if none of the nodes (of the values) in the test match the searched nodes array
                    if (!$displayconditioncheck)
                        {
                        $displaycondition=false; # Do not render field
                        }


                // Certain fixed list types allow for multiple nodes to be passed at the same time

                // Generate a javascript function specific to the field being rendered
                // This function will be invoked whenever any governing field changes
                if(in_array($fields[$cf]['type'], $FIXED_LIST_FIELD_TYPES))
                    {
                        if(FIELD_TYPE_CATEGORY_TREE == $fields[$cf]['type'])
                            {
                            ?>
                            <!-- SETUP HANDLER FOR GOVERNOR=<?php echo $fields[$cf]['ref']; ?> GOVERNED=<?php echo $field['ref']; ?>-->
                            <script type="text/javascript">
                            var wto;
                            jQuery(document).ready(function()
                                {
                                jQuery('#<?php echo $forsearchbar ? "SearchBox" : "CentralSpace" ?>').on('categoryTreeChanged', function(e,node)
                                    {
                                    // Debounce multiple events fired by the category tree
                                    clearTimeout(wto);
                                    wto=setTimeout(function() {
                                        // Reflect the change of the governing field into the following governed field condition checker
                                        console.debug("<?php echo "DISPCOND CATTREE CHANGEGOVERNOR=".$fields[$cf]['ref']; ?>");
                                        for (i = 0; i<categoryTreeChecksArray.length; i++) {
                                            categoryTreeChecksArray[i]();
                                        }
                                    }, 200);
                                    });
                                });
                            </script>
                            <?php
                            }
                        elseif(FIELD_TYPE_DYNAMIC_KEYWORDS_LIST == $fields[$cf]['type'])
                            {
                            if ($forsearchbar) {
                                if ($simple_search_show_dynamic_as_dropdown) {
                                    $checkname       = "nodes_searched[{$fields[$cf]['ref']}]";
                                    $jquery_selector = "select[name=\"{$checkname}\"]";
                                }
                                else {
                                    $jquery_selector = "input[name=\"field_{$fields[$cf]["name"]}\"]";
                                }
                            ?>
                            <!-- SETUP HANDLER FOR GOVERNOR=<?php echo $fields[$cf]['ref']; ?> GOVERNED=<?php echo $field['ref']; ?>-->
                            <script type="text/javascript">
                            jQuery(document).ready(function()
                                {
                                jQuery('<?php echo $jquery_selector; ?>').change(function ()
                                    {
                                    // Reflect the change of the governing field into the following governed field condition checker
                                    console.debug("<?php echo "DISPCOND DYNAMKKD CHANGEGOVERNOR=".$fields[$cf]['ref']." CHECK GOVERNED=".$field['ref']; ?>");
                                    checkSearchDisplayCondition<?php echo $field['ref']; ?>();
                                    });
                                });
                            </script>
                            <?php
                            }
                            else { # Advanced search
                            ?>
                            <!-- SETUP HANDLER FOR GOVERNOR=<?php echo $fields[$cf]['ref']; ?> GOVERNED=<?php echo $field['ref']; ?>-->
                            <script type="text/javascript">
                            jQuery(document).ready(function()
                                {
                                jQuery('#CentralSpace').on('dynamicKeywordChanged', function(e,node)
                                    {
                                    // Reflect the change of the governing field into the following governed field condition checker
                                    console.debug("<?php echo "DISPCOND DYNAMKWD CHANGEGOVERNOR=".$fields[$cf]['ref']." CHECK GOVERNED=".$field['ref']; ?>");
                                    checkSearchDisplayCondition<?php echo $field['ref']; ?>();
                                    });
                                });
                            </script>
<?php
                            }
                            }
                        else
                            {
                            # Otherwise FIELD_TYPE_CHECK_BOX_LIST or FIELD_TYPE_DROP_DOWN_LIST or FIELD_TYPE_RADIO_BUTTONS

                            # Simple search will always display these types as dropdowns
                            if ($forsearchbar) {
                                $checkname       = "nodes_searched[{$fields[$cf]['ref']}]";
                                $jquery_selector = "select[name=\"{$checkname}\"]";
                            }
                            # Advanced search will display these as dropdowns if marked as such, otherwise they are displayed as checkbox lists to allow OR selection
                            else {
                                # Prepare selector on the assumption that its an input element (ie. a checkbox list or a radio button or a dropdown displayed as checkbox list)
                                $checkname = "nodes_searched[{$fields[$cf]['ref']}][]";
                                $jquery_selector = "input[name=\"{$checkname}\"]";

                                # If however its a drop down list then we should be processing select elements
                                if ($fields[$cf]['display_as_dropdown'])
                                    {
                                    $checkname       = "nodes_searched[{$fields[$cf]['ref']}]";
                                    $jquery_selector = "select[name=\"{$checkname}\"]";
                                    }
                            } 
                            ?>
                            <!-- SETUP HANDLER FOR GOVERNOR=<?php echo $fields[$cf]['ref']; ?> GOVERNED=<?php echo $field['ref']; ?>-->
                            <script type="text/javascript">
                            jQuery(document).ready(function()
                                {
                                jQuery('<?php echo $jquery_selector; ?>').change(function ()
                                    {
                                    // Reflect the change of the governing field into the following governed field condition checker
                                    console.debug("<?php echo "DISPCOND CHANGEGOVERNOR=".$fields[$cf]['ref']." CHECK GOVERNED=".$field['ref']; ?>");
                                    checkSearchDisplayCondition<?php echo $field['ref']; ?>();
                                    });
                                });
                            </script>
                            <?php
                            }
                        } 
                    else
                        { # Not one of the FIXED_LIST_FIELD_TYPES
                        ?>
                        <!-- SETUP HANDLER FOR GOVERNOR=<?php echo $fields[$cf]['ref']; ?> GOVERNED=<?php echo $field['ref']; ?>-->
                        <script type="text/javascript">
                        jQuery(document).ready(function()
                            {
                            jQuery('#field_<?php echo $fields[$cf]["ref"]; ?>').change(function ()
                                {
                                // Reflect the change of the governing field into the following governed field condition checker
                                checkSearchDisplayCondition<?php echo $field['ref']; ?>();
                                });
                            });
                        </script>
<?php
                        }
                    }
                } # see if next field needs to be checked

            $condref++;
            } # check next condition

        ?>
        <?php echo "<!-- CHECK CONDITIONS FOR GOVERNED FIELD ".$field['name']." [".$field['ref']."] -->";
        $function_has_category_tree_check=false;
        ?>
        <script type="text/javascript">

        checkSearchDisplayCondition<?php echo $field["ref"];?> = function ()   
            {
            // Is the governed field resource type ok; if not then no checking necessary as its already hidden
            const conditionalquestion = document.getElementById("question_<?php echo $n ?>");
            if (conditionalquestion != null && conditionalquestion.dataset?.resource_type_ok != "1") { return; }

            // Check the node passed in from the changed governing field
            var idname<?php echo $field['ref']; ?>     = "<?php echo $forsearchbar?"#simplesearch_".$field['ref']:"#question_".$n; ?>";
            var ixThisField;
            // Get current display state for governed field ("block" or "none")
            field<?php echo $field['ref']; ?>status    = jQuery(idname<?php echo $field['ref']; ?>).css('display');
            newfield<?php echo $field['ref']; ?>status = 'none';

            // Assume visible by default
            field<?php echo $field['ref']; ?>visibility = true;

            <?php
            foreach($scriptconditions as $scriptcondition)
                {
                echo "// Checking values on field ".$scriptcondition['field']."\n";
                # Example of $scriptconditions: [{"field":"73","type":"3","display_as_dropdown":"0","valid":["267","266"]}] 
                if ($scriptcondition['type'] == FIELD_TYPE_CATEGORY_TREE) {
                    $function_has_category_tree_check=true;
                }
            ?>

            field<?php echo $field['ref']; ?>valuefound = false;
            fieldokvalues<?php echo $scriptcondition['field']; ?> = <?php echo json_encode($scriptcondition['valid']); ?>;

            <?php
            if (
                $scriptcondition['type'] == FIELD_TYPE_DYNAMIC_KEYWORDS_LIST
                && $forsearchbar
                && !$simple_search_show_dynamic_as_dropdown
                ) {
                    ?>
                        // When a dynamic keyword list is rendered as regular input field on simple search, the valid values to check against are the text values (not nodes) 
                        fieldokvalues<?php echo $scriptcondition['field']; ?> = <?php echo json_encode($scriptcondition['validtext']); ?>;
                    <?php
                }
            ?>

            <?php echo "// CHECK IF GOVERNING ".$scriptcondition['name']." [".$scriptcondition['field']."] VALUE(S) ENABLE DISPLAY";?>

            <?php

            # Generate the javascript code necessary to condition the rendered field based on value(s) present in the governing field

            # Prepare base name for selector 
            $checkname = "nodes_searched[{$scriptcondition['field']}]";
            $js_conditional_statement  = "fieldokvalues{$scriptcondition['field']}.indexOf(element.value) != -1";

            # Prepare fallback selector 
            $jquery_condition_selector = "input[name=\"{$checkname}\"]";

            if(in_array($scriptcondition['type'], $FIXED_LIST_FIELD_TYPES))
                {
                # Append additional brackets rendered on category tree and dynamic keyword list hidden inputs
                if (in_array($scriptcondition['type'], array(FIELD_TYPE_CATEGORY_TREE, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST)) ) {
                    $jquery_condition_selector = "input[name=\"{$checkname}[]\"]";
                }

                # Prepare selector for a checkbox list or a radio button or a dropdown list
                if (in_array($scriptcondition['type'], array(FIELD_TYPE_CHECK_BOX_LIST, FIELD_TYPE_RADIO_BUTTONS, FIELD_TYPE_DROP_DOWN_LIST))) {

                    # Simple search will always display these types as dropdowns, so search for selected option
                    if ($forsearchbar) {
                        $jquery_condition_selector = "select[name=\"{$checkname}\"] option:selected";
                    }
                    # Advanced search will display these as dropdowns if marked as such, otherwise they are displayed as checkbox lists to allow OR selection
                    else {
                        # Prepare selector on the assumption that its an input element (ie. a checkbox list or a radio button or a dropdown displayed as checkbox list)
                        #   so search for checked boxes
                        $jquery_condition_selector = "input[name=\"{$checkname}[]\"]:checked:enabled";

                        # If however its a drop down list then we should be searching for selected option
                        if ($scriptcondition['display_as_dropdown'])
                            {
                            $jquery_condition_selector = "select[name=\"{$checkname}\"] option:selected";
                            }
                    }                    

                }

                # Prepare selector for unusual dynamic keyword list configurations
                if (
                    $scriptcondition['type'] == FIELD_TYPE_DYNAMIC_KEYWORDS_LIST
                    && $forsearchbar
                    ) {
                        if($simple_search_show_dynamic_as_dropdown) {
                            # Prepare selector for a dynamic keyword list configured to display as a dropdown list on simple search
                            $jquery_condition_selector = "select[name=\"{$checkname}\"] option:selected";
                        } else {
                            # Prepare selector for a dynamic keyword list rendered as regular input field
                            $jquery_condition_selector = "input[name=\"field_{$scriptcondition['name']}\"]";
                        }
                    }

                ?>
                    jQuery('<?php echo $jquery_condition_selector; ?>').each(function(index, element)
                    {
                        if(<?php echo $js_conditional_statement; ?>)
                        {
                        // The governing node is in the list of qualifying node(s) which enable this governed field
                        field<?php echo $field['ref']; ?>valuefound = true;
                        }
                    });

                <?php
                }?>

                // If no governing node found then disable this governed field
                if(!field<?php echo $field['ref']; ?>valuefound)
                {
                field<?php echo $field['ref']; ?>visibility = false;
                }

            <?php
                echo "// End of checking values on field ".$scriptcondition['field']."\n\n            ";
                }
            ?>

                // If not yet defined, initialise an array of governed fields to be hidden when resetting simple search
                if(typeof fieldsToHideOnClear == "undefined")
                    {
                    fieldsToHideOnClear = new Array();
                    }

                // If the governed field is enabled then set it to display
                if(field<?php echo $field['ref']; ?>visibility)
                    {
                    newfield<?php echo $field['ref']; ?>status = 'block';
                    // This governed field will be shown, so remove it from array of fields to hide when resetting simple search
                    ixThisField = fieldsToHideOnClear.indexOf('<?php echo $field["ref"]; ?>');
                    fieldsToHideOnClear.splice(ixThisField,1);
                    }
                else
                    {
                    // This governed field will be hidden, so add it to array of fields to hide when resetting simple search
                    ixThisField = fieldsToHideOnClear.indexOf('<?php echo $field["ref"]; ?>');
                    if (ixThisField < 0) 
                        { 
                        fieldsToHideOnClear.push('<?php echo $field["ref"]; ?>'); 
                        }
                    }

                // If the governed field display state has changed then enact the change by sliding
                if ( newfield<?php echo $field['ref']; ?>status != field<?php echo $field['ref']; ?>status )
                    {
                    console.debug("IDNAME " + idname<?php echo $field['ref']; ?>);
                    console.debug("   FIELD <?php echo $field['ref']; ?> STATUS '" + field<?php echo $field['ref']; ?>status+"'");
                    console.debug("NEWFIELD <?php echo $field['ref']; ?> STATUS '" + newfield<?php echo $field['ref']; ?>status+"'");
                    // Toggle the display state between "block" and "none", clearing any incomplete actions in the process
                    jQuery(idname<?php echo $field['ref']; ?>).slideToggle(function()
                        {
                        console.debug("SLIDETOGGLE FIELD <?php echo $field['ref']; ?>");
                        jQuery(this).clearQueue();
 
                        // If field is being made visible, check if section is visible and trigger the parent slide if not
                        if(newfield<?php echo $field['ref']; ?>status == 'block' && jQuery(this).parent().css('display') == 'none') {
                            jQuery(this).parent().prev('h1').click();
                        }
                        <?php 
                        if ($forsearchbar) {
                            if ($field['type'] == FIELD_TYPE_CATEGORY_TREE) { ?>
                                jQuery('.search_tree_<?php echo $field['ref']; ?>_nodes').each(function(i, element){    
                                    jQuery('#'+element.value+'_anchor').click();
                                });
                            <?php } elseif (in_array($field['type'], $DATE_FIELD_TYPES)) { ?>
                                jQuery('#field_<?php echo $field['ref']; ?>-y').val('');
                                jQuery('#field_<?php echo $field['ref']; ?>-m').val('');
                                jQuery('#field_<?php echo $field['ref']; ?>-d').val('');
                            <?php } else { ?>
                                jQuery(idname<?php echo $field['ref']; ?> + ' #field_<?php echo $field['ref']; ?>').val('');
                            <?php 
                                }
                        } ?>
                        });

                    // Adjust the border accordingly
                    if(jQuery(idname<?php echo $field['ref']; ?>).css('display') == 'block')
                        {
                        jQuery(idname<?php echo $field['ref']; ?>).css('border-top', '');
                        }
                    else
                        {
                        jQuery(idname<?php echo $field['ref']; ?>).css('border-top', 'none');
                        }
                    }
        }

        <?php 
        if ($function_has_category_tree_check) {
        ?>
        categoryTreeChecksArray.push(checkSearchDisplayCondition<?php echo $field["ref"];?>);
        <?php
        }
        ?>

        </script>
        <?php
        if($forsearchbar)
            {
            // add the display condition check to the clear function
            $clear_function.="checkSearchDisplayCondition".$field['ref']."();";
            }

        } // Endif rendered field with a display condition

    $is_search = true;

    if (!$forsearchbar)
        {
        ?>
        <div class="Question ConditionalVisibility" data-resource_type_ok="" data-for_resource_types="<?php 
        // Add class for each supported resource type to allow showing/hiding on advanced search
        if($field["resource_types"] == "Collections")
            {
            echo "Collections";
            }
        elseif($field["global"] == 1)
            {
            echo "Global";
            }
        else
            {
            echo (string)$field["resource_types"];
            }
        ?>" id="question_<?php echo $n ?>" data-has_display_condition="<?php echo !$displaycondition ? '1' : '0'; ?>" data-question_field_ref="<?php echo $field['ref']; ?>"<?php
        if (strlen((string) $field["tooltip_text"])>=1)
            {
            echo "title=\"" . escape(lang_or_i18n_get_translated($field["tooltip_text"], "fieldtooltip-")) . "\"";
            }
        ?>>
        <label><?php echo escape(lang_or_i18n_get_translated($field["title"], "fieldtitle-")) ?></label>
        <?php
        }
    else
        {
        hook("modifysearchfieldtitle");
        ?>
        <div class="SearchItem" id="simplesearch_<?php echo $field["ref"]; ?>" <?php if (!$displaycondition || $simpleSearchFieldsAreHidden) {?>style="display:none;"<?php } if (strlen($field["tooltip_text"] ?? "" ) >= 1){ echo "title=\"" . escape(lang_or_i18n_get_translated($field["tooltip_text"], "fieldtooltip-")) . "\"";} ?> ><label for="simplesearch_<?php echo $field["ref"]; ?>"><?php echo escape(lang_or_i18n_get_translated($field["title"], "fieldtitle-")) ?></label><br/>

        <?php
        #hook to modify field type in special case. Returning zero (to get a standard text box) doesn't work, so return 1 for type 0, 2 for type 1, etc.
        if(hook("modifyfieldtype")){$fields[$n]["type"]=hook("modifyfieldtype")-1;}
        }

    # Generate markup for field
    switch ($field["type"]) {
        case FIELD_TYPE_TEXT_BOX_SINGLE_LINE:
        case FIELD_TYPE_TEXT_BOX_MULTI_LINE:
        case FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE:
        case FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE:
        case $forsearchbar && $field["type"]==FIELD_TYPE_DYNAMIC_KEYWORDS_LIST && !$simple_search_show_dynamic_as_dropdown:
        case FIELD_TYPE_WARNING_MESSAGE:
        # Dynamic keyword list behaviour replaced with regular input field under these circumstances
        if ((int)$field['field_constraint']==0)
            {
            ?><input class="<?php echo escape($class) ?>" type=text name="<?php echo escape($name) ?>" id="<?php echo escape($id) ?>" value="<?php echo escape((string)$value)?>" <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } if(!$forsearchbar){ ?> onKeyPress="if (!(updating)) {setTimeout('UpdateResultCount()',2000);updating=true;}"<?php } if($forsearchbar){?>onKeyUp="if('' != jQuery(this).val()){FilterBasicSearchOptions('<?php echo escape((string)$field["name"]) ?>',[<?php echo escape((string)$field["resource_types"]) ?>]);}"<?php } ?>><?php 
            # Add to the clear function so clicking 'clear' clears this box.
            $clear_function.="document.getElementById('field_" . ($forsearchbar? $field["ref"] : escape((string)$field["name"])) . "').value='';";
            }
        // number view - manipulate the form value (don't send these but send a compiled numrange value instead
        elseif ((int)$field['field_constraint']==1)
            {
             // parse value for to/from simple search
            $minmax=explode('|',str_replace("numrange","",$value));
            ($minmax[0]=='')?$minvalue='':$minvalue=str_replace("neg","-",$minmax[0]);
            (isset($minmax[1]))?$maxvalue=str_replace("neg","-",$minmax[1]):$maxvalue='';
            echo escape($lang["from"]); ?><input id="<?php echo escape($name) ?>_min" onChange="jQuery('#<?php echo escape($name) ?>').val('numrange'+jQuery(this).val().replace('-','neg')+'|'+jQuery('#<?php echo escape($name) ?>_max').val().replace('-','neg'));" class="NumberSearchWidth" type="number" value="<?php echo escape($minvalue)?>"><?php echo escape($lang["to"]); ?><input id="<?php echo escape($name) ?>_max" onChange="jQuery('#<?php echo escape($name) ?>').val('numrange'+jQuery('#<?php echo escape($name) ?>_min').val().replace('-','neg')+'|'+jQuery(this).val().replace('-','neg'));" class="NumberSearchWidth" type="number" value="<?php echo escape($maxvalue)?>">
            <input id="<?php echo escape($name) ?>" name="<?php echo escape($name) ?>" type="hidden" value="<?php echo escape($value) ?>">
            <?php 
            # Add to the clear function so clicking 'clear' clears this box.
             $clear_function.="document.getElementById('".$name."_max').value='';";
             $clear_function.="document.getElementById('".$name."_min').value='';";
             $clear_function.="document.getElementById('".$name."').value='';";
            }



        if ($forsearchbar && $autocomplete_search) { 
                # Auto-complete search functionality
                ?></div>
                <script type="text/javascript">

                jQuery(document).ready(function () { 

                    jQuery("#field_<?php echo escape($field["ref"])?>").autocomplete( { source: "<?php echo $baseurl?>/pages/ajax/autocomplete_search.php?field=<?php echo escape($field["name"]) ?>&fieldref=<?php echo escape($field["ref"]) ?>"} );
                    })

                </script>
                <div class="SearchItem">
<?php }

        break;

        case FIELD_TYPE_CHECK_BOX_LIST: 
        case FIELD_TYPE_DROP_DOWN_LIST:
        case $forsearchbar && $field["type"]==FIELD_TYPE_DYNAMIC_KEYWORDS_LIST && $simple_search_show_dynamic_as_dropdown:
       if(!hook("customchkboxes", "", array($field, $value, $autoupdate, $class, $forsearchbar, $limit_keywords)))
            {
            global $checkbox_ordered_vertically;

            # -------- Show a check list or dropdown for dropdowns and check lists?
            # By default show a checkbox list for both (for multiple selections this enabled OR functionality)

            # Translate all options
            $adjusted_dropdownoptions=hook("adjustdropdownoptions");
            if ($adjusted_dropdownoptions){$options=$adjusted_dropdownoptions;}

            $node_options = array_column($field["node_options"], "name");

            if((bool) $field['automatic_nodes_ordering'])
                {
                $field['node_options'] = reorder_nodes($field['node_options']);
                }

            $order_by_resetter = 0;
            foreach($field['node_options'] as $node_index => $node)
                {
                // Special case for vertically ordered checkboxes.
                // Order by needs to be reset as per the new order so that we can reshuffle them using the order by as a reference
                if($checkbox_ordered_vertically)
                    {
                    $field['node_options'][$node_index]['order_by'] = $order_by_resetter++;
                    }
                }

            if ($field["display_as_dropdown"] || $forsearchbar)
                {
                # Show as a dropdown box
                $name = "nodes_searched[{$field['ref']}]";
                ?>
                <select class="<?php echo escape($class) ?>" name="<?php echo escape($name) ?>" id="<?php echo escape($id) ?>"
                    <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } 
                          if($forsearchbar){ ?>onChange="FilterBasicSearchOptions('<?php echo escape($field["name"]) ?>',<?php echo escape(($field["global"] == 1 ? "[0]" : "[" . escape((string)$field["resource_types"]) . "]")) ?>);" <?php } ?>>
                    <option value=""></option>
                <?php
                foreach($field['node_options'] as $node)
                    {
                    if('' != trim($node['name']))
                        {
                        ?>
                        <option value="<?php echo escape(trim($node['ref'])); ?>" <?php if (0 < count($searched_nodes) && in_array($node['ref'], $searched_nodes)) { ?>selected<?php } ?>><?php echo escape(trim(i18n_get_translated($node['name']))); ?></option>
                        <?php
                        }
                    }
                ?></select><?php
                if($forsearchbar)
                    {
                    // Add to the clear function so clicking 'clear' clears this box.
                    $clear_function .= "document.getElementById('{$id}').selectedIndex = -1;";
                    }
                }
            else
                {
                # Show as a checkbox list (default)
                $setnames=trim_array(explode(";",$value));
                $wrap=0;

                $l    = average_length($node_options);
                switch($l)
                    {
                    case $l > 40: $cols = 1; break; 
                    case $l > 25: $cols = 2; break;
                    case $l > 15: $cols = 3; break;
                    case $l > 10: $cols = 4; break;
                    case $l > 5:  $cols = 5; break;
                    default:       $cols = 10;
                    }

                $height = ceil(count($field['node_options']) / $cols);

                global $checkbox_ordered_vertically, $checkbox_vertical_columns;
                if($checkbox_ordered_vertically)
                    {
                    if(!hook('rendersearchchkboxes'))
                        {
                        # ---------------- Vertical Ordering (only if configured) -----------
                        ?>
                        <table cellpadding=4 cellspacing=0>
                            <tbody>
                                <tr>
                                <?php
                                for($i = 0; $i < $height; $i++)
                                    {
                                    for($j = 0; $j < $cols; $j++)
                                        {
                                        $order_by = ($height * $j) + $i;

                                        $node_index_to_be_reshuffled = array_search($order_by, array_column($field['node_options'], 'order_by', 'ref'));

                                        if(false === $node_index_to_be_reshuffled)
                                            {
                                            continue;
                                            }

                                        $node = $field['node_options'][$node_index_to_be_reshuffled];
                                        ?>
                                        <td valign=middle>
                                            <input id="nodes_searched_<?php echo $node['ref']; ?>" class="nodes_input_checkbox" type="checkbox" name="nodes_searched[<?php echo $field['ref']; ?>][]" value="<?php echo $node['ref']; ?>" <?php if((0 < count($searched_nodes) && in_array($node['ref'], $searched_nodes)) || in_array(i18n_get_translated($node['name']),$setnames)) { ?>checked<?php } ?> <?php if($autoupdate) { ?>onClick="UpdateResultCount();"<?php } ?>>
                                            <label class="customFieldLabel" for="nodes_searched_<?php echo $node['ref']; ?>">
                                                <?php echo escape(i18n_get_translated($node['name'])); ?>
                                            </label>
                                        </td>
                                        <?php
                                        }
                                        ?>
                                    </tr>
                                    <tr>
                                    <?php
                                    }
                                    ?>
                            </tbody>
                        </table>
<?php
                        }
                    }
                else
                    {
                    # ---------------- Horizontal Ordering (Standard) ---------------------             
                    ?>
                    <table cellpadding=4 cellspacing=0>
                        <tr>
                    <?php
                    foreach($field['node_options'] as $node)
                        {
                        $wrap++;

                        if($wrap > $cols)
                            {
                            $wrap = 1;
                            ?>
                            </tr>
                            <tr>
                            <?php
                            }

                        if('' != $node['name'])
                            {
                            ?>
                            <td valign=middle>
                                <input id="nodes_searched_<?php echo $node['ref']; ?>" class="nodes_input_checkbox" type="checkbox" name="nodes_searched[<?php echo $field['ref']; ?>][]" value="<?php echo $node['ref']; ?>" <?php if ((0 < count($searched_nodes) && in_array($node['ref'], $searched_nodes)) || in_array(i18n_get_translated($node['name']),$setnames)) {?>checked<?php } ?> <?php if ($autoupdate) { ?>onClick="UpdateResultCount();"<?php } ?>>
                                <label class="customFieldLabel" for="nodes_searched_<?php echo $node['ref']; ?>">
                                    <?php echo escape(i18n_get_translated($node['name'])); ?>
                                </label>
                            </td>
                            <?php
                            }
                        }
                        ?>
                        </tr>
                    </table>
<?php
                    }

                }
            }
        break;

        case FIELD_TYPE_DATE_AND_OPTIONAL_TIME:
        case FIELD_TYPE_EXPIRY_DATE: 
        case FIELD_TYPE_DATE: 
        case FIELD_TYPE_DATE_RANGE: 
        $found_year='';$found_month='';$found_day='';$found_start_year='';$found_start_month='';$found_start_day='';$found_end_year='';$found_end_month='';$found_end_day='';
        if (!$forsearchbar && $daterange_search)
            {
            render_date_range_field($name, $value, true, $autoupdate, array(), $reset);
            }
        else
            {
            $s=explode("|",$value);
            if(is_array($s))
                {
                $found_year  = $s[0];
                $found_month = (array_key_exists(1, $s)) ? $s[1] : '';
                $found_day   = (array_key_exists(2, $s)) ? $s[2] : '';
                }
            ?>      
            <select name="<?php echo escape($name);?>-y" id="<?php echo $id?>-y" class="SearchWidth<?php if ($forsearchbar){ echo "Half";} ?>" style="width:120px;" <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } ?>>
              <option value=""><?php echo escape($lang["anyyear"])?></option>
              <?php
              $y=date("Y");
              $y += $maxyear_extends_current;
              for ($d=$y;$d>=$minyear;$d--)
                {
                ?><option <?php if ($d==$found_year) { ?>selected<?php } ?>><?php echo $d?></option><?php
                }
              ?>
            </select>

            <?php if ($forsearchbar && $searchbyday) { ?><br /><?php } ?>

            <select name="<?php echo escape($name);?>-m" id="<?php echo $id?>-m" class="SearchWidth<?php if ($forsearchbar){ echo "Half SearchWidthRight";} ?>" style="width:120px;" <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } ?>>
              <option value=""><?php echo escape($lang["anymonth"])?></option>
              <?php
              for ($d=1;$d<=12;$d++)
                {
                $m=str_pad($d,2,"0",STR_PAD_LEFT);
                ?><option <?php if ($d==$found_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo escape($lang["months_list"][$d-1]); ?></option><?php
                }
              ?>
            </select>

            <?php if (!$forsearchbar || ($forsearchbar && $searchbyday)) 
                { 
                ?>
                <select name="<?php echo escape($name);?>-d" id="<?php echo $id?>-d" class="SearchWidth<?php if ($forsearchbar){ echo "Half";} ?>" style="width:120px;" <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } ?>>
                  <option value=""><?php echo escape($lang["anyday"])?></option>
                  <?php
                  for ($d=1;$d<=31;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
                    }
                  ?>
                </select>
                <?php 
                }
            if($forsearchbar)
                {
                # Add to the clear function so clicking 'clear' clears this box.
                $clear_function.="
					document.getElementById('field_" . $field["ref"] . "-y').selectedIndex=0;
					document.getElementById('field_" . $field["ref"] . "-m').selectedIndex=0;
					";
                if($searchbyday)
                    {
                    $clear_function.="document.getElementById('field_" . $field["ref"] . "-d').selectedIndex=0;";
                    }
                }
            }

        break;

        case FIELD_TYPE_CATEGORY_TREE:
        global $category_tree_add_parents, $category_tree_search_use_and;

        $set  = preg_split('/[;\|]/', cleanse_string($value, true));
        $name = "nodes_searched[{$field['ref']}][]";

        /*
        For search, category trees work slightly different than the intended behaviour shown in edit_fields/7.php:
        Intended behaviour:
        1. Selecting a sub (child) node will automatically select all parent nodes up to and including the root level,
        unless the option $category_tree_add_parents is set to false

        On search this should work like this:
        Selecting a sub (child) node will NOT select all parent nodes unless the system is configured to search using AND
        */
        $category_tree_add_parents = $category_tree_search_use_and;

        if($forsearchbar)
            {
            $original_category_tree_open = $category_tree_open;
            $category_tree_open          = true;
            $treeonly                    = true;
            $status_box_elements         = '';

            ?>
            <script type="text/javascript">
                jQuery(document).ready(function()
                {
                    jQuery('#SearchBox').on('categoryTreeChanged', function(e,node)
                    {
                        FilterBasicSearchOptions('<?php echo escape($field["name"]) ?>',[<?php echo escape((string)$field["resource_types"]) ?>]);
                    });
                });
            </script>
            <?php

            foreach($searched_nodes as $node)
                {
                $n_details = array();

                if(get_node($node, $n_details)===false)
                    {
                    continue;
                    }

                if($n_details["resource_type_field"] != $field["ref"])
                    {
                    continue;
                    }

                // Show previously searched options on the status box
                $status_box_elements .= "
                <div id=\"tree_{$field['ref']}_selected_{$n_details['ref']}\" class=\"tree_{$field['ref']}_options_status\">
                    <span id=\"nodes_searched_{$field['ref']}_statusbox_option_{$n_details['ref']}\">{$n_details['name']}</span><br />
                </div>";
                }
            ?>
            <div id="field_<?php echo escape($field['name']); ?>">
                <div id="nodes_searched_<?php echo $field['ref']; ?>_statusbox" class="MiniCategoryBox">
                    <?php echo $status_box_elements; ?>
                </div> 
                <a href="#"
                   onClick="
                        jQuery('#cattree_<?php echo $field['name']; ?>').slideToggle();

                        return false;"><?php echo escape($lang['showhidetree']); ?></a>
                        <div id="cattree_<?php echo $field['name']; ?>" class="RecordPanel PopupCategoryTree">
                    <?php
                    include __DIR__ . '/../pages/edit_fields/7.php';

                    // Reset category_tree_open because normally searchbar occurs before edit/ advanced search page
                    $category_tree_open = $original_category_tree_open;
                    ?>
                </div>

            </div>
            <?php
            # Add to clear function
            $clear_function .= "
                jQuery('#search_tree_{$field['ref']}').jstree({
                    'core' : {
                        'themes' : {
                            'name' : 'default-dark',
                            'icons': false
                        }
                    }
                }).deselect_all();

                /* remove the hidden inputs */
                var elements = document.getElementsByName('nodes_searched[{$field['ref']}][]');
                while(elements[0])
                    {
                    elements[0].parentNode.removeChild(elements[0]);
                    }

                /* update status box */
                var node_statusbox = document.getElementById('nodes_searched_{$field['ref']}_statusbox');
                while(node_statusbox.lastChild)
                    {
                    node_statusbox.removeChild(node_statusbox.lastChild);
                    }
                ";
            }
        else
            {
            # For advanced search and elsewhere, include the category tree.
            include __DIR__ . "/../pages/edit_fields/7.php";
            }
        break;

        case FIELD_TYPE_DYNAMIC_KEYWORDS_LIST:
            include __DIR__ . '/../pages/edit_fields/9.php';
        break;      

        case FIELD_TYPE_RADIO_BUTTONS:
            // auto save is not needed when searching
            $edit_autosave           = false;
            $display_as_radiobuttons = false;
            $display_as_checkbox     = true;
            $name                    = "nodes_searched[{$field['ref']}][]";

            if($forsearchbar || $field['display_as_dropdown'])
                {
                $display_as_dropdown = true;
                $display_as_checkbox = false;
                $name                = "nodes_searched[{$field['ref']}]";

                $clear_function .= "document.getElementsByName('{$name}')[0].selectedIndex = -1;";
                }

            include __DIR__ . '/../pages/edit_fields/12.php';
            // need to adjust the field's name value
            ?>
            <script type="text/javascript">
                jQuery("#field_<?php echo $field['ref']; ?>").attr('name', 'field_<?php echo $field["name"]; ?>');
            </script>
            <?php
        break;
        } ## END CASE
    ?>
    <div class="clearerleft"> </div>
    </div>
    <!-- ************************************************ -->
<?php
    } # End of render_search_field

/**
* Renders sort order functionality as a dropdown box
*
*/
function render_sort_order(array $order_fields,$default_sort_order)
    {
    global $order_by, $baseurl_short, $lang, $search, $archive, $restypes, $k, $sort, $date_field;

    // use query strings here as this is used to render elements and sometimes it
    // can depend on other params
    $modal  = ('true' == getval('modal', ''));
    $access  = getval('access', '');
    $sort = validate_sort_value($sort) ? mb_strtoupper($sort) : 'DESC';
    ?>
    <select id="sort_order_selection" onChange="UpdateResultOrder();" aria-label="<?php echo escape($lang["sortorder"]) ?>">
    
    <?php
    $options = '';
    foreach($order_fields as $name => $label)
        {
        // date shows as 'field'.$date_field rather than 'date' for collection searches so let's fix it
        if($name=='field'.$date_field)
            {
            $name='date';
            }
        
        // Are we constructing the option for the default order (ie. the first entry in the order_fields array)
        $current_is_default = ($name == $default_sort_order);

        // Is the currently set order that of the current field
        $selected = ($order_by == $name || ($name=='date' && $order_by=='field'.$date_field));
        
        // Build the option:
        $option = '<option value="' . $name . '"';

        // Set selection attribute if necessary
        if(($selected && $current_is_default) || $selected)
            {
            $option .= ' selected';
            }

        $option .= sprintf('
                data-url="%spages/search.php?search=%s&amp;order_by=%s&amp;archive=%s&amp;k=%s&amp;restypes=%s&amp;access=%s"
            ',
            $baseurl_short,
            urlencode($search),
            $name,
            urlencode($archive),
            urlencode($k),
            urlencode($restypes),
            urlencode($access)
        );

        $option .= '>';
        $option .= $label;
        $option .= '</option>';

        // Add option to the options list
        $options .= $option;
        }

        hook('render_sort_order_add_option', '', array($options));
        echo $options;
    ?>
    </select>
    &nbsp;
    <a href="#" class="update_result_order_button" onClick="UpdateResultOrder(true);" aria-label="<?php echo escape($sort === "ASC" ? $lang['sortorder-asc'] : $lang['sortorder-desc']) ?>">
        <i id="sort_selection_toggle"
            class="icon-<?php echo mb_strtolower($sort) == 'desc' ? 'arrow-down-wide-narrow' : 'arrow-up-narrow-wide'; ?> "></i>
    </a>

    <script>
    function UpdateResultOrder(toggle_order)
        {
        var selected_option = jQuery('#sort_order_selection :selected');
        var option_url      = selected_option.data('url');
        var sort_by         = jQuery('#sort_order_selection').find(":selected").val();

        if (toggle_order)
            {
            var selected_sort_option='<?php echo $sort == 'ASC' ? 'DESC' : 'ASC'; ?>';
            }
        else
            {
            if(sort_by == 'resourcetype' || sort_by == 'collection')
                {
                // The default sort should be ascending when sorting by resource type
                var selected_sort_option = 'ASC';
                }
            else
                {
                var selected_sort_option = 'DESC';
                }
            }
        option_url += '&sort=' + selected_sort_option;
         <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(option_url);
        }
    </script>
    <?php
    }

/**
* Renders a dropdown option
* 
*/
function render_dropdown_option($value, $label, array $data_attr = array(), $extra_tag_attributes  = '')
    {
    $result = '<option value="' . escape($value) . '"';

    // Add any extra tag attributes
    if(trim($extra_tag_attributes) !== '')
        {
        $result .= ' ' . $extra_tag_attributes;
        }

    // Add any data attributes you may need
    foreach($data_attr as $data_attr_key => $data_attr_value)
        {
        $data_attr_key = str_replace(' ', '_', $data_attr_key);

        $result .= ' data-' . $data_attr_key . '="' . $data_attr_value . '"';
        }

    $result .= '>' . escape($label) . '</option>';

    return $result;
    }


/**
* Renders search actions functionality as a dropdown box
* 
* @param array   $collection_data  Collection data
* @param boolean $top_actions      Set to true if actions are to be rendered in the search filter bar (above results)
* @param boolean $two_line         Display on two lines
* @param string  $id               Selector HTML ID
* @param array   $resource_data    Resource data
* @param boolean $optionsonly      Render only options
* @param string  $forpage          Specifically target for which page actions apply
* 
* @return void
*/
function render_actions(array $collection_data, $top_actions = true, $two_line = true, $id = '',$resource_data=array(),$optionsonly=false, $forpage="")
    {
    if(hook('prevent_running_render_actions'))
        {
        return;
        }

    global $baseurl, $lang, $k, $pagename, $order_by, $sort, $USER_SELECTION_COLLECTION;

    $is_selection_collection = isset($collection_data['ref']) && $collection_data['ref'] == $USER_SELECTION_COLLECTION;
    
    // globals that could also be passed as a reference
    global $result /*search result*/;

    $action_selection_id = ($forpage!=""?$forpage:$pagename) . '_action_selection' . $id;
    if(!$top_actions)
        {
        $action_selection_id .= '_bottom';
        }
    if(isset($collection_data['ref']))
        {
        $action_selection_id .= '_' . str_replace("-","_",$collection_data['ref']);
        }
    

    if(!$optionsonly)
            {?>
    
            <div class="ActionsContainer  <?php if($top_actions) { echo 'InpageNavLeftBlock'; } ?>"
                data-actions-loaded="<?php echo (int) $is_selection_collection; ?>"
            >
                <?php
        
                if($two_line)
                    {
                    ?>
                    <br />
                    <?php
                    }
                    ?>
                <select onchange="action_onchange_<?php echo escape($action_selection_id); ?>(this.value);"
                    id="<?php echo escape($action_selection_id); ?>"
                    <?php if(!$top_actions) { ?>
                        class="SearchWidth"
                    <?php } else { ?>
                        accesskey="A"
                    <?php } ?>
                    aria-label="<?php echo escape($lang["actions"]) ?>">
            <?php } ?>
            <option class="SelectAction" selected disabled hidden value=""><?php echo escape($lang["actions-select"])?></option>
            <?php

            // Collection Actions
            $collection_actions_array = compile_collection_actions($collection_data, $top_actions, $resource_data);
            // Usual search actions
            $search_actions_array = compile_search_actions($top_actions);

            // Remove certain actions that apply only to searches
            if(!$top_actions)
                {
                $action_index_to_remove = array_search('search_items_disk_usage', array_column($search_actions_array, 'value'));
                unset($search_actions_array[$action_index_to_remove]);
                $search_actions_array = array_values($search_actions_array);
                
                $action_index_to_remove = array_search('save_search_items_to_collection', array_column($search_actions_array, 'value'));
                unset($search_actions_array[$action_index_to_remove]);
                $search_actions_array = array_values($search_actions_array);

                if($forpage === "themes")
                    {
                    $action_index_to_remove = array_search('remove_collection', array_column($collection_actions_array, 'value'));
                    unset($collection_actions_array[$action_index_to_remove]);
                    $collection_actions_array = array_values($collection_actions_array);
                    }
                }
    
            /**
            * @var A global variable that allows other parts in ResourceSpace to append extra options to the actions 
            * unified dropdown (plugins can use existing hooks).
            */
            $render_actions_extra_options = array();
            if(
                isset($GLOBALS["render_actions_extra_options"])
                && is_array($GLOBALS["render_actions_extra_options"])
                && !empty($GLOBALS["render_actions_extra_options"]))
                {
                $render_actions_extra_options = $GLOBALS["render_actions_extra_options"];
                }

            $actions_array = array_merge($collection_actions_array, $search_actions_array, $render_actions_extra_options);
            unset($render_actions_extra_options);

            $modify_actions_array = hook('modify_unified_dropdown_actions_options', '', array($actions_array,$top_actions));

            if(!empty($modify_actions_array))
                {
                $actions_array = $modify_actions_array;
                }

            /**
            * @var A global variable that allows other parts in ResourceSpace to filter actions options (plugins can use 
            * existing hooks).
            */
            if(isset($GLOBALS["render_actions_filter"]) && is_callable($GLOBALS["render_actions_filter"]))
                {
                $actions_array = array_filter($actions_array, $GLOBALS["render_actions_filter"]);
                unset($GLOBALS["render_actions_filter"]);
                }

            // Sort array into category groups
            usort($actions_array, function($a, $b){
               if(isset($a['category']) && isset($b['category']))
                    {
                    if($a['category'] == $b['category'])
                        {
                        // Same category, check for order_by. If no order_by add to end of category
                        if(isset($a['order_by']) && (!isset($b['order_by']) || ($b['order_by'] > $a['order_by'])))
                            {
                            return -1;
                            }
                        return 1;
                        }
                    else
                        {
                        return  $a['category'] - $b['category'];
                        }
                    }
                else
                    {
                    return isset($a['category']) ? -1 : 1;
                    }
                });
                                    
            // loop and display
            $options='';
            $lastcategory = 0;
            for($a = 0; $a < count($actions_array); $a++)
                {
                // Is this a new category?
                if(!isset($actions_array[$a]['category']))
                    {
                    $actions_array[$a]['category'] = 999;  
                    }
                if($lastcategory != $actions_array[$a]['category'])
                    {
                    if($a > 0)
                        {
                        $options .= "</optgroup>\n";
                        }
                    $options .= "<optgroup label='" . escape($lang["collection_actiontype_" . $actions_array[$a]['category']]) . "'>\n";
                    }

                if(!isset($actions_array[$a]['data_attr']))
                    {
                    $actions_array[$a]['data_attr'] = array();
                    }

                if(!isset($actions_array[$a]['extra_tag_attributes']))
                    {
                    $actions_array[$a]['extra_tag_attributes'] = '';
                    }

                $options .= render_dropdown_option($actions_array[$a]['value'], $actions_array[$a]['label'], $actions_array[$a]['data_attr'], $actions_array[$a]['extra_tag_attributes']);

                $add_to_options = hook('after_render_dropdown_option', '', array($actions_array, $a));
                if($add_to_options != '')
                    {
                    $options .= $add_to_options;
                    }
                if($a == count($actions_array))
                    {
                    $options .= "\n</optgroup>\n";
                    }
                $lastcategory = $actions_array[$a]['category'];
                }

            echo $options;
            
            if(!$optionsonly)
                { ?>
                </select>
                <?php } ?>
        <script>
        function action_onchange_<?php echo $action_selection_id; ?>(v)
            {
            if(v == '')
                {
                return false;
                }

            v = v.match(/^[^~]*/i)[0]; // Remove unique value identifier: ~id

            switch(v)
                {
            <?php
            if(0 !== count($collection_data) && collection_readable($collection_data['ref']))
                {
                ?>
                case 'select_collection':
                    ChangeCollection(<?php echo $collection_data['ref']; ?>, '');
                    break;

                case 'remove_collection':
                    if(confirm("<?php echo escape($lang['removecollectionareyousure']); ?>")) {
                        // most likely will need to be done the same way as delete_collection
                        var post_data = {
                            ajax: true,
                            dropdown_actions: true,
                            remove: <?php echo urlencode($collection_data['ref']); ?>,
                            <?php echo generateAjaxToken("remove_collection"); ?>
                        };

                        jQuery.post('<?php echo $baseurl; ?>/pages/collection_manage.php', post_data, 'json')
                        .always(function(){
                            CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php');
                        }); 
                        
                    }
                    break;

                case 'purge_collection':
                    if(confirm('<?php echo escape($lang["purgecollectionareyousure"]); ?>'))
                        {
                        document.getElementById('collectionpurge').value='".urlencode($collections[$n]["ref"])."';
                        document.getElementById('collectionform').submit();
                        }
                    break;

                case 'relate_all':
                    var collection = <?php echo urlencode($collection_data['ref']);?>;
                    jQuery.ajax({
                        type: 'POST',
                        url: baseurl_short + 'pages/ajax/relate_resources.php?collection=' + collection,
                        data: {<?php echo generateAjaxToken("relate_resources"); ?>},
                        success: function(data) {
                            if (data.trim() == "SUCCESS") {
                                styledalert('<?php echo escape($lang["complete"])?>', '<?php echo escape($lang['relateallresources_confirmation'])?>');
                            }
                        },
                        error: function (err) {
                            console.log("AJAX error : " + JSON.stringify(err, null, 2));
                        }
                    }); 
                    break;

                case 'unrelate_all':
                    var collection = <?php echo urlencode($collection_data['ref']);?>;
                    jQuery.ajax({
                        type: 'POST',
                        url: baseurl_short + 'pages/ajax/unrelate_resources.php?collection=' + collection,
                        data: {<?php echo generateAjaxToken("unrelate_resources"); ?>},
                        success: function(data) {
                            if (data.trim() == "SUCCESS") {
                                styledalert('<?php echo escape($lang["complete"])?>', '<?php echo escape($lang['unrelateallresources_confirmation'])?>');
                            }
                        },
                        error: function (err) {
                            console.log("AJAX error : " + JSON.stringify(err, null, 2));
                        }
                    }); 
                    break;
                <?php
                }

            if((!$top_actions || !empty($collection_data)) &&  $collection_data['type'] != COLLECTION_TYPE_REQUEST)
                {
                global $search;
                $search_collection='';
                if(substr($search,0,11)=='!collection')
                    {
                    $search_trimmed = substr($search,11); // The collection search must always be the first part of the search string
                    $search_elements = split_keywords($search_trimmed, false, false, false, false, true);
                    $search_collection = (int)$search_elements[0];
                    }
                ?>
                case 'delete_collection':
                    if(confirm('<?php echo escape($lang["collectiondeleteconfirm"]); ?>')) {
                        var post_data = {
                            ajax: true,
                            dropdown_actions: true,
                            delete: <?php echo urlencode($collection_data['ref']); ?>,
                            <?php echo generateAjaxToken("delete_collection"); ?>
                        };

                        jQuery.post('<?php echo $baseurl; ?>/pages/collection_manage.php', post_data, function(response) {
                            if(response.success === 'Yes')
                                {
                                CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php?collection=' + response.redirect_to_collection + '&k=' + response.k + '&nc=' + response.nc);

                                if(basename(document.URL).substr(0, 17) === 'collection_manage')
                                    {
                                    CentralSpaceLoad(document.URL);
                                    }
                                else if(basename(document.URL).substr(0, 6) === 'search' && '<?php echo $search_collection?>'=='<?php echo $collection_data["ref"]; ?>')
                                    {
                                    CentralSpaceLoad('<?php echo $baseurl; ?>/pages/search.php?search=!collection' + response.redirect_to_collection, true);
                                    }
                                else if(basename(document.URL).substr(0, 20) === 'collections_featured'){
                                    CentralSpaceLoad(document.URL);
                                    }
                                }
                        }, 'json');    
                    }
                    break;
                <?php
                }

            // Add extra collection actions javascript case through plugins
            // Note: if you are just going to a different page, it should be easily picked by the default case
            $extra_options_js_case = hook('render_actions_add_option_js_case', '', array($action_selection_id));
            if(trim($extra_options_js_case) !== '')
                {
                echo $extra_options_js_case;
                }
            ?>

                case 'save_search_to_collection':
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    CollectionDivLoad(option_url);
                    break;

                case 'save_search_to_dash':
                    var option_url  = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    var option_link = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('link');
                    
                    // Dash requires to have some search parameters (even if they are the default ones)
                    if((basename(option_link).substr(0, 10)) != 'search.php')
                        {
                        option_link = (window.location.href).replace(window.baseurl, '');
                        }

                    option_url    += '&link=' + option_link;

                    CentralSpaceLoad(option_url);
                    break;

                case 'save_search_smart_collection':
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    CollectionDivLoad(option_url);
                    break;

                case 'save_search_items_to_collection':
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    CollectionDivLoad(option_url);
                    break;

                case 'empty_collection':
                    if(!confirm('<?php echo escape($lang["emptycollectionareyousure"]); ?>'))
                        {
                        break;
                        }

                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    CollectionDivLoad(option_url);
                    break;

                case 'copy_collection':
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    ModalLoad(option_url, false, true);
                    break;

            <?php
            if(!$top_actions || $is_selection_collection)
                {
                ?>
                case 'delete_all_in_collection':
                    if(confirm('<?php echo escape($lang["deleteallsure"]); ?>'))
                        {
                        api('delete_resources_in_collection',
                            {'collection': '<?php echo (int) $collection_data["ref"]; ?>'},
                            function(response)
                                {
                                console.debug('Deleted resources in collection');
                                if (response) {
                                    <?php
                                    if ($is_selection_collection)
                                        {
                                        ?>
                                        CentralSpaceLoad(window.location.href, null, null, false);
                                        <?php
                                        }
                                    else
                                        {
                                        ?>
                                        CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php?collection=<?php echo (int) $collection_data["ref"]; ?>');
                                        <?php
                                        }
                                        ?>
                                    }
                                else
                                    {
                                    styledalert("<?php echo escape($lang["error"]); ?>", "<?php echo escape($lang["error-editpermissiondenied"]); ?>");
                                    }
                                },
                            <?php echo generate_csrf_js_object('delete_all_in_collection'); ?>
                        );
                        }
                    break;
                <?php
                }
                ?>

            <?php
            if(!$top_actions)
                {
                ?>
                    case 'hide_collection':
                        var action = 'hidecollection';
                        var collection = <?php echo urlencode($collection_data['ref']);?>;
                        var mycol = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('mycol');

                        jQuery.ajax({
                            type: 'POST',
                            url: baseurl_short + 'pages/ajax/showhide_collection.php?action=' + action + '&collection=' + collection,
                            data: {<?php echo generateAjaxToken("hide_collection"); ?>},
                            success: function(data) {
                                if (data.trim() == "HIDDEN") {
                                    CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php?collection='+mycol);
                                }
                            },
                            error: function (err) {
                                console.log("AJAX error : " + JSON.stringify(err, null, 2));
                            }
                        }); 
                        break;
                <?php
                }
                ?>

                default:
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    var option_callback = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('callback');
                    var option_no_ajax = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('no-ajax');

                    // If action option has a defined data-callback attribute, then we can call it
                    // IMPORTANT: never allow callback data attribute to be input/saved by user. Only ResourceSpace should
                    // generate the callbacks - key point is "generate"
                    if(typeof option_callback !== "undefined")
                        {
                        eval(option_callback);
                        }

                    // If action option has a defined data-url attribute, then we can CentralSpaceLoad it
                    if(typeof option_url !== "undefined")
                        {
                        if (typeof option_no_ajax == "undefined")
                            {
                            CentralSpaceLoad(option_url, true);
                            }
                        else
                            {
                            window.location.href = option_url;
                            }
                        }
    
                    break;
                }
                
                // Go back to no action option
                jQuery('#<?php echo $action_selection_id; ?> option[value=""]').prop('selected', true);
                

        }
        </script>
        
    <?php if (!$optionsonly)
        {?>
        </div>
        <?php
        }
    }



/**
* @param string $name
* @param array  $current  Current selected values (eg. array(1, 3) for Admins and Super admins user groups selected)
* @param int    $size     How many options to show before user has to scroll
*/
function render_user_group_multi_select($name, array $current = array(), $size = 10, $style = '')
    {
    ?>
    <select id="<?php echo escape($name); ?>" class="MultiSelect" name="<?php echo escape($name); ?>[]" multiple="multiple" size="<?php echo $size; ?>" style="<?php echo $style; ?>">
    <?php
    foreach(get_usergroups() as $usergroup)
        {
        ?>
        <option value="<?php echo (int) $usergroup['ref']; ?>"<?php echo in_array($usergroup['ref'], $current) ? ' selected' : ''; ?>><?php echo escape($usergroup['name']); ?></option>
        <?php
        }
        ?>
    </select>
    <?php
    }


/**
* Renders a list of user groups
* 
* @param string $name
* @param array  $current  Current selected values (eg. array(1, 3) for Admins and Super admins user groups selected)
* @param string $style    CSS styling that will apply to the outer container (ie. table element)
*
* @return void
*/
function render_user_group_checkbox_select($name, array $current = array(), $style = '')
    {
    ?>
    <table id="<?php echo escape($name); ?>"<?php if('' !== $style) { ?>style="<?php echo $style; ?>"<?php } ?>>
        <tbody>
    <?php
    foreach(get_usergroups(true) as $group)
        {
        ?>
        <tr>
            <td><input id="<?php echo escape($name . '_' . $group['ref']); ?>" type="checkbox" name="<?php echo escape($name); ?>[]" value="<?php echo $group['ref']; ?>"<?php if(in_array($group['ref'], $current)) { ?> checked<?php } ?> /></td>
            <td><label for="<?php echo escape($name . '_' . $group['ref']); ?>"><?php echo $group['name']; ?></label></td>
        </tr>
        <?php
        }
        ?>
        </tbody>
    </table>
    <?php
    }

/**
* render_text_input_question - Used to display a question with simple text input
* 
* @param string $label          Label of question
* @param string $input          Name of input field
* @param string $additionaltext Text to to display after input
* @param boolean $numeric       Set to true to force numeric input
* @param array  $ctx            Rendering context. Should be used to inject different elements (e.g set the div class)
* 
* @return void
*/
function render_text_question($label, $input, $additionaltext="", $numeric=false, $extra="", $current="", array $ctx = array())
    {
    $div_class = array("Question");
    if(isset($ctx["div_class"]) && is_array($ctx["div_class"]) && !empty($ctx["div_class"]))
        {
        $div_class = array_merge($div_class, $ctx["div_class"]);
        }
    ?>
    <div id="question_<?php echo $input; ?>" class="<?php echo implode(" ", $div_class); ?>" >
        <label for="<?php echo escape($input); ?>_input"><?php echo $label; ?></label>
        <?php
        printf('<input name="%s" id="%s_input" type="%s" value="%s"%s/>',
            escape($input),
            escape($input),
            $numeric ? "number" : "text",
            escape((string) $current),
            $extra
        );
            
        echo $additionaltext;
        ?>
        <div class="clearerleft"> </div>
    </div>
    <?php
    }
    
/**
* render_split_text_question - Used to display a question with two inputs e.g. for a from/to range
* 
* @param string $label  Label of question
* @param array  $inputs  Array of input names and labels(eg. array('pixelwidthmin'=>'From','pixelwidthmin'=>'To')
* @param string $additionaltext (optional)  Text to to display after input
* @param boolean $numeric                   Set to true to force numeric input
*/
function render_split_text_question($label, $inputs = array(), $additionaltext="", $numeric=false, $extra="", $currentvals=array())
    {
    ?>
    <div class="Question" id = "pixelwidth">
        <label><?php echo $label; ?></label>
        <div>
        <?php
        foreach ($inputs as $inputname=>$inputtext)
            {
            echo "<div class=\"SplitSearch\">" . $inputtext . "</div>\n";
            echo "<input name=\"" . $inputname . "\" class=\"SplitSearch\" type=\"text\"". ($numeric?"numericinput":"") . "\" value=\"" . $currentvals[$inputname] . "\"" . $extra . " />\n";
            }
        echo $additionaltext;
        ?>
        </div>
    </div>
    <div class="clearerleft"> </div>
    <?php
    }

/**
* render_dropdown_question - Used to display a question with a dropdown selector
* 
* @param string $label     Label of question
* @param string $inputname Name of input field
* @param array  $options   Array of options (value and text pairs) (eg. array('pixelwidthmin'=>'From','pixelwidthmin'=>'To')
* @param string $current   The current selected value
* @param string $extra     Extra attributes used on the selector element
* @param array  $ctx       Rendering context. Should be used to inject different elements (e.g set the div class, add onchange for select)
* 
* @return void
*/
function render_dropdown_question($label, $inputname, $options = array(), $current="", $extra="", array $ctx = array())
    {
    $div_class = array("Question");
    if(isset($ctx["div_class"]) && is_array($ctx["div_class"]) && !empty($ctx["div_class"]))
        {
        $div_class = array_merge($div_class, $ctx["div_class"]);
        }
    $input_class = isset($ctx["input_class"]) ? $ctx["input_class"] : "stdwidth";

    $onchange = (isset($ctx["onchange"]) && trim($ctx["onchange"]) != "" ? trim($ctx["onchange"]) : "");
    $onchange = ($onchange != "" ? sprintf("onchange=\"%s\"", $onchange) : "");

    $extra .= " {$onchange}";
    ?>
    <div class="<?php echo escape(implode(" ", $div_class)); ?>">
        <label><?php echo escape($label); ?></label>
        <select  name="<?php echo escape($inputname); ?>" class="<?php echo escape($input_class); ?>" id="<?php echo escape($inputname); ?>" <?php echo $extra; ?>>
        <?php
        foreach ($options as $optionvalue=>$optiontext)
            {
            ?>
            <option value="<?php echo escape(trim((string)$optionvalue))?>" <?php if (trim((string)$optionvalue)==trim((string)$current)) {?>selected<?php } ?>><?php echo escape(trim((string)$optiontext))?></option>
            <?php
            }
        ?>
        </select>
        <div class="clearerleft"></div>
    </div>
    <?php
    }

/**
* Render a table row (tr) for a single access key
* 
* @param array $record Access key record details
* 
* @return void
*/
function render_access_key_tr(array $record)
    {
    global $baseurl, $baseurl_short, $lang;
    $link      = '';
    $type      = '';
    $edit_link = '';

    // Set variable dependent on type (ie. Resource / Collection)
    if('' == $record['collection'] && '' != $record['resource'])
        {
        // For resource
        $link      = $baseurl . '?r=' . urlencode($record['resource']) . '&k=' . urlencode($record['access_key']);
        $type      = $lang['share-resource'];
        $edit_link = sprintf('%spages/resource_share.php?ref=%s&editaccess=%s&editexpiration=%s&editaccesslevel=%s&editgroup=%s&backurl=%s',
            $baseurl_short,
            urlencode($record['resource']),
            urlencode($record['access_key']),
            urlencode($record['expires']),
            urlencode($record['access']),
            urlencode($record['usergroup']),
            urlencode("/pages/team/team_external_shares.php")
        );
        }
    else
        {
        // For collection
        $link      = $baseurl . '?c=' . urlencode($record['collection']) . '&k=' . urlencode($record['access_key']);
        $type      = $lang['sharecollection'];
        $edit_link = sprintf('%spages/collection_share.php?ref=%s&editaccess=%s&editexpiration=%s&editaccesslevel=%s&editgroup=%s&backurl=%s',
            $baseurl_short,
            urlencode($record['collection']),
            urlencode($record['access_key']),
            urlencode($record['expires']),
            urlencode($record['access']),
            urlencode($record['usergroup']),
            urlencode("/pages/team/team_external_shares.php")
        );
        }
        ?>


    <tr id="access_key_<?php echo $record['access_key']; ?>">
        <td>
            <div class="ListTitle">
                <a href="<?php echo $link; ?>" target="_blank"><?php echo escape($record['access_key']); ?></a>
            </div>
        </td>
        <td><?php echo escape($type); ?></td>
        <td><?php echo escape(resolve_users($record['users'])); ?></td>
        <td><?php echo escape($record['emails']); ?></td>
        <td><?php echo escape(nicedate($record['maxdate'], true, true, true)); ?></td>
        <td><?php echo escape(nicedate($record['lastused'], true, true, true)); ?></td>
        <td><?php echo escape(('' == $record['expires']) ? $lang['never'] : nicedate($record['expires'], false)); ?></td>
        <td><?php echo escape((-1 == $record['access']) ? '' : $lang['access' . $record['access']]); ?></td>
        <td>
            <div class="ListTools">
                <a href="#" onClick="delete_access_key('<?php echo $record['access_key']; ?>', '<?php echo $record['resource']; ?>', '<?php echo $record['collection']; ?>');"><?php echo LINK_CARET ?><?php echo escape($lang['action-delete']); ?></a>
                <a href="<?php echo $edit_link; ?>"><?php echo LINK_CARET ?><?php echo escape($lang['action-edit']); ?></a>
            </div>
        </td>
    </tr>
    <?php
    }

/**
 * Checks whether a specified field should be displayed based on various conditions.
 *
 * This function evaluates several criteria to determine if a field is visible in the resource editing interface.
 * It takes into account the field's active status, permissions for viewing and editing, upload conditions, 
 * and any custom hooks that may hide the field.
 *
 * @param array $field The field configuration array containing information about the field's settings.
 * @return bool Returns true if the field should be displayed; false otherwise.
 */
function is_field_displayed($field)
    {
    global $ref, $resource, $upload_review_mode;

    # Conditions under which the field is not displayed
    return !(
        ($field['active']==0)
        # Field does not have individual write access allowed; and does not have edit access allowed on upload
        || (checkperm("F*") && !checkperm("F-" . $field["ref"]) && !($ref < 0 && checkperm("P" . $field["ref"])))
        # Field has write access denied directly
        || checkperm("F" . $field["ref"])
        # Field is hidden on upload
        || (($ref < 0 || $upload_review_mode) && $field["hide_when_uploading"])
        # Other field conditions
        || hook('edithidefield', '', array('field' => $field))
        || hook('edithidefield2', '', array('field' => $field)));
    }

/**
 * Displays a field in the resource edit interface.
 *
 * This function handles the rendering of various types of fields based on their configurations and context.
 * It supports multiple modes, such as batch editing, and adjusts the display based on user permissions,
 * errors from previous saves, and whether the field is being copied from another resource.
 *
 * @param int $n The index of the field in the form.
 * @param array $field The field configuration array containing settings and metadata for the field.
 * @param bool $newtab Indicates if the field should be displayed in a new tab.
 * @param bool $modal Indicates if the field is part of a modal dialog.
 */
function display_field($n, $field, $newtab=false,$modal=false)
  {
  debug_function_call(__FUNCTION__, [$n, $field['ref'], $newtab, $modal]);

  global $use, $ref, $original_fields, $multiple, $lastglobal,$is_template, $language, $lang,
  $blank_edit_template, $edit_autosave, $errors, $tabs_on_edit, $collapsible_sections, $ctrls_to_save,
  $embedded_data_user_select, $embedded_data_user_select_fields, $show_error, $save_errors, $baseurl, $is_search,
  $all_selected_nodes,$original_nodes, $FIXED_LIST_FIELD_TYPES, $TEXT_FIELD_TYPES, $DATE_FIELD_TYPES, $upload_review_mode, $check_edit_checksums, $locked_fields, $lastedited, $copyfrom, $fields;

  // Set $is_search to false in case page request is not an ajax load and $is_search hs been set from the searchbar
  $is_search=false;
  
  if(!isset($locked_fields))
    {
    $locked_fields = explode(",",getval("lockedfields",""));
    }

  if(!isset($copyfrom))
    {
    $copyfrom = getval('copyfrom', '');
    }

  $name="field_" . $field["ref"];
  $value=$field["value"];
  $value=trim((string) $value);
  $use_copyfrom=true;
  $omit_when_copying_enacted=false;
  if ($use != $ref && ($field["omit_when_copying"]))
        {
        debug("display_field: reverting copied value for field " . $field["ref"] . " as omit_when_copying is enabled");
        # Return this field value back to the original value, instead of using the value from the copied resource/metadata template
        # This is triggered if field has the 'omit_when_copying' flag set
        reset($original_fields);
        $use_copyfrom=false;
        foreach ($original_fields as $original_field)
            {
            if ($original_field["ref"]==$field["ref"])
                {
                $value=$original_field["value"];
                }
            }
        $selected_nodes = $original_nodes;
        $omit_when_copying_enacted=true;
        }
    elseif(($ref<0 || $upload_review_mode) && isset($locked_fields) && in_array($field["ref"], $locked_fields) && $lastedited > 0)
        {
        // Get value from last edited resource 
        debug("display_field: locked field " . $field['ref'] . ". Using nodes from last resource edited - " . $lastedited);
        $selected_nodes = get_resource_nodes($lastedited,$field["ref"]);
        }
    else
        {
        $selected_nodes = $all_selected_nodes;
        if (in_array($field["type"], $DATE_FIELD_TYPES) && !$GLOBALS['use_native_input_for_date_field'])
            {
            $submitted_val = sanitize_date_field_input($field["ref"], false);
            }
        else
            {
            $submitted_val = getval("field_" . $field['ref'], "");
            }
        if(!empty($save_errors) && $submitted_val != "")
            {
            // Set to the value that was submitted 
            $value = $submitted_val;
            }
        }

    $displaycondition=true;
    if ($field["display_condition"] != "") {
        // Check if field has a display condition set and render the client side check display condition functions
        $displaycondition = check_display_condition($n, $field, $fields, true, $use);
        debug(sprintf('$displaycondition = %s', json_encode($displaycondition)));
    }

    if ($multiple && 
    ( (getval("copyfrom","") == "" && getval('metadatatemplate', '') == "") 
      || str_replace(array(" ",","),"",(string)$value)=="") ) 
        {
        debug('Blank the value for multi-edits unless copying data from resource');
        $value="";
        }

    if ($field["global"] == 0 && $lastglobal && $collapsible_sections) { ?>
        </div>
        <h2 class="CollapsibleSectionHead" id="resource_type_properties"><?php echo escape($lang["typespecific"]); ?></h2>
        <div class="CollapsibleSection" id="ResourceProperties<?php if ($ref<0) {echo "Upload";} ?>TypeSpecificSection">
        <?php
    }

    # Blank form if 'reset form' has been clicked
    if('' != getval('resetform', ''))
        {
        $value = '';

        if(in_array($field['type'], $FIXED_LIST_FIELD_TYPES))
            {
            $selected_nodes = array();
            }
        $user_set_values = array();
        }
    // Copy from resource should only show values from the resource we are copying from
    elseif($ref != $use && $copyfrom != '')
        {
        $user_set_values = array();

        if(in_array($field['type'], $FIXED_LIST_FIELD_TYPES))
            {
            $field['node_options'] = array_filter($field['node_options'], 'node_is_active');
            }
        }
    else
        {
        debug("display_field: getting all user selected values from form data for field " . $field['ref']);
        $user_set_values = getval('nodes', [], false, 'is_array');
        debug(sprintf('$user_set_values = %s', json_encode($user_set_values)));
        }

    /****************************** Errors on saving ***************************************/
    $field_save_error = false;
    if (
        isset($show_error) 
        && isset($save_errors)
        && array_key_exists($field['ref'], $save_errors)
        ) {
        $field_save_error = true;
        }
     
    if ($multiple && !hook("replace_edit_all_checkbox","",array($field["ref"])))
      {
      # Multiple items, a toggle checkbox appears which activates the question
      ?>
      <div class="Question edit_multi_checkbox">
        <input name="editthis_<?php echo escape($name) ?>"
               id="editthis_<?php echo $n?>"
               type="checkbox"
               value="yes"
               <?php if($field_save_error){?> checked<?php } ?>
               onClick="batch_edit_toggle_edit_multi_checkbox_question(<?php echo (int) $n; ?>);" <?php if(getval("copyfrom","")!="" && $use_copyfrom && $value!=""){echo " checked" ;} ?>>&nbsp;
            <label for="editthis<?php echo $n?>"><?php echo escape($field["title"]) ?>
                <?php if(!$is_template && $field["required"]==1){ echo "<sup>*</sup>"; } ?>
            </label>
            <div class="clearerleft"></div>
        </div>
        <!-- End of edit_multi_checkbox -->
<?php
      }
  if ($multiple && !hook("replace_edit_all_mode_select","",array($field["ref"])))
      {
      # When editing multiple, give option to select Replace All Text or Find and Replace
      $onchangejs = '';
      if (in_array($field['type'], $FIXED_LIST_FIELD_TYPES))
          {
          $onchangejs = 'setActionPromptText(' . (int) $n . '); ';
          }
      $onchangejs .= "var fr = document.getElementById('findreplace_" . (int) $n . "');\n";
      $onchangejs .= "var q =  document.getElementById('question_" . (int) $n . "');\n";
      if ($field["type"] == FIELD_TYPE_CATEGORY_TREE)
        {
        $onchangejs .= "if (this.value=='RM'){branch_limit_field['field_" . $field["ref"] . "']=1;}else{branch_limit_field['field_" . $field["ref"] . "']=0;}";
        }
      elseif (in_array($field["type"], $TEXT_FIELD_TYPES ))
        {
        $onchangejs .= "
        var cf  = document.getElementById('copy_from_field_" . $field["ref"] . "');";

        if ($field["type"] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE) {
            $onchangejs .= "
            var rff = document.getElementById('remove_from_field_" . (int) $n . "');
            
            if (this.value == 'CF') {
                cf.style.display  = 'block';
                fr.style.display  = 'none';
                q.style.display   = 'none';
                rff.style.display = 'none';
            } else if (this.value == 'FR') {
                fr.style.display  = 'block';
                cf.style.display  = 'none';
                q.style.display   = 'none';
                rff.style.display = 'none';
            } else if (this.value == 'RM') {
                fr.style.display  = 'none';
                cf.style.display  = 'none';
                q.style.display   = 'none';
                rff.style.display = 'block';
            } else {
                fr.style.display  = 'none';
                cf.style.display  = 'none';
                q.style.display   = 'block';
                rff.style.display = 'none';
            }";
        } else {
            $onchangejs .= "
            if (this.value == 'CF') {
                cf.style.display  = 'block';
                fr.style.display  = 'none';
                q.style.display   = 'none';
            } else if (this.value == 'FR') {
                fr.style.display  = 'block';
                cf.style.display  = 'none';
                q.style.display   = 'none';
            } else {
                fr.style.display  = 'none';
                cf.style.display  = 'none';
                q.style.display   = 'block';
            }";
        }          

        }
      ?>
      <div class="Question" id="modeselect_<?php echo $n?>" style="<?php if($value=="" && !$field_save_error ){echo "display:none;";} ?>padding-bottom:0;margin-bottom:0;">
      <label for="modeselectinput"><?php echo escape($lang["editmode"])?></label>
      <select id="modeselectinput_<?php echo $n?>" name="modeselect_<?php echo $field["ref"]; ?>" class="stdwidth" onChange="<?php echo $onchangejs;hook ("edit_all_mode_js"); ?>">
      <option value="RT"><?php echo escape($lang["replacealltext"])?></option>
      <?php
      if (in_array($field["type"], $TEXT_FIELD_TYPES ))
        {
        # 'Find and replace', prepend and 'copy from field' options apply to text boxes only.
        ?>
        <option value="FR"<?php if(getval("modeselect_" . $field["ref"],"")=="FR"){?> selected<?php } ?>><?php echo escape($lang["findandreplace"])?></option>
        <option value="CF"<?php if(getval("modeselect_" . $field["ref"],"")=="CF"){?> selected<?php } ?>><?php echo escape($lang["edit_copy_from_field"])?></option>
        <option value="PP"<?php if(getval("modeselect_" . $field["ref"],"")=="PP"){?> selected<?php } ?>><?php echo escape($lang["prependtext"])?></option>
        <?php  
        }
      if((in_array($field['type'], $TEXT_FIELD_TYPES)) || in_array($field['type'], [FIELD_TYPE_CHECK_BOX_LIST, FIELD_TYPE_CATEGORY_TREE, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST]))
        {
        # Append applies to text boxes, checkboxes ,category tree and dynamic keyword fields onl.
        ?>
        <option value="AP"<?php if(getval("modeselect_" . $field["ref"],"")=="AP"){?> selected<?php } ?>><?php echo escape($lang["appendtext"])?></option>
<?php
        }
      if (
        # Remove applies to text boxes, checkboxes, dropdowns, category trees and dynamic keywords only. 
        in_array($field['type'], array_merge($TEXT_FIELD_TYPES, array(FIELD_TYPE_CHECK_BOX_LIST, FIELD_TYPE_DROP_DOWN_LIST, FIELD_TYPE_CATEGORY_TREE, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST)))
        # And it only applies if the field is optional
        && $field['required'] == 0 
        ) {
            ?> 
            <option value="RM"<?php if(getval("modeselect_" . $field["ref"],"")=="RM"){?> selected<?php } ?>><?php echo escape($lang["removetext"])?></option>
            <?php
        }
        hook ("edit_all_extra_modes","",[$field]);
        ?>
        </select>
      </div><!-- End of modeselect_<?php echo $n?> -->

      <?php
      if (in_array($field["type"], $TEXT_FIELD_TYPES))
        {
        render_field_selector_question("","copy_from_field_" . $field["ref"], array(), "stdwidth", true);
        }
        ?>

      <div class="Question" id="findreplace_<?php echo $n?>" style="display:none;border-top:none;">
        <label>&nbsp;</label>
        <?php echo escape($lang["find"])?> <input type="text" name="find_<?php echo $field["ref"]; ?>" class="shrtwidth">
        <?php echo escape($lang["andreplacewith"])?> <input type="text" name="replace_<?php echo $field["ref"]; ?>" class="shrtwidth">
      </div><!-- End of findreplace_<?php echo $n?> -->

    <?php 
    if ($field['type'] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE && $field['required'] == 0) {
    ?>
      <div class="Question" id="remove_from_field_<?php echo $n?>" style="display:none;border-top:none;">
        <label>&nbsp;</label>
        <textarea
            class="stdwidth MultiLine"
            rows=6
            cols=50
            name="remove_<?php echo $field["ref"]; ?>"
            id="remove_<?php echo $field["ref"]; ?>"
         ></textarea>
      </div><!-- End of remove_from_field_<?php echo $n?> -->
    <?php
    }?>

      <?php hook ("edit_all_after_findreplace","",array($field,$n)); 
      }

      if ($multiple && in_array($field['type'], $FIXED_LIST_FIELD_TYPES))
          {
          ?>
          <div class="Question" class="stdwidth" style="display:none;" id="displayexisting_<?php echo (int) $n; ?>" data-rtfid="<?php echo (int) $field['ref']; ?>">
            <div class="questionSpacer"></div>
            <p><?php echo escape($lang["edit_multiple_existing_values"]) . ':'; ?></p>
            <div class="questionSpacer"></div>
            <div>
              <div class="displayexisting_options" id="displayexisting_options_<?php echo (int) $n; ?>" data-questionid="<?php echo (int) $n; ?>"></div>
              <div class="questionSpacer"></div>
              <input type="button" id="displayexisting_options_more_<?php echo (int) $n; ?>" style="display:none;" value="+ <?php echo escape($lang["edit_multiple_show_more"]); ?>" onclick="showMoreSelectedOptions(<?php echo (int) $n; ?>);">
              </div>
          </div>
          <?php
          }
          ?>
      <div class="Question <?php if($upload_review_mode && in_array($field["ref"],$locked_fields)){echo " lockedQuestion ";} if($field_save_error) { echo 'FieldSaveError'; } ?>" id="question_<?php echo $n . ($multiple ? '' : '_' . $use); ?>"<?php
      if (($multiple && !$field_save_error) || !$displaycondition || $newtab)
        {?>style="border-top:none;<?php 
        if (($multiple && $value=="") || !$displaycondition)
        {
        debug('Hide this');
        ?>
        display:none;
        <?php
        }
        ?>"<?php
        }
     ?>>
     <?php
     if ($multiple && in_array($field['type'], $FIXED_LIST_FIELD_TYPES))
        {
        ?>
        <div id="ActionPrompt_<?php echo (int) $n; ?>">
          <div class="questionSpacer"></div>
          <p id="ActionPromptText_<?php echo (int) $n; ?>"></p>
       </div>
     <?php
        }
     $labelname = $name;
      
     // Add _selector to label so it will keep working:
     if($field['type'] == 9)
      {
      $labelname .= '_selector';
      }

      // Add -d to label so it will keep working
     if($field['type'] == 4)
        {
        $labelname .= '-d';
        }
        ?>
     <label for="<?php echo escape($labelname)?>" <?php if($field['type']==FIELD_TYPE_DATE_RANGE) {echo " class='daterangelabel'";} ?> >
     <?php 
     if (!$multiple) 
        {
        echo escape($field["title"]);
        if (!$is_template && $field["required"]==1)
            {
            echo "<sup>*</sup>";
            }
        } 
     if ($upload_review_mode)
        {
        renderLockButton($field["ref"], $locked_fields);
        }
        ?>
     </label>

     <?php
    # Autosave display
     if ($edit_autosave || $ctrls_to_save)
      {
      ?>
      <div class="AutoSaveStatus">
      <span id="AutoSaveStatus<?php echo $field["ref"]; ?>" style="display:none;"></span>
      </div>
      <?php
      } 

    #hook to modify field type in special case. Returning zero (to get a standard text box) doesn't work, so return 1 for type 0, 2 for type 1, etc.
     $modified_field_type="";
     $modified_field_type=(hook("modifyfieldtype"));
     if ($modified_field_type){$field["type"]=$modified_field_type-1;}

     hook("addfieldextras");
    # ----------------------------  Show field -----------------------------------
    $type = $field['type'];

    // Default to text type.
    if('' == $type)
        {
        $type = 0;
        }

    // The visibility status (block/none) will be sent to the server for validation purposes
    echo "<input id='field_" . (int) $field['ref']  . "_displayed' name='" . "field_" . (int) $field['ref']  . "_displayed' type='hidden' value='block'>";

    if(!hook('replacefield', '', array($field['type'], $field['ref'], $n)))
        {
        global $auto_order_checkbox, $auto_order_checkbox_case_insensitive, $FIXED_LIST_FIELD_TYPES, $is_search;
        
        // Establish the full set of selected nodes to be rendered for this field
        // Do this only if the field's selected nodes haven't previously been adjusted to take account of omit_when_copying

        if (!$omit_when_copying_enacted && isset($field['nodes'])) {
            $selected_nodes = array_unique(array_merge($selected_nodes, explode(",", $field['nodes'])));
        }

        debug(sprintf('$selected_nodes = %s', json_encode($selected_nodes)));

        if(in_array($field['type'], $FIXED_LIST_FIELD_TYPES))
            {
            $name = "nodes[{$field['ref']}]";

            // Sometimes we need to pass multiple options
            if(in_array($field['type'], array(FIELD_TYPE_CHECK_BOX_LIST, FIELD_TYPE_CATEGORY_TREE)))
                {
                $name = "nodes[{$field['ref']}][]";
                }
            elseif(FIELD_TYPE_DYNAMIC_KEYWORDS_LIST == $field['type'])
                {
                $name = "field_{$field['ref']}";
                }

            $field_nodes = [];
            foreach ($selected_nodes as $selected_node) {
                // Check node is valid                
                if (!isset($field['node_options'][$selected_node])) {
                    continue;
                }
                $field_nodes[] = $selected_node;
            }
            sort($field_nodes);
            debug(sprintf('$field_nodes = %s', json_encode($field_nodes)));
            if(!$multiple && getval("copyfrom","") == "" && getval('metadatatemplate', '') == "" && $check_edit_checksums)
                {
                echo "<input id='field_" . (int) $field['ref']  . "_checksum' name='" . "field_" . (int) $field['ref']  . "_checksum' type='hidden' value='" . md5(implode(",",$field_nodes)) . "'>";
                echo "<input id='field_" . (int) $field['ref']  . "_currentval' name='" . "field_" . (int) $field['ref']  . "_currentval' type='hidden' value='" . implode(",",$field_nodes) . "'>";
                }
            }
        elseif($field['type']==FIELD_TYPE_DATE_RANGE && getval("copyfrom","") == "" && getval('metadatatemplate', '') == "" && $check_edit_checksums)
            {
            $field['node_options'] = get_nodes($field['ref'], null, false);
            $field_nodes = array();
            foreach($selected_nodes as $selected_node)
                {
                if(in_array($selected_node,array_column($field['node_options'],"ref")))
                    {
                    $field_nodes[] = $selected_node;
                    }
                }
            sort($field_nodes);         
            debug(sprintf('$field_nodes = %s', json_encode($field_nodes)));
            echo "<input id='field_" . (int) $field['ref']  . "_checksum' name='" . "field_" . (int) $field['ref']  . "_checksum' type='hidden' value='" . md5(implode(",",$field_nodes)) . "'>";
            }
        elseif(!$multiple && getval("copyfrom","")=="" && getval('metadatatemplate', '') == "" && $check_edit_checksums)
            {
            echo "<input id='field_" . (int) $field['ref']  . "_checksum' name='" . "field_" . (int) $field['ref']  . "_checksum' type='hidden' value='" . md5(trim(preg_replace('/\s\s+/', ' ', (string) $field['value']))) . "'>";
            }
        elseif ($field['type'] === FIELD_TYPE_DATE && $GLOBALS['use_native_input_for_date_field'])
            {
            if ($GLOBALS['blank_date_upload_template'] && $value !== '' && $ref <= 0)
                {
                $value = '';
                }
            }

        $is_search = false;

        include "edit_fields/{$type}.php";
        $lastglobal = $field['global']==1;
        }
        
    # ----------------------------------------------------------------------------

    # Display any error messages from previous save
    if (array_key_exists($field["ref"],$errors))
      {
       ?>
       <div class="FormError">!! <?php echo $errors[$field["ref"]]; ?> !!</div>
       <?php
      }

    if (trim($field["help_text"]!=""))
     {
        # Show inline help for this field.
        # For certain field types that have no obvious focus, the help always appears
       ?>
       <div class="FormHelp" style="padding:0; clear:left;" id="help_<?php echo (int)$field["ref"]; ?>"><div class="FormHelpInner"><?php echo strip_tags_and_attributes(nl2br(trim(i18n_get_translated($field["help_text"]))))?></div></div>
<?php
     }
    if(($embedded_data_user_select || (isset($embedded_data_user_select_fields) && in_array($field["ref"],$embedded_data_user_select_fields))) && ($ref<0 && !$multiple))
    {
      ?>
      <table id="exif_<?php echo $field["ref"]; ?>" class="ExifOptions" cellpadding="3" cellspacing="3" <?php if ($embedded_data_user_select){?> style="display: none;" <?php } ?>>                    
         <tbody>
           <tr>        
             <td>
                <?php echo "&nbsp;&nbsp;" . escape($lang["embeddedvalue"]) . ": " ?>
             </td>
             <td width="10" valign="middle">
               <input type="radio" id="exif_extract_<?php echo $field["ref"]; ?>" name="exif_option_<?php echo $field["ref"]; ?>" value="yes" checked>
            </td>
            <td align="left" valign="middle">
               <label class="customFieldLabel" for="exif_extract_<?php echo $field["ref"]; ?>"><?php echo escape($lang["embedded_metadata_extract_option"]) ?></label>
            </td>


            <td width="10" valign="middle">
               <input type="radio" id="no_exif_<?php echo $field["ref"]; ?>" name="exif_option_<?php echo $field["ref"]; ?>" value="no">
            </td>
            <td align="left" valign="middle">
               <label class="customFieldLabel" for="no_exif_<?php echo $field["ref"]; ?>"><?php echo escape($lang["embedded_metadata_donot_extract_option"]) ?></label>
            </td>


            <td width="10" valign="middle">
               <input type="radio" id="exif_append_<?php echo $field["ref"]; ?>" name="exif_option_<?php echo $field["ref"]; ?>" value="append">
            </td>
            <td align="left" valign="middle">
               <label class="customFieldLabel" for="exif_append_<?php echo $field["ref"]; ?>"><?php echo escape($lang["embedded_metadata_append_option"]) ?></label>
            </td>


            <td width="10" valign="middle">
               <input type="radio" id="exif_prepend_<?php echo $field["ref"]; ?>" name="exif_option_<?php echo $field["ref"]; ?>" value="prepend">
            </td>
            <td align="left" valign="middle">
               <label class="customFieldLabel" for="exif_prepend_<?php echo $field["ref"]; ?>"><?php echo escape($lang["embedded_metadata_prepend_option"]) ?></label>
            </td>

         </tr>
      </tbody>
   </table>        
   <?php
  }
  ?>
  <div class="clearerleft"> </div>
  </div><!-- end of question_<?php echo $n?> div -->
  <?php     
  
  hook('afterfielddisplay', '', array($n, $field));
  }

/**
 * Renders a date range input field for resource editing.
 *
 * This function creates a set of input fields for selecting a start and end date. It supports EDTF format
 * and includes validation for the entered dates. It also provides functionality for handling multiple edit modes
 * and integrates with the autosave feature.
 *
 * @param string $name The base name for the input fields.
 * @param string $value The value of the date range (in a specific format).
 * @param bool $forsearch Indicates if the field is being used for a search query.
 * @param bool $autoupdate Indicates if the field should trigger an update on change.
 * @param array $field The field configuration array containing settings and metadata for the field.
 * @param string $reset An optional parameter indicating if the form should be reset.
 */
function render_date_range_field($name,$value,$forsearch=true,$autoupdate=false,$field=array(),$reset="")
    {
    $found_year='';$found_month='';$found_day='';$found_start_year='';$found_start_month='';$found_start_day='';$found_end_year='';$found_end_month='';$found_end_day=''; 
    global $daterange_edtf_support,$lang, $minyear,$date_d_m_y, $edit_autosave,$forsearchbar, $maxyear_extends_current;
    if($forsearch)
        {
        // Get the start/end date from the string
        $startpos   = strpos($value,"start");
        $endpos     = strpos($value,"end");
        $startvalue = $startpos !== false ? substr($value,$startpos+5,($endpos ? ($endpos - ($startpos + 5)) : null)) : "";
        $endvalue   = $endpos !== false ? substr($value,strpos($value,"end")+3,10) : "";
        }
    else
        {
        if($value!="" && strpos($value,",")!==false)
            {
            // Extract the start date from the value obtained from get_resource_field_data
            $rangevalues = explode(",",$value);
            $startvalue = $rangevalues[0];
            $endvalue = $rangevalues[1];
            }
        elseif(strlen($value)==10 && strpos($value,"-") !==  false)
            {
            $startvalue = $value;
            $endvalue = "";
            }
        else
            {
            $startvalue = "";
            $endvalue = "";
            }
        }

    $startvalue = trim($startvalue);
    $endvalue = trim($endvalue);

    $ss=explode("-",$startvalue);
    if (count($ss)>=1)
        {
        $found_start_year   = $ss[0] ?? "";
        $found_start_month  = $ss[1] ?? "";
        $found_start_day    = $ss[2] ?? "";
        }
    $se=explode("-",$endvalue);
    if (count($se)>=1)
        {
        $found_end_year     = $se[0] ?? "";
        $found_end_month    = $se[1] ?? "";
        $found_end_day      = $se[2] ?? "";
        }
        
    // If the form has been submitted (but not reset) but data was not saved get the submitted values   
    if($reset == "") 
        {
        foreach(array("start_y", "start-m","start-d","end-y","end-m","end-d") as $subpart)
            {
            if(getval($name . "_" . $subpart,"") != "")
                {
                ${"found_" . $subpart} = escape(getval($name . "_" . $subpart,""));
                }
            }
        }
    
    if($daterange_edtf_support)
        {
        // Use EDTF format for date input
        ?>      
        <input class="<?php echo $forsearch?"SearchWidth":"stdwidth"; ?>"  name="<?php echo escape($name); ?>_edtf" id="<?php echo escape($name); ?>_edtf" type="text" value="<?php echo escape(($startvalue != "" | $endvalue !="") ? $startvalue . "/" . $endvalue : ""); ?>" style="display:none;" disabled <?php if ($forsearch && $autoupdate) { ?>onChange="UpdateResultCount();"<?php } if($forsearch && !$forsearchbar){ ?> onKeyPress="if (!(updating)) {setTimeout('UpdateResultCount()',2000);updating=true;}"<?php } elseif (!$forsearch  && $edit_autosave){?>onChange="AutoSave('<?php echo (int) $field["ref"]; ?>');"<?php } ?>>
<?php
        }?>
    <!--  date range search start -->
    <!--- start date -->
    <div class="stdwidth indent <?php echo escape($name); ?>_range" id="<?php echo escape($name); ?>_start">
    <label class="InnerLabel"><?php echo escape($lang["fromdate"])?></label>

        <?php
        if($date_d_m_y)
            {
            ?>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_start-d"><?php echo escape($lang["day"]); ?></label>
            <select name="<?php echo escape($name); ?>_start-d" id="<?php echo escape($name); ?>_start-d"
             <?php
            if ($forsearch && $autoupdate)
                    { ?>onChange="UpdateResultCount();"<?php }
            elseif (!$forsearch  && $edit_autosave)
            {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_start')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>
              >
              <option value=""><?php echo escape($forsearch?$lang["anyday"]:$lang["day"]); ?></option>
              <?php
              for ($d=1;$d<=31;$d++)
                {
                $m=str_pad($d,2,"0",STR_PAD_LEFT);
                ?><option <?php if ($d==$found_start_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
                }
              ?>
            </select>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_start-m"><?php echo escape($lang["month"]); ?></label>
            <select name="<?php echo escape($name); ?>_start-m" id="<?php echo escape($name); ?>_start-m"
                <?php
                if ($forsearch && $autoupdate)
                    { ?>onChange="UpdateResultCount();"<?php }
                elseif (!$forsearch  && $edit_autosave)
                    {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_start')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>
                    >
                <option value=""><?php echo escape($forsearch?$lang["anymonth"]:$lang["month"]); ?></option>
                <?php
                for ($d=1;$d<=12;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_start_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo escape($lang["months_list"][$d-1]); ?></option><?php
                    }?>
            </select>
            <?php
            }
        else
            {
            ?>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_start-m"><?php echo escape($lang["month"]); ?></label>
            <select name="<?php echo escape($name); ?>_start-m" id="<?php echo escape($name); ?>_start-m"
                <?php 
                if ($forsearch && $autoupdate) 
                    { ?>onChange="UpdateResultCount();"<?php }
                elseif (!$forsearch  && $edit_autosave)
                    {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_start')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>
                    >
                <option value=""><?php echo escape($forsearch?$lang["anymonth"]:$lang["month"]); ?></option>
                <?php
                for ($d=1;$d<=12;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_start_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo escape($lang["months_list"][$d-1]); ?></option><?php
                    }?>
            </select>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_start-d"><?php echo escape($lang["day"]); ?></label>
            <select name="<?php echo escape($name); ?>_start-d"  id="<?php echo escape($name); ?>_start-d"
              <?php
                if ($forsearch && $autoupdate)
                    { ?>onChange="UpdateResultCount();"<?php }
                elseif (!$forsearch  && $edit_autosave)
                    {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_start')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>
                    >
              <option value=""><?php echo escape($forsearch?$lang["anyday"]:$lang["day"]); ?></option>
              <?php
              for ($d=1;$d<=31;$d++)
                {
                $m=str_pad($d,2,"0",STR_PAD_LEFT);
                ?><option <?php if ($d==$found_start_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
                }
              ?>
            </select>
            <?php
            }
        if($forsearch)
            {?>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_start-y"><?php echo escape($lang["year"]); ?></label>
            <select name="<?php echo escape($name) ?>_start-y" id="<?php echo escape($name); ?>_start-y"
                <?php
                if ($forsearch && $autoupdate)
                        { ?>onChange="UpdateResultCount();"<?php }
                elseif (!$forsearch  && $edit_autosave)
                {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_start')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>
                >
                <option value=""><?php echo escape($forsearch?$lang["anyyear"]:$lang["year"]); ?></option>
                <?php
                $y=date("Y");
                $y += $maxyear_extends_current;
                for ($d=$y;$d>=$minyear;$d--)
                    {
                    ?><option <?php if ($d==$found_start_year) { ?>selected<?php } ?>><?php echo $d?></option><?php
                    }?>
            </select>
            <?php
            }
        else
            {?>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_end-y"><?php echo escape($lang["year"]); ?></label>
            <input size="5" name="<?php echo escape($name) ?>_start-y" id="<?php echo escape($name) ?>_start-y" type="text" value="<?php echo escape($found_start_year) ?>"
                <?php 
                if ($forsearch && $autoupdate)
                    { ?>onChange="UpdateResultCount();"<?php }
                if($forsearch && !$forsearchbar)
                    { ?> onKeyPress="if (!(updating)) {setTimeout('UpdateResultCount()',2000);updating=true;}"<?php }
                elseif (!$forsearch  && $edit_autosave)
                    {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_start')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>>
            <?php
            }?>
    </div>

    <div class="clearerleft"> </div>

    <!--- to date -->
    <label  class='daterangelabel'></label>

    <div class="stdwidth indent <?php echo escape($name); ?>_range" id="<?php echo escape($name); ?>_to" >
    <label class="InnerLabel"><?php echo escape($lang["todate"])?></label>
    <?php
        if($date_d_m_y)
            {
            ?>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_end-d"><?php echo escape($lang["day"]); ?></label>
            <select name="<?php echo escape($name); ?>_end-d" id="<?php echo escape($name); ?>_end-d"
              <?php
                if ($forsearch && $autoupdate)
                    { ?>onChange="UpdateResultCount();"<?php }
                elseif (!$forsearch  && $edit_autosave)
                    {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_end')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>
                    >
                <option value=""><?php echo escape($forsearch?$lang["anyday"]:$lang["day"]); ?></option>
                <?php
                for ($d=1;$d<=31;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_end_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
                    }?>
            </select>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_end-m"><?php echo escape($lang["month"]); ?></label>
            <select name="<?php echo escape($name); ?>_end-m"  id="<?php echo escape($name); ?>_end-m"
                <?php
                if ($forsearch && $autoupdate)
                    { ?>onChange="UpdateResultCount();"<?php }
                elseif (!$forsearch  && $edit_autosave)
                    {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_end')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>
                    >
                <option value=""><?php echo escape($forsearch?$lang["anymonth"]:$lang["month"]); ?></option>
                <?php
                for ($d=1;$d<=12;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_end_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo escape($lang["months_list"][$d-1]); ?></option><?php
                    }?>
            </select>
            <?php
            }
        else
            {
            ?>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_end-m"><?php echo escape($lang["month"]); ?></label>
            <select name="<?php echo escape($name); ?>_end-m" id="<?php echo escape($name); ?>_end-m" <?php
                if (!$forsearch  && $edit_autosave)
                    {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_end')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } 
                else
                    {?>onChange="UpdateResultCount();"<?php } ?>
                    >
                <option value=""><?php echo escape($forsearch?$lang["anymonth"]:$lang["month"]); ?></option>
                <?php
                for ($d=1;$d<=12;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_end_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo escape($lang["months_list"][$d-1]); ?></option><?php
                    }?>
            </select>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_end-d"><?php echo escape($lang["day"]); ?></label>
            <select name="<?php echo escape($name); ?>_end-d" id="<?php echo escape($name); ?>_end-d"
              <?php
                if ($forsearch && $autoupdate)
                    { ?>onChange="UpdateResultCount();"<?php }
                elseif (!$forsearch  && $edit_autosave)
                    {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_end')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>
                    >
              <option value=""><?php echo escape($forsearch?$lang["anyday"]:$lang["day"]); ?></option>
              <?php
              for ($d=1;$d<=31;$d++)
                {
                $m=str_pad($d,2,"0",STR_PAD_LEFT);
                ?><option <?php if ($d==$found_end_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
                }
              ?>
            </select>
            <?php
            }
        if($forsearch)
            {?>
            <label class="accessibility-hidden" for="<?php echo escape($name) ?>_end-y"><?php echo escape($lang["year"]); ?></label>
            <select name="<?php echo escape($name); ?>_end-y" id="<?php echo escape($name); ?>_end-y"
            <?php
            if ($forsearch && $autoupdate) { ?>onChange="UpdateResultCount();"<?php }
                elseif (!$forsearch  && $edit_autosave)
                    {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_end')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>
                    >
              <option value=""><?php echo escape($forsearch?$lang["anyyear"]:$lang["year"]); ?></option>
              <?php
              $y=date("Y");
              $y += $maxyear_extends_current;
              for ($d=$y;$d>=$minyear;$d--)
                {
                ?><option <?php if ($d==$found_end_year ) { ?>selected<?php } ?>><?php echo $d?></option><?php
                }
              ?>
            </select>
             <?php
                }
            else
                {?>
                <label class="accessibility-hidden" for="<?php echo escape($name) ?>_end-y"><?php echo escape($lang["year"]); ?></label>
                <input size="5" name="<?php echo escape($name) ?>_end-y" id="<?php echo escape($name) ?>_end-y" type="text" value="<?php echo $found_end_year ?>"
                    <?php

                    if ($forsearch && $autoupdate)
                        { ?>onChange="UpdateResultCount();"<?php }
                    if($forsearch && !$forsearchbar)
                        { ?> onKeyPress="if (!(updating)) {setTimeout('UpdateResultCount()',2000);updating=true;}"<?php }
                    elseif (!$forsearch  && $edit_autosave)
                        {?>onChange="if(sufficientDateParts('<?php echo escape($name); ?>_end')){AutoSave('<?php echo $field["ref"]; ?>');}"<?php } ?>>
                <?php
                }

            if($forsearch !== true)
                {
                ?>
        <script>
            // Get value of the date element before the change
            jQuery('[name^=<?php echo escape($name); ?>]').on('focus', function(){
                jQuery.data(this, 'current', jQuery(this).val());
            });
            //Check the value of the date after the change
            jQuery('[name^=<?php echo escape($name); ?>_start]').on('change', function(){
                let day   = jQuery('[name=<?php echo escape($name); ?>_start-d]').val().trim();
                let month = jQuery('[name=<?php echo escape($name); ?>_start-m]').val().trim();
                let year  = jQuery('[name=<?php echo escape($name); ?>_start-y]').val().trim(); 
                if (year != "" && !jQuery.isNumeric(year))
                    {
                    styledalert(<?php echo "'" . escape($lang["error"]) . "','" . escape($lang["invalid_date_generic"]) . "'" ?>);
                    jQuery(this).val(jQuery.data(this, 'current'));
                    }
                if(jQuery.isNumeric(year) && jQuery.isNumeric(day) && jQuery.isNumeric(month)){
                    //format date string into yyyy-mm-dd
                    let date_string = year + '-' + month + '-' + day;
                    //get a timestamp from the date string and then convert that back to yyyy-mm-dd
                    let date        = new Date(date_string).toISOString().split('T')[0];
                    //check if the before and after are the same, if a date like 2021-02-30 is selected date would be 2021-03-02
                    if(date_string !== date){
                        styledalert(<?php echo "'" . escape($lang["error"]) . "','" . escape($lang["invalid_date_generic"]) . "'" ?>);
                        jQuery(this).val(jQuery.data(this, 'current'))
                    }
                }
            })
            //Same again but for the end of the date range
            jQuery('[name^=<?php echo escape($name); ?>_end]').on('change', function(){
                let day   = jQuery('[name=<?php echo escape($name); ?>_end-d]').val().trim();
                let month = jQuery('[name=<?php echo escape($name); ?>_end-m]').val().trim();
                let year  = jQuery('[name=<?php echo escape($name); ?>_end-y]').val().trim();
                if (year != "" && !jQuery.isNumeric(year))
                    {
                    styledalert(<?php echo "'" . escape($lang["error"]) . "','" . escape($lang["invalid_date_generic"]) . "'" ?>);
                    jQuery(this).val(jQuery.data(this, 'current'));
                    }
                if(jQuery.isNumeric(year) && jQuery.isNumeric(day) && jQuery.isNumeric(month)){
                    //format date string into yyyy-mm-dd
                    let date_string = year + '-' + month + '-' + day;
                    //get a timestamp from the date string and then convert that back to yyyy-mm-dd
                    let date        = new Date(date_string).toISOString().split('T')[0];
                    //check if the before and after are the same, if a date like 2021-02-30 is selected date would be 2021-03-02
                    if(date_string !== date){
                        styledalert(<?php echo "'" . escape($lang["error"]) . "','" . escape($lang["invalid_date_generic"]) . "'" ?>);
                        jQuery(this).val(jQuery.data(this, 'current'))
                    }
                }
            })
        </script>
        <?php } ?>
    <!--  date range search end date-->
    </div>
    <div class="clearerleft"></div>
    <?php if($daterange_edtf_support)
        {?>
        <a href="#" onclick="if(jQuery('#<?php echo escape($name); ?>_edtf').prop('disabled')){jQuery('#<?php echo escape($name); ?>_edtf').prop('disabled',false);jQuery('#<?php echo escape($name); ?>_edtf').show();jQuery('.<?php echo escape($name); ?>_range').hide();}else{jQuery('#<?php echo escape($name); ?>_edtf').prop('disabled',true);jQuery('#<?php echo escape($name); ?>_edtf').hide();jQuery('.<?php echo escape($name); ?>_range').show();}return false;">
            <i aria-hidden="true" class="icon-chevron-right"></i>
            <?php echo "EDTF"; ?>
        </a>
        <?php
        }
    }

/**
* Renders a full breadcrumbs trail.
* 
* @param array  $links     List of link "objects" that create the trail
* @param string $pre_links Pre-rendered links in HTML form
* @param string $class     Extra classes for the main container div
* 
* @return void
*/
function renderBreadcrumbs(array $links, $pre_links = '', $class = '')
    {
    global $lang;
    /*
    NOTE: implemented as seen on themes and search. There is a lot of room for improvement UI wise

    TODO: search_title_processing.php is using it intensively and at the moment there are differences in terms of 
    rendered HTML between themes/ search and search_title_processing.php. We should refactor all places were breadcrumbs
    are being created and make sure they all use this function (or any future related functions - like generateBreadcrumb() ).
    */

    if(0 === count($links))
        {
        return;
        }
    ?>
    <div class="BreadcrumbsBox <?php echo $class; ?>">
        <div class="SearchBreadcrumbs">
        <?php
        if('' !== $pre_links && $pre_links !== strip_tags($pre_links))
            {
            echo $pre_links . '&nbsp;' . LINK_CHEVRON_RIGHT;
            }

        for ($i = 0; $i < count($links); $i++) {
            $anchor = isset($links[$i]['href']);
            $anchor_attrs = (isset($links[$i]["attrs"]) && is_array($links[$i]["attrs"]) && !empty($links[$i]["attrs"]) ? $links[$i]["attrs"] : array());
            $anchor_attrs = join(" ", $anchor_attrs);

            // search_title_processing.php is building spans with different class names. We need to allow HTML in link titles.
            $title = get_inner_html_from_tag(strip_tags_and_attributes($links[$i]['title']), "p");

            if(0 < $i)
                {
                echo LINK_CHEVRON_RIGHT;
                }
                
            if ($anchor) { ?>
                <a href="<?php echo escape($links[$i]['href']); ?>"
                    <?php if (isset($links[$i]["menu"]) && $links[$i]["menu"]) { ?>
                        onclick="ModalClose(); return ModalLoad(this, true, true, 'right');"
                    <?php } else { ?>
                        onclick="return CentralSpaceLoad(this, true);"
                    <?php }
                    echo escape($anchor_attrs); ?>>
            <?php } ?>

            <span><?php echo $title; ?></span>

            <?php if ($anchor) { ?>
                </a>
            <?php }

            if (isset($links[$i]['help']))
                {
                render_help_link($links[$i]['help']);
                }

            if (isset($links[$i]['context_menu'])) {
                ?>
                <div>
                <?php
                render_top_right_menu_btn($links[$i]['context_menu']);
                render_featured_collection_context_menu("BreadCrumb{$i}", $links[$i]['context_menu']);
                ?>
                </div>
                <?php
            }
        }
        ?>
        </div>
    </div>
    <?php
    }


/**
* Render a blank tile used for call to actions (e.g: on featured collections, a tile for creating new collections)
* 
* @param string $url URL
* @param array  $ctx Rendering options determined by the outside context
* 
* @return void
*/
function render_new_featured_collection_cta(string $url, array $ctx)
    {
    if('' === $url)
        {
        return;
        }

    $full_width = (isset($ctx["full_width"]) && $ctx["full_width"]);
    $centralspaceload = (isset($ctx["centralspaceload"]) && $ctx["centralspaceload"]);
    $html_h2_span_class = (isset($ctx["html_h2_span_class"]) && trim($ctx["html_h2_span_class"]) != "" ? trim($ctx["html_h2_span_class"]) : "icon-circle-plus");

    $html_tile_class = array("FeaturedSimplePanel", "HomePanel", "DashTile", "FeaturedSimpleTile", "FeaturedCallToActionTile");
    $html_contents_h2_class = array();

    if($full_width)
        {
        $html_tile_class[] = "FullWidth";
        $html_contents_h2_class[] = "MarginZeroAuto";
        }

    $onclick_fn = ($centralspaceload ? "CentralSpaceLoad(this, true);" : "ModalLoad(this, true, true);");
    ?>
    <div id="FeaturedSimpleTile" class="<?php echo implode(" ", $html_tile_class); ?>">
        <a href="<?php echo $url; ?>" onclick="return <?php echo $onclick_fn; ?>">
            <div class="FeaturedSimpleTileContents">
                <div class="FeaturedSimpleTileText">
                    <h2 class="<?php echo implode(" ", $html_contents_h2_class); ?>"><span class="<?php echo $html_h2_span_class; ?>"></span></h2>
                </div>
            </div>
        </a>
    </div>
    <?php
    }

/**
* Renders social media links in order to share a particular link
* 
* @param string $url The URL to be shared on social media networks
* 
* @return void
*/
function renderSocialMediaShareLinksForUrl($url)
{
    global $social_media_links;

    if (in_array("facebook", $social_media_links)) { ?>
        <!-- Facebook -->
        <a target="_blank" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($url); ?>"><i class="icon-facebook lucide-lg" aria-hidden="true"></i></a>
        <?php
    }

    if (in_array("twitter", $social_media_links)) { ?>
        <!-- Twitter -->
        <a target="_blank" href="https://twitter.com/intent/tweet?url=<?php echo urlencode($url); ?>"><span class="x-twitter-icon">𝕏</span></a>
        <?php
    }

    if (in_array("linkedin", $social_media_links)) { ?>
        <!-- LinkedIn -->
        <a target="_blank" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($url); ?>"><i class="icon-linkedin lucide-lg" aria-hidden="true"></i></a>
        <?php
    }
}
    
/**
* Renders a lock button for a field - used to 'lock' metadata in upload_review_mode
* 
* @param string $name  The field identifier e.g. 'resource_type', '18'
* @param array $locked_fields - Array of locked field identifiers
* 
* @return void
*/
function renderLockButton($name, $locked_fields=array())
    {
    global $lang;
    ?>
    <button type="submit" class="lock_icon" id="lock_icon_<?php echo escape($name) ; ?>" onClick="toggleFieldLock('<?php echo escape($name) ; ?>');return false;" title="<?php echo escape($lang['lock-tooltip']); ?>">
        <i aria-hidden="true" class="icon-<?php if (in_array($name, $locked_fields)) {echo "lock";} else {echo "lock-open";} ?>"></i>
    </button>
    <?php    
    }

/**
* Renders an image, with width and height specified for centering in div
* 
* @param array  $resource  An array of resource data from search results
* @param string $img_url   URL to image file
* @param string $display   size to use - from search results
* 
* @return void
*/
function render_resource_image($resource, $img_url, $display="thumbs")
    {
    global $view_title_field;
    
    list($width, $height, $margin) = calculate_image_display($resource, $img_url, $display);

    $margin = (is_numeric($margin)) ? $margin . "px" : $margin;

    // Produce a 'softer' colour for the loading preview (extracted colours tend to have a very high saturation)
    if (  (isset($resource["image_red"]) && isset($resource["image_green"]) && isset($resource["image_blue"]))
       && (is_int_loose($resource["image_red"]) && is_int_loose($resource["image_green"]) && is_int_loose($resource["image_blue"]))  )
        {
        $preview_red=100+($resource["image_red"]/1000)*156;
        $preview_green=100+($resource["image_green"]/1000)*156;
        $preview_blue=100+($resource["image_blue"]/1000)*156;
        }
    else
        {
        $preview_red=$preview_green=$preview_blue=255;
        }
    ?>
    <div class="ImageColourWrapper" 
    style="background-color: rgb(<?php echo $preview_red ?>,<?php echo $preview_green ?>,<?php echo $preview_blue ?>);
    width:<?php echo $width; ?>px;height:<?php echo $height; ?>px;margin:<?php echo $margin; ?> auto 0 auto; 
    <?php 
    $blurbleedstopper = hook("stopblurbleed"); 
    if ($blurbleedstopper) { echo $blurbleedstopper; }
    ?>">
    <img border="0" width="<?php echo $width ?>" height="<?php echo $height ?>"
    src="<?php echo $img_url ?>" 
    alt="<?php echo str_replace(array("\"","'"),"",escape(i18n_get_translated(strip_tags(strip_tags_and_attributes($resource["field".$view_title_field] ?? ""))))); ?>"
    /></div>
    <?php
    }


/**
 * Calculations width, height and margin-top property for resource image to display in ResourcePanel
 * 
 * @param array{'thumb_width': positive-int, 'thumb_height': positive-int} $imagedata
 * @param string $img_url
 * @param string $display
 * 
 * @return  array Returns a tuple of width, height and margin
 */
function calculate_image_display(array $imagedata, string $img_url, string $display="thumbs"): array
    {
    if(
        '' != $imagedata['thumb_width']
        && '' != $imagedata['thumb_height']
        && $imagedata['thumb_width'] > 0
        && $imagedata['thumb_height'] > 0
    )
        {
        $ratio = $imagedata["thumb_width"] / $imagedata["thumb_height"];   
        }
    else
        {
        $size = $img_url != "" ? getimagesize($img_url) : [];
        $ratio = isset($size[0]) ? $size[0] / $size[1] : 1;
        }
    
    switch($display)
        {
        case "xlthumbs":
            $defaultwidth = 320;
            $defaultheight = 320;
            break;

        case "thumbs":
            $defaultwidth = 200;
            $defaultheight = 200;
            break;

        case "list":
            $defaultwidth = 40;
            $defaultheight = 40;
            break;

        case "col":
            $defaultwidth = $imagedata['thumb_width'];
            $defaultheight = $imagedata['thumb_height'];
            break;

        default:
            $defaultwidth = 75;
            $defaultheight = 75;
            break;
        }

    if ($ratio > 1)
        {
        $width = $defaultwidth;
        $height = round($defaultwidth / $ratio);
        $margin = floor(($defaultheight - $height ) / 2);
        }
    elseif ($ratio < 1)
        {
        # portrait image dimensions
        $height = $defaultheight;
        $width = round($defaultheight * $ratio);
        $margin = floor(($defaultheight - $height ) / 2);
        }
    else
        {
        # square image or no image dimensions
        $height = $defaultheight;
        $width = $defaultwidth;
        $margin = "auto";
        }

    return array($width, $height, $margin);
    }

/**
 * Render the share options (used on collection_share.php and resource_share.php)
 *
 * @param  array $shareopts     Array of share options. If not set will use the old getval() methods
 *                  "password" bool             Has a password been set for this share? (password will not actually be displayed)
 *                  "editaccesslevel" int       Current access level of share
 *                  "editexpiration" string     Current expiration date
 *                  "editgroup" int             ID of existing share group
 * @return void
 */
function render_share_options($shareopts=array())
    {
    global $lang, $usergroup,$minaccess,$allowed_external_share_groups,$resource_share_expire_never;
    global $resource_share_expire_days,$resource_share_expire_days_default;

    $password = $shareopts['password'] ?? getval('password', '');
    $editaccesslevel = $shareopts['editaccesslevel'] ?? getval('editaccesslevel', '');
    $editexpiration = $shareopts['editexpiration'] ?? getval('editexpiration', null);
    $editgroup = $shareopts['editgroup'] ?? getval('editgroup', '');

    if (
        $editexpiration === "" && $resource_share_expire_days_default > 0
        || $editexpiration != "" && date('Y-m-d', strtotime($editexpiration)) < date('Y-m-d',time())
        ) {
        $editexpiration = date('Y-m-d',time() + (60*60*24*$resource_share_expire_days_default));
    }

    if(!hook('replaceemailaccessselector'))
        {?>
        <div class="Question" id="question_access">
            <label for="archive"><?php echo escape($lang["access"]) ?></label>
            <select class="stdwidth" name="access" id="access">
            <?php
            # List available access levels. The highest level must be the minimum user access level.
            for ($n=$minaccess;$n<=1;$n++) 
                { 
                $selected = $editaccesslevel == $n;
                ?>
                <option value="<?php echo $n?>" <?php if ($selected) {echo "selected";} ?>><?php echo escape($lang["access" . $n])?></option>
                <?php 
                } 
                ?>
            </select>
            <div class="clearerleft"> </div>
        </div><?php
        } #hook replaceemailaccessselector
    
    if(!hook('replaceemailexpiryselector'))
        {
        ?>
        <div class="Question">
            <label><?php echo escape($lang["expires"]) ?></label>
            <select name="expires" class="stdwidth">
            <?php
            if($resource_share_expire_never) 
                { ?>
                <option value=""><?php echo escape($lang["never"])?></option><?php 
                } 
            for ($n=1;$n<=$resource_share_expire_days;$n++)
                {
                $date       = time() + (60*60*24*$n);
                $ymd_date   = date('Y-m-d', $date);
                $selected   = (substr($editexpiration  ?? "",0,10) == $ymd_date);
                $date_text  = nicedate($ymd_date,false,true);
                $option_class = '';
                $day_date = date('D', $date);
                if (($day_date == "Sun") || ($day_date == "Sat"))
                    {
                    $option_class = 'optionWeekend';
                    }
                ?>
                <option class="<?php echo $option_class ?>" value="<?php echo $ymd_date ?>" <?php if ($selected) {echo "selected";} ?>><?php echo $date_text ?></option>
                <?php
                } ?>
            </select>
            <div class="clearerleft"> </div>
        </div>
        <?php 
        }
    if (checkperm("x")) 
        {
        # Allow the selection of a user group to inherit permissions from 
        # for this share (the default is to use the current user's user group).
        ?>
        <div class="Question">
            <label for="groupselect"><?php echo escape($lang["share_using_permissions_from_user_group"]); ?></label>
            <select id="groupselect" name="usergroup" class="stdwidth">
            <?php $grouplist = get_usergroups(true);
            foreach ($grouplist as $group)
                {
                if(!empty($allowed_external_share_groups) && !in_array($group['ref'], $allowed_external_share_groups))
                    {
                    continue;
                    }

                $selected = $editgroup == $group["ref"] || ($editgroup == "" && $usergroup == $group["ref"]);
                ?>
                <option value="<?php echo $group["ref"]; ?>" <?php if ($selected) {echo "selected";} ?>><?php echo $group["name"]; ?></option>
                <?php
                }
                ?>
            </select>
            <div class="clearerleft"> </div>
        </div>
<?php 
        }
    elseif(!checkperm("x") && !empty($allowed_external_share_groups) && in_array($usergroup, $allowed_external_share_groups))
        {
        ?>
        <input type="hidden" name="usergroup" value="<?php echo $usergroup; ?>">
        <?php
        }
        render_share_password_question(!$password);
        hook("additionalresourceshare");
        ?>
    <?php        
    }
    
/**
* Renders a metadata field selector
* 
* @param string     $label      label for the field
* @param string     $name       name of form select
* @param array      $ftypes     Array of integer field type ids to include. See definitions.php. Will return all types if incorrect value or empty array supplied.
* @param string     $class      array CSS class to apply
* @param boolean    $hidden     optionally hide the question usng CSS display:none
* @param array      $current    Current selected value
* 
* @return void
*/
function render_field_selector_question($label, $name, $ftypes, $class = "stdwidth", $hidden = false, $current = 0)
    {
    global $lang;
    $fieldtypefilter = "";
    $parameters = array();

    // Check only valid field types supplied. An empty array is valid i.e. return all types.
    $ftypes = array_filter($ftypes, function($t) {global $field_types; return is_int($t) && in_array($t, array_keys($field_types));});
        
    if(count($ftypes)>0)
        {
        $fieldtypefilter = " WHERE type IN (" . ps_param_insert(count($ftypes)) . ")";
        $parameters = ps_param_fill($ftypes, "i");
        }

    $fields = ps_query("SELECT " . columns_in("resource_type_field") . " from resource_type_field " .  (($fieldtypefilter=="")?"":$fieldtypefilter) . " ORDER BY title, name", $parameters, "schema");

    echo "<div class='Question' id='" . escape($name) . "'" . ($hidden ? " style='display:none;border-top:none;'" : "") . ">";
    echo "<label for='" . escape($name) . "' >" . escape($label) . "</label>";
    echo "<select name='" . escape($name) . "' id='" . escape($name) . "' class='" . $class . "'>";
    echo "<option value='' selected >" . escape($lang["select"]) . "</option>";
    foreach($fields as $field)
        {
        $selected = ($field["ref"] == $current ? "selected" : "");
        echo "<option value='{$field['ref']}' {$selected}>" . lang_or_i18n_get_translated($field['title'],'fieldtitle-') . "</option>";
        }
    echo "</select>";
    echo "<div class='clearerleft'></div>";
    echo "</div>";
    }


/**
* Render a filter bar button
* 
* @param string $text Button text
* @param string $attr Button attributes
* @param string $icon HTML for icon element (e.g "<i aria-hidden="true" class="icon-upload"></i>")
* 
* @return void
*/
function render_filter_bar_button($text, $attr, $icon)
    {
    ?>
    <div class="InpageNavLeftBlock">
        <button type="button" <?php echo $attr; ?>><?php echo $icon . escape($text); ?></button>
    </div>
    <?php
    }


/**
* Render "Upload here" button.
*
* This applies to search results that are either a special search "!collection" and/or consist of purely the following:
* - Nodes
* - Resource type
* - Workflow (archive) state
* 
* For free text searches this SHOULD NOT work!
* 
* @param array{search: string, restypes: string, archive: string, advsearch?: 'true'|''} $search_params
* @param boolean $return_params_only Exception to the rule! Rather than render, return the upload here params
* 
* @return void|array
*/
function render_upload_here_button(array $search_params, $return_params_only = false)
    {
    if(!(checkperm('c') || checkperm('d')))
        {
        return;
        }

    if(!isset($search_params['search']) || !isset($search_params['restypes']) || !isset($search_params['archive']))
        {
        return;
        }

    if(
        isset($search_params['search'])
        && (
            mb_substr($search_params['search'], 0, 11) != '!collection'
            && empty(resolve_nodes_from_string($search_params['search']))
        )
    )
        {
        return;
        }

    $upload_here_params = array();

    $upload_endpoint = 'pages/upload_batch.php';
    if(!$GLOBALS['upload_then_edit'])
        {
        $upload_endpoint = 'pages/edit.php';
        $upload_here_params['ref'] = 0 - $GLOBALS['userref'];
        $upload_here_params['uploader'] = $GLOBALS['top_nav_upload_type'];
        }

    $upload_here_params['upload_here'] = true;
    $upload_here_params['search'] = $search_params['search'];

    // Special search !collection
    if (mb_substr($search_params['search'], 0, 11) == '!collection') {
        $collection = explode(' ', $search_params['search']);
        $collection = str_replace('!collection', '', $collection[0]);
        $collection = explode(',', $collection);
        $collection = (int) $collection[0];

        //Check the user is able to upload to this collection before continuing
        if(!collection_writeable($collection)) {return;}

        $smart_collection = get_saved_searches($collection)[0] ?? [];
        if ($smart_collection !== []) {
            $search_params = array_merge(
                $search_params,
                array_intersect_key($smart_collection, array_flip(['search', 'restypes', 'archive']))
            );
            $upload_here_params['search'] = $search_params['search'];
        } else {
            $upload_here_params['collection_add'] = $collection;
            $col_data = get_collection($collection);
            $upload_here_params['entercolname'] = $col_data['name'];
        }
    }

    if (isset($search_params['advsearch']) && $search_params['advsearch'] == 'true')
        {
        $upload_here_params['advsearch'] = 'true';
        }

    // If resource types is a list then always select the first resource type the user has access to
    $resource_types = explode(',', $search_params['restypes']);
    foreach($resource_types as $resource_type)
        {
        if(!checkperm("XU{$resource_type}"))
            {
            $upload_here_params['resource_type'] = $resource_type;
            break;
            }
        }

    // Archive can be a list (e.g from advanced search) so always select the first archive state user access to, 
    // favouring the Active one
    $search_archive = explode(',', $search_params['archive']);
    $use_default = false;

    if (count($search_archive) == 1 && $search_archive[0] == "")
        {
        $search_archive = array();
        $use_default = true;
        }

    // Check access to Active state
    if(in_array(0, $search_archive) && checkperm("e0"))
        {
        $upload_here_params['status'] = 0;
        $search_archive = array();
        }
    // Check remaining states
    foreach($search_archive as $archive)
        {
        if($archive == '' || !is_numeric($archive))
            {
            continue;
            }

        if(checkperm("e{$archive}"))
            {
            $upload_here_params['status'] = $archive;
            $search_archive = array();
            break;
            }
        }

    if (count($search_archive) != 0 || $use_default)
        {
        // None of the supplied archive states were accessible with e permission so use default.
        $upload_here_params['status'] = get_default_archive_state();
        }

    // Option to return out just the upload params
    if ($return_params_only)
        {
        return $upload_here_params;
        }
        
    $upload_here_url = generateURL("{$GLOBALS['baseurl']}/{$upload_endpoint}", $upload_here_params);
    $attributes = "onclick=\"CentralSpaceLoad('{$upload_here_url}');\"";

    render_filter_bar_button($GLOBALS['lang']['upload_here'], $attributes, UPLOAD_ICON);
    }

/**
* Renders the trash bin. This is used to delete dash tiles and remove resources from collections
* 
* @param string $type   type of trash_bin
* 
* @return string|void
*/ 

function render_trash($type, $deletetext,$forjs=false)
    {
    $trash_html = '<div id="' . $type . '_bin" class="trash_bin ui-droppable ui-droppable-active ui-state-hover"><span class="trash_bin_text"><i class="icon-trash-2" aria-hidden="true"></i></span></div>
    <div id="trash_bin_delete_dialog" style="display:none;"></div>
    <div id="delete_permanent_dialog" style="display:none;text-align:left;">'  . $deletetext . '</div>
';
    if($forjs)
        {
        return str_replace(array("\r","\n"),"",$trash_html);
        }
    else
        {
        echo $trash_html;
        }
    }

/**
* Renders the browse bar
*  
* @return void
*/ 

function render_browse_bar()
    {
    global $lang, $browse_bar_workflow, $enable_themes;
    $bb_html = '<div id="BrowseBarContainer" style="display:none;">';
    $bb_html .= '<div id="BrowseBar" class="BrowseBar">';
    $bb_html .= '<div id="BrowseBarContent" >'; 
    
    //Browse row template
    // script will replace %BROWSE_TYPE%, %BROWSE_EXPAND_CLASS%, %BROWSE_CLASS% %BROWSE_LEVEL%, %BROWSE_EXPAND%, %BROWSE_NAME%, %BROWSE_TEXT%, %BROWSE_ID%
    $bb_html .= "
            <div id='BrowseBarTemplate' style='display: none;'>
            <div class='BrowseBarItem BrowseRowOuter %BROWSE_DROP%' data-browse-id='%BROWSE_ID%' data-browse-parent='%BROWSE_PARENT%'  data-browse-loaded='0' data-browse-status='closed' data-browse-level='%BROWSE_LEVEL%' style='display: none;'>
                <div class='BrowseRowInner' >
                    %BROWSE_INDENT%
                    %BROWSE_EXPAND%
                    %BROWSE_TEXT%
                    %BROWSE_REFRESH%
                </div><!-- End of BrowseRowInner -->
            </div><!-- End of BrowseRowOuter -->
            </div><!-- End of BrowseBarTemplate -->
            ";

    // Add root elements
    $bb_html .= generate_browse_bar_item("R", $lang['browse_by_tag']);
    if($enable_themes)
        {
        $bb_html .= generate_browse_bar_item("FC", $lang["themes"]);
        }
    if(!checkperm('b'))
        {
        $bb_html .= generate_browse_bar_item("C", $lang["mycollections"]);
        }
        
    if($browse_bar_workflow)
        {
        $bb_html .= generate_browse_bar_item("WF", $lang['browse_by_workflow_state']);
        }

    $bb_html .= '</div><!-- End of BrowseBarContent -->
                </div><!-- End of BrowseBar -->
                </div><!-- End of BrowseBarContainer -->';
    echo $bb_html;
    
    echo '<script>
        b_loading = new Array();
        // Expand tree to previous state based on stored cookie
        jQuery(document).ready(function()
            {
            ReloadBrowseBar();
            });
        </script>';
    }


/**
* Generates a root row item for the browse bar
*  
* @return string  $html
*/    
function generate_browse_bar_item($id, $text)
    {
    global $lang;
    $html = '<div class="BrowseBarItem BrowseRowOuter BrowseBarRoot" data-browse-id="' . $id . '" data-browse-parent="root" data-browse-loaded="0" data-browse-status="closed" data-browse-level="0" >';
    $html .= '<div class="BrowseRowInner" >';
    
    $html .= '<div class="BrowseBarStructure">
            <a href="#" class="browse_expand browse_closed" onclick="toggleBrowseElements(\'' . $id . '\',false,true);" alt="' . escape($lang["expand"]) . '"></a>
            </div><!-- End of BrowseBarStructure -->';  
    $html .= '<div onclick="toggleBrowseElements(\'' . $id . '\',false,true);" class="BrowseBarLink" >' . $text . '</div>';
    
    $html .= '<a href="#" class="BrowseRefresh " onclick="toggleBrowseElements(\'' . $id . '\',true, true);" ><i class="icon-refresh-cw"></i></a>';  
    
    $html .= "</div><!-- End of BrowseRowInner -->
            </div><!-- End of BrowseRowOuter -->";
    return $html;
    }
    
/**
* Generates a help icon that opens the relevant Knowledge Base article in a modal
* 
* @param string  $page              Knowledge Base article to display, leave blank to show the Knowledge Base homepage
* @param boolean $return_string     Set to true to return the html as a single line string, False will cause the function to echo the html
* 
* @return mixed  if $return_string=true return is string, else void
*/
function render_help_link($page='',$return_string=false)
    {
    global $lang,$help_modal,$baseurl;

    // Build html for link into a string
    $help_link_html  =      '<a ';
    $help_link_html .=          'href="' . $baseurl . '/pages/help.php?page=' . escape($page) . '" ';
    $help_link_html .=          'title="' . escape($lang["help-tooltip"]) . '" ';
    $help_link_html .=          'class="HelpLink" ';
    if ($help_modal) 
        { $help_link_html .=    'onClick="return ModalLoad(this, true);" ';}
    else
        { $help_link_html .=    'target="_blank" ';}
    $help_link_html .=      '>';
    $help_link_html .=      '<i aria-hidden="true" class="icon-circle-question-mark"></i>';
    $help_link_html .=      '</a>';

    if ($return_string===false) {echo $help_link_html;}
    else {return $help_link_html;}
    }


/**
* Render generic Question div (including clearleft)
* 
* @var  string    $id              Div ID if required. Set to empty string if not needed.
* @var  callable  $render_content  Content renderer
* 
* @return void
*/
function render_question_div($id, callable $render_content)
    {
    $id = (trim($id) !== "" ? 'id="' . escape(trim($id)) . '"' : "");
    ?>
    <div <?php echo $id; ?> class="Question">
        <?php $render_content(); ?>
        <div class="clearerleft"></div>
    </div>
    <?php
    }


/**
* Render custom fields (NOT metadata fields)
* 
* @param  array  $cfs  Custom fields information (as returned by process_custom_fields_submission function)
* 
* @return true
*/
function render_custom_fields(array $cfs)
    {
    return array_walk($cfs, function($field, $i)
        {
        render_question_div("Question_{$field["html_properties"]["id"]}", function() use ($field)
            {
            $field_id    = $field["html_properties"]["id"];
            $field_name  = $field["html_properties"]["name"];
            $field_value = $field["value"];

            $show_help_text = isset($field['help_text']) && trim($field['help_text'] !== '');

            global $FIXED_LIST_FIELD_TYPES;
            $selected_options_hashes = array_map(function($opt) use ($field_id)
                {
                return md5("{$field_id}_{$opt}");
                }, (in_array($field["type"], $FIXED_LIST_FIELD_TYPES) ? $field["selected_options"] : array()));

            $required_html = ($field["required"] ? "<sup>*</sup>" : "");
            ?>
            <label for="<?php echo escape($field_id); ?>"><?php echo escape(i18n_get_translated($field["title"])) . $required_html; ?></label>
            <?php
            switch($field["type"])
                {
                case FIELD_TYPE_TEXT_BOX_MULTI_LINE:
                    ?>
                    <textarea id="<?php echo escape($field_id); ?>"
                              class="stdwidth MultiLine"
                              name="<?php echo escape($field_name); ?>"
                              rows=6
                              cols=50><?php echo escape($field_value); ?></textarea>
                    <?php
                    break;

                case FIELD_TYPE_DROP_DOWN_LIST:
                    ?>
                    <select id="<?php echo escape($field_id); ?>" class="stdwidth" name="<?php echo escape($field_name); ?>">
                    <?php
                    foreach($field["options"] as $f_option)
                        {
                        $computed_value = md5("{$field_id}_{$f_option}");
                        $label = escape(i18n_get_translated($f_option));
                        $extra_attributes = (in_array($computed_value, $selected_options_hashes) ? " selected" : "");

                        echo render_dropdown_option($computed_value, $label, array(), $extra_attributes);
                        }
                    ?>
                    </select>
                    <?php
                    break;

                case FIELD_TYPE_CHECK_BOX_LIST:
                    ?>
                    <div>
                    <?php
                    foreach($field["options"] as $f_option)
                        {
                        $computed_value = md5("{$field_id}_{$f_option}");
                        $label = escape(i18n_get_translated($f_option));
                        $checked = (in_array($computed_value, $selected_options_hashes) ? " checked" : "");
                        ?>
                        <div class="Inline">
                            <input type="checkbox" name="<?php echo escape($field_name); ?>" value="<?php echo $computed_value; ?>"<?php echo $checked; ?>>&nbsp;<?php echo $label; ?>
                        </div>
                        <?php
                        }
                        ?>
                        <div class="clearerleft"></div>
                    </div>
                    <?php
                    break;

                case FIELD_TYPE_TEXT_BOX_SINGLE_LINE:
                default:
                    // Allow plugins to render (their own) custom field types
                    if (hook('render_custom_fields_default_case_override', '', [$field]) !== false) {
                        break;
                    }
                    ?>
                    <input type=text
                           id="<?php echo escape($field_id); ?>"
                           class="stdwidth"
                           name="<?php echo escape($field_name); ?>"
                           value="<?php echo escape($field_value); ?>"
                    >
                    <?php
                    break;
                }

            if ($show_help_text) {
                ?>
                <div id="help_<?php echo escape($field_id); ?>" class="FormHelp" style="clear: left;">
                    <div class="FormHelpInner"><?php echo escape($field['help_text']); ?></div>
                </div>
                <?php
            }

            if(isset($field["error"]) && trim($field["error"]) != "")
                {
                ?>
                <div class="FormError"><?php echo escape($field["error"]); ?></div>
                <?php
                }
            });
        });
    }


/**
* Generates HTML for the "X Selected" in the search results found part pointing to the special collection COLLECTION_TYPE_SELECTION
* 
* @param integer $i Counter to display
* 
* @return string  Returns HTML
*/
function render_selected_resources_counter($i)
    {
    global $baseurl, $lang, $USER_SELECTION_COLLECTION;

    $url = generateURL("{$baseurl}", array("c" => $USER_SELECTION_COLLECTION));

    $x_selected = '<span class="Selected">' . number_format($i) . "</span> {$lang["selected"]}";
    return "<a href=\"{$url}\" class=\"SelectionCollectionLink\" onclick=\"return CentralSpaceLoad(this, true);\">{$x_selected}</a>";
    }


/**
* Renders the "Edit selected" button. This is using the special 'COLLECTION_TYPE_SELECTION' collection
* 
* @return void
*/
function render_edit_selected_btn()
    {
    global $baseurl_short, $lang, $USER_SELECTION_COLLECTION, $restypes, $archive;

    $search = "!collection{$USER_SELECTION_COLLECTION}";
    # Editable_only=true (so returns editable resources only)
    $editable_resources = do_search($search, $restypes, "resourceid", $archive, -1, "desc", false, 0, false, false, "", false, false, true, true);
    # Editable_only=false (so returns resources whether editable or not)
    $all_resources = do_search($search, $restypes, "resourceid", $archive, -1, "desc", false, 0, false, false, "", false, false, true, false);

    # If there are no editable resources then don't render the edit selected button

    # Setup count of editable resources
    $editable_resources_count = 0;
    $editable_resource_refs=array();
    if(is_array($editable_resources))
        {
        $editable_resources_count = count($editable_resources);
        $editable_resource_refs=array_column($editable_resources,"ref");
        }

    # Setup count of editable and non-editable resources
    $all_resources_count = 0;
    $all_resource_refs=array();
    if(is_array($all_resources))
        {
        $all_resources_count = count($all_resources);
        $all_resource_refs=array_column($all_resources,"ref");
        }

    # If both counts are zero then there cannot be any editable resources, so no edit selected button
    if($editable_resources_count == 0 && $all_resources_count == 0)
        {
        return;
        }

    # If not all selected resources are editable then the edit selected button may be inappropriate
    if($editable_resources_count != $all_resources_count)
        {
        # Counts differ meaning there are non-editable resources
        $non_editable_resource_refs=array_diff($all_resource_refs,$editable_resource_refs);

        # Is grant edit present for all non-editables?
        foreach($non_editable_resource_refs as $non_editable_ref) 
            {
            if ( !hook('customediteaccess','',array($non_editable_ref)) ) { return; }
            }

        # All non_editables have grant edit
        # Don't return as edit button can be rendered
        }

    $batch_edit_url = generateURL(
        "{$baseurl_short}pages/edit.php",
        array(
            "search"            =>  $search,
            "collection"        =>  $USER_SELECTION_COLLECTION,
            "restypes"          =>  $restypes,
            "order_by"          =>  "resourceid",
            "archive"           =>  $archive,
            "sort"              =>  "desc",
            "daylimit"          =>  "",
            "editsearchresults" => "true",
            "modal"             => "true",
        ));

    $attributes  = " id=\"EditSelectedResourcesBtn\"";
    $attributes .= " onclick=\"ModalLoad('{$batch_edit_url}', true);\"";

    render_filter_bar_button($lang["edit_selected"], $attributes, ICON_EDIT);
    }


/**
* Renders the "Clear selected" button. This is using the special 'COLLECTION_TYPE_SELECTION' collection
* 
* @return void
*/
function render_clear_selected_btn()
    {
    global $lang, $USER_SELECTION_COLLECTION, $CSRF_token_identifier, $usersession;

    $attributes  = " id=\"ClearSelectedResourcesBtn\" class=\"ClearSelectedButton\"";
    $attributes .= " onclick=\"ClearSelectionCollection(this);\"";
    $attributes .= " data-csrf-token-identifier=\"{$CSRF_token_identifier}\"";
    $attributes .= " data-csrf-token=\"" . generateCSRFToken($usersession, "clear_selected_btn_{$USER_SELECTION_COLLECTION}") . "\"";

    render_filter_bar_button($lang["clear_selected"], $attributes, ICON_REMOVE);
    }


/**
* Render the actions specific to when a user selected resources (using the special "COLLECTION_TYPE_SELECTION" collection)
* 
* @return void
*/
function render_selected_collection_actions()
    {
    global $USER_SELECTION_COLLECTION, $usercollection, $usersession, $lang, $CSRF_token_identifier, $search,
           $render_actions_extra_options, $render_actions_filter, $resources_count, $result;

    $orig_resources_count = $resources_count;
    $orig_search = $search;
    $search = "!collection{$USER_SELECTION_COLLECTION}";

    $orig_result = $result;
    $result = get_collection_resources_with_data($USER_SELECTION_COLLECTION);

    $selected_resources = array_column($result, "ref");
    $resources_count = count($selected_resources);
    $usercollection_resources = get_collection_resources($usercollection);
    $refs_to_remove = count(array_intersect($selected_resources, $usercollection_resources));
    $collection_data = get_collection($USER_SELECTION_COLLECTION);

    $valid_selection_collection_actions = array(
        "relate_all",
        "save_search_items_to_collection",
        "remove_selected_from_collection",
        "search_items_disk_usage",
        "csv_export_results_metadata",
        "share_collection",
        "download_collection",
        "license_batch",
        'delete_all_in_collection',
        'link_consent_batch',
        'unlink_consent_batch'
    );

    if($refs_to_remove > 0)
        {
        $callback_csrf_token = generateCSRFToken($usersession, "collection_remove_resources");
        $render_actions_extra_options = array(
            array(
                "value" => "remove_selected_from_collection",
                "label" => $lang["remove_selected_from_collection"],
                "data_attr" => array(
                    "callback" => "RemoveSelectedFromCollection('{$CSRF_token_identifier}', '{$callback_csrf_token}');",
                ),
                "category" => ACTIONGROUP_COLLECTION,
            ),
        );
        }
    $render_actions_filter = function($action) use ($valid_selection_collection_actions)
        {
        return in_array($action["value"], $valid_selection_collection_actions);
        };

    // override the language for actions as it's now specific to a selection of resources
    $lang["relateallresources"] = $lang["relate_selected_resources"];
    $lang["savesearchitemstocollection"] = $lang["add_selected_to_collection"];
    $lang["searchitemsdiskusage"] = $lang["selected_items_disk_usage"];
    $lang["share"] = $lang["share_selected"];
    $lang['deleteallresourcesfromcollection'] = $lang['deleteselectedfromcollection'];
    $lang["deleteallsure"] = $lang['deleteallselectedsure'];

    render_actions($collection_data, true, false);

    $search = $orig_search;
    $result = $orig_result;
    $resources_count = $orig_resources_count;
    }


// Render a select input for a user's collections
function render_user_collection_select($name = "collection", $collections=array(), $selected=0, $classes = "", $onchangejs = "")
    {
    global $userref,$hidden_collections,$active_collections,$lang;
    if(count($collections) == 0)
        {
        $collections = get_user_collections($userref);   
        }
    
    echo "<select name=\"" . $name . "\" id=\"" . $name . "\" " . ($onchangejs != "" ? (" onchange=\"" . escape($onchangejs) . "\"") : "") . ($classes != "" ? (" class=\"" . escape($classes) . "\"") : "")  . ">";
    echo "<option value=\"0\">" . escape($lang["select"]) . "</option>";
    for ($n=0;$n<count($collections);$n++)
        {
        if(in_array($collections[$n]['ref'],$hidden_collections))
            {
            continue;
            }
        
        #show only active collections if a start date is set for $active_collections 
        if (strtotime($collections[$n]['created']) > ((isset($active_collections))?strtotime($active_collections):1))
            {
            echo "<option value=\"" . $collections[$n]["ref"] . "\" " . ($selected==$collections[$n]["ref"] ? "selected" : "") . ">" . i18n_get_collection_name($collections[$n]) . "</option>";
            }
        }
           
    echo "</select>";
    }


/**
* Render CSRF information as data attributes. Useful to allow JS to run state changing operations
*/
function render_csrf_data_attributes($ident)
    {
    global $CSRF_token_identifier, $usersession;

    $token = generateCSRFToken($usersession, $ident);
    return "data-csrf-token-identifier=\"{$CSRF_token_identifier}\" data-csrf-token=\"{$token}\"";
    }


/**
* Check display condition for a field. 
* 
* @uses get_nodes()
* @uses extract_node_options()
* @uses get_resource_nodes()
* @uses get_node_by_name()
* 
* @param  integer   $n              Question sequence number on the rendered form
* @param  array     $field          Field on which we check display conditions
* @param  array     $fields         Resource field data and properties as returned by get_resource_field_data()
* @param  boolean   $render_js      Set to TRUE to render the client side code for checking display conditions or FALSE otherwise
* @param  integer   $resource_ref   Resource reference for which the display condition applies
* 
* @return boolean Returns TRUE if no display condition or if field should be displayed or FALSE if field should not be displayed.
*/
function check_display_condition($n, array $field, array $fields, $render_js, int $resource_ref)
    {
    debug_function_call(__FUNCTION__, [$n, $field['ref'], ['ignored on purpose - too verbose'], $render_js]);
    global $required_fields_exempt, $blank_edit_template, $ref, $use, $FIXED_LIST_FIELD_TYPES;

    if(trim((string) $field['display_condition']) == "")
        {
        return true;  # This field does not have a display condition, so it should be displayed
        }

    debug(sprintf('$use = %s', json_encode($use)));

    // Assume the candidate field is to be displayed    
    $displaycondition = true;
    // Break down into array of conditions
    $conditions       = explode(';', $field['display_condition']);
    $condref          = 0;
    $scriptconditions = array();
    
    // Need all field data to check display conditions
    global $display_check_data;
    if(!is_array($display_check_data))
        {
        $display_check_data = get_resource_field_data($use,false,false);
        debug('Loaded $display_check_data');
        }

    // On upload, check against the posted nodes as save_resource_data() saves nodes after going through all the fields
    $user_set_values = getval('nodes', [], false, 'is_array');
    debug(sprintf('$user_set_values = %s', json_encode($user_set_values)));

    foreach ($conditions as $condition) # Check each condition
        {
        debug(sprintf('field #%s - checking condition "%s"', $field['ref'], $condition));
        $displayconditioncheck = false;

        // Break this condition down into fieldname $s[0] and value(s) $s[1]
        $s = explode('=', $condition);

        // Process all fields which are referenced by display condition(s) on the candidate field
        // For each referenced field, render javascript to trigger when the referenced field changes
        for ($cf=0;$cf<count($display_check_data);$cf++) # Check each field to see if needs to be checked
            {
            // Work out nodes submitted by user, if any
            $ui_selected_node_values = array();
            if(
                isset($user_set_values[$display_check_data[$cf]['ref']])
                && !is_array($user_set_values[$display_check_data[$cf]['ref']])
                && $user_set_values[$display_check_data[$cf]['ref']] != ''
                && is_numeric($user_set_values[$display_check_data[$cf]['ref']])
            )
                {
                $ui_selected_node_values[] = $user_set_values[$display_check_data[$cf]['ref']];
                debug(sprintf('$ui_selected_node_values = %s', json_encode($ui_selected_node_values)));
                }
            elseif(isset($user_set_values[$display_check_data[$cf]['ref']]) && is_array($user_set_values[$display_check_data[$cf]['ref']]))
                {
                $ui_selected_node_values = $user_set_values[$display_check_data[$cf]['ref']];
                debug(sprintf('$ui_selected_node_values = %s', json_encode($ui_selected_node_values)));
                }

            // Does the fieldname on this condition match the field being processed
            if($s[0] == $display_check_data[$cf]['name']) # this field needs to be checked
                {
                $display_check_data[$cf]['nodes'] = get_nodes($display_check_data[$cf]['ref'], null, (FIELD_TYPE_CATEGORY_TREE == $display_check_data[$cf]['type'] ? true : false));

                $scriptconditions[$condref]['field'] = $display_check_data[$cf]['ref'];
                $scriptconditions[$condref]['type']  = $display_check_data[$cf]['type'];

                $checkvalues=$s[1];
                debug("\$checkvalues = {$checkvalues}");
                // Break down values delimited with pipe characters
                $validvalues = explode("|",$checkvalues);
                $validvalues = array_map("i18n_get_translated",$validvalues);
                debug(sprintf('$validvalues = %s', json_encode($validvalues)));
                $scriptconditions[$condref]['valid'] = array();

                // Use submitted values if field was shown and user has edit access to it
                if(getval("field_" . $fields[$n]['ref'] . "_displayed","") == "block" && metadata_field_edit_access($fields[$n]['ref']))
                    {
                    $v = $ui_selected_node_values;
                    }
                else
                    {
                    $v = trim_array(get_resource_nodes($ref, $display_check_data[$cf]['ref']));
                    }

                // If blank edit template is used, on upload form the dependent fields should be hidden
                if($blank_edit_template && $ref < 0 && $use == $ref)
                    {
                    $v = array();
                    }

                foreach($validvalues as $validvalue)
                    {
                    $found_validvalue = get_node_by_name($display_check_data[$cf]['nodes'], $validvalue);

                    if(0 != count($found_validvalue))
                        {
                        $scriptconditions[$condref]['valid'][] = (string)$found_validvalue['ref'];

                        if(in_array($found_validvalue['ref'], $v))
                            {
                            $displayconditioncheck = true;
                            }
                        }
                    }

                 if(!$displayconditioncheck)
                    {
                    $displaycondition = false;
                    $required_fields_exempt[]=$field["ref"];
                    }

                // Skip rendering the JS calls to checkDisplayCondition functions
                // Skip if user does not have access to the master (parent) field 
                if(!$render_js || !in_array($display_check_data[$cf]['ref'], array_column($fields,"ref")))
                    {
                    continue;
                    }

                // Check display conditions
                // Certain fixed list types allow for multiple nodes to be passed at the same time

                // Generate a javascript function specific to the field with the display condition
                // This function will be invoked whenever a field referenced by the display condition changes
                if(in_array($display_check_data[$cf]['type'], $FIXED_LIST_FIELD_TYPES))
                    {
                    if(FIELD_TYPE_CATEGORY_TREE == $display_check_data[$cf]['type'])
                        {
                        ?>
                        <script>
                        jQuery(document).ready(function()
                            {
                            <?php
                            if($GLOBALS["multiple"] === false)
                                {
                                ?>
                                checkDisplayCondition<?php echo $field['ref']; ?>();
                                <?php
                                }
                            ?>
                            jQuery('#CentralSpace').on('categoryTreeChanged', function(e,node)
                                {
                                checkDisplayCondition<?php echo $field['ref']; ?>();
                                });
                            });
                        </script>
                        <?php

                        // Move on to the next field now
                        continue;
                        }
                    elseif(FIELD_TYPE_DYNAMIC_KEYWORDS_LIST == $display_check_data[$cf]['type'])
                        {
                        ?>
                        <script>
                        jQuery(document).ready(function()
                            {
                            <?php
                            if($GLOBALS["multiple"] === false)
                                {
                                ?>
                                console.debug('[document.ready] Going to call checkDisplayCondition<?php echo $field['ref']; ?>()');
                                checkDisplayCondition<?php echo $field['ref']; ?>();
                                <?php
                                }
                            ?>
                            jQuery('#CentralSpace').on('dynamicKeywordChanged', function(e,node)
                                {
                                console.debug('#CentralSpace-on-dynamicKeywordChanged for field #<?php echo $field['ref']; ?>');
                                checkDisplayCondition<?php echo $field['ref']; ?>();
                                });
                            });
                        </script>
                        <?php

                        // Move on to the next field now
                        continue;
                        }

                    $checkname = "nodes[{$display_check_data[$cf]['ref']}][]";

                    if(FIELD_TYPE_RADIO_BUTTONS == $display_check_data[$cf]['type'])
                        {
                        $checkname = "nodes[{$display_check_data[$cf]['ref']}]";
                        }

                    $jquery_selector = "input[name=\"{$checkname}\"]";

                    if(FIELD_TYPE_DROP_DOWN_LIST == $display_check_data[$cf]['type'])
                        {
                        $checkname       = "nodes[{$display_check_data[$cf]['ref']}]";
                        $jquery_selector = "select[name=\"{$checkname}\"]";
                        }
                    ?>
                    <script type="text/javascript">
                    jQuery(document).ready(function()
                        {
                        <?php
                        if($GLOBALS["multiple"] === false)
                            {
                            ?>
                            checkDisplayCondition<?php echo $field['ref']; ?>();
                            <?php
                            }
                        ?>
                        jQuery('<?php echo $jquery_selector; ?>').change(function ()
                            {
                            checkDisplayCondition<?php echo $field['ref']; ?>();
                            });
                        });
                    </script>
                    <?php
                    }
                else
                    {
                    ?>
                    <script type="text/javascript">
                    jQuery(document).ready(function()
                        {
                        <?php
                        if($GLOBALS["multiple"] === false)
                            {
                            ?>
                            checkDisplayCondition<?php echo $field['ref']; ?>();
                            <?php
                            }
                        ?>
                        jQuery('#field_<?php echo $display_check_data[$cf]["ref"]; ?>').change(function ()
                            {
                            checkDisplayCondition<?php echo $field['ref']; ?>();
                            });
                        });
                    </script>
                    <?php
                    }
                }

            } # see if next field needs to be checked
        $condref++;

        } # check next condition

    if($render_js)
        {
        $question_id = '#question_' . $n . ($GLOBALS["multiple"] === true ? '' : '_' . $resource_ref);
        ?>
        <script type="text/javascript">
        function checkDisplayCondition<?php echo $field["ref"];?>()
            {
            console.debug('(<?php echo str_replace(dirname(__DIR__), '', __FILE__) . ':' . __LINE__?>) checkDisplayCondition<?php echo $field["ref"]; ?>()');

            if (jQuery('#field_<?php echo (int) $field["ref"]; ?>_displayed').length === 0)
                {
                console.debug('Skipping - field <?php echo (int) $field["ref"]; ?> not found on form');
                return;
                }

            // Get current display state for governed field ("block" or "none")
            field<?php echo $field['ref']; ?>status    = jQuery('<?php echo escape($question_id); ?>').css('display');
            newfield<?php echo $field['ref']; ?>status = 'none';

            // Assume visible by default
            field<?php echo $field['ref']; ?>visibility = true;
            <?php
            foreach($scriptconditions as $scriptcondition)
                {
                /* Example of $scriptconditions:
                    [{"field":"73","type":"3","display_as_dropdown":"0","valid":["267","266"]}]
                */
                ?>

                field<?php echo $field['ref']; ?>valuefound = false;
                fieldokvalues<?php echo $scriptcondition['field']; ?> = <?php echo json_encode($scriptcondition['valid']); ?>;
                console.debug('[checkDisplayCondition<?php echo $field["ref"]; ?>] fieldokvalues<?php echo $scriptcondition['field']; ?> = %o', fieldokvalues<?php echo $scriptcondition['field']; ?>);

                <?php
                ############################
                ### Field type specific
                ############################
                if(in_array($scriptcondition['type'], $FIXED_LIST_FIELD_TYPES))
                    {
                    $jquery_condition_selector = "input[name=\"nodes[{$scriptcondition['field']}][]\"]";
                    $js_conditional_statement  = "fieldokvalues{$scriptcondition['field']}.indexOf(element.value) != -1";

                    if(FIELD_TYPE_CHECK_BOX_LIST == $scriptcondition['type'])
                        {
                        $js_conditional_statement = "element.checked && {$js_conditional_statement}";
                        }

                    if(FIELD_TYPE_DROP_DOWN_LIST == $scriptcondition['type'])
                        {
                        $jquery_condition_selector = "select[name=\"nodes[{$scriptcondition['field']}]\"] option:selected";
                        }

                    if(FIELD_TYPE_RADIO_BUTTONS == $scriptcondition['type'])
                        {
                        $jquery_condition_selector = "input[name=\"nodes[{$scriptcondition['field']}]\"]:checked";
                        }
                    ?>
                        jQuery('<?php echo $jquery_condition_selector; ?>').each(function(index, element)
                            {
                            if(<?php echo $js_conditional_statement; ?>)
                                {
                                field<?php echo $field['ref']; ?>valuefound = true;
                                }
                            });

                    <?php
                    }
                ?>
                if(!field<?php echo $field['ref']; ?>valuefound)
                    {
                    field<?php echo $field['ref']; ?>visibility = false;
                    }
<?php
                }
                ?>

                // Is field to be displayed
                if(field<?php echo $field['ref']; ?>visibility)
                    {
                    newfield<?php echo $field['ref']; ?>status = 'block';
                    }

                // If display status changed then toggle the visibility
                if(newfield<?php echo $field['ref']; ?>status != field<?php echo $field['ref']; ?>status)
                    {
                    jQuery('<?php echo escape($question_id); ?>').css("display", newfield<?php echo $field['ref']; ?>status); 
                    // The visibility status (block/none) will be sent to the server in the following field
                    jQuery('#field_<?php echo $field['ref']; ?>_displayed').attr("value",newfield<?php echo $field['ref']; ?>status);

                <?php
                // Batch edit mode
                if($GLOBALS["multiple"] === true)
                    {
                    ?>
                    var batch_edit_editthis = jQuery("#<?php echo "editthis_{$n}"; ?>");
                    batch_edit_editthis.prop("checked", !batch_edit_editthis.prop("checked"));
                    batch_edit_toggle_edit_multi_checkbox_question(<?php echo (int) $n; ?>);
                    <?php
                    }
                    ?>

                    if(jQuery('<?php echo escape($question_id); ?>').css('display') == 'block')
                        {
                        jQuery('<?php echo escape($question_id); ?>').css('border-top', '');
                        }
                    else
                        {
                        jQuery('<?php echo escape($question_id); ?>').css('border-top', 'none');
                        }
                    }
            }
        </script>
        <?php
        }

    return $displaycondition;
    }


/**
* Utility to check if browse bar should be rendered
*  
* @return boolean
*/   
function has_browsebar()
    {
    global $username, $pagename, $loginterms, $not_authenticated_pages, $k, $internal_share_access, $browse_bar;
    return isset($username)
    && is_array($not_authenticated_pages) && !in_array($pagename, $not_authenticated_pages)
    && ('' == $k || $internal_share_access)
    && $browse_bar;
    //   && false == $loginterms ?
    }

/**
* Utility to if collapsable upload options should be displayed
*  
* @return boolean
*/   
function display_upload_options()
    {
    global $metadata_read, $enable_add_collection_on_upload, $relate_on_upload, $camera_autorotation;
    return $metadata_read || $enable_add_collection_on_upload || $relate_on_upload || $camera_autorotation;
    }
    

function display_field_data(array $field,$valueonly=false,$fixedwidth=452)
    {
    debug_function_call(__FUNCTION__, [$field['ref'], $valueonly, $fixedwidth]);
    global $ref, $show_expiry_warning, $access, $search, $extra, $lang, $FIXED_LIST_FIELD_TYPES, $force_display_template_orderby;

    $value = (string) $field["value"];
    $title=escape($field["title"]);
    $modified_field=hook("beforeviewdisplayfielddata_processing","",array($field));
    if($modified_field)
        {
        $field=$modified_field;
        debug(sprintf('$field #%s was modified by beforeviewdisplayfielddata_processing hook', $field['ref']));
        }

    $warningtext="";
    $dismisstext="";
    $dismisslink="";
    # Handle expiry date warning messages
    if (!$valueonly && $field["type"] == FIELD_TYPE_EXPIRY_DATE && $value != "" && $value <= date("Y-m-d H:i") && $show_expiry_warning) 
        {
        debug('Handle expiry date warning messages');
        $title = escape($lang["warningexpired"]);
        $warningtext = escape($lang["warningexpiredtext"]);
        $dismisstext = LINK_CARET . escape($lang["warningexpiredok"]);
        $dismisslink = "<p id=\"WarningOK\">
        <a href=\"#\" onClick=\"document.getElementById('RecordDownloadTabContainer').style.display='block';document.getElementById('WarningOK').style.display='none';\">{$dismisstext}</a></p>";
        $extra.="<style>#RecordDownloadTabContainer {display:none;}</style>";

        # If there is no display template then prepare the full markup here
        if (trim((string) $field["display_template"]) == "") 
            {
            $extra.="<div class=\"clearerleft\"></div><div class=\"RecordStory\"><h1>{$title}</h1>
            <p>{$value}</p><p>{$warningtext}</p>{$dismisslink}</div>";
            }
        }
    
    # Handle general warning messages
    if (!$valueonly && $field["type"] == FIELD_TYPE_WARNING_MESSAGE && trim($value) != "") 
        {
        debug('Handle general warning messages');
        $warningtext = $value;
        $dismisstext = LINK_CARET . escape($lang["warningexpiredok"]);
        $dismisslink = "<p id=\"WarningOK_{$field['ref']}\">
        <a href=\"#\" onClick=\"document.getElementById('RecordDownloadTabContainer').style.display='block';document.getElementById('WarningOK_{$field['ref']}').style.display='none';\">{$dismisstext}</a></p>";
        $extra.="<style>#RecordDownloadTabContainer {display:none;}</style>";

        # If there is no display template then prepare the full markup here
        if (trim((string) $field["display_template"]) == "") 
            {
            $extra.="<div class=\"clearerleft\"></div><div class=\"RecordStory\"><h1>{$title}</h1>
            <p>".nl2br(escape(i18n_get_translated($warningtext)))."</p>{$dismisslink}</div>";
            }
        }

    if ($field['value_filter']!="")
        {
        debug('Calling value_filter...');
        eval(eval_check_signed($field['value_filter']));
        }
    elseif ($field["type"] == FIELD_TYPE_DATE_AND_OPTIONAL_TIME && strpos($value,":") !== false)
        {
        // Show the time as well as date if entered
        $value=nicedate($value,true,true);
        }
    elseif ($field["type"]==FIELD_TYPE_DATE_AND_OPTIONAL_TIME || $field["type"]==FIELD_TYPE_EXPIRY_DATE || $field["type"]==FIELD_TYPE_DATE)
        {
        $value=nicedate($value,false,true);
        }
    elseif ($field["type"]==FIELD_TYPE_DATE_RANGE)
        {
        $rangedates = explode(",",$value);
        natsort($rangedates);
        $value=implode(DATE_RANGE_SEPARATOR,$rangedates);
        }
    
        if($field['type'] == FIELD_TYPE_CATEGORY_TREE)
            {
            $parentnode = null;
            $recursive = true;
            $treenodes = get_nodes($field["ref"], $parentnode, $recursive);
            $detailed=false; # Just get the nodes
            $node_sort=false; # Do not sort the nodes
            $resource_nodes=get_resource_nodes($ref, $field["ref"], $detailed, $node_sort);
            $treenodenames = array();
            foreach ($treenodes as $treenode)
                {
                if(in_array($treenode["ref"],$resource_nodes)) 
                    {
                    $treenodenames[] = ["ref" => $treenode["ref"], "name" => $treenode["translated_path"]];
                    }
                }

                // Render as plain text if the user is not authenticated as they will not be able to use the node searches from the pills view
                if (is_authenticated()) {
                    $value = render_fixed_list_as_pills($treenodenames);
                } else {
                    $value = implode(', ', array_column($treenodenames, 'name'));
                }
            }
    
    if (($value!="") && ($value!=",") && ($field["display_field"]==1) && ($access==0 || ($access==1 && !$field["hide_when_restricted"])))
        {
        debug('Field can display...');
        if (!$valueonly)
            {
            debug('Showing title');
            $title=escape(str_replace("Keywords - ","",$field["title"]));
            }
        else
            {
            debug('Blanking title');
            $title="";
            }

        # Value formatting
        # Optimised to use the value as is if there are no "~" characters present in the value
        if (is_i18n_language_string($value) && $field['type'] != FIELD_TYPE_CATEGORY_TREE) {
            $field_value = $value;
            debug('value formatting due to ~ character...');
            # The field value may be a list of comma separated language encoded values, so process the nodes
            $field_nodes_in_value = explode(",", $field["nodes"]);
            if (count($field_nodes_in_value) > 1) {
                # Multiple nodes in value; Get all nodes for the field and translate each one which is in the metadata
                $field_nodes_all = get_nodes($field['ref']);
                $names_i18n_in_value = extract_node_options($field_nodes_all, true, true);
                # Convert the field nodes in value as an array keyed by the names to allow an intersect by key operation 
                $node_names_in_value = array_intersect_key($names_i18n_in_value, array_flip($field_nodes_in_value));
                $value = implode(', ', $node_names_in_value);
                $field_nodes_translated = array_map(function($node_value, $node_id) 
                        {
                        return ['name' => $node_value, 'ref' => $node_id];
                        }
                        , $node_names_in_value, array_keys($node_names_in_value));
            }

            # Not a node list so translate the raw value or something went wrong trying to resolve the node values 
            # so attempt to translate the raw value to display instead
            if (count($field_nodes_in_value) == 1 || trim($value) == "") {
                # Translate the single value
                $value=i18n_get_translated($field_value);
                $field_nodes_translated = [['name' => $value, 'ref' => $field['nodes']]];
            }
        }

        // Handle the rest of the fixed list fields, category trees have their own section
        // Only render the pills view if the user is authenticated as unauthenticated users will not be able to perform the node searches
        if (in_array($field['type'], $FIXED_LIST_FIELD_TYPES) && $field['type'] != FIELD_TYPE_CATEGORY_TREE && is_authenticated()) {
            $nodes = $field_nodes_translated ?? array_map(function($ref, $name) {
                return ['ref' => $ref, 'name' => $name];
            }, array_keys($field['nodes_values']), $field['nodes_values']);
            $value = render_fixed_list_as_pills($nodes);
        }

        # Do not convert HTML formatted fields (that are already HTML) to HTML. Added check for extracted fields set to 
        # TinyMCE that have not yet been edited.
        if(
            ($field["type"] != FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE && !(in_array($field['type'], $FIXED_LIST_FIELD_TYPES) && is_authenticated()))
            || ($field["type"] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE && $value == strip_tags($value))
            )
            {
            $value = nl2br(escape($value));
            }
        elseif($field["type"] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE && $value != strip_tags($value))
            {
            $value = strip_tags_and_attributes($value, ['a'], ['href', 'target', 'rel', 'title']);
            }

        $modified_value = hook('display_field_modified_value', '', array($field));
        if($modified_value)
            {       
            $value = $modified_value['value'];
            debug('field value modified by display_field_modified_value hook');
            }
        # Final stages of rendering

        # Include display template when necessary
        if (!$valueonly && trim($field["display_template"] ?? "")!="")
            {
            debug('Include display_template');
            $value_for_url=$value;
           
            $value_mod_after_highlight=hook('value_mod_after_highlight', '', array($field,$value));
            if($value_mod_after_highlight)
                {
                $value=$value_mod_after_highlight;
                }

            # Use a display template to render this field
            $template = $field['display_template'];
            $template = str_replace(['[url]', '%5Burl%5D'], $value_for_url, $template); # prevent sanitising the placeholder (within strip_tags_and_attributes)
            $template = strip_tags_and_attributes($template, array("a"), ['href', 'target', 'rel', 'title']);
            $template = str_replace('[title]', $title, $template);
            $template = str_replace('[value]', $value, $template);
            $template = str_replace('[warning]', escape($warningtext), $template);
            $template = str_replace('[ref]', (int) $ref, $template);
            $template = str_replace('[link]', strip_tags_and_attributes($dismisslink, array("a"), array("href", "onclick")), $template);

            /*Language strings
            Format: [lang-language-name_here]
            Example: [lang-resourcetype-photo]
            */
            preg_match_all('/\[lang-(.+?)\]/', $template, $template_language_matches);
            $i = 0;
            foreach($template_language_matches[0] as $template_language_match_placeholder)
                {
                $placeholder_value = $template_language_match_placeholder;

                if(isset($lang[$template_language_matches[1][$i]]))
                    {
                    $placeholder_value = $lang[$template_language_matches[1][$i]];
                    }

                $template = str_replace($template_language_match_placeholder, $placeholder_value, $template);

                $i++;
                }

            $extra   .= $template;
            }
        else
            {
            debug('No display template');
            # There is a value in this field, but we also need to check again for a current-language value after the i18n_get_translated() function was called, to avoid drawing empty fields
            if ($value!="")
                {
                debug('Draw this field normally...');
                # Draw this field normally. - value has already been sanitized by htmlspecialchars
                $value_mod_after_highlight=hook('value_mod_after_highlight', '', array($field,$value));
                if($value_mod_after_highlight)
                    {
                    $value=$value_mod_after_highlight;
                    }
                
                ?><div 
                <?php
                if (!$valueonly)
                    {
                    if ($field["full_width"])
                        {
                        echo "class=\"clearerleft item itemType".$field['type']."\"";
                        }
                    else
                        {
                        echo "class=\"itemNarrow itemType".$field['type']."\"";
                        }
                    }
                elseif (isset($fixedwidth))
                    {
                    echo "style=\"width:" . $fixedwidth . "px\"";
                    } ?>>
                <h3><?php echo $title?></h3><p><?php echo $value; ?></p></div><?php
                }
            }
            
        if($force_display_template_orderby)
            {
            echo $extra;
            $extra='';
            }
        }
    }

/*
* Helper function for display_field to generate html for fixed list fields, rendering them as a series of pills with search links
*
* @param array $node Array of nodes to display
*/
function render_fixed_list_as_pills($nodes):string 
{ 
    global $baseurl;
    $display_html = "";
    if (count($nodes) > 0) {
        foreach ($nodes as $nodedata) {
            // Add zero width spaces to break up strings on non alphanumeric characters to allow for text wrapping
            $nodedata["name"] = preg_replace_callback('/[^a-zA-Z0-9]/u', function($matches) {
                return $matches[0] . "\u{200B}";
            }, $nodedata["name"]);
            $search_url = generateURL($baseurl . '/pages/search.php', ['search' => NODE_TOKEN_PREFIX . $nodedata['ref']]);
            $display_html .= "<a href=\"{$search_url}\" onclick=\"CentralSpaceLoad(this)\">";
            $display_html .= "<div class=\"fixedlistnodepill\">" . escape($nodedata["name"]) . "</div>";
            $display_html .= "</a>";
        }
        return "<div>" . $display_html . "</div>";
    }
    return '';
}

/*
* Render the resource lock/unlock link for resource tools
* 
* @param  int       $ref         Resource ID
* @param  int       $lock_user   ID of the user that locked the resource
* @param  boolean   $editaccess  Does the user have edit access to the resource?
* 
* @return void
*/
function render_resource_lock_link($ref,$lockuser,$editaccess)
    {
    global $userref, $lang;
    
    $resource_locked = (int)$lockuser > 0;

    $edit_lock_option = false;
    if(checkperm("a") 
        ||
        $userref == $lockuser
        ||
        (!$resource_locked && !checkperm("nolock") && $editaccess)
        )
        {
        $edit_lock_option = true;
        }

    if(!$resource_locked && !$edit_lock_option)
        {
        // User is not permitted to lock resource
        return;
        }    
    
    $lock_details = get_resource_lock_message($lockuser);

    echo "<li>";
    if($edit_lock_option)
        {
        echo "<a href='#' id='lock_link_" . $ref . "' onclick='return updateResourceLock(" . $ref . ",!resource_lock_status);' ";
        echo "title='" .  $lock_details . "'";
        echo "class='LockedResourceAction " . ($resource_locked ? "ResourceLocked" : "ResourceUnlocked" ). "'>&nbsp;";
        if($resource_locked)
            {
            $locktext = (checkperm("a") || ($lockuser == $userref)) ? $lang["action_unlock"] : $lang["status_locked"];
            }
        else
            {
            $locktext = $lang["action_lock"];
            }
        echo $locktext . "</a>";
        }
    else
        {
        echo "<div  class='ResourceLocked' title='" .  escape($lock_details) . "' >" . $lang["status_locked"] . "</div>";
        }

    echo "<a id='lock_details_link' href='#' " . ($resource_locked ? "" : "style='display:none;'") . " onclick='if(resource_lock_status){styledalert(\"" . escape($lang["status_locked"]) . "\",lockmessage[" . escape($ref) . "]);}'>&nbsp;<i class='icon-info'></i></a> </li>";
    }

/**
 * EditNav - render html for next/back browsing on the resource edit page. Called by SaveAndClearButtons()
 *
 * @return void
 */
function EditNav() 
   {
    global $baseurl_short,$ref,$search,$offset,$order_by,$sort,$archive,$lang,$modal,$restypes,$disablenavlinks,$upload_review_mode, $urlparams;
    ?>
    <div class="BackToResultsContainer"><div class="backtoresults">
    <?php
    if(!$disablenavlinks && !$upload_review_mode)
        {?>
        <a class="prevLink icon-arrow-left" onClick="return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Load(this,true);" href="<?php echo generateURL($baseurl_short . "pages/edit.php",$urlparams, array("go"=>"previous")); ?>" title="<?php echo escape($lang["previous"]); ?>"></a>
   
        <a class="upLink" onClick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl_short . "pages/search.php",$urlparams, array("go"=>"previous")); ?>"><?php echo escape($lang["viewallresults"])?></a>
   
        <a class="nextLink icon-arrow-right" onClick="return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Load(this,true);" href="<?php echo generateURL($baseurl_short . "pages/edit.php",$urlparams, array("go"=>"next")); ?>" title="<?php echo escape($lang["next"]); ?>"></a>
   
        <?php
        }
    if ($modal)
        { ?>
        &nbsp;&nbsp;<a class="maxLink icon-maximize-2" href="<?php echo generateURL($baseurl_short . "pages/edit.php",$urlparams); ?>" onClick="return CentralSpaceLoad(this);" title="<?php echo escape($lang["maximise"]); ?>"></a>
        &nbsp;<a href="#"  class="closeLink icon-x" onClick="ModalClose();" title="<?php echo escape($lang["close"]); ?>"></a>
        <?php
        } ?>
    </div></div><?php
  }
 
/**
 * Render the 'QuestionSubmit' div with the 'Save', 'Clear' and 'Save all with values' locked Buttons - used by the resource edit page
 *
 * @param  string   $extraclass   - Additional CSS classes to add to the Question div
 * @param  bool     $requiredfields - to indicate the input is required
 * @param  bool     $backtoresults - Show the next/back links using EditNav()
 * @return void
 */
function SaveAndClearButtons($extraclass="",$requiredfields=false,$backtoresults=false)
    {
    global $lang, $multiple, $ref, $upload_review_mode, $noupload, $is_template,
    $modal, $edit_selection_collection_resources, $locked_fields;

    $save_btn_value = ($ref > 0 ? ($upload_review_mode ? $lang["saveandnext"] : $lang["save"]) : $lang["next"]);
    if($ref < 0 && $noupload)
        {
        $save_btn_value = $lang['create'];
        }

    $confirm_text = $lang["confirmeditall"];
    if($edit_selection_collection_resources)
        {
        $confirm_text = $lang["confirm_edit_all_selected_resources"];
        }
    ?>
    <div class="QuestionSubmit <?php echo $extraclass ?>">
        <?php
        if($ref < 0 || $upload_review_mode)
            {
            echo "<input id='edit_reset_button' name='resetform' class='resetform' type='submit' value='" . escape($lang["clearbutton"]) . "' />&nbsp;";
            }
            ?>
        <input 
        <?php 
        if ($multiple) 
            { ?>onclick="return confirm('<?php echo $confirm_text; ?>');"
            <?php 
            } 
            ?>
               name="save"
               id="edit_save_button"
               class="editsave"
               type="submit"
               value="<?php echo escape($save_btn_value); ?>" />
        <?php
        if($upload_review_mode)
            {
            ?>&nbsp;<input id="edit_save_auto_button" name="save_auto_next" <?php if(count($locked_fields) == 0){echo "style=\"display:none;\"";} ?>class="editsave save_auto_next" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["save_and_auto"]) ?>&nbsp;&nbsp;" />
            <?php
            }

        if(!$is_template && $requiredfields)
            {
            ?>
            <div class="RequiredFieldLabel"><sup>*</sup> <?php if($multiple){echo escape($lang["requiredfield_multiple"]); } else {echo escape($lang['requiredfield']); } ?></div>
            <?php
            }

        hook("extra_edit_buttons");
        
        # Duplicate navigation
       if (!$multiple && !$modal && $ref>0 && !hook("dontshoweditnav") && $backtoresults)
            {
            EditNav();
            }
            ?>
        <br />
        <div class="clearerleft"> </div>
    </div>
    <?php
    }


/**
 * display_size_option
 *
 * @param  mixed $sizeID
 * @param  mixed $sizeName
 * @param  mixed $fordropdown
 * @return void
 */
function display_size_option($sizeID, $sizeName, $fordropdown=true, $selected=false)
    {
    global $available_sizes, $lang, $result;
    if(!hook('replace_display_size_option','',array($sizeID, $sizeName, $fordropdown))){
        if ($fordropdown)
            {
            ?><option value="<?php echo escape($sizeID) ?>" <?php echo ($selected) ? 'selected' : ''; ?>><?php
            echo $sizeName;
            }
        if(isset($available_sizes[$sizeID]))
            {
            $availableCount = count($available_sizes[$sizeID]);
            }
        else
            {
            $availableCount=0;
            }
        $resultCount = count($result);
        if ($availableCount != $resultCount && $sizeID != "largest")
            {
            echo " (" . escape($availableCount . " " . $lang["of"] . " " . $resultCount) . " ";
            switch ($availableCount)
                {
                case 0:
                    echo escape($lang["are_available-0"]);
                    break;
                case 1:
                    echo escape($lang["are_available-1"]);
                    break;
                default:
                    echo escape($lang["are_available-2"]);
                    break;
                }
            echo ")";
            }
             if ($fordropdown)
                {
            ?></option><?php
            }
        }
    }


/**
* Render the featured collection category selector
* 
* @param integer $parent   Parent collection ref
* @param array   $context  Contextual data (e.g depth level to render or the current branch path)
* 
* @return void
*/
function render_featured_collection_category_selector(int $parent, array $context)
    {
    global $lang;

    // If this information is missing, that's an unrecoverable error, the developer should really make sure this information is provided
    $collection = $context["collection"]; # as returned by get_collection()
    $depth = (int) $context["depth"];
    $current_branch_path = $context["current_branch_path"]; # as returned by get_featured_collection_category_branch_by_leaf()
    $modal = (isset($context["modal"]) && is_bool($context["modal"]) ? $context["modal"] : false);

    $featured_collection_categories = get_featured_collection_categories($parent, array());
    if(empty($featured_collection_categories) && $depth>0)
        {
        return;
        }

    $html_selector_name = "selected_featured_collection_category_{$depth}";
    $html_question_label_txt = $lang["themecategory"] . ($depth == 0 ? "" : " {$depth}");
    ?>
    <div class="Question">
        <label for="<?php echo $html_selector_name; ?>"><?php echo $html_question_label_txt; ?></label>
        <?php
        $next_level_parent = null;
        ?>
        <select id="<?php echo $html_selector_name; ?>" class="stdwidth" name="<?php echo $html_selector_name; ?>"
                onchange="featured_collection_category_select_onchange(this, document.getElementById('collectionform'));
                <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(document.getElementById('collectionform'));">                
            <option value="0"><?php echo escape($lang["select"]); ?></option>
        <?php
        // Allow user to move FC category to the root. Because we don't expose the collection type to the user, this will
        // give users the ability to convert between public collection and featured category at root level without access
        // to the collection type.
        if($depth == 0)
            {
            $dummy_root_lvl_selected = ($collection["type"] == COLLECTION_TYPE_FEATURED && $parent == 0 ? "selected" : "");
            ?>
            <option value="root" <?php echo $dummy_root_lvl_selected; ?>><?php echo escape($lang["featured_collection_root_category"]); ?></option>
            <?php
            }
        foreach($featured_collection_categories as $fc_category)
            {
            // Never show as an option the FC you're editing
            if($fc_category["ref"] == $collection["ref"])
                {
                continue;
                }

            $html_attr_selected = "";
            if(isset($current_branch_path[$depth]) && $fc_category["ref"] == $current_branch_path[$depth]["ref"])
                {
                $html_attr_selected = "selected";
                $next_level_parent = $fc_category["ref"];
                }
            ?>
            <option value="<?php echo $fc_category["ref"]; ?>" <?php echo $html_attr_selected; ?>><?php echo escape(i18n_get_translated($fc_category["name"])); ?></option>
            <?php
            }
            ?>
        </select>
        <div class="clearerleft"></div>
    </div>
    <?php
    if(is_null($next_level_parent))
        {
        return;
        }

    $context["depth"] = ++$depth;
    render_featured_collection_category_selector($next_level_parent, $context);
    }


/**
* Render featured collections (as tiles on the collections_featured.php page)
* 
* @param array $ctx    Context data to allow caller code to decide rendering requirements
* @param array $items  List of items to render (featured collection category, actual collection or smart collection)
*/
function render_featured_collections(array $ctx, array $items)
    {
    global $baseurl_short, $lang, $k, $themes_simple_images, $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS, $themes_simple_view,$show_theme_collection_stats;

    $is_smart_featured_collection = (isset($ctx["smart"]) ? (bool) $ctx["smart"] : false);
    $general_url_params = (isset($ctx["general_url_params"]) && is_array($ctx["general_url_params"]) ? $ctx["general_url_params"] : array());
    $all_fcs = (isset($ctx["all_fcs"]) && is_array($ctx["all_fcs"]) ? $ctx["all_fcs"] : array());

    foreach($items as $fc)
        {
        $render_ctx = $ctx;
        $is_featured_collection_category = is_featured_collection_category($fc);
        $is_featured_collection = (!$is_featured_collection_category && !$is_smart_featured_collection);

        $tool_edit = array(
            "href" => generateURL("{$baseurl_short}pages/collection_edit.php",
                array(
                    "ref" => $fc["ref"],
                    "redirection_endpoint" => urlencode(
                        generateURL(
                            "{$baseurl_short}pages/collections_featured.php",
                            $general_url_params,
                            array("parent" => $fc["parent"])
                        )
                    )
                )
            ),
            "icon" => 'icon-square-pen',
            "text" => $lang['action-edit'],
            "modal_load" => true,
            "redirect" => true
        );
        $tool_select = array(
            "icon" => 'icon-circle-check',
            "text" => $lang['action-select'],
            'custom_onclick' => sprintf("return ChangeCollection(%s, '');", escape($fc['ref'])),
        );
        $tool_upload_to_collection = [
            'href' => $GLOBALS['upload_then_edit']
                ? generateURL(
                    "{$baseurl_short}pages/upload_batch.php", 
                    [
                        'collection_add' => $fc['ref'], 
                        'entercolname' => $fc['name']
                    ]
                )
                : generateURL(
                    "{$baseurl_short}pages/edit.php",
                    [
                        'uploader' => $GLOBALS['upload_then_edit'],
                        'ref' => -$GLOBALS['userref'],
                        'collection_add' => $fc['ref']
                    ]
                ),
            'icon' => 'icon-upload',
            'text' => $lang["action-upload-to-collection"],
        ];

        // Prepare FC images
        $thumbnail_selection_method = $fc["thumbnail_selection_method"];
        $show_images = ($themes_simple_view && in_array($thumbnail_selection_method, $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS) && $thumbnail_selection_method != $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["no_image"]);
        unset($fc_resources);
        if($themes_simple_images && $show_images)
            {
            $fc_resources = get_featured_collection_resources(
                $fc,
                array(
                    "smart" => $is_smart_featured_collection,
                    "use_thumbnail_selection_method" => !$is_smart_featured_collection,
                    "all_fcs" => $all_fcs,
                ));
            $fc_images = generate_featured_collection_image_urls($fc_resources, "pre");

            if(!empty($fc_images))
                {
                $render_ctx["images"] = $fc_images;
                }
            }

        // Featured collection default tools
        if ($is_featured_collection && checkPermission_dashmanage()) {
            $render_ctx["tools"][] = array(
                "href" => generateURL(
                    "{$baseurl_short}pages/dash_tile.php",
                    array(
                        'create'            => 'true',
                        'tltype'            => 'srch',
                        'title'             => "{$fc['name']}",
                        'freetext'          => 'true',
                        'tile_audience'     => 'false',
                        'all_users'         => 1,
                        'promoted_resource' => (isset($render_ctx["images"]) ? 'true' : ""),
                        'link'              => "{$baseurl_short}pages/search.php?search=!collection{$fc['ref']}",
                    )
                ),
                "icon" => 'icon-layout-dashboard',
                "text" => $lang['add_to_dash'],
            );
        }
        if($is_featured_collection && allow_featured_collection_share($fc))
            {
            $render_ctx["tools"][] = array(
                "href" => generateURL("{$baseurl_short}pages/collection_share.php", array("ref" => $fc["ref"])),
                "icon" => 'icon-share-2',
                "text" => $lang["share"]);
            }
        if($is_featured_collection && collection_readable($fc['ref']))
            {
            $render_ctx["tools"][] = $tool_select;
            }
        if($is_featured_collection && collection_writeable($fc['ref']))
            {
            $render_ctx["tools"][] = $tool_edit;
            }
        if($is_featured_collection && $show_theme_collection_stats)
            {
            $render_ctx['show_resources_count'] = true;
            }
        if (
            $is_featured_collection
            && !$is_smart_featured_collection
            && ($collection_data = get_collection($fc['ref'], true))
            && allow_upload_to_collection(is_array($collection_data) ? $collection_data : [])
        ) {
            $render_ctx['tools'][] = $tool_upload_to_collection;
        }


        if ($is_featured_collection_category && !$is_smart_featured_collection) {
            global $enable_theme_category_edit;

            $fc_category_url = generateURL("{$baseurl_short}pages/collections_featured.php", $general_url_params, array("parent" => $fc["ref"]));
            $fc_category_has_children = (isset($fc["has_children"]) ? (bool) $fc["has_children"] : false);

            $render_ctx["href"] = $fc_category_url;
            $render_ctx["icon"] = ICON_FOLDER;
            $render_ctx["tools"] = array();

            if(checkPermission_dashmanage())
                {
                $render_ctx["tools"][] = array(
                    "href" => generateURL(
                        "{$baseurl_short}pages/dash_tile.php",
                        array(
                            'create'            => 'true',
                            'tltype'            => 'fcthm',
                            'tlstyle'           => 'thmbs',
                            'title'             => "{$fc['name']}",
                            'freetext'          => 'true',
                            'tile_audience'     => 'false',
                            'promoted_resource' => (isset($render_ctx["images"]) ? 'true' : ""),
                            'link'              => $fc_category_url
                        )
                    ),
                    "icon" => 'icon-layout-dashboard',
                    "text" => $lang["add_to_dash"]);
                }

            $render_ctx["tools"][] = array(
                "href" => generateURL("{$baseurl_short}pages/collection_share.php", array("ref" => $fc["ref"])),
                "icon" => 'icon-share-2',
                "text" => $lang["share"],
            );


            if(!$fc_category_has_children && collection_readable($fc['ref']))
                {
                $render_ctx["tools"][] = $tool_select;
                }

            if(can_edit_featured_collection_category())
                {
                $render_ctx["tools"][] = $tool_edit;
                }

            if (
                !$fc_category_has_children
                && ($collection_data = get_collection($fc['ref'], true))
                && allow_upload_to_collection(is_array($collection_data) ? $collection_data : [])
            ) {
                $render_ctx['tools'][] = $tool_upload_to_collection;
            }
        }

        if($is_smart_featured_collection)
            {
            $search = NODE_TOKEN_PREFIX . $fc["ref"];
            $render_ctx["href"] = generateURL("{$baseurl_short}pages/search.php", array("search" => $search, "resetrestypes" => "true"));

            $node_is_parent = (isset($fc["node_is_parent"]) ? $fc["node_is_parent"] : true);
            if($node_is_parent)
                {
                $render_ctx["href"] = generateURL(
                    "{$baseurl_short}pages/collections_featured.php",
                    array(
                        "smart_rtf" => $fc["resource_type_field"],
                        "smart_fc_parent" => $fc["parent"],
                    ));
                }
            $render_ctx["icon"] = ICON_FOLDER;
            $render_ctx["tools"] = array();
            }
            
        // Don't show the tools for external shares
        if (trim($k) != "")
            {
            $render_ctx["tools"] = array();
            }

        render_featured_collection($render_ctx, $fc);
        }
    }


/**
* Render a featured collection (as tiles on the collections_featured.php page)
* 
* @param array $ctx Context data to allow caller code to decide rendering requirements
* @param array $fc  Featured collection data structure
* 
* @return void
*/
function render_featured_collection(array $ctx, array $fc)
    {
    if(empty($fc))
        {
        return;
        }

    global $baseurl_short, $lang, $k, $flag_new_themes, $flag_new_themes_age, $view_title_field;

    $is_smart_featured_collection = (isset($ctx["smart"]) ? (bool) $ctx["smart"] : false);
    $full_width = (isset($ctx["full_width"]) && $ctx["full_width"]);
    $general_url_params = (isset($ctx["general_url_params"]) && is_array($ctx["general_url_params"]) ? $ctx["general_url_params"] : array());
    $show_resources_count = (isset($ctx["show_resources_count"]) ? (bool) $ctx["show_resources_count"] : false);
    $reorder = (bool) ($ctx['reorder'] ?? false);


    $html_container_class = array("FeaturedSimplePanel", "HomePanel", "DashTile", "FeaturedSimpleTile");
    $html_container_style = array();
    $html_container_data_items = [];

    // Make featured collection tile sortable
    if($reorder && !$is_smart_featured_collection)
        {
        $html_container_class[] = 'SortableItem';
        $html_container_data_items['data-fc-ref'] = $fc['ref'];
        }


    // Set main featured collection URL (e.g for collections it's the !collection[ID], for categories it's for collection_featured.php)
    $html_fc_a_href = generateURL("{$baseurl_short}pages/search.php", $general_url_params, array("search" => "!collection{$fc["ref"]}"));
    $html_fc_a_href = (isset($ctx["href"]) && trim($ctx["href"]) !== "" ? $ctx["href"] : $html_fc_a_href);


    $html_contents_class = array("FeaturedSimpleTileContents");
    $html_contents_icon = (isset($ctx["icon"]) && trim($ctx["icon"]) != "" ? $ctx["icon"] : ICON_CUBE);
    $fc_display_name = strip_prefix_chars(i18n_get_collection_name($fc),"*");

    $html_contents_h2 = $html_contents_icon . $fc_display_name;
    $html_contents_h2_style = array();
    if(!$is_smart_featured_collection && $flag_new_themes && (time() - strtotime((string)$fc["created"])) < (60 * 60 * 24 * $flag_new_themes_age))
        {
        $html_contents_h2 .= sprintf(' <div class="NewFlag">%s</div>', escape($lang['newflag']));
        }
    if($full_width)
        {
        $html_container_class[] = "FullWidth";
        $html_contents_h2_style[] = "max-width: unset;";

        $action_selection_id = "themes_action_selection{$fc["ref"]}_bottom_{$fc["ref"]}";

        if($show_resources_count && !$is_smart_featured_collection)
            {
            $html_contents_h2 .= sprintf(
                ' <span data-tag="resources_count" data-fc-ref="%s">%s</span>',
                escape($fc['ref']),
                escape($lang['counting_resources']));
            }
        }


    $theme_images = (isset($ctx["images"]) ? $ctx["images"] : array());
    $theme_images = array_map(
        function($theme_image) use ($view_title_field, $lang){
            if (!isset($theme_image['ref'])) {return $theme_image;} // Invalid data [t35944]
            $ref = $theme_image['ref'];
            $resource_data = get_resource_data($ref);
            if ($resource_data===false) {return $theme_image;} // Resource not found
            $theme_image['alt_text'] = $resource_data['field' . $view_title_field] ?? $lang['resource-1'] . ' ' . $ref;
            return $theme_image;
        }
    , $theme_images);
    if(!empty($theme_images))
        {
        $html_container_class[] = "FeaturedSimpleTileImage";

        if(count($theme_images) == 1)
            {
            $alt_string = $theme_images[0]['alt_text'] ?? "";
            $theme_image_path = $theme_images[0]["path"] ?? "";
            $html_container_style[] = "background: url({$theme_image_path});";
            $html_container_style[] = "background-size: cover;";
            $theme_images = array();
            }
        }


    $html_container_data = '';
    foreach($html_container_data_items as $name => $value)
        {
        $html_container_data .= sprintf(' %s="%s"', $name, escape($value));
        }

    $tools = (isset($ctx["tools"]) && is_array($ctx["tools"]) && !$full_width ? $ctx["tools"] : array());

    // DEVELOPER NOTE: anything past this point should be set. All logic is handled above
    ?>
    <div id="FeaturedSimpleTile_<?php echo md5($fc['ref']); ?>" 
         class="<?php echo implode(" ", $html_container_class); ?>" 
         alt="<?php echo escape($alt_string ?? '') ?>"
         style="<?php echo implode(" ", $html_container_style); ?>" <?php echo $html_container_data; ?> >
    <?php
    if (!$full_width) {
        ?>
        <div>
            <a href="<?php echo $html_fc_a_href; ?>" onclick="return CentralSpaceLoad(this, true);" id="featured_tile_<?php echo escape($fc["ref"]); ?>" class="FeaturedSimpleLink">
                <div id="FeaturedSimpleTileContents_<?php echo escape($fc["ref"]); ?>" class="<?php echo implode(" ", $html_contents_class); ?>">
                    <h2 style="<?php echo implode(" ", $html_contents_h2_style); ?>"><?php echo $html_contents_h2; ?></h2>
                <?php
                foreach($theme_images as $i => $theme_image)
                    {
                    ?>
                    <div class="FeaturedImageTile" style="background-image: url('<?php echo $theme_image['path']; ?>')"></div>
                    <?php
                    }
                    ?>
                </div>
            </a>
            <?php
            render_top_right_menu_btn($tools);
            ?>
        </div>
        <?php
        render_featured_collection_context_menu(md5($fc['ref']), $tools);
    } else if ($full_width && !$is_smart_featured_collection) {
        ?>
        <a href="<?php echo $html_fc_a_href; ?>" onclick="return CentralSpaceLoad(this, true);" id="featured_tile_<?php echo escape($fc["ref"]); ?>" class="FeaturedSimpleLink">
            <div id="FeaturedSimpleTileContents_<?php echo escape($fc["ref"]); ?>" class="<?php echo implode(" ", $html_contents_class); ?>">
                <h2 style="<?php echo implode(" ", $html_contents_h2_style); ?>"><?php echo $html_contents_h2; ?></h2>
            <?php
            foreach($theme_images as $i => $theme_image)
                {
                ?>
                <div class="FeaturedImageTile" style="background-image: url('<?php echo $theme_image['path']; ?>')"></div>
                <?php
                }
                ?>
            </div>
        </a>
        <div class="ListTools">
            <div class="ActionsContainer">
                <select class="fcollectionactions" id="<?php echo $action_selection_id ?>" data-actions-loaded="0" data-actions-populating="0" data-col-id="<?php echo escape($fc["ref"]);?>" onchange="action_onchange_<?php echo $action_selection_id ?>(this.value);">
                    <option><?php echo escape($lang["actions-select"]); ?></option>
                </select>
            </div>            
        </div><!-- End of ListTools -->
        <?php
    } else {
        ?>
        <div>
            <a href="<?php echo $html_fc_a_href; ?>" onclick="return CentralSpaceLoad(this, true);" id="featured_tile_<?php echo escape($fc["ref"]); ?>" class="FeaturedSimpleLink">
                <div id="FeaturedSimpleTileContents_<?php echo escape($fc["ref"]); ?>" class="<?php echo implode(" ", $html_contents_class); ?>">
                    <h2 style="<?php echo implode(" ", $html_contents_h2_style); ?>"><?php echo $html_contents_h2; ?></h2>
                </div>
            </a>
        </div>

        <?php
    }
    ?>
    </div><!-- End of FeaturedSimpleTile_<?php echo escape($fc["ref"]); ?>-->
<?php
    }

/**
 * Render the top right menu button ellipsis icon.
 * 
 * @param list<array{href: string, icon: string, text: string, ?modal_load: bool, ?custom_onclick: string}> $options The available menu options
 */
function render_top_right_menu_btn(array $options): void
{
    if ($options === []) {
        return;
    }

    ?>
    <div class="top-right-menu" onclick="return showContextMenu(this);">
        <i class="icon-ellipsis-vertical"></i>
    </div>
    <?php
}

/**
 * Render a featured collection (category) contextual menu
 *
 * @param string $id The HTML identifier for the menu container.
 * @param list<array{href: string, icon: string, text: string, ?modal_load: bool, ?custom_onclick: string}> $options The available menu options
 */
function render_featured_collection_context_menu(string $id, array $options): void
{
    if ($options === []) {
        return;
    }

    ?>
    <div id="<?php echo escape($id); ?>" class="featured-collection context-menu-container" style="display:none;">
        <?php
        foreach ($options as $option) {
            if(isset($option['custom_onclick']) && trim($option['custom_onclick']) != '') {
                $onclick = "hideContextMenu(); {$option['custom_onclick']}";
            } else {
                $href = sanitise_url(isset($option['href']) && trim($option['href']) != '' ? $option['href'] : '#');
                $onclick = (isset($option['modal_load']) && $option['modal_load'])
                    ? "hideContextMenu(); return ModalLoad('{$href}', true);"
                    : "return CentralSpaceLoad('{$href}', true);";
            }
            ?>
            <button class="context-menu-row" onclick="<?php echo $onclick; ?>">
                <i class="<?php echo escape($option['icon']); ?>"></i>
                <span><?php echo escape($option['text']); ?></span>
            </button>
            <?php
        }
        ?>
    </div>
    <?php
}


/**
* Renders an option in the Permission Manager (admin_group_permissions.php page) 
* 
* @param string  $permission   Permission identifier
* @param string  $description  User friendly description of the permission
* @param boolean $reverse      Reverse the permission
* @param boolean $reload       deprecated - Autosave changes done on this permission
* @param boolean $disabled     Disable this permission as another supersedes it (greys it out and checks it)
*/
function DrawOption(string $permission, string $description, bool $reverse = false, bool $reload = false, bool $disabled = false): void
    {
    global $permissions,$permissions_done;

    $description_escaped = strip_paragraph_tags(strip_tags_and_attributes($description));
    
    $checked = in_array($permission, $permissions);
    if ($reverse)
        {
        $checked = !$checked;
        }
    
    $input_value = $reverse ? "reverse" : "normal";
    $base64_perm = base64_encode($permission);

    // Other attributes - note: a disabled input also gets checked automatically (some plugins do it)
    $disabled_attr = '';
    $onchange_attr = " onchange=SavePermissions(['$base64_perm']);";
    if ($disabled)
        {
        $checked = true;
        $disabled_attr = ' disabled';
        $onchange_attr = '';
        }
    $checked_attr = $checked ? ' checked' : '';
    ?>
    <input type="hidden" name="permission_<?php echo $base64_perm; ?>" value="<?php echo $input_value; ?>">
    <tr class="<?php echo count($permissions_done) % 2 == 0 ? 'tableRowEven' : 'tableRowOdd'; ?>">
    <td><?php if ($reverse) {?><i><?php } ?><?php echo escape($permission) ?><?php if ($reverse) {?></i><?php } ?></td>
        <td><?php echo $description_escaped; ?></td>
        <td>
            <input
                type="checkbox"
                name="checked_<?php echo $base64_perm; ?>"
                data-reverse="<?php echo (int) $reverse; ?>"
                <?php echo $disabled_attr . $checked_attr . $onchange_attr; ?>>
        </td>
    </tr>
    <?php
    $permissions_done[] = $permission;
    }


/**
* Render featured collections options in the Permission Manager (admin_group_permissions.php page)
* 
* This function will generate and render the following permissions that target featured collection categories
*   # j[numeric ID of new collection]  - valid for FC categories at root level. These are normal permissions.
*   # -j[numeric ID of new collection] - valid for the rest of FC sub-categories. These permissions are reversed, {@see DrawOption()}!
* 
* @param array $ctx Context data to allow caller code to start from different tree levels. Supports the following
*                   properties: parent and depth
* 
* @return void
*/
function render_featured_collections_category_permissions(array $ctx)
    {
    global $lang;

    $permissions = (isset($ctx["permissions"]) && is_array($ctx["permissions"]) ? $ctx["permissions"] : array());
    $parent = (isset($ctx["parent"]) ? validate_collection_parent(array("parent" => $ctx["parent"])) : 0);
    $path_depth = (isset($ctx["depth"]) ? $ctx["depth"] : 0);
    $branch_path = (isset($ctx["branch_path"]) && is_array($ctx["branch_path"]) ? $ctx["branch_path"] : array());

    $current_depth = $path_depth;
    $current_branch_path = $branch_path;
    $reverse_permission = ($parent > 0);

    foreach(get_featured_collection_categories($parent, array("access_control" => false)) as $fc)
        {
        $branch_path = $current_branch_path;
        $branch_path[] = array(
            "ref"    => $fc["ref"],
            "name"   => $fc["name"],
            "parent" => validate_collection_parent($fc),
        );

        $fc_perm_id = (!$reverse_permission ? "" : "-") . "j{$fc["ref"]}";
        $description = sprintf("%s%s '%s'",
            ($path_depth == 0 ? "" : str_pad("", $path_depth * 7, "&mdash;") . " "),
            (!$reverse_permission ? $lang["can_see_theme_category"] : $lang["can_see_theme_sub_category"]),
            i18n_get_translated($fc["name"])
        );
        DrawOption($fc_perm_id, $description, $reverse_permission, true);

        // Root categories (ie that don't have a parent) get rendered as normal permissions. Sub-categories, get rendered
        // as reverse permissions
        debug(sprintf("render_featured_collections_category_permissions: Check if allowed to render sub-categories for FC category '%s'", $fc['ref']));
        $render_subcategories = array_reduce($branch_path, function($carry, $item) use ($permissions)
            {
            $root_node = is_null($item["parent"]);
            $perm_id = ($root_node ? "" : "-") . "j{$item["ref"]}";
            $allow_render = ($root_node ? in_array($perm_id, $permissions) : !in_array($perm_id, $permissions));
            debug(sprintf("render_featured_collections_category_permissions: For perm ID '%s': carry = %s; root_node = %s; allow_render = %s", $perm_id, json_encode($carry), json_encode($root_node), json_encode($allow_render)));

            // FALSE if at least one featured collection category parent is forbidden
            return !is_bool($carry) ? $allow_render : $carry && $allow_render;
            }, null);
        debug("render_featured_collections_category_permissions: render_subcategories = " . json_encode($render_subcategories));
        debug("render_featured_collections_category_permissions: ");

        if($render_subcategories)
            {
            render_featured_collections_category_permissions(
                array(
                    "permissions" => $permissions,
                    "parent" => $fc["ref"],
                    "depth" => ++$path_depth,
                    "branch_path" => $branch_path,
                ));

            // Step back to initial depth level
            $path_depth = $current_depth;
            }
        }
    }

/**
 * show_upgrade_in_progress message
 *
 * @param  bool $dbstructonly - Indicates whether this is a full upgrade with migration scripts or just a check_db_structs()
 * @return void
 */
function show_upgrade_in_progress($dbstructonly=false)
    {
    global $lang;
    $title = (isset($lang["upgrade_in_progress"])?$lang["upgrade_in_progress"]:"Upgrade in progress");
    $message="This system is currently being upgraded by another process. Delete filestore/tmp/process_locks/* if this process has stalled." . PHP_EOL;
    if(!$dbstructonly)
        {
        $upgrade_progress_overall=get_sysvar(SYSVAR_UPGRADE_PROGRESS_OVERALL);
        $upgrade_progress_script=get_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT);
        $message.=($upgrade_progress_overall===false ? '' : $upgrade_progress_overall . PHP_EOL);
        $message.=($upgrade_progress_script===false ? '' : 'Script status: ' . $upgrade_progress_script . PHP_EOL);
        }
    if(PHP_SAPI == 'cli')
        {
        echo $message;
        }
    else
        {
        echo "<h1>{$title}</h1>";
        echo nl2br($message);
        ?>
        <script>
        setTimeout(function()
            {
            window.location.reload(true);
            }, 5000);
        </script>
        <?php
        }
    }



/**
*  add link to mp3 preview file if resource is a wav file
* 
* @param array      $resource               - resource data
* @param int        $ref                    - resource ref
* @param string     $k                      - url param key
* @param array      $ffmpeg_audio_extensions - config var containing a list of extensions which will be ported to mp3 format for preview      
* @param string     $baseurl                - config base url
* @param array      $lang                   - array containing language strings         
 * 
 */
function render_audio_download_link($resource, $ref, $k, $ffmpeg_audio_extensions, $baseurl, $lang)
{

// if resource is a .wav file and user has permissions to download then allow user also to download the mp3 preview file if available
// resources with extension in $ffmpeg_audio_extensions will always create an mp3 preview file 
    
    $path                       = get_resource_path($resource['ref'],true,"",false,"mp3");
    $resource_download_allowed  = resource_download_allowed($ref,'',$resource["resource_type"]);
    $size_info                  = array('id' => '', 'extension' => 'mp3');

    if (in_array($resource['file_extension'], $ffmpeg_audio_extensions) && file_exists($path) && $resource_download_allowed)
        {
        ?>
        <tr class="DownloadDBlend">
            <td class="DownloadFileName">
                <h2><?php echo escape($lang['mp3_preview_file']); ?></h2>
                <p><?php echo formatfilesize(filesize_unlimited($path)); ?></p>
            </td>
            <?php add_download_column($ref,$size_info, true); ?>
        </tr>
        <?php
        }   
}


/**
 * Render a table based on ResourceSpace data to include sorting by various columns
 *
 * @param  array $tabledata - This must be constructed as detailed below
 * 
 * Required elements:-
 * 
 * "class"  Optional class to add to table div
 * "headers"  - Column headings using the identifier as the index,
 *  - name - Title to display
 *  - Sortable - can column be sorted?
 *  - width - Optional column width
 * 
 * "orderbyname"    - name of variable used on page to determine orderby (used to differentiate from standard search values)
 * "orderby"        - Current order by value
 * "sortbyname"     - name of variable used on page to determine sort
 * "sort"           - Current sort
 * "defaulturl"     - Default URL to construct links
 * "modal"          - Open links in modal? (false by default)
 * "params"         - Current parameters to use in URL
 * "pager"          - Pager settings 
 *  - current page
 *  - total pages
 * "data"          - Array of data to display in table, using header identifers as indexes
 *  - If "rowid" is specified this will be used as the id attribute for the <tr> element
 *  - The "alerticon" can be used to specify a CSS class to use for a row status icon
 *  - An additional 'tools' element can be included to add custom action icons
 *  - "icon" - Lucide class to use for icon
 *  - "text" - title attribute
 *  - "url" - URl to link to
 *  - "url:class" - The styling classes for the URL. Type: string. This tool property is optional.
 *  - "modal" - (boolean) Open link in modal?
 *  - "onclick" - OnClick action to add to icon
 *  
 *   e.g.
 * 
 *   array(
 *       "icon"=>"icon-trash-2",
 *       "text"=>$lang["action-delete"],
 *       "url"=>"",
 *       "modal"=>false,
 *       "onclick"=>"delete_job(" . $jobs[$n]["ref"] . ");return false;"
 *       );
 *
 *   array(
 *       "icon"=>"icon-info",
 *       "text"=>$lang["job_details"],
 *       "url"=>generateURL($baseurl . "/pages/job_details.php",array("job" => $jobs[$n]["ref"])),
 *       "modal"=>true,
 *       );
 * 
 * @return void
 */
function render_table($tabledata)
    {
    global $list_display_array, $lang, $default_perpage_list;
    $modal = isset($tabledata["modal"]) && $tabledata["modal"];
    $alertcolumn = count(array_column($tabledata["data"],'alerticon')) > 0;
    if(isset($tabledata["pager"]))
        {          
        $pageroptions = array(
            "curpage" => $tabledata["pager"]["current"],
            "totalpages" => $tabledata["pager"]["total"],
            "per_page" => isset($tabledata["pager"]["per_page"]) ? $tabledata["pager"]["per_page"] : $default_perpage_list,
            "break" => isset($tabledata["pager"]["break"]) ? $tabledata["pager"]["break"] : true,
            "scrolltotop" => isset($tabledata["pager"]["scrolltotop"]) ? $tabledata["pager"]["scrolltotop"] : true,
            "url" => $tabledata["defaulturl"],
            "url_params" => $tabledata["params"],
            );
        echo '<div class="TopInpageNav TableNav">';
        echo '<div class="InpageNavLeftBlock">' . escape($lang["resultsdisplay"]) . ': ';
        // Show per page options
        $list_display_array["all"] = 99999;
        $pplinks = array();
        foreach($list_display_array as $ldopt => $ldnum)
            {
            $lpp_name = isset($lang[$ldopt]) ? $lang[$ldopt] : $ldnum;
            if ($pageroptions["per_page"] == $ldnum)
                {
                $pplinks[] =  "<span class='Selected'>" . escape($lpp_name) . "</span>";
                }
            else
                {
                if(isset($tabledata["params"]["search_go"]))
                    {
                    $tabledata["params"]["search_go"] = "";
                    }
                $perpageurl = generateURL($pageroptions["url"],$tabledata["params"], array("per_page"=>$ldnum));
                $pplinks[] = "<a onclick='return " . ($modal ? "Modal" : "CentralSpace") . "Load(this, true);' href='" . 
                $perpageurl . "'>" . $lpp_name . "</a>";
                }
            }
        echo implode("&nbsp;|&nbsp;", $pplinks);
        echo "</div> <!-- End of InpageNavLeftBlock per page div -->";
        echo "<div class='TablePagerHolder'>";
        pager(false, true,$pageroptions);
        echo "</div>";
        }
    echo '</div>';
    echo "<div class='Listview " . (isset($tabledata["class"]) ? escape($tabledata["class"]) : "") . "'>\n";
    echo "<table border='0' cellspacing='0' cellpadding='0' class='ListviewStyle'>\n";
    echo "<tbody><tr class='ListviewTitleStyle'>\n";
    if($alertcolumn)
        {
        echo "<th id='RowAlertStatus' style='width: 10px;'></th>";
        }
    foreach($tabledata["headers"] as $header=>$headerdetails)
        {
        echo "<th " . ($headerdetails["name"]==$lang["tools"] ? "class='ListTools'" : "") . (isset($headerdetails["width"]) ? ("style='width:" . escape($headerdetails["width"]) . "'") : "") . ">";
        if($headerdetails["sortable"])
            {
            $revsort = ($tabledata["sort"]=="ASC") ? "DESC" : "ASC";
            echo "<a href='" . generateURL($tabledata["defaulturl"],$tabledata["params"],array($tabledata["orderbyname"]=>$header,$tabledata["sortname"]=>($tabledata["orderby"] == $header ? $revsort : $tabledata["sort"]))) . "' onclick='return " . ($modal ? "Modal" : "CentralSpace") . "Load(this, true);'>" . escape($headerdetails["name"]);
            if($tabledata["orderby"] == $header)
                {
                // Currently sorted by this column
                echo "<span class='" . $revsort . "'></span>";
                }
            echo "</a>";
            }
        else
            {
            echo escape($headerdetails["name"]);
            }
        
        
        echo "</th>";
        }
    echo "</tr>\n"; // End of table header row

    if(count($tabledata["data"]) == 0)
        {
        echo "<tr><td colspan='" . (strval(count($tabledata["headers"]))) . "'>" . escape($lang["no_results_found"]) . "</td></tr>\n";
        }
    else
        {
        foreach($tabledata["data"] as $rowdata)
            {
            $rowid = isset($rowdata["rowid"]) ? " id='" . escape($rowdata["rowid"])  . "'" : "";
            if(isset($rowdata["rowlink"]))
                {
                $rowid .=  " class='row_clickable' data-link='" . escape($rowdata["rowlink"]) . "'";
                }
            echo "<tr" . $rowid . ">";

            if(isset($rowdata['alerticon']))
                {
                echo "<td><i class='" . escape($rowdata['alerticon']) . "' title = '" . escape($rowdata['alerticontitle'] ?? "") . "'></i></td>";
                }
            elseif($alertcolumn)
                {
                echo "<td></td>";
                }
            foreach($tabledata["headers"] as $header=>$headerdetails)
                {
                if(isset($rowdata[$header]))
                    {
                    echo "<td>";
                    // Data is present
                    if($header == "tools")
                        {
                        echo "<div class='ListTools'>";
                        foreach($rowdata["tools"] as $toolitem)
                            {
                            echo "<a aria-hidden='true' href='" . escape($toolitem["url"]) . "' class=\"" . escape($toolitem['url:class'] ?? '') . "\" onclick='";
                            if(isset($toolitem["onclick"]))
                                {
                                echo escape($toolitem["onclick"]);
                                }
                            else
                                {
                                echo "return " . ($toolitem["modal"] ? "Modal" : "return CentralSpace") . "Load(this,true);";
                                }
                            echo "' title='" . escape($toolitem["text"]) . "'><i class='" . escape($toolitem["icon"]) . "'></i>&nbsp;" . escape($toolitem["text"]) . "</a>";
                            }
                        echo "</div>";
                        }
                    else
                        {
                        echo (isset($headerdetails["html"]) && (bool)$headerdetails["html"]) 
                                ? strip_tags_and_attributes($rowdata[$header], array("a","input"), array("href", "target", "type", "class", "onclick", 'name', 'value', 'title')) 
                                : escape($rowdata[$header]);
                        }
                    echo "</td>";
                    }
                else
                    {
                    echo "<td></td>\n";
                    }
                }
            echo "</tr>\n";
            }
        }
    echo '</tbody>
    </table>
    </div>
    
    <script>
    jQuery(document).ready(function()
        {
        jQuery(".row_clickable").click(function (e, row, $element)
            {
            return ModalLoad(jQuery(this).data(\'link\'), true);
            });
        });
    </script>';
    }

/**
 * Render multimensional array or object to display within table cells
 *
 * @param  array $array
 * @return void
 */
function render_array_in_table_cells($array)
    {
    foreach($array as $name => $value)
        {
        echo '<table class="TableArray">';
        echo "<tr><td width='50%'>";
        echo escape($name);
        echo "</td><td width='50%'>";

        if(is_array($value))
            {
            render_array_in_table_cells($value);
            }
        elseif(is_bool($value))
            {
            echo $value ? "TRUE" : "FALSE";
            }
        else
            {
            echo escape((string)$value);
            }
                
        echo "</td></tr>";
        echo "</table>";
        }
    }

/**
* Render the top page error style version
* 
* @param string $err_msg Error message
* 
* @return void
*/
function render_top_page_error_style(string $err_msg)
    {
    if(trim($err_msg) === '')
        {
        return;
        }

    ?><div class="PageInformal"><?php echo escape($err_msg); ?></div><?php
    }


/**
* Render a FormHelper. These are used in forms, to provide extra information to the user to a question.
* 
* @param string $txt Help text
* @param string $id  Div ID
* @param array  $ctx Contextual data
*/
function render_question_form_helper(string $txt, string $id, array $ctx)
    {
    $txt = trim($txt);
    $id = trim($id);

    if($txt === '' || $id === '')
        {
        return;
        }

    $ctx_class = (isset($ctx['class']) && is_array($ctx['class']) ? $ctx['class'] : array());
    $ctx_style = (isset($ctx['style']) && is_string($ctx['style']) ? $ctx['style'] : ''); # Use a class if possible!


    $class = escape(join(' ', array_merge(array('FormHelp'), $ctx_class)));
    $style = (trim($ctx_style) !== '' ? sprintf(' style="%s"', escape($ctx_style)) : '');
    ?>
    <div id="help_<?php echo escape($id); ?>" class="<?php echo $class; ?>"<?php echo $style; ?>>
        <div class="FormHelpInner"><?php echo escape($txt); ?></div>
    </div>
    <?php
    }


/**
* Render an HTML hidden input
* 
* @param string $name  Input name
* @param string $value Input value
*/
function render_hidden_input(string $name, string $value)
    {
    ?>
    <input type="hidden" name="<?php echo escape($name); ?>" value="<?php echo escape($value); ?>">
    <?php
    }

/**
 * Renders a dropdown for selecting the workflow state of a resource.
 *
 * This function generates a dropdown menu with various workflow states, 
 * allowing the user to select the current state of the resource. 
 * The available options are based on permission checks and additional states defined globally.
 *
 * @param int|null $current The currently selected state, if any.
 * @param bool $checkaccess Indicates whether to check user permissions for each state.
 */
function render_workflow_state_question($current=null, $checkaccess=true)
    {
    global $additional_archive_states, $lang;
    $statusoptions = array();
    for ($n=-2;$n<=3;$n++)
        {
        if (!$checkaccess || checkperm("e" . $n) || $n==$current)
            {
            $statusoptions[$n] =  isset($lang["status" . $n]) ?  $lang["status" . $n] : $n;
            }
        }
    foreach ($additional_archive_states as $additional_archive_state)
        {
        if (!$checkaccess || checkperm("e" . $additional_archive_state) || $additional_archive_state==$current)
            {
            $statusoptions[$additional_archive_state] =  isset($lang["status" . $additional_archive_state]) ?  $lang["status" . $additional_archive_state] : $additional_archive_state;
            }
        }
    
    render_dropdown_question($lang["status"], "share_status", $statusoptions, $current, " class=\"stdWidth\"");
    }

/**
 * Renders a question field for setting a share password.
 *
 * This function generates an HTML input field where users can set or change 
 * the password for shared access to a resource. It includes functionality to 
 * toggle password visibility and indicates if the password is required.
 *
 * @param bool $blank Indicates if the password field should be left blank (default is true).
 */
function render_share_password_question($blank=true)
    {
    global $lang, $share_password_required; 
    ?>
    <div class="Question">
    <label for="sharepassword"><?php echo strip_tags_and_attributes($lang["share-set-password"]); echo $share_password_required ? '<sup>*</sup>' : ''; ?></label>
    <input type="password" <?php echo $share_password_required ? 'required' : ''; ?> id="sharepassword" name="sharepassword" autocomplete="new-password" maxlength="40" class="stdwidth" value="<?php echo $blank ? "" : escape($lang["password_unchanged"]); ?>">
    <span class="icon-eye-off infield-icon" id="share-password-icon" onclick="togglePassword('sharepassword');"></span>
    <script>

    function togglePassword(pwdelement)
        {
        input = jQuery('#' + pwdelement);
        if (input.attr("type") == "password")
            {
            input.attr("type", "text");
            jQuery('#share-password-icon').removeClass('icon-eye-off').addClass('icon-eye');
            }
        else
            {
            input.attr("type", "password");
            jQuery('#share-password-icon').removeClass('icon-eye').addClass('icon-eye-off');
            }
        }
    var passInput="";
    var passState="(unchanged)";
    var passHistory="";
    function pclick(id) 
        {
        // Set to password mode
        document.getElementById(id).type="password";
        document.getElementById(id).value=passState;
        document.getElementById(id).select();
        }
    function pblur(id) 
        {
        // Copy keyed input other than bracketed placeholders to hidden password
        passInput = document.getElementById(id).value;
        if(passInput!="(unchanged)" && passInput!="(changed)") 
            {
            document.getElementById("sharepassword").value=passInput; 
            passState="(changed)";
            }
        // Return to text mode showing the appropriate bracketed placeholder
        document.getElementById(id).value=passState;
        document.getElementById(id).type="text";
        }
    </script>
    </div>
    <?php
    }

    
/**
 * Get required rows and columns for use when displaying radio buttons in a table 
 *
 * @param  array $options   Array of text options
 * @return array            (Number of rows, number of columns)
 */
function radio_get_layout($options)
    {
    $l = average_length($options);
    
    $cols = 10;
    if($l > 5) 
        {
        $cols = 6;
        }

    if($l > 10)
        {
        $cols = 4;
        }

    if($l > 15)
        {
        $cols = 3;
        }

    if($l > 25)
        {
        $cols = 2;
        }

    $rows = ceil(count($options) / $cols);
    return array($rows, $cols);
    }


/**
* render_radio_buttons_question - Used to display a question with radio buttons
* 
* @param string $label     Label of question
* @param string $inputname Name of input field
* @param array  $options   Array of options (value and text pairs) (eg. array('pixelwidthmin'=>'From','pixelwidthmin'=>'To')
* @param string $current   The current selected value
* @param string $extra     Extra attributes used on the selector element
* @param bool   $listview  Show as vertical list? (false for table view)
* @param array  $ctx       Rendering context. Should be used to inject different elements (e.g set the div class, add onclick for select)
* 
* @return void
*/
function render_radio_buttons_question($label, $inputname, $options = array(), $current="", $extra="", $listview=false, array $ctx = array())
    {
    $div_class = array("Question");
    if(isset($ctx["div_class"]) && is_array($ctx["div_class"]) && !empty($ctx["div_class"]))
        {
        $div_class = array_merge($div_class, $ctx["div_class"]);
        }

    $onchange = (isset($ctx["onchange"]) && trim($ctx["onchange"]) != "" ? trim($ctx["onchange"]) : "");
    $onchange = ($onchange != "" ? sprintf("onchange=\"%s\"", $onchange) : "");

    $extra .= " {$onchange}";

    list($rows,$cols) = radio_get_layout(array_values($options));
    if($listview)
        {
        $cols=1;
        }
    ?>
    <div class="<?php echo implode(" ", $div_class); ?>">
        <label><?php echo $label; ?></label>
        
        <table id="<?php echo $inputname  . "_radio_table"; ?>" class="radioOptionTable" cellpadding="3" cellspacing="3">                    
            <tbody>
                <tr>
                <?php 
                $row = 1;
                $col = 1;

                foreach($options as $optionvalue=>$optiontext)
                    {
                    if($col > $cols) 
                        {
                        $col = 1;
                        $row++; ?>
                        </tr>
                        <tr>
                        <?php 
                        }
                    $col++;
                    ?>
                    <td width="10" valign="middle">
                        <input type="radio"
                            id="radio_<?php echo escape($optionvalue); ?>"
                            name="<?php echo $inputname; ?>"
                            value="<?php echo escape($optionvalue); ?>"
                        <?php
                        if($current == $optionvalue)
                                {
                                ?>
                                checked
                                <?php
                                }?>>
                    </td>
                    <td align="left" valign="middle">
                        <label class="customFieldLabel"
                            for="radio_<?php echo escape($optionvalue); ?>"
                            ><?php echo escape($optiontext); ?></label>
                    </td>
                    <?php 
                    } 
                    ?>
                </tr>
            </tbody>
        </table>
        <div class="clearerleft"></div>
    </div>
    <?php
    }
/**
 * Render a user message for use in conversation view
 *
 * @param array $message  Message data from message_get_conversation()
 * @return void
 */
function render_message($message="")
    {
    global $userref;
    $msgdata = array();
    if($message == "")
        {
        // Template
        $msgdata[] = "%%CLASSES%%"; // %%CLASSES%%
        $msgdata[] = ""; // EXTRA 
        $msgdata[] = "%%PROFILEIMAGE%%";  // %%PROFILEIMAGE%%
        $msgdata[] = "%%MESSAGE%%"; // %%MESSAGE%%
        }
    else
        {
        $udata = get_user($message["owner"]);
        if($udata["ref"] == $userref)
            {
            $msgdata[] = "user_message own_message"; // %%CLASSES%%
            }
        else
            {
            $msgdata[] = "user_message"; // %%CLASSES%%
            }
        $sendername = isset($udata["fullname"]) && trim($udata["fullname"]) != "" ? $udata["fullname"] : $udata["username"];

        $msgdata[] = ""; // EXTRA 

        $pimage = get_profile_image($message["owner"]);
        if($pimage == "")
            {
            $msgdata[] = "<i title='" . escape($sendername) . "' aria-hidden='true' class='icon-user lucide-lg ProfileImage'></i>";  // %%PROFILEIMAGE%%
            }
        else
            {
            $msgdata[] = "<img title='" . escape($sendername) . "' alt='" . escape($sendername) . "' class='ProfileImage' src='" . $pimage . "'>";  // %%PROFILEIMAGE%%
            }  
        $msgdata[] = escape($message["message"]);  // %%MESSAGE%%     
        }

    $messagehtml = "<div class='%%CLASSES%%' %%EXTRA%%>
        <div class='message_content'>
            <div class='profileimage'>
            %%PROFILEIMAGE%%
            </div>
            <div class='user_message_text'>%%MESSAGE%%</div>
        </div>
        <div class='clearerleft'></div>
    </div>";


    echo str_replace(array("%%CLASSES%%","%%EXTRA%%","%%PROFILEIMAGE%%","%%MESSAGE%%"),$msgdata,$messagehtml);
    }


/**
 * Render the antispam Question form section
 */
function render_antispam_question()
    {
    global $scramble_key, $lang;

    $rndword = array_merge(range('0', '9'), range('A', 'Z'));
    shuffle($rndword);
    $timestamp=time();
    $rndwordarray=  array_slice ($rndword , 0,6);
    $rndcode= hash("SHA256",implode("",$rndwordarray) .  $scramble_key . $timestamp);
    $height = 50; //CAPTCHA image height
    $width = 160; //CAPTCHA image width
    $font_size = 25; //CAPTCHA Font size
    $font=__DIR__. "/../gfx/fonts/vera.ttf";

    $capimage = imagecreate($width, $height);
    imagecolorallocate($capimage, 245, 245, 245);
    $textcolor = imagecolorallocate($capimage, 34, 34, 34);
    $green = ImageColorAllocate($capimage, 121, 188, 65);

    ImageRectangle($capimage,0,0,$width-1,$height-1,$green); 
    imageline($capimage, 0, $height/2, $width, $height/2, $green); 
    imageline($capimage, $width*4/5, 2, $width*4/5, $height, $green);
    imageline($capimage, $width*3/5, 2, $width*3/5, $height, $green);
    imageline($capimage, $width*2/5, 2, $width*2/5, $height, $green);
    imageline($capimage, $width/5, 2, $width/5, $height, $green);
    
    $n=0;
    foreach($rndwordarray as $rndletter)
        {
        imagefttext($capimage, $font_size,rand(-20, 20), 10 + (24*$n), rand(25, 45), $textcolor, $font, $rndletter);
        $n++;
        }
        
    ob_start();
    imagegif($capimage);
    $imagedata = ob_get_contents();
    ob_end_clean();
    ?>
    <div class="field-input">
        <input type="hidden" name="antispamcode" value="<?php echo $rndcode; ?>">
        <input type="hidden" name="antispamtime" value="<?php echo $timestamp; ?>">
        <label for="antispam"><?php echo escape($lang["antispam"]); ?><br></label> 
        <div class="field-hint-text"><?php echo escape($lang["enterantispamcode"]); ?></div>
        <div 
            id="AntiSpamImage"
            title="Anti-spam image"
            style="
                margin: 4px 0 0 0;
                background: url(data:image/gif;base64,<?php echo base64_encode($imagedata); ?>) top left no-repeat;
                height: <?php echo $height; ?>px;
                width: <?php echo $width; ?>px;
                display: inline-block;
        ">
        </div>
        <input type="text" name="antispam_user_code" value="">
        <input type=text name="antispam" id="antispam" value="" required>     
    </div>
    <?php
    }

/**
 * Renders a 'fixed' text question - not an input but to display information or values that cannot be changed
 *
 * @param  string $label        Question label
 * @param  string $text         Fixed text
 * @param  string $helptext     Optional help text
 * @param  string $helptext     Optional div identifier
 *
 */
function render_fixed_text_question(string $label, string $text, string $helptext = "", string $divid = ""): void
{
    $fixed_html = '
        <div class="Question" %identifier>
            <label>%label</label>
            <div class="Fixed">%text</div>
            <div class="clearerleft"></div>
            %helptextdiv
        </div>';

    $help_html = '
        <div class="FormHelp" style="padding:0; clear:left;" >
            <div class="FormHelpInner">%helptext</div>
        </div>';

    $replace = [
        "%identifier" => (trim($divid) != '' ? ' id="' . escape($divid) . '"' : ''),
        "%label" => escape($label),
        "%text" => escape($text),
        "%helptextdiv" => (trim($helptext) != "" ? str_replace("%helptext", escape($helptext), $help_html) : ""),
    ];

     echo str_replace(array_keys($replace),$replace,$fixed_html);
}


/**
 * Output encoding for HTML context when unsafe input is rendered inside it
 * 
 * @return string
 */
function escape(string $unsafe): string
{
    return htmlspecialchars($unsafe, ENT_QUOTES | ENT_SUBSTITUTE);
}


/**
 * HTML aware function to break up long words onto multiple lines for
 * PDF generation where CSS text wrapping can't be used
 * 
 * @param  string $html     HTML/text to be processed
 * @param  int    $length   Maximum word length
 * @return string 
 * 
 */
function html_break_long_words(string $html, int $length): string {
    
    // Regex to match tags and text separately
    preg_match_all('/<[^>]+>|[^<]+/', $html, $matches);

    $output = '';

    foreach ($matches[0] as $part) {
        if ($part[0] === '<') {
            // If it's an HTML tag, add it to the output as-is
            $output .= $part;
        } else {
            // If it's plain text, process it for long words
            $words = preg_split('/(\s+)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($words as $word) {
                if (strlen($word) > $length) {
                    // Insert <br> tags between chunks only
                    $word = wordwrap($word, $length, '<br>', true);
                }
                $output .= $word;
            }
        }
    }

    return $output;
}


/**
 * Renders a Lucide icon selector question
 * Requires lib/lucide/icon_classes.php to be included in the page using the function
 *
 * @param  string $label
 * @param  string $name     Input name
 * @param  string $current  Current value
 * 
 * @return void
 */
function render_lucide_icon_selector(string $label="",string $name="icon",string $current="")
{
    global $lang, $lucide_icons;

    if (trim($label) == "") {
        $label = $lang["property-icon"];
    }

    ?>
    <div class="Question">
        <label><?php echo escape($label) ?></label>
        <?php $blank_icon = ($current == "" || !in_array($current, $lucide_icons)); ?>
        <div id="iconpicker-question">
            <input name="<?php echo escape($name) ?>" type="text" id="iconpicker-input" value="<?php echo escape($current)?>" /><span id="iconpicker-button"><i class="icon-<?php echo $blank_icon ? 'chevron-down' : escape($current)?>" id="iconpicker-button-lucide"></i></span>
        </div>
        <div id="iconpicker-container">
            <div class="iconpicker-title">
                <input type="text" id="iconpicker-filter" placeholder="<?php echo escape($lang['icon_picker_placeholder']) ?>" onkeyup="filterIcons()">
            </div>
            <div class="iconpicker-content">
                <?php foreach ($lucide_icons as $icon_name)
                    {
                    ?>
                    <div class="iconpicker-content-icon" data-icon="<?php echo escape(trim($icon_name)) ?>" title="<?php echo escape(trim($icon_name)) ?>">
                        <i class="icon-<?php echo escape(trim($icon_name)) ?>"></i>
                    </div>
                    <?php
                    } ?>
            </div>
        </div>
        <div class="clearerleft"> </div>
    </div>

    <script type="text/javascript">

    jQuery("#iconpicker-button").click(function() {
        jQuery("#iconpicker-container").toggle();
    });

    jQuery("#iconpicker-input").focus(function() {
        jQuery("#iconpicker-container").show();
    });

    jQuery(".iconpicker-content-icon").click(function() {
        var icon_name = jQuery(this).data("icon");
        jQuery("#iconpicker-input").val(icon_name);
        jQuery("#iconpicker-button i").attr("class","icon-" + icon_name);
    });

    jQuery(document).mouseup(function(e) {
        var container = jQuery("#iconpicker-container");
        var question = jQuery("#iconpicker-question");

        if (!container.is(e.target) && container.has(e.target).length === 0
            && !question.is(e.target) && question.has(e.target).length === 0) {
            container.hide();
        }
    });

    function filterIcons() {
        filter_text = document.getElementById("iconpicker-filter");
        var filter_upper = filter_text.value.toLowerCase();

        container = document.getElementById("iconpicker-container");
        icon_divs = container.getElementsByClassName("iconpicker-content-icon");

        for (i = 0; i < icon_divs.length; i++) {
            icon_short_name = icon_divs[i].getAttribute("data-icon");
            if (icon_short_name.toLowerCase().indexOf(filter_upper) > -1) {
                icon_divs[i].style.display = "inline-block";
            } else {
                icon_divs[i].style.display = "none";
            }
        }
    }

    </script>
    <?php
    }


/**
 * Render all related resources on view page
 *
 * @param array $context Array with all required info from the view page
 *
 * @return void
 *
 */
function display_related_resources($context)
{
    $ref                        =  $context["ref"] ?? 0;
    $k                          =  $context["k"] ?? "";
    $userref                    =  $context["userref"] ?? 0;
    $arr_related                =  $context["relatedresources"] ?? [];
    $internal_share_access      =  $context["internal_share_access"] ?? false;
    $related_restypes           =  $context["related_restypes"] ?? [];
    $relatedtypes_shown         =  $context["relatedtypes_shown"] ?? [];
    $edit_access                =  $context["edit_access"] ?? false;
    $urlparams                  =  $context["urlparams"] ?? ["ref" => $ref];

    global $baseurl, $baseurl_short, $lang, $view_title_field, $sort_relations_by_filetype, $related_resources_title_trim, $sort_relations_by_restype, $metadata_template_title_field, $metadata_template_resource_type;

    $allrestypes = get_resource_types();
    $allrestypes_names = array_column($allrestypes, 'name', 'ref');

    if ($ref == 0 || count(array_diff(array_column($allrestypes, "ref"), $relatedtypes_shown)) == 0) {
        return;
    }

    $use_watermark = check_use_watermark();
    ?>
    <!--Display panel for related resources-->
    <div class="RecordBox">
        <div class="RecordPanel">
            <div id="RelatedResources">
                <div class="RecordResource">
                    <div class="Title"><?php echo escape($lang["relatedresources"]); ?></div>
                    <?php
                    if (
                        checkperm("s")
                        && ($k == "" || $internal_share_access)
                    ) {
                        if (count(array_diff(array_column($arr_related, "resource_type"), $relatedtypes_shown)) > 0) {
                            ?>
                            <a href="<?php echo generateURL($baseurl . '/pages/search.php', ['search' => "!related$ref", 'restypes' => '']); ?>"
                                onClick="return CentralSpaceLoad(this,true);">
                                <?php echo LINK_CARET . escape($lang["clicktoviewasresultset"]); ?>
                            </a>
                            <div class="clearerleft"></div>
                            <?php
                        }
                        if ($sort_relations_by_filetype) {
                            // Sort by file extension
                            $related_file_extensions = array_unique(array_column($arr_related, "file_extension"));
                            foreach ($related_file_extensions as $rext) {
                                ?>
                                <h4><?php echo escape((string) $rext); ?></h4>
                                <?php
                                // loop and display the results by file extension
                                for ($n = 0; $n < count($arr_related); $n++) {
                                    if (in_array($arr_related[$n]["resource_type"], $relatedtypes_shown)) {
                                        // Don't show this type again.
                                        continue;
                                    }
                                    if ($arr_related[$n]["file_extension"] == $rext) {
                                        $rref = $arr_related[$n]["ref"];
                                        $title = $arr_related[$n]["field" . $view_title_field];
                                        $access = get_resource_access($rref);
                                        $safeviewlink = generateURL(
                                            $baseurl . "/pages/view.php",
                                            ["ref"  => (int) $rref, "search" => "!related" . $ref]
                                        );
                                        // Swap title fields if necessary
                                        if (
                                            isset($metadata_template_title_field)
                                            && isset($metadata_template_resource_type)
                                            && $arr_related[$n]['resource_type'] == $metadata_template_resource_type
                                        ) {
                                            $title = $arr_related[$n]["field" . $metadata_template_title_field];
                                        }
                                        ?>
                                        <div class="CollectionPanelShell">
                                            <table border="0" class="CollectionResourceAlign">
                                                <tr>
                                                    <td>
                                                        <a href="<?php echo $safeviewlink; ?>"
                                                            onClick="return CentralSpaceLoad(this,true);">
                                                            <?php
                                                            $thumbnail = get_resource_preview($arr_related[$n], ["col"], $access, $use_watermark);
                                                            if ($thumbnail !== false) {
                                                                render_resource_image($arr_related[$n], $thumbnail["url"], "collection");
                                                            } else {
                                                                echo get_nopreview_html((string) $arr_related[$n]["file_extension"], $arr_related[$n]['resource_type']);
                                                            } ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        <div class="CollectionPanelInfo">
                                            <a href="<?php echo $safeviewlink; ?>"
                                                onClick="return CentralSpaceLoad(this,true);">
                                                <?php echo escape(tidy_trim(i18n_get_translated($title), $related_resources_title_trim)); ?>
                                            </a>&nbsp;
                                        </div>
                                        <?php hook("relatedresourceaddlink"); ?>
                                        </div>
                                        <?php
                                    }
                                }
                                ?>
                                <div class="clearerleft"> </div>
                                <?php
                            } // End of display loop by resource extension
                        // End of IF sorted relations by extension
                        } elseif ($sort_relations_by_restype) {
                            foreach ($related_restypes as $rtype) {
                                if (in_array($rtype, $relatedtypes_shown)) {
                                    // Don't show this type again.
                                    continue;
                                }
                                ?>
                                <h4><?php echo escape($allrestypes_names[$rtype]); ?></h4>
                                <?php
                                // Loop and display the results by file extension
                                for ($n = 0; $n < count($arr_related); $n++) {
                                    if ($arr_related[$n]["resource_type"] == $rtype) {
                                        $rref = $arr_related[$n]["ref"];
                                        $title = $arr_related[$n]["field" . $view_title_field];
                                        $access = get_resource_access($rref);
                                        $safeviewlink = generateURL(
                                            $baseurl . "/pages/view.php",
                                            ["ref"  => (int) $rref, "search" => "!related" . $ref]
                                        );
                                        // Swap title fields if necessary
                                        if (
                                            isset($metadata_template_title_field)
                                            && isset($metadata_template_resource_type)
                                            && $arr_related[$n]['resource_type'] == $metadata_template_resource_type
                                        ) {
                                            $title = $arr_related[$n]["field" . $metadata_template_title_field];
                                        }
                                        ?>
                                        <!--Resource Panel-->
                                        <div class="CollectionPanelShell">
                                            <table border="0" class="CollectionResourceAlign">
                                                <tr>
                                                    <td>
                                                        <a href="<?php echo $safeviewlink; ?>"
                                                            onClick="return CentralSpaceLoad(this,true);">
                                                            <?php
                                                            $thumbnail = get_resource_preview($arr_related[$n], ["col"], $access, $use_watermark);
                                                            if ($thumbnail !== false) {
                                                                render_resource_image($arr_related[$n], $thumbnail["url"], "collection");
                                                            } else {
                                                                echo get_nopreview_html((string) $arr_related[$n]["file_extension"], $arr_related[$n]['resource_type']);
                                                            } ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        <div class="CollectionPanelInfo">
                                            <a href="<?php echo $safeviewlink; ?>" onClick="return CentralSpaceLoad(this,true);">
                                                <?php echo escape(tidy_trim(i18n_get_translated($title), $related_resources_title_trim)); ?>
                                            </a>&nbsp;
                                        </div>
                                        <?php hook("relatedresourceaddlink"); ?>
                                        </div>
                                        <?php
                                    } // End of this resource type
                                } // End of $arr_related
                                ?>
                                <div class="clearerleft"> </div>
                                <?php
                            } // End of display loop by resource extension
                        // End of IF sorted relations
                        } else {
                            // Related resources (default view)
                            for ($n = 0; $n < count($arr_related); $n++) {
                                if (in_array($arr_related[$n]["resource_type"], $relatedtypes_shown)) {
                                    // Don't show this type again.
                                    continue;
                                }
                                $rref = $arr_related[$n]["ref"];
                                $title = $arr_related[$n]["field" . $view_title_field];
                                $access = get_resource_access($rref);
                                $safeviewlink = generateURL(
                                    $baseurl . "/pages/view.php",
                                    ["ref"  => (int) $rref, "search" => "!related" . $ref]
                                );
                                // Swap title fields if necessary
                                if (
                                    isset($metadata_template_title_field)
                                    && isset($metadata_template_resource_type)
                                    && $arr_related[$n]["resource_type"] == $metadata_template_resource_type
                                ) {
                                    $title = $arr_related[$n]["field" . $metadata_template_title_field];
                                }
                                ?>
                                <div class="CollectionPanelShell">
                                    <table border="0" class="CollectionResourceAlign">
                                        <tr>
                                            <td>
                                                <a href="<?php echo $safeviewlink; ?>"
                                                    onClick="return CentralSpaceLoad(this,true);">
                                                    <?php
                                                    $thumbnail = get_resource_preview($arr_related[$n], ["col"], $access, $use_watermark);
                                                    if ($thumbnail !== false) {
                                                        render_resource_image($arr_related[$n], $thumbnail["url"], "collection");
                                                    } else {
                                                        echo get_nopreview_html((string) $arr_related[$n]["file_extension"], $arr_related[$n]['resource_type']);
                                                    }
                                                    ?>
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <div class="CollectionPanelInfo">
                                        <a href="<?php echo $safeviewlink; ?>" onClick="return CentralSpaceLoad(this,true);">
                                            <?php echo escape(tidy_trim(i18n_get_translated($title), $related_resources_title_trim)); ?>
                                        </a>&nbsp;
                                    </div>
                                    <?php hook("relatedresourceaddlink"); ?>
                                </div>
                            <?php
                            } // End of $arr_related
                        } // End related resources default display
                    } // End of if any related resources exist
                    ?>
                </div><!-- End of RecordResource -->
                <?php if ($edit_access) {
                    // Add link to create new related resource and view as result set
                    $add_related_params = [
                        "ref" => -$userref,
                        "relateto" => $ref,
                        "noupload" => "true",
                        "recordonly" => "true",
                        "collection_add" => "false",
                        "redirecturl" => generateURL($baseurl . "/pages/view.php", $urlparams),
                        ];
                    $addrelated_url = generateURL($baseurl_short . "pages/edit.php", $add_related_params);
                    ?>
                    <div class="clearerleft"></div>
                    <a href="<?php echo $addrelated_url; ?>"
                        onclick="return CentralSpaceLoad(this, true);">
                        <?php echo LINK_PLUS  . escape($lang['related_resource_create']); ?>
                    </a>
                    <?php
                }?>
            </div><!-- End of RelatedResources -->
        </div><!-- End of RecordPanel -->
        </div><!-- End of RecordBox -->
    <?php
}

/**
 * Display appropriate field constraint for use on admin_resource_type_field_edit.php e.g. single select/Number
 *
 * @param int   Metadata field ID
 * @param int   Current field type    
 * 
 * @return void
 * 
 */
function admin_resource_type_field_constraint(int $ref, int $currentvalue): void
    {
    global $lang;
    $constraint=ps_value("SELECT field_constraint value FROM resource_type_field WHERE ref=?",array("i",$ref),0, "schema");
    ?>
        <div class="clearerleft"></div>
    </div> <!-- end question -->
    <div class="Question">
        <label><?php  echo escape($lang["property-field_constraint"]) ?></label>
        <select id="field_constraint" name="field_constraint" class="stdwidth" onchange="CentralSpacePost(this.form);">

            <option value="0" <?php if ($constraint==0) { echo " selected"; } ?>><?php echo escape($lang["property-field_constraint-none"]) ?></option>
            <option value="1" <?php if ($constraint==1) { echo " selected"; } ?>><?php echo escape(($currentvalue==FIELD_TYPE_TEXT_BOX_SINGLE_LINE ? $lang["property-field_constraint-number"] : $lang["property-field_constraint-singlekeyword"]))?></option>
        </select>
        <?php
    }
    
/**
 * Render metadata field option input on admin_resource_type_field_edit.php
 *
 * @param string $propertyname          Field property/column name
 * @param string $propertytitle         Title 
 * @param string $helptext              Help text
 * @param mixed $type                   Input type (0=text,1=boolean,2=text area)
 * @param mixed $currentvalue           Current field setting
 * @param int   $fieldtype              Field type. See definitions.php
 * @param bool  $system_date_field      Is this field set as the system $date_field?
 * 
 * @return void
 * 
 */
function admin_resource_type_field_option(string $propertyname,string $propertytitle, string $helptext, $type,$currentvalue,int $fieldtype, bool $system_date_field) : void
    {
    debug_function_call("admin_resource_type_field_option",func_get_args());

    global $ref,$lang, $baseurl_short,$FIXED_LIST_FIELD_TYPES, $daterange_edtf_support, $allfields, $newfield,
    $resource_type_array, $existingrestypes, $regexp_slash_replace, $resource_type_array;
    if($propertyname=="linked_data_field")
        {
        if($fieldtype==FIELD_TYPE_DATE_RANGE && $daterange_edtf_support)
            {
            // The linked_data_field column is is only used for date range fields at present
            $propertytitle = $lang["property-field_raw_edtf"];
            }
        else
            {
            return;
            }
        }
    $resource_types=get_resource_types("",true,false,true);
    foreach($resource_types as $resource_type)
        {
        $resource_type_array[$resource_type["ref"]]=$resource_type["name"];
        }
    
    if($propertyname == 'regexp_filter')
        {
        $currentvalue = str_replace($regexp_slash_replace, '\\', (string) $currentvalue);
        }
        
    $alt_helptext=hook('rtfieldedithelptext', 'admin_resource_type_field_edit', array($propertyname));
    if($alt_helptext!==false){
        $helptext=$alt_helptext;
    }
    
    ?>
    <div class="Question" >
        <label><?php echo ($propertytitle!="") ? escape((string) $propertytitle) : escape((string) $propertyname); ?></label>
        <?php
        if($propertyname=="global")
            { 
            // Special case - new global/resource type selector
            ?>
            <table>
                <tr>
                    <td>
                        <input type="checkbox" 
                            name="global" 
                            id="globalfield" 
                            value="1"
                            <?php if($currentvalue == 1) { ?> checked="checked"<?php } ?>s
                            onchange="showHideResTypeSelector();">
                        <?php echo escape($lang["resourcetype-global_field"]) ?>
                    </td>
                </tr>
                <?php
                foreach($resource_type_array as $resource_type=>$restypename)
                    {
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox"
                                name="field_restype_select_<?php echo escape($resource_type); ?>"
                                id="field_restype_select_<?php echo escape($resource_type); ?>" 
                                class="field_restype_select"
                                value="1"
                                <?php if($currentvalue == 1) { ?> disabled="true"<?php } ?>
                                <?php if(in_array($resource_type,$existingrestypes)) { ?> checked="checked"<?php } ?>>
                            <?php echo escape($restypename) ?>
                        </td>
                    </tr>
                    <?php
                    }
                ?>
            </table>
            <script>
            function showHideResTypeSelector() {
                if(jQuery("#globalfield").prop("checked")){
                    jQuery(".field_restype_select").display = 'none';
                    jQuery(".field_restype_select").prop('checked',false);
                    jQuery(".field_restype_select").prop('disabled',true);
                }
                else {                    
                    jQuery(".field_restype_select").display = 'block';
                    jQuery(".field_restype_select").prop('disabled',false);
                }
            }
            </script>
<?php
            }
        elseif($propertyname=="type")
            {
            global $field_types;

            // Sort  so that the display order makes some sense
            //natsort($field_types);
            ?>
                <select id="field_edit_<?php echo escape((string) $propertyname); ?>"
                        name="<?php echo escape((string) $propertyname); ?>"
                        class="stdwidth"
                        onchange="
                                <?php if(!$newfield)
                                {?>
                                newval=parseInt(this.value);
                                if((jQuery.inArray(newval,fixed_list_fields) > -1) && (jQuery.inArray(current_type,text_fields) > -1))
                                    {
                                    jQuery('input[name=\'keywords_index\']')[0].checked = true;

                                    if(confirm('<?php echo escape($lang["admin_resource_type_field_migrate_data_prompt"]) ?>'))
                                        {
                                        jQuery('#migrate_data').val('yes');
                                        }
                                    else
                                        {
                                        jQuery('#migrate_data').val('');
                                        }

                                    this.form.submit();
                                    }

                                    else if ((jQuery.inArray(newval,text_fields) > -1) && (jQuery.inArray(current_type,fixed_list_fields) > -1)) 
                                {
                                    if(confirm('<?php echo escape($lang["admin_resource_type_field_cannot_migrate_data_prompt"]) ?>'))
                                        {
                                            this.form.submit(); 
                                        } else {
                                            jQuery('#field_edit_type').val(current_type);
                                        }
                                }
                                else
                                    {
                                    this.form.submit();
                                    }
                                <?php
                                }
                            else
                                {
                                ?>
                                this.form.submit();
                                <?php
                                }
                                ?>
                ">
                <?php
                foreach($field_types as $field_type=>$field_type_description)
                    {
                    ?>
                    <option value="<?php echo $field_type ?>"<?php if ($currentvalue == $field_type) { echo " selected"; } ?>><?php echo escape($lang[$field_type_description])  ; ?></option>
                    <?php
                    }
                ?>
                </select>
            <?php
            if (in_array($currentvalue, $FIXED_LIST_FIELD_TYPES))
                {
                ?>
                <div class="clearerleft"></div>
                </div> <!-- end question -->

                <div class="Question">
                    <label><?php  echo escape($lang['options']) ; ?></label>
                    <span><a href="<?php echo $baseurl_short ?>pages/admin/admin_manage_field_options.php?field=<?php echo $ref ?>" onClick="return CentralSpaceLoad(this,true);"><?php  echo escape($lang['property-options_edit_link']) ; ?></a></span>
                    <div class="clearerleft"></div>

                <?php
                if(FIELD_TYPE_CATEGORY_TREE != $currentvalue)
                    {
                    ?>
                    </div>
                    <?php
                    $field_index              = array_search($ref, array_column($allfields, 'ref'));
                    $automatic_nodes_ordering = (false !== $field_index ? $allfields[$field_index]['automatic_nodes_ordering'] : 0);
                    ?>
                    <div class="Question">
                        <label><?php  echo escape($lang['property-automatic_nodes_ordering_label']) ; ?></label>
                        <input type="checkbox" name="automatic_nodes_ordering" value="1"<?php if(1 == $automatic_nodes_ordering) { ?> checked="checked"<?php } ?>>
                    <?php
                    // create constraints selector
                    admin_resource_type_field_constraint($ref, (int)$currentvalue);
                    }
                }
            elseif (in_array($currentvalue, array(FIELD_TYPE_TEXT_BOX_SINGLE_LINE)))
                { 
                // create constraints selector
                admin_resource_type_field_constraint($ref, (int)$currentvalue);
                }
            }
        elseif($propertyname=="linked_data_field")
            {
            if ($fieldtype==FIELD_TYPE_DATE_RANGE && $daterange_edtf_support)
                {
                // The linked_data_field column is is only used for date range fields at present            
                // Used to store the raw EDTF string submitted
                ?>
                <input id="linked_data_field" name="linked_data_field" type="text" class="stdwidth" value="<?php echo escape((string) $currentvalue)?>">
<?php
                }
            }
        elseif($propertyname === 'tab')
            {
            ?>
            <select class="stdwidth" name="<?php echo escape($propertyname); ?>">
            <?php
            foreach(get_tab_name_options() as $tab_ref => $tab_name)
                {
                $selected = $tab_ref === (int) $currentvalue ? 'selected' : '';
                ?>
                <option value="<?php echo (int) $tab_ref; ?>" <?php echo $selected; ?>><?php echo escape((string) $tab_name); ?></option>
                <?php
                }
            ?>
            </select>
            <?php
            }
        elseif($propertyname == 'partial_index')
            {
            ?>
            <input 
                id="field_edit_<?php echo escape((string) $propertyname); ?>"
                name="<?php echo escape((string) $propertyname); ?>"
                type="checkbox"
                value="1"
                <?php if ($currentvalue==1) { ?> checked="checked"<?php } ?>
                onchange='handleIndexOptions(this, "field_edit_complete_index")'
                >
            <?php
            }
        elseif ($propertyname == 'complete_index')
            {
            ?>
            <input 
                id="field_edit_<?php echo escape((string) $propertyname); ?>"
                name="<?php echo escape((string) $propertyname); ?>"
                type="checkbox"
                value="1"
                <?php if ($currentvalue==1) { ?> checked="checked"<?php } ?>
                onchange="handleIndexOptions(this, 'field_edit_partial_index')"
                >
            <?php
        } elseif ($propertyname == 'sort_method') {
            ?>
            <select class="stdwidth" name="<?php echo escape($propertyname); ?>">
            <?php 
            foreach(FIELD_SORT_METHODS as $method => $value) {
                $selected = $value === $currentvalue ? 'selected' : '';
                ?>
                <option
                    value="<?php echo (int) $value; ?>" <?php echo $selected; ?>
                    ><?php echo escape($lang["sort-method_$method"]); ?>
                </option>
            <?php }
            ?>
            </select>
            <?php
        }
        elseif($type==1)
            {
            if ($propertyname=="advanced_search" && $system_date_field)
                {
                ?><input id="field_edit_<?php echo escape((string) $propertyname); ?>" name="<?php echo escape((string) $propertyname); ?>" type="checkbox" value="1" checked="checked" onclick="return false;"><?php
                $helptext=$lang["property-system_date_help_text"];
                }
            else
                {
                ?><input id="field_edit_<?php echo escape((string) $propertyname); ?>" name="<?php echo escape((string) $propertyname); ?>" type="checkbox" value="1" <?php if ($currentvalue==1) { ?> checked="checked"<?php } ?>><?php
                }
            }
        elseif($type==2)
            {
            ?>
            <textarea class="stdwidth" rows="5" id="field_edit_<?php echo escape((string) $propertyname); ?>" name="<?php echo escape((string) $propertyname); ?>"><?php echo escape((string) $currentvalue)?></textarea>
            <?php
            }
        else
            {
            ?>
            <input id="field_edit_<?php echo escape((string) $propertyname); ?>" name="<?php echo escape((string) $propertyname); ?>" type="text" class="stdwidth" value="<?php echo escape((string) $currentvalue)?>">
            <?php
            }

        if($helptext!="")
                {
                ?>
                <div class="FormHelp" style="padding:0;clear:left;" >
                    <div class="FormHelpInner"><?php echo strip_tags_and_attributes(str_replace("%ref",$ref,$helptext), ["a"], ["href", "target"]) ?>
                    </div>
                </div>
                <?php
                }

    if($propertyname == "name")
        {
        ?>
        <div id="shortname_err_msg" class="FormHelp DisplayNone" style="padding:0;clear:left;" >
            <div class="FormHelpInner PageInformal"><?php echo escape($lang["warning_duplicate_shortname_fields"]) ; ?></div>
        </div>
        <script>
        var validate_shortname_in_progress = false;
        jQuery("input[name='name']").keyup(function(event)
            {
            if(validate_shortname_in_progress)
                {
                return;
                }

            validate_shortname_in_progress = true;

            jQuery.get(
                baseurl + "/pages/admin/ajax/validate_rtf_shortname.php",
                {
                ref: "<?php echo escape($ref); ?>",
                new_shortname: event.target.value
                },
                function (response)
                    {
                    var err_msg_el = jQuery("#shortname_err_msg");
                    if(err_msg_el.hasClass("DisplayNone") === false)
                        {
                        err_msg_el.addClass("DisplayNone");
                        }

                    if(typeof response.data !== "undefined" && !response.data.valid)
                        {
                        err_msg_el.removeClass("DisplayNone");
                        }

                    validate_shortname_in_progress = false;
                    },
                "json");

            return;
            });
        </script>
        <?php
        }
        ?>
        <div class="clearerleft"></div>
    </div>
    <?php
    }

  
/**
* Renders a resource type selection dropdown
* 
* @param string     $label      label for the field
* @param string     $name       name of form select
* @param string     $class      array CSS class to apply
* @param boolean    $hidden     optionally hide the question usng CSS display:none
* @param int        $current    Current selected value
* 
* @return void
*/
function render_resource_type_selector_question(string $label, string $name, string $class = "stdwidth", bool $hidden = false, $current = 0) : void
    {
    global $lang;
    $resource_types = get_resource_types('',true,false,true);

    echo "<div class='Question' id='" . escape($name) . "'" . ($hidden ? " style='display:none;border-top:none;'" : "") . ">";
    echo "<label for='" . escape($name) . "' >" . escape($label) . "</label>";
    echo "<select name='" . escape($name) . "' id='" . escape($name) . "' class='" . $class . "'>";
    echo "<option value='' selected >" . escape($lang["select"]) . "</option>";
    foreach($resource_types as $resource_type)
        {
        $selected = ($resource_type["ref"] == $current ? "selected" : "");
        echo "<option value='{$resource_type['ref']}' {$selected}>" . escape(lang_or_i18n_get_translated($resource_type['name'],'fieldtitle-')) . "</option>";
        }
    echo "</select>";
    echo "<div class='clearerleft'></div>";
    echo "</div>";
    }

/**
 * Render the Download info for the resource tool (on view page)
 *
 * @param int $ref Resource ref
 * @param array $size_info Preview size information
 * @param bool $downloadthissize Should the size be downloadable or requested?
 * @param bool $view_in_browser Allow the size to be viewed directly in the browser
 */
function add_download_column($ref, $size_info, $downloadthissize, $view_in_browser=false)
    {
    global $save_as, $terms_download, $order_by, $lang, $baseurl, $k, $search, $request_adds_to_collection, $offset, $archive, $sort, $internal_share_access, $urlparams, $resource, $iOS_save,$download_usage;
    $view_in_browser_msg = $view_in_browser ? $lang["view_in_browser"] : $lang["action-download"];
    if ($downloadthissize)
        {
        ?>
        <td class="DownloadButton">
            <?php
            global $size_info_array;
            $size_info_array = $size_info;
            if(!hook("downloadbuttonreplace"))
                {
                if($terms_download || $save_as)
                    {
                    ?>
                    <a id="downloadlink"
                        <?php
                        if (!hook("downloadlink","",array("ref=" . $ref . "&k=" . $k . "&size=" . $size_info["id"] . "&ext=" . $size_info["extension"], $view_in_browser)))
                            {
                            if ($view_in_browser)
                                {
                                echo "href=\"" . generateURL($baseurl . "/pages/terms.php",$urlparams,array("url"=> generateURL($baseurl . "/pages/download.php",$urlparams,array("size"=>$size_info["id"],"ext"=> $size_info["extension"],"direct"=>1,"noattach"=>true)))) . "\"";
                                }
                            else
                                {
                                echo "href=\"" . generateURL($baseurl . "/pages/terms.php",$urlparams,array("url"=> generateURL($baseurl . "/pages/download_progress.php",$urlparams,array("size"=>$size_info["id"],"ext"=> $size_info["extension"])))) . "\"";
                                }
                            }

                        if($iOS_save || $view_in_browser)
                            {
                            echo " target=\"_blank\"";
                            }
                        else
                            {
                            echo " onClick=\"return CentralSpaceLoad(this,true);\"";
                            }
                        ?>>
                        <?php echo escape($view_in_browser_msg); ?>
                    </a>
                    <?php
                    }
                elseif ($download_usage)
                    // download usage form displayed - load into main window
                    { 
                    ?>
                    <a id="downloadlink"
                        <?php
                        if (!hook("downloadlink","",array("ref=" . $ref . "&k=" . $k . "&size=" . $size_info["id"]. "&ext=" . $size_info["extension"])))
                            {
                            if ($view_in_browser)
                                {
                                echo "href=\"" . generateURL($baseurl . "/pages/download_usage.php",$urlparams,array("url"=> generateURL($baseurl . "/pages/download.php",$urlparams,array("size"=>$size_info["id"],"ext"=> $size_info["extension"],"direct"=>1,"noattach"=>true)))) . "\"";
                                }
                            else
                                {
                                echo "href=\"" . generateURL($baseurl . "/pages/download_usage.php",$urlparams,array("url"=> generateURL($baseurl . "/pages/download_progress.php",$urlparams,array("size"=>$size_info["id"],"ext"=> $size_info["extension"])))) . "\"";
                                }
                            }

                        if($iOS_save || $view_in_browser)
                            {
                            echo " target=\"_blank\"";
                            }
                        else
                            {
                            echo " onClick=\"return CentralSpaceLoad(this,true);\"";
                            }
                        ?>>
                        <?php echo escape($view_in_browser_msg); ?>
                    </a>
                    <?php
                    }
                else
                    {
                    ?>
                    <a id="downloadlink"
                        <?php
                        if (!hook("downloadlink","",array("ref=" . $ref . "&k=" . $k . "&size=" . $size_info["id"]. "&ext=" . $size_info["extension"], $view_in_browser)))
                            {
                            if ($view_in_browser)
                                {
                                echo 'href="' . $baseurl . "/pages/download.php?direct=1&noattach=true&ref=" . urlencode($ref) . "&ext=" . escape($size_info['extension']) . "&k=" . urlencode($k) . '" target="_blank"';
                                }
                            else
                                {
                                echo 'href="#" onclick="directDownload(' . '\'' . $baseurl . '/pages/download_progress.php?ref=' . urlencode($ref) . '&size=' . $size_info['id'] . '&ext=' . escape($size_info['extension']) . '&k=' . urlencode($k) . '\'' . ', this); toastNotification(\'Download\', \'' . escape($lang["downloadinprogress"]) . '\');"';
                                }
                            }
                        ?>>
                        <?php echo escape($view_in_browser_msg); ?>
                    </a>
                    <?php
                    }

                unset($size_info_array);
                }
            ?>
        </td>
        <?php
        }
    elseif (checkperm("q"))
        {
        if (!hook("resourcerequest"))
            {
            ?>
            <td class="DownloadButton">
                <?php
                if ($request_adds_to_collection && ($k=="" || $internal_share_access) && !checkperm('b')) // We can't add to a collection if we are accessing an external share, unless we are a logged in user
                    {
                    if (isset($size_info["id"])) 
                        {
                        echo add_to_collection_link($ref,"alert('" . escape($lang["requestaddedtocollection"]) . "');",$size_info["id"]);
                        }
                    else
                        {
                        echo add_to_collection_link($ref);
                        }
                    }
                else
                    {
                    ?><a href="<?php echo generateURL($baseurl . "/pages/resource_request.php",$urlparams) ?>" onClick="return CentralSpaceLoad(this,true);"><?php
                    }
                echo escape($lang["action-request"]) ?>
                </a>
            </td>
            <?php
            }
        }
    else
        {
        # No access to this size, and the request functionality has been disabled. Show just 'restricted'.
        ?><td class="DownloadButton DownloadDisabled"><?php echo escape($lang["access1"])?></td><?php
        }
    }

/**
 * Render image on view.php
 *
 * @param array $resource   Resource data
 * @param array  $context   Array with following named elements
 *                              "access"    - Resource access 
 *                              "edit_access" - Resource edit access 
 * 
 * @return void
 * 
 */
function render_resource_view_image(array $resource, array $context)
    {
    global $lang;
    $imageurl="";   

    $preview_title = get_data_by_field($resource["ref"],$GLOBALS["view_title_field"]);
    if ($preview_title == "") {
        $preview_title = $lang["fullscreenpreview"];
        }
    $use_watermark = check_use_watermark();
    $access = $context["access"] ?? 1; // Default to restricted
    $edit_access = $context["edit_access"] ?? 0;

    // Set the preview sizes to look for. Access will be checked by get_resource_preview()
    // Retina mode uses 'scr' size
    $viewsizes = (($GLOBALS["retina_mode"] || !$GLOBALS["resource_view_use_pre"]) && $access === 0) ? ["scr"]: [];
    $viewsizes[] = "pre";
    $viewsizes[] = "thm";

    $imagepre = get_resource_preview($resource,$viewsizes, $access, $use_watermark);
    if($imagepre)
        {
        $imageurl = $imagepre["url"];
        $imagepath = $imagepre["path"];
        $image_width = $imagepre["width"];
        $image_height = $imagepre["height"];
        $validimage = true;
        }
    else
        {
        ?>
        <div id="previewimagewrapper">
        <?php echo get_nopreview_html((string) $resource["file_extension"], $resource["resource_type"]); ?>
        </div>
        <?php 
        return true;
        }

    $previewimagelink = generateURL("{$GLOBALS["baseurl"]}/pages/preview.php", $GLOBALS["urlparams"], array("ext" => $resource["preview_extension"])) . "&" . hook("previewextraurl");
    $previewimagelink_onclick = 'return CentralSpaceLoad(this);';

    if (!hook("replacepreviewlink","",[$preview_title]))
        {
        ?>
        <div id="previewimagewrapper">
            <a id="previewimagelink"
                class="enterLink"
                href="<?php echo $previewimagelink; ?>"
                title="<?php echo escape($preview_title); ?>"
                style="position:relative;"
                onclick="<?php echo $previewimagelink_onclick; ?>">
        <?php
        }
    ?>
    <img id="previewimage"
        class="Picture"
        src="<?php echo $imageurl; ?>" 
        alt="<?php echo escape($preview_title); ?>" 
        onload="jQuery('.DownloadDBlend').css('pointer-events','auto')"
        GALLERYIMG="no"
    <?php
    if($GLOBALS["annotate_enabled"])
        {
        ?>
        data-original="<?php echo "{$GLOBALS["baseurl"]}/annotation/resource/" . (int) $resource["ref"]; ?>"
        <?php
        }

    if($GLOBALS["retina_mode"])
        {
        ?>
        onload="this.width/=1.8;this.onload=null;"
        <?php
        }
        ?>/>
    </a>

    <?php
    hook('aftersearchimg', '', array($resource["ref"]));
    hook('previewextras'); ?>
        
    <?php
    if (is_null($resource['preview_tweaks'])) {
        $GLOBALS["image_preview_zoom"] = false;
    }

    if($validimage)
        {
        if ($GLOBALS["image_preview_zoom"])
            {
            $GLOBALS["image_preview_zoom"] = false;
            $tile_region_support = false;
            $not_jpg = strtolower((string)$resource['file_extension']) != "jpg";
            $fulljpgsize = $not_jpg ? "hpr" : "";
            $fulljpgext = $not_jpg ? 'jpg' : $resource['file_extension'];
            $zoom_image_path = get_resource_path($resource["ref"], true, $fulljpgsize, false, $fulljpgext, true, 1, $use_watermark);

            if($GLOBALS["preview_tiles"] && file_exists($zoom_image_path) && resource_download_allowed($resource["ref"], '', $resource['resource_type']))
                {
                $image_size = get_original_imagesize($resource["ref"], $zoom_image_path);
                $image_width = (int) $image_size[1];
                $image_height = (int) $image_size[2];

                $tiles = compute_tiles_at_scale_factor(1, $image_width, $image_height);
                $first_tile = (isset($tiles[0]['id']) ? $tiles[0]['id'] : '');
                $last_tile = (isset($tiles[count($tiles) - 1]['id']) ? $tiles[count($tiles) - 1]['id'] : '');
                if(
                    $first_tile !== '' && $last_tile !== ''
                    && file_exists(get_resource_path($resource["ref"], true, $first_tile, false))
                    && file_exists(get_resource_path($resource["ref"], true, $last_tile, false))
                )
                    {
                    $tile_region_support = true;
                    }
                }

            if ($tile_region_support)
                {
                // Force $hide_real_filepath temporarily to get the download URL
                $orig_hrfp = $GLOBALS["hide_real_filepath"];
                $GLOBALS["hide_real_filepath"] = true;
                $tile_url = get_resource_path($resource["ref"], false, '', false, $resource['file_extension'], true, 1, $use_watermark);
                $GLOBALS["hide_real_filepath"] = $orig_hrfp;

                // Generate the custom tile source object for OpenSeadragon
                ?>
                <script>
                var openseadragon_custom_tile_source = {
                    height: <?php echo $image_height; ?>,
                    width:  <?php echo $image_width; ?>,
                    tileSize: <?php echo (int) $GLOBALS["preview_tile_size"]; ?>,
                    minLevel: 11,
                    getTileUrl: function(level, x, y)
                        {
                        var scale_factor = Math.pow(2, this.maxLevel - level);
                        var tile_url = '<?php echo $tile_url; ?>';
                            tile_url += '&tile_region=1';
                            tile_url += '&tile_scale=' + scale_factor;
                            tile_url += '&tile_row=' + y;
                            tile_url += '&tile_col=' + x;

                        console.info('[OpenSeadragon] level = %o, x (column) = %o, y (row) = %o, scale_factor = %o', level, x, y, scale_factor);
                        console.debug('[OpenSeadragon] tile_url = %o', tile_url);
                        return tile_url;
                        }
                };
                </script>
                <?php
                $GLOBALS["image_preview_zoom"] = true;
                }
            else
                {
                // Use static image of a higher resolution (lpr/scr) preview
                foreach(['lpr', 'scr'] as $hrs)
                    {
                    $zoom_image_path = get_resource_path($resource["ref"], true, $hrs, false, $resource['preview_extension'], true, 1, $use_watermark);
                    $allowed_static_image_size = resource_download_allowed($resource["ref"], $hrs, $resource['resource_type']);
                    if(file_exists($zoom_image_path) && !resource_has_access_denied_by_RT_size($resource['resource_type'], $hrs) && $allowed_static_image_size)
                        {
                        $preview_url = get_resource_path($resource["ref"], false, $hrs, false, $resource['preview_extension'], true, 1, $use_watermark);

                        // Generate the custom tile source object for OpenSeadragon
                        $GLOBALS["image_preview_zoom_lib_required"] = true;
                        ?>
                        <script>
                        var openseadragon_custom_tile_source = { type: 'image', url: '<?php echo $preview_url; ?>' };
                        </script>
                        <?php
                        $GLOBALS["image_preview_zoom"] = true;
                        break;
                        }
                    }
                }
            }

        if ($GLOBALS["annotate_enabled"]) {
            include_once RESOURCESPACE_BASE_PATH . '/include/annotation_functions.php';
        }

        if (canSeePreviewTools($resource))
            {
            ?>
            <!-- Available tools to manipulate previews -->
            <div id="PreviewTools" >
                <script>
                function is_another_tool_option_enabled(element)
                    {
                    var current_selected_tool = jQuery(element);
                    var tool_options_enabled = jQuery('#PreviewToolsOptionsWrapper')
                        .find('.ToolsOptionLink.Enabled')
                        .not(current_selected_tool);

                    if(tool_options_enabled.length === 0)
                        {
                        return false;
                        }

                    styledalert('<?php echo escape($lang['not_allowed']); ?>', '<?php echo escape($lang['error_multiple_preview_tools']); ?>');
                    return true;
                    }

                function toggleMode(element)
                    {
                    jQuery(element).toggleClass('Enabled');
                    }
                </script>

                <div id="PreviewToolsOptionsWrapper">
                    <?php
                    if($GLOBALS["annotate_enabled"] && file_exists($imagepath) && canSeeAnnotationsFields())
                        {
                        ?>
                        <a class="ToolsOptionLink AnnotationsOption" href="#" onclick="toggleAnnotationsOption(this); return false;">
                            <i class='icon-square-pen' aria-hidden="true"></i>
                        </a>

                        <script>
                        var rs_tagging_plugin_added = false;

                        function toggleAnnotationsOption(element)
                            {
                            var option             = jQuery(element);
                            var preview_image      = jQuery('#previewimage');
                            var preview_image_link = jQuery('#previewimagelink');
                            var img_copy_id        = 'previewimagecopy';
                            var img_src            = preview_image.attr('src');

                            // Setup Annotorious (has to be done only once)
                            if(!rs_tagging_plugin_added)
                                {
                                anno.addPlugin('RSTagging',
                                    {
                                    annotations_endpoint: '<?php echo generateURL("{$GLOBALS['baseurl']}/pages/ajax/annotations.php", ['k' => $GLOBALS['k']]); ?>',
                                    nodes_endpoint      : '<?php echo $GLOBALS["baseurl"]; ?>/pages/ajax/get_nodes.php',
                                    resource            : <?php echo (int) $resource["ref"]; ?>,
                                    read_only: <?php
                                        echo (
                                            $edit_access
                                            || (
                                                !$edit_access
                                                && $GLOBALS['annotate_text_adds_comment']
                                                && !checkPermission_anonymoususer()
                                            )
                                        )
                                            ? 'false'
                                            : 'true';
                                    ?>,
                                    lang: <?php echo json_encode(get_annotorious_lang($lang)); ?>,
                                    rs_config: <?php echo json_encode(get_annotorious_resourcespace_config()); ?>,
                                    // We pass CSRF token identifier separately in order to know what to get in the Annotorious plugin file
                                    csrf_identifier: '<?php echo $GLOBALS["CSRF_token_identifier"]; ?>',
                                    <?php echo generateAjaxToken('RSTagging'); ?>
                                    });

                                <?php if ($GLOBALS["facial_recognition"]) { ?>
                                    anno.addPlugin('RSFaceRecognition',
                                        {
                                        annotations_endpoint: '<?php echo $GLOBALS["baseurl"]; ?>/pages/ajax/annotations.php',
                                        facial_recognition_endpoint: '<?php echo $GLOBALS["baseurl"]; ?>/pages/ajax/facial_recognition.php',
                                        resource: <?php echo (int) $resource["ref"]; ?>,
                                        facial_recognition_tag_field: <?php echo (int) $GLOBALS["facial_recognition_tag_field"]; ?>,
                                        // We pass CSRF token identifier separately in order to know what to get in the Annotorious plugin file
                                        fr_csrf_identifier: '<?php echo $GLOBALS["CSRF_token_identifier"]; ?>',
                                        <?php echo generateAjaxToken('RSFaceRecognition'); ?>
                                        });
                                <?php } ?>

                                rs_tagging_plugin_added = true;

                                // We have to wait for initialisation process to finish as this does ajax calls
                                // in order to set itself up
                                setTimeout(function ()
                                    {
                                    toggleAnnotationsOption(element);
                                    }, 
                                    1000);

                                return false;
                                }

                            // Feature enabled? Then disable it.
                            if(option.hasClass('Enabled'))
                                {
                                anno.destroy(preview_image.data('original'));

                                // Remove the copy and show the linked image again
                                jQuery('#' + img_copy_id).remove();
                                preview_image_link.show();

                                toggleMode(element);

                                return false;
                                }

                            // Always check no other conflicting preview tool option is enabled
                            if(is_another_tool_option_enabled(element))
                                {
                                return false;
                                }

                            // Enable feature
                            // Hide the linked image for now and use a copy of it to annotate
                            var preview_image_copy = preview_image.clone(true);
                            preview_image_copy.prop('id', img_copy_id);
                            preview_image_copy.prop('src', img_src);

                            // Set the width and height of the image otherwise if the source of the file
                            // is fetched from download.php, Annotorious will not be able to determine its
                            // size
                            var preview_image_width=preview_image.width();
                            var preview_image_height=preview_image.height();
                            preview_image_copy.width( preview_image_width );
                            preview_image_copy.height( preview_image_height );

                            preview_image_copy.insertAfter(preview_image_link);
                            preview_image_link.hide();

                            anno.makeAnnotatable(document.getElementById(img_copy_id));

                            toggleMode(element);

                            return false;
                            }
                        </script>
                        <?php
                        }

                    if($GLOBALS["image_preview_zoom"])
                        {
                        # Process rotation from preview tweaks and use it to display the openseadragon preview in the correct orientation.
                        $osd_preview_rotation = 0;
                        if (isset($resource['preview_tweaks']))
                            {
                            $preview_tweak_parts = explode('|', $resource['preview_tweaks']);
                            if ($preview_tweak_parts[0] > 0 && is_numeric($preview_tweak_parts[0]))
                                {
                                $osd_preview_rotation = 360 - $preview_tweak_parts[0];
                                }
                            } ?>

                        <a class="ToolsOptionLink ImagePreviewZoomOption" href="#" onclick="return toggleImagePreviewZoomOption(this);">
                            <i class='icon-zoom-in' aria-hidden="true"></i>
                        </a>

                        <script>
                        var openseadragon_viewer = null;
                        function toggleImagePreviewZoomOption(element)
                            {
                            var zoom_option_enabled = jQuery(element).hasClass('Enabled');

                            if(!zoom_option_enabled && is_another_tool_option_enabled(element))
                                {
                                // Don't enable the tool while a conflicting preview tool is enabled
                                return false;
                                }
                            else if(!zoom_option_enabled)
                                {
                                console.debug('Enabling image zoom with OpenSeadragon');

                                jQuery('#previewimagewrapper').prepend('<div id="openseadragon_viewer"></div>');

                                // Hide the usual preview image of the resource
                                jQuery('#previewimagelink').toggleClass('DisplayNone');

                                openseadragon_viewer = OpenSeadragon({
                                    id: "openseadragon_viewer",
                                    prefixUrl: "<?php echo $GLOBALS["baseurl"] . LIB_OPENSEADRAGON; ?>/images/",
                                    degrees: <?php echo (int) $osd_preview_rotation; ?>,
                                    // debugMode: true,
                                    // debugGridColor: ['red'],

                                    tileSources: openseadragon_custom_tile_source
                                });
                                }
                            else if(zoom_option_enabled)
                                {
                                console.debug('Disabling image zoom with OpenSeadragon');
                                openseadragon_viewer.destroy();
                                openseadragon_viewer = null;
                                jQuery('#openseadragon_viewer').remove();

                                // Show the usual preview image of the resource
                                jQuery('#previewimagelink').toggleClass('DisplayNone');
                                }
                            else
                                {
                                console.error('Something went wrong with toggleImagePreviewZoomOption');
                                }

                            toggleMode(element);

                            return false;
                            }
                        </script>
                        <?php
                        }
                        ?>
                </div>
            </div>
            <?php
            } /* end of canSeePreviewTools() */
        }
        ?>
    </div>
    <?php
    }

/**
 * Render the resource tools for preview sizes
 * @param array $resource Resource data - {@see get_resource_data()} 
 * @param array{'download_multisize': bool, "sizes"?: array, 'urlparams': array} $ctx Contextual information.
 */
function render_resource_tools_size_download_options(array $resource, array $ctx): void
{
    $ref = $resource['ref'];
    $download_multisize = $ctx['download_multisize'];
    $sizes = $ctx['sizes'] ?? get_image_sizes($resource['ref'], false, $resource['file_extension'], false);
    $urlparams = $ctx['urlparams'];
    
    /** @var string Client side namespace to prevent clashes */
    $ns = "Resource{$ref}{$ctx['context']}";

    if (!(($resource['has_image'] !== RESOURCE_PREVIEWS_NONE) && $download_multisize)) {
        return;
    }

    $allowed_sizes = [];
    foreach ($sizes as $size) {
        if ($size['id'] === '') {
            continue;
        }

        $downloadthissize = resource_download_allowed($ref, $size['id'], $resource['resource_type']);
        if (!$downloadthissize && !checkperm('q')) {
            continue;
        }

        // Fake $size key entry used in JS land
        $size['html'] = [
            // to display the preview size info to the user when selecting a size
            'size_info' => get_size_info($size),
            // to update the download button when selecting a size
            'download_column' => cast_echo_to_string('add_download_column', [$ref, $size, $downloadthissize]),
        ];

        if (
            $downloadthissize 
            && $size['allow_preview'] == 1 
            && !hook('previewlinkbar')
            && file_exists($size['path'])
        ) {
            $GLOBALS['data_viewsize'] = $size['id']; # relied upon by some plugins (e.g lightbox)

            // Fake $size key entry used in JS land to update the View button before showing it to the user
            $size['html']['view_btn'] = [
                'viewsizeurl' => (string) hook('getpreviewurlforsize'),
                'href' => generateURL(
                        "{$GLOBALS['baseurl']}/pages/preview.php",
                        $urlparams,
                        ['ext' => $resource['file_extension']]
                    )
                    . '&' . hook('previewextraurl'),
            ];
        }

        $allowed_sizes[$size['id']] = $size;
    }

    if ($allowed_sizes === []) {
        return;
    }
    $modified_sizes = hook('modifyallowed_sizes', 'view', [$resource, $allowed_sizes]);
    if ($modified_sizes !== false) {
        $allowed_sizes = $modified_sizes;
    }

    $use_selector = count($allowed_sizes) > 1;
    $render = function(bool $show_selector) use ($ns, $allowed_sizes, $resource): void {
        if ($show_selector) {
            ?>
            <select id="<?php echo escape($ns); ?>size">
                <?php
                foreach ($allowed_sizes as $allowed_size) {
                    echo render_dropdown_option($allowed_size['id'], $allowed_size['name']);
                }
                ?>
            </select>
            <?php hook('append_to_download_filename_td', '', [$resource, $ns]); ?>
            <div id="<?php echo escape($ns); ?>sizeInfo"><?php
                echo $allowed_sizes[array_key_first($allowed_sizes)]['html']['size_info'];
            ?></div>
            <?php
            return;
        }

        // If only one size is available, render in the same way as the original file (ie not as a drop down selector).
        $allowed_size = $allowed_sizes[array_key_first($allowed_sizes)];
        ?>
        <h2><?php echo escape($allowed_size['name']); ?></h2>
        <?php hook('append_to_download_filename_td', '', [$resource, $ns]); ?>
        <div id="<?php echo escape($ns); ?>sizeInfo"><?php echo $allowed_size['html']['size_info']; ?></div>
        <?php
    };
    $render_js_picker = function(bool $show_selector) use ($ns, $allowed_sizes) {
        if ($show_selector) {
            ?>
            const picker = jQuery('select#<?php echo escape($ns); ?>size');
            const selected_size = picker.val();
            updateSizeInfo('<?php echo escape($ns); ?>', selected_size);
            updatePreviewLink('<?php echo escape($ns); ?>', selected_size, picker);
            updateDownloadLink('<?php echo escape($ns); ?>', selected_size, picker);
            <?php
            return;
        }

        // If only one size is available, there's no "size" to select from so ensure functions get called correctly to
        // the context.
        $allowed_size = $allowed_sizes[array_key_first($allowed_sizes)];
        ?>
        const picker = jQuery('.Picker #<?php echo escape($ns); ?>sizeInfo');
        updatePreviewLink('<?php echo escape($ns); ?>', '<?php echo escape($allowed_size['id']); ?>', picker);
        updateDownloadLink('<?php echo escape($ns); ?>', '<?php echo escape($allowed_size['id']); ?>', picker);
        <?php
    };
    ?>
    <tr class="DownloadDBlend">
        <td class="DownloadFileName Picker"><?php $render($use_selector); ?></td>
        <td class="DownloadButton">
            <?php
            if(!hook("downloadbuttonreplace"))  {?> 
            <a id="<?php echo escape($ns); ?>downloadlink" onclick="return CentralSpaceLoad(this, true);"><?php
                echo escape($GLOBALS['lang']['action-download']);
            ?></a>
            <a
                id="<?php echo escape($ns); ?>previewlink"
                class="enterLink previewsizelink DisplayNone"
                href="#"
                data-viewsize=""
                data-viewsizeurl=""
            ><?php echo escape($GLOBALS['lang']["action-view"]); ?></a>
            <?php
        }?>
        </td>
    </tr>
    <script>
    function <?php echo escape($ns); ?>_get_preview_size_info()
    {
        return <?php
            echo json_encode(
                array_map(
                    get_sub_array_with(
                        [
                            'allow_preview',
                            'html',
                        ]
                    ),
                    $allowed_sizes
                ),
                JSON_NUMERIC_CHECK
            );
        ?>;
    }

    jQuery(document).ready(function() {
        <?php $render_js_picker($use_selector); ?>
    });

    jQuery('select#<?php echo escape($ns); ?>size').change(function() {
        const picker = jQuery(this);
        const selected_size = picker.val();
        updateSizeInfo('<?php echo escape($ns); ?>', selected_size);
        updatePreviewLink('<?php echo escape($ns); ?>', selected_size, picker);
        updateDownloadLink('<?php echo escape($ns); ?>', selected_size, picker);
    });
    <?php hook('append_to_resource_tools_size_download_options_script', '', [$ns, $allowed_sizes, $resource]); ?>
    </script>
<?php
}


/**
 * Adds script to display toast notification. Note: Must be included after header.php.
 *
 * @param  ToastNotificationType  $type      Options are: 'success', 'error' or 'download'. Determines toast notification icon.
 * @param  string                 $message   Notification text to display.
 */

function toast_notification(ToastNotificationType $type, string $message): void
{
    $message_escaped = escape($message);
    ?>
    <script>
    toastNotification(<?php echo "'{$type->name}', '{$message_escaped}'"; ?>);
    </script>
    <?php
}

// phpcs:disable
enum ToastNotificationType
{
    case Success;
    case Download;
    case Error;
}
// phpcs:enable