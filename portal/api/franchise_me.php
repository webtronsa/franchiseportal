<?php
// Returns the signed-in franchisee's details for the My Franchise card.
// Identity comes from the shared sign-in (sso_current()). Franchise name comes from inastix
// (/companies/connected/, franchisee group), the same check franchise-portal's inastixClient uses.
// Anything not known is returned as null, so the form starts empty and can be filled by hand.
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function mnx_fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

$lib = '/home/monkedotonlne/sso_config/lib.php';
if (!is_readable($lib)) {
    mnx_fail(500, 'sign-in unavailable');
}
require_once $lib;
if (!function_exists('sso_current')) {
    mnx_fail(500, 'sign-in unavailable');
}

$user = sso_current();
if (!is_array($user) || empty($user['email'])) {
    mnx_fail(401, 'not signed in');
}

$franchise = [
    'name' => null,
    'city' => null,
    'address' => null,
    'email' => (string) $user['email'],
    'phone' => null,
    'contact_name' => null,
    'facebook_url' => null,
    'company_uuid' => null,
];

// The inastix access token must come from the sign-in session. It is never sent to the browser.
$token = $user['inastix_access_token'] ?? null;
if (is_string($token) && $token !== '') {
    $ch = curl_init('https://backend.inastix.com/companies/connected/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: JWT ' . $token, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $tenants = ($code === 200 && is_string($body)) ? json_decode($body, true) : null;
    if (is_array($tenants)) {
        foreach ($tenants as $tenant) {
            foreach (($tenant['groups'] ?? []) as $group) {
                if (($group['name'] ?? '') === 'franchisee') {
                    $franchise['name'] = $tenant['tenant_name'] ?? null;
                    $franchise['company_uuid'] = $tenant['id'] ?? null;
                    break 2;
                }
            }
        }
    }
}

echo json_encode(['franchise' => $franchise], JSON_UNESCAPED_SLASHES);
