<?php

include __DIR__ . "../../config/config.php";

const CHECKED_VALUE = "yes";

function HookMeta_appendAllAfterfielddisplay($field_id)
    {
    global $edit_autosave,$fields,$meta_append_field_ref,$meta_append_prompt;
    
    if ($edit_autosave || !isset($fields[$field_id]['ref']) || $fields[$field_id]['ref'] != $meta_append_field_ref)
        {
        return;     // this is not the meta append field we are looking for
        }
        
    $field_id .= "_meta_append";
        
?><div class="Question" id="question_<?php echo $field_id; ?>">
    <label for="field_<?php echo $field_id; ?>"><?php echo $meta_append_prompt; ?></label>
    <fieldset class="customFieldset" name="<?php echo $meta_append_prompt; ?>">
        <legend class="accessibility-hidden"><?php echo $meta_append_prompt; ?></legend>
        <table cellpadding="2" cellspacing="0">
            <tbody>
                <tr>
                    <td width="1"><input type="checkbox" id="field_<?php echo $field_id; ?>" name="field_<?php echo $meta_append_field_ref; ?>_meta_append" value="<?php echo CHECKED_VALUE;  ?>" checked="checked"></td>
                </tr>
            </tbody>
        </table>
    </fieldset>
</div>
<?php

    }
    
$found_meta_append_field_ref = false;
    
function HookMeta_appendAlleditbeforesave()
    {
    global $meta_append_field_ref, $found_meta_append_field_ref, $upload_then_edit, $meta_append_date_format, $userref; 

    if (isset($_POST["field_{$meta_append_field_ref}"]) && isset($_POST["field_{$meta_append_field_ref}_meta_append"]) && $_POST["field_{$meta_append_field_ref}_meta_append"] == CHECKED_VALUE)
        {
        if($upload_then_edit)
            {
            if (trim(getval('field_'.$meta_append_field_ref, '')) == '')
            {
            return;
            }       
            $result = ps_query("SELECT ref FROM resource WHERE date(creation_date) = curdate() AND created_by = ?", array("i", $userref));      
            if (!isset($result[0]))
                {
                $count = 1;
                }
                else
                {
                $count = count($result);
                }
            $count_string = str_pad($count,4,"0", STR_PAD_LEFT);    
            $date_string = date($meta_append_date_format);
            $_POST['field_'.$meta_append_field_ref] .= $date_string . $count_string;
            }
        else
            {
            $found_meta_append_field_ref = $meta_append_field_ref;      
            unset ($_POST["field_{$meta_append_field_ref}_meta_append"]);       // remove from the POST data at earliest stage and set flag in this scope ready for addtouploadurl() hook
            }
        }
    }
    
function HookMeta_appendAllAddtouploadurl()
    {
    global $found_meta_append_field_ref;
    if ($found_meta_append_field_ref)
        {
        return "&metaappend=" . $found_meta_append_field_ref;       // pass on to uploader via URL
        }
    }
    
    
    
