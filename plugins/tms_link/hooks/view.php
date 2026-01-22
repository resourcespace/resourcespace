<?php
function HookTms_linkViewRenderfield($field)
{
    if (!checkperm("a")) {
        return false;
    }

    global $baseurl,$search, $ref;

    if (tms_link_is_rs_uid_field($field["ref"]) && $field["value"] != "") {
        $tmsid = $field["value"];
        $value = escape($tmsid);
        $title = escape($field["title"]);
        $a_href = generateURL(
            "{$baseurl}/plugins/tms_link/pages/tms_details.php",
            array(
                'ref' => $ref,
                'tmsid' => $tmsid,
            )
        );
        ?>
        <div class="itemNarrow">
            <h3><?php echo escape($title); ?></h3>
            <p>
                <a href="<?php echo $a_href; ?>" onclick="return ModalLoad(this,true)"><?php echo $value; ?></a>
            </p>
        </div>
        <?php
        return true;
        }
    return false;
}