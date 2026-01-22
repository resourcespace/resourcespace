<?php
/**
 * Display news items  * 
 */

include __DIR__."/../../../include/boot.php";

include __DIR__."/../../../include/authenticate.php";
include_once __DIR__."/../inc/news_functions.php";

global $baseurl;

$max = get_news_ref("max");
$min = get_news_ref("min");
$maxref = $max[0]["max(ref)"];
$minref = $min[0]["min(ref)"];

if (isset($debugtext))
    {
    $debugtext.=" AND MORE: ";
    }
else
    {
    $debugtext="Debug: ";
    }

if (!isset($ref)){$ref=getval("ref","",true);}

if ((getval("edit","")!="") && (checkperm("o")))
    {
    redirect("plugins/news/pages/news_content_edit.php?ref=".$ref);
    }
        
if (getval("previous", "") != "")
    {
    $ref=getval("ref","",true);
    $ref--;
    redirect("plugins/news/pages/news.php?ref=".$ref);
    }

if (getval("next", "") != "")
    {
    $ref=getval("ref","",true);
    $ref++;
    redirect("plugins/news/pages/news.php?ref=".$ref);
    }

if ($ref=="")
    {
    header("location: ".$baseurl."/plugins/news/pages/news.php?ref=".$maxref);
    exit;
    }

$newsdisplay=get_news($ref,"","");

if (!$newsdisplay)
    {
    $debugtext.= " no news found";  
    while (!$newsdisplay)
        {
        if (getval("next", "") != "")   
            {
            $ref++;
            if ($ref>$maxref)
                {
                $ref=$minref;
                header('location: '.$baseurl.'/plugins/news/pages/news.php?ref='.$ref);
                exit;
                }               
            }
        else
            {
            $ref--;
            if ($ref<$minref)
                {
                $ref=$maxref;   
                header('location: '.$baseurl.'/plugins/news/pages/news.php?ref='.$ref);
                exit;
                }   
            }
        $newsdisplay=get_news($ref,"","");
        }

    header('location: '.$baseurl.'/plugins/news/pages/news.php?ref='.$ref); 
    exit;
    }
    
include __DIR__."/../../../include/header.php";

?>
 
<div>
<form id="NewsNav" action="<?php echo $baseurl . '/plugins/news/pages/news.php?ref=' . $ref ?>" method="post">
<?php generateFormToken("news"); ?> 
    <input name="previous" type="submit" value="&lt;"/> 
    <?php if (checkperm("o")) { ?>  
        <input name="edit" type="submit" value="<?php echo escape($lang["action-edit"]); ?>"/>
    <?php } ?>      
    <input name="next" type="submit" value="&gt;"/>
</div>
</form> 

<div class="BasicsBox" id="NewsDisplayBox"> 
    <h1><?php echo escape($newsdisplay[0]["title"]);?></h1>
    <hr>
    <div id="NewsBodyDisplay">
        <p>
            <?php
            // If the news item contains HTML we don't need to use nl2br
            echo $newsdisplay[0]["body"] !== strip_tags($newsdisplay[0]["body"])
                ? strip_tags_and_attributes($newsdisplay[0]["body"])
                : nl2br(escape($newsdisplay[0]["body"]));
            ?>
        </p>
    </div>
    <h2><?php echo $newsdisplay[0]["date"];?></h2>
</div>

<?php
include __DIR__."/../../../include/footer.php";