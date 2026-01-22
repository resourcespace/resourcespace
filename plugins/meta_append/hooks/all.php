<?php

function HookMeta_appendAllInitialise()
    {
    global $meta_append_fieldvars;
    config_register_core_fieldvars("Meta append plugin",$meta_append_fieldvars);
    }
