<?php

// Set a minimum date for fixed list field node reversion in sysvars.
// Prior to v10.1 the previous node values were not stored so batch reverting field values to a date prior to that will result in loss of data.
// This date will be checked by the rse_version plugin which will only allow users to revert edits of fixed list fields later than this.

set_sysvar("fixed_list_revert_enabled", date('Y-m-d H:i:s'));
