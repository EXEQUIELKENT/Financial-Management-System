# Bug-fix status / handoff

Last updated: 2026-10-08. Branch: `fix/otp-budget-numbers` (not pushed, not merged into `main`).

## Client complaints and outcome

| Complaint | Root cause | Status |
|---|---|---|
| Amounts like `262145427,963.00` | On Linux, PHP's standard extension already defines a `CURRENCY_SYMBOL` constant (an `nl_langinfo` item, value `262145`). `define('CURRENCY_SYMBOL', ...)` silently failed, so every amount was printed as `262145` + number. Windows/XAMPP has no such constant, so it never reproduced locally. | **Fixed**: renamed to `APP_CURRENCY_SYMBOL` (`config/config.php`). Verified in the Docker image. |
| No OTP at sign-in | Commit `6476768` skips the two-step code in production when `MAIL_USERNAME`/`MAIL_PASSWORD` are unset. HostForge has no SMTP settings. | **Needs config**: set the MAIL_* environment variables on HostForge (Gmail needs a 16-char App Password) and redeploy. Settings → Email card shows the status and has a "Send test email to me" button that shows the real SMTP error. |
| Reset password does nothing | Same cause: no mail. The page used to promise a code that was never sent. | **Fixed in UI**: it now says reset is unavailable. Works once mail is configured. |
| Can't add budget / numbers messed up | Blank 500 page on any DB error; `type=number` grid silently blocked submits (`1,500`, `1500.555`); mouse wheel changed focused inputs; bill/invoice lines with a blank description were silently dropped. | **Fixed** (see the commit list below). |

## Done (commits on the branch)

- `ac4aae6`: budget validation and parsing (`parse_amount()`, `MAX_AMOUNT` in `includes/functions.php`), Draft-only edits scoped to the budget's own lines, live totals; bill/invoice line validation; global production exception page; wheel guard and `formatMoney()` in `includes/header.php`; `budget_ytd_totals()` / `budget_months_through()` shared by the dashboard, Assistant and DecisionEngine (expense-only, period-limited); variance report month mapping and revenue sign; mail status card; MAIL_PASSWORD whitespace strip; KPI cards stay on one line.
- Next commit: currency constant rename, plus `tests/e2e/` and this file.

## Deploy checklist

1. Merge `fix/otp-budget-numbers` into `main` and push.
2. Confirm the HostForge build finished (the 20-minute build cap has been hit before).
3. Set `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_EMAIL` and redeploy. Then use Settings → Send test email.

## How to test

There is no usable local MySQL (local MariaDB root uses Windows GSSAPI auth). Use Docker:

```
docker compose up -d --build          # app on http://localhost:8080, seeded, APP_ENV=production
cd tests/e2e && npm i && node e2e.js   # headless Chrome, prints PASS/FAIL, screenshots in tests/e2e/shots/
docker compose down                    # add -v to wipe DB
```

Demo login: `admin` / `Passw0rd!` (OTP is skipped because compose has no mail). Last run: all 21 checks pass, no JS errors. Rebuild the image after code edits (the code is copied into the image, not mounted).

## Backlog: found, not fixed (ranked)

1. **AP payment WHT can unbalance the GL**: `modules/ap/payment-form.php:20,27,62-74`. `withheld_amount` isn't checked (it can be negative, or set with no `withholding_tax_payable_account_id`), so the journal entry throws after `ap_payments` and the bill are already updated, leaving the bill Paid with no GL entry. Fix: require `withheld >= 0` and a configured account; wrap the whole handler in one transaction.
2. **No outer transaction around multi-table money writes**: `modules/ar/receipt-form.php:36-62`, `modules/cash/transaction-form.php:38-45`, `modules/cash/transfer-form.php`, `modules/tax/remittance-form.php:33-55`. Fix: `beginTransaction()` around all writes; make `post_journal_entry` (includes/Ledger.php) join an already-open transaction (`$db->inTransaction()`).
3. **Overpayment / foreign IDs**: `modules/ar/receipt-form.php:21-23`, `modules/ap/payment-form.php:24-26`, `modules/collection/receipt-form.php:21-23`, `modules/disbursement/voucher-form.php:21-23`. Invoice/bill IDs aren't checked against the chosen customer/vendor, open status or remaining balance, so a ₱10,000 payment can go on a ₱1,000 invoice. Fix: re-select `WHERE id=? AND customer_id=? AND status IN ('Open','PartiallyPaid')` and cap the amount at the balance.
4. **Journal entry form** `modules/gl/journal-entry-form.php:25-31`: accepts negative debits/credits and lines with both a debit and a credit (debit 200, debit -100, credit 100 counts as "balanced"). Fix: reject values below 0 and lines where both sides are above 0; check the accounts are active.
5. **Header totals from unrounded floats**: `modules/collection/receipt-form.php:33`, `modules/disbursement/voucher-form.php`. Fix: `parse_amount()` per line, round, then sum.
6. **Cash opening balance** `modules/cash/account-form.php:26,40`: written to `current_balance` with no GL entry, negatives accepted, ignored on edit. Fix: post an opening-balance journal entry (or lock the field) and require `>= 0`.
7. **Tax remittance direction** `modules/tax/remittance-form.php:14`: not whitelisted. Fix: allow only `Output` and `Withholding`.
8. **Everywhere else**: `(float)$_POST[...]` on money fields. Replace with `parse_amount()` and reject `null`; add `min="0"` to amount inputs.

## Gotchas for whoever continues

- PHP sources are CRLF in the working tree and LF in the repo (`autocrlf=true`). Git-Bash `sed -i` strips the CRs, which is harmless (git normalises), but don't mix endings inside one file.
- Inline page `<script>` blocks run **before** `main.js` (loaded in the footer). Shared JS helpers must live in `includes/header.php`.
- Never name a constant after a PHP built-in. Check with `php -r 'var_dump(defined("NAME"));'` **inside the Linux container**, not on Windows.
