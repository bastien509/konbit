-- Konbit (petiht.com/freelanceragent) schema. MySQL 8 / MariaDB 10.6+.
-- Runs automatically on first request (inc/bootstrap.php) and is safe to re-run.
-- Money is always stored in integer cents.

CREATE TABLE IF NOT EXISTS fa_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(80) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL,
  business_name VARCHAR(120) NOT NULL DEFAULT '',
  hook_key_hash CHAR(64) NULL,
  hook_key_hint VARCHAR(12) NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_banned TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email (email),
  KEY idx_hook (hook_key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The recipient allowlist. The agent can only ever pay people on this list.
CREATE TABLE IF NOT EXISTS fa_crew (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  role VARCHAR(60) NOT NULL DEFAULT '',
  paypal_email VARCHAR(190) NOT NULL,
  email_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  color VARCHAR(9) NOT NULL DEFAULT '#00209F',
  status ENUM('active','removed') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_owner (owner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- received - reserve - paid_out - held = money the agent may still pay out.
CREATE TABLE IF NOT EXISTS fa_projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  client_name VARCHAR(120) NOT NULL DEFAULT '',
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  reserve_pct TINYINT UNSIGNED NOT NULL DEFAULT 15,
  received_cents BIGINT NOT NULL DEFAULT 0,
  reserve_cents BIGINT NOT NULL DEFAULT 0,
  paid_out_cents BIGINT NOT NULL DEFAULT 0,
  held_cents BIGINT NOT NULL DEFAULT 0,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_owner (owner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Revenue share: each client payment is split by these percentages (basis points).
CREATE TABLE IF NOT EXISTS fa_splits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  crew_id INT UNSIGNED NOT NULL,
  share_bp SMALLINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_split (project_id, crew_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-fee deliverables. Work evidence releases them.
CREATE TABLE IF NOT EXISTS fa_milestones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  crew_id INT UNSIGNED NOT NULL,
  title VARCHAR(140) NOT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  due_on DATE NULL,
  status ENUM('open','in_payout','paid') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_project (project_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The mandate: what the agent may do on its own ("auto"), what needs a tap ("ask"), and hard caps ("never").
CREATE TABLE IF NOT EXISTS fa_mandates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  auto_below_cents INT UNSIGNED NOT NULL,
  max_payout_cents INT UNSIGNED NOT NULL,
  max_recipient_month_cents INT UNSIGNED NOT NULL,
  month_cap_cents INT UNSIGNED NOT NULL,
  max_auto_per_day TINYINT UNSIGNED NOT NULL DEFAULT 10,
  expires_at DATETIME NOT NULL,
  status ENUM('active','paused','revoked','expired') NOT NULL DEFAULT 'active',
  consent_text TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  KEY idx_owner (owner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Client payments (PayPal Orders v2). A request has a public token for the client's pay page.
CREATE TABLE IF NOT EXISTS fa_client_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  pay_token CHAR(32) NOT NULL,
  memo VARCHAR(200) NOT NULL DEFAULT '',
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  mode ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  paypal_order_id VARCHAR(40) NULL,
  capture_id VARCHAR(40) NULL,
  payer_email VARCHAR(190) NULL,
  status ENUM('requested','created','pending','completed','failed','cancelled') NOT NULL DEFAULT 'requested',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_token (pay_token),
  KEY idx_order (paypal_order_id),
  KEY idx_owner (owner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Work evidence from email, GitHub, calendar, Upwork, Notion or the API. Always untrusted text.
CREATE TABLE IF NOT EXISTS fa_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  source VARCHAR(20) NOT NULL,
  title VARCHAR(160) NOT NULL DEFAULT '',
  body TEXT NOT NULL,
  body_hash CHAR(64) NOT NULL,
  status ENUM('new','analyzing','done','ignored','flagged','error') NOT NULL DEFAULT 'new',
  agent_summary VARCHAR(600) NULL,
  risk VARCHAR(20) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  analyzed_at DATETIME NULL,
  KEY idx_owner (owner_id, id),
  KEY idx_hash (owner_id, body_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A payout plan groups items paid in one PayPal Payouts batch.
CREATE TABLE IF NOT EXISTS fa_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NULL,
  client_payment_id INT UNSIGNED NULL,
  kind ENUM('split','milestone','bonus') NOT NULL,
  created_by ENUM('agent','rule','owner') NOT NULL,
  title VARCHAR(200) NOT NULL,
  reason VARCHAR(600) NOT NULL DEFAULT '',
  evidence_quote VARCHAR(600) NULL,
  total_cents INT UNSIGNED NOT NULL,
  decision ENUM('auto','ask','block') NOT NULL,
  policy_json TEXT NOT NULL,
  status ENUM('needs_approval','blocked','scheduled','sending','sent','paid','partial','failed','rejected') NOT NULL,
  pay_on DATE NULL,
  sender_batch_id VARCHAR(40) NULL,
  paypal_batch_id VARCHAR(40) NULL,
  approved_by ENUM('mandate','owner') NULL,
  approved_at DATETIME NULL,
  last_sync_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_owner (owner_id, status),
  KEY idx_batch (paypal_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fa_plan_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id INT UNSIGNED NOT NULL,
  crew_id INT UNSIGNED NOT NULL,
  milestone_id INT UNSIGNED NULL,
  receiver VARCHAR(190) NOT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  note VARCHAR(200) NOT NULL DEFAULT '',
  status ENUM('planned','sent','success','unclaimed','pending','failed','returned','cancelled') NOT NULL DEFAULT 'planned',
  paypal_item_id VARCHAR(40) NULL,
  paypal_status VARCHAR(20) NULL,
  settled TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_plan (plan_id),
  KEY idx_crew (crew_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Double-entry style money log. idem_key makes every movement exactly-once.
CREATE TABLE IF NOT EXISTS fa_ledger (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  account ENUM('pot','reserve','crew','hold') NOT NULL,
  type VARCHAR(30) NOT NULL,
  amount_cents BIGINT NOT NULL,
  crew_id INT UNSIGNED NULL,
  plan_id INT UNSIGNED NULL,
  note VARCHAR(200) NOT NULL DEFAULT '',
  idem_key VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_idem (idem_key),
  KEY idx_owner (owner_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fa_agent_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  tool VARCHAR(40) NOT NULL DEFAULT '',
  detail TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_owner (owner_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fa_rate_limits (
  bucket CHAR(40) NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
