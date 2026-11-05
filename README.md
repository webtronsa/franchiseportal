# Franchise portal

Franchise portal for Monkeynastix, served at https://portal.monkeynastix.online/.

- `portal/index.html`: the dashboard home. It is deployed as `index.php` behind the shared `mnx_sso` sign-in from accounts.monkeynastix.online.
- `portal/api/stats.php`: the "Your numbers" section (Minastix enrollments, schools CRM venues, pipeline and visits) for the signed-in franchise.
- `portal/api/support.php`: open HESK tickets for the signed-in franchise, shown in the Support card.

## Stats setup (server, once)

1. Create a read-only MySQL user with `SELECT` on `monkeynet_connect` (and on the schools copy's database, if it has its own).
2. Create `/home/monkedotonlne/mnx-secrets/stats.php` (owner monkedotonlne, mode 0640) returning
   `['b44_api_key' => <the Minastix api_key from the CRM config>, 'b44_base' => 'https://minastix.base44.app/api/entities',
     'franchise_map' => '/home/monkedotonlne/mnx-secrets/franchise_map.php',
     'crm' => [['label' => 'monkeynastix.net', 'dsn' => 'mysql:host=localhost;dbname=monkeynet_connect;charset=utf8mb4', 'user' => .., 'pass' => ..], ...]]`.
3. As root, build the franchise map from the CRM sign-in lists and add the same line to root's daily cron:
   `php tools/sync_franchise_map.php /home/monkedotonlne/mnx-secrets/franchise_map.php monkedotonlne /home/monkeynet/public_html/api/config.php [schools copy config.php]`

4. For the Support card, add `'hesk' => ['dsn' => 'mysql:host=localhost;dbname=<hesk db>;charset=utf8mb4', 'user' => .., 'pass' => .., 'prefix' => 'hesk_']` to the same file, with a user that has `SELECT` on HESK's `tickets`, `customers` and `ticket_to_customer` tables only (HESK 3.6). HESK has no API, so `portal/api/support.php` reads open tickets by the franchise's emails.

Emails not in the map see no stats. Admins in the map see all franchises and can pick one.
