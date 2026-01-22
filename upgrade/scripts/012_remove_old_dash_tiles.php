<?php

// Remove tiles that are no longer supported by the system.
ps_query("delete from dash_tile where title in ('themes','mycollections','helpandadvice')");
