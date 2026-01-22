<?php

declare(strict_types=1);

namespace SimpleSAML\Module\consent;

use SimpleSAML\IdP;
use SimpleSAML\Module;
use SimpleSAML\Utils;

/**
 * Class defining the logout completed handler for the consent page.
 *
 * @package SimpleSAMLphp
 */

class Logout
{
    /**
     * @param \SimpleSAML\IdP $idp
     * @param array $state
     */
    public static function postLogout(IdP $idp, array $state): void
    {
        $url = Module::getModuleURL('consent/logoutcompleted');

        $httpUtils = new Utils\HTTP();
        $httpUtils->redirectTrustedURL($url);
    }
}
