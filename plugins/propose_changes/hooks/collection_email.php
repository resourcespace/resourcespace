<?php


    
function HookPropose_changesCollection_emailAdditionalemailfield()
    {
    global $propose_changes_always_allow;   
    if(!$propose_changes_always_allow)
        {   
        global $lang, $collection;
        ?>
        <div class="Question">
        <label for="propose_changes"><?php echo escape($lang["propose_changes_option"]); ?></label><input type=checkbox id="propose_changes" name="propose_changes" <?php if ($collection["propose_changes"]==1) { ?>checked<?php } ?>>
        <div class="clearerleft"> </div>
        </div>
        <?php 
        }
    return true;
          
    }

function HookPropose_changesCollection_emailAdditional_email_collection($colrefs,$collectionname,$fromusername,$userlist,$message,$feedback,$access,$expires,$useremail,$from_name,$cc,$themeshare,$themename,$themeurlsuffix,$template,$templatevars)
    {
    global $propose_changes_always_allow;   
    if(!$propose_changes_always_allow)
        {   
        $propose_changes=(getval("propose_changes","")!=""?1:0);
        if($propose_changes)
            {
            $colrefs_array=explode(",",$colrefs);
            $colrefs_array = array_filter($colrefs_array, 'is_int_loose');
            if(count($colrefs_array) > 0) 
                {
                $colrefs=implode(",",$colrefs_array);
                echo $colrefs;
                $parameters=ps_param_fill($colrefs_array,"i");
                ps_query("update collection set propose_changes=1 where ref in (". ps_param_insert(count($colrefs_array)).")", $parameters);
                }
            }
        }
    return true;
          
    }   

    
    
