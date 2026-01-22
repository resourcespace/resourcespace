<?php

include_once __DIR__ . '/../include/search_notifications_functions.php';

function HookRse_search_notificationsSearchafterResulthints()
    {
    global $lang, $search, $restypes, $archive, $watched_searches_url, $k;

    $href = generateURL(
        $watched_searches_url,
        array(
            'callback' => 'add',
            'search'   => $search,
            'restypes' => $restypes,
            'archive'  => $archive,
        )
    );

    if($k == '')
        {
        ?><a href="<?php echo $href; ?>" onClick="return CentralSpaceLoad(this, true);"><?php
            echo escape($lang['search_notifications_notify_me']);
        ?></a>
        <?php
        }
    }