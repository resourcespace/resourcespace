/* global.js : Functions to support features available globally throughout ResourceSpace */
var modalalign=false;
var modalfit=false;
var CentralSpaceLoading=false;
var ajaxinprogress=false;

const currentPageUrlParams = new URLSearchParams(window.location.search);
const external_access_key = currentPageUrlParams.get('k');

// prevent all caching of ajax requests by stupid browsers like IE
jQuery.ajaxSetup({ cache: false });

// function to help determine exceptions
function basename(path) {
    return path.replace(/\\/g,'/').replace( /.*\//, '' );
}

// IE 8 does not support console.log unless developer dialog is open, so we need a failsafe here if we're going to use it for debugging
   var alertFallback = false;
   if (typeof console === "undefined" || typeof console.log === "undefined") {
     console = {};
     if (alertFallback) {
         console.log = function(msg) {
              alert(msg);
         };
     } else {
         console.log = function() {};
     }
   }

var isMouse=false;
var mouseCheck=false;
function is_touch_device()
    {
        if (!mouseCheck) {
            mouseCheck = true;
            document.addEventListener("pointermove", (event) => {
                isMouse = event.pointerType === "mouse";
            }, {once: true});
        }
        return ('ontouchstart' in window // works on most browsers
            || (navigator.maxTouchPoints > 0)
            || (navigator.msMaxTouchPoints > 0))
            && !(matchMedia('(pointer:fine)').matches) 
            && !(isMouse);
    }



function SetCookie (cookieName,cookieValue,nDays)
    {
    var today = new Date();
    var expire = new Date();

    if (nDays==null || nDays==0) nDays=1;
    expire.setTime(today.getTime() + 3600000*24*nDays);
    path = ";path=" + baseurl_short + ";SameSite=Strict;";

    // Expire first the old cookie which might not be on the desired path (path used to be set to empty string which made
    // certain cookies be set at /pages) causing issues further down the process (e.g collection bar "thumbs" cookie which
    // was causing a "Exceeding call stack" error)
    var expired_date = new Date();
    expired_date.setTime(today.getTime() + 3600000 * 24 * -1);
    if (window.location.protocol === "https:")
        {
        document.cookie = cookieName + "=" + encodeURI(cookieValue) + ";expires=" + expired_date.toGMTString()+";secure";
        }
    else
        {
        document.cookie = cookieName + "=" + encodeURI(cookieValue) + ";expires=" + expired_date.toGMTString();
        }

    // Set cookie
    if (window.location.protocol === "https:")
        {
        document.cookie = cookieName+"="+encodeURI(cookieValue)+";expires="+expire.toGMTString()+path+";secure";
        }
    else
        {
        document.cookie = cookieName+"="+encodeURI(cookieValue)+";expires="+expire.toGMTString()+path;
        }
    }

function getCookie(c_name)
{
    var i,x,y,ARRcookies=document.cookie.split("; ");
    for (i=0;i<ARRcookies.length;i++)
    {
        x=ARRcookies[i].slice(0,ARRcookies[i].indexOf("="));
        y=ARRcookies[i].slice(ARRcookies[i].indexOf("=")+1);
        x=x.replace(/^\s+|\s+$/g,"");
        if (x==c_name)
        {
            return decodeURI(y);
        }
    }
}



/* Keep a global array of timers */
var timers = new Array();
var loadingtimers = new Array();

function ClearTimers()
    {
    // Remove all existing page timers.
    for (var i = 0; i < timers.length; i++)
        {
        clearTimeout(timers[i]);
        }
    }

function ClearLoadingTimers()
    {
    // Remove all existing page timers.
    for (var i = 0; i < loadingtimers.length; i++)
        {
        clearTimeout(loadingtimers[i]);
        }
    }

/* AJAX loading of searchbar contents for search executed outside of searchbar */
function ReloadSearchBar()
{
    var SearchBar = jQuery('#SearchBarContainer');

    SearchBar.load(baseurl_short + "pages/ajax/reload_searchbar.php?ajax=true&pagename=" + pagename, function (response, status, xhr) {
        if (status=="error") {
            SearchBar.html(errorpageload  + xhr.status + " " + xhr.statusText + "<br/>" + response);
        } else if (typeof chosen_config !== 'undefined' && chosen_config['#SearchBox select']!=='undefined') {
            // Load completed, add Chosen dropdowns if configured
            jQuery('#SearchBox select').each(function() {
                ChosenDropdownInit(this, '#SearchBox select');
            });
        }
    });

    return false;
}

/* Scroll to top if parameter set - used when changing pages */
function pageScrolltop(element)
    {
    jQuery(element).animate({scrollTop:0}, 'fast');
    }

/* AJAX loading of central space contents given a link */
function CentralSpaceLoad (anchor,scrolltop,modal,keep_fragment = true)
    {
    ajaxinprogress=true;
    var CentralSpace=jQuery('#CentralSpace');

    if (typeof modal=='undefined') {modal=false;}

    // Prohibit edit page in modal when already in centralspace because this combination does not work
    if (basename(window.location.href).substr(0,8)=="edit.php" && typeof anchor.href!='undefined' && basename(anchor.href).substr(0,8)=="edit.php")
        {
        modal = false;
        }

    if (!modal)
        {
        // If what we're loading isn't a modal, close any modal. Ensures the modal closes if a link on it is clicked.
        ModalClose();
        }
    else
        {
        // Targeting a modal. Set the target to be the modal, not CentralSpace.
        CentralSpace=jQuery('#modal');
        }

    // Handle straight urls:
    if (typeof(anchor)!=='object'){
        var plainurl=anchor;
        var anchor = document.createElement('a');
        anchor.href=plainurl;
    }

    // Remove fragments when necessary
    if(anchor.href.includes('#') && keep_fragment == false)
        {
        anchor.href = anchor.href.substr(0, anchor.href.lastIndexOf('#'));
        }

    /* Open as standard link in new tab (no AJAX) if URL is external */
    if (anchor.hostname != "" && window.location.hostname != anchor.hostname)
        {
        var win=window.open(anchor.href,'_blank');win.focus();
        return false;
        }

    /* Handle link normally (no AJAX) if the CentralSpace element does not exist */
    if (!CentralSpace )
        {
        location.href=anchor.href;
        return false;
        }

    /* more exceptions, going to or from pages without header */
    var fromnoheader=false;
    var tonoheader=false;
    if (
            basename(window.location.href).substr(0,11)=="preview.php"
            ||
            basename(window.location.href).substr(0,9)=="index.php"
            ||
            basename(window.location.href).substr(0,16)=="team_plugins.php"
            ||
            basename(window.location.href).substr(0,19)=="search_advanced.php"
        ) {
            fromnoheader=true;
        }

    if (
            basename(anchor.href).substr(0,11)=="preview.php"
            ||
            basename(anchor.href).substr(0,9)=="index.php"
            ||
            basename(anchor.href).substr(0,19)=="search_advanced.php"
        ) {
            tonoheader=true;
        }

    if (typeof fromnoheaderadd!=='undefined' && !modal)
        {
        for (var i = 0; i < fromnoheaderadd.length; i++)
            {
            if (basename(window.location.href).substr(0,fromnoheaderadd[i].charindex)==fromnoheaderadd[i].page) fromnoheader=true;
            }
        }
    if (typeof tonoheaderadd!=='undefined' && !modal)
        {
        for (var i = 0; i < tonoheaderadd.length; i++)
            {
            if (basename(anchor.href).substr(0,tonoheaderadd[i].charindex)==tonoheaderadd[i].page) tonoheader=true;
            }
        }

    // XOR to allow these pages to ajax with themselves
    if((tonoheader || fromnoheader) && !(fromnoheader && tonoheader) && !modal)
        {
        location.href = anchor.href;

        CentralSpace.trigger('CentralSpaceLoadedNoHeader', [{url: anchor.href}]);

        return false;
        }

    var url = anchor.href;
    pagename=basename(url);
    pagename=pagename.substr(0, pagename.lastIndexOf('.'));
    pluginname=getPluginName(url);

    var end_url = url.substr(url.indexOf('#'));
    // Drop # at end of url if present
    if (end_url.substr(0,1) == "#") {
        url = url.substr(0,url.length - end_url.length);
    }

    // Attach ajax parameter
    if (url.indexOf("?")!=-1)
        {
        url += '&ajax=true';
        }
    else
        {
        url += '?ajax=true';
        }

    // Reinstate # at end of url if present
    if (end_url.substr(0,1) == "#") {
        url += end_url;
    }

    if (modal) {url+="&modal=true";}

    // Fade out the link temporarily while loading. Helps to give the user feedback that their click is having an effect.
    // if (!modal) {jQuery(anchor).fadeTo(0,0.6);}

    // Start the timer for the loading box.
    CentralSpaceShowProcessing();

    CentralSpace.load(url, function (response, status, xhr)
        {
        if(xhr.status == 403)
            {
            CentralSpaceHideProcessing();
            styledalert(errortext,xhr.responseText);
            }
        else if (xhr.status == 205)
            {
            console.debug("Full page reload required " + response);
            CentralSpaceHideProcessing();
            window.location.href = anchor.href;
            }
        else if (status=="error")
            {
            CentralSpaceHideProcessing();
            CentralSpace.html(errorpageload  + xhr.status + " " + xhr.statusText + "<br/>" + response);
            jQuery(anchor).fadeTo(0,1);
            }
        else
            {
            // Load completed
            CentralSpaceHideProcessing();
            if (modal)
                {
                // Show the modal
                ModalCentre();
                jQuery('#modal_overlay').fadeIn('fast');
                if (modalalign=='right')
                    {
                    // Right aligned "drop down" style modal used for My Account.
                    jQuery('#modal').slideDown('fast');
                    }
                else
                    {
                    jQuery('#modal').show();
                    }
                jQuery('#modal').focus();
                }

            // Activate or deactivate the large slideshow, if this function is enabled.
            if (typeof ActivateSlideshow == 'function' && !modal)
                {
                if (basename(anchor.href).substr(0,8)=="home.php")
                {
                ActivateSlideshow();
                }
                else
                {
                DeactivateSlideshow();
                }
                }

            // Only allow reordering when search results are collections
            if(basename(anchor.href).substr(0, 10) == 'search.php')
                {
                var query_strings = get_query_strings(anchor.href);

                if(!is_empty(query_strings))
                    {
                    if(query_strings.hasOwnProperty('search') && query_strings.search.substring(0, 11) !== '!collection')
                        {
                        allow_reorder = false;
                        }
                    }

                CentralSpace.trigger('CentralSpaceSortable');
                }

            CentralSpace.trigger('CentralSpaceLoaded', [{url: url}]);

            // Update title in browser for accessibility
            updatePageTitle(pagename, pluginname);

            // Change the browser URL and save the CentralSpace HTML state in the browser's history record.
            if(typeof(top.history.pushState)=='function' && !modal)
                {
                top.history.pushState(document.title+'&&&'+CentralSpace.html(), applicationname, anchor.href);
                }
            }

            /* Scroll to top if parameter set - used when changing pages */
            if (scrolltop==true)
                {
                if (modal)
                    {
                    pageScrolltop(scrolltopElementModal);
                    }
                else if (jQuery(window).width() <= 1100)
                    {
                    pageScrolltop(scrolltopElementContainer);
                    }
                else
                    {
                    pageScrolltop(scrolltopElementCentral);
                    }
                }

            // Add accessibility enhancement:
            CentralSpace.append('<!-- Use aria-live assertive for high priority changes in the content: -->');
            CentralSpace.append('<span role="status" aria-live="assertive" class="ui-helper-hidden-accessible"></span>');

            // Add global trash bin:
            CentralSpace.append(global_trash_html);
            CentralSpace.trigger('prepareDragDrop');

            // Add Chosen dropdowns, if configured
            if (typeof chosen_config !== 'undefined' && chosen_config['#CentralSpace select']!=='undefined')
                {
                jQuery('#CentralSpace select').each(function()
                    {
                    ChosenDropdownInit(this, '#CentralSpace select');
                    });
                }

            if (typeof AdditionalJs == 'function') {
              AdditionalJs();
            }

            ReloadLinks();

        });

    jQuery('#UICenter').show(0);
    jQuery("#SearchBarContainer").removeClass("FullSearch");

    ajaxinprogress=false;
    return false;
    }


/* When back button is clicked, reload AJAX content stored in browser history record */
top.window.onpopstate = function(event)
    {

    if (!event.state) {return true;} // No state

   page=window.history.state;
   mytitle=page.substr(0, page.indexOf('&&&'));
   if (mytitle.substr(-1,1)!="'" && mytitle.length!=0) {
   page=page.substr(mytitle.length+3);
   document.title=mytitle;

    // Calculate the name of the page the user is navigating to.
    pagename=basename(document.URL);
    pagename=pagename.substr(0, pagename.lastIndexOf('.'));

    if (pagename=="home" || pagename=="view" || pagename=="preview" || pagename=="search" || pagename=="collection_manage"){

        // Certain pages do not handle back navigation well as start-up scripts are not executed. Always reload in these cases.

        // The ultimate fix is for this all to be unnecessary due
        // to scripts initialising correctly, perhaps using an event or similar,
        // or for all initialisation to have already completed in the header.php load ~DH

        CentralSpaceShowProcessing();
        window.location.reload(true);
        return true;
    }
    ModalClose();
    jQuery('#CentralSpace').html(page);

    }
}


/* AJAX posting of a form, result are displayed in the CentralSpace area. */
function CentralSpacePost (form, scrolltop, modal, update_history, container_id)
    {
    update_history = (typeof update_history !== "undefined" ? update_history : true);
    ajaxinprogress=true;
    var url=form.action;
    let CentralSpaceCtID = typeof container_id !== "undefined" ? container_id : 'CentralSpace';
    var CentralSpace=jQuery('#' + CentralSpaceCtID);// for ajax targeting top div

    formdata = jQuery(form).serialize();
    if (typeof modal=='undefined') {modal=false;}
    if (!modal)
        {
        // If what we're loading isn't a modal, close any modal. Ensures the modal closes if a link on it is clicked.
        ModalClose();
        }
    else
        {
        // Targeting a modal. Set the target to be the modal, not CentralSpace.
        CentralSpace=jQuery('#modal');
        }

        var end_url = url.substr(url.indexOf('#'));
        // Drop # at end of url if present
        if (end_url.substr(0,1) == "#") {
            url = url.substr(0,url.length - end_url.length);
        }
    
        // Attach ajax parameter
        if (url.indexOf("?")!=-1)
            {
            url += '&ajax=true';
            }
        else
            {
            url += '?ajax=true';
            }
        url += '&posting=true';
        // Reinstate # at end of url if present
        if (end_url.substr(0,1) == "#") {
            url += end_url;
        }

    CentralSpaceShowProcessing();

    pagename=basename(url);
    pagename=pagename.substr(0, pagename.lastIndexOf('.'));
    pluginname=getPluginName(url);
    jQuery.post(url,formdata,function(data)
        {
        // Check for a redirect (use instead of HTTP redirect to avoid URL not updating)
        if(isJson(data))
            {
            jsonresponse = JSON.parse(data);
            if(typeof jsonresponse.redirecturl !== "undefined")
                {
                CentralSpaceLoad(jsonresponse.redirecturl);
                }
            return;
            }
        CentralSpaceHideProcessing();
        CentralSpace.html(data);

        jQuery('#UICenter').show(0);
        jQuery("#SearchBarContainer").removeClass("FullSearch");

        // Add global trash bin:
        CentralSpace.append(global_trash_html);
        CentralSpace.trigger('prepareDragDrop');

        // Activate or deactivate the large slideshow, if this function is enabled.
        if (typeof ActivateSlideshow == 'function' && !modal)
            {
            if (basename(form.action).substr(0,8)=="home.php")
            {
            ActivateSlideshow();
            }
            else
            {
            DeactivateSlideshow();
            }
            }

        // Update title in browser for accessibility
        updatePageTitle(pagename, pluginname);

        // Change the browser URL and save the CentralSpace HTML state in the browser's history record.
        if(update_history && typeof(top.history.pushState)=='function' && !modal)
            {
            top.history.pushState(document.title+'&&&'+data, pagetitle, form.action);
            }

        /* Scroll to top if parameter set - used when changing pages */
        if (scrolltop==true && (typeof preventautoscroll == 'undefined' || !preventautoscroll)) {
                if (modal)
                    {
                    pageScrolltop(scrolltopElementModal);
                    }
                else
                    {
                    pageScrolltop(scrolltopElementCentral);
                    }
        }

        // Reset scroll prevention flag
        preventautoscroll = false;

        // Add Chosen dropdowns, if configured
        if (typeof chosen_config !== 'undefined' && chosen_config['#CentralSpace select']!=='undefined')
            {
            jQuery('#CentralSpace select').each(function()
                {
                ChosenDropdownInit(this, '#CentralSpace select');
                });
            }

        return false;
        })
    .fail(function(result,textStatus) {
        if (result.status>0)
            {
            CentralSpaceHideProcessing();
            if(result.status == 409)
                {
                // This is used for edit conflicts - show the response
                CentralSpace.append(result.responseText);
                }
            else if(result.status == 403)
                {
                styledalert(errortext,result.responseText);
                }
            else
                {
                CentralSpace.html(errorpageload + result.status + ' ' + result.statusText + '<br/>URL:  ' + url + '<br/>POST data: ' + jQuery(form).serialize());
                }
            }
        });
    ajaxinprogress=false;

    // Reload Browse bar item if required
    if (typeof browsereload !== "undefined")
        {
        toggleBrowseElements(browsereload, true);
        }

    return false;
    }


/* AJAX loading of CollectionDiv contents given a link */
function CollectionDivLoad (anchor,scrolltop)
    {
    // Handle straight urls:
    if (typeof(anchor)!=='object'){
        var plainurl=anchor;
        var anchor = document.createElement('a');
        anchor.href=plainurl;
    }

    /* Handle link normally if the CollectionDiv element does not exist */

    if (jQuery('#CollectionDiv').length==0 && top.collections!==undefined)
        {
        top.collections.location.href=anchor.href;
        return false;
        }

    /* Scroll to top if parameter set - used when changing pages */
    if (scrolltop==true) {pageScrolltop(scrolltopElementCollection);}

    var url = anchor.href;

    if (url.indexOf("?")!=-1)
        {
        url += '&ajax=true';
        }
    else
        {
        url += '?ajax=true';
        }

    // Load the collection bar and execute a callback after the load has completed
    jQuery('#CollectionDiv').load(url, function ()
        {
        jQuery("#CollectionDiv").trigger("CollectionDiv_loaded");

        // For each resource in centralspace, assume its not in the collection, so show + icon and hide - icon
        jQuery('div.ResourcePanel a.addToCollection').removeClass('DisplayNone');
        jQuery('div.ResourcePanel a.removeFromCollection').addClass('DisplayNone');

        // For each resource in the collection bar, set all matching items in central space to hide + icon and show - icon
        collection_resources.forEach(function(value) {
            var resource_in_centralspace=jQuery('#ResourceShell' + value +'.ResourcePanel');
            if(resource_in_centralspace.length == 0)
                {
                resource_in_centralspace=jQuery('#CentralSpace [data-identifier="'+jQuery(this).attr('data-identifier')+'"]');
                }
            resource_in_centralspace.find('div.ResourcePanelIcons > a.addToCollection').addClass('DisplayNone');
            resource_in_centralspace.find('div.ResourcePanelIcons > a.removeFromCollection').removeClass('DisplayNone');
            });

        });

    return false;
    }


function directDownload(url, element = undefined)
    {
    console.debug('directDownload(url = %o, element = %o)', url, element);
    dlIFrma = document.getElementById('dlIFrm');

    if(typeof dlIFrma != "undefined")
        {
        dlIFrma.src = url;
        if (element != undefined){
            // Lock width of download options column to prevent it resizeing due to smaller download button
            let options_td = jQuery(element).parents('tbody').children('tr:first').children('td:last');
            options_td.attr('style', 'width: ' + options_td.width() + 'px;');

            let el = jQuery(element);
            let el_txt = el.text();
            el.html('<i id="download_spinner" class="icon-loader-circle lucide--spin" style="font-size: 100%"></i>');
            jQuery(dlIFrma).one('load', () => el.text(el_txt));
        }
        }
    else
        {
        window.open(url, '_blank').focus();
        }
    }



/* AJAX loading of navigation link */
function ReloadLinks()
    {
    if(!linkreload)
        {
        return false;
        }

    var nav2=jQuery('#HeaderNav2');
    if(!nav2.has("#HeaderLinksContainer").length)
        {
        return false;
        }

    nav2.load(baseurl_short+"pages/ajax/reload_links.php?ajax=true", function (response, status, xhr)
        {
        // 403 is returned when user is logged out and ajax request! @see revision #12655
        if(xhr.status == 403)
            {
            window.location = baseurl_short + "login.php";
            }
        else if(status=="error")
            {
            var SearchBar=jQuery('#SearchBarContainer');
            SearchBar.html(errorpageload  + xhr.status + " " + xhr.statusText + "<br/>" + response);
            }
        else
            {
            // Load completed
            ActivateHeaderLink(document.location.href);
            }
        });

    headerLinksDropdown();
    return false;
    }

function relateresources(ref,related,action, ctx)
    {
    var add_param='1';
    if (action=="remove") { add_param='0';}

    var post_data = {
    'ref': ref,
    'related': related,
    'add': add_param
    };

    api("update_related_resource", post_data, function(response) {
    if(response!= false)
        {
        jQuery('#relatedresource' + related).remove();
        return true;
        }
    else
        {
        alert ("ERROR");
        return false;
        }
    }, ctx);
    }

/*
When an object of class "CollapsibleSectionHead" is clicked then next element is collapsed or expanded.
 */
function registerCollapsibleSections(use_cookies, callback, data)
    {
    // Default params
    use_cookies = (typeof use_cookies !== 'undefined') ? use_cookies : true;

    jQuery(document).ready(function()
        {
        jQuery('.CollapsibleSectionHead').click(function()
            {
            cur    = jQuery(this).next();
            cur_id = cur.attr("id");
            var cur_state = null;

            if (cur.is(':visible'))
                {
                if(use_cookies)
                    {
                    SetCookie(cur_id, 'collapsed');
                    }

                cur_state = "collapsed";
                jQuery(this).removeClass('expanded');
                jQuery(this).addClass('collapsed');
                }
            else
                {
                if(use_cookies)
                    {
                    SetCookie(cur_id, 'expanded');
                    }

                cur_state = "expanded";
                jQuery(this).addClass('expanded');
                jQuery(this).removeClass('collapsed');
                }

            cur.stop(); // Stop existing animation if any
            cur.slideToggle(400, function () {
                if (callback) callback(this);
            });

            jQuery("#" + cur_id).trigger("ToggleCollapsibleSection", [{section_id: cur_id, state: cur_state}]);

            return false;
            }).each(function()
                {
                cur_id = jQuery(this).next().attr("id");

                if ((use_cookies && getCookie(cur_id) == 'collapsed') || jQuery(this).hasClass('collapsed'))
                    {
                    jQuery(this).next().hide();
                    jQuery(this).addClass('collapsed');
                    }
                else
                    {
                    jQuery(this).addClass('expanded');
                    }
                });
        });
    }

function getQueryStrings()
{
    var assoc  = {};
    var decode = function(s) { return decodeURIComponent(s.replace(/\+/g, " ")); };
    var queryString = location.search.substring(1);
    var keyValues = queryString.split('&');

    for(var i in keyValues) {
        if (typeof keyValues[i].split == 'function'){

            var key = keyValues[i].split('=');
            if (key.length > 1) {
                assoc[decode(key[0])] = decode(key[1]);
            }
        }
    }

    return assoc;
}

// Take the current query string and attach it to a form action
// Useful when avoiding moving away from the page you are on
function passQueryStrings(params, formID)
{
    var form_action = '';
    var query_string = '';
    var qs = getQueryStrings();

    if (params.constructor !== Array) {
        return false;
    }

    // Pass only specified params to the query string
    for (var i = 0; i < params.length; i++) {
        if (qs.hasOwnProperty(params[i]) && query_string === '') {
            query_string = params[i] + '=' + qs[params[i]];
        } else if(qs.hasOwnProperty(params[i]) && query_string !== '') {
            query_string += '&' + params[i] + '=' + qs[params[i]];
        }
    }

    form_action = document.getElementById(formID).action + '?' + query_string;

    if (document.getElementById(formID).action !== form_action) {
        document.getElementById(formID).action = form_action;
    }

    return true;
}

// Use this function to confirm special searches
// e.g: is_special_search('!collection', 11)
function is_special_search(special_search, string_length)
{
    var query_strings = getQueryStrings();

    if (is_empty(query_strings)) {
        return false;
    }

    return query_strings.search.substring(0, string_length) === special_search;
}

// Check if object is empty or not
// Note: safer solution compared to using keys()
function is_empty(object)
{
    for(var property in object) {

        if(object.hasOwnProperty(property)) {
            return false;
        }

    }

    return true;
}

// Returns object with all query strings found
// Note: should be used when the location does not have the correct URL
function get_query_strings(url)
{
    if (url.trim() === '') {
        return {};
    }

    var query_strings = {};
    var url_split = url.split('?');

    if (url_split.length === 1) {
        return query_strings;
    }

    url_split = url_split[1];
    url_split = url_split.split('&');

    for (var i = 0; i < url_split.length; i++) {
        var var_value_pair = url_split[i].split('=');
        query_strings[var_value_pair[0]] = decodeURI(var_value_pair[1]);
    }

    return query_strings;
}

function ModalLoad(url,jump,fittosize,align)
    {
    // Load to a modal rather than CentralSpace. "url" can be an anchor object.

    // No modal? Don't launch a modal. Go to CentralSpaceLoad
    if (!jQuery('#modal')) {return CentralSpaceLoad(url,jump);}

    // Prohibit edit page in modal when already in centralspace because this combination does not work
    if (basename(window.location.href).substr(0,8)=="edit.php" && typeof url.href!='undefined' && basename(url.href).substr(0,8)=="edit.php")
        {
        return CentralSpaceLoad(url,jump);
        }

    // Window smaller than the modal? No point showing a modal as it wouldn't appear over the background.
    if (jQuery(window).width()<=jQuery('#modal').width()) {return CentralSpaceLoad(url,jump);}

    jQuery('#modal').draggable({ handle: ".RecordHeader", opacity: 0.7 });

    // Set modalfit so that resizing does not change the size
    modalfit = false;
    if ((typeof fittosize != 'undefined') && fittosize) {
        modalfit = true;
    }

    // Set modalalign to store the alignment of the current modal (needs to be global so that resizing the window still correctly aligns the modal)
    modalalign = false;
    if (typeof align != 'undefined') {
        modalalign = align;
    }

    // To help with calling of a popup modal from full modal, can return to previous modal location
    if (typeof modalurl != 'undefined') {
        modalbackurl = modalurl;
    }

    url=SetContext(url);
    modalurl=url;

    return CentralSpaceLoad(url,jump,true);
    }

function ModalPost(form,jump,fittosize)
    // Post to a modal rather than CentralSpace. "url" can be an anchor object.
    {
    var url=form.action;
    jQuery('#modal_overlay').show();
    jQuery('#modal').show();
    jQuery('#modal').draggable({ handle: ".RecordHeader" });

     // Set modalfit so that resizing does not change the size
    modalfit=false;
    if ((typeof fittosize != 'undefined') && fittosize) {
        modalfit = true;
    }

    ModalCentre();

    if (typeof modalurl != 'undefined') {
        modalbackurl = modalurl;
    }

    modalurl=url;
    return CentralSpacePost(form,jump,true);
    }

function ModalCentre()
{
    // Centre the modal and overlay on the screen. Called automatically when opened and also when the browser is resized, but can also be called manually.

    // If modalfit is not specified default to the full modal dimensions
    if (modalalign == 'right') {
        modalmaxheight = Math.max(jQuery(window).height() - 100);
        modalwidth = 550;
        if (!TileNav) {
            // Smaller menu if tile navigation disabled.
            modalwidth = 250;
        }
    } else if (modalalign == 'rightnarrow') {
        modalmaxheight = Math.max(jQuery(window).height() - 100);
        modalwidth = 440;
    } else if ((typeof modalfit != 'undefined') && modalfit) {
        modalmaxheight = 'auto';
        modalwidth = 'auto';
    } else {
        modalmaxheight = jQuery('.ui-layout-container').height() - 60;
        modalwidth = 1235;
    }

    jQuery('#modal').css({
        maxHeight: modalmaxheight,
        width: modalwidth
    });

    // Support alignment of modal (e.g. for 'My Account')
    topmargin = 30;
    if (modalalign == 'right' || modalalign == 'rightnarrow') {
        left = Math.max(jQuery(window).width() - jQuery('#modal').outerWidth(), 0) - 20;
        topmargin = 50;
    } else {
        left = Math.max(jQuery(window).width() - jQuery('#modal').outerWidth(), 0) / 2;
    }

    jQuery('#modal').css({
        top: topmargin + jQuery(window).scrollTop(),
        left: left + jQuery(window).scrollLeft()
    });

    // Resize knowledge base iframe to correct size if present
    if (jQuery('#knowledge_base').length) {
        jQuery('#knowledge_base').css('height', modalmaxheight);
        jQuery('#modal').css('overflow', 'hidden');
    } else {
        jQuery('#modal').css('overflow', 'auto');
    }

}

function ModalClose()
{
    jQuery('#modal_overlay').fadeOut('fast');
    jQuery('#modal').hide();
    jQuery('#modal').html('');
    jQuery('.toastify').hide();

    // Trigger an event so we can detect when a modal is closed
    if (typeof modalurl != 'undefined') {
        jQuery('#CentralSpace').trigger('ModalClosed', [{url: modalurl}]);
        delete modalurl;
    }

    let pagename = document.querySelector('meta[name="pagename"]').getAttribute('content');
    let pluginname = document.querySelector('meta[name="pluginname"]').getAttribute('content');
    updatePageTitle(pagename, pluginname);
}

// For modals, set what context the modal was called from and insert if missing
function SetContext(url)
    {
    if(typeof url != 'string'){url = url.href;}
    var query_strings = get_query_strings(url);
    var context = query_strings.context;

    if(context==undefined){
        if(jQuery('#modal').is(":visible")){
            context='Modal';
        } else {
            context='Body';
        }
        if (url.indexOf("?")!=-1){
            url = url + '&context=' + context;
        } else {
            url = url + '?context=' + context;
        }


    }

    return url;
    }

// Update header links to add a class that indicates current location
function ActivateHeaderLink(activeurl)
        {
        var matchedstring=0;
        activelink='';

        // Was the 'recent' link clicked?
        var recentlink=(decodeURIComponent(activeurl).indexOf('%21last')>-1 ||
        decodeURIComponent(activeurl).indexOf('!last')>-1);

        jQuery('#HeaderNav2 li a').each(function() {
            // Remove current class from all header links
            jQuery(this).removeClass('current');

            if(decodeURIComponent(activeurl).indexOf(this.href)>-1

            // Set "recent" rather than "search results" when the search is for recent items.
            || (recentlink && this.href.indexOf('%21last')>-1))
                {
                    if (this.href.length>matchedstring) {
                    // Find longest matched URL
                    matchedstring=this.href.length;
                    activelink=jQuery(this);
                    }
                }
            });
        jQuery(activelink).addClass('current').attr('aria-current', 'page');
        }

function ReplaceUrlParameter(url, parameter, value){
    var parameterstring = new RegExp('\\b(' + parameter + '=).*?(&|$)')
    if(url.search(parameterstring)>=0){
        return url.replace(parameterstring,'$1' + value + '$2');
        }
    return url + (url.indexOf('?')>0 ? '&' : '?') + parameter + '=' + value;
    }

function nl2br(text) {
  return (text).replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + '<br />' + '$2');
}

// Check if given JSON can be parsed or not
function isJson(str) {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}

// Display toast notification on screen
function toastNotification(type, text) {
    let displayTime = 3000;
    let closeButton = false;
    let icon = "check";

    if (type == "Download") {
        icon = "download";
    } else if (type == "Error") {
        displayTime = 10000;
        closeButton = true;
        icon = "triangle-alert";
    }

    Toastify({
        text: '<i class="icon-' + icon + '"></i>&nbsp;&nbsp;' + text,
        className: "toastify-" + type,
        close: closeButton,
        gravity: "bottom",
        position: "center",
        duration: displayTime,
        escapeMarkup: false,
        offset: {
            y: 10
        },
    }).showToast();
}

function styledalert(title,text,minwidth){
 if(typeof minwidth === 'undefined') {
        minwidth = 300;
    }
    jQuery("#modal_dialog").html(text);
    jQuery("#modal_dialog").dialog({
        title: title,
        resizable: false,
        minWidth: minwidth,
        dialogClass: 'no-close',
        modal: true,
        maxHeight:500,
        buttons: [{
            // Decode the text label (see header.php) because jquery.dialog internally will also use textContent (safe sink)
            text: new DOMParser().parseFromString(oktext, 'text/html').documentElement.textContent,
            click: function() {
            jQuery( this ).dialog( "close" );
            }
        }],
        create: function(event, ui)
            {
            if (title == '')
                {
                jQuery('.ui-dialog-title').html('<i class=\'icon-info\' ></i>');
                }
            }
        });

    jQuery("#modal_dialog").bind('clickoutside',function(){
        jQuery( this ).dialog( "close" );
    });

    }

// Restrict all defined numeric fields
jQuery(document).ready(function()
    {
    jQuery('.numericinput').keypress(function(e)
        {
        var key = e.which || e.keyCode;
        if (!(key >= 48 && key <= 57) && // Interval of values (0-9)
             (key !== 8))             // Backspace
            {
            e.preventDefault();
            return false;
            }
        })
    });

function HideCollectionBar() {
    if (typeof collection_bar_hidden === "undefined" || collection_bar_hidden==false){
        colbarresizeon=false;
        myLayout.options.south.spacing_open = 0;
        myLayout.options.south.spacing_closed = 0;
        myLayout.options.south.minSize = 0;
        myLayout.sizePane("south",1);
        jQuery('#CollectionDiv').hide();
        collection_bar_hidden=true;
        }
    }


function ShowCollectionBar() {
    if (typeof collection_bar_hidden === "undefined" || collection_bar_hidden==true){
        colbarresizeon=true;
        myLayout.options.south.spacing_open = 6;
        myLayout.options.south.spacing_closed = 6;
        myLayout.options.south.minSize = 40;
        collection_bar_hidden=false;
        jQuery('#CollectionDiv').show();
        InitThumbs();
    }
}

function ChosenDropdownInit(elem, selector)
    {
    if(typeof chosen_config!=='undefined')
        {
        css_width = jQuery(elem).css("width").replace("px","");
        css_boxsixing = jQuery(elem).css("box-sizing");
        if(css_boxsixing=='border-box')
            {
            css_padding_left = jQuery(elem).css("padding-left").replace("px","");
            css_padding_right = jQuery(elem).css("padding-right").replace("px","");
            css_width_total = (parseInt(css_width)+parseInt(css_padding_left)+parseInt(css_padding_right))+"px";
            }
        else
            {
            css_width_total = css_width;
            }
        chosen_config[selector]['width']=css_width_total;
        jQuery(elem).chosen(chosen_config[selector]);
        }
    }

function removeSearchTagInputPills(search_input)
    {
    var tags = search_input.tagEditor('getTags')[0].tags;

    for(i = 0; i < tags.length; i++)
        {
        search_input.tagEditor('removeTag', tags[i]);
        }

    return true;
    }

function array_diff(array_1, array_2)
    {
    var a    = []
    var diff = [];

    for(var i = 0; i < array_1.length; i++)
        {
        a[array_1[i]] = true;
        }

    for(var i = 0; i < array_2.length; i++)
        {
        if(a[array_2[i]])
            {
            delete a[array_2[i]];
            }
        else
            {
            a[array_2[i]] = true;
            }
        }

    for(var k in a)
        {
        diff.push(k);
        }

    return diff;
    }

function StripResizeResults(targetImageHeight)
{
    var screenWidth = jQuery('#CentralSpaceResources').width()-15;
    var images = jQuery('.ImageStrip');
    var accumulatedWidth = 0;
    var imageGap = 15;
    var processArray = [];

    for (var i = 0; i < images.length; i++) {
        // Take a copy to get the unscaled image size
        var imageCopy = new Image();imageCopy.src=images[i].src;
        var ratio = imageCopy.width/imageCopy.height;
        var presentationWidth = (targetImageHeight * ratio);
        accumulatedWidth += presentationWidth+imageGap; // add to our calculation of the row width

        if (accumulatedWidth > screenWidth) {
            // Work out a height for the row (excluding the current image which will fall to the next row)
            var factor = screenWidth / (accumulatedWidth-presentationWidth - imageGap);

            // Rescale images on current row
            for (var n=0; n< processArray.length; n++) {
                jQuery(processArray[n]).css("height", Math.floor(factor * targetImageHeight) + "px");
            }

            // Empty process array
            processArray = [];
            accumulatedWidth = images[i].width + imageGap;
            }
        processArray.push(images[i]);
    }
}

/**
 * Load unified dropdown actions
 *
* @param {string} pagename
* @param {string|object} id The ID of the element where the actions should be loaded. Can also be a jQuery object.
* @param {string} type The action type (@see ajax/load_actions.php)
* @param {int} ref The associated (type) ref (e.g for type "collection", the collection ref)
* @param {object} extra_data Any other extra data the associated type might need (e.g for type "search", the searchparams)
*
* @return {Promise} Returns a promise of whether the actions were loaded or not (true/false)
*/
function LoadActions(pagename,id,type,ref, extra_data)
    {
    console.debug('LoadActions(pagename = %o, id = %o, type = %o, ref = %o, extra_data = %o)', pagename, id, type, ref, extra_data);

    var actionspace = typeof id === "object" ? id : jQuery('#' + id);
    console.debug('[LoadActions] actionspace = %o', actionspace);

    if(actionspace.attr('data-actions-loaded') != 0)
        {
        return jQuery.when(false);
        }

    CentralSpaceShowProcessing();
    url = baseurl_short+"pages/ajax/load_actions.php";
    var data = {
        actiontype: type,
        ref: ref,
        page: pagename
    };
    var data_obj = Object.assign({}, data, extra_data);
    var request = jQuery.ajax({
        type:'GET',
        url: url,
        data: data_obj,
        async: false
    });

    return jQuery.when(request)
        .then(function(response, status, xhr)
            {
            console.debug('[LoadActions] xhr = %o', xhr);
            if(response.includes('pagename="login"'))
                {
                styledalert(errortext,errornotloggedin + ' <a href="' + baseurl_short + '" target="_new">' + login + '</a>');
                }
            else if(!response.includes('<option'))
                {
                console.error('[LoadActions] Unexpected response, no options');
                }
            else
                {
                console.debug('[LoadActions] actionspace.html(response)');
                actionspace.html(response);
                
                // If type is selection collection, set attribute to 1 to prevent further reloading (not needed as selection 
                // collection actions won't change unless selection is cleared which is handled in ajax_collections.js)
                if(type == "selection_collection") 
                    {
                    actionspace.attr('data-actions-loaded','1');
                    } 
                else 
                    {
                    actionspace.attr('data-actions-loaded','0');
                    }

                // The following hack is required for Chrome (tested in version 111). When rendering dropdowns in the
                // lower part of the screen that slide upwards, the new options fail to show until the dropdown is reopened.
                setTimeout(() => actionspace.css('height', 'auto'), 100);

                return true;
                }

            return false;
            })
        .always(() => CentralSpaceHideProcessing());
    }

/**
* Get extension of a file
*
*/
function getFilePathExtension(path)
    {
    var filename   = path.split('\\').pop().split('/').pop();

    return filename.substring(filename.lastIndexOf('.') + 1, filename.length) || filename;
    }

/*
*
* Toggles the locking of an edit field
*
*/
function toggleFieldLock(field)
{
    if (typeof lockedfields === 'undefined') {
        lockedfields = new Array();
    }

    if (lockedfields.indexOf(field.toString())!=-1) {
        jQuery('#lock_icon_' + field + '> i').removeClass('icon-lock');
        jQuery('#lock_icon_' + field + '> i').addClass('icon-lock-open');
        jQuery('#lock_icon_' + field).parent().closest('div').removeClass('lockedQuestion');
        lockedfields = jQuery.grep(lockedfields, function(value) {return value != field.toString();});
        SetCookie('lockedfields',lockedfields.map(String));
    } else {
        jQuery('#lock_icon_' + field + '> i').removeClass('icon-lock-open');
        jQuery('#lock_icon_' + field + '> i').addClass('icon-lock');
        jQuery('#lock_icon_' + field).parent().closest('div').addClass('lockedQuestion');
        // Remove checksum as this will break things
        jQuery('#field_' + field + '_checksum').val("");
        jQuery('#' + field + '_checksum').val("");
        lockedfields.push(field.toString());
        SetCookie('lockedfields', lockedfields);
    }

    if (lockedfields.length > 0) {
        jQuery(".save_auto_next").show();
    } else {
        jQuery(".save_auto_next").hide();
    }

    return true;
}

/*
*
* Places overflowing header links into a dropdown
*
*/
function headerLinksDropdown() {

    if (jQuery(window).width() < 1200) {
        return;
    }

    var menuBox = jQuery("#HeaderNav2");
    var mb_offset = menuBox.offset();
    var mb_width = menuBox.outerWidth(true);
    var mb_rightedge = mb_offset.left + mb_width;
    
    var uploadBox = jQuery("#HeaderNav1");
    var ub_offset = uploadBox.offset();
    var ub_leftedge = ub_offset.left;

    var containerTarget = jQuery("#HeaderLinksContainer");
    var containerWidth = containerTarget.innerWidth();

    var containerOverlap = mb_rightedge - ub_leftedge;
    
    var links =  jQuery("#HeaderLinksContainer").find(".HeaderLink"); // get elements that are links in the header bar
    var linksWidth = 0;
    var caretCreated = false;

    jQuery("#OverFlowLinks").remove(); // remove the drop-down menu div

    if (jQuery('#OverflowListElement').is(':visible'))
        {
        caretCreated = true;
        }

    for (var i = 0; i < links.length; i++)
        {
        linksWidth += jQuery(links[i]).outerWidth();

        if (linksWidth > containerWidth-containerOverlap)
            {
            if (!caretCreated)
                {
                jQuery(links[i- 1]).after('<li id="OverflowListElement"><a href="#" id="DropdownCaret" onclick="showHideLinks();"><span class="icon-chevron-down"></span></a></li>');
                // append a div to the document.body element that will contain the drop-down menu items
                jQuery(document.body).append('<div id="OverFlowLinks"><ul id="HiddenLinks"></ul></div>');
                caretCreated = true;
                }
            // remove the li element from header links and append it to drop-down link list
            jQuery(links[i]).remove();
            jQuery(links[i]).appendTo('#HiddenLinks');
            }
        }
}

/*
*
* Show or hide the header overflow drop down links
*
*/
function showHideLinks()
    {
    jQuery('div#OverFlowLinks').toggle();
    jQuery('div#OverFlowLinks').css('right', 290);
    jQuery('div#OverFlowLinks').css('z-index', 1000);
    jQuery('div#OverFlowLinks').css('top', 64);
    }

/*
*
* Toggles the edit_multi_checkbox question on edit page when in batch edit mode (ie. multiple == true)
*
*/
function batch_edit_toggle_edit_multi_checkbox_question(question_ref)
    {
    var question = document.getElementById('question_' + question_ref);
    var modeselect = document.getElementById('modeselect_' + question_ref);
    var findreplace = document.getElementById('findreplace_' + question_ref);
    var revert = document.getElementById('revert_' + question_ref);
    var remove = document.getElementById('remove_from_field_' + question_ref);

    // The copy_from_field is rendered with an id of the resource_type_field ref, not the question_ref (seq number) passed in here
    // The resource_type_field ref is however the last digits of the checkbox name attribute, so use that to get the copyfrom element
    var this_checkbox=document.getElementById('editthis_' + question_ref);
    var copyfrom = document.getElementById('copy_from_field_' + this_checkbox.name.substring(15));

    var displayexisting = document.getElementById('displayexisting_' + question_ref);

    if(jQuery("#editthis_" + question_ref).prop("checked"))
        {
        question.style.display = 'block';
        modeselect.style.display = 'block';
        if(displayexisting)
            {
            displayexisting.style.display = 'block';
            getCurrentFixedListFieldVals(question_ref);
            setActionPromptText(question_ref);
            }
        }
    else
        {
        question.style.display = 'none';
        modeselect.style.display = 'none';
        findreplace.style.display = 'none';
        // revert is not always present, but if it is then hide it
        if(revert)
            {
            revert.style.display = 'none';
            }
        // copyfrom is not always present, but if it is then hide it
        if(copyfrom)
            {
            copyfrom.style.display = 'none';
            }
        // remove is not always present, but if it is then hide it
        if(remove)
            {
            remove.style.display = 'none';
            }

        // Only present for fixed list fields.
        if(displayexisting)
            {
            displayexisting.style.display = 'none';
            jQuery('div .currentmultiquestion' + question_ref).hide();
            }

        document.getElementById('modeselectinput_' + question_ref).selectedIndex = 0;
        }
    }

/*
*
* Redirects to a target URL after a specified delay time expressed in milliseconds
*
*/
function redirectAfterDelay(targetUrl,delayTime)
    {
    setTimeout(function()
        {
        window.location.href = targetUrl;
        }, delayTime);
    }

function UICenterScrollBottom()
    {
    // Smoothly croll to the bottom of the central container. Useful to show content after expanding a section at the bottom of the page (e.g. the uploader)
    window.setTimeout('jQuery(\'#UICenter\').animate({scrollTop: document.getElementById(\'UICenter\').scrollHeight },"slow");',300);
    }



/**
* Detect the users' local time zone using the Internationalisation API
*
* @returns {string}
*/
function detect_local_timezone()
    {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
    }




/**
 *
 * @summary Escape characters in a string that may cause the string to be interpreted as HTML in the browser. If regex pattern parameter use this to escape characters, otherwise use default regex pattern provided.
 *
 * @param {string} string - string potentially containing characters to escape - required
 * @param {string} pattern - regex pattern containing HTML characters to escape - not required
 *
 * @var  {array} entityMap - array of HTML entities with their escaped versions
 * @var {string} default_pattern  - default regex pattern to use if no pattern passed
 *
 * @returns {string} escaped_string - string with characters escaped
 *
 * @see upload_plupload.php plupload.addFileFilter()
 *
 */

function escape_HTML(string, pattern)
    {

    var entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '/': '&#x2F;',
        '`': '&#x60;',
        '=': '&#x3D;'
        };

    var default_pattern = "[&<>\"'`=]";

    // if regex pattern is not passed as argument use default pattern
    pattern =  (pattern === undefined) ? default_pattern : pattern;

    pattern = new RegExp(pattern, "g");

    var escaped_string = string.replace(pattern, function (s)
        {
        return entityMap[s];
        });

    return escaped_string;
    }

/* Manage .TabBar clicks so the show/hide the correct .TabbedPanel within .TabBar */

function SelectMetaTab(resourceid,tabnum,modal)
{
    if (modal)
        {
        jQuery('#Modaltabswitch'+tabnum+'-'+resourceid).siblings().removeClass('TabSelected');
        jQuery('#Modaltabswitch'+tabnum+'-'+resourceid).addClass('TabSelected');
        jQuery('div.MetaTabIsModal-'+resourceid).hide();
        jQuery('#Modaltab'+tabnum+'-'+resourceid).show();
        }
    else
        {
        jQuery('#tabswitch'+tabnum+'-'+resourceid).siblings().removeClass('TabSelected');
        jQuery('#tabswitch'+tabnum+'-'+resourceid).addClass('TabSelected');
        jQuery('div.MetaTabIsNotModal-'+resourceid).hide();
        jQuery('#tab'+tabnum+'-'+resourceid).show();
        }
}

/**
 * Handle clicks on the .TabBar in the resource downloads area
 *
 * @param {string} tabName - name of the tab clicked
 * @param {boolean} modal - whether the resource is opened in a modal or not
 */
function selectDownloadTab(tabName, modal)
    {
    if (modal)
        {
        jQuery('#modal #RecordDownloadTabButtons .Tab').removeClass('TabSelected');
        jQuery('#modal #'+tabName+'Button').addClass('TabSelected');
        jQuery('#modal #RecordDownloadTabContainer .RecordDownloadSpace').hide();
        jQuery('#modal #'+tabName).show();
        }
    else
        {
        jQuery('#RecordDownloadTabButtons .Tab').removeClass('TabSelected');
        jQuery('#'+tabName+'Button').addClass('TabSelected');
        jQuery('#RecordDownloadTabContainer .RecordDownloadSpace').hide();
        jQuery('#'+tabName).show();
        }
    }

/**
 * Handle clicks on the tab bar in the search area
 *
 * @param {string} tabName - name of the tab clicked
 */
function selectSearchBarTab(tabName)
    {
    jQuery('#SearchBarTabsContainer .SearchBarTab').removeClass('SearchBarTabSelected');

    switch(tabName)
        {
        case 'search':
            jQuery('#SearchBarTabsContainer .SearchTab').addClass('SearchBarTabSelected');
            jQuery('#SearchBoxPanel').show();
            jQuery('#BrowseBarContainer').hide();
            SetCookie('selected_search_tab', 'search');
            break;
        case 'browse':
            jQuery('#SearchBarTabsContainer .BrowseTab').addClass('SearchBarTabSelected');
            jQuery('#SearchBoxPanel').hide();
            jQuery('#BrowseBarContainer').show();
            SetCookie('selected_search_tab', 'browse');
            break;
        }
    }

// unset cookie
function unsetCookie(cookieName, cpath)
    {
    document.cookie = cookieName + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;path=' + cpath;
    }


/**
* Check if system upgrade is in progress. Reloads the page if it is to show user current status.
*
* @return void
*/
function check_upgrade_in_progress()
{
    jQuery.ajax({
        type: 'GET',
        url: baseurl + "/pages/ajax/message.php",
        data: {
            ajax: true,
            check_upgrade_in_progress: true
        },
        dataType: "json"
    }).done(function(response) {
        if (response.status != "success") {
            return;
        }

        if (response.data.upgrade_in_progress) {
            window.location.reload(true);
        }
    });
}

/**
* Add hidden input dynamically to help further requests maintain modals.
*
* @param   {string}     form                The form ID to which to append the hidden input
* @param   {boolean}    decision_factor     TRUE to add it, FALSE otherwise
*
* @return boolean
*/
function add_hidden_modal_input(form, decision_factor)
    {
    if(!decision_factor)
        {
        return false;
        }

    jQuery('<input type="hidden" name="modal" value="true">').appendTo("#" + form);

    return true;
    }

function api(name, params, callback, post_data_extra = {}, options = {})
    {
    query = {};
    query["function"] = name;
    for (var key in params) {
        query[key] = params[key];
        }
    console.debug("API Query",query);

    postobj = Object.assign({}, post_data_extra);
    postobj['query'] = jQuery.param(query);
    postobj['authmode'] = "native";

    var onStart = (options && typeof options.onStart === "function") ? options.onStart : null;
    var onEnd   = (options && typeof options.onEnd === "function")   ? options.onEnd   : null;

    if (onStart) onStart();

    // Below is used to get access to responseURL
    var xhr = new XMLHttpRequest();
    jQuery.ajax({
        method: 'POST',
        url: baseurl_short + 'api/?',
        data: postobj,
        xhr: function() {
            return xhr;
            }
        })
        .done(function(data, textStatus, jqXHR )
            {
            response = jqXHR.responseText;
            if(response.includes('pagename="login"'))
                {
                styledalert(errortext,errornotloggedin + ' <a href="' + baseurl_short + '" target="_new">' + login + '</a>');
                return false;
                }
            if(isJson(response))
                {
                response = JSON.parse(response)
                }
            if(typeof callback === "function")
                {
                callback(response);
                }
            })
        .fail(function(jqXHR, textStatus, errorThrown)
            {
            let response = typeof jqXHR.responseJSON.data.message !== 'undefined'
                ? jqXHR.responseJSON.data.message
                : textStatus;
            console.error("API Error: %s - %s", errorThrown, response);
            })
        .always(function() {
            if (onEnd) onEnd();
        });
    }

/**
* Deselect the children and all associated descendents of a given node in a category tree
* Children which are themselves parents will be opened if necessary so that their descendents can be deselected
*
* @param   {jsTree}     theJstree    The category tree component
* @param   {number}     nodeId       The node to be processed
*
* @return void
*/
function deselect_children_of_jstree_node(theJstree, nodeId)
{
    // Node is open by the time we get here so that its children can also be deselected if necessary
    var children_of_this = theJstree.jstree('get_children_dom', nodeId);

    for (var i = 0; i < children_of_this.length; i++) {
        // Trigger deselection of each child if necessary
        if (theJstree.jstree('is_selected', children_of_this[i].id)) {
            theJstree.jstree('deselect_node', children_of_this[i].id);
        } else if (theJstree.jstree('is_parent', children_of_this[i].id)) {
            // Child is not selected; but continue to process the descendents to cater for tier gaps
            if (theJstree.jstree('is_closed', children_of_this[i].id)) {
                theJstree.jstree('open_node', children_of_this[i].id, function(e, data) {
                    deselect_children_of_jstree_node(theJstree, e.id);
                });
            } else {
                // Child is already open
                deselect_children_of_jstree_node(theJstree, children_of_this[i].id);
            }
        }
    }
}

/**
* Show the help box if available.
*
* @param   {string}   id   The identifier (NOT the id attribute) for the div.FormHelp element.
*
* @return void
*/
function ShowHelp(id)
    {
    var el_id = 'help_' + id;
    if (document.getElementById(el_id))
        {
        jQuery('#' + el_id).fadeIn();
        }
    }

/**
* Hide the help box if available.
*
* @param   {string}   id   The identifier (NOT the id attribute) for the div.FormHelp element.
*
* @return void
*/
function HideHelp(id)
    {
    var el_id = 'help_' + id;
    if (document.getElementById(el_id))
        {
        document.getElementById(el_id).style.display = 'none';
        }
    }

var ProcessingTimersActive=false;
var ProcessingFirstTimer=0;
var ProcessingSecondTimer=0;
var ProcessingDisplayTimer=0;
var ProcessingAPITimer=0;
var ProcessingMessages;
var ProcessingCount=-1;

function CentralSpaceShowProcessing(delay = 2000, defaultmessage = '')
    {
    if (ProcessingTimersActive) { return; }

    if (b_progressmsgs && (external_access_key == null || external_access_key.length == 0)) {
        jQuery('#ProcessingStatus').html('');
        ProcessingMessages=[];
        ProcessingDisplayTimer = setInterval(CentralSpace_ProcessingDisplayTimer, 350);
        ProcessingFirstTimer=setTimeout(CentralSpace_ProcessingAPITimer, 1000);
        ProcessingAPITimer = setInterval(CentralSpace_ProcessingAPITimer, 2000);
        jQuery('#ProcessingStatus').html(DOMPurify.sanitize(defaultmessage));
    }

    ProcessingSecondTimer=setTimeout(
        function (){jQuery('#ProcessingBox').fadeIn('fast');},
        delay
    );

    ProcessingTimersActive=true;
    }

function CentralSpaceHideProcessing()
    {
    if (!ProcessingTimersActive) { return; }

    jQuery('#ProcessingBox').fadeOut('fast');
    clearInterval(ProcessingDisplayTimer); ProcessingDisplayTimer=0; 
    clearTimeout(ProcessingFirstTimer);    ProcessingFirstTimer=0; 
    clearInterval(ProcessingAPITimer);     ProcessingAPITimer=0; 
    clearTimeout(ProcessingSecondTimer);   ProcessingSecondTimer=0; 

    ProcessingTimersActive=false;
    }

function CentralSpace_ProcessingDisplayTimer()
    {
    console.debug("Display timer tick, messages:", ProcessingMessages);
    var NewMessage=ProcessingMessages.shift();
    if (NewMessage!==undefined)
        {
        jQuery('#ProcessingStatus').html(DOMPurify.sanitize(NewMessage));
        }
    }

function CentralSpace_ProcessingAPITimer()
    {
    // Use the API to fetch any new processing messages.       
    api("get_processing_message", null, function(response) {
    console.log ("API execution");
    console.log(response);
    if(response!= false && Array.isArray(response))
        {
        ProcessingMessages=ProcessingMessages.concat(response);
        console.debug('ProcessingMessages = %o', ProcessingMessages);
        }
    }, ProcessingCSRF);
    }



/**
* Generate ResourceSpace URL with context aware params (from an elements' data set).
*
* @param   {HTML element}  el         The element from which we want to generate a URL for
* @param   {string}        path       URL path
* @param   {string}        data_key   The data set key to retrieve params from. Note: the data value is an object
*
* @return string
*/
function GenerateRsUrlFromElement(el, path, data_key)
    {
    params = jQuery(el).data(data_key);
    url = baseurl_short + path + '?' + new URLSearchParams(params).toString();
    console.debug('[GenerateRsUrlFromElement] url = %s', url);
    return url;
    }

/**
 * Get client's date and time in friendly format
 *
 * @return string
 *
 */
function rsGetLocalDatetime()
    {
    var dt = new Date();
    var logtime = dt.getFullYear() + "-" +dt.getMonth() + "-" + dt.getDate() + " " + dt.getHours() + ":" + dt.getMinutes() + ":" + dt.getSeconds();
    return logtime;
    }

/**
 * Log all ResourceSpace global client side variables
 */
function resourcespace_get_global_state() {
    // global variables that exist in chrome browser by default
    let stdChromeVars = [
        "parent",
        "opener",
        "top",
        "length",
        "frames",
        "closed",
        "location",
        "self",
        "window",
        "document",
        "name",
        "customElements",
        "history",
        "locationbar",
        "menubar",
        "personalbar",
        "scrollbars",
        "statusbar",
        "toolbar",
        "status",
        "frameElement",
        "navigator",
        "origin",
        "external",
        "screen",
        "innerWidth",
        "innerHeight",
        "scrollX",
        "pageXOffset",
        "scrollY",
        "pageYOffset",
        "visualViewport",
        "screenX",
        "screenY",
        "outerWidth",
        "outerHeight",
        "devicePixelRatio",
        "clientInformation",
        "screenLeft",
        "screenTop",
        "defaultStatus",
        "defaultstatus",
        "styleMedia",
        "onsearch",
        "isSecureContext",
        "onabort",
        "onblur",
        "oncancel",
        "oncanplay",
        "oncanplaythrough",
        "onchange",
        "onclick",
        "onclose",
        "oncontextmenu",
        "oncuechange",
        "ondblclick",
        "ondrag",
        "ondragend",
        "ondragenter",
        "ondragleave",
        "ondragover",
        "ondragstart",
        "ondrop",
        "ondurationchange",
        "onemptied",
        "onended",
        "onerror",
        "onfocus",
        "onformdata",
        "oninput",
        "oninvalid",
        "onkeydown",
        "onkeypress",
        "onkeyup",
        "onload",
        "onloadeddata",
        "onloadedmetadata",
        "onloadstart",
        "onmousedown",
        "onmouseenter",
        "onmouseleave",
        "onmousemove",
        "onmouseout",
        "onmouseover",
        "onmouseup",
        "onmousewheel",
        "onpause",
        "onplay",
        "onplaying",
        "onprogress",
        "onratechange",
        "onreset",
        "onresize",
        "onscroll",
        "onseeked",
        "onseeking",
        "onselect",
        "onstalled",
        "onsubmit",
        "onsuspend",
        "ontimeupdate",
        "ontoggle",
        "onvolumechange",
        "onwaiting",
        "onwebkitanimationend",
        "onwebkitanimationiteration",
        "onwebkitanimationstart",
        "onwebkittransitionend",
        "onwheel",
        "onauxclick",
        "ongotpointercapture",
        "onlostpointercapture",
        "onpointerdown",
        "onpointermove",
        "onpointerup",
        "onpointercancel",
        "onpointerover",
        "onpointerout",
        "onpointerenter",
        "onpointerleave",
        "onselectstart",
        "onselectionchange",
        "onanimationend",
        "onanimationiteration",
        "onanimationstart",
        "ontransitionend",
        "onafterprint",
        "onbeforeprint",
        "onbeforeunload",
        "onhashchange",
        "onlanguagechange",
        "onmessage",
        "onmessageerror",
        "onoffline",
        "ononline",
        "onpagehide",
        "onpageshow",
        "onpopstate",
        "onrejectionhandled",
        "onstorage",
        "onunhandledrejection",
        "onunload",
        "performance",
        "stop",
        "open",
        "alert",
        "confirm",
        "prompt",
        "print",
        "queueMicrotask",
        "requestAnimationFrame",
        "cancelAnimationFrame",
        "captureEvents",
        "releaseEvents",
        "requestIdleCallback",
        "cancelIdleCallback",
        "getComputedStyle",
        "matchMedia",
        "moveTo",
        "moveBy",
        "resizeTo",
        "resizeBy",
        "scroll",
        "scrollTo",
        "scrollBy",
        "getSelection",
        "find",
        "webkitRequestAnimationFrame",
        "webkitCancelAnimationFrame",
        "fetch",
        "btoa",
        "atob",
        "setTimeout",
        "clearTimeout",
        "setInterval",
        "clearInterval",
        "createImageBitmap",
        "close",
        "focus",
        "blur",
        "postMessage",
        "onappinstalled",
        "onbeforeinstallprompt",
        "crypto",
        "indexedDB",
        "webkitStorageInfo",
        "sessionStorage",
        "localStorage",
        "chrome",
        "applicationCache",
        "onpointerrawupdate",
        "trustedTypes",
        "speechSynthesis",
        "webkitRequestFileSystem",
        "webkitResolveLocalFileSystemURL",
        "openDatabase",
        "caches",
        "ondevicemotion",
        "ondeviceorientation",
        "ondeviceorientationabsolute"
    ];

    let T = Object.keys(window)
        .filter(prop => stdChromeVars.indexOf(prop) < 0)
        .filter(prop => typeof window[prop] !== 'function')
        .sort()
    ;

    for (let i in T) {
        let prop = T[i];
        console.debug('%s: %s = %o', prop, typeof window[prop], window[prop]);
    }
}

function enforceSharePassword(error_text)
{
    let passwordInput = document.getElementById('sharepassword');
    if (passwordInput.hasAttribute('required') && passwordInput.value === '') {
        alert(error_text);
        return false;
    }
return true;
}

/**
 * Determine if we have minimum date parts in order to allow calling AutoSave (on edit page - see edit_fields/4.php)
 * @param {String} fieldidentifier 
 * @returns {bool}
 */
function sufficientDateParts(fieldidentifier)
{
    year = jQuery('#' + fieldidentifier + "-y").val() ?? "";
    month = jQuery('#' + fieldidentifier + "-m").val() ?? "";
    day = jQuery('#' + fieldidentifier + "-d").val() ?? "";
    hour = jQuery('#' + fieldidentifier + '-h').val() ?? "";
    minute = jQuery('#' + fieldidentifier + '-i').val() ?? "";

    console.debug("Day: (#" + fieldidentifier + "-d) : " + day);
    console.debug("Month: (#" + fieldidentifier + "-m) : " + month);
    console.debug("Year: (#" + fieldidentifier + "-y) : " +  year);
    console.debug("Hour: (#" + fieldidentifier + "-h) : " +  hour);
    console.debug("Minute: (#" + fieldidentifier + "-i) : " +  minute);

    const required_validator = (
        year !== '' // Always need a year
        && (day === '' || month !== '') // If a day is set then must have month
        && (hour === '' || day !== '') // If an hour is set then must have day
        && (minute === '' || hour !== '') // If a minute is set then must have hour
    );
    const optional_validator = (
        [year, month, day, hour, minute].filter(e => e === '').length === 5
        || required_validator
    );
    const valid = jQuery(`label[for="${fieldidentifier}"] > sup`).text() === '*'
        ? required_validator
        : optional_validator;

    if (!valid) {
        console.debug("Insufficient date inputs selected for field " + fieldidentifier + ". Preventing autosave");
    }
    return valid;
}

function registerResourceSelectDeselectHandlers() {
    jQuery('#CentralSpace').on('resourcesaddedtocollection', function(response,resource_list) {
        resource_list.forEach(function (resource)
            {
                jQuery("#ResourceShell" + resource).addClass("Selected");
                jQuery("#check" + resource).prop('checked','checked');
            });

        UpdateSelColSearchFilterBar();
        CentralSpaceHideProcessing();
    });

    jQuery('#CentralSpace').on('resourcesremovedfromcollection', function(response,resource_list) {
        resource_list.forEach(function (resource)
            {
                jQuery("#ResourceShell" + resource).removeClass("Selected");
                jQuery("#check" + resource).prop('checked','');
            });

        CentralSpaceHideProcessing();
        UpdateSelColSearchFilterBar();
    });
}

// Set a cookie to store the user's prefers-color-scheme value
function setThemePreference() {
    const darkMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    SetCookie('css_color_scheme', darkMode, 1000);
}

// Dynamically update header image using Ajax 
function updateHeaderImage() {
    const isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? "dark" : "light";
    const ajax_url = baseurl_short + 'pages/ajax/header_image.php?force_appearance=' + encodeURIComponent(isDarkMode);

    jQuery.ajax({
        type: "GET",
        url: ajax_url,
        success: function(data) {
            jQuery('#HeaderImg').attr("src", DOMPurify.sanitize(data, { SAFE_FOR_URL: true }));
        }
    });
}



// Hide/show function for hidden fields.
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('show-hidden-btn')) {
        const hiddenItem = e.target.nextElementSibling;
        if (hiddenItem) {
            hiddenItem.style.display = 'inline';
            e.target.style.display = 'none';
        }
    }
});

/**
 * Dynamically update the page title using Ajax.
 *
 * @param {string} pagename - The name of the page.
 * @param {string} [pluginname=''] - The name of the plugin (optional).
 */
function updatePageTitle(pagename, pluginname = '') {
    const params = new URLSearchParams({
        page: pagename
    });

    if (typeof pluginname !== 'undefined') {
        params.append('plugin', pluginname);
    }
    const ajax_url = baseurl_short + 'pages/ajax/update_page_title.php?' + params.toString();

    jQuery.ajax({
        type: "GET",
        url: ajax_url,
        success: function(data) {
            document.title = data;
        }
    })
}

/**
 * Extracts the plugin name from a given URL.
 * @param {string} url - The URL to extract the plugin name from.
 * @returns {string} The extracted plugin name or an empty string if not found.
 */
function getPluginName(url) {
    const pluginsIndex = url.indexOf('/plugins/');
    
    if (pluginsIndex !== -1) {
        const startIndex = pluginsIndex + 9; // Length of '/plugins/'
        const endIndex = url.indexOf('/', startIndex);
        
        if (endIndex !== -1) {
            return url.substring(startIndex, endIndex);
        }
    }
    
    return '';
}
