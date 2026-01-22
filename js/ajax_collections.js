// Functions to support collections.

// Prevent caching
jQuery.ajaxSetup({ cache: false });

function ChangeCollection(collection,k,last_collection,searchParams) {
    console.debug("ChangeCollection(collection = %o, k = %o, last_collection = %o, searchParams = %o)", collection, k, last_collection, searchParams);
    if(typeof last_collection == 'undefined'){last_collection='';}
    if(typeof searchParams == 'undefined') {searchParams='';}
    thumbs = getCookie("thumbs");
    // Set the collection and update the count display
    CollectionDivLoad(baseurl_short + 'pages/collections.php?collection=' + collection + '&thumbs=' + thumbs + '&last_collection=' + last_collection + '&k=' + k + '&' +searchParams);
}

function UpdateCollectionDisplay(k) {
    thumbs = getCookie("thumbs");
    // Update the collection count display
    jQuery('#CollectionDiv').load(baseurl_short + 'pages/collections.php?thumbs=' + thumbs + '&k=' + k);
}

function AddResourceToCollection(event, ui, resource, size, collection_id) {

    // Optional params
    if(typeof collection_id === 'undefined') {
        collection_id = '';
    }

    if(event.shiftKey == true) {
        if (typeof prevadded != 'undefined') {
            lastchecked = jQuery('#check' + prevadded);
            if (lastchecked.length != 0) {
                var resource_list = [];
                addresourceflag = false;
                jQuery('.checkselect').each(function () {
                    if(jQuery(this).attr("id") == lastchecked.attr("id")) {
                        if(addresourceflag == false) {   
                            // Set flag to mark start of resources to add
                            addresourceflag = true;
                        }
                        else { 
                            // Clear flag to mark end of resources to add
                            addresourceflag = false;  
                        }
                    }
                    else if(jQuery(this).attr("id") == 'check'+resource) {
                        // Add resource to list before clearing flag
                        resourceid = jQuery(this).attr("id").substring(5)
                        resource_list.push(resourceid);
                        if(addresourceflag == false) {
                            addresourceflag = true;
                        }
                        else {
                            addresourceflag = false;
                        }
                    }

                    if(addresourceflag) {
                        // Add resource to list 
                        resourceid = jQuery(this).attr("id").substring(5)
                        resource_list.push(resourceid);
                        jQuery("#ResourceShell" + resourceid).addClass("Selected");
                    }
                });
                resource = resource_list.join(",");
            }
        }
    }

    // Get the relevant CSRFToken
    artc_csrf="";
    
    if(event.type=="click")
        {
        try {
            artc_csrf=JSON.parse(event.currentTarget.dataset.apiNativeCsrf);
            }
        catch {
              console.debug('Click csrf not found');
              }
        }
    else if(event.type=="drop")
        {
        artc_dropped = jQuery(ui.draggable);
        try {
            artc_csrf=JSON.parse(artc_dropped.find("a.addToCollection").attr("data-api-native-csrf"));
            } 
        catch {
            console.debug('Dropped csrf not found');
            }
        }

    prevadded = resource;
    searchstring = '';
    if(typeof searchParams !== "undefined")
        {
        searchstring = searchParams.get("search");
        if (searchstring == null)
            {
            searchstring = '';
            }
        }
    post_data = {
        'resource'   : resource,
        'collection' : collection_id,
        'search'     : searchstring
     };
    
    api("add_resource_to_collection",post_data,function(response) {
        if(typeof response == 'string')
            {
            styledalert("", response);
            UpdateSelColSearchFilterBar();
            CentralSpaceHideProcessing();
            }
        else
            {
            CollectionDivLoad(baseurl_short + 'pages/collections.php');
            jQuery("#CentralSpace").trigger("resourceremovedfromcollection",[resource_list]);
            }
        },
        artc_csrf 
    );

    delete prevremoved;
}

function RemoveResourceFromCollection(event,resource,pagename, collection_id, csrf_token) {
    // Optional params
    if(typeof collection_id === 'undefined') {
        collection_id = '';
    }

    if(event.shiftKey == true) {
        if (typeof prevremoved != 'undefined') {
            lastunchecked=jQuery('#check' + prevremoved)
            if (lastunchecked.length != 0) {
                var resource_list = [];
                removeresourceflag = false;
                jQuery('.checkselect').each(function () {
                    if(jQuery(this).attr("id") == lastunchecked.attr("id")) {
                        if(removeresourceflag == false) { 
                            // Set flag to mark start of resources to remove
                            removeresourceflag = true;
                        }
                        else { 
                            // Clear flag to mark end of resources to remove
                            removeresourceflag = false;
                        }
                    }
                    else if(jQuery(this).attr("id") == 'check'+resource) {
                        // Add resource to list before clearing flag
                        resourceid = jQuery(this).attr("id").substring(5)
                        resource_list.push(resourceid);
                        if(removeresourceflag == false) {
                            removeresourceflag = true;
                        }
                        else {
                            removeresourceflag = false;
                        }
                    }

                    if(removeresourceflag) {
                        // Add resource to list to remove
                        resourceid = jQuery(this).attr("id").substring(5)
                        resource_list.push(resourceid);
                        jQuery("#ResourceShell" + resourceid).removeClass("Selected");
                    }
                });
                resource = resource_list.join(",");
            }
        }
    }
    prevremoved = resource;

    post_data = {
        'resource'   : resource,
        'collection' : collection_id,
    };

    if(typeof csrf_token !== 'object')
        {
        csrf_token = jQuery(event.target).data('api-native-csrf');
        }

    api("remove_resource_from_collection",post_data,function(response) {
        if(typeof response == 'string')
            {
            styledalert("", response);
            UpdateSelColSearchFilterBar();
            CentralSpaceHideProcessing();
            }
        else
            {
            CollectionDivLoad(baseurl_short + 'pages/collections.php');
            jQuery("#CentralSpace").trigger("resourceremovedfromcollection",[resource_list]);
            }
        },
        csrf_token
    );

    delete prevadded;
}


function UpdateHiddenCollections(checkbox, collection, post_data) {
    var action = (checkbox.checked) ? 'showcollection' : 'hidecollection';
    jQuery.ajax({
        type: 'POST',
        url: baseurl_short + 'pages/ajax/showhide_collection.php?action=' + action + '&collection=' + collection,
        data: post_data,
        success: function(data) {
            if (data.trim() == "HIDDEN") {
                jQuery(checkbox).prop('checked',false);
                mycol = jQuery('#CollectionDiv .ActionsContainer option[value="hide_collection"]').data('mycol');
            }
            else if (data.trim() == "UNHIDDEN") {
                jQuery(checkbox).prop('checked',true);
                mycol = usercollection;
            }
            CollectionDivLoad(baseurl_short + 'pages/collections.php?collection='+mycol);
        },
        error: function (err) {
            console.log("AJAX error : " + JSON.stringify(err, null, 2));
            if(action == 'showcollection') {
                jQuery(checkbox).removeAttr('checked');
            }
            else {
                jQuery(checkbox).prop('checked','checked');
            }
        }
    }); 
}


function ProcessCollectionResourceSelection(resource_list, primary_action, collection, csrf_data)
    {
    var csrf_post_data = JSON.parse(csrf_data);
    if(primary_action)
        {
        CentralSpaceShowProcessing();
        add_multiple_resources_to_collection(resource_list, collection, csrf_post_data)
        }
    else
        {
        CentralSpaceShowProcessing();
        remove_multiple_resources_from_collection(resource_list, collection, csrf_post_data)
        }
    return true;
}


function ToggleCollectionResourceSelection(e, collection)
    {
    var input = jQuery(e.target);
    var resource = input.data("resource");

    var csrf_token_identifier = input.data("csrf-token-identifier");
    var csrf_token = input.data("csrf-token");
    var csrf_post_data = {};
    csrf_post_data[csrf_token_identifier] = csrf_token;

    if(input.prop("checked"))
        {
        add_resource_to_collection(resource, collection, csrf_post_data)
            .then(function(add_ok)
                {
                if(add_ok)
                    {
                    UpdateSelColSearchFilterBar();
                    jQuery("#ResourceShell" + resource).addClass("Selected");
                    }
                });
        }
    else
        {
        remove_resource_from_collection(resource, collection, csrf_post_data)
            .then(function(remove_ok)
                {
                if(remove_ok)
                    {
                    UpdateSelColSearchFilterBar();
                    jQuery("#ResourceShell" + resource).removeClass("Selected");
                    }
                });
        }

    return true;
    }


function ClearSelectionCollection(t)
    {
    var button = jQuery(t);
    var csrf_token_identifier = button.data("csrf-token-identifier");
    var csrf_token = button.data("csrf-token");

    var default_post_data = {};
    default_post_data[csrf_token_identifier] = csrf_token;
    var post_data = Object.assign({}, default_post_data);
    post_data.ajax = true;
    post_data.action = "clear_selection_collection_resources";

    console.debug("ClearSelectionCollection: post_data = %o", post_data);

    CentralSpaceShowProcessing();

    jQuery.ajax({
        type: 'POST',
        url: baseurl + "/pages/ajax/collections.php",
        data: post_data,
        dataType: "json"
        })
        .done(function(response, textStatus, jqXHR)
            {
            if(typeof response.status !== "undefined" && response.status == "success")
                {
                CentralSpaceLoad(window.location.href, null, null, false);
                }
            })
        .fail(function(data, textStatus, jqXHR)
            {
            if(typeof data.responseJSON === 'undefined')
                {
                return;
                }

            var response = data.responseJSON;
            styledalert(jqXHR, response.data.message);
            })
        .always(function()
            {
            CentralSpaceHideProcessing();
            });

    return true;
    }


function UpdateSelColSearchFilterBar()
    {
    console.log('Called UpdateSelColSearchFilterBar()');
    CentralSpaceShowProcessing();

    jQuery.ajax({
        type: 'GET',
        url: baseurl + "/pages/ajax/collections.php",
        data: {
            ajax: true,
            action: "get_selected_resources_counter"
        },
        dataType: "json"
        })
        .done(function(response, textStatus, jqXHR)
            {
            if(response.status == "success")
                {
                var selected_resources = response.data.selected;
                var clear_btns = (selected_resources == 0);

                jQuery(".TopInpageNavLeft").trigger("UpdateForSelectionCollection", [clear_btns]);                    
                }
            })
        .fail(function(data, textStatus, jqXHR)
            {
            if(typeof data.responseJSON === 'undefined')
                {
                return;
                }

            var response = data.responseJSON;
            styledalert(jqXHR, response.data.message);
            })
        .always(function()
            {
            CentralSpaceHideProcessing();
            });

    jQuery(".TopInpageNavLeft").unbind('UpdateForSelectionCollection').one("UpdateForSelectionCollection", function(e, clear)
        {
        UpdateSelectedResourcesCounter(clear);
        UpdateSelectedUnifiedActions(clear);
        UpdateSelectedBtns(clear);
        });
    }

function UpdateSelectedResourcesCounter(clear)
    {
    console.debug("UpdateSelectedResourcesCounter(clear = %o)", clear);

    if(clear)
        {
        jQuery(".SelectionCollectionLink").parent().remove();

        var orig_srf = jQuery("#OriginalSearchResultFound");

        // We already had the "selected" counter - there is no search results found counter in DOM. Reload in this case.
        if(!orig_srf.length)
            {
            CentralSpaceLoad(window.location.href, null, null, false);
            }

        orig_srf.addClass("InpageNavLeftBlock");
        orig_srf.removeClass("DisplayNone");
        orig_srf.attr("id", "SearchResultFound");

        return;
        }

    jQuery.ajax({
        type: 'GET',
        url: baseurl + "/pages/ajax/collections.php",
        data: {
            action: "render_selected_resources_counter"
        },
        dataType: "html"
        })
        .done(function(response, textStatus, jqXHR)
            {
            var orig_srf = jQuery("#OriginalSearchResultFound");
            var remove_old = false;

            if(orig_srf.length)
                {
                remove_old = true;
                }

            srf = jQuery("#SearchResultFound");
            var srf_copy = srf.clone();

            srf_copy.html(response);
            srf_copy.insertAfter(srf);

            if(remove_old)
                {
                srf.remove();
                return;
                }

            // Hide the field
            srf.attr("id", "OriginalSearchResultFound");
            srf.addClass("DisplayNone");
            srf.removeClass("InpageNavLeftBlock");
            })
        .always(function()
            {
            CentralSpaceHideProcessing();
            });
    }

function UpdateSelectedBtns(clear)
    {
    console.debug("UpdateSelectedBtns(clear = %o)", clear);

    if(clear)
        {
        jQuery("#EditSelectedResourcesBtn").parent().remove();
        jQuery("#ClearSelectedResourcesBtn").parent().remove();
        return;
        }

    var EditBtn_ajax = jQuery.ajax({
        type: 'GET',
        url: baseurl + "/pages/ajax/collections.php",
        data: {
            action: "render_edit_selected_btn",
            restypes: searchparams.restypes,
            archive: searchparams.archive,
        },
        dataType: "html"
        });

    var ClearBtn_ajax = jQuery.ajax({
        type: 'GET',
        url: baseurl + "/pages/ajax/collections.php",
        data: {
            action: "render_clear_selected_btn"
        },
        dataType: "html"
        });

    jQuery.when(EditBtn_ajax, ClearBtn_ajax)
        .then(function(edit_btn_response, clear_btn_response)
            {
            var TopInpageNavLeft = jQuery(".TopInpageNavLeft");
            var btn = jQuery("#EditSelectedResourcesBtn");
            if(!btn.length)
                {
                TopInpageNavLeft.append(edit_btn_response[0]);
                }

            var btn = jQuery("#ClearSelectedResourcesBtn");
            if(!btn.length)
                {
                TopInpageNavLeft.append(clear_btn_response[0]);
                }
            });
    }

function UpdateSelectedUnifiedActions(clear)
    {
    console.debug("UpdateSelectedUnifiedActions(clear = %o)", clear);

    var search_action_selection = jQuery("select[id^=search_action_selection");
    var load_actions_action_selection = jQuery("select[id^=load_actions_action_selection");
    var actionspace = (!search_action_selection.length ? load_actions_action_selection : search_action_selection);
    var actionspace_parent = actionspace.parent();
    console.debug('[UpdateSelectedUnifiedActions] actionspace_parent = %o', actionspace_parent);

    if(clear)
        {
        // Allow LoadActions to reload the options when clearing
        actionspace_parent.attr('data-actions-loaded', '0');
        var load_actions = LoadActions("search", actionspace_parent, "search", null, searchparams);
        }
    else
        {
        var load_actions = LoadActions("search", actionspace_parent, "selection_collection", null, searchparams);
        }

    load_actions.then(function(actions_loaded)
        {
        console.debug('[UpdateSelectedUnifiedActions] actions_loaded = %o', actions_loaded);
        if(!actions_loaded)
            {
            return;
            }

        let actions_html = actionspace_parent.find('.ActionsContainer.InpageNavLeftBlock').html();
        actionspace_parent.empty().append(actions_html);
        });
    }

function RemoveSelectedFromCollection(csrf_id, csrf_token)
    {
    console.debug("RemoveSelectedFromCollection(csrf_id = %o, csrf_token = %o)", csrf_id, csrf_token);
    var k = "";
    if(typeof searchparams.k !== "undefined")
        {
        k = searchparams.k;
        }

    var post_data = {
        selected:true
    };
    let csrf_data = {};
    csrf_data[csrf_id] = csrf_token;

    CentralSpaceShowProcessing();
    api("collection_remove_resources",post_data,function(response){
        if(typeof response == 'string')
            {
            styledalert("", response);
            UpdateSelColSearchFilterBar();
            CentralSpaceHideProcessing();
            }
        else
            {
            jQuery("#CentralSpace").trigger("resourcesremovedfromcollection",[[]]);
            }
        UpdateCollectionDisplay('');

    return true;
        }, csrf_data);
    }


function add_resource_to_collection(resource, collection, csrf)
    {
    console.log("add_resource_to_collection: adding resource #%i to collection #%i", resource, collection);
    var postdata = {
        'resource'   : resource,
        'collection' : collection,
    }

    console.debug("add_resource_to_collection: post_data = %o", postdata);

    api("add_resource_to_collection",postdata,function(response){
        if(typeof response == 'string')
        {
        styledalert("", response);
        UpdateSelColSearchFilterBar();
        CentralSpaceHideProcessing();
        }
    else
        {
        jQuery("#CentralSpace").trigger("resourceaddedtocollection",[resource_list]);
        }

    return true;
    }, csrf);
    }

function add_multiple_resources_to_collection(resource_list, collection, csrf)
    {
    console.log("add_multiple_resource_to_collection: adding resource list %s to collection %s", JSON.stringify(resource_list), JSON.stringify(collection));

    var postdata = {
        'resources'  : resource_list,
        'collection' : collection,
    };
    
    console.debug("add_multiple_resources_to_collection: post_data = %o", postdata);

    return api("collection_add_resources",postdata,function(response){
        if(typeof response == 'string')
            {
            styledalert("", response);
            UpdateSelColSearchFilterBar();
            CentralSpaceHideProcessing();
            }
        else
            {
            jQuery("#CentralSpace").trigger("resourcesaddedtocollection",[resource_list]);
            }

        return true;
    }, csrf);
    }


function remove_resource_from_collection(resource, collection, csrf)
    {
    console.log("remove_resource_from_collection: removing resource #%i from collection #%i", resource, collection);

    var postdata = {
        'resources'  : resource,
        'collection' : collection,
    };

    console.debug("remove_resource_from_collection: post_data = %o", postdata);

    return api("remove_resource_from_collection",postdata,function(response){
        if(typeof response == 'string')
            { 
            UpdateSelColSearchFilterBar();
            CentralSpaceHideProcessing();
            styledalert("", response);
            }
        else
            {
            jQuery("#CentralSpace").trigger("removeresourcefromcollection",[resource]);
            }

        return true;
    }, csrf);
    }

function remove_multiple_resources_from_collection(resource_list, collection, csrf)
    {
    console.log("remove_multiple_resources_from_collection: removing resource list %s from collection %s", JSON.stringify(resource_list), JSON.stringify(collection));

    var postdata = {
        'resources' : resource_list,
        'collection'    : collection,
    };

    console.debug("remove_multiple_resources_from_collection: post_data = %o", postdata);

    return api("collection_remove_resources",postdata,function(response){
        if(typeof response == 'string')
            {
            UpdateSelColSearchFilterBar();
            CentralSpaceHideProcessing();
            styledalert("", response);
            }
        else
            {
            jQuery("#CentralSpace").trigger("resourcesremovedfromcollection",[resource_list]);
            }

        return true;
    }, csrf);
    }

function toggle_fc_bg_image_txt_input(el, show_on_val)
    {
    if(el.value == show_on_val)
        {
        jQuery('#question_bg_img_resource_ref').removeClass('DisplayNone');
        return;
        }

    jQuery('#question_bg_img_resource_ref').addClass('DisplayNone');
    }

function featured_collection_category_select_onchange(el, form)
    {
    if(el.value == "root")
        {
        jQuery('<input type="hidden" name="force_featured_collection_type" value="true">').prependTo(jQuery(form));
        }

    document.getElementsByName('update_parent')[0].value = 'true';
    document.getElementById('redirect').value = '';
    }

function delete_collection(ref, confirm_msg, csrf)
{
    console.debug(`Delete collection #${ref}`);
    if(confirm(confirm_msg)) {
        const post_data = {
            ajax: true,
            dropdown_actions: true,
            delete: ref,
            ...JSON.parse(csrf)
        };

        jQuery.post(`${baseurl}/pages/collection_manage.php`, post_data, function(response) {
            if (response.success === 'Yes') {
                let collections_params = new URLSearchParams({
                    collection: response.redirect_to_collection,
                    k: response.k,
                    nc: response.nc,
                });

                CollectionDivLoad(`${baseurl}/pages/collections.php?${collections_params.toString()}`);
                CentralSpaceLoad(`${baseurl}/pages/collections_featured.php`);
            }
        }, 'json');
    }
}
