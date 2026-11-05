<?php
// Tells the public portal page whether the visitor is signed in, so it can show franchise data or a sign-in button.
// Never fails with 401: signed-out visitors are normal here, since the app menu is public.
require_once __DIR__ . '/_common.php';

$user = null;
if (is_readable(MNX_SSO_LIB)) {
    require_once MNX_SSO_LIB;
    if (function_exists('sso_current')) $user = sso_current();
}
$signedIn = is_array($user) && !empty($user['email']);
mnx_json(200, ['signed_in' => $signedIn, 'email' => $signedIn ? (string) $user['email'] : null]);
