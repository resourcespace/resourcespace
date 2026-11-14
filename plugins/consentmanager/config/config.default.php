<?php

$consent_usage_mediums = array("Print", "Websites", "Merchandise", "TV", "Social Media");
# You may add your own options or define a new array of usage mediums in config.php.
# The options may be translated in the language files.

$consent_expiry_notification = false;
$consent_expiry_notification_days = 14;

$consent_attach_upload = true;

$consent_expired_workflow_state = "";

// External requests extend the existing consent management workflow.
$consentmanager_external_enabled = true;
$consentmanager_external_validity_days = 14;
$consentmanager_external_default_template = 0;
$consentmanager_external_drawn = true;
$consentmanager_external_typed = true;
$consentmanager_external_originals = false;
$consentmanager_external_reminders = false;
$consentmanager_external_reminder_days = 3;
$consentmanager_external_reminder_limit = 2;
$consentmanager_external_audit_days = 365;
$consentmanager_external_temporary_days = 30;
$consentmanager_external_max_resources = 100;
$consentmanager_external_recipient_copy = true;
$consentmanager_external_include_requester = true;
// Optional verification is not implemented; enabling this fails closed.
$consentmanager_external_verification = false;
