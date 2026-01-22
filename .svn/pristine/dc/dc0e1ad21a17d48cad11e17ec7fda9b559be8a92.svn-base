<?php
    
function HookRse_VersionAllBeforeremoveexistingfile($ref)
    {
    # Hook into upload_file and move out the existing file when uploading a new one.
    global $rse_version_override_groups, $rse_version_block, $usergroup;
    if(isset($rse_version_override_groups) && in_array($usergroup,$rse_version_override_groups) && isset($rse_version_block) && $rse_version_block)
        {
        // Versioning has been disabled, no action required.
        return false;
        }

    $old_extension=ps_value("SELECT file_extension value from resource where ref=?",array("i",$ref),"");

    if ($old_extension!="")
        {
        $old_path=get_resource_path($ref,true,"",true,$old_extension);
        if (file_exists($old_path))
            {
            $alt_file=add_alternative_file($ref,'','','',$old_extension,0,'');
            $new_path = get_resource_path($ref, true, '', true, $old_extension, -1, 1, false, "", $alt_file);

            copy($old_path,$new_path);

            # Also copy thumbnail
            $old_thumb=get_resource_path($ref,true,'thm',true,"");
            if (file_exists($old_thumb))
                {
                $new_thumb=get_resource_path($ref, true, 'thm', true, "", -1, 1, false, "", $alt_file);
                copy($old_thumb,$new_thumb);
                }
            
            # Store this value so it is written to the log later.
            global $previous_file_alt_ref;
            $previous_file_alt_ref=$alt_file;
            }
        }
    }

    
function HookRse_VersionAllUpload_image_after_log_write($ref,$log_ref)
    {
    global $rse_version_override_groups, $rse_version_block, $usergroup;
    if(isset($rse_version_override_groups) && in_array($usergroup,$rse_version_override_groups) && isset($rse_version_block) && $rse_version_block)
        {
        // Versioning has been disabled, no action required.
        return false;
        }
    # After uploading an image and writing to the resource log, update the resource log so it stores the ref of the alternative file.
    global $previous_file_alt_ref;
    if (isset($previous_file_alt_ref))
        {
        $parameters=array("i",$previous_file_alt_ref, "i",$log_ref);
        ps_query("UPDATE resource_log set previous_file_alt_ref=? where ref=?", $parameters);
        }
    }

function HookRse_VersionAllGet_alternative_files_extra_sql($resource)
    {
    $extra_query = new PreparedStatementQuery();
    # Filter the alternative files view to exclude alternative files that have been created to store earlier revisions.
    # This removes them from both the resource view page and also the 'manage alternative files' area.
    $extra_query->sql = "AND ref not in (select previous_file_alt_ref 
            from resource_log where previous_file_alt_ref is not null and type='u' and resource=?)";
    $extra_query->parameters=array("i",$resource);
    return $extra_query;
    }


function HookRse_versionAllSave_resource_data_multi_extra_modes($ref,$field,$existing,$postvals,&$errors)
    {
    # Process the batch revert action - hooks in to the save operation (save_resource_data_multi())
    global $FIXED_LIST_FIELD_TYPES, $lang, $user_local_timezone;
    # Remove text/option(s) mode?
    if (($postvals["modeselect_" . $field["ref"]] ?? "") == "Revert")
        {
        $revert_date = $postvals["revert_" . $field["ref"]] ?? "";

        if(in_array($field["type"],$FIXED_LIST_FIELD_TYPES))
            {
            // Check if nodes can be reverted - only supported from date moved to system upgrade level 23 (v10.1)
            $revert_min_time = get_sysvar("fixed_list_revert_enabled");
            if($revert_min_time  > $revert_date)
                {
                $errors[$field["ref"]] = str_replace("[date]",$revert_min_time,$lang["rse_version_invalid_time"]);
                return false;
                }
            }

        # The incoming revert date is in local timezone; convert it to UTC for the fetch
        $dateTime_from = $revert_date; 
        $newDateTime = new DateTime($dateTime_from, new DateTimeZone($user_local_timezone)); 
        $newDateTime->setTimezone(new DateTimeZone("UTC")); 
        $revert_date = $newDateTime->format("Y-m-d H:i:s");

        # Find the value of this field as of this date and time in the resource log.
        $parameters=array("i",$ref, "i",$field["ref"], "s",$revert_date);
        $value=ps_value("SELECT previous_value value from resource_log 
            where resource=? and resource_type_field=? 
            and (type='e' or type='m') and date>? and previous_value is not null order by date limit 1",$parameters,-1);
        if ($value!=-1) {return $value;}
        // No log entries for this field, don't change
        return $existing;
        }
    return false;
    }

function HookRse_versionAllGet_resource_log_extra_fields()
    {
    # Extend get_resource_log so that the state of the previous value is fetched also.
    return new PreparedStatementQuery(
        ",previous_file_alt_ref, ((r.previous_value IS NOT NULL AND (r.type='e' OR r.type='m' OR r.type='N')) 
            OR (r.previous_file_alt_ref IS NOT NULL AND r.type='u')) revert_enabled"
        );
    }