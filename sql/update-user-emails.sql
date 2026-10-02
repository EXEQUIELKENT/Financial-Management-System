-- Replace old demo/test addresses with the real OTP accounts.
-- Idempotent: safe to run more than once. Matches by role + demo username
-- so it won't touch any other accounts (e.g. extra admins).
-- Run via phpMyAdmin or: mysql -u root travelcore_fms < update-user-emails.sql

UPDATE users u JOIN roles r ON r.id = u.role_id
SET u.email = 'rvincetimothy@gmail.com'
WHERE r.name = 'Admin' AND u.username = 'admin';

UPDATE users u JOIN roles r ON r.id = u.role_id
SET u.email = 'vlmnzn.fnc@gmail.com'
WHERE r.name = 'Approver' AND u.username = 'approver';

UPDATE users u JOIN roles r ON r.id = u.role_id
SET u.email = 'maddyperez22111@gmail.com'
WHERE r.name = 'Accountant' AND u.username = 'accountant';

UPDATE users u JOIN roles r ON r.id = u.role_id
SET u.email = 'romerojanvincetimothy@gmail.com'
WHERE r.name = 'Auditor' AND u.username = 'auditor';

-- Verify: should return 0
-- SELECT COUNT(*) AS old_test_accounts_remaining FROM users WHERE email LIKE '%@travelcore.test';
