<?php

include_once "../../../include/boot.php";

$k = getval('k', '');
if ((is_array($k) || trim($k) === '') && getval('noauth', '') != true) {
    include '../../../include/authenticate.php';
}

header("Content-type: text/css");

if (isset($user_pref_appearance)) {
    if ($user_pref_appearance == "device") {
        ?>
        @media (prefers-color-scheme: dark) {
        <?php
    }

    if ($user_pref_appearance == "dark" || $user_pref_appearance == "device") {
        ?>
        .guidelines-sidebar {
            border-right: unset;
            box-shadow: 12px 0px 10px -12px black;
        }
        .add-new-content-container {
            color: white;
        }
        .guidelines-content button.new {
            color: white; 
        }
        .guidelines-sidebar li > a:hover {
            background: black;
        }
        .context-menu-container {
            background: #404040;
        }
        div[id^='page-content-item-']:hover .top-right-menu, .group:hover > .top-right-menu, div[id^='page-content-item-']:hover > a.grid-item.video-js-resource {
            background: #404040;
        }
        <?php
    }

    if ($user_pref_appearance == "device") {
        ?>
        }
        <?php
    }
}
