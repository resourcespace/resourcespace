<?php

include dirname(__DIR__) . '/../../../include/boot.php';
include dirname(__DIR__) . '/../../../include/authenticate.php';

$accepted_cookies_use = getval('accepted_cookies_use', null, true);
$return               = array();

if (
    !is_null($accepted_cookies_use)
    && (int) $accepted_cookies_use === 0
) {
    rs_setcookie('accepted_cookies_use', '', -1, '', '', substr($baseurl, 0, 5) == 'https', false);

    $return['error'] = array(
        'status' => 307,
        'title'  => 'Temporary redirect',
        'detail' => "{$baseurl}/login.php?logout=true&cookies_use=true");
}

header('Content-Type: application/json');
echo json_encode($return);
exit();
