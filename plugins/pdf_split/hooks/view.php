<?php

function HookPdf_splitViewAfterresourceactions()
    {
    global $lang,$ref,$resource;

    if (
        strtoupper((string) $resource["file_extension"]) != "PDF" # PDF files only.
        || resource_file_readonly($ref)
    ) {
        return false;
    }
    ?>
    <li><a href="../plugins/pdf_split/pages/pdf_split.php?ref=<?php echo $ref ?>"><?php echo "<i class='icon-scissors'></i>&nbsp;" .$lang["splitpdf"]; ?></a></li>
    <?php

    return false; # Allow other plugins to also use this hook.
    }

