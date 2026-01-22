<?php

function HookSensitive_imagesAllAdditionaljoins()
    {
    global $sensitive_images_field;
    if ($sensitive_images_field>0)
        {
        // If plugin is configured, add the sensitive images field to the array of join fields so it's returned in the results.
        return array($sensitive_images_field);
        }
    }

function HookSensitive_imagesAllStopblurbleed()
{
global $sensitive_images_field;
if ($sensitive_images_field>0)
    {
    // If plugin is configured, prevent bleed of blur outside of the blurred image container
    return " overflow:hidden;";
    }
}


function SensitiveImageResultsReplace($collection)
    {
    // Blur image in search results and collections bar
    global $sensitive_images_field, $sensitive_images_blur_level, $result, $n;
    if ($sensitive_images_field>0 && isset($result[$n]["field" . $sensitive_images_field]))
        {
        $sensitive=$result[$n]["field" . $sensitive_images_field];
        if (strlen($sensitive)>0)
            {
            ?>
            <style>
            <?php echo $collection ? "#CollectionSpace" : "#CentralSpaceResources"; ?> #ResourceShell<?php echo $result[$n]["ref"]; ?> 
             img {filter: blur(<?php echo (int)$sensitive_images_blur_level; ?>px);}


            <?php if (!$collection) { ?>#CentralSpaceResources #ResourceShell<?php echo $result[$n]["ref"]; ?>::before 
                {
                content: '!';
                z-index: 5;
                position: absolute;
                left: 50%;
                top: 40%;
                transform: translate( -50%, -50% );
                color: white;
                font-size: <?php echo $collection ? "20px" : "50px"; ?> !important;
                }
            <?php } ?>
            </style>
            <?php
            }
        }
    }

function HookSensitive_imagesSearchResourcethumbtop()
    {
    // Blur image in search results
    SensitiveImageResultsReplace(false);
    }

function HookSensitive_imagesCollectionsRendercollectionthumb()
    {
    // Blue image in collections bar
    SensitiveImageResultsReplace(true);
    }
