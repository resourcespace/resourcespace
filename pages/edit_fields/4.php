<?php /* -------- Date ---------------------------- */

global $reset_date_upload_template, $reset_date_field, $blank_date_upload_template,$date_d_m_y,$date_validation_js;

# Start with a null date
$dy = "";
$dm = $dd = $dh = $di = -1;

$y = getval("$name-y", "");
$m = getval("$name-m", "");
$d = getval("$name-d", "");
$h = getval("$name-h", "");
$i = getval("$name-i", "");

if (
    (
        !$blank_date_upload_template
        || $ref > 0
        || '' != getval('submitted', '')
    )
    && !empty(array_filter([$value, $y, $m, $d, $h, $i], 'strlen'))
) {
    if ($value != "") {
        #fetch the date parts from the value
        $sd = explode(" ", $value);
        if (count($sd) >= 2) {
            # Attempt to extract hours and minutes from second part.
            $st = explode(":", $sd[1]);
            if (count($st) >= 2) {
                $dh = intval($st[0]);
                $di = intval($st[1]);
            }
        }

        # Extract date parts taking account of BCE dates which have a leading -
        $value = $sd[0];
        $sd = explode("-", $value);

        if (substr($value, 0, 1) === "-") {
            if (count($sd) >= 2) {
                $dy = "-" . $sd[1];
            }
            if (count($sd) >= 3) {
                $dm = intval($sd[2]);
            }
            if (count($sd) >= 4) {
                $dd = intval($sd[3]);
            }
        } else {
            if (count($sd) >= 1) {
                $dy = $sd[0];
            }
            if (count($sd) >= 2) {
                $dm = intval($sd[1]);
            }
            if (count($sd) >= 3) {
                $dd = intval($sd[2]);
            }
        }
    } else {
        # values from incomplete uploads may be present even if no value is set
        $dy = $y;
        $dm = $m;
        $dd = $d;
        $dh = $h;
        $di = $i;
    }
}

if ($date_d_m_y) {
    ?>
    <select id="<?php echo $name; ?>-d" name="<?php echo $name?>-d">
        <option value=""><?php echo escape($lang["day"]); ?></option>
        <?php for ($d = 1; $d <= 31; $d++) { ?>
            <option value="<?php echo sprintf("%02d", $d)?>" <?php echo ($d == $dd) ? " selected" : ''; ?>>
                <?php echo sprintf("%02d", $d)?>
            </option>
        <?php } ?>
    </select>
        
    <select id="<?php echo $name; ?>-m" name="<?php echo $name?>-m">
        <option value=""><?php echo escape($lang["month"]); ?></option>
        <?php for ($m = 1; $m <= 12; $m++) { ?>
            <option <?php echo ($m == $dm) ? " selected" : ''; ?> value="<?php echo sprintf("%02d", $m)?>">
                <?php echo escape($lang["months_list"][$m - 1]); ?>
            </option>
        <?php } ?>
    </select>
    <?php
} else {
    ?>
    <select id="<?php echo $name; ?>-m" name="<?php echo $name?>-m">
        <option value=""><?php echo escape($lang["month"]); ?></option>
        <?php for ($m = 1; $m <= 12; $m++) { ?>
            <option <?php echo ($m == $dm) ? " selected" : ''; ?> value="<?php echo sprintf("%02d", $m)?>">
                <?php echo escape($lang["months_list"][$m - 1]); ?>
            </option>
        <?php } ?>
    </select>

    <select id="<?php echo $name; ?>-d" name="<?php echo $name?>-d">
        <option value=""><?php echo escape($lang["day"]); ?></option>
        <?php for ($d = 1; $d <= 31; $d++) { ?>
            <option value="<?php echo sprintf("%02d", $d)?>"<?php echo ($d == $dd) ? " selected" : ''; ?>>
                <?php echo sprintf("%02d", $d)?>
            </option>
        <?php } ?>
    </select>
    <?php
}
?>

<script>
    jQuery(document).ready(function() {
        jQuery('[id^=<?php echo escape($name); ?>-]').on('change', function(event){
            partsok = sufficientDateParts('<?php echo $name; ?>');
            if (partsok) {
                datevalid = validate_date_field(event.target.id.split('-')[0]);
                <?php
                if ($edit_autosave) { ?>
                    if (datevalid) {
                        AutoSave('<?php echo $field["ref"]; ?>');
                    } else {
                        console.debug("Invalid date inputs selected. Preventing autosave");
                    }
                    <?php
                } ?>
            } else {
                console.debug("Insufficient date inputs selected. Preventing autosave");
            }
        })
    })

    <?php
    if ($date_validation_js) {
        ?>
        function validate_date_field(field_ref) {
            let day   = jQuery('#'+field_ref+'-d').val();
            let month = jQuery('#'+field_ref+'-m').val();
            let year  = jQuery('#'+field_ref+'-y').val(); 
            <?php
            // If using Date and optional time, validate hour and minute
            if ($field["type"] != 10) {
                ?>
                let hour = jQuery('#'+field_ref+'-h').val();
                let minute = jQuery('#'+field_ref+'-i').val();
                hour_is_valid = false;
                minute_is_valid = false;

                if ((hour >= 0 && hour <= 23) || (!hour)) {
                    hour_is_valid=true;
                }
                if ((minute >= 0 && minute <= 59) || (!minute)) {
                    minute_is_valid=true;
                }
                if (!hour_is_valid || !minute_is_valid) {
                    styledalert(<?php echo "'" . escape($lang["error"]) . "','" . escape($lang["invalid_date_generic"]) . "'" ?>);
                    return false;
                }

            <?php
            } ?>
            // Day and month viability check
            let day_is_valid=false;
            let month_is_valid=false;
            if ((day >= 1 && day <= 31) || !day) {
                day_is_valid=true;
            }
            if ((month >= 1 && month <= 12) || !month) {
                month_is_valid=true;
            }
            if (!day_is_valid || !month_is_valid) {
                styledalert(<?php echo "'" . escape($lang["error"]) . "','" . escape($lang["invalid_date_generic"]) . "'" ?>);
                return false;
            }

            // The minimum viable non-blank date must have a valid year which can be CE or BCE
            let year_formatted="";
                if (year != "" && year!=undefined) {
                    let year_is_valid=false;
                    if (jQuery.isNumeric(year)) {
                        if (year >=-9999 && year <=9999) {
                            year_is_valid=true;
                            // Refresh year to ensure it is in the correct format yyyy or -yyyy
                            if (year>=0) {
                                year_formatted = year.toString().padStart(4,'0');
                            } else {
                                year_formatted = "-"+(0-year).toString().padStart(4,'0');
                            }
                            jQuery('#'+field_ref+'-y').val(year_formatted);
                        }
                    }
                    if (!year_is_valid) {
                        styledalert(<?php echo "'" . escape($lang["error"]) . "','" . escape($lang["invalid_date_generic"]) . "'" ?>);
                    }
                } else {
                    jQuery('#'+field_ref+'-y').val("");
                }
                year =jQuery('#'+field_ref+'-y').val();

            // Partial date viability check  
            let year_numeric=jQuery.isNumeric(year);
            let month_numeric=jQuery.isNumeric(month);
            let day_numeric=jQuery.isNumeric(day);
            let date_is_valid=true;

            if (year_numeric) {
                if (month_numeric && day_numeric) {
                    date_is_valid=true; // All present
                } else  {
                    if (day_numeric) {  
                        date_is_valid=false; // Year and day without month
                    }
                }
            } else {
                // Year absent
                if (day_numeric && !month_numeric) {  
                    date_is_valid=true; // Allow day only
                } else {
                    if (!day_numeric && month_numeric) {  
                        date_is_valid=true; // Allow month only
                    }
                }
            }
            
            if (!date_is_valid) {
                styledalert(<?php echo "'" . escape($lang["error"]) . "','" . escape($lang["invalid_date_generic"]) . "'" ?>);
            }

            // Fully entered date viability check  
            if (year_numeric && month_numeric && day_numeric) {
                // For CE dates only, check the viability of the entered date we must convert the date object back to ISO format
                // BCE dates are accepted as-is because this technique does not work for them due to known limitations of the Date class
                if (year>=0) {
                    // Construct an ISO date string formatted as yyyy-mm-dd (or -yyyy-mm-dd for BCE date)
                    let date_entered_iso = year + '-' + month + '-' + day;
                    // When the whole date is entered then date object creation allows the presence of additional days 
                    let date_entered_obj = new Date(date_entered_iso);
                    let date_viable_iso = "";
                    date_viable_iso = date_entered_obj.toISOString().split('T')[0];
                    // So an entered ISO date of 2021-02-30 will convert back into 2021-03-02
                    // If the entered ISO date matches its converted back counterpart then it's a viable date  
                    if (date_entered_iso !== date_viable_iso) {
                        styledalert(<?php echo "'" . escape($lang["error"]) . "','" . escape($lang["invalid_date_generic"]) . "'" ?>);
                        date_is_valid = false;
                    }
                }
            }
            return date_is_valid;
        }
        <?php
        $date_validation_js = false;
    }
    ?>
</script>

<label class="accessibility-hidden" for="<?php echo $name; ?>-y"><?php echo escape($lang["year"]); ?></label>
<input id="<?php echo $name; ?>-y" type=text size=5 name="<?php echo $name?>-y" value="<?php echo escape($dy); ?>">

<?php if ($field["type"] != 10) { ?>
<!-- Time (optional) -->
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <select id="<?php echo $name; ?>-h" name="<?php echo $name?>-h">
        <option value=""><?php echo escape($lang["hour-abbreviated"]); ?></option>
        <?php for ($m = 0; $m <= 23; $m++) { ?>
            <option <?php echo ($m == $dh) ? " selected" : '';?>>
                <?php echo sprintf("%02d", $m)?>
            </option>
        <?php } ?>
    </select>

    <select id="<?php echo $name; ?>-i" name="<?php echo $name?>-i">
        <option value=""><?php echo escape($lang["minute-abbreviated"]); ?></option>
        <?php for ($m = 0; $m <= 59; $m++) { ?>
            <option <?php echo ($m == $di) ? " selected" : ''; ?>>
                <?php echo sprintf("%02d", $m)?>
            </option>
        <?php } ?>
    </select>
<?php }

