<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Indexing configuration preferences');
check_db_structs();
