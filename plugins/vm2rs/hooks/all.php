<?php

function HookVm2rsAllInitialise()
    {
    global $vm2rs_fieldvars;
    config_register_core_fieldvars("vm2rs plugin",$vm2rs_fieldvars);
    }