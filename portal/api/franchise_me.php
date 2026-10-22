<?php
// My Franchise: details for the signed-in franchisee. inastix supplies the franchise name and id.
// Saved edits (stored by the signed-in franchise only) override the inastix values where set.
require_once __DIR__ . '/_common.php';

[$email, $token, $sessionCompany] = mnx_session();
$tenant = mnx_inastix_tenant($token);
$companyUuid = $tenant['company_uuid'] ?? $sessionCompany;

$franchise = ['name' => $tenant['name'], 'company_uuid' => $companyUuid, 'email' => $email];
foreach (MNX_FIELDS as $f) { $franchise[$f] = $franchise[$f] ?? null; }
$franchise['city'] = null; // inastix does not expose a town; see the save endpoint for the form field name

if ($companyUuid && is_readable(MNX_DB_CONFIG)) {
    $stmt = mnx_db()->prepare('SELECT name, contact_name, phone, email, address, town, facebook_url FROM franchise_details WHERE company_uuid = ?');
    $stmt->execute([$companyUuid]);
    if ($saved = $stmt->fetch()) {
        foreach ($saved as $k => $v) { if ($v !== null && $v !== '') $franchise[$k] = $v; }
    }
}
$franchise['city'] = $franchise['town'];

mnx_json(200, ['franchise' => $franchise]);
