<?php /* -------- Text Box (multi-line) ----------- */ 

global $merge_filename_with_title,$merge_filename_with_title_default,$view_title_field;

$show_merge_options = $merge_filename_with_title && $field['ref'] == $view_title_field && $ref < 0;

if ($show_merge_options) {
    $do_not_use = false;
    $replace = false;
    $prefix = false;
    $suffix = false;

    // Choose option to be used as default
    switch ('merge_filename_title_' . $merge_filename_with_title_default) {
        case 'merge_filename_title_do_not_use':
            $do_not_use = true;
            break;
        case 'merge_filename_title_replace':
            $replace = true;
            break;
        case 'merge_filename_title_prefix':
            $prefix = true;
            break;
        case 'merge_filename_title_suffix':
            $suffix = true;
            break;
        default:
            $do_not_use = true;
    }
    ?>

    <div id="merge_filename_title_container">
        <div id="" class=""><?php echo strip_tags_and_attributes($lang['merge_filename_title_question']); ?></div>
        <table id="" class="radioOptionTable" cellpadding="3" cellspacing="3">
            <tbody>
                <tr>
                    <td>
                        <input
                            type="radio"
                            id="merge_filename_title_do_not_use"
                            name="merge_filename_with_title_option"
                            value="<?php echo escape($lang['merge_filename_title_do_not_use']); ?>"
                            <?php echo $do_not_use ? "checked" : ''; ?>
                        />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="merge_filename_title_do_not_use">
                            <?php echo escape($lang['merge_filename_title_do_not_use']); ?>
                        </label>
                    </td>
                    <td>
                        <input
                            type="radio"
                            id="merge_filename_title_replace"
                            name="merge_filename_with_title_option"
                            value="<?php echo escape($lang['merge_filename_title_replace']); ?>"
                            <?php echo $replace ? "checked" : ''; ?>
                        />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="merge_filename_title_replace">
                            <?php echo escape($lang['merge_filename_title_replace']); ?>
                        </label>
                    </td>
                    <td>
                        <input
                            type="radio"
                            id="merge_filename_title_prefix"
                            name="merge_filename_with_title_option"
                            value="<?php echo escape($lang['merge_filename_title_prefix']); ?>"
                            <?php echo $prefix ? "checked" : ''; ?>
                        />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="merge_filename_title_prefix">
                            <?php echo escape($lang['merge_filename_title_prefix']); ?>
                        </label>
                    </td>
                    <td>
                        <input
                            type="radio"
                            id="merge_filename_title_suffix"
                            name="merge_filename_with_title_option"
                            value="<?php echo escape($lang['merge_filename_title_suffix']); ?>"
                            <?php echo $suffix ? "checked" : ''; ?>
                        />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="merge_filename_title_suffix">
                            <?php echo escape($lang['merge_filename_title_suffix']); ?>
                        </label>
                    </td>
                    <!-- Include extension? -->
                    <td>
                        <input type="checkbox" id="merge_filename_title_include_extension" name="merge_filename_with_title_include_extensions" value="yes" />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="merge_filename_title_include_extension">
                            <?php echo escape($lang['merge_filename_title_include_extensions']); ?>
                        </label>
                    </td>
                    <!-- Spacer -->
                    <td>
                        <input type="text" id="merge_filename_title_spacer" name="merge_filename_with_title_spacer" value="" maxlength="3" />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="merge_filename_title_spacer">
                            <?php echo escape($lang['merge_filename_title_spacer']); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
} ?>

<textarea
    class="stdwidth MultiLine"
    rows=6
    cols=50
    name="<?php echo $name?>"
    id="<?php echo $name?>"
    <?php
    echo $help_js;
    echo $edit_autosave ? 'onChange="AutoSave(\'' .  $field["ref"] . '\');"' : '';
    ?>
><?php echo escape((string) $value)?></textarea>

<?php
if ($show_merge_options) {
    echo "</div>";
}
