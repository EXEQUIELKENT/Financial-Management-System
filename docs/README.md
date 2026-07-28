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
