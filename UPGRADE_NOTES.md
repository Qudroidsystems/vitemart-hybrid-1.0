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
