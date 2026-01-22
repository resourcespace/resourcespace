<?php
/**
 * Manage news items (under Team Center)
 * 
  */

include __DIR__."/../../../include/boot.php";

include __DIR__."/../../../include/authenticate.php";if (!checkperm("o")) {exit ("Permission denied.");}
include_once __DIR__."/../inc/news_functions.php";
global $baseurl;

$offset=getval("offset",0,true);
if (array_key_exists("findtext",$_POST)) {$offset=0;} # reset page counter when posting
$findtext=getval("findtext","");

$delete=getval("delete","");
if ($delete!="" && enforcePostRequest(false))
    {
    # Delete news
    delete_news($delete);
    }

if (getval("create","")!="")
    {
    redirect("plugins/news/pages/news_content_edit.php?ref=new");
    }

include __DIR__."/../../../include/header.php";

?>

<div class="BasicsBox"> 
  <h1><?php echo escape($lang["news_manage"]); ?></h1>
  <h2><?php echo escape($lang["news_intro"]); ?></h2>
 
<?php 
$news=get_news("","",$findtext);

# pager
$per_page = $default_perpage_list;
$results=count($news);
$totalpages=ceil($results/$per_page);
$curpage=floor($offset/$per_page)+1;
$url="news_edit.php?findtext=".urlencode($findtext)."&offset=". $offset;
$jumpcount=1;
?>

<div class="BasicsBox">
    <form method="post">
        <?php generateFormToken("news_add"); ?> 
        <input name="create" type="submit" value="<?php echo escape($lang["news_add"]); ?>"/>
    </form>
</div>

<div class="TopInpageNav"><?php pager();    ?></div>


<form method=post id="newsform">
    <?php generateFormToken("newsform"); ?>
<input type=hidden name="delete" id="newsdelete" value="">


<div class="Listview">
<table class="ListviewStyle">
<tr class="ListviewTitleStyle">
<th><?php echo escape($lang["date"]); ?></th>
<th><?php echo escape($lang["news_headline"]); ?></th>
<th><?php echo escape($lang["news_body"]); ?></th>
<th><div class="ListTools"><?php echo escape($lang["tools"]); ?></div></th>
</tr>

<?php
for ($n=$offset;(($n<count($news)) && ($n<($offset+$per_page)));$n++)
    {
    ?>
    <tr>
    <td><div class="ListTitle"><?php echo escape($news[$n]["date"]);?></div></td>
    
    <td><div class="ListTitle"><?php echo "<a href=\"" . $baseurl . "/plugins/news/pages/news.php?ref=" . $news[$n]["ref"] . "\">" . escape($news[$n]["title"]);?></a></div></td>
    
    <td><?php echo escape(tidy_trim($news[$n]["body"],100)) ?></td>
    
    <td>
    <div class="ListTools">
        <a href="news_content_edit.php?ref=<?php echo $news[$n]["ref"]; ?>&backurl=<?php echo urlencode($url . "&offset=" . $offset . "&findtext=" . escape($findtext))?>"><?php echo LINK_CARET . $lang["action-edit"]; ?> </a>
        <a href="#" onclick="if (confirm('<?php echo escape($lang["confirm-deletion"]); ?>')) {document.getElementById('newsdelete').value='<?php echo $news[$n]["ref"]; ?>';document.getElementById('newsform').submit();} return false;"><?php echo LINK_CARET . $lang["action-delete"]; ?></a>
        </div>
    </td>
    </tr>
    <?php
    }
?>

</table>
</div>
<div class="BottomInpageNav"><?php pager(true); ?></div>
</div>

<div class="BasicsBox">
    <form method="post">
        <?php generateFormToken("news_search"); ?>
        <div class="Question">
            <label for="find"><?php echo escape($lang["news_search"]); ?><br/></label>
            <div class="tickset">
             <div class="Inline">           
            <input type=text placeholder="<?php echo escape($lang['searchbytext']); ?>" name="findtext" id="findtext" value="<?php echo escape($findtext)?>" maxlength="100" class="shrtwidth" />
            
            <input type="button" value="<?php echo escape($lang['clearbutton']); ?>" onClick="$('findtext').value='';form.submit();" />
            <input name="Submit" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["searchbutton"]); ?>&nbsp;&nbsp;" />
             
            </div>
            </div>
            <div class="clearerleft"> 
            </div>
        </div>
    </form>
</div>


<?php

include __DIR__."/../../../include/footer.php";