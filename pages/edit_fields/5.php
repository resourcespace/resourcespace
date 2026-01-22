<?php /* -------- Text Box (large multi-line) ----- */ ?>
<textarea
    class="stdwidth MultiLine"
    rows=20 
    cols=80 
    name="<?php echo $name; ?>"
    id="<?php echo $name; ?>"
    <?php
    echo $help_js;
    if ($edit_autosave) {
        ?>
        onChange="AutoSave('<?php echo $field["ref"]; ?>');"
        <?php
    }
    ?>
><?php echo escape($value); ?></textarea>
