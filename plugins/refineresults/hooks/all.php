<?php

function HookRefineresultsSearchBeforesearchresults()
{
    global $baseurl_short, $result, $lang, $search, $k, $collections;

    // Only time when this would be needed is when legacy_actions is enabled otherwise we do it through dropdown actions
    $query = 'SELECT inst_version AS `value` FROM plugins WHERE name = \'legacy_actions\';';

    if (trim((string)ps_value($query, array(), '')) === '') {
        return false;
    }

    $results = 0;

    if (is_array($result)) {
        $results = count($result);
    }

    if (is_array($collections)) {
        $results += count($collections);
    }

    # External sharing search support. Clear search drops back to the collection only search.
    $default_search = "";
    if ($k!="") {
        $s = explode(" ", $search);
        $default_search=$s[0];
    }

    # dropping back to a special search seems like appropriate behavior in general.
    if ($k == "" && substr($search, 0, 1) == "!") {
        $s = explode(" ", $search);
        $default_search=$s[0];
    }
    ?>

    <div class="SearchOptionNav">
        <?php if ($results != 0 && $results != 1) { ?>
            <a href="#" onClick="
                if (jQuery('#RefinePlus').html()=='+') {
                    jQuery('#RefineResults').slideToggle();
                    jQuery('#RefinePlus').html('&minus;');
                    jQuery('#refine_keywords').focus();
                } else {
                    jQuery('#RefineResults').slideToggle();
                    jQuery('#RefinePlus').html('+');
                }">
                <span id='RefinePlus'>+</span>
                <?php echo escape($lang["refineresults"]); ?>
            </a>
            &nbsp;&nbsp;
        <?php }
        
        if ($search != "") { ?>
            <a href='<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode($default_search); ?>'>
            &gt;&nbsp;
            <?php echo escape($lang["clearsearch"]); ?></a>
        <?php } ?>
        
    </div>

    <?php
    return true;
}
    
function HookRefineresultsSearchBeforesearchresultsexpandspace()
    {
    global $baseurl_short,$lang,$search,$k,$archive;

    # Slightly different behaviour when allowing external share searching. Show the full search string in the box.
    $value="";
    if ($k!="")
        {
        $s=explode(" ",$search);
        if (count($s)>1)
            {
            array_shift($s);
            $value=join(" ",$s);
            }
        }
    
    # Get the url parameters to pass back in to the search
    $modal          = getval('modal', '');
    $display        = getval('display', '');
    $order_by       = getval('order_by', '');
    $offset         = getval('offset', '');
    $per_page       = getval('per_page', '');
    $sort           = getval('sort', '');
    $restypes       = getval('restypes', '');
    $editable_only  = getval('foredit','')=='true';

    // Construct archive string and array
    $archive_choices = getval("archive", "");
    $selected_archive_states = array();
    if(!is_array($archive_choices))
        {
        $archive_choices = explode(",", $archive_choices);
        }
    foreach($archive_choices as $archive_choice)
        {
        if(is_numeric($archive_choice))
            {
            $selected_archive_states[] = $archive_choice;
            }  
        }
    $archive = implode(",", $selected_archive_states);

    $searchparams= array(
    'search'            => $search,
    'k'                 => $k,
    'modal'             => $modal,
    'display'           => $display,
    'order_by'          => $order_by,
    'offset'            => $offset,
    'per_page'          => $per_page,
    'archive'           => $archive,
    'sort'              => $sort,
    'restypes'          => $restypes,
    'recentdaylimit'    => getval('recentdaylimit', '', true),
    'foredit'           => ($editable_only?"true":"")
    );

    $searchparams = array_filter($searchparams);

    $search_url = generateURL('search.php', $searchparams);
    ?>
    
    <div class="RecordBox clearerleft" id="RefineResults" style="display:none;"> 
    
    <form method="post" action="<?php echo $search_url ?>" onSubmit="return CentralSpacePost (this,true);">
       <?php generateFormToken("refineresults_before_search_results"); ?>
    <div class="Question Inline" id="question_refine" style="border-top:none;">
    <label id="label_refine" for="refine_keywords"><?php echo escape($lang["additionalkeywords"]); ?></label>
    <input class="medwidth Inline" type=text id="refine_keywords" name="refine_keywords" value="<?php echo escape($value); ?>">
    <input type=hidden name="archive" value="<?php echo escape($archive); ?>">
    <input class="vshrtwidth Inline" name="save" type="submit" id="refine_submit" value="&nbsp;&nbsp;<?php echo escape($lang["refine"]); ?>&nbsp;&nbsp;" />
    <div class="clearerleft"> </div>
    </div>

    </form>

    </div>
    <?php
    
    return true;
    }

function HookRefineresultsSearchSearchstringprocessing()
{
    global $search,$k;
    $refine = trim(getval("refine_keywords",""));
    if ($refine != "") {
        if ($k != "") {
            # Slightly different behaviour when searching within external shares. There is no search bar, so the provided string is the entirity of the search.
            $s = explode(" ",$search);
            $search = $s[0] . " " . $refine;
        } elseif ((string) $search != "") {
            $search .= ", " . $refine;
        } else {
            $search = $refine;
        }
    }
    $search = refine_searchstring($search);
}

?>
