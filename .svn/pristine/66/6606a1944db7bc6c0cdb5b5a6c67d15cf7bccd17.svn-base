<?php

declare(strict_types=1);

use function Montala\ResourceSpace\Plugins\BrandGuidelines\colour_cmyk_input_validator;
use function Montala\ResourceSpace\Plugins\BrandGuidelines\colour_hex_input_validator;
use function Montala\ResourceSpace\Plugins\BrandGuidelines\colour_rgb_input_validator;
use function Montala\ResourceSpace\Plugins\BrandGuidelines\richtext_input_parser;

/**
 * Render decorator of custom fields used by Brand Guidelines
 * @param array $field Custom field structure - {@see process_custom_fields_submission()}
 * @return bool Returns true to indicate to ResourceSpace that the core render functionality is being overriden by the
 * plugin (for this field type), false otherwise.
 */
function HookBrand_guidelinesContentRender_custom_fields_default_case_override(array $field): bool
{
    $field_id = $field['html_properties']['id'];
    $field_name = $field['html_properties']['name'];
    $field_value = $field['value'];

    if ($field['type'] === FIELD_TYPE_NUMERIC) {
        $attrs = '';
        $constraints = $field['constraints'] ?? [];
        foreach ($constraints as $attr => $val) {
            if (in_array($attr, ['min', 'step'])) {
                $attrs .= sprintf('%s="%s"', $attr, escape((string) $val));
            }
        }
        ?>
        <input
            id="<?php echo escape($field_id); ?>"
            class="stdwidth"
            name="<?php echo escape($field_name); ?>"
            value="<?php echo escape($field_value); ?>"
            type="number"
            <?php echo $attrs; ?>
        >
        <?php
        return true;
    } elseif ($field['type'] === FIELD_TYPE_TEXT_RICH) {
        ?>
        <textarea id="<?php echo escape($field_id); ?>" name="<?php echo escape($field_name); ?>"><?php
            echo escape($field_value); ?>
        </textarea>
        <?php
        return true;
    } elseif ($field['type'] === FIELD_TYPE_COLOUR_PREVIEW) {
        ?>
        <div class="preview guidelines-colour-block"></div>
        <?php
        return true;
    }

    return false;
}

/**
 * Validate custom fields' submitted input.
 * @return false|string Return custom fields' type input validation error or false to let ResourceSpace core handling
 * to run.
 */
function HookBrand_guidelinesContentProcess_custom_fields_submission_validator(array $field)
{
    $field_value = trim($field['value']);

    if ($field['type'] === FIELD_TYPE_NUMERIC) {
        if (!is_positive_int_loose($field_value) || $field_value < $field['constraints']['min']) {
            return sprintf(
                '%s. %s: %s',
                $GLOBALS['lang']['requiredfields-general'],
                i18n_get_translated($field['title']),
                str_replace('?', '1', $GLOBALS['lang']['shouldbeormore'])
            );
        } elseif (get_resource_data($field_value) === false) {
            return $GLOBALS['lang']['brand_guidelines_err_invalid_input'];
        }
    } elseif ($field['type'] === FIELD_TYPE_TEXT_RICH) {
        if ($field_value === '') {
            return $GLOBALS['lang']['requiredfields-general'];
        } elseif (richtext_input_parser($field_value) === '') {
            return $GLOBALS['lang']['brand_guidelines_err_invalid_input'];
        }
    } elseif (
        $field['type'] === FIELD_TYPE_TEXT_BOX_SINGLE_LINE
        && $field_value !== ''
        && (
            ($field['id'] === 'hex' && !colour_hex_input_validator($field_value))
            || ($field['id'] === 'rgb' && !colour_rgb_input_validator($field_value))
            || ($field['id'] === 'cmyk' && !colour_cmyk_input_validator($field_value))
        )
    ) {
        return $GLOBALS['lang']['brand_guidelines_err_invalid_input'];
    }

    return false;
}
