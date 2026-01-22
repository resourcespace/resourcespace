var SlideshowTimer=0;
var SlideshowActive=false;
            
function RegisterSlideshowImage(image, resource, single_image_flag)
    {
    if(typeof single_image_flag === 'undefined')
        {
        single_image_flag = false;
        }

    // If we are only registering one image then remove any images registered so far
    if(single_image_flag)
        {
        SlideshowImages.length = 0;
        }

    SlideshowImages.push(image);
    }

function SlideshowChange()
    {
    if (SlideshowImages.length==0 || !SlideshowActive) {return false;}

    if (SlideshowImages.length===1) {
        jQuery(".slide").css("background-image", "url(" + SlideshowImages[0] + ")");
        return true;
    }

    var SlideshowNext = jQuery(".slide").not(".slide-active");
    if (SlideshowCurrent>=SlideshowImages.length)
        {
        SlideshowCurrent=0;
        }

    SlideshowNext.css("background-image", "url(" + SlideshowImages[SlideshowCurrent] + ")");
    SlideshowNext.addClass("slide-active").siblings(".slide").removeClass("slide-active");
    SlideshowCurrent++;

    var photo_delay = 1000 * big_slideshow_timer;
    window.clearTimeout(SlideshowTimer);    
    if (!StaticSlideshowImage) {SlideshowTimer=window.setTimeout(SlideshowChange, photo_delay);}
    
    return true;
    }

function ActivateSlideshow(show_footer)
    {
    if (!SlideshowActive)
        {
        SlideshowCurrent=0;
        SlideshowActive=true;
        jQuery(".slide-active").css("background-image", "url(" + SlideshowImages[SlideshowCurrent] + ")");
        jQuery(".slide").css("transition", "none");
        SlideshowChange();
        setTimeout(function() {jQuery(".slide").css("transition", "opacity 1s ease-in-out");}, 50);

        if (typeof show_footer == 'undefined' || !show_footer)
            {
            jQuery('#Footer').hide();
            }
        }

        jQuery( document ).ready(function() 
            {
            jQuery('body').css('transition', 'background-image 1s linear');
            jQuery('.slide').css('z-index', '0');
            });
    }
    
function DeactivateSlideshow()
    {
    jQuery('body').css('background-image','none');
    jQuery('.slide').css('z-index', '-1');
    SlideshowActive=false;
    window.clearTimeout(SlideshowTimer);

    jQuery('#Footer').show();
    }

