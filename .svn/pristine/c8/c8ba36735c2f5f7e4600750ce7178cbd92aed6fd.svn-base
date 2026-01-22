<?php

declare(strict_types=1);

namespace SimpleSAML\Module\statistics;

use Exception;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Utils;

/**
 * Class implementing the access checker function for the statistics module.
 *
 * @package SimpleSAMLphp
 */
class AccessCheck
{
    /**
     * Check that the user has access to the statistics.
     * If the user doesn't have access, send the user to the login page.
     *
     * @param \SimpleSAML\Configuration $statconfig
     * @throws \Exception
     * @throws \SimpleSAML\Error\Exception
     */
    public static function checkAccess(Configuration $statconfig): void
    {
        $protected = $statconfig->getOptionalBoolean('protected', false);
        $authsource = $statconfig->getOptionalString('auth', null);
        $allowedusers = $statconfig->getOptionalValue('allowedUsers', null);
        $useridattr = $statconfig->getOptionalString('useridattr', 'eduPersonPrincipalName');

        $acl = $statconfig->getOptionalValue('acl', null);
        if ($acl !== null && !is_string($acl) && !is_array($acl)) {
            throw new Error\Exception('Invalid value for \'acl\'-option. Should be an array or a string.');
        }

        if (!$protected) {
            return;
        }

        $authUtils = new Utils\Auth();
        if ($authUtils->isAdmin()) {
            // User logged in as admin. OK.
            Logger::debug('Statistics auth - logged in as admin, access granted');
            return;
        }

        if (!isset($authsource)) {
            // If authsource is not defined, init admin login.
            $authUtils->requireAdmin();
        }

        // We are using an authsource for login.

        $as = new Auth\Simple($authsource);
        $as->requireAuth();

        // User logged in with auth source.
        Logger::debug('Statistics auth - valid login with auth source [' . $authsource . ']');

        // Retrieving attributes
        $attributes = $as->getAttributes();

        if (!empty($allowedusers)) {
            // Check if userid exists
            if (!isset($attributes[$useridattr][0])) {
                throw new Exception('User ID is missing');
            }

            // Check if userid is allowed access..
            if (in_array($attributes[$useridattr][0], $allowedusers, true)) {
                Logger::debug(
                    'Statistics auth - User granted access by user ID [' . $attributes[$useridattr][0] . ']'
                );
                return;
            }
            Logger::debug(
                'Statistics auth - User denied access by user ID [' . $attributes[$useridattr][0] . ']'
            );
        } else {
            Logger::debug('Statistics auth - no allowedUsers list.');
        }

        if (!is_null($acl)) {
            $acl = new ACL($acl);
            if ($acl->allows($attributes)) {
                Logger::debug('Statistics auth - allowed access by ACL.');
                return;
            }
            Logger::debug('Statistics auth - denied access by ACL.');
        } else {
            Logger::debug('Statistics auth - no ACL configured.');
        }
        throw new Error\Exception('Access denied to the current user.');
    }
}
