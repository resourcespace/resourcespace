<?php
# Feeder page for AJAX search auto-completion.

include "../../include/boot.php";
include "../../include/authenticate.php";

$field = getval("field", ""); # get field name if doing a simple search completion (to get it easily from $_GET)
$ref = getval("fieldref", "", true); #get field ref if doing simple search completion (for get_suggested_keywords())
$search = getval("term", "");

# Find last keyword user is searching for
$s = explode(" ", $search);
$last = $s[count($s) - 1];

# Merge the words back together so existing words can be added to the results.
array_pop($s);
$otherwords = join(" ", $s);

?>[<?php

if (strlen($last) >= 2) { # Activate when last entered keyword >=3 chars long
    ?>
    <?php
    $keywords = get_suggested_keywords($last, $ref);
    for ($n = 0; $n < count($keywords); $n++) {
        if ($n > 0) {
            echo ", ";
        }
        ?>
        "<?php echo (($otherwords != "") ? escape($otherwords) . " " : "") . escape($keywords[$n]) ?>"
        <?php
    }
    ?>
    <?php
}
?>
]
