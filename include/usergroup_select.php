<div class="Question">
    <label for="groupselect"><?php echo escape($lang["group"]); ?></label>
    <select
        id="groupselect"
        name="groupselect"
        class="stdwidth"
        onchange="
            if (this.value=='viewall') {
                document.getElementById('groupselector').style.display='none';
            } else {
                document.getElementById('groupselector').style.display='block';
            }">

        <?php if (!checkperm("U")) { ?>
            <option
                <?php echo ($groupselect == "viewall") ? "selected " : '';?>
                value="viewall">
                <?php echo escape($lang["allgroups"]); ?>
            </option>
        <?php } ?>

        <option
            <?php echo ($groupselect == "select") ? "selected" : ''; ?>
            value="select">
            <?php echo escape($lang["select"]); ?>
        </option>
    </select>
    <div class="clearerleft"></div>
    <table
        id="groupselector"
        cellpadding=3
        cellspacing=3
        style="padding-left:150px;<?php echo ($groupselect == "viewall") ? "display:none;" : ''; ?>">
        <?php
        $grouplist = get_usergroups(true);
        for ($n = 0; $n < count($grouplist); $n++) { ?>
            <tr>
                <td valign=middle nowrap><?php echo escape($grouplist[$n]["name"])?>&nbsp;&nbsp;</td>
                <td width=10 valign=middle>
                    <input
                        type=checkbox
                        name="groups[]"
                        value="<?php echo $grouplist[$n]["ref"]; ?>"
                        <?php echo (in_array($grouplist[$n]["ref"], $groups)) ? "checked" : ''; ?>
                    >
                </td>
            </tr>
            <?php
        }
        ?>
    </table>
    <div class="clearerleft"></div>
</div>
