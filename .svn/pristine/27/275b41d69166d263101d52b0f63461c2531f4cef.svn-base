<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';

$ref=getval("ref","",true);
$error=false;
# fetch the current search (for finding similar matches)
$search=getval("search","");
$order_by=getval("order_by","relevance");
$offset=getval("offset",0,true);
$restypes=getval("restypes","");
if (strpos($search,"!")!==false) {$restypes="";}
$archive=getval("archive",0,true);

$default_sort="DESC";
if (substr($order_by,0,5)=="field"){$default_sort="ASC";}
$sort=getval("sort",$default_sort);

if (getval("save","")!="")
    {
    $details=getval("request","");
    $templatevars['username']=$username . " (" . $useremail . ")";
    $templatevars['url']=$baseurl."/?r=".$ref;

    $htmlbreak="";
    global $use_phpmailer;
    if ($use_phpmailer){$htmlbreak="<br><br>";}

    $templatevars['details']=$details;
    if ($templatevars['details']!=""){$adddetails=$lang["offline_archive_request_restore_reason"] . ": " . newlines($templatevars['details'])."\n\n";} else {return false;}


    $message=$lang["username"] . ": " . $username . " (" . $useremail . ")\n".$adddetails . $lang["clicktoviewresource"] . "\n\n". $templatevars['url'];

    $userconfirmmessage = $lang["offline_archive_requestsenttext"];
    $result=send_mail($email_notify,$applicationname . ": " . $lang["offline_archive_request_email_subject"] . " - $ref",$message,$useremail,$useremail);
    resource_log($ref,"",0,$lang['offline_archive_resource_log_restore_request'],"","");
    if ($result===false)
        {
        $error=$lang["requiredfields"];
        }
    }
include "../../../include/header.php";

?>
<div class="BasicsBox">
<?php
if (isset($userconfirmmessage))
    {
    echo "<div class=\"FormError\">" . escape($userconfirmmessage) . "</div>";
    }

?>

<div class="BasicsBox">

  <h1><?php echo escape($lang["offline_archive_request_restore"]); ?></h1>
  <p><?php echo escape($lang["offline_archive_request_restore_text"]); ?></p>

    <form method="post" name="request_restore_form" id="request_restore_form" action="<?php echo $baseurl ?>/plugins/offline_archive/pages/restore_request.php?ref=<?php echo urlencode($ref)?>&search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?>&archive=<?php echo urlencode($archive)?>">
    <?php generateFormToken("request_restore_form"); ?>
    <input type=hidden name=ref value="<?php echo escape($ref)?>">

    <div class="Question">
        <label><?php echo escape($lang["resourceid"]) ?></label>
        <div class="Fixed"><?php echo escape($ref)?></div>
        <div class="clearerleft"> </div>
    </div>


    <div class="Question">
        <label for="request"><?php echo escape($lang["offline_archive_request_restore_reason"]); ?> <sup>*</sup></label>
        <textarea class="stdwidth" name="request" id="request" rows=5 cols=50><?php echo escape(getval("request","")) ?></textarea>
        <div class="clearerleft"> </div>
    </div>

    <div class="QuestionSubmit">
        <?php if ($error)
            {?>
            <div class="FormError"><?php echo escape($error) ?></div><?php
            } ?>
        <input name="cancel" type="button" value="<?php echo escape($lang["cancel"]); ?>" onclick="document.location='<?php echo $baseurl_short?>pages/view.php?ref=<?php echo escape($ref)?>';"/>
        <input name="save" type="submit" value="<?php echo escape($lang["offline_archive_request_restore"]); ?>" />
        </div>
    </form>

</div>
</div>

<?php
include "../../../include/footer.php";
?>
