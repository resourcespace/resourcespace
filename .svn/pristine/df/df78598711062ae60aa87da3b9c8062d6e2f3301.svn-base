<?php

// Rename existing 'My collection' collections
ps_query("UPDATE collection SET `name` = 'Default Collection' WHERE TRIM(`name`) = 'My Collection' AND `cant_delete`=1");
echo 'Collections migrated' . PHP_EOL;

// Rename existing 'My collection' dash tiles
ps_query("UPDATE dash_tile SET `title` = 'Default Collection' WHERE TRIM(`title`) = 'My Collection'");
echo 'Dash Tiles migrated' . PHP_EOL;

echo 'Migration to Default Collection complete' . PHP_EOL;
