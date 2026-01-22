<?php
/**
* JS functions for the Browse Bar
*
*/

include_once "../include/boot.php";
include_once "../include/authenticate.php";
header("Content-type: text/javascript");
?>

function renderBrowseItem(node, parent)
    {
    console.debug("Calling renderBrowseItem(node = %o, parent = %o)", node, parent);
    var parentid = parent.attr('data-browse-id');
    var newlevel = parent.attr("data-browse-level");
    newlevel++;
   
    var indent = "<div class='BrowseBarStructure BrowseLine'>&nbsp;</div>";
    var refreshel = "<a href='#' class='BrowseRefresh' onclick='toggleBrowseElements(\"%BROWSE_ID%\",true, true);return false;' ><i class='icon-refresh-cw reloadicon'></i></a>";
    var refreshel = refreshel.replace("%BROWSE_ID%",node.id);
   
    if(node.expandable != "false")
        {
        var expand = "<div class='BrowseBarStructure BrowseBarExpand'><a href='#' class='browse_expand browse_closed' onclick='toggleBrowseElements(\"%BROWSE_ID%\", false, true);return false;' alt='<?php echo escape($lang["expand"]); ?>'></a></div>";
        expand = expand.replace("%BROWSE_ID%",node.id);        
        }
    else
        {
        expand = "";    
        }
    
    var rowindent = "";
    for (i = 0; i < newlevel; i++)
        { 
        rowindent += indent;
        }

    var brwstmplt = jQuery('#BrowseBarTemplate').html();
    brwstmplt = brwstmplt.replace("%BROWSE_DROP%", node.drop ? "BrowseBarDroppable" : "");
    brwstmplt = brwstmplt.replace("%BROWSE_NAME%",node.name);
                                  
                                  
    brwstmplt = brwstmplt.replace("%BROWSE_LEVEL%",newlevel);
    brwstmplt = brwstmplt.replace("%BROWSE_INDENT%",rowindent);    
    
    brwstmplt = brwstmplt.replace("%BROWSE_EXPAND%",expand);
    if(node.link != "")
        {
        if(node.modal)
            {
            linkfunction = "browsereload=\"" + parentid + "\";return ModalLoad(this,false,true);";
            }
        else
            {
            linkfunction = "return CentralSpaceLoad(this,true);";
            }
        
        link = "<a class='browse_droplink'  href='%BROWSE_LINK%' onclick='" + linkfunction + "'><div class='BrowseBarStructure BrowseType%BROWSE_CLASS%'>%ICON_HTML%</div><div class='BrowseBarLink' >%BROWSE_NAME%</div></a>";
        link = link.replace("%BROWSE_CLASS%",node.class);
        link = link.replace("%BROWSE_LINK%",node.link);  
        link = link.replace("%BROWSE_NAME%",node.name);      
        
        iconhtml = "";
        if(node.icon)
            {
            iconhtml = node.icon;
            }
        
        link = link.replace("%ICON_HTML%",iconhtml);
        
        brwstmplt = brwstmplt.replace("%BROWSE_TEXT%",link);  
        }
    else
        {
        var text = "<div class='BrowseBarStructure BrowseType%BROWSE_CLASS%'></div><div class='BrowseBarLink' >%BROWSE_NAME%</div>";
        text = text.replace("%BROWSE_CLASS%",node.class);
        text = text.replace("%BROWSE_NAME%",node.name);  
        brwstmplt = brwstmplt.replace("%BROWSE_TEXT%",text);
        }
    brwstmplt = brwstmplt.replace("%BROWSE_PARENT%",parentid); 
    brwstmplt = brwstmplt.replace("%BROWSE_ID%",node.id);
    brwstmplt = brwstmplt.replace("%BROWSE_REFRESH%",refreshel);
    parent.after(brwstmplt);
    }


function toggleBrowseElements(browse_id, reload, useraction)
    {
    console.debug("toggleBrowseElements(browse_id = %o, reload = %o, useraction = %o)", browse_id, reload, useraction);
    if (typeof reload === 'undefined') {reload = false;}
    if (typeof useraction === 'undefined') {useraction = false;}

    if (browse_clicked && useraction)
        {            
        return false;
        }

    if(useraction)
        {
        browse_clicked=true;
        }

    if(typeof b_loading === 'undefined')
        {
        b_loading = new Array();
        }

    var loadindex = b_loading.indexOf(browse_id);
    if (loadindex > -1)
        {
        // Already in progress
        return true;
        }
    
    if(typeof browse_toload === 'undefined')
        {
        browse_toload = new Array();
        }

    if(typeof browse_reloaded === 'undefined' || useraction)
        {
        // Used to ensure we don't get into an endless loop of loading parents and looking for non-existent child elements
        browse_reloaded = new Array();
        }

    var curel = jQuery(".BrowseBarItem[data-browse-id='" + browse_id + "']");

    if(!curel.length)
        {
        // Node not present, load parent first
        var item_elements = browse_id.split('-');
        item_elements.pop();
        if (item_elements.length  < 1)
            {
            // This is the root node and is not present, give up
            browse_clicked = false;
            curel.stop(true, false);
            return false;
            }
            
        var parentitem = item_elements.join('-');
        
        // Add this id so that it is loaded after the parent has completed
        if(typeof browsepostload[parentitem] === "undefined")
            {
            browsepostload[parentitem] = new Array();
            }

        browsepostload[parentitem].push(browse_id);
        toggleBrowseElements(parentitem, true);
        browse_clicked = false;
        return true;
        }
    
   
    var loaded =curel.attr("data-browse-loaded");
    var openclose = curel.find("a.browse_expand");
    var refreshicon = curel.find("a.BrowseRefresh i");

    if(typeof browseopen === 'undefined')
        {
        browseopen = new Array();
        }

    var curstatus = curel.attr("data-browse-status");
    
    if(curstatus == "open" && !reload)
        {
        // Hide the children and close, close all child items also
        
        jQuery(".BrowseBarItem[data-browse-parent|='" + browse_id + "']")
            .find("a.browse_expand")
            .removeClass("browse_expanded")
            .addClass("browse_closed")
            .attr("alt", "<?php echo escape($lang["expand"]); ?>");
        
        jQuery(".BrowseBarItem[data-browse-parent|='" + browse_id + "']").removeClass("BrowseOpen").attr("data-browse-status","closed").slideUp("fast",function() {
            browse_clicked = false;
            });

        
        openclose.removeClass("browse_expanded");
        openclose.addClass("browse_closed");
        openclose.attr('alt',"<?php echo escape($lang["expand"]); ?>");
        curel.attr("data-browse-status","closed");
        curel.removeClass("BrowseOpen");

        remaining = browseopen.filter(function(value, index, arr)
            {
            return value.substring(0, browse_id.length) != browse_id;
            });

        browseopen = remaining;
        SetCookie('browseopen',encodeURIComponent(browseopen));
        return true;
        }
        
    if(loaded == 1 && !reload)
        {
        // Show the child items
        jQuery("[data-browse-parent='" + browse_id + "']").slideDown("fast",function() {
            browse_clicked = false;
            });
        openclose.removeClass("browse_closed");
        openclose.addClass("browse_expanded");
        openclose.attr('alt',"<?php echo escape($lang["close"]); ?>");
        curel.attr("data-browse-status","open");
        curel.addClass("BrowseOpen");
        browseopen.push(browse_id);
        return true;
        }
    

    refreshicon.addClass("lucide-spin");
    b_loading.push(browse_id);
    console.debug("b_loading = %o", b_loading);
    
    if(typeof browsepostload === "undefined")
        {
        browsepostload = new Array();
        }
    if(typeof browsepostload[browse_id] === "undefined")
        {
        browsepostload[browse_id] = new Array();
        }

    // Remove any current child items before load/reload
    jQuery("[data-browse-parent|='" + browse_id + "']").remove();
                    
    url = baseurl_short+"pages/ajax/browsebar_load.php";
    
    var post_data = {
                    id: browse_id,
                    };
    jQuery.ajax({
        type:"GET",
        url: url,
        data: post_data,
        dataType: "json"     
        }).done(function(response, status, xhr)
            {
            // Load completed	
            // Parse response items
            //console.log(response);
            // Reverse so each can be appended in turn and still appear in correct order 
            response.items.reverse();
            response.items.forEach(function (item)
                {
                renderBrowseItem(item, curel);
                });
            
            // Show all immediate children
            jQuery("[data-browse-parent='" + browse_id + "']").slideDown();

        
            curel.attr("data-browse-status","open");
            curel.attr("data-browse-loaded","1");

            if (browseopen.indexOf(browse_id)==-1)
                {
                browseopen.push(browse_id);
                SetCookie('browseopen',encodeURIComponent(browseopen));
                }

            openclose.removeClass("browse_closed");
            openclose.addClass("browse_expanded");
            openclose.attr('alt',"<?php echo escape($lang["close"]); ?>");
            curel.addClass("BrowseOpen");
            refreshicon.removeClass("lucide-spin");

            var loadindex = b_loading.indexOf(browse_id);
            if (loadindex > -1)
                {
                b_loading.splice(loadindex, 1);
                }

            var toloadindex = browse_toload.indexOf(browse_id);
            if (toloadindex > -1)
                {
                browse_toload.splice(toloadindex, 1);
                }
                
            if(typeof browsepostload[parentitem] === "undefined")
                {
                browsepostload[parentitem] = new Array();
                }

            browsepostload[browse_id].forEach(function (childitem)
                {
                if(!browse_reloaded.includes(childitem))
                    {
                    console.debug("Finished loading %o, loading child item %o", browse_id, childitem);
                    toggleBrowseElements(childitem, true);
                    browse_reloaded.push(childitem); 
                    }
                });
                
            if(browse_toload.length == 0)
                {
                console.debug("Finished browse_bar reload, initialising drop"); 
                BrowseBarInit();
                browse_clicked = false;
                }
            else
                {
                console.debug("Still to load: %o", browse_toload);
                }
            })
        .fail(function(xhr, status, error)
            {
            if (xhr.status=="403")
                {				
                window.location = baseurl_short + "login.php";
                }
            else
                {
                var loadindex = b_loading.indexOf(browse_id);
                if (loadindex > -1)
                    {
                    b_loading.splice(loadindex, 1);
                    }
                browse_clicked = false;
                styledalert('<?php echo escape($lang["error"]); ?> ' + xhr.status, '<?php echo escape($lang['error_generic']); ?> : ' + error);	 refreshicon.removeClass("lucide-spin");
                }
            });

    browse_reloaded.push(browse_id);
    return true;
    }

function ReloadBrowseBar()
    {
    console.debug("ReloadBrowseBar()");
    var allopen = jQuery.cookie("browseopen") ? decodeURIComponent(jQuery.cookie("browseopen")).split(/,/) : new Array();  
    console.debug("allopen = %o", allopen);

    browse_toload = allopen;   
    console.debug("browse_toload = %o", browse_toload);
    allopen.forEach(function (item)
        {
        toggleBrowseElements(item, true);
        });
    }

function BrowseBarInit()
    {
    if(!jQuery('#BrowseBar'))
        {
		jQuery('#CentralSpaceContainer').removeClass('BrowseMode');
		jQuery('#Footer').removeClass('BrowseMode');
        }
        
    jQuery(".BrowseBarDroppable").droppable({
        accept: ".ResourcePanel, .CollectionPanelShell",

        drop: function(event, ui)
            {
            // Get the dragged resource id
            dropped = jQuery(ui.draggable);
            var resource_id = dropped.attr("id");
            resource_id = resource_id.replace("ResourceShell", "");
            
            // Get the drop target
            browsetarget = jQuery(this).attr("data-browse-id");
            item_elements = browsetarget.split('-'); 
            droptype = item_elements[0];
            switch(droptype)
                {
                case 'R':
                    tgt_rt = item_elements[1].replace('RT:','');
                    if(dropped.hasClass('ResourceType' + tgt_rt))
                        {
                        nodeid = item_elements[item_elements.length - 1].replace('N:','');
                        
                        var post_data = {
                            action: 'add_node',
                            node: nodeid,
                            resource: resource_id,
                            <?php echo generateAjaxToken('browse_action'); ?>
                            };
                        
                        BrowseAction(post_data,jQuery(this).find("a.browse_droplink"));
                        }
                    else
                        {
                        styledalert('<?php echo escape($lang['error']); ?>','<?php echo escape($lang['error-invalid_resource_type']); ?>');
                        browse_err = document.createElement( "div" ),
                        jQuery(browse_err).html('<?php echo escape($lang["save-error"]); ?>');
                        jQuery(browsetarget).append(browse_err);
                        jQuery(browse_err).fadeOut('slow');
                        }
                    break;
                case 'FC':
                case 'C':
                    cid = item_elements[item_elements.length - 1].replace('C:','');
                    AddResourceToCollection(event, ui, resource_id, '', cid);
                    jQuery(this).find(".BrowseBarLink a").fadeTo(100, 0.3, function() { jQuery(this).fadeTo(500, 1.0); });
                    break;
                default:
                    
                }
            },

        tolerance: "pointer"
        });
    
    if(typeof browsereload !== "undefined")
        {
        setTimeout(function() {jQuery("[data-browse-id='" + browsereload + "']")[0].scrollIntoView({ behavior: "smooth",block: "start"});browsereload = undefined;}, 200);        
        }
    }

function BrowseAction(post_data,browselink)
    {
    url = baseurl_short+"pages/ajax/browse_action.php";
    jQuery.ajax({
        type:'POST',
        url: url,
        data: post_data,
        dataType: "json", 
        async:true            
            })
        .done(function(response, status, xhr)
            {
            // Load completed	
            browselink.fadeTo(300, 0.3, function() {jQuery(this).fadeTo(1000, 1.0); });
            })
        .fail(function(xhr)
            {
                //console.log(response);
            if(typeof xhr.responseJSON.message !== "undefined")
                {
                styledalert('<?php echo escape($lang["error"]); ?>',xhr.responseJSON.message);
                }
            else
                {
                styledalert('<?php echo escape($lang["error"]); ?>', xhr.status + ' ' + xhr.statusText + ' ' + xhr.responseText);
                }
            });
    }
