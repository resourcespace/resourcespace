<?php
include_once __DIR__ . '/../include/totp_functions.php';

/**
 * Redirects the user to the TOTP setup page if their TOTP cookie is invalid or missing.
 *
 * This hook runs before the page header output and ensures that users with TOTP enabled
 * are redirected to complete setup or authentication if needed.
 *
 * @return void
 */
function HookTotpAllPreheaderoutput()
{
    global $userref,$pagename, $anonymous_login, $username;
    $cookie = getval("totp", "");

    if (!in_array($pagename, array('totp', 'user_change_password')) && is_numeric($userref) && $userref > 0 && (!(isset($anonymous_login) && $username == $anonymous_login)) && $cookie != TOTP_cookie($userref) && !TOTP_saml_authenticate()) {
        redirect("plugins/totp/pages/totp.php");
    }
}

/**
 * Includes the QR code JavaScript library needed for TOTP setup.
 *
 * This hook appends the necessary JS to the page header.
 *
 * @return void
 */
function HookTotpAllAdditionalheaderjs()
{
    global $baseurl_short;
    ?><script src="<?php echo $baseurl_short; ?>plugins/totp/js/qrcode.min.js"></script><?php
}

/**
 * Adds a TOTP reset checkbox to the user edit form.
 *
 * This allows administrators to reset a user's TOTP configuration, e.g. if they have lost their device.
 *
 * @return void
 */
function HookTotpTeam_user_editAdditionaluserfields()
{
    global $lang;
    ?>
    <div class="Question">
        <label><?php echo escape($lang["totp_reset"]); ?></label>
        <input name="totp_reset" type="checkbox" value="yes">
        <div class="clearerleft"></div>
    </div>
    <?php
}

/**
 * Processes the TOTP reset checkbox and prepares a query to reset the user's TOTP data.
 *
 * If the reset checkbox was ticked, returns a PreparedStatementQuery that modifies the user save action to clear the user's TOTP fields.
 *
 * @return PreparedStatementQuery|null A query to reset TOTP fields, or null if not resetting.
 */
function HookTotpTeam_user_editAdditionaluserfieldssave()
{
    if (getval("totp_reset", "") != "") {
        return new PreparedStatementQuery(',totp=0,totp_tries=0');
    }
}

function HookTotpAllBeforetermsredirect() 
{
    return ['totp'];
}

/**
 * Set a TOTP cookie for an admin user when logging in as another user 
 * so that they are not prompted to enter the impersonated users authentication code. 
 */
function HookTotpAllImpersonateuser($userref)
{
    rs_setcookie("totp", TOTP_cookie($userref), 1, "/", "", false, true);
}