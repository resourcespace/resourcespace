<?php

function HookEmbedvideoAllDirectdownloadaccess()
{
    return getval('ext', '') === 'vtt';
}

function HookEmbedvideoAllModified_cors_process()
{
    return getval('ext', '') === 'vtt'
        && getval('alternative', 0, true) !== 0
        && getval('ref', 0, true) !== 0
        && (
            file_exists(get_resource_path(getval('ref', 0, true), true, '', false, 'mp4'))
            || file_exists(get_resource_path(getval('ref', 0, true), true, 'pre', false, 'mp4'))
        );
}
