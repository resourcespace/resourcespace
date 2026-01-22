<?php


function HookPropose_changesCollection_editSavecollectionAdditionalfields()
    {
    global $propose_changes_always_allow;
    if(!$propose_changes_always_allow)
        {
        $propose_changes=(getval("propose_changes","")!=""?1:0);
        return "propose_changes='$propose_changes',";
        }
    return "";
    }
    
function HookPropose_changesCollection_editGetcollectionAdditionalfields()
    {
    global $propose_changes_always_allow;
    if(!$propose_changes_always_allow)
        {   
        return ", propose_changes "; 
        }           
    return "";
    }
    
function HookPropose_changesCollection_editAdditionalfields2()
    {
    global $propose_changes_always_allow;   
    if(!$propose_changes_always_allow)
        {   
        global $lang, $collection;
        ?>
        <div class="Question">
        <label for="propose_changes"><?php echo escape($lang["propose_changes_option"]); ?></label><input type=checkbox id="propose_changes" name="propose_changes" <?php if (isset($collection["propose_changes"]) && $collection["propose_changes"]==1) { ?>checked<?php } ?>>
        <div class="clearerleft"> </div>
        </div>
        <?php 
        }
    return true;
          
    }
    
    
