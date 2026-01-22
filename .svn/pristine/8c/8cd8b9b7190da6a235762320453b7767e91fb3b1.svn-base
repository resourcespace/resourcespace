<?php
if (is_null($value)) {
    $value = "";
}

$valid_date = validateDatetime($value, 'Y-m-d') || trim($value) === '';
if (!$valid_date && trim($value) !== '') {
    $value = substr($value, 0, 10);
    $valid_date = validateDatetime($value, 'Y-m-d');
}

if ($GLOBALS['use_native_input_for_date_field'] && $valid_date) {
    $onchange_attr = $edit_autosave ? sprintf('onchange="AutoSave(%s);"', (int) $field['ref']) : '';
    ?>
    <input
        type="date"
        name="<?php echo escape($name); ?>"
        id="<?php echo escape($name); ?>"
        value="<?php echo escape($value); ?>"
        <?php echo $onchange_attr; ?>
    >
    <?php
} elseif (!$valid_date && $GLOBALS['use_native_input_for_date_field']) {
    include '0.php';
    ?>
    <input class="button" type="button" value="?" onclick="styledalert('','<?php echo escape(str_replace('[value]', $value, $lang['error_invalid_date_format'])); ?>')"></input>
    <?php
} else {
    // Legacy: Date uses same code as date + time
    include '4.php';
}