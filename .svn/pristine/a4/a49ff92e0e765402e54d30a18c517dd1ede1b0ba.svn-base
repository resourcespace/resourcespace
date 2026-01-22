<?php

function HookRse_versionEditEdit_all_extra_modes($field)
    {
    global $lang;
    ?>
    <option value="Revert"><?php echo escape($lang["revertmetadatatodatetime"]); ?></option>
    <?php
    }
    
    #edit_all_mode_js
    #edit_all_after_findreplace
    

function HookRse_versionEditEdit_all_mode_js()
    {
    # Add to the JS executed when a mode selector is changed on 'edit all', but not immediately after the preceding closing brace
    global $n;
    ?>
    var r=document.getElementById('revert_<?php echo (int) $n?>');
    if (this.value=='Revert')
        {
         /* 
        hide default input field(s) - q var
        show revert to state as of datetime - r var
        hide findreplace fr var
        */
       
        q.style.display='none';
        r.style.display='block';
        fr.style.display='none';  
        
        /* cf refers to copy from field - not always present, e.g. date field */
        if (typeof cf !== 'undefined') 
            {
            // the variable is defined
            cf.style.display='none';
            }
        
        let existingoptions = document.getElementById('displayexisting_<?php echo (int) $n?>');
        if (existingoptions !== null)
            {
            existingoptions.style.display='none';
            }

        }
    else 
        {
        r.style.display='none';
        }
    <?php
    }
    
function HookRse_versionEditEdit_all_after_findreplace($field,$n)
    {
    # Add a revert date/time box after 'edit all' mode selector when reversion mode selected.
    global $lang;
    $initial_revert_to_date=offset_user_local_timezone(date('YmdHis'), 'Y-m-d H:i');
    ?>
    <div class="Question" id="revert_<?php echo (int) $n?>" style="display:none;border-top:none;">
    <label>&nbsp;</label>
    <input type="text" name="revert_<?php echo (int) $field["ref"]; ?>" class="stdwidth" value="<?php echo escape($initial_revert_to_date); ?>" />
    </div>
    <?php
    }
    
function HookRse_versionEditBefore_status_question()
    {
    global $lang;
    ?>
    <script>
    jQuery(document).ready(function()
        {
        jQuery("#editthis_status").click(function()
            {
            var edit_mode_status          = jQuery("#edit_mode_status");
            var question_status           = jQuery("#question_status");
            var modeselect_status         = jQuery("#modeselect_status");
            var revert_status_to_date  = jQuery("#revert_status_to_date");

            if(jQuery(this).is(":checked") && question_status.is(":visible") !== false)
                {
                question_status.show();
                edit_mode_status.show();

                modeselect_status_onchange(modeselect_status);

                return true;
                }

            question_status.hide();
            edit_mode_status.hide();
            revert_status_to_date.hide();

            return true;
            });
        });
    </script>
    <div class="Question" id="edit_mode_status" style="display: none; padding-bottom: 0px; margin-bottom: 0px;">
        <label for="modeselect_status"><?php echo escape($lang["editmode"]); ?></label>
        <select id="modeselect_status" class="stdwidth" name="modeselect_status" onchange="modeselect_status_onchange(this);">
            <option value=""></option>
            <option value="revert"><?php echo escape($lang["revertmetadatatodatetime"]); ?></option>
        </select>
        <script>
        function modeselect_status_onchange(el)
            {
            var modeselect_status = jQuery(el);
            var question_status  = jQuery("#question_status");
            var revert_status_to_date  = jQuery("#revert_status_to_date");

            if(modeselect_status.val() == "revert")
                {
                question_status.hide();
                revert_status_to_date.show();

                return true;
                }

            question_status.show();
            revert_status_to_date.hide();

            return true;
            }
        </script>
        <div class="clearerleft"></div>
    </div>
    <div class="Question" id="revert_status_to_date" style="display: none; border-top: none;">
        <input type="text" name="revert_status_to_date" class="stdwidth" value="<?php echo date("Y-m-d H:i"); ?>" />
        <div class="clearerleft"></div>
    </div>
    <?php
    }


function HookRse_versionEditSave_resource_data_multi_set_archive_state($resource_ref, $old_archive)
    {
    if(getval("modeselect_status", "") !== "revert")
        {
        return false;
        }

    $revert_status_to_date = trim(getval("revert_status_to_date", ""));
    if($revert_status_to_date === "")
        {
        return $old_archive;
        }

    $parameters=array("i",$resource_ref, "s",$revert_status_to_date);
    $archive_status_at_date = ps_value("SELECT previous_value as `value`
                FROM resource_log
               WHERE resource = ? AND `type` = 's' AND previous_value IS NOT NULL AND `date` >= ?
            ORDER BY ref ASC
            LIMIT 1;",$parameters,null);

    if(!is_null($archive_status_at_date) && trim($archive_status_at_date) !== "" && is_numeric($archive_status_at_date))
        {
        return $archive_status_at_date;
        }

    return $old_archive;
    }