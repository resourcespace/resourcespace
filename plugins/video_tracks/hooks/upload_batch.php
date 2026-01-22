<?php

// Use same function as used after staticsync alt file 
function HookVideo_tracksAllAfter_alt_upload($resource, $altfile="")
        {
        HookVideo_tracksAllStaticsync_after_alt ($resource, $altfile);              
        }
