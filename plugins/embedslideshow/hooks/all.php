<?php

function HookEmbedslideshowAllExternal_share_view_as_internal_override()
{
    global $external_share_view_as_internal, $pagename;

    if ($pagename === "viewer") {
        $external_share_view_as_internal = false;
    }
}
