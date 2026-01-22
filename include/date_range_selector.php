<!-- Period select -->
<div class="Question" id="date_period">
    <label for="period"><?php echo escape($lang["period"]); ?></label>
    <select id="period" name="period" class="stdwidth" onChange="
        if (this.value==-1) {document.getElementById('DateRange').style.display='block';} else {document.getElementById('DateRange').style.display='none';}
        if (this.value==0) {document.getElementById('SpecificDays').style.display='block';} else {document.getElementById('SpecificDays').style.display='none';}
        if (this.value!=-1) {document.getElementById('EmailMe').style.display='block';} else {document.getElementById('EmailMe').style.display='none';}
        // Copy reporting period to e-mail period
        if (document.getElementById('period').value==0) {
            // Copy from specific day box
            document.getElementById('email_days').value=document.getElementById('period_days').value;
        } else {
            document.getElementById('email_days').value=document.getElementById('period').value;        
        }
        ">
        <?php
        foreach ($reporting_periods_default as $period_default) {
            if (is_int($period_default)) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    $period_default,
                    $period_init == $period_default ? ' selected' : '',
                    escape(str_replace('?', $period_default, $lang['lastndays']))
                );
            }
        }
        ?>
        <option value="0" <?php echo ($period_init == 0) ? "selected" : ''; ?>>
            <?php echo escape($lang["specificdays"]); ?>
        </option>
        <option value="-1" <?php echo ($period_init == -1) ? "selected" : ''; ?>>
            <?php echo escape($lang["specificdaterange"]); ?>
        </option>
    </select>
    <div class="clearerleft"></div>
</div>

<!-- Specific Days Selector -->
<div id="SpecificDays" <?php echo ($period_init != 0) ? 'style="display:none;"' : ''; ?>>
    <div class="Question">
        <label for="period_days"><?php echo escape($lang["specificdays"]); ?></label>
        <?php
        $textbox = "<input type=\"text\" id=\"period_days\" name=\"period_days\" size=\"4\" value=\"" . escape(getval("period_days", "")) . "\">";
        echo str_replace("?", $textbox, escape($lang["lastndays"]));
        ?>
        <div class="clearerleft"></div>
    </div>
</div>

<!-- Specific Date Range Selector -->
<div id="DateRange" <?php echo ($period_init != -1) ? 'style="display:none;"' : ''; ?>>
    <div class="Question">
        <label><?php echo escape($lang["fromdate"]); ?><br/><?php echo escape($lang["inclusive"]); ?></label>

        <?php
        $name = "from";
        $dy = getval($name . "-y", 2000);
        $dm = getval($name . "-m", 1);
        $dd = getval($name . "-d", 1);
        ?>

        <select name="<?php echo $name?>-d">
            <?php for ($m = 1; $m <= 31; $m++) { ?>
                <option <?php echo ($m == $dd) ? "selected" : ''; ?>>
                    <?php echo sprintf("%02d", $m)?>
                </option>
            <?php } ?>
        </select>

        <select name="<?php echo $name?>-m">
            <?php for ($m = 1; $m <= 12; $m++) { ?>
                <option <?php echo ($m == $dm) ? "selected" : ''; ?> value="<?php echo sprintf("%02d", $m)?>">
                    <?php echo escape($lang["months_list"][$m - 1]); ?>
                </option>
            <?php } ?>
        </select>

        <input type=text size=5 name="<?php echo $name?>-y" value="<?php echo escape($dy); ?>">
        <div class="clearerleft"></div>
    </div>

    <div class="Question">
        <label><?php echo escape($lang["todate"]); ?><br/><?php echo escape($lang["inclusive"]); ?></label>

        <?php
        $name = "to";
        $dy = getval($name . "-y", date("Y"));
        $dm = getval($name . "-m", date("m"));
        $dd = getval($name . "-d", date("d"));
        ?>

        <select name="<?php echo $name?>-d">
            <?php for ($m = 1; $m <= 31; $m++) { ?>
                <option <?php echo ($m == $dd) ? "selected" : ''; ?>>
                    <?php echo sprintf("%02d", $m)?>
                </option>
            <?php } ?>
        </select>

        <select name="<?php echo $name?>-m">
            <?php for ($m = 1; $m <= 12; $m++) { ?>
                <option <?php echo ($m == $dm) ? "selected" : ''; ?> value="<?php echo sprintf("%02d", $m)?>">
                    <?php echo escape($lang["months_list"][$m - 1]); ?>
                </option>
            <?php } ?>
        </select>

        <input type=text size=5 name="<?php echo $name?>-y" value="<?php echo escape($dy); ?>">
        <div class="clearerleft"> </div>
    </div>
</div>
<!-- end of Date Range Selector -->