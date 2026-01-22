<?php

/**
 * Edit resource request page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("R")) {
    exit("Permission denied.");
}

include "../../include/request_functions.php";

$ref = getval("ref", "", true);
$modal = (getval("modal", "") == "true");
$backurl = getval("backurl", "");
$url = $baseurl_short . "pages/team/team_request_edit.php?ref=" . $ref . "&backurl=" . urlencode($backurl);

if (getval("submitted", "") != "" && enforcePostRequest(false)) {
    # Save research request data
    if (!hook("saverequest", "", array($ref))) {
        save_request($ref);
    }
    if (!$modal) {
            redirect($baseurl_short . "pages/team/team_request.php?reload=true&nc=" . time());
            exit();
    } else {
        $resulttext = $lang["changessaved"];
    }
}

# Fetch research request data
$request = get_request($ref);
if ($request === false && !isset($resulttext)) {
    $resulttext = "Request " . escape($ref) . " not found.";
}

include "../../include/header.php";
?>

<div class="BasicsBox">    
    <div class="RecordHeader">
        <div class="backtoresults"> 
            <?php if ($modal) { ?>
                <a class="maxLink icon-maximize-2" href="<?php echo $url ?>" onClick="return CentralSpaceLoad(this);" title="<?php echo escape($lang["maximise"]); ?>"></a>
                &nbsp;
                <a href="#" class="closeLink icon-x" onClick="ModalClose();" title="<?php echo escape($lang["close"]); ?>"></a>
            <?php } ?>
        </div>

        <?php if (!$modal) { ?>
            <p>
                <a href="<?php echo $backurl != "" ? generateURL(escape($backurl)) : $baseurl_short . "pages/team/team_request.php";?>" onClick="return CentralSpaceLoad(this,true);">
                    <?php echo LINK_CARET_BACK . $backurl != "" ? escape($lang["back"]) : escape($lang["managerequestsorders"]); ?>
                </a>
            </p>
        <?php } ?>

        <h1>
            <?php
            echo escape($lang["editrequestorder"]);
            render_help_link('resourceadmin/user-resource-requests');
            ?>
        </h1>
    </div>

    <?php
    if (isset($resulttext)) {
        echo "<div class=\"PageInformal \">" . $resulttext . "</div>";
    }

    if ($request !== false) {
        $show_this_request = resource_request_visible($request);
        if (!$show_this_request) {
            ?>
            <p>
                <?php
                echo strip_tags_and_attributes(
                    str_replace(
                        "%",
                        "<b>" . ($request["assigned_to_username"] == "" ? "(unassigned)" : $request["assigned_to_username"]) . "</b>",
                        $lang["requestnotassignedtoyou"]
                    )
                );
                ?>
            </p>
            <?php
        } else {
            ?>
            <form method="post" action="<?php echo $baseurl_short?>pages/team/team_request_edit.php" onSubmit="return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(this,true);">
                <?php
                generateFormToken("team_request_edit");

                if ($modal) {
                    ?>
                    <input type=hidden name="modal" value="true">
                    <?php
                }
                ?>

                <input type="hidden" name="ref" value="<?php echo escape($ref) ?>" />
                <input type="hidden" name="submitted" value="yes" />

                <div class="Question">
                    <label><?php echo escape($lang["requestedby"]); ?></label>
                    <div class="Fixed"><?php echo $request["fullname"]; ?> (<?php echo $request["username"]; ?> / <?php echo $request["email"]; ?>)</div>
                    <div class="clearerleft"></div>
                </div>

                <div class="Question">
                    <label><?php echo escape($lang["date"]); ?></label>
                    <div class="Fixed"><?php echo nicedate($request["created"], true, true, true)?></div>
                    <div class="clearerleft"></div>
                </div>

                <div class="Question">
                    <label><?php echo escape($lang["comments"]); ?></label>
                    <div class="Fixed"><?php echo strip_tags(nl2br($request["comments"]), '<br>')?></div>
                    <div class="clearerleft"></div>
                </div>

                <div class="Question">
                    <label><?php echo escape($lang["requesteditems"]); ?></label>
                    <div class="Fixed">
                        <a href="#" onclick="ChangeCollection(<?php echo $request["collection"]; ?>,'');">
                            <?php echo LINK_CARET . escape($lang["action-selectrequesteditems"]); ?>
                        </a>
                    </div>
                    <div class="clearerleft"></div>
                </div>

                <?php
                # Show any warnings
                if (isset($warn_field_request_approval)) {
                    $warnings = ps_query(
                        "SELECT rn.resource,n.name
                                        FROM collection_resource cr
                                    RIGHT JOIN resource_node rn ON cr.resource=rn.resource
                                    RIGHT JOIN node n ON n.ref=rn.node AND n.resource_type_field = ?
                                        WHERE cr.collection = ?
                                    ORDER BY rn.resource",
                        ["i",$warn_field_request_approval,"i",$request["collection"]]
                    );

                    foreach ($warnings as $warning) { ?>
                        <div class="Question">
                            <div class="FormError">
                                <?php
                                echo strip_tags_and_attributes(
                                    str_replace(
                                        "%",
                                        sprintf(
                                            '<a onclick="return CentralSpaceLoad(this,true);" href="%s">%s</a>',
                                            generateURL("{$baseurl_short}pages/view.php", ['ref' => $warning['resource']]),
                                            $warning['resource']
                                        ),
                                        $lang["warningrequestapprovalfield"]
                                    ),
                                    ['a'],
                                    ['href', 'onclick']
                                );
                                ?><br/>
                                <?php echo $warning["name"]; ?>
                            </div>
                            <div class="clearerleft"></div>
                        </div>
                        <?php
                    }
                }

                if (checkperm("Ra")) {
                    ?>
                    <div class="Question">
                        <label><?php echo escape($lang["assignedtoteammember"]); ?></label>
                        <select class="shrtwidth" name="assigned_to">
                            <option value="0"><?php echo escape($lang["requeststatus0"]); ?></option>
                            <?php
                            $users = get_users_with_permission("Rb");
                            for ($n = 0; $n < count($users); $n++) {
                                $assigned_selected = ($request["assigned_to"] == $users[$n]["ref"]) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $users[$n]["ref"]; ?>" <?php echo $assigned_selected ?>><?php echo $users[$n]["username"]; ?></option> 
                                <?php
                            } ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>
                    <?php
                } ?>

                <div class="Question">
                    <label><?php echo escape($lang["status"]); ?></label>
                    <div class="tickset">
                        <?php
                        for ($n = 0; $n <= 2; $n++) {
                            $status_checked = ($request["status"] == $n) ? 'checked' : '';
                            ?>
                            <div class="Inline">
                                <label>
                                    <input type="radio" name="status" value="<?php echo $n ?>" <?php echo $status_checked; ?>
                                        onClick="<?php
                                        if ($n == 1) {
                                            ?>jQuery('#Expires').fadeIn();jQuery('#ReasonApprove').fadeIn();<?php
                                        } else {
                                            ?>jQuery('#Expires').slideUp();jQuery('#ReasonApprove').slideUp();<?php
                                        }
                                        if ($n == 2) {
                                            ?>jQuery('#ReasonDecline').fadeIn();<?php
                                        } else {
                                            ?>jQuery('#ReasonDecline').slideUp();<?php
                                        } ?>"/> 
                                    <?php echo escape($lang["resourcerequeststatus" . $n]); ?>
                                </label>
                            </div>
                            <?php
                        } ?>
                    </div>
                    <div class="clearerleft"></div>
                </div>

                <div class="Question" id="Expires" <?php echo ($request["status"] != 1) ? 'style="display:none;"' : ''; ?>>
                    <label><?php echo escape($lang["expires"]); ?></label>
                    <select name="expires" class="stdwidth">
                        <?php
                        if (!$removenever) { ?>
                            <option value=""><?php echo escape($lang["never"]); ?></option>
                            <?php
                        }
                        $sel = false;

                        for ($n = 1; $n <= 150; $n++) {
                            $date    = time() + (60 * 60 * 24 * $n);
                            $dateval = date("Y-m-d", $date);
                            $d       = date("D", $date);

                            $expires_selected = '';
                            if ($dateval == $request["expires"] || ($request["expires"] == '' && $removenever && $n == 7)) {
                                $sel = true;
                                $expires_selected = 'selected';
                            }

                            $option_class = '';
                            if (($d == "Sun") || ($d == "Sat")) {
                                $option_class = 'optionWeekend';
                            } ?>
                            <option class="<?php echo $option_class ?>" value="<?php echo $dateval ?>" <?php echo $expires_selected ?>><?php echo nicedate($dateval, false, true)?></option>
                            <?php
                        }

                        if ($request["expires"] != "" && !$sel) {
                            # Option is out of range, but show it anyway.
                            ?>
                            <option value="<?php echo $request["expires"]; ?>" selected><?php echo nicedate(date("Y-m-d", strtotime($request["expires"])), false, true)?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <div class="clearerleft"></div>
                </div>

                <div class="Question" id="ReasonDecline" <?php echo ($request["status"] != 2) ? 'style="display:none;"' : ''; ?>>
                    <label><?php echo escape($lang["declinereason"]); ?></label>
                    <textarea name="reason" class="stdwidth" rows="5" cols="50"><?php echo escape((string) $request["reason"])?></textarea>
                    <div class="clearerleft"></div>
                </div>

                <div class="Question" id="ReasonApprove" <?php echo ($request["status"] != 1) ? 'style="display:none;"' : ''; ?>>
                    <label><?php echo escape($lang["approvalreason"]); ?></label>
                    <textarea name="reasonapproved" class="stdwidth" rows="5" cols="50"><?php echo escape((string) $request["reasonapproved"])?></textarea>
                    <div class="clearerleft"></div>
                </div>

                <div class="Question">
                    <label><?php echo escape($lang["deletethisrequest"]); ?></label>
                    <input name="delete" type="checkbox" value="yes">
                    <div class="clearerleft"></div>
                </div>

                <div class="QuestionSubmit">    
                    <input name="save" type="submit" value="<?php echo escape($lang["save"]); ?>" />
                </div>
            </form>
            <?php
        }
    } ?>
</div> <!-- .BasicsBox -->

<?php
include "../../include/footer.php";
?>
