<?php

function HookVimeo_publishAllInitialise()
    {
    global $vimeo_publish_fieldvars;
    config_register_core_fieldvars("Vimeo publish plugin",$vimeo_publish_fieldvars);
    }
