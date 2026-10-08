# Bug-fix status / handoff

Last updated: 2026-10-08.

- `fix/otp-budget-numbers`: merged into `main` and pushed (`64807a4`).
- `fix/money-integrity`: the backlog below, committed locally. Not pushed, not merged.

## Client complaints and outcome

| Complaint | Root cause | Status |
|---|---|---|
| Amounts like `262145427,963.00` | On Linux, PHP's standard extension already defines a `CURRENCY_SYMBOL` constant (an `nl_langinfo` item, value `262145`). `define('CURRENCY_SYMBOL', ...)` silently failed, so every amount was printed as `262145` + number. Windows/XAMPP has no such constant, so it never reproduced locally. | **Fixed and on `main`**: renamed to `APP_CURRENCY_SYMBOL` (`config/config.php`). |
| No OTP at sign-in | Commit `6476768` skips the two-step code in production when `MAIL_USERNAME`/`MAIL_PASSWORD` are unset. HostForge has no SMTP settings. | **Needs config**: set the MAIL_* environment variables on HostForge (Gmail needs a 16-char App Password) and redeploy. Settings → Email card shows the status and has a "Send test email to me" button that shows the real SMTP error. |
| Reset password does nothing | Same cause: no mail. The page used to promise a code that was never sent. | **Fixed in UI**: it now says reset is unavailable. Works once mail is configured. |
| Can't add budget / numbers messed up | Blank 500 page on any DB error; `type=number` grid silently blocked submits (`1,500`, `1500.555`); mouse wheel changed focused inputs; bill/invoice lines with a blank description were silently dropped. | **Fixed and on `main`**. |

## Deploy checklist

1. Confirm the HostForge build of `main` @ `64807a4` finished (the 20-minute build cap has been hit before).
2. Set `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_EMAIL` and redeploy. Then use Settings → Send test email.
3. When `fix/money-integrity` is reviewed: merge into `main`, push, repeat step 1.

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

## How to test

There is no usable local MySQL (local MariaDB root uses Windows GSSAPI auth). Use Docker:

```
docker compose up -d --build          # app on http://localhost:8080, seeded, APP_ENV=production
cd tests/e2e && npm i && node e2e.js   # headless Chrome, prints PASS/FAIL, screenshots in tests/e2e/shots/
docker compose down                    # add -v to wipe DB
```

Demo login: `admin` / `Passw0rd!` (OTP is skipped because compose has no mail). Last run: all 40 checks pass, no JS errors, and the whole ledger balances. Step 9 sends forged POSTs (same session and CSRF token) to prove the server rejects what the browser's `min` would block, and checks the DB directly through `docker compose exec db mariadb`. Rebuild the image after code edits (the code is copied into the image, not mounted).

## Still open / known limits

- Voiding a PartiallyPaid bill or invoice reverses its GL entry but leaves the payments that were applied to it. That behaviour existed before this branch. Deciding what should happen to those payments is a business decision.
- `next_document_no()` / `generate_entry_no()` count rows to build the next number. Two simultaneous saves can still collide on the UNIQUE number. The transaction now rolls back cleanly and the user sees an error, but the numbering should move to a sequence table.
- Amount inputs in AP/AR/CR/DV/JE are still `type=number`, so the browser blocks `1,500` before submit. The server accepts it. Switch them to text inputs with `inputmode="decimal"`, as was done for the budget grid, if users complain.

## Gotchas for whoever continues

- PHP sources are CRLF in the working tree and LF in the repo (`autocrlf=true`). Git-Bash `sed -i` strips the CRs, which is harmless (git normalises), but don't mix endings inside one file.
- Inline page `<script>` blocks run **before** `main.js` (loaded in the footer). Shared JS helpers must live in `includes/header.php`.
- Never name a constant after a PHP built-in. Check with `php -r 'var_dump(defined("NAME"));'` **inside the Linux container**, not on Windows.
- Never call `redirect()` while a transaction is open: it `exit`s before the commit and the work is rolled back. Commit first, then flash and redirect.
- From Git-Bash, `docker compose exec app php -l /var/www/...` needs `MSYS_NO_PATHCONV=1`, or the path gets rewritten to a Windows path.
