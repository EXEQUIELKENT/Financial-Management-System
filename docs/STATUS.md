# Bug-fix status / handoff

Last updated: 2026-10-08.

- `fix/otp-budget-numbers`: merged into `main` and pushed (`64807a4`).
- `fix/money-integrity`: merged into `main` and pushed (`1dd5554`).
- `fix/void-numbering-inputs`: gap-free numbering, voiding payments, amount inputs. Merged into `main` (see git log).

## Client complaints and outcome

| Complaint | Root cause | Status |
|---|---|---|
| Amounts like `262145427,963.00` | On Linux, PHP's standard extension already defines a `CURRENCY_SYMBOL` constant (an `nl_langinfo` item, value `262145`). `define('CURRENCY_SYMBOL', ...)` silently failed, so every amount was printed as `262145` + number. Windows/XAMPP has no such constant, so it never reproduced locally. | **Fixed and on `main`**: renamed to `APP_CURRENCY_SYMBOL` (`config/config.php`). |
| No OTP at sign-in | Commit `6476768` skips the two-step code in production when `MAIL_USERNAME`/`MAIL_PASSWORD` are unset. HostForge has no SMTP settings. | **Needs config**: set the MAIL_* environment variables on HostForge (Gmail needs a 16-char App Password) and redeploy. Settings → Email card shows the status and has a "Send test email to me" button that shows the real SMTP error. |
| Reset password does nothing | Same cause: no mail. The page used to promise a code that was never sent. | **Fixed in UI**: it now says reset is unavailable. Works once mail is configured. |
| Can't add budget / numbers messed up | Blank 500 page on any DB error; `type=number` grid silently blocked submits (`1,500`, `1500.555`); mouse wheel changed focused inputs; bill/invoice lines with a blank description were silently dropped. | **Fixed and on `main`**. |

## Deploy checklist

1. Confirm the HostForge build of the latest `main` finished (the 20-minute build cap has been hit before).
2. `DB_AUTO_MIGRATE=true` must stay on: on boot, `scripts/migrate.php` now applies idempotent upgrades to an existing database (adds `document_sequences`, and `status`/`voided_at`/`void_reason` on `ap_payments` and `ar_receipts`). Check the boot log for `Upgrade:` lines or `Upgrades checked.`
3. Set `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_EMAIL` and redeploy. Then use Settings → Send test email.

## `fix/money-integrity`: what changed

Shared helpers:

- `includes/Ledger.php`
  - `db_transaction()` runs a callback in a transaction, or joins one that is already open.
  - `post_journal_entry()` and `create_draft_journal_entry()` now join the caller's transaction, so a form can wrap its document rows, the GL entry and the cash balance in one transaction. Before, a failure halfway left a bill marked Paid with no GL entry.
  - `validate_journal_lines()` rejects lines with no account (usually a control account missing from Settings), negative amounts, both a debit and a credit, or an unbalanced total. It runs on post, on draft creation and on Draft → Post.
  - Void reversals net each original line, so older bad entries can still be voided.
  - `apply_to_open_documents('ap'|'ar', $partyId, [id => amount], $dryRun)` locks each bill or invoice and checks that it belongs to the vendor or customer, is Open or PartiallyPaid, and has enough unpaid balance. Then it updates the paid amount and status. Forms call it with `$dryRun = true` to show errors, and again inside the transaction to apply.
- `includes/functions.php`: `parse_nonnegative_amount()` and `parse_amount_map()` replace `(float)$_POST[...]`.

Per handler:

- AP payment: WHT must be ≥ 0 and needs a tax type and a configured WHT payable account; control account is checked; one transaction. Also fixed a JS error on the page before a vendor is picked.
- AR receipt: same overpayment and party checks; one transaction.
- Collection / Disbursement: invoice/bill lines are checked when created. Deposit/Pay locks the document row and re-checks status, which stops a double submit from posting twice, and applies amounts through the helper. The header total is rounded from the rounded lines.
- Bill/Invoice approve: allowed only from Draft, with a row lock, so approving twice no longer posts twice. Void is allowed only from Open/PartiallyPaid. Both run in one transaction.
- Journal entry form: rejects negatives, lines with both sides, and inactive or missing accounts.
- Cash account: opening balance must be ≥ 0. It is posted to the GL (Dr. linked account, Cr. the chosen equity account, reference `OPEN-<id>`). Edits still can't change it.
- Cash transaction/transfer: amounts parsed, accounts must be active, one transaction. Transaction type is whitelisted.
- Tax remittance: direction whitelisted to `Output`/`Withholding`. Only rows still Pending are claimed. Payable account must be configured. One transaction.
- Reconciliation and tax type: numbers validated (tax rate 0–100).
- `min="0"` on the non-negative amount inputs.

## `fix/void-numbering-inputs`: what changed

- **Numbering.** `next_document_no()` (`includes/Ledger.php`) uses a counter row per prefix and year in `document_sequences`, locked inside the saving transaction. Numbers are unique under concurrent saves, and a failed save rolls the counter back, so there are no gaps (BIR expects unbroken invoice/receipt series). The counter never falls below the highest existing number, so seeded and older data are safe. Journal entry numbers use the same counter (`JE`).
- **Voiding follows the usual AP/AR rule**: a bill or invoice with money applied can't be voided. Void the payments first. That reopens the document, and then it can be voided.
  - AP Payments and AR Receipts lists have a **Void** action (needs `ap.approve` / `ar.approve`, plus a reason). `void_payment()` reverses the entry and the cash movement (`void_posting()`), takes the amounts off the bills or invoices (back to Open/PartiallyPaid), voids withholding tax still Pending, and marks the payment Void. Withholding tax that was already remitted blocks the void.
  - A payment created by a disbursement voucher or collection receipt is voided by voiding that voucher or receipt. Paid vouchers and Deposited receipts can now be voided, and that reverses everything.
  - Voiding a bill or invoice also voids its Pending input/output tax rows. If the output tax was already remitted, the void is blocked and the message says to issue a credit note.
- **Amount inputs** in AP/AR/CR/DV/JE, cash, reconciliation and bill/invoice unit price are text inputs (`class="money"`, `inputmode="decimal"`). They accept `1,500` and tidy to `1,500.00` on blur. Shared `parseMoney()` and the blur/focus handlers are in `includes/header.php`. Quantities and day counts stay numeric.

## How to test

There is no usable local MySQL (local MariaDB root uses Windows GSSAPI auth). Use Docker:

```
docker compose up -d --build          # app on http://localhost:8080, seeded, APP_ENV=production
cd tests/e2e && npm i && node e2e.js   # headless Chrome, prints PASS/FAIL, screenshots in tests/e2e/shots/
docker compose down                    # add -v to wipe DB
```

Demo login: `admin` / `Passw0rd!` (OTP is skipped because compose has no mail). Last run: all 49 checks pass (three runs in a row), no JS errors, and the whole ledger balances. Step 10 saves 9 bills at once from 3 separate sessions and checks the numbers are unique and consecutive. Step 9 sends forged POSTs (same session and CSRF token) to prove the server rejects what the browser's `min` would block, and checks the DB directly through `docker compose exec db mariadb`. Rebuild the image after code edits (the code is copied into the image, not mounted).

## Still open / known limits

- No credit/debit notes yet. A posted invoice whose output tax was already remitted can't be voided, and there is no way yet to correct it.
- `sql/schema-with-seed-data.sql` (the phpMyAdmin dump for XAMPP) predates the new table and columns. After importing it, run `php scripts/migrate.php` once to apply the upgrades.
- Draft bills, invoices and journal entries use up a number when saved. There is no delete, so the series has no gaps. If a delete is ever added, it should void instead.

## Gotchas for whoever continues

- PHP sources are CRLF in the working tree and LF in the repo (`autocrlf=true`). Git-Bash `sed -i` strips the CRs, which is harmless (git normalises), but don't mix endings inside one file.
- Inline page `<script>` blocks run **before** `main.js` (loaded in the footer). Shared JS helpers must live in `includes/header.php`.
- Never name a constant after a PHP built-in. Check with `php -r 'var_dump(defined("NAME"));'` **inside the Linux container**, not on Windows.
- Number sequences: lock with `INSERT ... ON DUPLICATE KEY UPDATE`, not `INSERT IGNORE` followed by `SELECT ... FOR UPDATE`. The second deadlocks under concurrent saves (the E2E step 10 caught it).
- Never call `redirect()` while a transaction is open: it `exit`s before the commit and the work is rolled back. Commit first, then flash and redirect.
- From Git-Bash, `docker compose exec app php -l /var/www/...` needs `MSYS_NO_PATHCONV=1`, or the path gets rewritten to a Windows path.
