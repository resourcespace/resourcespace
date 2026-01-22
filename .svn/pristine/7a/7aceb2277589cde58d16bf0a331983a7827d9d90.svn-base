<?php
include dirname(__DIR__) . '/include/boot.php';
include RESOURCESPACE_BASE_PATH . '/include/authenticate.php';

if (canSeeAnnotationsFields() === []) {
    exit($lang['error-permissiondenied']);
}

$ref = getval("ref", 0, true);
$col = getval('collection', 0, false, is_positive_int_loose(...));
$previewpage = getval("previewpage", 1, true);

if ($col != 0) {
    $is_collection = true;
    $collection = get_collection($col);
    $resources = do_search("!collection" . $col);
    set_user_collection($userref, $col);
    refresh_collection_frame();
    $ref = "C" . $col;
    $realref = $col; // C allows us to distinguish a collection from a resource in the JS without adding extra params.
} else {
    $is_collection = false;
    $resources = do_search("!list" . $ref);
    $realref = $ref;
}

$annotate = count($resources) > 0;

# Fetch search details (for next/back browsing and forwarding of search params)
$search = getval("search", "");
$order_by = getval("order_by", "relevance");
$offset = getval("offset", 0, true);
$restypes = getval("restypes", "");
if (strpos($search, "!") !== false) {
    $restypes = "";
}
$archive = getval("archive", 0, true);

$default_sort_direction = "DESC";
if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}
$sort = getval("sort", $default_sort_direction);

include RESOURCESPACE_BASE_PATH . '/include/header.php';

// a unique id allows us to isolate this page's temporary files.
$annotateid = uniqid($ref);

$jpghttppath = get_annotate_file_path($realref, false, "jpg");

?>

<?php if ($annotate) {?>
<script type="text/javascript" language="JavaScript">
var annotate_previewimage_prefix = "";

(function(jQuery) {
    
     var methods = {
        
        preview : function() { 
            var url = '<?php echo $baseurl_short; ?>pages/annotate_pdf_gen.php';

            var formdata = jQuery('#annotateform').serialize() + '&preview=true'; 

            jQuery.ajax(url,{
            data: formdata,
            success: function(response) {jQuery(this).annotate('refresh',response);},
            complete: function(response) {
                jQuery('#error').html(response.responseText);
                if (response.responseText=="nothing"){
                    jQuery('#heading').hide();
                    jQuery('#configform').hide();
                    jQuery('#previewdiv').hide();
                    jQuery('#introtext').hide();
                    jQuery('#noannotations').show();
                    jQuery('#noannotations').html("There are no annotations.");
                } 
            },
            beforeSend: function(response) {loadIt();}
            });
        },
        
        refresh : function( pagecount ) { 

            document.previewimage.src = '<?php echo $jpghttppath;?>?' +  Date.now();
            if (pagecount>1)
                {
                jQuery('#previewPageOptions').show(); // display selector  
                pagecount++;
                curval=jQuery('#previewpage').val();
                jQuery('#previewpage')[0].options.length = 0;
    
                for (x=1;x<pagecount;x++)
                    { 
                    selected=false;
                    var selecthtml="";
                    if (x==curval){selected=true;}
                    if (selected){selecthtml=' selected="selected" ';}
                    jQuery('#previewpage').append('<option value='+x+' '+selecthtml+'>'+x+'/'+(pagecount-1)+'</option>');
                    }
                }
            else
                {
                jQuery('#previewPageOptions').hide();
                }
            },            
        revert : function() { 
            jQuery('#previewpage')[0].options.length = 0;
            jQuery('#previewpage').append(new Option(1, 1,true,true));
            jQuery('#previewpage').value=1;jQuery('#previewPageOptions').hide();
            jQuery(this).annotate('preview');
        }
    };

    jQuery.fn.annotate = function( method ) {

    // Method calling logic
    if ( methods[method] ) {
      return methods[ method ].apply( this, Array.prototype.slice.call( arguments, 1 ));
    }  

  };


})(jQuery) 
</script>
<script>
function loadIt() {
   document.previewimage.src = '<?php echo $baseurl_short?>gfx/images/ajax-loader-on-sheet.gif';}
</script>
<?php } ?>

<div class="BasicsBox" style="float:left;">

    <?php

    $urlparams = [
        'ref' => $ref,
        'search' => $search,
        'offset' => $offset,
        'order_by' => $order_by,
        'sort' => $sort,
        'archive' => $archive
    ];

    if (!$is_collection) {?>
    <p><a href="<?php echo generateURL($baseurl_short . 'pages/view.php', $urlparams, ['annotate' => 'true'])?>" onClick="return CentralSpaceLoad(this);"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresourceview"])?></a></p>
    <?php } else {?>
    <p><a href="<?php echo generateURL($baseurl_short . 'pages/search.php?', $urlparams, ['search' => '!collection' . substr($ref, 1)])?>" onClick="return CentralSpaceLoad(this);"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresults"])?></a></p>
    <?php } ?>

    <h1><?php echo escape($lang['annotate_pdf_sheet_tool']); ?></h1>

    <?php if ($annotate) {?>
    <div id="heading" style="float:left;margin-bottom:0;" >
        <p id="introtext"><?php echo escape($lang['annotate_pdf_intro_text'])?></p>
    </div>
    <div style="clear:left;"></div>

    <div id="configform" >

        <form method=post name="annotateform" id="annotateform" action="<?php echo $baseurl_short; ?>pages/annotate_pdf_gen.php" >
        <input type=hidden name="ref" value="<?php echo escape($ref)?>">
        <input type=hidden name="annotateid" value="<?php echo escape($annotateid)?>">
        <?php
        generateFormToken("annotateform");
        if ($is_collection) {?>
        <div class="Question">
        <label><?php echo escape($lang["collection"])?></label><div class="Fixed"><?php echo escape(i18n_get_collection_name($collection))?></div>
        <div class="clearerleft"> </div>
        </div>

        <?php } else { ?>
        <div class="Question">
        <label><?php echo escape($lang["resourcetitle"])?></label><div class="Fixed"><?php echo escape(i18n_get_translated($resources[0]['field' . $view_title_field]))?></div>
        <div class="clearerleft"> </div>
        </div>
        <?php } ?>

        <div class="Question">
        <label for="size"><?php echo escape($lang["size"])?></label>
        <select class="shrtwidth" name="size" id="size" onChange="jQuery().annotate('preview'); "><?php echo $papersize_select ?>
        </select>
        <div class="clearerleft"> </div>
        </div>

        <div name="previewPageOptions" id="previewPageOptions" class="Question" style="display:none">
        <label for="previewpage"><?php echo escape($lang['previewpage'])?></label>
        <select class="shrtwidth" name="previewpage" id="previewpage" onChange="jQuery().annotate('preview');   ">
        </select>
        </div>
        <div class="QuestionSubmit">    
        <input name="preview" type="button" value="&nbsp;&nbsp;<?php echo escape($lang["preview"])?>&nbsp;&nbsp;" onClick="jQuery().annotate('preview');    "/>
        <input name="save" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["create"])?>&nbsp;&nbsp;" />
        </div>
        </form>

    </div>
</div>

<div class="BasicsBox" style="float:left;">
    <div id="previewdiv" style="float:left;padding:0px -50px 15px 0;height:425px;margin-right:-50px">
        <img alt="" id="previewimage" name="previewimage" src=''/>
    </div>
</div>

    <?php }
    ?>
<div <?php if ($annotate) {
    ?>style="display:none;"<?php
     } ?> id="noannotations"><?php if (!$annotate) {
    echo escape($lang['annotate_pdf_no_annotations']);
     } ?></div></div>
<?php if ($annotate) {?>
<script>
    jQuery().annotate('preview');
</script>
<?php } ?>
<?php
include RESOURCESPACE_BASE_PATH . '/include/footer.php';
