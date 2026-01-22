<?php

// E-commerce functionality was removed for 10.4
// Move any groups with these request states (2,3) back to managed (1)
ps_query("update usergroup set request_mode=1 where request_mode in (2,3)");
clear_query_cache('usergroup');