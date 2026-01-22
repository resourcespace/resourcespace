<?php

function save_proposed_changes($ref)
    {
    debug_function_call(__FUNCTION__, func_get_args());
    global $userref, $auto_order_checkbox,$languages,$language, $FIXED_LIST_FIELD_TYPES, $DATE_FIELD_TYPES;

    if(!is_numeric($ref))
        {
        return false;
        }
        
    # Loop through the field data and save (if necessary)
    $errors        = array();
    $fields        = get_resource_field_data($ref, false);
    $resource_data = get_resource_data($ref);

    // All the nodes passed for editing. Some of them were already a value
    // of the fields while others have been added/ removed
    $user_set_values = getval('nodes', [], false, 'is_array');

        for ($n=0;$n<count($fields);$n++)
            {
            /**
             * @var false|string $val The proposed value change submitted. Meanings:
             * - false: field hasn't been submitted (e.g. user doesn't have access)
             * - string: empty string to unset field value -or- the proposed value (for fixed list fields that means a
             *           CSV of node IDs)
             *
             * Note: the meaning of an empty string is contextual - it can also mean no proposed change to an existing
             * unset field. Those cases are ignored by our logic (further down) since there's no real change.
             */
            $val = false;
            $new_nodes = array();

            if(in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES))
                {
                debug("Field #{$fields[$n]['ref']} -- FIXED_LIST_FIELD_TYPES");
                $fields[$n]['nodes'] = get_nodes(
                    $fields[$n]['ref'],
                    null,
                    FIELD_TYPE_CATEGORY_TREE === $fields[$n]['type']
                );
                $valid_nodes = in_array($fields[$n]['type'], [FIELD_TYPE_DROP_DOWN_LIST, FIELD_TYPE_RADIO_BUTTONS])
                    ? array_column($fields[$n]['nodes'], 'ref')
                    : array_column(array_filter($fields[$n]['nodes'], 'node_is_active'), 'ref');

                // Fixed list fields use node IDs directly
                $ui_selected_node_values = array();
                if(isset($user_set_values[$fields[$n]['ref']])
                    && !is_array($user_set_values[$fields[$n]['ref']])
                    && '' != $user_set_values[$fields[$n]['ref']]
                    && is_numeric($user_set_values[$fields[$n]['ref']]))
                    {
                    $ui_selected_node_values[] = $user_set_values[$fields[$n]['ref']];
                    }
                elseif(isset($user_set_values[$fields[$n]['ref']])
                    && is_array($user_set_values[$fields[$n]['ref']]))
                    {
                    $ui_selected_node_values = $user_set_values[$fields[$n]['ref']];
                    }

                $new_nodes = array_intersect($valid_nodes, $ui_selected_node_values);
                natsort($new_nodes);
                debug(sprintf('Field #%s -- $new_nodes = %s', $fields[$n]['ref'], implode(',', $new_nodes)));
                $val = implode(', ', $new_nodes);

                /* 
                Field values contain only active nodes because when determining new nodes that will already remove
                inactive ones (e.g when rendering the field on the page or receiving the submitted data - see above).
                This way we won't detect a change is proposed if the only thing different is the lack of inactive 
                nodes.
                */
                $field_value = implode(
                    ', ',
                    array_column(
                        in_array($fields[$n]['type'], [FIELD_TYPE_DROP_DOWN_LIST, FIELD_TYPE_RADIO_BUTTONS])
                            ? get_resource_nodes($ref, $fields[$n]['ref'], true)
                            : array_filter(get_resource_nodes($ref, $fields[$n]['ref'], true), 'node_is_active'),
                        'ref'
                    )
                );
                }               
        else
                {
                $field_value = $fields[$n]['value'];

                if($fields[$n]['type']==FIELD_TYPE_DATE_RANGE)
                    {
                    # date range type
                    # each value will be a node so we end up with a pair of nodes to represent the start and end dates

                    $newval="";
                    
                    if(($date_edtf=getval("field_" . $fields[$n]["ref"] . "_edtf", false))!==false)
                        {
                        debug("Field #{$fields[$n]['ref']} -- EDTF date range");
                        // We have been passed the range in EDTF format, check it is in the correct format
                        $rangeregex="/^(\d{4})(-\d{2})?(-\d{2})?\/(\d{4})(-\d{2})?(-\d{2})?/";
                        if(!preg_match($rangeregex,$date_edtf,$matches))
                            {
                            $errors[$fields[$n]["ref"]]=$lang["information-regexp_fail"] . " : " . $val;
                            continue;
                            }
                        $rangedates = explode("/",$date_edtf);
                        $rangestart=str_pad($rangedates[0],  10, "-00");
                        $rangeendparts=explode("-",$rangedates[1]);
                        $rangeendyear=$rangeendparts[0];
                        $rangeendmonth=isset($rangeendparts[1])?$rangeendparts[1]:12;
                        $rangeendday=isset($rangeendparts[2])?$rangeendparts[2]:cal_days_in_month(CAL_GREGORIAN, $rangeendmonth, $rangeendyear);
                        $rangeend=$rangeendyear . "-" . $rangeendmonth . "-" . $rangeendday;              
                        }
                    else
                        {
                        debug("Field #{$fields[$n]['ref']} -- Range has been passed via normal inputs, construct the value from the date/time dropdowns");
                        $date_parts=array("start","end");
                        
                        foreach($date_parts as $date_part)
                            {   
                            $val = getval("field_" . $fields[$n]["ref"] . "_" . $date_part . "_year",false);
                            if ($val !== false && intval($val)<=0) 
                                {
                                $val="";
                                }
                            elseif (($field=getval("field_" . $fields[$n]["ref"] . "_" . $date_part . "_month",""))!="") 
                                {
                                $val.="-" . $field;
                                if (($field=getval("field_" . $fields[$n]["ref"] . "_" . $date_part . "_day",""))!="") 
                                    {
                                    $val.="-" . $field;
                                    }
                                 else 
                                    {
                                    $val.="-00";
                                    }
                                }
                            else 
                                {
                                $val.="-00-00";
                                }
                            $newval.= ($newval!=""?", ":"") . $val;
                            }
                        }
                        $val=$newval;
                    }
                elseif ($GLOBALS['use_native_input_for_date_field'] && $fields[$n]['type'] === FIELD_TYPE_DATE)
                    {
                    debug("Field #{$fields[$n]['ref']} -- FIELD_TYPE_DATE (w/ native input)");
                    $val = getval("field_{$fields[$n]['ref']}", false);
                    if($val !== false && !validateDatetime($val, 'Y-m-d'))
                        {
                        $errors[$fields[$n]["ref"]] = str_replace(
                            [' %row%', '%date%', '%field%'],
                            ['', $val, $fields[$n]['name']],
                            $GLOBALS['lang']['invalid_date_error']
                        );
                        continue;
                        }
                    }
                elseif(in_array($fields[$n]['type'], $DATE_FIELD_TYPES))
                    {
                    debug("Field #{$fields[$n]['ref']} -- DATE_FIELD_TYPES");
                    $val=sanitize_date_field_input($fields[$n]["ref"], false);

                    if ($GLOBALS['use_native_input_for_date_field'] && $fields[$n]['type'] === FIELD_TYPE_DATE)
                        {
                        $val = getval("field_{$fields[$n]['ref']}", '');
                        }
                    }
                else
                    {
                    debug("Field #{$fields[$n]['ref']} -- Set the value exactly as sent");
                    $val=getval("field_" . $fields[$n]["ref"],false);
                    }

                # Check for regular expression match
                if (strlen(trim((string)$fields[$n]["regexp_filter"]))>=1 && strlen($val)>0)
                    {
                    global $regexp_slash_replace;
                    if(preg_match("#^" . str_replace($regexp_slash_replace, '\\', $fields[$n]["regexp_filter"]) . "$#",$val,$matches)<=0)
                        {
                        global $lang;
                        debug($lang["information-regexp_fail"] . ": -" . "reg exp: " . str_replace($regexp_slash_replace, '\\', $fields[$n]["regexp_filter"]) . ". Value passed: " . $val);
                        if (getval("autosave","")!="")
                            {
                            exit();
                            }
                        $errors[$fields[$n]["ref"]]=$lang["information-regexp_fail"] . " : " . $val;
                        continue;
                        }
                    }
                $modified_val=hook("modifiedsavedfieldvalue",'',array($fields,$n,$val));
                if(!empty($modified_val)){$val=$modified_val;}
                
                $error=hook("additionalvalcheck", "all", array($fields, $fields[$n]));
                if ($error) 
                    {
                    global $lang;
                    if (getval("autosave","")!="")
                        {
                        exit($error);
                        }
                    $errors[$fields[$n]["ref"]]=$error;
                    continue;
                    }
                }
                    
                # Check for regular expression match
                if (strlen(trim((string)$fields[$n]["regexp_filter"]))>=1 && strlen($val)>0)
                        {
                        global $regexp_slash_replace;
                        if(preg_match("#^" . str_replace($regexp_slash_replace, '\\', $fields[$n]["regexp_filter"]) . "$#",$val,$matches)<=0)
                                {
                                global $lang;
                                debug($lang["information-regexp_fail"] . ": -" . "reg exp: " . str_replace($regexp_slash_replace, '\\', $fields[$n]["regexp_filter"]) . ". Value passed: " . $val);
                                if (getval("autosave","")!="")
                                        {
                                        exit();
                                        }
                                $errors[$fields[$n]["ref"]]=$lang["information-regexp_fail"] . " : " . $val;
                                continue;
                                }
                        }
            $error=hook("additionalvalcheck", "all", array($fields, $fields[$n]));
            if ($error) 
                {
                global $lang;               
                $errors[$fields[$n]["ref"]]=$error;
                continue;
                }

            debug(sprintf('Field #%s -- $field_value = %s', $fields[$n]['ref'], json_encode($field_value)));
            debug(sprintf('Field #%s -- $val = %s', $fields[$n]['ref'], json_encode($val)));

            if ($val !== false && trim(str_replace("\r\n", "\n", $field_value??"")) !== trim(str_replace("\r\n", "\n", unescape($val))))
                    {
                    if (
                        in_array($fields[$n]['type'], $DATE_FIELD_TYPES)
                        && trim((string) $field_value).":00" == trim($val) # Check that date hasn't only changed by adding seconds value
                    ) {
                        continue;    
                    }

                    debug("Field #{$fields[$n]['ref']} -- Save proposed change (this value is different from the value we have on record)");
                    ps_query(
                        'INSERT INTO propose_changes_data (resource, user, resource_type_field, value, date) VALUES (?, ?, ?, ?, now())',
                        [
                            'i',$ref,
                            'i',$userref,
                            'i',$fields[$n]['ref'],
                            's',$val,
                        ]
                    );
                    }            
            
            }

        return true;
        }
        
function get_proposed_changes($ref, $userid)
    {
    //Get all the changes proposed by a user
    $query = "SELECT d.value,
                     d.resource_type_field,
                     d.date,
                     f.*,
                     f.required AS frequired,
                     f.ref AS fref
                  FROM resource_type_field AS f
             LEFT JOIN (
                            SELECT *
                              FROM propose_changes_data
                             WHERE resource = ?
                               AND user = ?
                       ) AS d ON d.resource_type_field = f.ref AND d.resource = ?
             GROUP BY f.ref
             ORDER BY f.global DESC, f.order_by, f.ref;";
    $parameters=array("i",$ref, "i",$userid, "i",$ref);
    return ps_query($query, $parameters);
    }

function delete_proposed_changes($ref, $userid="")
    {
    $query = "DELETE FROM propose_changes_data WHERE resource = ?";
    $parameters=array("i",$ref);
    if ($userid!="")
        {
        $query.=" AND user=?";
        $parameters=array_merge($parameters,array("i",$userid));
        }
    ps_query($query, $parameters);
    }

function propose_changes_display_field($n, $field)
    {
    global $ref, $original_fields, $is_template, $language, $lang,  $errors, $proposed_changes, $editaccess,
    $FIXED_LIST_FIELD_TYPES,$range_separator, $edit_autosave;

    # Certain edit_fields/x.php functions check for bulk edit which must be defined as false prior to rendering propose change field  
    $multiple=false;

    $edit_autosave=false;
    $name="field_" . $field["ref"];
    $value=trim($field['value'] ?? '');
    
    // Force display field because we rely on display_field_data() which is meant to honour this property (but meant 
    // only for the view page). In this context, "hiding" fields is done using permissions.
    $field['display_field'] = 1;

    # is there a proposed value set for this field?
    /**
     * @var false|string $proposed_value The proposed value change submitted. Meanings:
     * - false: no proposed change found
     * - string: empty string to unset field value -or- the proposed value (for fixed list fields that means a CSV of node IDs)
     */
    $proposed_value = false;
    foreach($proposed_changes as $proposed_change)
        {
        if($proposed_change['resource_type_field'] == $field['ref'])
            {
            $proposed_value = $proposed_change['value'];
            }
        }
    
    $has_proposed_changes = $proposed_value !== false;

    // Don't show this if user is an admin viewing proposed changes, needs to be on form so that form is still submitted with all data
    if ($editaccess && !$has_proposed_changes)
        {
        ?>
        <div style="display:none" >
        <?php
        }

    ?>
    <div class="Question ProposeChangesQuestion" id="question_<?php echo $n?>">
    <div class="Label ProposeChangesLabel" ><?php echo escape($field["title"])?></div>

    <?php 
    # Define some Javascript for help actions (applies to all fields)
    $help_js="onBlur=\"HideHelp(" . $field["ref"] . ");return false;\" onFocus=\"ShowHelp(" . $field["ref"] . ");return false;\"";

    #hook to modify field type in special case. Returning zero (to get a standard text box) doesn't work, so return 1 for type 0, 2 for type 1, etc.
    $modified_field_type="";
    $modified_field_type=(hook("modifyfieldtype"));
    if ($modified_field_type){$field["type"]=$modified_field_type-1;}

    hook("addfieldextras");

    // ------------------------------
    // Show existing value so can edit
    $value=preg_replace("/^,/","",$field["value"]??"");
    $realvalue = $value; // Store this in case it gets changed by view processing
    if ($value!="")
            {
            # Draw this field normally.         
            ?><div class="propose_changes_current ProposeChangesCurrent"><?php display_field_data($field,true); ?></div><?php
            }                        
        else
            {
            ?><div class="propose_changes_current ProposeChangesCurrent"><?php echo escape($lang["propose_changes_novalue"])  ?></div>    
            <?php
            }
        if(!$editaccess && !$has_proposed_changes)
            {
            ?>
            <div class="propose_change_button" id="propose_change_button_<?php echo $field["ref"]; ?>">
            <input type="submit" value="<?php echo escape($lang["propose_changes_buttontext"]) ?>" onClick="ShowProposeChanges(<?php echo $field["ref"]; ?>);return false;" />
            </div>
            <?php
            }?>

    <div class="proposed_change proposed_change_value proposed ProposeChangesProposed" <?php if(!$has_proposed_changes){echo "style=\"display:none;\""; } ?> id="proposed_change_<?php echo escape($field["ref"]); ?>">
    <input type="hidden" id="propose_change_<?php echo escape($field["ref"]); ?>" name="propose_change_<?php echo escape($field["ref"]); ?>" value="true" <?php if(!$has_proposed_changes){echo "disabled=\"disabled\""; } ?> />
    <?php
    # ----------------------------  Show field -----------------------------------
    $value = $has_proposed_changes ? $proposed_value : $realvalue;
    $type = $field['type'];

    if('' == $type)
        {
        $type = 0;
        }

    if (!hook('replacefield', '', array($field['type'], $field['ref'], $n)))
        {
        global $auto_order_checkbox, $auto_order_checkbox_case_insensitive, $FIXED_LIST_FIELD_TYPES, $is_search;

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

            $selected_nodes = !$editaccess && !$has_proposed_changes
                ? get_resource_nodes($ref, $field['resource_type_field'])
                : ($has_proposed_changes ? explode(', ', $proposed_value) : []);
            }

        $is_search = false;

        include __DIR__ . "/../../../pages/edit_fields/{$type}.php";
        }
    # ----------------------------------------------------------------------------
    ?>
        </div><!-- close proposed_change_<?php echo $field["ref"]; ?> -->
        <?php
        if($editaccess)
            {
            ?>     
            <div class="ProposeChangesAccept ProposeChangesAcceptDeleteColumn">
            <table>
            <tr>
            <td><input class="ProposeChangesAcceptCheckbox" type="checkbox" id="accept_change_<?php echo $field["ref"]; ?>" name="accept_change_<?php echo $field["ref"]; ?>" onchange="UpdateProposals(this,<?php echo $field["ref"]; ?>);" checked ></input><?php echo escape($lang["propose_changes_accept_change"])  ?></td>
            <td>
            <input class="ProposeChangesDeleteCheckbox" type="checkbox" id="delete_change_<?php echo $field["ref"]; ?>" name="delete_change_<?php echo $field["ref"]; ?>" onchange="DeleteProposal(this,<?php echo $field["ref"]; ?>);" ></input><?php echo escape($lang["action-delete"])  ?></td>
            </tr>
            </table>
            </div>
            <?php
            }

    if (trim($field["help_text"]!=""))
        {
        # Show inline help for this field.
        # For certain field types that have no obvious focus, the help always appears.
        ?>
        <div class="FormHelp" style="<?php if (!in_array($field["type"],array(2,4,6,7,10))) { ?>display:none;<?php } else { ?>clear:left;<?php } ?>" id="help_<?php echo $field["ref"]; ?>"><div class="FormHelpInner"><?php echo nl2br(trim(escape(i18n_get_translated($field["help_text"]))))?></div></div>
        <?php
        }
    ?>
    <div class="clearerleft"> </div>
    </div><!-- end of question_<?php echo $n?> div -->
    <?php
    // Don't show this if user is an admin viewing proposed changes
    if ($editaccess && !$has_proposed_changes)
        {
        ?>
        </div><!-- End of hidden field -->
        <?php
        }
    }
