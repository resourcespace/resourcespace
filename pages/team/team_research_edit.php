<?php

/**
 * Research request edit page. (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("r")) {
    exit("Permission denied.");
}

include_once "../../include/research_functions.php";
include_once "../../include/request_functions.php";

$ref = getval("ref", "", true);

if (getval("submitted", "") != "" && enforcePostRequest(false)) {
    # Save research request data
    save_research_request($ref);
    redirect($baseurl_short . "pages/team/team_research.php?reload=true&nc=" . time());
}

# Fetch research request data
$research = get_research_request($ref);

if (!$research) {
    exit("The supplied research request reference is not valid.");
}

include "../../include/header.php";
?>

<div class="BasicsBox">
    <h1>
        <?php
        echo escape($lang["editresearchrequest"]);
        render_help_link('resourceadmin/user-research-requests');
        ?>
    </h1>

    <form method="post" action="<?php echo $baseurl_short?>pages/team/team_research_edit.php" onSubmit="return CentralSpacePost(this,true);">
        <?php generateFormToken("team_research_edit"); ?>
        <input type=hidden name="submitted" value="true">
        <input type=hidden name="ref" value="<?php echo escape($ref); ?>">

        <div class="Question">
            <label><?php echo escape($lang["nameofproject"])?></label>
            <div class="Fixed"><?php echo escape($research["name"]); ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["descriptionofproject"])?></label>
            <div class="Fixed"><?php echo escape($research["description"]); ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["requestedby"])?></label>
            <div class="Fixed"><?php echo escape($research["username"]); ?></div>
            <div class="clearerleft"></div>
        </div>

        <?php if (isset($anonymous_login) && $research["username"] == $anonymous_login) { ?>
            <div class="Question">
                <label><?php echo escape($lang["email"])?></label>
                <div class="Fixed">
                    <a href="mailto:<?php echo escape($research["email"])?>"><?php echo escape($research["email"])?></a>
                </div>
                <div class="clearerleft"></div>
            </div>
        <?php } ?>

        <div class="Question">
            <label><?php echo escape($lang["date"])?></label>
            <div class="Fixed"><?php echo nicedate($research["created"], false, true)?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["deadline"])?></label>
            <div class="Fixed"><?php echo nicedate($research["deadline"], false, true)?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["contacttelephone"])?></label>
            <div class="Fixed"><?php echo escape($research["contact"])?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["finaluse"])?></label>
            <div class="Fixed"><?php echo escape($research["finaluse"])?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["resourcetypes"])?></label>
            <div class="Fixed">
                <?php $first = true;
                $set = explode(", ", $research["resource_types"]);
                $types = get_resource_types();for ($n = 0; $n < count($types); $n++) {
                    if (in_array($types[$n]["ref"], $set)) {
                        if (!$first) {
                            echo ", ";
                        }
                        echo escape($types[$n]["name"]);
                        $first = false;
                    }
                } ?>
            </div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["noresourcesrequired"])?></label>
            <div class="Fixed"><?php echo escape((string) $research["noresources"])?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["shaperequired"])?></label>
            <div class="Fixed"><?php echo escape($research["shape"])?></div>
            <div class="clearerleft"></div>
        </div>

        <?php
        // Render research request custom fields
        $rr_cfields = gen_custom_fields_html_props(
            get_valid_custom_fields(
                json_decode($research["custom_fields_json"], true)
            ),
            []
        );

        array_walk($rr_cfields, function ($field, $i) {
            render_question_div("Question_{$field["html_properties"]["id"]}", function () use ($field) {
                $field_id = $field["html_properties"]["id"];
                ?>
                <label for="custom_<?php echo $field_id; ?>"><?php echo escape(i18n_get_translated($field["title"])); ?></label>
                <div class="Fixed"><?php echo escape(i18n_get_translated($field["value"])); ?></div>
                <?php
            });
        });
        ?>

        <div class="Question">
            <label><?php echo escape($lang["assignedtoteammember"])?></label>
            <select class="shrtwidth" name="assigned_to">
                <option value="0"><?php echo escape($lang["requeststatus0"])?></option>
                <?php
                $users = get_users_with_permission("r");
                for ($n = 0; $n < count($users); $n++) {
                    ?>
                    <option
                        value="<?php echo $users[$n]["ref"]; ?>"
                        <?php if ($research["assigned_to"] == $users[$n]["ref"]) { ?>
                            selected
                        <?php } ?>
                    >
                        <?php echo escape($users[$n]["username"])?>
                    </option>
                    <?php
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["status"])?></label>
            <div class="tickset">
                <?php for ($n = 0; $n <= 2; $n++) { ?>
                    <div class="Inline">
                        <input type="radio" name="status" value="<?php echo $n?>" <?php echo ($research["status"] == $n) ? " checked " : ''; ?>/>
                        <?php echo escape($lang["requeststatus" . $n]); ?>
                    </div>
                <?php } ?>
            </div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["copyexistingresources"])?></label>
            <input name="copyexisting" type="checkbox" value="yes">
            <b><?php echo escape($lang["yes"])?></b>
            <?php echo escape($lang["typecollectionid"])?>
            <br/>
            <input name="copyexistingref" type="text" class="shrtwidth">
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["ticktodeletethisresearchrequest"])?></label>
            <input name="delete" type="checkbox" value="yes">
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">        
            <input name="savexxx" type="submit" value="<?php echo escape($lang["save"])?>" />
        </div>
    </form>
</div>

<?php
include "../../include/footer.php";