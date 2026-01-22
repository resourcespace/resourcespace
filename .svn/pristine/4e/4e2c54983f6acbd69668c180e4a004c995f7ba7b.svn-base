function showResponsiveCollection() {
    jQuery('#cover').fadeIn(0);
    jQuery('#responsive_collection_toggle').addClass('slide_btn');
    jQuery('#responsive_collection_toggle a').html(responsive_show);
    jQuery('#CollectionDiv').show(0);
    ShowThumbs();
}
function hideResponsiveCollection() {
    if(!jQuery('#CollectionDiv').length){return false;}
    jQuery('#cover').fadeOut(0);
    jQuery('#responsive_collection_toggle').removeClass('slide_btn');
    jQuery('#responsive_collection_toggle a').html(responsive_hide);
    jQuery('#CollectionDiv').hide(0);
    HideThumbs();
}
function checkResponsiveCollection() {
    thumbs = getCookie("thumbs");
    if(thumbs==="show") {
        showResponsiveCollection();
    } else if(thumbs==="hide") {
        hideResponsiveCollection();
    }
}

function responsiveCollectionBar() {
    if(jQuery(window).width()<=900 && !(jQuery('#responsive_collection_toggle').length)) {
        jQuery('#CollectionDiv').hide(0);
        responsive_hide = function() {
            return jQuery('#CollectionMinitems').html();
        }
        jQuery('#UICenter').before("<div id='cover' style='display: none;'></div>");
        jQuery("#CollectionDiv").before("<div id='responsive_collection_toggle' class='CollectBack'><a class='rotate' href='#'></a></div>");
        hideResponsiveCollection();

        jQuery("#responsive_collection_toggle").click(function(event) {
            event.preventDefault();
            if(!jQuery('#responsive_collection_toggle').hasClass('slide_btn')) {
                showResponsiveCollection();
            } else {
                hideResponsiveCollection();
            }
        });
    }
    else if(jQuery(window).width()<=900 && (jQuery('#responsive_collection_toggle').length)) {
        jQuery("#responsive_collection_toggle").show();
        hideResponsiveCollection();
    }
    else if(jQuery(window).width()>900 && (jQuery('#responsive_collection_toggle').length)) {
        jQuery("#CollectionDiv").show();
        jQuery("#responsive_collection_toggle").hide();
        thumbs = getCookie("thumbs");
        if(thumbs==="show") {
            showResponsiveCollection();
        }else if(thumbs==="hide") {
            showResponsiveCollection();
            HideThumbs();
        }
    }
}


function hideMyCollectionsCols()
    {
    if(jQuery(window).width() < 700 && jQuery("#collectionform td:nth-child(2)").is(':hidden') && !is_touch_device())
        {
        jQuery("td:nth-child(2),th:nth-child(2)").show();
        jQuery("td:nth-child(3),th:nth-child(3)").show();
        jQuery("td:nth-child(4),th:nth-child(4)").show();
        jQuery("td:nth-child(6),th:nth-child(6)").show();
        jQuery("td:nth-child(7),th:nth-child(7)").show();
        }

    if(jQuery(window).width() < 800 && jQuery("#collectionform td:nth-child(8)").is(':hidden') && !is_touch_device())
        {
        jQuery("td:nth-child(8),th:nth-child(8)").show();
        }
    }

function touchScroll(id)
    {
    if(is_touch_device())
        {
        let el             = document.getElementById(id);
        var scrollStartPos = 0;

        el.addEventListener("touchstart", function(event) {
            scrollStartPos = this.scrollTop+event.touches[0].pageY;
        });

        el.addEventListener("touchmove", function(event) {
            this.scrollTop = scrollStartPos-event.touches[0].pageY;
        });
        }
    }