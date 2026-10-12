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
  sender – approval reserves the stock (the till can't sell it), dispatch moves it to *in transit*, the receipt clears transit;
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
