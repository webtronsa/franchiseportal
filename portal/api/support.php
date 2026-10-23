<?php
// Support tickets for the signed-in franchise, read straight from the HESK database (HESK has no API).
// Tickets match the signed-in email and any other email the franchise map lists for the same franchise.
// Admins in the map see every ticket. Unlisted emails get nothing.
// Config: 'hesk' => ['dsn' => .., 'user' => .., 'pass' => .., 'prefix' => 'hesk_']
// in the stats config file, with a read-only database user.
require_once __DIR__ . '/_stats.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') mnx_json(405, ['error' => 'method not allowed']);

[$email] = mnx_session();
$email = strtolower(trim($email));
$map = mnx_franchise_map();
$entry = $map[$email] ?? null;
if (!is_array($entry)) mnx_json(200, ['linked' => false]);
$isAdmin = !empty($entry['admin']);

$emails = [$email];
if (!$isAdmin && mnx_valid_fid($entry['id'] ?? null)) {
    foreach ($map as $other => $row) {
        if (($row['id'] ?? null) === $entry['id']) $emails[] = (string) $other;
    }
}
$emails = array_values(array_unique($emails));

$cfg = mnx_stats_config()['hesk'] ?? null;
if (!is_array($cfg)) mnx_json(200, ['linked' => true, 'available' => false]);
$prefix = preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['prefix'] ?? 'hesk_'));

// HESK's built-in statuses; custom ones (id 6+) are shown as "Other". 3 is the only closed state.
const HESK_STATUS = [0 => 'New', 1 => 'Waiting reply', 2 => 'Replied', 3 => 'Resolved', 4 => 'In progress', 5 => 'On hold'];

try {
    $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    // HESK 3.5+ links tickets to customers through ticket_to_customer; the email lives on the customer.
    $match = '';
    $args = [];
    if (!$isAdmin) {
        foreach ($emails as $i => $e) $args[":e$i"] = $e;
        $match = " WHERE id IN (SELECT tc.ticket_id FROM `{$prefix}ticket_to_customer` tc"
            . " JOIN `{$prefix}customers` c ON c.id = tc.customer_id"
            . ' WHERE LOWER(c.email) IN (' . implode(', ', array_keys($args)) . '))';
    }
    $t = "`{$prefix}tickets`";
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS n FROM $t$match GROUP BY status");
    $stmt->execute($args);
    $byStatus = [];
    $open = $total = 0;
    foreach ($stmt as $r) {
        $label = HESK_STATUS[(int) $r['status']] ?? 'Other';
        $byStatus[$label] = ($byStatus[$label] ?? 0) + (int) $r['n'];
        $total += (int) $r['n'];
        if ((int) $r['status'] !== 3) $open += (int) $r['n'];
    }
    $stmt = $pdo->prepare("SELECT subject, status, lastchange FROM $t$match"
        . ($match ? ' AND' : ' WHERE') . ' status <> 3 ORDER BY lastchange DESC LIMIT 3');
    $stmt->execute($args);
    $recent = [];
    foreach ($stmt as $r) {
        $recent[] = [
            'subject' => (string) $r['subject'],
            'status' => HESK_STATUS[(int) $r['status']] ?? 'Other',
            'updated' => (string) $r['lastchange'],
        ];
    }
} catch (Throwable $e) {
    error_log('portal support: HESK read failed: ' . $e->getMessage());
    mnx_json(200, ['linked' => true, 'available' => false]);
}

$out = ['linked' => true, 'available' => true, 'admin' => $isAdmin, 'open' => $open, 'total' => $total, 'recent' => $recent];
$out['by_status'] = [];
foreach ($byStatus as $label => $n) $out['by_status'][] = ['label' => $label, 'value' => $n];
mnx_json(200, $out);
