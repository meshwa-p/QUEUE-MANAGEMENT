-- ============================================================
-- Smart Queue Management System
-- Sprint 2 & 3: Service Selection, Token Generation,
--               Queue Status, and Staff Dashboard
-- ============================================================
-- Run AFTER queue_system.sql has already been imported.
-- Safe to re-run: all statements use IF NOT EXISTS / INSERT IGNORE.
-- ============================================================

USE queue_system;

-- ------------------------------------------------------------
-- 1. SERVICES TABLE
--    Stores the available counter services students can queue for.
--    icon: a single emoji character used in the UI.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
    service_id  INT          AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    icon        VARCHAR(10)  NOT NULL DEFAULT '📄',
    prefix      CHAR(1)      NOT NULL COMMENT 'One-letter token prefix, e.g. F for Fee Payment',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Seed the four required services.
-- INSERT IGNORE prevents duplicates if this file is run more than once.
INSERT IGNORE INTO services (service_id, name, description, icon, prefix, is_active) VALUES
(1, 'Fee Payment',            'School fees, tuition & exam payments',          '💰', 'F', 1),
(2, 'Document Submission',    'Forms, certificates & official records',         '📄', 'D', 1),
(3, 'Scholarship Query',      'Bursaries, grants & financial aid support',      '🎓', 'S', 1),
(4, 'General Accounts Query', 'Billing disputes & account inquiries',           '🔍', 'G', 1);

-- ------------------------------------------------------------
-- 2. TOKENS TABLE
--    Every "Generate Token" action inserts a row here.
--    token_number: formatted string like "F-001", "D-003".
--    The sequential number resets per service per calendar day.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tokens (
    token_id      INT          AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NOT NULL,
    service_id    INT          NOT NULL,
    token_number  VARCHAR(10)  NOT NULL,
    status        ENUM('waiting','called','served','cancelled','no_show')
                               NOT NULL DEFAULT 'waiting',
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    called_at     DATETIME     DEFAULT NULL,
    served_at     DATETIME     DEFAULT NULL,

    FOREIGN KEY (user_id)    REFERENCES users(user_id)    ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE,

    -- Unique token number per service per day (prevents collisions on concurrent inserts)
    UNIQUE KEY uq_token_per_service_day (service_id, token_number, created_at)
);
