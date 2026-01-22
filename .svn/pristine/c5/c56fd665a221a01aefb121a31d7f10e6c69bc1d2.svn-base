<?php
function HookRemote_assistAllHomeafterwelcometext()
    {
    # Load HTML from the ResourceSpace site, passing the base URL. The base URL allows the ResourceSpace website to feed appropriate options based
    # on whether the installation is a registered trial system, user installation, etc.
    global $baseurl;
    ?>
    <script>
    jQuery(document).ready(function()
        {
        if (jQuery('remote_assist').children().length==0)
            {
            jQuery('#remote_assist').load('https://www.resourcespace.com/remote_assist_plugin.php?baseurl=<?php echo base64_encode($baseurl) ?>');
            }
        });
    jQuery(document).on('keydown', function(e) {
        if (e.key === "Escape") {
            jQuery('#promptbox').hide();
        }
    });
    </script>
    <div id="remote_assist"></div>
    <?php
    }
