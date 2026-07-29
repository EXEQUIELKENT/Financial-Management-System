# Intelligent Integrated Financial Management System

Design and Development of an Intelligent Integrated Financial Management System with AI Financial Assistance, Predictive Analysis, and Decision Support for Travel and Tour Agencies.

Plain PHP + MySQL (PDO), no framework required. Built to run directly on XAMPP.

## Setup

1. **Start Apache and MySQL** in the XAMPP Control Panel.
2. **Create the database and import the schema**:
   - Open phpMyAdmin (`http://localhost/phpmyadmin`).
   - Import `sql/schema.sql` (it creates the `travelcore_fms` database and all tables, plus roles/permissions/default settings).
   - Or via CLI: `mysql -u root < sql/schema.sql`
3. **Check `config/config.php`** if your MySQL credentials differ from the XAMPP defaults (`root` / no password).
4. **Seed demo data** (recommended for first run): open
   `http://localhost/Financial%20Management%20System/sql/seed.php`
   in your browser. This creates 4 demo users, a chart of accounts, vendors/customers, an approved budget, and ~7 months of realistic AR/AP/cash activity — all posted through the same ledger engine the app uses, so the books balance from the start.
   - Re-run with `?reset=1` to wipe and regenerate business data.
5. **Log in** at `http://localhost/Financial%20Management%20System/login.php`.

### Demo accounts (after seeding)

| Username     | Password    | Role                    |
|--------------|-------------|-------------------------|
| admin        | Passw0rd!   | Admin (full access)     |
| accountant   | Passw0rd!   | Accountant / Finance Officer (create, no approve) |
| approver     | Passw0rd!   | Approver / Manager (approvals, reports) |
| auditor      | Passw0rd!   | Auditor (view-only + audit log) |

## How the System Works (Process Flow & User Guide)

This section explains the system end-to-end for someone operating it for the first time: the big picture, what each role is allowed to do, and the step-by-step flow for every module.

### The Big Picture

Every module you see in the sidebar feeds into one place: the **General Ledger (GL)**. You never post to the GL directly in day-to-day work — instead, you create documents (a Bill, an Invoice, a Disbursement Voucher...) in the module that matches what actually happened, and the system posts the accounting entry to the GL automatically the moment that document is approved. This means:

- The GL is always a byproduct of real business documents, never typed in free-hand (except for rare manual adjustments in General Ledger → Journal Entries).
- Every number on the Dashboard, the Aging Reports, and the Financial Statements is derived live from those same GL postings — nothing is a separate, disconnected "report database."
- Because of this, the **Trial Balance always balances** (total debits = total credits) as long as documents flow through the normal approve/pay/deposit buttons instead of being edited directly in the database.

Almost every transactional document in this system follows the same lifecycle:

```
Draft  →  Pending Approval  →  Approved / Posted  →  Paid / Deposited
                 (or Rejected / Void at any point before completion)
```

Two different people are normally involved: one person **creates** the document (the "maker"), and a different person **approves** it (the "checker"). This is enforced by the system, not just a suggestion — see "Maker-Checker" below.

### Roles: What Each Can Do

The system has four roles. A user's role decides which sidebar items they see and which buttons appear on each page — if you don't have permission for something, the button simply won't be there.

| Role | Can view | Can create/edit (Draft) | Can approve / post / pay | Typical use |
|---|---|---|---|---|
| **Admin** | Everything | Everything | Everything, including their own submissions | System owner / IT admin. Also the only role that can manage Users and Settings. |
| **Accountant** (Finance Officer) | Everything they create | Bills, Invoices, Journal Entries, Disbursement Vouchers, Collection Receipts, Budgets, Cash transactions/transfers, Tax records | Nothing — no approve buttons appear anywhere | Day-to-day data entry: recording bills, invoices, and requesting payments/collections. |
| **Approver** (Manager) | Everything | Nothing — no "New..." buttons appear | Bills, Invoices, Journal Entries, Disbursement Vouchers, Collection Receipts, Budgets, Tax Remittances | Reviews and approves what the Accountant submitted; the one who actually authorizes money to move. |
| **Auditor** | Everything, read-only, plus the Audit Log | Nothing | Nothing | Independent review — can see every screen and the full audit trail, but cannot change anything. |

Because Accountant and Approver are deliberately split this way, **no single non-Admin user can both create and approve the same transaction** — this is the segregation of duties a real finance department expects.

### Maker-Checker (Segregation of Duties)

On top of the role split above, the system has one more safeguard: **even a user who technically holds both permissions cannot approve a document they personally created.** If you try, you'll see: *"Segregation of duties: you cannot approve/post/void a [bill/invoice/entry] you created yourself."* The Admin role is the one exception (an Admin can approve their own entries), since Admin is meant for setup/testing and already has unrestricted access.

### Before You Record Any Transactions: One-Time Setup

Do this once (already done for you if you ran `sql/seed.php` — see Setup above):

1. **Chart of Accounts** (General Ledger → Chart of Accounts) — every account the business uses (Cash, AP, AR, Revenue, Expense accounts, etc.), each tagged Asset/Liability/Equity/Revenue/Expense with a Debit or Credit normal balance.
2. **GL Control Accounts** (Settings, Admin only) — tell the system which GL account represents Accounts Payable, Accounts Receivable, Input Tax, Output Tax, and Withholding Tax Payable. These are what auto-posted journal entries use — bill/invoice approval will show an error until these are set.
3. **Cash/Bank Accounts** (Cash Management → Accounts) — each one links to a Chart of Accounts GL asset account and holds a running balance.
4. **Tax Types** (Tax Management → Tax Types) — e.g. VAT 12%, Withholding Tax 2%.
5. **Vendors** (Accounts Payable → Vendors) and **Customers** (Accounts Receivable → Customers).
6. **Budget Period + Budget** (Budget Management) — optional but needed before the Dashboard's Budget Utilization KPI or the Decision Support budget-overrun alert will show anything.

### Accounts Payable — Paying a Vendor for Something the Agency Bought

1. **Accountant** creates a **Bill** (Accounts Payable → Bills → New Bill): pick the vendor, add line items against expense/asset accounts, apply tax if applicable. Saves as **Draft**.
2. **Approver** opens the bill and clicks **Approve & Post** → status becomes **Open**, and the system posts Dr. Expense (+ Input Tax) / Cr. Accounts Payable to the GL. (Approver cannot approve a bill the same Approver account created.)
3. When it's time to pay, **Accountant** or **Approver** goes to Accounts Payable → Payments → New Payment, picks the vendor, selects which open bill(s) to pay (full or partial), picks the cash/bank account, and optionally applies a Withholding Tax deduction. Recording the payment posts Dr. Accounts Payable / Cr. Cash to the GL and updates the bill to **Partially Paid** or **Paid**.
4. The **AP Aging Report** (Accounts Payable → Aging Report) buckets everything still unpaid into Current / 1-30 / 31-60 / 61-90 / 90+ days overdue.

### Accounts Receivable — Getting Paid by a Customer

Mirrors Accounts Payable exactly, in the other direction:

1. **Accountant** creates an **Invoice** (Accounts Receivable → Invoices → New Invoice) against a customer and revenue account(s). **Draft**.
2. **Approver** clicks **Approve & Post** → status **Open**; posts Dr. Accounts Receivable / Cr. Revenue (+ Output Tax) to the GL.
3. When the customer pays, record a **Receipt** (Accounts Receivable → Receipts → New Receipt), applying it against the open invoice(s). Posts Dr. Cash / Cr. Accounts Receivable and marks the invoice **Partially Paid** or **Paid**.
4. The **AR Aging Report** shows the same overdue buckets for money customers still owe.

### Disbursement Management — Controlled Cash Payouts (vouchers)

Use this instead of a plain AP Payment when a payout needs a formal approval trail before the money moves — e.g. paying an employee cash advance, or settling a vendor bill through a proper voucher rather than a direct payment.

1. **Accountant** creates a **Disbursement Voucher** (Disbursement Mgmt. → New Voucher): choose the payee (Vendor / Employee / Other), optionally check off open AP bills to settle and/or add ad hoc expense lines, pick the paying cash account. Submitting sends it straight to **Pending Approval**.
2. **Approver** reviews it in the **Approval Queue** and clicks **Approve** or **Reject** (with comments logged to the voucher's history).
3. Once Approved, **Approver** (or anyone with disbursement-approve rights) clicks **Mark Paid** — this posts the GL entry, deducts the cash account balance, and — if bills were selected — automatically creates the matching AP payment and updates those bills' status.
4. A printable voucher slip is available once Paid (top-right **Print** button), with signature lines for Requested By / Approved By.

### Collection Management — Controlled Cash Collections (receipts)

The mirror of Disbursement, for money coming in that needs the same approval trail:

1. **Accountant** creates a **Collection Receipt** (Collection Mgmt. → New Collection Receipt): payer (Customer/Other), apply against open AR invoices and/or add ad hoc lines (e.g. miscellaneous income), pick the deposit account. Goes to **Pending Approval**.
2. **Approver** reviews it in the Approval Queue → **Approve** or **Reject**.
3. Once Approved, click **Mark Deposited** — posts the GL entry, adds to the cash account balance, and auto-creates the matching AR receipt if invoices were applied.
4. Printable receipt slip available once Deposited.

### Budget Management

1. **Admin/Accountant** creates a **Budget Period** (e.g. "FY2026") and, within it, a **Budget** (Budget Management → New Budget), optionally scoped to a department.
2. Add account lines to the budget and fill in a monthly amount for each of the 12 months (Expense or Revenue accounts).
3. **Approver** clicks **Approve** on the budget — only Approved budgets feed the Variance Report and the Dashboard's Budget Utilization KPI.
4. **Variance Report** (Budget Management → Variance Report) compares year-to-date Budgeted vs. Actual (pulled live from posted GL activity) per account, with a color-coded utilization badge (green under 90%, amber 90-100%, red over 100%).

### Cash Management

- **Accounts**: set up each Cash on Hand / Bank / Petty Cash account, each linked to a GL asset account. The **Register** view (per account) shows every transaction with a running balance.
- **Transactions**: record a manual deposit/withdrawal not tied to AP/AR (e.g. bank charges, interest earned) against an offset account — posts the GL entry directly.
- **Transfers**: move money between two of the agency's own cash/bank accounts — posts a Dr/Cr entry between the two linked GL accounts and updates both balances.
- **Reconciliation**: start a reconciliation against a bank statement (statement date + balance), add reconciling items (outstanding checks, deposits in transit, bank charges, interest, corrections), and mark it Completed once the adjusted bank balance and adjusted book balance match.
- **Cash Position**: a consolidated view of every account's balance plus recent activity across all of them — this total is also what feeds the Dashboard's Cash Position KPI and the cash-runway Decision Support rule.

### Tax Management

- **Tax Types**: define each tax (e.g. VAT 12%, Withholding Tax 2%) and its rate.
- **Tax Transactions**: created *automatically* — every AP bill/AR invoice tax line generates one (Input tax for bills, Output tax for invoices), and every AP payment with withholding applied generates a Withholding one. You don't create these by hand.
- **Remittance**: when it's time to pay the government, go to Tax Management → Remittances → New Remittance, pick the tax type/direction and a date range — the system aggregates every matching Pending tax transaction, marks them Remitted, and posts Dr. Tax Payable / Cr. Cash.
- **Compliance Summary**: totals collected vs. remitted vs. still pending, by tax type and direction.

### Financial Reporting & Analytics

All reports read live from posted GL activity — nothing to "run" or "close" beforehand:

- **Trial Balance** — every account's debit/credit movement as of a date; tells you at a glance whether the books are in balance.
- **Income Statement** — Revenue minus Expenses for a date range, with Net Income.
- **Balance Sheet** — Assets = Liabilities + Equity (including current earnings) as of a date.
- **Cash Flow Statement** — Operating/Investing/Financing cash movement for a date range, with beginning and ending cash balances.
- **Custom / GL Detail Report** — pick any specific accounts and a date range for a raw transaction listing.
- Every report has an **Export CSV** option.

### The Dashboard & the "AI" Features

- **KPI cards**: Cash Position, Accounts Receivable, Accounts Payable, and Budget Utilization — all live totals.
- **Decision Support**: color-coded alert cards that automatically appear when a threshold is crossed (low cash runway, AP/AR aging risk, budget overrun, insufficient cash for upcoming bills, a declining revenue trend, or overdue tax remittances). Each links straight to the relevant report. Thresholds are editable under Settings (Admin only).
- **Predictive Analysis chart**: a 3-month moving average plus a linear-regression trend line, projecting the next 3 months of cash flow from the last 12 months of actual activity. Needs at least 4 months of posted history to appear.
- **AI Financial Assistant**: click one of the suggested questions (or type your own close to one of them) — e.g. "What is our current cash position?" — and it computes a real, current answer from the database. It recognizes a fixed set of financial questions (cash position, AP/AR totals, net income, overdue vendors, budget utilization, cash flow forecast); it is not a general chatbot, so questions outside that set will get a fallback response listing what it can answer.

### A Suggested First Session

If you're trying this system for the first time after seeding demo data:

1. Log in as **admin**, check Settings to see how the GL Control Accounts are wired up.
2. Log in as **accountant**, create one new Bill and one new Invoice (Draft).
3. Log in as **approver**, approve both from their respective Approval/Bills/Invoices screens, and confirm you can't approve anything *you* just created if you switch and try it as the same approver twice.
4. Record a Payment against the bill and a Receipt against the invoice.
5. Check the **Dashboard** — the KPIs, chart, and any Decision Support cards should reflect what you just did.
6. Open **General Ledger → Journal Entries** and find the entries your actions just created — this is the best way to see the "one document, one GL posting" relationship in action.
7. Log in as **auditor** to see the same data in read-only form, plus the **Audit Log** recording every action everyone above just took.

## Branding

- Colors and typography live in `assets/css/variables.css` (CSS custom properties) and are already set to the project's palette (`#2F80ED` primary, `#56CCF2` secondary, `#27AE60` accent, Poppins font).
- The circular "TravelCore" logo badge is rendered as an inline SVG placeholder (`includes/logo-placeholder.php`) until you supply the real artwork. To use the real logo: save the PNG file as `assets/images/logo.png` — every page already checks for that file first and will pick it up automatically, no code changes needed.

## Structure

- `config/` — app config and PDO database connection
- `includes/` — shared auth/RBAC, layout partials, the GL posting engine (`Ledger.php`), the predictive-analysis engine (`Forecast.php`), the rule-based recommendation engine (`DecisionEngine.php`), and the AI Financial Assistant (`Assistant.php`)
- `modules/` — one folder per module: `gl`, `ap`, `ar`, `disbursement`, `collection`, `budget`, `cash`, `tax`, `reports`, `dashboard`, plus `users` / `settings` / `audit` (admin-only)
- `api/` — small JSON endpoints used by the dashboard's Chart.js graphs and the AI Assistant panel
- `sql/schema.sql` — full database schema; `sql/seed.php` — demo data generator

## Notes on the "AI" layer

Per project scope, the Predictive Analysis, Decision Support, and AI Financial Assistant features are self-contained (no external API keys, no internet dependency):

- **Predictive Analysis**: a 3-month moving average plus a least-squares linear regression projected 3 months forward, computed in `includes/Forecast.php` from posted GL/cash data, rendered with Chart.js (loaded from a CDN — vendor a local copy under `assets/vendor/` and swap the `<script src>` in `modules/dashboard/index.php` if the server has no internet access).
- **Decision Support**: threshold/ratio rules in `includes/DecisionEngine.php` (cash runway, AP/AR aging risk, budget overrun, upcoming payables vs. cash on hand, revenue trend, tax remittance reminders). Thresholds are editable by Admin under Settings.
- **AI Financial Assistant**: keyword-matched canned questions in `includes/Assistant.php`, answered by querying the live database — not a general-purpose chatbot, but always factually grounded in current data.

## GL Control Accounts

Under **Settings** (Admin only), map the GL accounts the system posts to automatically: Accounts Payable, Accounts Receivable, Input Tax, Output Tax, and Withholding Tax Payable. These are pre-configured by `seed.php`; if you start from a blank chart of accounts instead, set them here before approving any bills/invoices.
