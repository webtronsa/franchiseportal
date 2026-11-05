<?php
// Stats for the signed-in franchise: Minastix enrollments and schools CRM numbers.
// The franchise comes from the signed-in email via the franchise map. Unlisted emails get no data.
// Admins in the map see all franchises, and may pick one with ?franchise=<id>.
require_once __DIR__ . '/_stats.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') mnx_json(405, ['error' => 'method not allowed']);

[$email] = mnx_session();
$map = mnx_franchise_map();
$entry = $map[strtolower(trim($email))] ?? null;
if (!is_array($entry)) mnx_json(200, ['linked' => false]);

$isAdmin = !empty($entry['admin']);
$names = [];
foreach ($map as $row) {
    if (mnx_valid_fid($row['id'] ?? null)) $names[$row['id']] = (string) $row['name'];
}
asort($names);

if ($isAdmin) {
    $pick = (string) ($_GET['franchise'] ?? '');
    $fid = isset($names[$pick]) ? $pick : null; // null = all franchises
} else {
    $fid = $entry['id'] ?? null;
    if (!mnx_valid_fid($fid)) mnx_json(200, ['linked' => false]);
}

$out = [
    'linked' => true,
    'admin' => $isAdmin,
    'franchise' => ['id' => $fid, 'name' => $fid ? ($names[$fid] ?? $entry['name']) : 'All franchises'],
    'enrollments' => mnx_mx_stats($fid, $names),
    'crm' => mnx_crm_stats($fid),
];
if ($isAdmin) {
    $out['franchises'] = [];
    foreach ($names as $id => $name) $out['franchises'][] = ['id' => $id, 'name' => $name];
}
// Per-franchise totals only make sense on the all-franchises view.
if ($fid !== null) unset($out['enrollments']['by_franchise']);

mnx_json(200, $out);
