<?php


function HookPropose_changesViewAfterresourceactions()
    {
    global $ref, $search,$offset,$archive,$sort, $order_by, $userref, $edit_access, $access, $propose_changes_always_allow, $propose_changes_allow_open;
    
    if($edit_access)
        {
        $userproposals= ps_value("select count(*) value from propose_changes_data where resource=?",array("i",$ref),0);
        if ($userproposals>0)
            {
            global $baseurl, $lang;
            ?>
            <li><a href="<?php echo $baseurl ?>/plugins/propose_changes/pages/propose_changes.php?ref=<?php echo urlencode($ref)?>&amp;search=<?php echo urlencode($search)?>&amp;search_offset=<?php echo urlencode($offset)?>&amp;order_by=<?php echo urlencode($order_by)?>&amp;sort=<?php echo urlencode($sort)?>&amp;archive=<?php echo urlencode($archive)?>" onClick="return CentralSpaceLoad(this,true);"><?php echo "<i class='icon-lightbulb'></i>&nbsp;" . $lang["propose_changes_review_proposed_changes"]; ?></a></li>
            <?php 
            }
        }
    else
        {
        $proposeallowed="";
        if(!$propose_changes_always_allow)
            {
            # Check user has permission.
            if($propose_changes_allow_open && $access==0)
                {
                $proposeallowed=$ref;
                }
            else
                {
                $parameters=array("i",$userref, "i",$ref);
                $proposeallowed=ps_value("SELECT cr.resource value 
					from user_collection uc 
					left join collection_resource cr
					on uc.collection=cr.collection
					left join collection c
					on c.ref=uc.collection 
					where uc.user=? and cr.resource=? and c.propose_changes=1",$parameters,"");
                }                        
            }

        if($propose_changes_always_allow || $proposeallowed!="")    
            {
            global $baseurl, $lang;
            ?>
            <li><a href="<?php echo $baseurl ?>/plugins/propose_changes/pages/propose_changes.php?ref=<?php echo urlencode($ref)?>&amp;search=<?php echo urlencode($search)?>&amp;search_offset=<?php echo urlencode($offset)?>&amp;order_by=<?php echo urlencode($order_by)?>&amp;sort=<?php echo urlencode($sort)?>&amp;archive=<?php echo urlencode($archive)?>" onClick="return CentralSpaceLoad(this,true);"><?php echo "<i class='icon-lightbulb'></i>&nbsp;".$lang["propose_changes_short"]; ?></a></li>
            <?php            
            }
        }
    
    }
