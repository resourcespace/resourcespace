<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Start dropping the user_userlist table...');

ps_query('DROP TABLE IF EXISTS user_userlist');

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Finished dropping the user_userlist table!');
