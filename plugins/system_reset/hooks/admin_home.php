<?php

function HookSystem_resetAdmin_homeCustomadminfunction()
    {
    global $baseurl,$lang;
    ?><li title="<?php echo escape($lang["system_reset-tooltip"]); ?>"><a href="<?php echo $baseurl ?>/plugins/system_reset/pages/reset.php"><i aria-hidden="true" class="icon-eraser"></i><br /><?php echo escape($lang["system_reset"]); ?></a></li><?php
    }