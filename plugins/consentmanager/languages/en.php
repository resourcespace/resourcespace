<?php

$lang["consent_management"] = "Consent management";
$lang["new_consent"] = "New consent";
$lang["edit_consent"] = "Edit consent";
$lang["delete_consent"] = "Delete consent";
$lang["consent_id"] = "Consent ID";
$lang["consentmanager_new"] = "New consent";
$lang["manageconsents"] = "Consents";
$lang["manageconsent"] = "Consent";
$lang["consent_status"] = "Consent status";

$lang["no_expiry_date"] = "No expiry date";
$lang["no_consent_date"] = "No consent date";
$lang["action-unlink"] = "Unlink";
$lang["unlink_consent"] = "Unlink consent";
$lang["consentsearch"] = "Search by name";
$lang["linkedresources"] = "Linked resources";

$lang["linkconsent"] = "Link consent";
$lang["unlinkconsent"] = "Unlink consent";
$lang["selectconsent"] = "Please select a consent ID";

$lang["name"] = "Name";
$lang["date_of_birth"] = "Date of birth";
$lang["address"] = "Address";
$lang["parent_guardian"] = "Parent/Guardian Name";
$lang["date_of_consent"] = "Date of consent";
$lang["usage"] = "Usage";
$lang["telephone"] = "Telephone";
$lang["notes"] = "Notes";
$lang["confirmdeleteconsentfile"] = "Are you sure you wish to DELETE this file? This cannot be undone.";

$lang['consent_manager_mediums'] = 'Consent usage mediums';

$lang["consent_manager_access"] = "Manage consent data (where not already enabled via \"Can access the System Setup area\" permission)";

// Admin menu tool tip
$lang["manageconsent-tooltip"] = "Create and manage consent records for resources";

$lang['consent_expiry_notification'] = "Consent expiring notifications";
$lang['consent_expiry_notification_days'] = "Number of days before consent expiry is approaching when users with the 'Consent management' permission will be notified";
$lang['consent_attach_upload'] = "Allow a consent record to be attached on Refine Metadata page after upload";
$lang['consent_expired_workflow_state'] = "Automatically archive resources linked to expired consents to this workflow state";

$lang['user_pref_consent_notifications'] = "Send me expiring consent notifications?";

$lang['consent_status_all'] = "All consents";
$lang['consent_status_active'] = "Active consents";
$lang['consent_status_expiring'] = "Expiring consents";
$lang['consent_status_expired'] = "Expired consents";
$lang["consent_view_linked_resources_short"] = "View";
$lang['consent_no_archiving'] = "No Change";

$lang['consent_notification_expiring_soon'] = "Consents Expiring Soon";
$lang['consent_notification_message'] = "There are some consents expiring soon.";
$lang['consent_notification_link'] = "View them here";
$lang['consent_notification_user_pref'] = "Change whether you receive these notifications";
$lang['consent_notification_global_pref'] = "Disable these notifications for everyone";

$lang["page-title_consentmanager_batch"]    = "Collection Consents";
$lang["page-title_consentmanager_delete"]   = "Delete Consent";
$lang["page-title_consentmanager_download"] = "Download Consent";
$lang["page-title_consentmanager_edit"]     = "Edit Consent";
$lang["page-title_consentmanager_list"]     = "Manage Consents";
$lang["page-title_consentmanager_setup"]    = "Setup Consent Plugin";
$lang["page-title_consentmanager_unlink"]   = "Unlink Consent";

// External consent collection.
$lang['cm_external_request'] = 'Request consent';
$lang['cm_external_denied'] = 'You do not have permission to perform this consent action.';
$lang['cm_external_invalid'] = 'Check the information and document revision, then try again. Review the current PDF before signing or submitting.';
$lang['cm_external_unavailable'] = 'This consent link is unavailable, expired or revoked.';
$lang['cm_external_failed'] = 'The consent action could not be completed. No successful submission has been recorded. Please try again or contact the requester.';
$lang['cm_external_csrf'] = 'The form security token is invalid. Reload the page and try again.';
$lang['cm_external_rate'] = 'Too many consent actions. Please wait one minute and try again.';
$lang['cm_external_pdf_failed'] = 'The consent document could not be generated or verified. Please contact the requester.';
$lang['cm_external_transaction'] = 'Consent processing requires database transactions. Please contact your administrator.';
$lang['cm_external_storage_failed'] = 'The signed document could not be stored and linked. Your signed draft remains available for retry.';
$lang['cm_external_mail_failed'] = 'Email delivery was not accepted. Use Send / replace link to try again.';
$lang['cm_external_invitation'] = 'Please review the assets and consent document, sign it if you agree, then explicitly submit the signed consent. Contact the requester below with any questions.';
$lang['cm_external_expires'] = 'Request link expires';
$lang['cm_external_name'] = 'Subject name';
$lang['cm_external_recipient_name'] = 'Subject name';
$lang['cm_external_recipient_email'] = 'Subject email';
$lang['cm_external_guardian'] = 'Parent / guardian (optional)';
$lang['cm_external_usage'] = 'Intended usage';
$lang['cm_external_usage_text'] = 'Intended usage';
$lang['cm_external_personal_message'] = 'Personal message (optional)';
$lang['cm_external_start'] = 'Consent starts';
$lang['cm_external_consent_expires'] = 'Consent expiry (blank for no fixed expiry)';
$lang['cm_external_no_expiry'] = 'No fixed expiry';
$lang['cm_external_granted'] = 'Granted';
$lang['cm_external_declined'] = 'Declined';
$lang['cm_external_manifest'] = 'Assets covered by this consent';
$lang['cm_external_signature_page'] = 'Electronic signature';
$lang['cm_external_signature_notice'] = 'This captured signature applies to the preceding terms, responses and asset manifest only when this exact document is explicitly submitted after review. An electronic signature does not independently verify identity.';
$lang['cm_external_method_typed'] = 'Typed electronic signature';
$lang['cm_external_method_drawn'] = 'Drawn electronic signature';
$lang['cm_external_completed'] = 'Signed consent submitted';
$lang['cm_external_view_consent'] = 'View consent record';
$lang['cm_external_decline_confirmed'] = 'You declined this request. No consent record has been created.';
$lang['cm_external_submission_confirmed'] = 'Your signed consent has been submitted successfully. Thank you.';
$lang['cm_external_download_signed'] = 'Open / download submitted signed PDF';
$lang['cm_external_assets'] = 'Assets covered';
$lang['cm_external_no_preview'] = 'A supported preview is unavailable. Contact the requester to review this asset before agreeing.';
$lang['cm_external_original'] = 'Download original';
$lang['cm_external_form'] = 'Complete consent details';
$lang['cm_external_declarations'] = 'Mandatory declarations';
$lang['cm_external_accept_declarations'] = 'I have reviewed the assets and agree to these declarations.';
$lang['cm_external_options'] = 'Optional permissions';
$lang['cm_external_fields'] = 'Additional required information';
$lang['cm_external_generate'] = 'Review completed information in unsigned PDF';
$lang['cm_external_review_signed'] = 'Review the signed PDF';
$lang['cm_external_review_unsigned'] = 'Review the unsigned PDF';
$lang['cm_external_not_submitted'] = 'Your signature has been applied. Your consent has NOT been submitted yet.';
$lang['cm_external_pdf_help'] = 'Read the complete PDF below. If the embedded viewer is unavailable, open or download the PDF using the link. Return to correct your details if needed.';
$lang['cm_external_open_pdf'] = 'Open / download PDF';
$lang['cm_external_document'] = 'Complete consent PDF';
$lang['cm_external_correct'] = 'Correct details (invalidates signature)';
$lang['cm_external_approve_signed'] = 'I have reviewed this signed PDF and confirm the signature and information are correct.';
$lang['cm_external_submit'] = 'Submit signed consent';
$lang['cm_external_signatory'] = 'Signatory full name';
$lang['cm_external_method'] = 'Signature method';
$lang['cm_external_draw_help'] = 'For a drawn signature, draw in the box. For keyboard operation, select Typed electronic signature above.';
$lang['cm_external_clear'] = 'Clear drawn signature';
$lang['cm_external_sign_confirm'] = 'I have read the complete PDF. Applying my drawn signature or typing my full name constitutes my electronic signature on that PDF.';
$lang['cm_external_apply_signature'] = 'Apply signature and review signed PDF';
$lang['cm_external_decline'] = 'Decline consent request';
$lang['cm_external_draft_created'] = 'Draft created with frozen terms and asset references. Manage this request under Admin > Consents > Consent Requests and use Send / replace link to email it.';
$lang['cm_external_sent'] = 'The request email was accepted for delivery.';
$lang['cm_external_revoked'] = 'The request link has been revoked.';
$lang['cm_external_withdrawn'] = 'Withdrawal recorded. Existing consent expiry and archive processing now applies.';
$lang['cm_external_owner'] = 'Requesting user';
$lang['cm_external_templates'] = 'Consent form templates';
$lang['cm_external_status'] = 'Status';
$lang['cm_external_audit'] = 'Audit trail';
$lang['cm_external_withdraw'] = 'Record withdrawal of consent';
$lang['cm_external_send'] = 'Send / replace link';
$lang['cm_external_revoke'] = 'Revoke request';
$lang['cm_external_empty'] = 'This collection is empty. Add assets before requesting consent.';
$lang['cm_external_no_templates'] = 'An administrator must create and enable a consent form template first.';
$lang['cm_external_new'] = 'New';
$lang['cm_external_template'] = 'Consent form template';
$lang['cm_external_snapshot_notice'] = 'The selected resource references and template terms are frozen when you create the draft. Collection and template changes do not alter this request.';
$lang['cm_external_create_draft'] = 'Send consent request';
$lang['cm_external_title'] = 'Consent form title';
$lang['cm_external_organisation'] = 'Organisation name';
$lang['cm_external_wording'] = 'Full consent wording, permitted usage and restrictions';
$lang['cm_external_privacy'] = 'Privacy policy information';
$lang['cm_external_duration'] = 'Consent duration explanation';
$lang['cm_external_template_enabled'] = 'Template enabled for new requests';
$lang['cm_external_duplicate'] = 'Duplicate template';
$lang['cm_external_configure'] = 'External consent collection';
$lang['cm_external_enabled'] = 'Enable external consent requests';
$lang['cm_external_validity_days'] = 'Default link validity (days)';
$lang['cm_external_default_template'] = 'Default template ID';
$lang['cm_external_drawn'] = 'Allow drawn signatures';
$lang['cm_external_typed'] = 'Allow typed signatures';
$lang['cm_external_originals'] = 'Allow original asset downloads';
$lang['cm_external_reminders'] = 'Enable reminders (replace previous link)';
$lang['cm_external_reminder_days'] = 'Reminder interval (days)';
$lang['cm_external_reminder_limit'] = 'Maximum reminders';
$lang['cm_external_audit_days'] = 'Incomplete request audit retention (days)';
$lang['cm_external_temporary_days'] = 'Temporary PDFs / signature retention after expiry, revocation or decline (days)';
$lang['cm_external_max_resources'] = 'Maximum resources per request';
$lang['cm_external_recipient_copy'] = 'Allow recipient download of completed signed PDF';
$lang['cm_external_immutable'] = 'This submitted consent is an immutable historical record. Create a new request for amendments. Record withdrawal through request management.';
$lang['cm_external_manage'] = 'Manage external requests';
$lang['cm_external_hash'] = 'Submitted PDF SHA-256';
$lang['cm_external_status_draft'] = 'Draft';
$lang['cm_external_status_pending'] = 'Pending';
$lang['cm_external_status_viewed'] = 'Viewed';
$lang['cm_external_status_awaiting_signature'] = 'Awaiting signature';
$lang['cm_external_status_signed'] = 'Signed - awaiting submission';
$lang['cm_external_status_completed'] = 'Completed';
$lang['cm_external_status_declined'] = 'Declined';
$lang['cm_external_status_expired'] = 'Expired';
$lang['cm_external_status_revoked'] = 'Revoked';
$lang['cm_external_status_failed'] = 'Failed';

$lang['collection_actiontype_70'] = 'Consent';
$lang['cm_external_no_requests'] = 'No consent requests yet.';

$lang['cm_external_requests'] = 'Consent Requests';
$lang['cm_external_recipient_view'] = 'Open recipient view';
$lang['cm_external_preview_notice'] = 'Administrator preview. Try the same fields and signature controls as the recipient. Next opens a signed PDF draft for review. Submission and decline are disabled; your preview changes are not saved.';

$lang['cm_external_all_statuses'] = 'All request statuses';
$lang['cm_external_confirm_send'] = 'Send a new consent link? This replaces any link previously emailed for this request.';
$lang['cm_external_confirm_revoke'] = 'Revoke this request? The recipient will no longer be able to use its link.';
$lang['cm_external_confirm_withdraw'] = 'Record withdrawal of this consent? Existing expiry and automatic archive actions will apply. The signed evidence will be retained.';
$lang['cm_external_create_help'] = 'To create a new request, use the "Request consent" option on the collection action menu.';
$lang['cm_external_admin'] = 'Admin';
$lang['cm_external_request_reference'] = 'Consent Request';
$lang['cm_external_templates_help'] = 'Create reusable consent wording for your organisation. Each request keeps its own copy of the template, so later edits do not change requests already issued.';
$lang['cm_external_new_template'] = 'New consent form template';
$lang['cm_external_edit_template'] = 'Edit consent form template';
$lang['cm_external_delete_template'] = 'Delete consent form template';
$lang['cm_external_version'] = 'Version';
$lang['cm_external_copy'] = 'Copy';
$lang['cm_external_template_snapshot_help'] = 'Use plain text. The wording and recipient responses appear in the PDF the recipient reviews and signs. Existing requests retain their original wording.';
$lang['cm_external_delete_template_help'] = 'Delete this template? It will no longer be available for new requests. Existing requests and signed documents keep their original terms. Disable the template instead if you want to keep it available for editing later.';
$lang['cm_external_help_title'] = 'A short name, such as General photography consent. It identifies the template and appears as the title of the PDF.';
$lang['cm_external_help_organisation'] = 'The organisation asking for consent. This name appears on the recipient page, email and PDF.';
$lang['cm_external_help_wording'] = 'The full terms the recipient must read, including permitted uses, limits and exclusions. Enter paragraphs of plain text. Intended usage is also selected from the existing Consent Manager usage list when creating a request.';
$lang['cm_external_help_declarations'] = 'The statements the recipient must agree to before signing. Enter the complete wording, with one statement per line if helpful. One mandatory checkbox asks the recipient to agree to all these declarations.';
$lang['cm_external_help_privacy'] = 'Explain how personal details and signed documents are used and retained, who to contact, and how to withdraw consent. Include your privacy policy address if appropriate.';
$lang['cm_external_help_duration'] = 'Describe how long the agreement applies. This is wording for the form; it does not calculate an expiry date. Set the actual consent expiry date on each request.';
$lang['cm_external_help_options'] = 'Optional choices shown to the recipient as separate, initially unticked checkboxes. Type a permission and press Enter to add it. Use the x on an item to remove it. For example, add Use on social media and Use in printed brochures as separate items. Leave blank if there are no optional choices. Both accepted and declined choices are recorded.';
$lang['cm_external_help_fields'] = 'Additional information the recipient must supply only if your form needs it. Type a field label and press Enter to add it, for example Event name. Use the x on an item to remove it. Each label creates a required text field. Leave blank to collect no additional information. Subject name and parent or guardian details already have their own fields.';

$lang['cm_external_template_active'] = 'Enabled';
$lang['cm_external_template_inactive'] = 'Disabled';
$lang['cm_external_add_item_help'] = 'Type an item and press Enter to add';
$lang['cm_external_add_item'] = 'Add';

$lang['cm_external_portal_title'] = 'Consent request';

$lang['cm_external_include_requester'] = 'Include requester and request date/time in consent PDF';
$lang['cm_external_requested_by'] = 'Requested by';
$lang['cm_external_requested_at'] = 'Request initiated';

$lang['cm_external_next'] = 'Next';
$lang['cm_external_draw_required'] = 'Please draw your signature in the box, or choose a typed signature, before continuing.';

$lang['cm_external_pdf_details'] = 'Consent details';
$lang['cm_external_pdf_terms'] = 'Consent terms';
$lang['cm_external_pdf_expiry'] = 'Consent expiry date';
$lang['cm_external_signed_at'] = 'Signature captured';
$lang['cm_external_link'] = 'Get recipient link';
$lang['cm_external_confirm_link'] = 'Generate a recipient link for manual sharing? This replaces the previous link. No email will be sent.';
$lang['cm_external_manual_link_ready'] = 'The new link is ready to copy and share. It replaces the previous link. No email has been sent.';
$lang['cm_external_recipient_link'] = 'Recipient link';
$lang['cm_external_copy_link'] = 'Copy link';
$lang['cm_external_request_closed'] = 'This request no longer accepts consent. Create a new request to send a new invitation.';
$lang['cm_external_status_withdrawn'] = 'Withdrawn';
$lang['cm_external_withdrawn_record'] = 'Consent has been withdrawn. This record is retained as historical evidence and is not active consent. Withdrawal recorded:';
$lang['cm_external_status_active'] = 'Active';
