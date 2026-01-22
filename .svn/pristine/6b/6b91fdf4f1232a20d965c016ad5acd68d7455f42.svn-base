<?php
include_once "../include/boot.php";

if($annotate_enabled)
    {
    include_once '../include/annotation_functions.php';
    }

# External access support (authenticate only if no key provided, or if invalid access key provided)
$s = getval("search","");
if (is_array($s))
    {
    redirect($baseurl . "/pages/search.php");
    }
$s = explode(" ", $s);

$k = getval("k","");
$resetlockedfields = getval("resetlockedfields","") != "";

if (($k=="") || (!check_access_key_collection(str_replace("!collection","",$s[0]),$k))) {include "../include/authenticate.php";}

// Set a flag for logged in users if $external_share_view_as_internal is set and logged on user is accessing an external share
$internal_share_access = internal_share_access();

if ($k=="" || $internal_share_access)
    {
    #note current user collection for add/remove links if we haven't got it set already
    if(!isset($usercollection))
        {
        if((isset($anonymous_login) && ($username==$anonymous_login)) && isset($rs_session) && $anonymous_user_session_collection)
            {   
            $sessioncollections=get_session_collections($rs_session,$userref,true); 
            $usercollection=$sessioncollections[0];                  
            }
        else
            {
            if(isset($user))
                {
                $user=get_user($userref);
                }
            $usercollection=$user['current_collection'];
            }
        }
    // Get usercollection resources - used for checks against the list (e.g is one of the resources found by search in the collection?)
    $usercollection_resources = get_collection_resources($usercollection);
    }

// Disable checkboxes for external users.
$use_selection_collection = true;
if($k != "" && !$internal_share_access || (isset($anonymous_login) && $username == $anonymous_login && !$anonymous_user_session_collection))
    {
    $use_selection_collection = false;
    }

$search = getval('search', '');
$modal  = ('true' == getval('modal', ''));
$collection_add=getval("collection_add",""); // Need this if redirected here from upload
$initial_search_cookie = (isset($_COOKIE['search']) ? trim(strip_leading_comma($_COOKIE['search'])) : '');
$initial_restypes_cookie = (isset($_COOKIE['restypes']) ? trim($_COOKIE['restypes']) : '');
$initial_saved_archive_cookie = (isset($_COOKIE['saved_archive']) ? trim($_COOKIE['saved_archive']) : '');

if(false !== strpos($search, TAG_EDITOR_DELIMITER))
    {
    $search = str_replace(TAG_EDITOR_DELIMITER, ' ', $search);
    }

hook("moresearchcriteria");

if (
    preg_match('/^[^\\s]+\\*/',$search)
    || preg_match('/^\\*[^\\s]+$/',$search)
) {
    // No spaces in string and wildcard search - only search for wildcard with whole string
    $keywords = [$search];
} else {
    // When searching for specific field options we convert search into nodeID search format (@@nodeID)
    // This is done because if we also have the field displayed and we search for country:France this needs to 
    // convert to @@74 in order for the field to have this option selected
    $keywords = split_keywords($search, false, false, false, false, true);
}
foreach($keywords as $keyword)
    {
    if('' == trim($keyword))
        {
        continue;
        }

    if(false === strpos($search, ':'))
        {
        continue;
        }
    if(substr($keyword,0,1) =="\"" && substr($keyword,-1,1) == "\"")
        {
        $specific_field_search=explode(":",substr($keyword,1,-1));
        }
    else
        {
        $specific_field_search = explode(':', $keyword);
        }

    if(2 !== count($specific_field_search))
        {
        continue;
        }

    $field_shortname = trim($specific_field_search[0]);

    if('' == $field_shortname)
        {
        continue;
        }

    $resource_type_field = ps_value("SELECT ref AS `value` FROM resource_type_field WHERE `name` = ?", array("s",$field_shortname), 0, "schema");
    if(0 == $resource_type_field || !metadata_field_view_access($resource_type_field))
        {
        // User can't search against a metadata field they don't have access to
        continue;
        }

    $resource_type_field = get_resource_type_field($resource_type_field);
    $nodes = get_nodes($resource_type_field["ref"], null, $resource_type_field["type"]==FIELD_TYPE_CATEGORY_TREE);
    
    // Check if multiple nodes have been specified for an OR search
    $keywords_expanded=explode(';',$specific_field_search[1]);
    $subnodestring = "";
    if(count($keywords_expanded) > 1) 
        {
        foreach($keywords_expanded as $keyword_expanded)
            {
            $node_found = get_node_by_name($nodes, $keyword_expanded);

            if (0 == count($node_found) && count($nodes) == 10000) {
                // get_nodes only returns 10k nodes at most check if this node is outside of these
                $node_found = get_nodes(
                    $resource_type_field["ref"],
                    null,
                    $resource_type_field["type"]==FIELD_TYPE_CATEGORY_TREE,
                    null,
                    null,
                    $keyword_expanded
                )[0] ?? [];
            }
            if(0 < count($node_found))
                {
                $subnodestring .= NODE_TOKEN_PREFIX . $node_found['ref'];
                }
            }
        if($subnodestring != "")
            {
            $search = str_ireplace($keyword, $subnodestring, $search);
            }
        }
    }
    
# create a display_fields array with information needed for detailed field highlighting
$df=array();


$all_field_info=get_fields_for_search_display(array_unique(array_merge($sort_fields,$thumbs_display_fields,$list_display_fields)));

# get display and normalize display specific variables
$display=getval("display",$default_display);rs_setcookie('display', $display,0,"","",false,false);

switch ($display)
    {
    case "list":
        $display_fields = $list_display_fields; 
        $results_title_trim = $list_search_results_title_trim;
        break;
        
    case "xlthumbs":            
    case "thumbs": 
    case "strip":
    case 'map':
        
    default:
        $display_fields = $thumbs_display_fields;  
        if (isset($search_result_title_height)) { $result_title_height = $search_result_title_height; }
        $results_title_trim = $search_results_title_trim;
        $results_title_wordwrap = $search_results_title_wordwrap;
        break;      
    }

$n=0;
foreach ($display_fields as $display_field)
    {
    # Find field in selected list
    $all_field_info_count = count($all_field_info);
    for ($m=0;$m<$all_field_info_count;$m++)
        {
        if ($all_field_info[$m]["ref"]==$display_field && $all_field_info[$m]['active'] == 1)
            {
            $field_info=$all_field_info[$m];
            $df[$n]['ref']=$display_field;
            $df[$n]['type']=$field_info['type'];
            $df[$n]['indexed']=$field_info['keywords_index'];
            $df[$n]['partial_index']=$field_info['partial_index'];
            $df[$n]['name']=$field_info['name'];
            $df[$n]['title']=$field_info['title'];
            $df[$n]['value_filter']=$field_info['value_filter'];
            ++$n;
            }
        }
    }
$n=0;   
$df_add=hook("displayfieldsadd");
# create a sort_fields array with information for sort fields
$n=0;
$sf=array();
foreach ($sort_fields as $sort_field)
    {
    # Find field in selected list
    $all_field_info_count = count($all_field_info);
    for ($m=0;$m<$all_field_info_count;$m++)
        {
        if ($all_field_info[$m]["ref"]==$sort_field)
            { 
            $field_info=$all_field_info[$m];
            $sf[$n]['ref']=$sort_field;
            $sf[$n]['title']=$field_info['title'];
            ++$n;
            }
        }
    }
$n=0;   

$saved_search=$search;

# Append extra search parameters from the quick search.
if (!$config_search_for_number || !is_numeric($search)) # Don't do this when the search query is numeric, as users typically expect numeric searches to return the resource with that ID and ignore country/date filters.
    {
    // For the simple search fields, collect from the GET and POST requests and assemble into the search string.
    $search = update_search_from_request($search);
    }

$searchresourceid = "";
if (is_numeric(trim(getval("searchresourceid","")))){
    $searchresourceid = trim(getval("searchresourceid",""));
    $search = "!resource$searchresourceid";
}

// Is this a collection search?
$collection_search_strpos = strpos($search, "!collection");
$collectionsearch = $collection_search_strpos !== false && $collection_search_strpos === 0; // We want the default collection order to be applied
if($collectionsearch)
    {
    // Collection search may also have extra search keywords passed to search within a collection
    $search_trimmed = substr($search,11); // The collection search must always be the first part of the search string
    $search_elements = split_keywords($search_trimmed, false, false, false, false, true);
    $collection = (int)array_shift($search_elements);
    if(count($search_elements) > 0)
        {
        $search = "!collection" . $collection . " " . implode(", ",$search_elements);
        }
    }

hook("searchstringprocessing");

# Fetch and set the values
$offset=getval("offset",0,true);if (strpos($search,"!")===false) {rs_setcookie('saved_offset', $offset,0,"","",false,false);}
$offset = intval($offset); 
if ($offset<0) {$offset=0;} 

$order_by=getval("order_by","");
if (strpos($search,"!")===false || strpos($search,"!properties")!==false) 
    {
    rs_setcookie('saved_order_by', $order_by,0,"","",false,false);
    }
if ($order_by=="")
    {
    if ($collectionsearch) // We want the default collection order to be applied
        {
        $order_by=$default_collection_sort;
        }
    elseif (substr($search,0,14)=="!contributions")
        {
        // As Added is the initial sort sequence to be used for contribution searches 
        $order_by="resourceid";
        }
    else
        {
        $order_by=$default_sort;
        }
    }

if (substr($order_by,0,5)=="field")
    {
    $order_by_field = substr($order_by,5);
        {
        if(!metadata_field_view_access($order_by_field))
            {
            $order_by = 'relevance';
            }
        }
    }

$per_page=getval("per_page",get_per_page_cookie(), true); 
$per_page= (!in_array($per_page,$results_display_array)) ? $default_perpage : $per_page;

set_per_page_cookie($per_page);

// Clear special selection collection if user runs a new search. Paging is not a new search. Also we allow for users that
// want to see what they've selected so far. Client side we can POST clear_selection_collection=no to prevent it from clearing
// (e.g when batch editing)
$clear_selection_collection = (getval("clear_selection_collection", "") != "no");
$paging_request = in_array(getval("go", ""), array("next", "prev", "page"));

// After a batch edit, check if search has been instructed to check for resources in the selection collection that have 
// fallen out of the results so should no longer be selected
$check_selection_collection = getval("check_selection_collection", "no") == "yes";

// Preserve selection on display layout change (not available for map view).
$displaytypes = array('xlthumbs', 'thumbs', 'strip', 'list');
if (isset($_POST['display']))
    {
    $thumbtypechange = in_array($_POST['display'], $displaytypes);
    }
elseif (!isset($_POST['display']) && isset($_GET['display']))
    {
    $thumbtypechange = in_array($_GET['display'], $displaytypes);
    }
else
    {
    $thumbtypechange = false;
    }

$view_selected_request = ($use_selection_collection && mb_strpos($search, "!collection{$USER_SELECTION_COLLECTION}") !== false);
if($use_selection_collection && $clear_selection_collection && !$paging_request && !$thumbtypechange && !$view_selected_request && !is_null($USER_SELECTION_COLLECTION))
    {
    remove_all_resources_from_collection($USER_SELECTION_COLLECTION);
    }

// Construct archive string and array
$archive_choices=getval("archive","");
$archive_standard = $archive_choices=="";
$selected_archive_states = array();
if(!is_array($archive_choices)){$archive_choices=explode(",",$archive_choices);}
foreach($archive_choices as $archive_choice)
    {
    if(is_numeric($archive_choice)) {$selected_archive_states[] = $archive_choice;}  
    }

$archive = implode(",",$selected_archive_states);
$archivesearched = in_array(2,$selected_archive_states);

// Disable search through all workflow states when an archive state is specifically requested
// This prevents links like 'View deleted resources' showing resources in all states
if($search_all_workflow_states && !$archive_standard)
    {
    $search_all_workflow_states = false;
    }

if(false === strpos($search, '!'))
    {
    rs_setcookie('saved_archive', $archive,0,"","",false,false);
    }

if($resetlockedfields)
    {
    // Reset locked metadata fields cookie after editing resources in upload_review_mode
    rs_setcookie('lockedfields', '',0,"","",false,false);
    }

$jumpcount=0;

if (getval('recentdaylimit', '', true)!="") //set for recent search, don't set cookie
    {
    $daylimit=getval('recentdaylimit', '', true);
    }
else {$daylimit="";} // clear cookie for new search

if ($order_by=="field{$date_field}")
    {
    $default_sort_direction="DESC";
    }
elseif ($order_by=="collection")
    {
    $default_sort_direction="ASC";
    }
$sort=getval("sort",$default_sort_direction);rs_setcookie('saved_sort', $sort,0,"","",false,false);
$revsort = ($sort=="ASC") ? "DESC" : "ASC";

## If displaying a collection
# Enable/disable the reordering feature. Just for collections for now.
$allow_reorder=false;

# get current collection resources to pre-fill checkboxes
if($use_selection_collection)
    {
    if(is_null($USER_SELECTION_COLLECTION))
        {
        $selection_collection_resources = array();
        $selection_collection_resources_count = 0;        
        }
    else
        {
        $selection_collection_resources = get_collection_resources($USER_SELECTION_COLLECTION);
        $selection_collection_resources_count = count($selection_collection_resources);
        }
    }

$hiddenfields=getval("hiddenfields","");

# fetch resource types from query string and generate a resource types cookie
if (getval("resetrestypes","")=="")
    {
    $restypes=getval("restypes","");
    }
else
    { 
    $restypes="";
    reset($_POST);reset($_GET);foreach (array_merge($_GET, $_POST) as $key=>$value)

        {
        $hiddenfields = array();
        if ($key=="rttickall" && $value=="on"){$restypes="";break;} 
        if ((substr($key,0,8)=="resource")&&!in_array($key, $hiddenfields)) {if ($restypes!="") {$restypes.=",";} $restypes.=substr($key,8);}
        }

    rs_setcookie('restypes', $restypes,0,"","",false,false);

    # This is a new search, log this activity
    if ($archivesearched) {daily_stat("Archive search",0);} else {daily_stat("Search",0);}
    }
$modified_restypes=hook("modifyrestypes_aftercookieset");
if($modified_restypes){$restypes=$modified_restypes;}

# If returning to an old search, restore the page/order by and other non search string parameters
$old_search = (!array_key_exists('search', $_GET) && !array_key_exists('search', $_POST));
if ($old_search)
    {
    $offset=getval("saved_offset",0,true);rs_setcookie('saved_offset', $offset,0,"","",false,false);
    $order_by=getval("saved_order_by","relevance");
    if ($collectionsearch) // We want the default collection order to be applied
        {
        $order_by=$default_collection_sort;
        }
    else
        {
        $order_by=$default_sort;
        }
    rs_setcookie('saved_order_by', $order_by,0,"","",false,false);
    $sort=getval("saved_sort","");rs_setcookie('saved_sort', $sort,0,"","",false,false);
    $archivechoices=getval("saved_archive",0);rs_setcookie('saved_archive', $archivechoices,0,"","",false,false);
    if(!is_array($archivechoices)){$archivechoices=explode(",",$archivechoices);}
    foreach($archivechoices as $archivechoice)
        {
        if(is_numeric($archivechoice)) {$selected_archive_states[] = $archivechoice;}  
        }
    $archive=implode(",",$selected_archive_states);
    }
    
hook("searchparameterhandler"); 
    
# If requested, refresh the collection frame (for redirects from saves)
if (getval("refreshcollectionframe","")!="")
    {
    refresh_collection_frame();
    }

# Initialise the results references array (used later for search suggestions)
$refs=array();

# Special query? Ignore restypes
if(
    mb_strpos($search, '!') !== false
    && !$special_search_honors_restypes
    // Except for these special searches
    && substr($search, 0, 11) !== '!properties'
    && substr($search, 0, 7) !== '!report'
)
    {
    $restypes = '';
    }

# Do the search!
$search=refine_searchstring($search);

$editable_only = getval("foredit","")=="true";

$get_post_array = array_merge($_GET, $_POST);
$search_access = null; # admins can search for resources with a specific access from advanced search
if(array_key_exists("access", $get_post_array))
    {
    $search_access = $get_post_array["access"];
    }
rs_setcookie("access", $search_access, 0, "{$baseurl_short}pages/", "", false, false);

$searchparams= array(
    'search'         => $search,
    'k'              => $k,
    'modal'          => $modal,  
    'display'        => $display,
    'order_by'       => $order_by,
    'offset'         => $offset,
    'per_page'       => $per_page,
    'archive'        => $archive,
    'sort'           => $sort,
    'restypes'       => $restypes,
    'recentdaylimit' => getval('recentdaylimit', '', true),
    'foredit'        => ($editable_only?"true":""),
    'noreload'       => "true",
    'access'         => $search_access,
);

if (!empty($selected_archive_states))
    {
    // This is an advanced search.
    $searchparams['advsearch'] = "true";
    }

$checkparams = array();
$checkparams[] = "order_by";
$checkparams[] = "sort";
$checkparams[] = "display";
$checkparams[] = "k";

foreach($checkparams as $checkparam)
    {
    if(preg_match('/[^a-z:_\-0-9]/i', $$checkparam))
        {
        exit($lang['error_invalid_input'] . ":- <pre>" . $checkparam . " : " . escape($$checkparam) . "</pre>");
        }
    }

$order_by = check_order_by_in_table_joins($order_by);

if (preg_match("/@{2}!/", $search) || false === strpos($search, '!') || '!properties' == substr($search, 0, 11) )
    {
    rs_setcookie('search', $search,0,"","",false,false);
    }
else
    {
    rs_setcookie('search', "",0,"","",false,false);    
    }

# set cookie when search form has been submitted - controls display of search results link in header_links.php
if( isset($_REQUEST["search"]) && $_REQUEST["search"] == "" )
    {
    rs_setcookie('search_form_submit', true,0,"","",false,false);
    }
hook('searchaftersearchcookie');

$rowstoretrieve = (!$disable_geocoding && $display == "map") ? $search_map_max_results : $per_page;

// Do collections search first as this will determine the rows to fetch for do_search() - not for external shares
if(($k=="" || $internal_share_access) 
    && strpos($search,"!")===false
    && ($archive_standard || in_array(0,$selected_archive_states))
    && $display !== "map"
    )
    {
    $collections=do_collections_search($search,$restypes,0,$order_by,$sort,$rowstoretrieve);
    if(is_array($collections))
        {
        $colcount = count($collections);
        } 
    else
        {
        $colcount = 0;
        }

    // Get the number of resources required after collections have been displayed
    $cols_this_page = max($colcount-$offset,0);
    $resourcestoretrieve = $per_page - $cols_this_page;
    }
else
    {
    $colcount = 0;
    $resourcestoretrieve = $rowstoretrieve;
    }

if ($search_includes_resources || substr($search,0,1)==="!")
    {
    $search_includes_resources=true; // Always enable resource display for special searches.
    if (!hook("replacesearch"))
        {
        if ($use_selection_collection && $check_selection_collection) {
            $full_search_results = do_search($search,$restypes,$order_by,$archive,-1,$sort,false,DEPRECATED_STARSEARCH,false,false,$daylimit, getval("go",""), true, true, $editable_only, false, $search_access,false,true);
        }
        $result=do_search($search,$restypes,$order_by,$archive,[max($offset-$colcount,0),$resourcestoretrieve],$sort,false,DEPRECATED_STARSEARCH,false,false,$daylimit, getval("go",""), true, false, $editable_only, false, $search_access,false,true);
        }
    }
else
    {
    $result=["total"=>0,"data"=>[]]; # Do not return resources (e.g. for collection searching only)
    }

// Convert structured results back to a simple array for display
if(isset($result["total"]))
    {
    $result_count   = $result["total"];
    $result         = $result["data"];
    }
else
    {
    $result_count   = 0;
    }
// Log the search and attempt to reduce log spam by only recording initial searches. Basically, if either of the search 
// string or resource types or archive states changed. Changing, for example, display or paging don't count as different
// searches.
$same_search_param = (trim(strip_leading_comma($search)) === $initial_search_cookie);
$same_restypes_param = (trim($restypes) === $initial_restypes_cookie);
$same_archive_param = (trim($archive) === $initial_saved_archive_cookie);
if(!$old_search && (!$same_search_param || !$same_restypes_param || !$same_archive_param))
    {
    log_search_event(trim(strip_leading_comma($search)), explode(',', $restypes), explode(',', $archive), $result_count);
    }

if ($collectionsearch)
    {
    $collectiondata = get_collection($collection);  

    if ($k!="" && !$internal_share_access) {$usercollection=$collection;} # External access - set current collection.
    if (!$collectiondata)
        {
        if (isset($_GET['search'])) 
            {
                error_alert($lang["error-collectionnotfound"],true);
                exit;
            }
        error_alert($lang["error-collectionnotfound"],false);
        exit;
        } 
    # Check to see if this user can edit (and therefore reorder) this resource
    if (($userref==$collectiondata["user"]) || ($collectiondata["allow_changes"]==1) || (checkperm("h")))
        {
        $allow_reorder=true;
        }
    }

# Include function for reordering
if ($allow_reorder && $display!="list")
    {
    # Also check for the parameter and reorder as necessary.
    $reorder=getval("reorder",false);
    if ($reorder)
        {
        $neworder=json_decode(getval("order",false));
        update_collection_order($neworder,$collection,$offset);
        exit("SUCCESS");
        }
    }

include "../include/search_title_processing.php";

# Special case: numeric searches (resource ID) and one result: redirect immediately to the resource view.
if ((($config_search_for_number && is_numeric($search)) || $searchresourceid > 0) && is_array($result) && count($result)==1)
    {
    redirect(generateURL($baseurl_short."pages/view.php",$searchparams,array("ref"=>$result[0]["ref"])));
    }
    

# Include the page header to and render the search results
include "../include/header.php";
if($k=="" || $internal_share_access)
    {
     ?>
    <script type="text/javascript">
    var dontReloadSearchBar=<?php echo getval('noreload', null)!=null ? 'true' : 'false' ?>;
    if (dontReloadSearchBar !== true)
        ReloadSearchBar();
    ReloadLinks();
    </script>
    <?php
    }
if($use_selection_collection) {

    $resource_not_in_search = array();
    
    if($check_selection_collection) {
        
        // Clean up the user selection collection so that only resources in the current search can exist in the colleciton. 
        $selection_collection = do_search('!collection'. $USER_SELECTION_COLLECTION);
        $resource_not_in_search = array_diff(
            array_column($selection_collection, 'ref'),
            is_array($full_search_results)?array_column($full_search_results, 'ref'):[]
        );

        if (count($resource_not_in_search) > 0) {
            collection_remove_resources($USER_SELECTION_COLLECTION, $resource_not_in_search);
        }
    }

    ?>
    <script>
    <?php 
    $clear = count(do_search('!collection'. $USER_SELECTION_COLLECTION)) == 0 ? 'true' : 'false';
    if (count($resource_not_in_search) > 0) {
        ?>
        UpdateSelectedResourcesCounter(<?php echo $clear; ?>);
        UpdateSelectedBtns(<?php echo $clear; ?>);
        <?php
    }
    ?>
    var searchparams = <?php echo json_encode($searchparams); ?>;
    </script>
    <?php
}

// Allow Drag & Drop from collection bar to CentralSpace only when special search is "!collection"
if($collectionsearch && collection_writeable(substr($search, 11)))
    {
    ?>
    <script>
        jQuery(document).ready(function() {
        if(is_touch_device())
            {
            return false;
            }
            // Element #CentralSpaceResources (search results) can be dropped on by .CollectionPanelShell elements (collection bar)
            jQuery('#CentralSpaceResources').droppable({
                accept: '.CollectionPanelShell',

                drop: function(event, ui) {
                    if(!is_special_search('!collection', 11)) {
                        return false;
                    }

                    // get the current collection from the search page (ie. CentralSpace)
                    var query_strings = getQueryStrings();
                    if(is_empty(query_strings)) {
                        return false;
                    }

                    var resource_id = jQuery(ui.draggable).attr("id");
                    resource_id = resource_id.replace('ResourceShell', '');
                    var collection_id = query_strings.search.substring(11);

                    jQuery('#trash_bin').hide();
                    AddResourceToCollection(event, ui, resource_id, '', collection_id);
                    CentralSpaceLoad(window.location.href, true);
                }
            });

            jQuery('#CentralSpace').trigger('CentralSpaceSortable');
        });
    </script>
    <?php
    }

if(!$collectionsearch)
    {
    ?>
    <!-- Search item results in centralspace have a class of "ResourcePanel" -->
    <!-- These items should be draggable to add them to the collection in the collection bar if results are NOT from collection search -->
    <!-- They should also be draggable to the trash_bin to removing them from a collection if results ARE from collection search -->
    <script>    
    // The below numbers are hardcoded mid points for thumbs and xlthumbs
    var thumb_vertical_mid = <?php if($display=='xlthumbs'){?>197<?php } else {?>123<?php }?>;
    var thumb_horizontal_mid = <?php if($display=='xlthumbs'){?>160<?php } else {?>87<?php }?>;
    jQuery(document).ready(function() {
        if(is_touch_device())
            {
            return false;
            }
        jQuery('.ResourcePanel').draggable({
            distance: 20,
            connectWith: '#CollectionSpace, .BrowseBarLink',
            appendTo: 'body',
            zIndex: 99000,
            helper: 'clone',
            revert: false,
            scroll: false,
            cursorAt: {top: thumb_vertical_mid, left: thumb_horizontal_mid},
            drag: function (event, ui)
                {
                jQuery(ui.helper).css('opacity','0.6');
                jQuery(ui.helper).css('transform','scale(0.8)');
                },
        });
    });
    </script>
    <?php
    }
    
// The sortable method must be enabled regardless of the order_by so that the trash bin is available for interactions from CentralSpace
// This allows resources to be removed from collection via the trash bin, but will abandon reorder attempts unless order_by is "collection"
if ($allow_reorder && $display!="list") {
    global $usersession;
?>
    <script type="text/javascript">
    var allow_reorder = true;

    var use_sortable_for_trash_only = false;
    <?php if ($order_by != "collection") { ?>
        var use_sortable_for_trash_only = true;
    <?php
    }
    ?>    
    function ReorderResources(idsInOrder) {
        var newOrder = [];
        jQuery.each(idsInOrder, function() {
            newOrder.push(this.substring(13));
            });
        jQuery.ajax({
          type: 'POST',
          url: 'search.php?search=!collection<?php echo urlencode($collection); ?>&reorder=true&offset=<?php echo urlencode($offset); ?>',
          data: {
            order: JSON.stringify(newOrder),
            <?php echo generateAjaxToken('reorder_search'); ?>
            },
          success: function(){
          <?php if (isset($usercollection) && ($usercollection==$collection)) { ?>
             UpdateCollectionDisplay('<?php echo isset($k)?escape($k):"" ?>');
          <?php } ?>
            } 
        });
    }
    jQuery('#CentralSpace').on('CentralSpaceSortable', function() {
        if(is_touch_device())
            {
            return false;
            }
        jQuery('.ui-sortable').sortable('enable');
        jQuery('#CentralSpaceResources').sortable({
            connectWith: '#CollectionSpace',
            appendTo: 'body',
            zIndex: 99000,
            distance: 20,
            scroll: false,
            helper: function(event, ui)
                {
                //Hack to append the element to the body (visible above others divs), 
                //but still belonging to the scrollable container
                jQuery('#CentralSpaceResources').append('<div id="CentralSpaceResourceClone" class="ui-state-default">' + ui[0].outerHTML + '</div>');   
                jQuery('#CentralSpaceResourceClone').hide();
                setTimeout(function() {
                    jQuery('#CentralSpaceResourceClone').appendTo('body'); 
                    jQuery('#CentralSpaceResourceClone').show();
                }, 1);
                
                return jQuery('#CentralSpaceResourceClone');
                },
            items: '.ResourcePanel',
            cancel: '.DisableSort',
            
            start: function (event, ui)
                {
                InfoBoxEnabled=false;
                if (jQuery('#InfoBox')) {jQuery('#InfoBox').hide();}
                if (jQuery('#InfoBoxCollection')) {jQuery('#InfoBoxCollection').hide();}
                if(is_special_search('!collection', 11))
                    {
                    // get the current collection from the search page (ie. CentralSpace)
                    var query_strings = getQueryStrings();
                    if(is_empty(query_strings))
                        {
                        return false;
                        }
                    var collection_id = query_strings.search.substring(11);

                    jQuery('#trash_bin').show();
                    }
                },

            update: function(event, ui)
                {
                if (use_sortable_for_trash_only)
                    {
                    // We are only using sortable for the ability to use the trash bin when the collection order is not "collection" 
                    // and so we need to abandon the attempted reorder in this scenario
                    return false;    
                    }
                
                // Don't reorder when top and bottom collections are the same and you drag & reorder from top to bottom
                if(ui.item[0].parentElement.id == 'CollectionSpace')
                    {
                    return false;
                    }

                InfoBoxEnabled=true;

                <?php if ($sort == "ASC")
                    {?>
                    var idsInOrder = jQuery('#CentralSpaceResources').sortable("toArray");
                    ReorderResources(idsInOrder);
                    <?php
                    }
                else
                    {?>
                   return false;
                    <?php
                    }?>
                    
                if(is_special_search('!collection', 11))
                    {
                    jQuery('#trash_bin').hide();
                    }
                },

            stop: function(event, ui)
                {
                InfoBoxEnabled=true;
                if(is_special_search('!collection', 11))
                    {
                    jQuery('#trash_bin').hide();
                    }
                }
        });
        jQuery('.ResourcePanelShell').disableSelection();
        jQuery('.ResourcePanelShellLarge').disableSelection();
        jQuery('.ResourcePanelShellSmall').disableSelection();

        // CentralSpace should only be sortable (ie. reorder functionality) for collections only
        if(!allow_reorder)
            {
            jQuery('#CentralSpaceResources').sortable('disable');
            }
    });
    </script>
<?php }
    elseif (!hook("noreorderjs")) { ?>
    <script type="text/javascript">
        var allow_reorder = false;
        jQuery(document).ready(function () {
            jQuery('#CentralSpaceResources .ui-sortable').sortable('disable');
            jQuery('.ResourcePanelShell').enableSelection();
            jQuery('.ResourcePanelShellLarge').enableSelection();
            jQuery('.ResourcePanelShellSmall').enableSelection();
        });
    
    </script>
    <?php }

if(getval("promptsubmit","")!= "" && getval("archive","")=="-2" && checkperm("e-1"))
    {
    // User has come here from upload. Show a prompt to submit the resources for review
    ?>
    <script>    
    jQuery(document).ready(function() {
        jQuery("#modal_dialog").html('<?php echo escape($lang["submit_dialog_text"]); ?>');
        jQuery("#modal_dialog").dialog({
                                    title:'<?php echo escape($lang["submit_review_prompt"]); ?>',
                                    modal: true,
                                    width: 400,
                                    resizable: false,
                                    dialogClass: 'no-close',
                                    buttons: {
                                        "<?php echo escape($lang['action_submit_review']); ?>": function() {
                                                jQuery.ajax({
                                                    type: "POST",
                                                    dataType: "json",
                                                    url: baseurl_short+"pages/ajax/user_action.php",
                                                    data: {
                                                        "action" : "submitpending",
                                                        "collection_add" : "<?php echo (int) $collection_add; ?>",
                                                        <?php echo generateAjaxToken('submit_for_review'); ?>
                                                    },
                                                    success: function(response){
                                                            <?php
                                                            if(is_int_loose($collection_add))
                                                                {
                                                                if($send_collection_to_admin) 
                                                                    {
                                                                    ?>
                                                                    api('send_collection_to_admin',{'collection': <?php echo (int) $collection_add; ?>}, function(response)
                                                                        {
                                                                        console.debug('A copy of collection #<?php echo (int) $collection_add; ?> has been sent for review.');
                                                                        },
                                                                        <?php echo generate_csrf_js_object('send_collection_to_admin'); ?>
                                                                    );
                                                                    <?php
                                                                    }
                                                                echo "window.location.href='" .  $baseurl_short . "pages/search.php?search=!collection" . (int) $collection_add . "';";
                                                                }
                                                            else
                                                                {
                                                                echo "window.location.href='" .  $baseurl_short . "pages/search.php?search=!contributions" . (int) $userref . "&archive=-1&order_by=date&sort=desc';";
                                                                }
                                                            ?>
                                                            },
                                                    error: function(response) {
                                                        styledalert('<?php echo escape($lang["error"]); ?>',response['responseJSON']['message']);
                                                        }
                                                    });


                                            },    
                                        "<?php echo escape($lang['action_continue_editing']); ?>": function() { 
                                                jQuery(this).dialog('close');
                                                <?php 
                                                if (is_int_loose($collection_add))
                                                    {
                                                    echo "window.location.href='" .  $baseurl_short . "pages/search.php?search=!collection" . (int) $collection_add . "';";
                                                    }
                                                else
                                                    {
                                                    echo "window.location.href='" .  $baseurl_short . "pages/search.php?search=!contributions" . $userref . "&archive=-2&order_by=date&sort=desc';";
                                                    }
                                                ?>
                                                }
                                            }
                                });
    });
    </script>
<?php
    }

# Extra CSS to support more height for titles on thumbnails.
if (isset($result_title_height))
    {
    ?>
    <style>
    .ResourcePanelInfo .extended
        {
        white-space:normal;
        height: <?php echo $result_title_height; ?>px;
        text-align: left;
        }
    </style>
    <?php
    }

hook('searchresultsheader');

if ($search_titles)
    {
    hook("beforesearchtitle");
    echo $search_title;
    hook("aftersearchtitle");
    hook("beforecollectiontoolscolumn");
    }

if (!hook("replacesearchheader")) # Always show search header now.
    {
    $resources_count = $result_count ?? (is_array($result) ? count($result) : 0);
    if (isset($collections)) 
        {
        $result_count=$colcount+$resources_count;
        }
    else
        {
        $result_count = $resources_count;
        }
    ?>
    <div class="BasicsBox SearchSticky">
    <div class="TopInpageNav">
    <div class="TopInpageNavLeft">

    <div id="SearchResultFound" class="InpageNavLeftBlock">
    <?php
    if($use_selection_collection && $selection_collection_resources_count > 0)
        {
        echo render_selected_resources_counter(count($selection_collection_resources));
        }
    elseif (isset($collections)) 
        {
        ?>
        <span class="Selected">
        <?php
        echo number_format($result_count); ?> </span><?php echo escape($result_count==1 ? $lang["youfoundresult"] : $lang["youfoundresults"]);
        }
    else
        {
        ?>
        <span class="Selected">
        <?php
        echo number_format($resources_count); ?> </span><?php echo escape($resources_count==1 ? $lang["youfoundresource"] : $lang["youfoundresources"]);
        }
     ?></div>
    <?php
    $replacedisplayselector=hook('replacedisplayselector','',array($search,(isset($collections)?$collections:"")));
    if(!$replacedisplayselector)
        {
        ?>
        <div class="InpageNavLeftBlock <?php if($iconthumbs) {echo 'icondisplay';} ?>">
        <?php 
        if($iconthumbs && !$high_contrast_mode)
            {
            if ($xlthumbs)
                {
                if($display == 'xlthumbs')
                    {
                    ?><span class="xlthumbsiconactive"></span><?php
                    }
                else
                    {
                    ?>
                    <a id="xlthumbs_view_link" href="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array("display"=>"xlthumbs")); ?>" title='<?php echo escape($lang["xlthumbstitle"]) ?>' onClick="return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this);">
                        <span class="xlthumbsicon"></span>
                    </a>
                    <?php
                    }
                }
            if($display == 'thumbs')
                {
                ?><span class="largethumbsiconactive"></span><?php
                }
            else
                {
                ?>
                <a id="thumbs_view_link" href="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array("display"=>"thumbs")); ?>" title='<?php echo escape($lang["largethumbstitle"]) ?>' onClick="return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this);">
                    <span class="largethumbsicon"></span>
                </a>
                <?php
                }
            if($display == 'strip')
                {
                ?><span class="stripiconactive"></span><?php
                }
            else
                {
                ?>
                <a id="strip_view_link" href="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array("display"=>"strip")); ?>" title='<?php echo escape($lang["striptitle"]) ?>' onClick="return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this);">
                    <span class="stripicon"></span>
                </a>
                <?php
                }
                

            if($display == 'list')
                {
                ?><span class="smalllisticonactive"></span><?php
                }
            else
                {
                ?>
                <a id="list_view_link"  href="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array("display"=>"list")); ?>" title='<?php echo escape($lang["listtitle"]) ?>' onClick="return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this);">
                    <span class="smalllisticon"></span>
                </a>
                <?php
                }
                
            
            if (!$disable_geocoding)
                {
                if($display == 'map')
                    { ?>
                    <span class="icon-map"></span><?php
                    }
                else
                    { ?>
                    <a  id="map_view_link" href="<?php echo generateURL($baseurl_short . "pages/search.php",$searchparams,array('display'=>'map')); ?>" title='<?php echo escape(($search_map_max_results > 0 && $resources_count > $search_map_max_results)? $lang['search_results_overlimit'] : $lang['maptitle']); ?>' onClick="<?php
                    if($search_map_max_results > 0  && $resources_count > $search_map_max_results)
                        {
                        echo "styledalert('" . escape($lang["error"]) . "','" . escape($lang['search_results_overlimit']) . "');return false;";
                        }
                    else
                        {
                        echo "return " . ($modal ? 'Modal' : 'CentralSpace') . "Load(this);";
                        }
                    ?>">
                    <span class="icon-map"></span>
                    </a>
                    <?php
                    }
                }
    
            hook('adddisplaymode');
            }
        else
            {
            if ($xlthumbs) { ?> <?php if ($display=="xlthumbs") { ?><span class="Selected"><?php echo escape($lang["xlthumbs"]); ?></span><?php } else { ?><a href="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array("display"=>"xlthumbs")); ?>" onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["xlthumbs"]); ?></a><?php } ?>&nbsp; |&nbsp;<?php } ?>
            <?php if ($display=="thumbs") { ?> <span class="Selected"><?php echo escape($lang["largethumbs"]); ?></span><?php } else { ?><a href="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array("display"=>"thumbs")); ?>" onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["largethumbs"]); ?></a><?php } ?>&nbsp; |&nbsp; 
            <?php if ($display=="strip") { ?><span class="Selected"><?php echo escape($lang["striptitle"]); ?></span><?php } else { ?><a href="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array("display"=>"strip")); ?>" onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["striptitle"]); ?></a><?php } ?>&nbsp; |&nbsp;
            <?php if ($display=="list") { ?> <span class="Selected"><?php echo escape($lang["list"]); ?></span><?php } else { ?><a href="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array("display"=>"list")); ?>" onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["list"]); ?></a><?php } ?> <?php hook("adddisplaymode"); ?> 
            <?php
            if(!$disable_geocoding)
                {
                if ($display == 'map')
                    { ?>
                    &nbsp;|&nbsp;<span class="Selected"><?php echo escape($lang['maptitle']); ?></span><?php
                    }
                else
                    { ?>
                    &nbsp;|&nbsp;<a href="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array('display'=>'map')); ?>" onClick="<?php
                    if($resources_count > $search_map_max_results)
                        {
                        echo "styledalert('" . escape($lang["error"]) . "','" . escape($lang['search_results_overlimit']) . "');return false;";
                        }
                    else
                        {
                        echo "return " . ($modal ? 'Modal' : 'CentralSpace') . "Load(this);";
                        }
                    ?>"><?php echo escape($lang['maptitle']); ?></a><?php
                    }
                }
            }
        ?>
        </div>
        <?php
        }

    if ($search!="!duplicates" && $search!="!unused" && !hook("replacesearchsortorder")) 
        {
        // Relevance is the default sort sequence if viewing resources following a search
        $default_sort_order = $default_sort;
        $rel = $lang[$default_sort] ?? $lang["relevance"];
        if(!hook("replaceasadded"))
            {
            if (isset($collection))
                {
                // Collection is the default sort sequence if viewing resources in a collection
                $default_sort_order='collection';
                $rel=$lang["collection_order_description"];
                }
            elseif (strpos($search,"!")!==false && substr($search,0,11)!="!properties") 
                {
                // As Added is the default sort sequence for special searches other than image properties
                $default_sort_order = 'resourceid';
                $rel=$lang["asadded"];
                }
            }
        // Build the available sort sequence entries, starting with the default derived above
        $orderFields = array($default_sort_order => $rel);

        // Ensure relevance is the next available option if not yet present
        if (!array_key_exists('relevance', $orderFields)) {
            $orderFields['relevance'] = $lang['relevance'];
        }

        // Add the remaining options
        if ($popularity_sort) {
            $orderFields['popularity'] = $lang['popularity'];
        }
        if ($orderbyrating) {
            $orderFields['rating'] = $lang['rating'];
        }
        if ($date_column) {
            $orderFields['date'] = $lang['date'];
        }
        if ($colour_sort) {
            $orderFields['colour'] = $lang['colour'];
        }
        if ($order_by_resource_id) {
            $orderFields['resourceid'] = $lang['resourceid'];
        }
        $orderFields['resourcetype'] = $lang['type'];
        
        $orderFields['modified'] = $lang['modified'];

        # Add thumbs_display_fields to sort order links for thumbs views
        $sf_count = count($sf);
        for ($x=0;$x<$sf_count;$x++)
            {
            if (!isset($metadata_template_title_field)){$metadata_template_title_field=false;}
            if ($sf[$x]['ref']!=$metadata_template_title_field)
                {
                $orderFields['field' . $sf[$x]['ref']] = escape($sf[$x]['title']);
                }
            }

        $modifiedFields = hook('modifyorderfields', '', array($orderFields));
        if ($modifiedFields) {
            $orderFields = $modifiedFields;
        }

        if (!hook('sortordercontainer') && $display != 'map')
            {
            ?>
            <div id="searchSortOrderContainer" class="InpageNavLeftBlock ">
            <?php

            if(!hook('render_sort_order_differently', '', array($orderFields)))
                {
                render_sort_order($orderFields,$default_sort_order);
                }

            hook('sortorder');
            ?>
            </div>
            <?php
            }
        }

        if($display != 'map')
            {
            ?>
            <div class="InpageNavLeftBlock">
                <select id="resultsdisplay" style="width:auto" name="resultsdisplay" aria-label="<?php echo escape($lang["resultsdisplay"]) ?>" onchange="<?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this.value,true);">
            <?php
            $results_display_array_count = count($results_display_array);
            for($n = 0; $n < $results_display_array_count; $n++)
                {               
                if (isset($searchparams["offset"]))
                    {
                    $new_offset = floor($searchparams["offset"] / max($results_display_array[$n],1)) * $results_display_array[$n];
                    }
                ?>
                <option <?php if($per_page == $results_display_array[$n]) { ?>selected="selected"<?php } ?> value="<?php echo generateURL($baseurl_short."pages/search.php",$searchparams,array("per_page"=>$results_display_array[$n],"offset"=>$new_offset)); ?>"><?php echo escape(str_replace("?",$results_display_array[$n],$lang["perpage_option"])); ?></option>
                <?php
                }
                ?>
                </select>
            </div>
            <?php
            }

        if(!isset($collectiondata) || !$collectiondata)
            {
            $collectiondata = array();
            $collectionsearch = false;
            }

        $url=generateURL($baseurl . "/pages/search.php",$searchparams); // Moved above render_actions as $url is used to render search actions
        if($use_selection_collection && $selection_collection_resources_count > 0)
            {
            render_selected_collection_actions();
            }
        else
            {
            if (is_array($result))
                {
                render_actions($collectiondata, true, false, '', $result);
                }
            }

        hook("search_header_after_actions");

        if(isset($is_authenticated) && $is_authenticated)
            {
            render_upload_here_button($searchparams);

            if($use_selection_collection && $selection_collection_resources_count > 0)
                {
                render_edit_selected_btn();
                render_clear_selected_btn();
                }
            }
    
    $totalpages=$per_page==0 ? 1 : ceil($result_count/$per_page);
    if ($offset>$result_count) {$offset=0;}
    $curpage=$per_page==0 ? 1 : floor($offset/$per_page)+1;

    ?>
    </div>
    <?php hook("stickysearchresults"); ?> <!--the div TopInpageNavRight was added in after this hook so it may need to be adjusted -->
    <div class="TopInpageNavRight">
    <?php
    if($display != 'map')
        {
        pager(false);
        $draw_pager=true;
        }
    ?>
    </div>
    <div class="clearerleft"></div>
    </div>
    </div>
    <?php
} 
    hook("stickysearchresults");

    // Show collection title and description.
    if (
        $collectionsearch
        && isset($collectiondata) 
        && array_key_exists("name",$collectiondata) 
        && $show_collection_name
        ) {
            ?>
            <div class="RecordHeader">
                <h1 class="SearchTitle">
                <?php echo i18n_get_collection_name($collectiondata); ?>
                </h1>
            <?php
            if((isset($collectiondata) && array_key_exists("description",$collectiondata)) && trim((string)$collectiondata['description']) != "")
                {
                echo "<p>" . nl2br(escape(i18n_get_translated($collectiondata['description']))) . "</p>";
                }
            echo "</div>";
        }
    
    hook("beforesearchresults");
    
    # Archive link
    if (
        !$archivesearched
        && strpos($search, "!") === false
        && $archive_search
        && $archive_standard
    ) {?>
        <div class="SearchOptionNav">
            <a
            href="<?php echo generateURL($baseurl_short . "pages/search.php", $searchparams, ["archive" => 2]); ?>"
            onClick="return CentralSpaceLoad(this);"
            >
                <?php echo LINK_CARET . escape($lang["view"]); ?>
                <span class="Selected">
                    <?php echo escape($lang["matches"] . " " . $lang["inthearchive"]); ?>
                </span>
                <span
                    id="ArchiveSearchCount"
                    style="display: none;">
                </span>
            </a>
        </div>
        <script>
            jQuery(document).ready(function(){
                api("do_search",
                    {
                        "search": "<?php echo escape($search); ?>",
                        "restypes": "<?php echo escape($restypes); ?>",
                        "archive": "2",
                        "fetchrows": "1,1",
                    },
                    function(response) {
                        jQuery('#ArchiveSearchCount').html('(' + Number(response.total) + ')').fadeIn();
                    },
                    <?php echo generate_csrf_js_object('do_search'); ?>
                );
            });
        </script>
        <?php 
        }
    echo $search_title_links;
    if($collectionsearch) // Fetch collection name 
        {
            $collectionsearchname = escape($collectiondata["name"]);
        }
    else
        {
            $collectionsearchname = "";
        }
    hook("beforesearchresultsexpandspace");

    // DRAG AND DROP TO UPLOAD FUNCTIONALITY
    // Generate a URL for drag drop function - fires same URL as "upload here" when dragging.
    $drag_upload_params=render_upload_here_button($searchparams,true);
    $drag_over="";
    if (is_array($drag_upload_params) && ($display=='thumbs' || $display=='xlthumbs') && $order_by == 'collection')
        {
        $drag_url=generateURL("{$GLOBALS['baseurl']}/pages/upload_batch.php", $drag_upload_params);
        $drag_over=" onDragOver=\"UploadViaDrag('" . $drag_url . "');\" ";
        }
    ?>
    <script>
    var DragUploading=false
    function UploadViaDrag(url)
        {
        if (DragUploading) {return false;}
        DragUploading=true;CentralSpaceLoad(url);
        }
    </script>
    
    
    <div class="clearerleft"></div>
    <div id="CentralSpaceResources" collectionSearchName="<?php echo $collectionsearchname; ?>" <?php echo $drag_over ?>>
    <?php

    if ((!is_array($result) || count($result)<1) && empty($collections))
        {
        // No matches found? Log this in
        ?>
        <div class="BasicsBox"> 
          <div class="NoFind">
            <p><?php echo escape($lang["searchnomatches"]); ?></p>
            <?php
            if(!$collectionsearch) // Don't show hints if a collection search is empty 
                {
                if ($result!="" && !is_array($result))
                    {
                    ?>
                    <p><?php echo escape($lang["try"]); ?>: <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode(strip_tags($result)); ?>"><?php echo strip_tags($result); ?></a></p>
                    <?php $result=array();
                    }
                else
                    {
                    ?>
                    <p><?php if (strpos($search,"country:")!==false) { ?><p><?php echo escape($lang["tryselectingallcountries"]); ?> <?php } 
                    elseif (strpos($search,"basicyear:")!==false) { ?><p><?php echo escape($lang["tryselectinganyyear"]); ?> <?php } 
                    elseif (strpos($search,"basicmonth:")!==false) { ?><p><?php echo escape($lang["tryselectinganymonth"]); ?> <?php } 
                    elseif (strpos($search,":")!==false) { ?><p><?php echo escape($lang["field_search_no_results"]); } 
                    else        {?><?php echo escape($lang["trybeinglessspecific"]); ?><?php } ?> <?php echo escape($lang["enteringfewerkeywords"]); ?></p>
                    <?php
                    }
                hook("afterresulthints");
                }
          ?>
          </div>
        </div>
<?php
        }

    $list_displayed = false;
    # Listview - Display title row if listview and if any result.
    if ($display=="list" && ((is_array($result) && count($result)>0) || (isset($collections) && is_array($collections) && $colcount>0)))
        {
        $list_displayed = true;
        ?>
        <div class="BasicsBox"><div class="Listview">
        <table class="ListviewStyle">

        <?php if(!hook("replacelistviewtitlerow")){?>   
        <tr class="ListviewTitleStyle">
        <?php if (!hook("listcheckboxesheader")){?>
        <?php if ($use_selection_collection) {?><th><?php echo escape($lang['addremove']); ?></th><?php } ?>
        <th><?php echo escape($lang["imagesize-thumbnail"]); ?></th>
        <?php } # end hook listcheckboxesheader 

        $is_special_search_duplicates = mb_substr($search, 0, 11) == '!duplicates';
        $df_count = count($df);
        for ($x=0;$x<$df_count;$x++) {
            if ($is_special_search_duplicates) {
                ?><th><?php echo escape($df[$x]['title']); ?></th><?php
            } elseif ($order_by=="field".$df[$x]['ref']) {
                ?>
                <th class="Selected">
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short."pages/search.php",
                            $searchparams,
                            array("order_by"=>"field" . $df[$x]['ref'], "sort"=>$revsort)
                        ); ?>"
                        onclick="return CentralSpaceLoad(this);"><?php echo escape($df[$x]['title']); ?></a>
                    <div class="<?php echo urlencode($sort); ?>">&nbsp;</div>
                </th>
                <?php
            } else {
                ?>
                <th>
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short."pages/search.php",
                            $searchparams,
                            array("order_by"=>"field" . $df[$x]['ref'])
                        ); ?>"
                        onClick="return CentralSpaceLoad(this);"><?php echo escape($df[$x]['title']); ?></a>
                </th>
                <?php
            }
        }
        
        hook("searchbeforeratingfieldtitlecolumn");
        if ($id_column) {
            if ($is_special_search_duplicates) {
                ?><th><?php echo escape($lang['id']); ?></th><?php
            } elseif ($order_by=="resourceid") {?>
                <th class="Selected">
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short."pages/search.php",
                            $searchparams,
                            array("order_by"=>"resourceid","sort"=>$revsort)
                        ); ?>"
                        onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["id"]); ?></a>
                    <div class="<?php echo urlencode($sort); ?>">&nbsp;</div>
                </th><?php
            } else {?>
                <th>
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short."pages/search.php",
                            $searchparams,
                            array("order_by"=>"resourceid")
                        ); ?>"
                        onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["id"]); ?></a>
                </th><?php
            }
        }

        if ($resource_type_column) {
            if ($is_special_search_duplicates) {
                ?><th><?php echo escape($lang['type']); ?></th><?php
            } elseif ($order_by == "resourcetype") {?>
                <th class="Selected">
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short . "pages/search.php",
                            $searchparams,
                            array("order_by" => "resourcetype","sort" => $revsort)
                        ); ?>"
                        onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["type"]); ?></a>
                    <div class="<?php echo urlencode($sort); ?>">&nbsp;</div>
                </th><?php
            } else {?>
                <th>
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short . "pages/search.php",
                            $searchparams,
                            array("order_by" => "resourcetype","sort" => "ASC")
                        ); ?>"
                        onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["type"]); ?></a>
                </th><?php
            }
        }

        if ($is_special_search_duplicates) {
            ?><th><?php echo escape($lang['list_file_extension']); ?></th><?php
        } elseif ($order_by == "extension") {?>
            <th class="Selected">
                <a
                    href="<?php echo generateURL(
                        $baseurl_short . "pages/search.php",
                        $searchparams,
                        array("order_by" => "extension","sort" => $revsort)
                    ); ?>"
                    onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["list_file_extension"]); ?></a>
                <div class="<?php echo urlencode($sort); ?>">&nbsp;</div>
            </th><?php
        } else {?>
            <th>
                <a
                    href="<?php echo generateURL(
                        $baseurl_short . "pages/search.php",
                        $searchparams,
                        array("order_by" => "extension","sort" => "ASC")
                    ); ?>"
                    onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["list_file_extension"]); ?></a>
            </th><?php
        }

        if ($list_view_status_column) {
            if ($is_special_search_duplicates) {
                ?><th><?php echo escape($lang['status']); ?></th><?php
            } elseif ($order_by == "status") {?>
                <th class="Selected">
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short . "pages/search.php",
                            $searchparams,
                            array("order_by" => "status","sort" => $revsort)
                        ); ?>"
                        onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["status"]); ?></a>
                    <div class="<?php echo urlencode($sort); ?>">&nbsp;</div>
                </th><?php
            } else {?>
                <th>
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short . "pages/search.php",
                            $searchparams,
                            array("order_by" => "status")
                        ); ?>"
                        onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["status"]); ?></a>
                </th><?php
            }
        }

        if ($date_column) {
            if ($is_special_search_duplicates) {
                ?><th><?php echo escape($lang['date']); ?></th><?php
            } elseif ($order_by == "date") {?>
                <th class="Selected">
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short . "pages/search.php",
                            $searchparams,
                            array("order_by" => "date","sort" => $revsort)
                        ); ?>"
                        onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["date"]); ?></a>
                    <div class="<?php echo urlencode($sort); ?>">&nbsp;</div>
                </th><?php
            } else {?>
                <th>
                    <a
                        href="<?php echo generateURL(
                            $baseurl_short . "pages/search.php",
                            $searchparams,
                            array("order_by" => "date")
                        ); ?>"
                        onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang["date"]); ?></a>
                </th><?php
            }
        }
        hook("addlistviewtitlecolumn"); ?>
        <th><div class="ListTools ListSearchTools"><?php echo escape($lang["tools"]); ?></div></th>
        </tr>
        <?php } ?> <!--end hook replace listviewtitlerow-->
        <?php
        }
        # Include public collections and themes in the main search, if configured.      
        if (isset($collections) 
            && strpos($search,"!")===false 
            && ($archive_standard || in_array(0,$selected_archive_states))
            && !hook('replacesearchpublic','',array($search,$collections)))
            {
            include "../include/search_public.php";
            }
    if ($search_includes_resources) {

    hook('searchresources');

    // Determine geolocation parameters for map search view.
    if (!$disable_geocoding && $display == "map")
        {
        global $marker_metadata_field, $use_watermark;

        // Loop through search results.
        for ($n = 0; $n < $result_count; $n++)
            {            
            if(!isset($result[$n]) || !is_array($result[$n]) || ($search_map_max_results > 0 && $n > $search_map_max_results))
                {
                continue;
                }
            // Get resource data for resources returned by the current search.
            $geo = $result[$n]['ref'];
            $geomark = get_resource_data($geo, true);
            $geomark2 = isset($marker_metadata_field) ? get_data_by_field($geo, $marker_metadata_field) : '';

            // Check for resources without geolocation or invalid coordinates and skip those.
            if (is_numeric($geomark['geo_lat']) && is_numeric($geomark['geo_long']) && $geomark['geo_lat'] >= -90 && $geomark['geo_lat'] <= 90 && $geomark['geo_long'] >= -180 && $geomark['geo_long'] <= 180)
                {
                // Create array of geolocation parameters.
                $geomarker[] = "[" . $geomark['geo_long'] . ", " . $geomark['geo_lat'] . ", " . $geomark['ref'] . ", " . $geomark['resource_type'] . "," . (trim($geomark2) != "" ? floatval($geomark2) : "") . "]";
                $preview_paths[] = (int) $result[$n]['has_image'] !== 0 && !resource_has_access_denied_by_RT_size($result[$n]['resource_type'], 'thm')
                    ? get_resource_path($geo, false, 'thm', false, $result[$n]['preview_extension'], true, 1, $use_watermark, $result[$n]['file_modified'])
                    : $baseurl_short . 'gfx/no_preview/default.png';

                }
            }
        }
    # work out common keywords among the results
    if (is_array($result) && (count($result)>$suggest_threshold) && (strpos($search,"!")===false) && ($suggest_threshold!=-1))
        {
        for ($n=0;$n<$result_count;$n++)
            {
            if(!is_array($result[$n])){continue;}
            if ($result[$n]["ref"]) {$refs[]=$result[$n]["ref"];} # add this to a list of results, for query refining later
            }
        $suggest=suggest_refinement($refs,$search);
        if (count($suggest)>0)
            {
            ?><p><?php echo escape($lang["torefineyourresults"]); ?>: <?php
            $suggest_count = count($suggest);
            for ($n=0;$n<$suggest_count;$n++)
                {
                if ($n>0) {echo ", ";}
                ?><a  href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo  urlencode(strip_tags($suggest[$n])); ?>" onClick="return CentralSpaceLoad(this);"><?php echo stripslashes($suggest[$n]); ?></a><?php
                }
            ?></p><?php
            }
        }
        
    $rtypes=array();
    if (!isset($types)){$types=get_all_resource_types();}
    $types_count = count($types);
    for ($n=0;$n<$types_count;$n++) {$rtypes[$types[$n]["ref"]]=lang_or_i18n_get_translated($types[$n]["name"], "resourcetype-");}
    if (is_array($result) && count($result)>0)
        {
        /**
         * If global var $annotate_enabled global == true, then ResourcePanel height is adjusted in thumbs.php.
         * If there is a mix of resource_types in results, and there is a config option for a particular resource_type that overrides $annotate_enabled, then display of ResourcePanels in search.php is affected.
         * This line detects if $annotate_enabled == true in config, and ensures that all ResourcePanels have same height value 
         */
        if ($annotate_enabled)  {
            $annotate_enabled_adjust_size_all = true;
            $show_annotation_count =  count(canSeeAnnotationsFields()) > 0;
            if ($show_annotation_count) {
                // Update the search results to include the annotation count
                search_add_annotation_count($result);
            }
        }
     
        # loop and display the results, unless map view

        if ($display == 'map')
            {
            # ----Map view----
            include_once 'search_views/map.php';
            }
        else
            {
            // This is used to ensure that all resource panels are the same height 
            $resource_panel_height_max = 0;            
            for ($n=0;$n<$result_count-$offset && $n<$resources_count && $n<$resourcestoretrieve;$n++)
                {
                # Allow alternative configuration settings for this resource type.
                resource_type_config_override($result[$n]["resource_type"]);
                
                if (
                    $order_by == "resourcetype" 
                    && $display != "list"
                    && 
                    ( 
                        $n==0 
                        || 
                        ( 
                            isset($result[$n-1]) 
                            && $result[$n]["resource_type"] != $result[$n-1]["resource_type"]
                        )
                    )
                    ) {
                        if($result[$n]["resource_type"]!="")
                            {
                            echo "<h1 class=\"SearchResultsDivider\" style=\"clear:left;\">" . escape($rtypes[$result[$n]["resource_type"]]) .  "</h1>";
                            }
                        else
                            {
                            echo "<h1 class=\"SearchResultsDivider\" style=\"clear:left;\">" . escape($lang['unknown']) .  "</h1>";
                            }
                    }

                $ref = $result[$n]["ref"];

                $GLOBALS['get_resource_data_cache'][$ref] = $result[$n];
                $url=generateURL($baseurl_short."pages/view.php",$searchparams, array("ref"=>$ref));

                // Global $access needs to be set to check watermarks in search views (and may be used in hooks)
                $access =
                    $result[$n]["resultant_access"] ?? // Access should have already been calculated in search
                        $result[$n]["user_access"] ??
                                $result[$n]["group_access"] ??
                                    $result[$n]["access"];

                if (isset($result[$n]["url"])) {$url = $result[$n]["url"];} # Option to override URL in results

                hook('beforesearchviewcalls');

                // Prepare for display all $data_joins fields (ie fieldX columns)
                $result[$n] = process_resource_data_joins_values($result[$n], get_resource_table_joins());

                if ($display=="thumbs")
                    {
                    #  ---------------------------- Thumbnails view ----------------------------
                    include 'search_views/thumbs.php';
                    } 

                if ($display=="strip")
                    {
                    #  ---------------------------- Thumbnails view ----------------------------
                    include 'search_views/strip.php';
                    }


                if ($display=="xlthumbs")
                    {
                    #  ---------------------------- X-Large Thumbnails view ----------------------------
                    include "search_views/xlthumbs.php";
                    }

                if ($display=="list")
                    {
                    # ----------------  List view -------------------
                    include "search_views/list.php";
                    }

                hook('customdisplaymode');
                }
            }
    }
        }
    # Listview - Add closing tag if a list is displayed.
    if ($list_displayed)
        {
        ?>
        </table>
        </div></div>
        <?php
        }
    
if ($display=="strip")
    {
    #  ---------------------------- Extra footer for strip view ----------------------------
    include 'search_views/strip_footer.php';
    }

$url=generateURL($baseurl . "/pages/search.php",$searchparams); 

?>
</div> <!-- end of CentralSpaceResources -->

<?php
hook("endofsearchpage");

if($display != 'map')
    { ?>
    <script>
    place     = '<?php echo escape(getval("place", "")); ?>';
    display   = '<?php echo escape($display) ; ?>';

    jQuery(document).ready(function()
        {
        if(place)
            {
            ele_id        = 'ResourceShell' + place;
            elementScroll = document.getElementById(ele_id);

            if(jQuery(elementScroll).length)
                {
                elementScroll.scrollIntoView();
                }
            }
        });
    </script>
    <?php
    }
    ?>
<script>
function toggle_addremove_to_collection_icon(plus_minus_link)
    {
    // The plus minus link can be from the collection bar or from the resource shell in centralspace  
    var icon = jQuery(plus_minus_link);

    // Use the link to locate the resource shell in centralspace  
    var resource_shell = jQuery('#ResourceShell' + icon.data('resource-ref') + ".ResourcePanel");

    // Each resource shell has one plus icon for addition and one minus icon for removal
    // Each collection bar resource has only one minus icon for removal

    // If its a plus icon then it must be from centralspace, so hide it and then show its minus icon sibling
    if(icon.hasClass('addToCollection'))
        {
        icon.addClass('DisplayNone');
        var rfc = icon.siblings('.removeFromCollection');
        if(rfc.length > 0)
            {
            jQuery(rfc[0]).removeClass('DisplayNone');
            }
        }
    // If its a minus icon then it can be from the collection bar or centralspace
    else if(icon.hasClass('removeFromCollection'))
        {
        // If there is a plus icon then it must be in centralspace and so show it
        resource_shell.find('div.ResourcePanelIcons > a.addToCollection').removeClass('DisplayNone');

        // Now hide the minus icon in centralspace
        resource_shell.find('div.ResourcePanelIcons > a.removeFromCollection').addClass('DisplayNone');
        }

    return;
    }


<?php
if($use_selection_collection)
    {
    ?>
    jQuery(document).ready(function()
        {
        var resource_starting=null; // Regular click resource marks the start of a range
        var resource_ending=null; // Shifted click resource marks the end of a range
        var primary_action = null;

        // The presence of a collection div indicates that event handlers for resource selection and deselection are established elsewhere
        var isCollectionDivPresent = document.getElementById("CollectionDiv");
        // Its absence means that handlers for resource selection and deselection need to be established here 
        if (!isCollectionDivPresent) { 
            registerResourceSelectDeselectHandlers();
        }

        // Process the clicked box
        jQuery(".checkselect").click(function(e)
            {
            var resource_selections=[];
            var input = e.target;
            var box_resource = jQuery(input).data("resource");
            var box_checked = jQuery(input).prop("checked");
            if (!e.shiftKey) {
                // Regular click; note the action required if there is a range to be processed
                primary_action=box_checked;
                resource_starting=box_resource;
                resource_ending=null;
            } else {
                if (!resource_starting) {
                    styledalert('<?php echo escape($lang["range_no_start_header"]); ?>', '<?php echo escape($lang["range_no_start"]); ?>');
                    if(jQuery(input).prop("checked")) {
                        this.removeAttribute("checked");
                        } 
                    else  {
                        this.setAttribute("checked", "checked");
                        }
                    return false;
                }
                resource_ending=box_resource; // Shifted click resource
            }

            // Process all clicked boxes
            jQuery(".checkselect").each(function()
                {
                // Fetch the event and store it in the selection array
                var toggle_event = jQuery.Event("click", { target: this });
                var toggle_input = toggle_event.target;
                var box_resource = jQuery(toggle_input).data("resource");
                var box_checked = jQuery(toggle_input).prop("checked");
                resource_selections.push({box_resource: box_resource, box_checked: box_checked});
                });

            // Process resources within a clicked range
            var res_list=[];
            if (resource_starting && resource_ending) {
                console.log("PROCESS " + resource_starting + " TO " + resource_ending);
                var found_start = false;
                var found_end = false;
                for (i = 0; i < resource_selections.length; i++) {
                    if (resource_selections[i].box_resource == resource_starting) {
                        // Range starting point is being processed; skip because already processed by single shot; move on
                        found_start = true;
                    }
                    else if (resource_selections[i].box_resource == resource_ending) {
                        // Range ending point is being processed; process it and move on (because it may be before the startin point)
                        found_end = true;
                        res_list.push(resource_selections[i].box_resource); // Resource to process
                    }
                    else {
                        // Element is not at the starting point or ending point; check whether its within the range
                        if ( !found_start && !found_end ) {
                            // Range is not yet being processed; skip
                        }
                        else if (found_start && found_end) {
                            // Both starting and ending points have been processed; quit loop
                            break;
                        }
                        else {
                            // Process the element within the range
                            res_list.push(resource_selections[i].box_resource); // Resource to process
                        }
                    }
                }
                
                // AJAX will be used to send multiple resources and actions
                var csrf_data = '{<?php echo generateAjaxToken("ProcessCollectionResourceSelection"); ?>}';
                // Convert token from format {CSRFToken:"data"} to strict JSON format which is {"CSRFToken":"data"} so that it can be parsed 
                var csrf_data = csrf_data.replace('<?php echo $CSRF_token_identifier; ?>','"<?php echo $CSRF_token_identifier; ?>"');
                ProcessCollectionResourceSelection(res_list, primary_action, '<?php echo $USER_SELECTION_COLLECTION; ?>', csrf_data);

                // Reset processing points
                resource_starting=null;
                resource_ending=null;
                primary_action = null;
                }

            else if (resource_starting) {
                console.log("PROCESS " + resource_starting + " ONLY");
                for (i = 0; i < resource_selections.length; i++) {
                    if (resource_selections[i].box_resource == resource_starting) {
                        // Range starting point is being processed; skip because already processed by single shot; move on
                        res_list.push(resource_selections[i].box_resource); // One resource to process
                        break;
                    }
                }

                // AJAX will be used to send single resource and action only
                var csrf_data = '{<?php echo generateAjaxToken("ProcessCollectionResourceSelection"); ?>}';
                // Convert token from format {CSRFToken:"data"} to strict JSON format which is {"CSRFToken":"data"} so that it can be parsed 
                var csrf_data = csrf_data.replace('<?php echo $CSRF_token_identifier; ?>','"<?php echo $CSRF_token_identifier; ?>"');
                ProcessCollectionResourceSelection(res_list, primary_action, '<?php echo $USER_SELECTION_COLLECTION; ?>', csrf_data);
                }

            else if (resource_ending) {
                console.log("ERROR - ENDING ONLY");
                }

            console.log("RESOURCE_LIST\n" + JSON.stringify(res_list));

            });
        });
    <?php
    }
    ?>
</script>
<?php
include '../include/footer.php';
