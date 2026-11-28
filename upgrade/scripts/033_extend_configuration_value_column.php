<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Extending configuration storage for option lists');
check_db_structs();
