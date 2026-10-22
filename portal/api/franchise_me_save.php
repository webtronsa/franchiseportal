<?php
// Saves the signed-in franchise's details. The franchise is taken from the session and inastix only,
// never from the request, so nobody can write another franchise's row.
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') mnx_json(405, ['error' => 'method not allowed']);
if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === false) mnx_json(415, ['error' => 'send JSON']);

[$email, $token, $sessionCompany] = mnx_session();
$tenant = mnx_inastix_tenant($token);
$companyUuid = $tenant['company_uuid'] ?? $sessionCompany;
if (!$companyUuid) mnx_json(403, ['error' => 'no franchise on this account']);

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) mnx_json(400, ['error' => 'bad JSON']);

$clean = [];
foreach (MNX_FIELDS as $f) {
    $v = trim((string) ($in[$f] ?? ''));
    if (mb_strlen($v) > 500) mnx_json(400, ['error' => "$f is too long"]);
    $clean[$f] = $v === '' ? null : $v;
}
if (!$clean['name']) mnx_json(400, ['error' => 'enter the franchise name']);
if ($clean['facebook_url'] !== null && !preg_match('#^https://(www\.|m\.)?(facebook|fb)\.com/#i', $clean['facebook_url'])) {
    mnx_json(400, ['error' => 'use a full Facebook link starting with https://www.facebook.com/']);
}
if ($clean['email'] !== null && !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) mnx_json(400, ['error' => 'invalid email']);

$pdo = mnx_db();
$sql = 'INSERT INTO franchise_details (company_uuid, name, contact_name, phone, email, address, town, facebook_url, updated_by)
        VALUES (:company, :name, :contact_name, :phone, :email, :address, :town, :facebook_url, :by)
        ON DUPLICATE KEY UPDATE name = VALUES(name), contact_name = VALUES(contact_name), phone = VALUES(phone),
          email = VALUES(email), address = VALUES(address), town = VALUES(town), facebook_url = VALUES(facebook_url),
          updated_by = VALUES(updated_by)';
$pdo->prepare($sql)->execute([
    ':company' => $companyUuid, ':name' => $clean['name'], ':contact_name' => $clean['contact_name'],
    ':phone' => $clean['phone'], ':email' => $clean['email'], ':address' => $clean['address'],
    ':town' => $clean['town'], ':facebook_url' => $clean['facebook_url'], ':by' => $email,
]);

mnx_json(200, ['saved' => true]);
