<?php

function HookImage_textAllInitialise()
    {
    global $image_text_fieldvars;
    config_register_core_fieldvars("Image text plugin",$image_text_fieldvars);
    }
