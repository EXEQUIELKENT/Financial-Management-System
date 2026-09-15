<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('dashboard.view');

// The Getting Started tour is an onboarding aid, hidden on a deployed site. The
// sidebar entry is gone there too, so this only catches a bookmark or a typed URL.
if (!SHOW_GUIDES) {
    redirect('modules/dashboard/index.php');
}

$user = current_user();
$role = $user['role_name'];

$roleBlurbs = [
    'Admin' => 'Full access to every module, plus Users and Settings. Can also approve things it created itself (the one exception to maker-checker).',
    'Accountant' => 'Creates and edits Draft records (Bills, Invoices, Journal Entries, Vouchers, Receipts, Budgets, Cash entries). Cannot approve anything — no approve buttons will appear.',
    'Approver' => 'Reviews and approves what an Accountant submitted (Bills, Invoices, Journal Entries, Vouchers, Receipts, Budgets, Tax Remittances). Cannot create new Draft records.',
    'Auditor' => 'Read-only access to every screen in the system, plus the Audit Log. Cannot create, edit, or approve anything.',
];
$roleBlurbsTl = [
    'Admin' => 'May access ka sa lahat ng module, pati na rin sa Users at Settings. Ikaw lang ang maaaring mag-approve ng sarili mong ginawang transaksyon.',
    'Accountant' => 'Ikaw ang gumagawa at nag-e-edit ng mga Draft na dokumento (Bills, Invoices, Journal Entries, Vouchers, Receipts, Budgets, Cash entries). Hindi ka maaaring mag-approve — wala kang makikitang Approve button kahit saan.',
    'Approver' => 'Ikaw ang nagre-review at nag-a-approve ng mga isinumite ng Accountant (Bills, Invoices, Journal Entries, Vouchers, Receipts, Budgets, Tax Remittances). Hindi ka gumagawa ng bagong Draft na dokumento.',
    'Auditor' => 'View-only access sa lahat ng screen sa sistema, kasama ang Audit Log. Hindi ka maaaring gumawa, mag-edit, o mag-approve ng kahit ano.',
];
$canAp = has_permission('ap.create');
$canAr = has_permission('ar.create');
$canDv = has_permission('disbursement.create');
$canCr = has_permission('collection.create');
$canBudget = has_permission('budget.create');
$canCash = has_permission('cash.create');
$canTax = has_permission('tax.create');
$canUsers = has_permission('users.view');
$canSettings = has_permission('settings.view');
$canAudit = has_permission('audit.view');

$pageTitle = 'Getting Started';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card fade-in-up" id="tour-controls" style="animation-delay:0s;">
    <div class="card-header"><h3>🎓 Guided Tour</h3></div>
    <p class="text-muted">Let the system walk you through itself: it will highlight each part of this page in order and can read the explanation out loud in English or Tagalog.</p>
    <div class="tour-controls-bar">
        <label class="tour-lang-choice">
            <input type="radio" name="tourLangChoice" value="en" checked> English
        </label>
        <label class="tour-lang-choice">
            <input type="radio" name="tourLangChoice" value="tl"> Tagalog
        </label>
        <label class="tour-voice-choice">
            <input type="checkbox" id="tourVoiceToggle" checked> Voice-over
        </label>
        <button type="button" class="btn btn-primary btn-sm" onclick="startTour()">▶ Start Guided Tour</button>
    </div>
    <p class="form-hint" id="tourVoiceNote"></p>
</div>

<div class="card fade-in-up" id="tour-intro" style="animation-delay:0.05s">
    <h3>Getting Started — How This System Works</h3>
    <p class="text-muted">This page is a visual guide you can come back to any time. Every "Try it" link below opens a real page in the live system — you're always looking at (and safely practicing on) actual data, not a mockup.</p>
    <div class="alert alert-info" style="margin-top:16px;">
        <span class="alert-title">You are logged in as: <?= e($role) ?></span>
        <?= e($roleBlurbs[$role] ?? '') ?>
    </div>
</div>

<div class="card fade-in-up" id="tour-bigpicture" style="animation-delay:0.1s">
    <div class="card-header"><h3>The Big Picture</h3></div>
    <p class="text-muted">Every module below produces real accounting entries automatically. You never type numbers directly into the General Ledger — it fills itself in as a byproduct of the documents you approve.</p>
    <div class="flow-diagram">
        <div class="flow-row">
            <div class="flow-box">Accounts Payable</div>
            <div class="flow-box">Accounts Receivable</div>
            <div class="flow-box">Disbursement</div>
            <div class="flow-box">Collection</div>
            <div class="flow-box">Budget</div>
            <div class="flow-box">Cash Mgmt.</div>
            <div class="flow-box">Tax Mgmt.</div>
        </div>
        <div class="flow-arrow-down">all post automatically to ↓</div>
        <div class="flow-row">
            <div class="flow-box hub">General Ledger</div>
        </div>
        <div class="flow-arrow-down">↓ powers, live, no manual step</div>
        <div class="flow-row">
            <div class="flow-box hub-alt">Dashboard · Financial Reports · Trial Balance · Predictive Analysis</div>
        </div>
    </div>
</div>

<div class="card fade-in-up" id="tour-roles" style="animation-delay:0.15s">
    <div class="card-header"><h3>Roles: Who Can Do What</h3></div>
    <div class="kpi-grid">
        <div class="kpi-card <?= $role === 'Admin' ? 'primary' : '' ?>">
            <div class="kpi-label">Admin <?= $role === 'Admin' ? '(you)' : '' ?></div>
            <div style="font-size:13px;margin-top:8px;line-height:1.5;">Everything, including Users &amp; Settings. Can approve its own submissions.</div>
        </div>
        <div class="kpi-card <?= $role === 'Accountant' ? 'accent' : '' ?>">
            <div class="kpi-label">Accountant <?= $role === 'Accountant' ? '(you)' : '' ?></div>
            <div style="font-size:13px;margin-top:8px;line-height:1.5;">Creates Draft records. No approve buttons anywhere.</div>
        </div>
        <div class="kpi-card <?= $role === 'Approver' ? 'warning' : '' ?>">
            <div class="kpi-label">Approver <?= $role === 'Approver' ? '(you)' : '' ?></div>
            <div style="font-size:13px;margin-top:8px;line-height:1.5;">Approves what Accountants submit. No "New..." buttons anywhere.</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Auditor <?= $role === 'Auditor' ? '(you)' : '' ?></div>
            <div style="font-size:13px;margin-top:8px;line-height:1.5;">Sees every screen + the Audit Log, read-only. Nothing to click but "View."</div>
        </div>
    </div>
    <p class="form-hint">Because Accountant and Approver are split like this, no single non-Admin person can both create <em>and</em> approve the same transaction — that's segregation of duties, enforced by the system rather than left to trust.</p>
</div>

<div class="card fade-in-up" id="tour-ap" style="animation-delay:0.2s">
    <div class="card-header"><h3>Practice: Bill a Vendor (Accounts Payable)</h3></div>
    <p class="text-muted">A Bill moves through 3 stages. Open any real bill in the system and you'll see this exact stepper at the top of the page showing you where it currently sits.</p>
    <?= render_status_stepper(['1. Drafted by Accountant', '2. Approved & Posted to GL', '3. Fully Paid'], 0) ?>
    <ol style="margin:0 0 16px;padding-left:20px;font-size:13.5px;line-height:1.9;">
        <li><strong>Accountant</strong> fills out a Bill against a vendor — saved as <strong>Draft</strong>, no GL effect yet.</li>
        <li><strong>Approver</strong> opens it and clicks <strong>Approve &amp; Post</strong> — posts Dr. Expense / Cr. Accounts Payable to the GL.</li>
        <li>Someone records a <strong>Payment</strong> against it — posts Dr. Accounts Payable / Cr. Cash, and the bill becomes Paid.</li>
    </ol>
    <?php if ($canAp): ?>
        <a href="../ap/bill-form.php" class="btn btn-primary btn-sm">Try it: Create a Bill →</a>
    <?php else: ?>
        <a href="../ap/bills.php" class="btn btn-outline btn-sm">View existing Bills →</a>
        <span class="form-hint">Your role (<?= e($role) ?>) can't create new bills — ask an Accountant, or view/approve existing ones.</span>
    <?php endif; ?>
</div>

<div class="card fade-in-up" id="tour-ar" style="animation-delay:0.25s">
    <div class="card-header"><h3>Practice: Invoice a Customer (Accounts Receivable)</h3></div>
    <p class="text-muted">The mirror image of Accounts Payable — money coming in instead of going out.</p>
    <?= render_status_stepper(['1. Drafted by Accountant', '2. Approved & Posted to GL', '3. Fully Received'], 0) ?>
    <ol style="margin:0 0 16px;padding-left:20px;font-size:13.5px;line-height:1.9;">
        <li><strong>Accountant</strong> fills out an Invoice against a customer — <strong>Draft</strong>.</li>
        <li><strong>Approver</strong> clicks <strong>Approve &amp; Post</strong> — posts Dr. Accounts Receivable / Cr. Revenue.</li>
        <li>Someone records a <strong>Receipt</strong> when the customer pays — posts Dr. Cash / Cr. Accounts Receivable.</li>
    </ol>
    <?php if ($canAr): ?>
        <a href="../ar/invoice-form.php" class="btn btn-primary btn-sm">Try it: Create an Invoice →</a>
    <?php else: ?>
        <a href="../ar/invoices.php" class="btn btn-outline btn-sm">View existing Invoices →</a>
        <span class="form-hint">Your role (<?= e($role) ?>) can't create new invoices — ask an Accountant, or view/approve existing ones.</span>
    <?php endif; ?>
</div>

<div class="form-row">
    <div class="card fade-in-up" id="tour-dv" style="flex:1;min-width:380px;animation-delay:0.3s">
        <div class="card-header"><h3>Practice: Disbursement Voucher</h3></div>
        <p class="text-muted">Use this instead of a plain Payment when a payout needs a formal approval trail first (e.g. an employee cash advance).</p>
        <?= render_status_stepper(['1. Submitted for Approval', '2. Approved', '3. Paid'], 0) ?>
        <?php if ($canDv): ?>
            <a href="../disbursement/voucher-form.php" class="btn btn-primary btn-sm">Try it: New Voucher →</a>
        <?php else: ?>
            <a href="../disbursement/approval-queue.php" class="btn btn-outline btn-sm">View Approval Queue →</a>
        <?php endif; ?>
    </div>
    <div class="card fade-in-up" id="tour-cr" style="flex:1;min-width:380px;animation-delay:0.35s">
        <div class="card-header"><h3>Practice: Collection Receipt</h3></div>
        <p class="text-muted">The inflow mirror of a Disbursement Voucher — collected money that needs sign-off before it's counted as deposited.</p>
        <?= render_status_stepper(['1. Submitted for Approval', '2. Approved', '3. Deposited'], 0) ?>
        <?php if ($canCr): ?>
            <a href="../collection/receipt-form.php" class="btn btn-primary btn-sm">Try it: New Collection Receipt →</a>
        <?php else: ?>
            <a href="../collection/approval-queue.php" class="btn btn-outline btn-sm">View Approval Queue →</a>
        <?php endif; ?>
    </div>
</div>

<div class="form-row">
    <div class="card fade-in-up" id="tour-budget" style="flex:1;min-width:380px;animation-delay:0.4s">
        <div class="card-header"><h3>Practice: Budget Management</h3></div>
        <p class="text-muted">Set spending targets per account and month, then compare them against what actually happened.</p>
        <?= render_status_stepper(['1. Drafted by Accountant', '2. Approved & Tracked'], 0) ?>
        <ol style="margin:0 0 16px;padding-left:20px;font-size:13.5px;line-height:1.9;">
            <li>Create a <strong>Budget Period</strong> (e.g. "FY2026"), then a <strong>Budget</strong> inside it with a monthly amount per account.</li>
            <li><strong>Approver</strong> clicks <strong>Approve</strong> — only Approved budgets count.</li>
            <li>The <strong>Variance Report</strong> then compares Budgeted vs. Actual live from the GL — nothing to "run," it's always current.</li>
        </ol>
        <?php if ($canBudget): ?>
            <a href="../budget/budget-form.php" class="btn btn-primary btn-sm">Try it: New Budget →</a>
        <?php else: ?>
            <a href="../budget/variance-report.php" class="btn btn-outline btn-sm">View Variance Report →</a>
            <span class="form-hint">Your role (<?= e($role) ?>) can't create new budgets.</span>
        <?php endif; ?>
    </div>
    <div class="card fade-in-up" id="tour-cash" style="flex:1;min-width:380px;animation-delay:0.45s">
        <div class="card-header"><h3>Practice: Cash Management</h3></div>
        <p class="text-muted">Where every bank/cash-on-hand account lives, and where money movements that aren't AP/AR get recorded.</p>
        <ul style="margin:0 0 16px;padding-left:20px;font-size:13.5px;line-height:1.9;">
            <li><strong>Accounts</strong> — each linked to a GL asset account, with a running balance.</li>
            <li><strong>Transactions / Transfers</strong> — post straight to the GL, no separate approval step (they represent money that's already moved).</li>
            <li><strong>Reconciliation</strong> — match your books against a real bank statement.</li>
        </ul>
        <?php if ($canCash): ?>
            <a href="../cash/transaction-form.php" class="btn btn-primary btn-sm">Try it: New Cash Transaction →</a>
        <?php else: ?>
            <a href="../cash/cash-position.php" class="btn btn-outline btn-sm">View Cash Position →</a>
            <span class="form-hint">Your role (<?= e($role) ?>) can't record cash transactions.</span>
        <?php endif; ?>
    </div>
</div>

<div class="form-row">
    <div class="card fade-in-up" id="tour-reports" style="flex:1;min-width:380px;animation-delay:0.5s">
        <div class="card-header"><h3>Practice: Financial Reports</h3></div>
        <p class="text-muted">Read-only — nothing to draft or approve here, every report reads live from posted GL activity.</p>
        <ul style="margin:0 0 16px;padding-left:20px;font-size:13.5px;line-height:1.9;">
            <li><strong>Trial Balance</strong> — every account's movement; confirms the books balance.</li>
            <li><strong>Income Statement</strong>, <strong>Balance Sheet</strong>, <strong>Cash Flow Statement</strong> — the three core financial statements.</li>
            <li><strong>Custom / GL Detail Report</strong> — pick any accounts and date range yourself.</li>
        </ul>
        <a href="../reports/index.php" class="btn btn-primary btn-sm">Try it: Open Reports →</a>
    </div>
    <div class="card fade-in-up" id="tour-tax" style="flex:1;min-width:380px;animation-delay:0.55s">
        <div class="card-header"><h3>Practice: Tax Management</h3></div>
        <p class="text-muted">Tax Types are configured once; the transactions themselves are never entered by hand.</p>
        <ol style="margin:0 0 16px;padding-left:20px;font-size:13.5px;line-height:1.9;">
            <li>Every taxed <strong>Bill</strong> or <strong>Invoice</strong> line automatically creates a <strong>Tax Transaction</strong> (Input or Output) — <strong>Pending</strong>.</li>
            <li>When it's time to pay the government, file a <strong>Remittance</strong> — it aggregates every matching Pending transaction, marks them Remitted, and posts Dr. Tax Payable / Cr. Cash.</li>
        </ol>
        <?php if ($canTax): ?>
            <a href="../tax/remittance-form.php" class="btn btn-primary btn-sm">Try it: New Remittance →</a>
        <?php else: ?>
            <a href="../tax/compliance-report.php" class="btn btn-outline btn-sm">View Compliance Summary →</a>
            <span class="form-hint">Your role (<?= e($role) ?>) can't file remittances.</span>
        <?php endif; ?>
    </div>
</div>

<div class="card fade-in-up" id="tour-admin" style="animation-delay:0.6s">
    <div class="card-header"><h3>Admin &amp; Compliance</h3></div>
    <p class="text-muted">A separate area from day-to-day financial work — mostly Admin-only, with the Auditor able to look but not touch.</p>
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Users</div>
            <div style="font-size:13px;margin-top:8px;line-height:1.5;">Create logins and assign one of the 4 roles. Admin-only to create/edit.</div>
            <?php if ($canUsers): ?><a href="../users/list.php" class="btn btn-outline btn-sm" style="margin-top:10px;">Open Users →</a><?php else: ?><span class="form-hint">Not visible to your role.</span><?php endif; ?>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Settings</div>
            <div style="font-size:13px;margin-top:8px;line-height:1.5;">The GL Control Accounts (where AP/AR/Tax auto-post) and Decision Support thresholds. Admin-only to edit.</div>
            <?php if ($canSettings): ?><a href="../settings/system-settings.php" class="btn btn-outline btn-sm" style="margin-top:10px;">Open Settings →</a><?php else: ?><span class="form-hint">Not visible to your role.</span><?php endif; ?>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Audit Log</div>
            <div style="font-size:13px;margin-top:8px;line-height:1.5;">A timestamped, un-editable record of every create/approve/pay/void action, by every user.</div>
            <?php if ($canAudit): ?><a href="../audit/audit-log.php" class="btn btn-outline btn-sm" style="margin-top:10px;">Open Audit Log →</a><?php else: ?><span class="form-hint">Not visible to your role.</span><?php endif; ?>
        </div>
    </div>
</div>

<div class="card fade-in-up" id="tour-results" style="animation-delay:0.65s">
    <div class="card-header"><h3>See the Result</h3></div>
    <p class="text-muted">After practicing any of the flows above, these are the best places to see the effect:</p>
    <div class="kpi-grid">
        <a href="../dashboard/index.php" class="card" style="margin:0;text-decoration:none;color:inherit;">
            <strong>Dashboard →</strong>
            <p class="text-muted" style="margin:6px 0 0;font-size:12.5px;">KPIs, Decision Support alerts, and the cash flow forecast, all live.</p>
        </a>
        <a href="../gl/journal-entries.php" class="card" style="margin:0;text-decoration:none;color:inherit;">
            <strong>Journal Entries →</strong>
            <p class="text-muted" style="margin:6px 0 0;font-size:12.5px;">The actual Debit/Credit postings your actions just created.</p>
        </a>
        <a href="../reports/trial-balance.php" class="card" style="margin:0;text-decoration:none;color:inherit;">
            <strong>Trial Balance →</strong>
            <p class="text-muted" style="margin:6px 0 0;font-size:12.5px;">Proof the books are still in balance after everything you did.</p>
        </a>
        <?php if (has_permission('audit.view')): ?>
        <a href="../audit/audit-log.php" class="card" style="margin:0;text-decoration:none;color:inherit;">
            <strong>Audit Log →</strong>
            <p class="text-muted" style="margin:6px 0 0;font-size:12.5px;">A timestamped record of every action, by every user.</p>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Guided tour overlay: hidden until "Start Guided Tour" is clicked. The spotlight box
     is repositioned/resized via JS to match whichever #tour-* section is the current step. -->
<div class="tour-spotlight" id="tourSpotlight" style="display:none;"></div>
<!-- Always fixed to the corner regardless of the tooltip's own position/state, so
     there is always a guaranteed, reachable way to end the tour (also: Escape key). -->
<button type="button" class="tour-exit-pin" id="tourExitPin" style="display:none;" onclick="endTour()" title="Exit tour (Esc)">✕ Exit Tour</button>
<div class="tour-tooltip" id="tourTooltip" style="display:none;">
    <div class="tour-tooltip-header" id="tourTooltipHeader" title="Drag to move this box">
        <span class="tour-drag-handle" aria-hidden="true">⠿⠿</span>
        <span id="tourStepCounter">Step 1 of 9</span>
        <button type="button" class="tour-close" onclick="endTour()" aria-label="Close tour">✕</button>
    </div>
    <h4 id="tourTitle"></h4>
    <p id="tourBody"></p>
    <div class="tour-tooltip-controls">
        <button type="button" class="btn btn-outline btn-sm" id="tourPrevBtn" onclick="tourPrev()">← Back</button>
        <button type="button" class="btn btn-outline btn-sm" id="tourVoiceBtn" onclick="toggleTourSpeech()">⏸ Pause voice</button>
        <button type="button" class="btn btn-primary btn-sm" id="tourNextBtn" onclick="tourNext()">Next →</button>
    </div>
</div>

<script>
window.TOUR_ROLE = <?= json_encode($role) ?>;
window.TOUR_ROLE_BLURB_EN = <?= json_encode($roleBlurbs[$role] ?? '') ?>;
window.TOUR_ROLE_BLURB_TL = <?= json_encode($roleBlurbsTl[$role] ?? '') ?>;
</script>
<?php $extraScripts = [BASE_URL . '/assets/js/tour.js']; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
