<?php

/*

Add a "GPT Source" column to the metadata fields display.

*/
function HookOpenai_gptAdmin_resource_type_fieldsReplacetabnamecolumnheader()
    {
    addColumnHeader('openai_gpt_input_field', 'property-gpt_source');
    return false;
    }

function HookOpenai_gptAdmin_resource_type_fieldsReplacetabnamecolumn()
{
    global $fields, $n, $lang;
    $source = $fields[$n]["openai_gpt_input_field"];
    
    if ($source == -1) {
        $source = $lang["image"];
    } elseif (is_numeric($source)) {
        $source = $lang["field"] . " " . $source;
    }
    ?>

    <td>
        <?php echo is_null($source) ? "" : escape($source); ?>
    </td>

    <?php
    return false;
}
