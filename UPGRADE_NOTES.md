# ViteMart supermarket upgrade – October 2026

## Install

```bash
php artisan migrate          # 4 new migrations, all guarded (safe on an existing database)
php artisan optimize:clear
```

The migration also:
- converts stock quantities to decimals (for weighed goods),
- drops the UNIQUE index on `stocks.reference_number` (it made multi-item POS sales fail),
- creates the new permissions and gives them to **Super Admin** and **Admin**.

Then, in **Roles and Permissions**, give your other roles what they need, e.g.

| Role | Suggested permissions |
|---|---|
| Cashier | View pos, View product, Create stock adjustment |
| Supervisor | + Give pos discount, Override pos price, Void pos sale, Process pos return, Sell below cost, Approve stock adjustment, Manage pos shifts |
| Manager | + dashboard, View profit figures, Manage price control, View audit trail |

Set a **supervisor PIN** for each supervisor in **Security → Login Control → Staff accounts → Edit**.

`app/Console/Commands/Kernel.php` was moved to `app/Console/Kernel.php` – it declared `App\Console\Kernel`
from the wrong folder, which crashed the test runner. Nothing else uses it in Laravel 12.

## What changed

### Products (`/products`, `/products/create`)
- New full-page product form. Barcode (valid in-store EAN-13, prefix `20`) and SKU are generated automatically;
  scanning an existing manufacturer barcode is checked for duplicates live.
- **Packs**: Piece / Pack ×6 / Carton ×24 … each with its own price, wholesale price and barcode.
  Scanning a carton barcode at the till sells a carton. Stock is always kept in the base unit.
- **Variations** get their own barcode, SKU, cost, price, wholesale price and **stock**.
  Editing a product no longer deletes/recreates variations (this used to wipe their stock history).
- Weighed products with a PLU code – scale labels (21/22 prefix, weight or price embedded) are read at the till.
- Promo price with start/end dates (packs get the same % off), wholesale price, reorder level.
- Product list: filters (low stock, below cost, missing barcode, promo), bulk actions (print labels, adjust,
  count, change prices, activate/deactivate, generate barcodes), quick stock adjust, duplicate product.
- Barcode label printing (`/products/labels`) in 4 sticker sizes, with pack labels.
- Products with sales/stock history are deactivated instead of deleted.

### Price control (`/price-control`)
- Bulk change by %, fixed amount, exact price or cost + markup; by selection, category, brand or search;
  include variations and pack prices; round up to ₦5/10/50/100/500.
- Preview before applying, below-cost warning, one-click **undo** of any bulk change.
- Start/end promotions for many products. Full price history per product.

### Stock adjustments (`/stock-adjustments`)
- Adjustment, stock count (stock-take), goods received (supplier, invoice, cost, expiry, batch), damage, expired.
- Maker–checker: entries are **pending** until someone with *Approve stock adjustment* approves them
  (setting can be turned off). Bulk approve, reject with reason, cancel.
- Stock counts post the difference from the balance at counting time, so sales during the count are not lost.
- The old inventory "adjust" / "bulk adjust" endpoints now go through the same approval flow.

### POS (`/pos`)
- New full-screen till: scanner-first, `3*` quantity prefix, live search, category grid, pack switcher,
  line and order discounts, price change, customer search/quick add, wholesale pricing for wholesale customers,
  loyalty points, notes.
- **Prices are recalculated on the server**; tampered prices are refused.
- Split payments (cash/card/transfer/credit), quick cash buttons, keypad, change, credit limit check,
  duplicate-submit protection.
- Supervisor PIN approval for discounts above the limit, price changes, selling below cost, voids and refunds.
- Hold / resume sales, receipts lookup → reprint, **void** (stock returned, points reversed) or **partial refund**.
- Shifts / cash drawer: open float, cash in/out, close with counted cash → X/Z report with variance.
- Keyboard: F2 search, F4 hold, F6 discount, F8 held, F9 pay, +/- qty, Del remove, Enter to pay/complete.
- Sales transactions page (`/pos/transactions`) with filters and CSV export; shift list (`/pos/shifts`).

### Dashboard (`/dashboard`)
- Counts **till sales** (the old one only counted Paystack orders).
- Today / yesterday / 7 / 30 days / month / last month / year / custom, compared with the previous period.
- Revenue, transactions, basket, gross profit & margin (permission *View profit figures*), discounts,
  refunds, voids, new customers; sales by hour/day chart; payment mix; category sales; top products;
  cashier performance; stock value, low/out of stock, pending approvals, expiring stock; online users,
  failed logins and latest activity. CSV export. Auto-refreshes today's numbers.

### Login control (`/security`)
- Store login hours and days; per-role hours; per-user rule (store hours / custom / any time / blocked);
  disable accounts.
- Log out everyone now, log out one user, end a single session, schedule a one-off logout,
  daily automatic logout, idle timeout, one device per user, maintenance lock (Super Admin only).
- Super Admin can never be locked out. Public self-registration was disabled (staff are created by an admin).
- Login accepts email **or** username.

### Audit trail (`/audit-trail`)
- Logins, failed/blocked logins, logouts, forced logouts; product, variation, pack, category, brand, customer,
  unit, store setting, location, user and role changes (before → after); price changes; stock adjustments and
  approvals; sales, overrides, voids, refunds; shift and cash movements; settings. Filters and CSV export.

## Tests

```bash
# needs MySQL – create an empty database first
DB_DATABASE=vitemart_test php artisan migrate
DB_CONNECTION=mysql DB_DATABASE=vitemart_test ./vendor/bin/pest --filter=SupermarketUpgradeTest
```

---

# Multi-branch, phase 1–2 (foundations and identity) – release 2026.11.0

## What it adds
- Every server now knows **who it is**: `standalone` (one shop – how it works today), `central` (head office)
  or `branch` (a store server that syncs with head office). Check with `php artisan vitemart:status`.
- Your existing shop becomes branch **#1 "MAIN"** automatically. Nothing changes until you run `vitemart:install`.
- Every record that will travel between servers gets a permanent ID (`uid`, a ULID); branch-owned records get `branch_id`.
- Receipt and document numbers are per branch: `IKJ-261011-000123`, `ADJ-IKJ-000001`, `TRF-IKJ-000001` …
- Generated barcodes carry the branch number: `20` + `02` (branch) + 8 digits + check digit, so two branches never
  create the same barcode while offline.
- **Branches** page (`/branches`): list with online status, add a branch (head office only), branch details,
  enrollment codes, server revoke, and which staff may work at each branch.
- Each branch has *store*, *transit* and *quarantine* stock locations.
- **Branch-scoped sign-in**: on a central or branch server, staff can only sign in at their home branch or branches
  they were added to. Super Admin and roles with *Access all branches* can sign in anywhere.
- **Two-step sign-in** (authenticator app, 6-digit codes, 8 one-time recovery codes) under the user menu →
  *Two-step sign-in*. Required for Super Admin, HQ Admin, Finance Officer and Inventory Controller on central and
  branch servers (set `VITEMART_MFA_ENFORCE=true|false` in `.env` to force it on or off). A Super Admin can reset it
  for someone who lost their phone (Security → Staff accounts → *Reset 2-step*).

## Install
```bash
php artisan migrate        # 2 new migrations; existing data becomes branch #1
php artisan optimize:clear
```

### Head office server (VPS)
```bash
php artisan vitemart:install --mode=central --org="ViteMart Ltd"
```
Then in **Branches**: *Add branch* (code e.g. `IKJ`, number 1–99) → open it → **Issue enrollment code** (valid 24 h, single use).

### Each store server
```bash
php artisan vitemart:install --mode=branch --central-url=https://hq.example.com --branch-code=IKJ --code=XXXX-XXXX-XXXX
php artisan vitemart:status
```
Keep the Laravel scheduler running (`* * * * * php artisan schedule:run`) – it sends a heartbeat to head office every minute.

The store's existing data stays with it: the store server takes the branch identity that head office issued.
The branch server talks to head office over HTTPS with a token and a signed request (HMAC-SHA256, 5-minute clock window),
so keep the server clock synced (NTP).

## Tests
```bash
DB_CONNECTION=mysql DB_DATABASE=vitemart_test ./vendor/bin/pest      # 30 tests
```

---

# Multi-branch, phases 3–4 (catalogue and the branch till)

## Install
```bash
php artisan migrate                 # 2 new migrations
php artisan vitemart:rebuild-stock --barcodes   # optional check: recomputes balances and the barcode index
php artisan optimize:clear
```
Keep the scheduler running (`* * * * * php artisan schedule:run`): on branch servers it now also sends
price-change requests / new products to head office and applies head office's decisions every minute.

## Catalogue (phase 3)
- **Barcode index** – every barcode (product, variation, pack, and *extra* codes) lives in one table with a unique key,
  so a code can never ring up two items. Product form → *Other barcodes for this item*: add the new-packaging code,
  a second supplier's code or an old code; scanning any of them rings up that item (or that carton).
- **Branch prices** (Catalogue → Branch prices) – price lists that override the catalogue price item by item:
  a branch's own list beats an all-branch list, higher priority wins, optional start/end dates. The till, labels and
  every price shown use them automatically.
- **What branches may change** (Catalogue → What branches may change) – per field: head office only / branch asks /
  branch decides. Defaults: name, barcodes, category, packs, cost = head office; prices = branch asks;
  reorder level and "sold at this branch" = branch decides.
- On a branch server, a **head-office product** opens with a blue banner; locked fields are greyed out. Changing a
  price sends a **request** to head office (with a reason) – the till keeps the old price until it is approved.
  The bulk price tool and promotions skip head-office products; price-control quick edits become requests.
- **Requests from branches** (head office): approve a price for that branch only (goes into its branch price list)
  or for all branches (changes the catalogue), with a note; or reject.
- A branch can **offer a product it created** to head office (*Offer to head office* on the product). Head office
  reviews it (barcode clashes are flagged) and adds it to the catalogue under the same identity.
- Branch servers keep requests if head office is unreachable and send them when it is back.

New permissions (given to Super Admin and Admin): Manage price lists, Request price change, Approve price change,
Submit product to head office, Approve product submission, Manage field policies.

## The branch till (phase 4)
- **Stock balances per branch**: on hand (store/warehouse), reserved, quarantined, in transit, incoming.
  *Available = on hand − reserved*; the till refuses to sell stock that is reserved (e.g. for a transfer).
  Stock moved to a quarantine location stops being sellable. Product stock figures now show this branch's sellable stock.
- **Tills** (POS → Tills): each till sells from a stock location; one open shift per till. Each till PC picks its till
  once from the top bar of the POS. Sales and payments record the till.
- **Transfers wait for confirmation** (setting `transfer_needs_confirmation`, on by default): a bank transfer taken at
  the till is *Waiting* until someone with *Confirm payments* checks the bank (POS → Payments to confirm) and marks it
  *Received* or *Not received* (the sale becomes unpaid and the customer owes it). A supervisor who has seen the money
  can tick "I have seen this money" at the till.
  Optional `card_needs_terminal_ref`: the cashier must type the card terminal reference.
- **No internet needed in the shop**: the till and back-office pages now load fonts and libraries from `public/vendor`
  instead of CDNs. The till also installs a small service worker (`public/pos-sw.js`, on https or localhost) that keeps
  its files on the PC and shows a "reconnecting" screen – with the cart kept – if the store server can't be reached.
- Fixed: the stock ledger could not record the receiving side of a transfer (`transfer_in` was missing from the column).

New permissions: Manage cash registers, Confirm payments, View stock balances.

## Tests
```bash
DB_CONNECTION=mysql DB_DATABASE=vitemart_test ./vendor/bin/pest      # 46 tests
```

---

# Multi-branch, phase 5 (sync)

## Install
```bash
php artisan migrate        # 1 new migration
php artisan optimize:clear
```
- **Head office:** `php artisan vitemart:sync --snapshot` once (puts the whole catalogue, staff, roles, customers and
  head-office settings into the feed). After that the scheduler keeps the feed up to date every minute.
- **Each branch:** `php artisan vitemart:sync --backfill` once (sends the branch's existing sales, stock, customers and
  shifts to head office – add `--since=2026-01-01` to limit history), then the scheduler syncs every minute.
  New branches do this automatically when they run `vitemart:install`.
- The cron entry is the same on every server: `* * * * * php artisan schedule:run`.

## How it works
- **Branch → head office:** every sale, sale line, payment, refund, stock movement, stock adjustment, shift, cash
  in/out, customer, loyalty-point entry, till, location, locally created product and audit entry is written to an
  outbox *in the same database transaction* as the change, then sent in order. If the internet is down nothing is
  lost – it waits and goes when the line is back. Head office applies each event exactly once.
- **Head office → branches:** catalogue (products, packs, variations, barcodes), categories, brands, units, price lists
  (a branch only receives its own and the all-branch ones), the "what branches may change" rules, staff (with their
  roles, home branch and extra branches), roles and permissions, customers with their loyalty balance, and the
  head-office settings (discount limit, below-cost rule, approval rules, payment rules, scale-barcode rules …).
  Each branch reads a change feed with its own position, so a branch that was offline for a week just catches up.
- **Who wins:** head office for what it owns; the branch for what the rules let it decide (reorder level, "sold
  here"); last change wins for a customer's details; loyalty points are one balance – head office adds up the
  points earned and spent at every branch.
- **Same thing created twice:** a customer with the same phone, a product with the same barcode, staff with the same
  email, a category/unit with the same name – they are recognised as one record; the branch takes head office's
  identity and history stays linked. These matches are listed on the Sync page.
- **Which branches sell a product:** product page at head office → *Which branches sell this* (all / selected).
- **Sync page** (sidebar → Branches → Sync):
  - Branch: last sync, what is waiting, problems with the reason head office gave, *Sync now*, *Send again*,
    *Download catalogue again*.
  - Head office: every branch's last contact, how far behind, what is waiting there, what failed here, matched
    records, and *Send instruction*: download catalogue again, recalculate stock, send history again, sign everyone
    out, retry failed, report diagnostics. A branch on an older version refuses an instruction that needs a newer one.
- Head-office generated barcodes now start `2900…` (the old single-store codes `2000…` stay with the first branch).
- Two-step sign-in secrets are not synced: each person sets it up once on each server they sign in to.
- Several branches can now each have a "Main store" location (location names/codes are unique per branch).

New permissions: View sync status, Manage sync, Distribute products.

## Tests
```bash
DB_CONNECTION=mysql DB_DATABASE=vitemart_test ./vendor/bin/pest      # 57 tests
```

---

# Multi-branch, phase 6 (transfers, stock-takes, purchasing)

## Install
```bash
php artisan migrate        # 1 new migration
php artisan optimize:clear
```
New sidebar section **Stock & buying**: Transfers, Stock counts, Purchase orders, Goods received, Returns to supplier, Suppliers.

## Transfers between branches (and from the head-office warehouse)
- **requested → approved → on the way → received → closed** (or rejected / cancelled before dispatch).
  A branch asks for stock, the sending branch or head office approves (and can cut quantities), the sender dispatches
  (driver / vehicle / waybill on the delivery note), the receiver counts what arrived.
- Quantities can be entered in cartons / packs; stock always moves in single units.
- Each step happens on the server where it physically happens and the document travels through head office.
  Each server posts its own stock exactly once:
  sender – approval reserves the stock (the till can't sell it), dispatch moves it to *in transit*, the receipt clears what
  arrived from transit (a shortage stays in transit until it is settled – *written off* / *charged* posts it out as a loss,
  *found* clears it);
  receiver – dispatch shows it as *incoming*, receiving books it into the store.
- **Differences** (short / extra) are flagged and must be settled line by line: *write off*, *charge to someone* (with a note),
  or *more turned up* (the receiver books it in). The transfer closes when every difference is settled;
  transfers that arrive complete close by themselves.
- Only head-office catalogue items can move between branches (offer a local product to head office first).

## Stock-takes (blind counts)
- Pick a location and products (or a category); counters see the products but **not** what the system expects.
- On submit the system quantity is taken at that moment (sales during the count are not lost). Lines that differ by more
  than the recount threshold (setting `count_recount_threshold`, default 10 %) are sent back once for a recount.
- The result becomes a stock-count adjustment that a supervisor approves (the existing approval flow).

## Purchasing
- **Suppliers** (code, contact, payment terms, bank details).
- **Purchase orders**: draft → submitted → approved (orders above `po_approval_limit` need *Approve purchase order*;
  0 = every order) → sent → part received → received / closed. Order by carton or piece; printable / PDF.
- **Goods received (GRN)**: against an order or without one; supplier invoice number; free goods (e.g. 12 + 1);
  expiry and batch. Stock goes in, the order's outstanding quantities drop, and the product's cost price is updated
  (head-office products keep this branch's own cost).
- **Returns to supplier**: stock out, linked to the delivery if you choose, with a credit-note reference.
- Suppliers, orders, deliveries and returns sync to head office; head-office suppliers reach every branch.

New permissions: Request / Approve / Dispatch / Receive stock transfer, Allocate stock, View purchasing, Create / Approve
purchase order, Manage suppliers, Post goods receipt, Return to supplier, Count stock.

## Tests
```bash
DB_CONNECTION=mysql DB_DATABASE=vitemart_test ./vendor/bin/pest      # 64 tests
```

---

# Multi-branch, phases 7–8 (head-office reports, backups, upgrades, acceptance) – pilot release

## Install
```bash
php artisan migrate        # 1 new migration (backup_runs, upgrade_runs, damaged quantity on transfer lines)
php artisan optimize:clear
php artisan vitemart:backup --list     # check backups work on this machine (needs mysqldump – see below)
```
`.env` (all optional):
```
VITEMART_BACKUP_AT=23:40              # nightly backup time (backup:run, database only), backup:clean at 23:55
VITEMART_MIN_BRANCH_VERSION=2026.11.0 # head office: branch servers older than this are told to upgrade (HTTP 426)
DB_DUMP_PATH=/Applications/XAMPP/xamppfiles/bin   # where mysqldump / mysql live, if they're not on the PATH
```
The scheduler must run every minute (`php artisan schedule:run` from cron / Task Scheduler) – it already runs sync.

## Head office: All branches (`/hq-dashboard`, sidebar → *All branches*, head office only)
- Today / yesterday / 7 / 30 days / this or last month / this year / own dates; tick branches to compare.
- Per branch: sales, receipts, basket, gross profit and margin, refunds and voids, stock value, low / out of stock,
  transfers in / out; sales per day, sales by branch, best sellers with each branch's share, how customers paid; CSV export.
- **Freshness**: every branch row says how fresh its figures are – *Up to date* (synced in the last 15 min), *Behind*
  (up to 2 h) or *Out of date* / *Never synced* – with the time ("12 Oct 08:40 · 3 hours ago"). A banner above the totals
  says how many branches are behind, so nobody reads yesterday's numbers as today's.

## Backups
- **Nightly**: `backup:run --only-db` at `VITEMART_BACKUP_AT`; old ones are pruned by `backup:clean`.
- **Back up now**: Sync page → *Back up now* (needs *Manage sync*), or `php artisan vitemart:backup`.
- `php artisan vitemart:backup --list` / `--verify=FILE` (checks the file is a complete dump and counts the tables).
- Every backup is recorded; head office's Sync page shows each branch's **last backup** (red when none for 2 days).
- Copy `storage/app/vitemart-backups` (and the nightly zips) off the machine – a USB disk or cloud folder. A backup on
  the same disk as the database doesn't survive a disk failure.

## Restore (`php artisan vitemart:restore FILE` or `--latest`)
safety backup of the current database → maintenance mode → load the backup → migrate → recalculate stock → maintenance off.
On a **branch** it also:
- sends again everything the backup still had waiting (head office recognises what it already has – nothing is doubled);
- moves receipt / document numbers past anything head office has already seen (asks head office; if it can't be reached
  it jumps 1,000 ahead), so a receipt number is never used twice.
Sales made at the branch after the backup are gone from the branch but stay in head-office reports (they were synced).
If the restore fails half-way, the safety backup is loaded back.

## Upgrades (`php artisan vitemart:upgrade`)
1. `git pull` / copy the new release, `composer install --no-dev`, then
2. `php artisan vitemart:upgrade --dry-run` – lists the database changes waiting;
3. `php artisan vitemart:upgrade` – backup → maintenance mode → migrations → checks (main tables answer, the app starts)
   → maintenance off. **If anything fails, the backup is loaded back and the app comes up on the old database**; the run
   is recorded with the reason (`upgrade_runs`).
- Upgrade **head office first**. A branch refuses to run ahead of head office's version (`--force` overrides), and refuses
  while more than 5,000 sync events are waiting.
- Head office rejects branch servers older than `VITEMART_MIN_BRANCH_VERSION` with *upgrade required*; their sales wait
  safely in the outbox until the branch is upgraded.

## Other changes in this release
- **Damaged on arrival**: when receiving a transfer, enter good and damaged quantities; damaged stock goes to the branch's
  quarantine location, not the shelf.
- **Card machine timed out**: on the till, tick *The card machine didn't answer* – the sale completes with the card amount
  *pending* on *Payments to confirm*; a supervisor confirms it (or marks it not received) after checking the terminal.
  The till's automatic retry of the same sale never creates a second one.
- **Two tills selling the last units at the same moment** can no longer both succeed: the stock check reads the latest
  committed balance (it could read a stale snapshot before).
- Head office rejects a change from a branch to another branch's records (sales, stock lines, tills, transfers it isn't
  part of); the branch shows it as a problem on its Sync page instead of retrying for ever.

## Acceptance tests
The 18 acceptance tests from the technical design run as `tests/Feature/AcceptanceTest.php` (1–6, 8–15, 18) and
`tests/Feature/AcceptanceOperationsTest.php` (7: ten parallel tills; 16: restore; 17: failing upgrade). The operations
tests use real processes and a scratch database:
```bash
mysql -e "CREATE DATABASE vitemart_ops"     # scratch copy, wiped by the tests
DB_CONNECTION=mysql DB_DATABASE=vitemart_test VITEMART_OPS_DATABASE=vitemart_ops ./vendor/bin/pest     # 82 tests
```

---

# Pilot checklist (head office + the first branch)

**Let the server check itself first.** Sync page → **Go-live check** (or `php artisan vitemart:preflight`; exit code 1 while
anything is red) checks everything below that a server can see for itself – debug mode, HTTPS, scheduler, mysqldump, recent
backup, enrolment, head office reachable, clock difference with head office, sales stuck in the outbox, catalogue downloaded,
version, VAT, duplicate barcodes, products without a price, negative stock, managers without two-step sign-in, admin accounts
still on a default password, tills, old pending payments and open transfer differences – and says what to do for each.
Items marked *You confirm* (backups copied off the machine, restore drill, card reference rule) are for a person to tick.
It works on the current single-store install too, so run it **before** switching the store over.

`vitemart:install` now backs the database up before it changes a server's role (`--no-backup` to skip); it stops if the backup fails.

**Before go-live – head office (VPS)**
- [ ] HTTPS certificate on the head-office address; `APP_URL` set; `APP_DEBUG=false`.
- [ ] `php artisan vitemart:install --mode=central --org="…"`, migrations run, `php artisan vitemart:status` shows *central*.
- [ ] Cron: `* * * * * php /path/artisan schedule:run`; queue worker if you use one.
- [ ] `php artisan vitemart:backup` works; nightly backup copied off the server (provider snapshot or another disk).
- [ ] Head-office staff have two-step sign-in set up (roles that require it can't sign in until they do).
- [ ] Catalogue checked: prices, VAT (7.5 % inclusive), packs, barcodes; field policies decided (which fields branches may change).

**Before go-live – branch (the current store)**
- [ ] Full backup of the store database taken and copied off the machine **before** switching to branch mode.
- [ ] Enrollment code issued at head office → `php artisan vitemart:install --mode=branch …` on the store server → *Sync* page shows head office.
- [ ] First sync finished (Sync page: nothing waiting, catalogue *up to date*); spot-check 10 products' prices and stock.
- [ ] Tills: each till registered, opening float, receipt printer and scanner tested; cashiers can sign in **only** here.
- [ ] Card terminal reference rule decided (`card_needs_terminal_ref`); supervisors know *Payments to confirm*.
- [ ] Nightly backup time set; *Back up now* tried once; backups copied off the machine (USB / cloud folder).

**Pilot week – daily**
- [ ] Head office *All branches*: branch shows *Up to date* during trading hours; totals match the store's Z-report.
- [ ] Sync page at the branch: *Problems* = 0; anything *dead* is looked at the same day.
- [ ] *Payments to confirm* emptied by close; transfers/card timeouts resolved.
- [ ] Unplug the internet for 15 minutes once: tills keep selling; after reconnecting the queue empties within minutes.
- [ ] One transfer head office → branch, with one short and one damaged item, settled end to end.
- [ ] One stock count on a shelf, approved, the adjustment checked.

**Pilot week – once**
- [ ] Restore drill on a spare machine / copy: `php artisan vitemart:restore --latest` – app starts, sync resumes, no duplicate receipts.
- [ ] Upgrade drill: `vitemart:upgrade --dry-run` then `vitemart:upgrade` on head office, then the branch.

**Go / no-go for the next branch**: a full week with no lost or doubled sales at head office, the daily totals matching,
no sync problem older than a day, and a successful restore drill.
