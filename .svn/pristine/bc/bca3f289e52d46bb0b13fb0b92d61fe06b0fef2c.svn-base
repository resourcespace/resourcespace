<?php
/**
 * Edit news items page 
 */
include __DIR__."/../../../include/boot.php";

include __DIR__."/../../../include/authenticate.php";if (!checkperm("o")) {exit ("Permission denied.");}
include_once __DIR__."/../inc/news_functions.php";

$ref=getval("ref","",true);
$offset=getval("offset",0,true);
$findtext=getval("findtext","");

$date=getval("date",date("Y-m-d H:i:s"));

$error = array();

$parts = array();
// Check the format of the date to "yyyy-mm-dd hh:mm:ss"
preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/", $date, $parts);

if (count($parts) < 6 || !checkdate($parts[2], $parts[3], $parts[1]))
    {
    # raise error - invalid date format
    $error[] = str_replace("%date%", $date, $lang["invalid_date_error2"]) ;
    }

$title=getval("title",0);

if (strlen($title) > 50)
    {
    $error[] = $lang['title-too-long'] ;
    }



$body=getval("body",0);

# get ref value from database, unless it is set to new 
if (getval("ref","")=="new"){$createnews=true;} else {$news=get_news($ref,"",""); $createnews=false;}

if (!isset($error[0]) && getval("save","")!=""  && enforcePostRequest(false))
    {
    # Save news
    if ($createnews) {
        add_news($date, $title, $body);
    } else {
        update_news($ref, $date, $title, $body);
    }
    redirect("plugins/news/pages/news_edit.php?findtext=".$findtext."&offset=".$offset);
    }
    
# Fetch news data
$news=get_news($ref,"","");
include __DIR__."/../../../include/header.php";
?>

<p id="EditNewsBack">
    <a href="news_edit.php?offset=<?php echo $offset?>&findtext=<?php echo escape($findtext)?>"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["news_manage"]); ?></a>
</p>

<div class="BasicsBox">
    <h1><?php echo escape($lang["news_edit"]); ?></h1>
    <span class="FormError">
    <?php 
    
    foreach ($error as $err)
        {
        echo "<p>" . $err . "</p>";
        }
    ?>
    </span>
    <form method=post id="mainform">
        <?php generateFormToken("mainform"); ?>

        <input type=hidden name=name value="<?php echo $ref?>">

        <div class="Question">
            <label><?php echo escape($lang["date"]); ?></label>
            <input name="date" class="stdwidth" type="text" value="<?php echo $createnews ? date("Y-m-d H:i:s") : $news[0]["date"]; ?>">
        </div>

        <div class="clearerleft"> </div>

        <div class="Question">
            <label><?php echo escape($lang["news_headline"]);?></label>
            <input name="title" class="stdwidth" type="text" value="<?php echo $createnews ? $lang["news_addtitle"] : $news[0]["title"]; ?>">
        </div>

        <div class="clearerleft"> </div>

        <div class="Question">
            <label><?php echo escape($lang["news_body"]); ?></label>
            <textarea name="body" class="stdwidth" rows="15" cols="50"><?php if (!$createnews) { echo escape($news[0]["body"]); } ?></textarea>
        </div>

        <div class="clearerleft"> </div>

        <div class="QuestionSubmit">
            <input name="save" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["save"]); ?>&nbsp;&nbsp;" />
        </div>
    </form>
</div>

<?php	  
include __DIR__."/../../../include/footer.php";