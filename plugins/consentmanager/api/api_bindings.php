<?php

function api_consentmanager_get_consents($ref)
{
    return consentmanager_get_consents((int) $ref);
}

function api_consentmanager_delete_consent($ref)
{
    return consentmanager_delete_consent((int) $ref);
}

function api_consentmanager_batch_link_unlink($consent, $collection, $unlink)
{
    return consentmanager_batch_link_unlink((int) $consent, (int) $collection, $unlink);
}

function api_consentmanager_link_consent($consent, $resource)
{
    return consentmanager_link_consent((int) $consent, (int) $resource);
}

function api_consentmanager_unlink_consent($consent, $resource)
{
    return consentmanager_unlink_consent((int) $consent, (int) $resource);
}

function api_consentmanager_create_consent($name, $email, $telephone, $consent_usage, $notes = "", $expires = null, $date_of_birth = null, $address = null, $parent_guardian = null, $date_of_consent = null)
{
    global $userref;
    return consentmanager_create_consent($name, $date_of_birth, $address, $parent_guardian, $email, $telephone, $consent_usage, $notes, $date_of_consent, $expires, $userref);
}

function api_consentmanager_get_consent($consent)
{
    return consentmanager_get_consent((int) $consent);
}

function api_consentmanager_update_consent($consent, $name, $email, $telephone, $consent_usage, $notes = "", $expires = null, $date_of_birth = null, $address = null, $parent_guardian = null, $date_of_consent = null)
{
    return consentmanager_update_consent((int) $consent, $name, $date_of_birth, $address, $parent_guardian, $email, $telephone, $consent_usage, $notes, $date_of_consent, $expires);
}

function api_consentmanager_get_all_consents($findtext = "")
{
    return consentmanager_get_all_consents($findtext);
}

function api_consentmanager_get_all_consents_by_collection($collection)
{
    return consentmanager_get_all_consents_by_collection((int) $collection);
}

function api_consentmanager_save_file($consent, $filename)
{
    $filedata=getval("filedata","");// Receive the file data via post. It will be too long to include in the query.
    return consentmanager_save_file((int) $consent, $filename, $filedata);
}
