<?php
// Shared helpers for the My Franchise endpoints. Identity comes from the shared sign-in only.
declare(strict_types=1);

const MNX_SSO_LIB = '/home/monkedotonlne/sso_config/lib.php';
const MNX_DB_CONFIG = '/home/monkedotonlne/mnx-secrets/portal_db.php'; // created on the server; returns ['dsn'=>..,'user'=>..,'pass'=>..]

function mnx_json(int $code, array $body): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

// Returns [email, inastix_access_token, company_uuid] or exits with 401/500.
function mnx_session(): array {
    if (!is_readable(MNX_SSO_LIB)) mnx_json(500, ['error' => 'sign-in unavailable']);
    require_once MNX_SSO_LIB;
    if (!function_exists('sso_current')) mnx_json(500, ['error' => 'sign-in unavailable']);
    $user = sso_current();
    if (!is_array($user) || empty($user['email'])) mnx_json(401, ['error' => 'not signed in']);
    return [(string) $user['email'], $user['inastix_access_token'] ?? null, $user['company_uuid'] ?? null];
}

// Finds the franchisee tenant for the signed-in account from inastix. Returns ['name','company_uuid'] or nulls.
function mnx_inastix_tenant(?string $token): array {
    $out = ['name' => null, 'company_uuid' => null];
    if (!is_string($token) || $token === '') return $out;
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
    if (!is_array($tenants)) return $out;
    foreach ($tenants as $tenant) {
        foreach (($tenant['groups'] ?? []) as $group) {
            if (($group['name'] ?? '') === 'franchisee') {
                return ['name' => $tenant['tenant_name'] ?? null, 'company_uuid' => $tenant['id'] ?? null];
            }
        }
    }
    return $out;
}

function mnx_db(): PDO {
    if (!is_readable(MNX_DB_CONFIG)) mnx_json(500, ['error' => 'storage unavailable']);
    $cfg = require MNX_DB_CONFIG;
    return new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

const MNX_FIELDS = ['name', 'contact_name', 'phone', 'email', 'address', 'town', 'facebook_url'];
