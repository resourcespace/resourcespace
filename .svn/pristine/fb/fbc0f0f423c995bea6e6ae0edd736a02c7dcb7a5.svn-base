<?php

command_line_only();

# Resolve a known keyword
$key1 = resolve_keyword("Test", false);

# Resolve an unknown keyword and have it create it.
$key2 = resolve_keyword("Unknown", true);

# Everything as expected?
return is_numeric($key1) && is_numeric($key2) && $key1 != $key2;
