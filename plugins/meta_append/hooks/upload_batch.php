<?php

include __DIR__ . "../../config/config.php";

function HookMeta_appendAllAddtopluploadurl()
    {   
    $found_meta_append_field = getval("metaappend",false);  
    if ($found_meta_append_field)
        {       
        return ['metaappend' => $found_meta_append_field];
        }       
    }

function HookMeta_appendAllAfterpluploadfile()
    {
    global $meta_append_field_ref,$meta_append_date_format,$ref,$userref;
    
    $found_meta_append_field = getval("metaappend",false);
    
    if ($found_meta_append_field && $found_meta_append_field==$meta_append_field_ref && $ref > 0)       // make sure that the passed value is legal and ref looks legal
        {
        $result = get_data_by_field($ref, $meta_append_field_ref);
        
        if ('' == trim($result))
            {
            return;
            }               
        $value_string = $result;
        
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
        $new_value_string = $value_string . $date_string . $count_string;
        
        update_field($ref, $meta_append_field_ref, $new_value_string);
        
        }
        
    }
