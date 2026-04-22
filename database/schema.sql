-- ============================================================
-- Triply Project — SQLite Schema
-- Open with: DB Browser for SQLite → triply.db
-- ============================================================

PRAGMA foreign_keys = ON;
PRAGMA journal_mode  = WAL;

-- ------------------------------------------------------------
-- users
-- Sensitive fields packed into one AES-256-GCM encrypted JSON blob.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    email         TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL DEFAULT 'member'
                          CHECK(role IN ('member','leader','admin')),
    data          TEXT    NOT NULL,   -- AES-256-GCM encrypted JSON
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- trips
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trips (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    title         TEXT    NOT NULL,
    destination   TEXT    NOT NULL,
    start_date    TEXT    NOT NULL,
    end_date      TEXT    NOT NULL,
    base_currency TEXT    NOT NULL DEFAULT 'EGP'
                          CHECK(base_currency IN ('EGP','USD','EUR')),
    budget_limit  REAL    DEFAULT NULL,
    created_by    INTEGER NOT NULL REFERENCES users(id),
    status        TEXT    NOT NULL DEFAULT 'planning'
                          CHECK(status IN ('planning','active','completed','settled')),
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- trip_members
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trip_members (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id    INTEGER NOT NULL REFERENCES trips(id)  ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
    role       TEXT    NOT NULL DEFAULT 'member'
                       CHECK(role IN ('member','leader')),
    can_edit   INTEGER NOT NULL DEFAULT 0,
    joined_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(trip_id, user_id)
);

-- ------------------------------------------------------------
-- activities
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activities (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id        INTEGER NOT NULL REFERENCES trips(id)  ON DELETE CASCADE,
    title          TEXT    NOT NULL,
    location       TEXT    DEFAULT NULL,
    lat            REAL    DEFAULT NULL,
    lng            REAL    DEFAULT NULL,
    datetime       TEXT    NOT NULL,
    duration_min   INTEGER NOT NULL DEFAULT 60,
    status         TEXT    NOT NULL DEFAULT 'draft'
                           CHECK(status IN ('draft','confirmed','cancelled')),
    transport_mode TEXT    NOT NULL DEFAULT 'car'
                           CHECK(transport_mode IN ('walk','car','bus','train','flight')),
    created_by     INTEGER NOT NULL REFERENCES users(id),
    created_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- activity_attendance
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_attendance (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    activity_id INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL REFERENCES users(id)      ON DELETE CASCADE,
    status      TEXT    NOT NULL DEFAULT 'pending'
                        CHECK(status IN ('in','out','pending')),
    UNIQUE(activity_id, user_id)
);

-- ------------------------------------------------------------
-- expenses
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id           INTEGER NOT NULL REFERENCES trips(id)  ON DELETE CASCADE,
    title             TEXT    NOT NULL,
    amount            REAL    NOT NULL,
    original_currency TEXT    NOT NULL DEFAULT 'EGP'
                              CHECK(original_currency IN ('EGP','USD','EUR')),
    converted_amount  REAL    NOT NULL,
    type              TEXT    NOT NULL DEFAULT 'general',
    paid_by           INTEGER NOT NULL REFERENCES users(id),
    created_at        TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- expense_splits
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expense_splits (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_id  INTEGER NOT NULL REFERENCES expenses(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL REFERENCES users(id)    ON DELETE CASCADE,
    amount      REAL    NOT NULL,
    percentage  REAL    DEFAULT NULL,
    is_settled  INTEGER NOT NULL DEFAULT 0
);

-- ------------------------------------------------------------
-- settlements
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settlements (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id         INTEGER NOT NULL REFERENCES trips(id)  ON DELETE CASCADE,
    from_user       INTEGER NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
    to_user         INTEGER NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
    amount          REAL    NOT NULL,
    approved_by_all INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- polls
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS polls (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id      INTEGER NOT NULL REFERENCES trips(id)  ON DELETE CASCADE,
    question     TEXT    NOT NULL,
    type         TEXT    NOT NULL DEFAULT 'general'
                         CHECK(type IN ('destination','activity','general','must_have')),
    is_anonymous INTEGER NOT NULL DEFAULT 0,
    deadline     TEXT    DEFAULT NULL,
    status       TEXT    NOT NULL DEFAULT 'open'
                         CHECK(status IN ('open','closed')),
    created_by   INTEGER NOT NULL REFERENCES users(id),
    created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- poll_options
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS poll_options (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    poll_id     INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
    option_text TEXT    NOT NULL
);

-- ------------------------------------------------------------
-- votes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS votes (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    poll_id   INTEGER NOT NULL REFERENCES polls(id)         ON DELETE CASCADE,
    option_id INTEGER NOT NULL REFERENCES poll_options(id)  ON DELETE CASCADE,
    user_id   INTEGER NOT NULL REFERENCES users(id)         ON DELETE CASCADE,
    cast_at   TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(poll_id, user_id)
);

-- ------------------------------------------------------------
-- documents
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
    trip_id     INTEGER NOT NULL REFERENCES trips(id)  ON DELETE CASCADE,
    type        TEXT    NOT NULL DEFAULT 'other'
                        CHECK(type IN ('passport','ticket','visa','other')),
    stored_name TEXT    NOT NULL,   -- random filename on disk (.enc)
    visibility  TEXT    NOT NULL DEFAULT 'private'
                        CHECK(visibility IN ('private','leader')),
    metadata    TEXT    DEFAULT NULL,  -- AES-256-GCM encrypted JSON
    uploaded_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- itinerary_versions  (gzip-compressed JSON snapshots)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS itinerary_versions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id     INTEGER NOT NULL REFERENCES trips(id)  ON DELETE CASCADE,
    snapshot    BLOB    NOT NULL,   -- gzcompress(json_encode(activities[]))
    changed_by  INTEGER NOT NULL REFERENCES users(id),
    changed_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    note        TEXT    DEFAULT NULL
);

-- ------------------------------------------------------------
-- notifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type       TEXT    NOT NULL
                       CHECK(type IN ('budget_alert','daily_briefing','poll_closed','invite','settlement')),
    message    TEXT    NOT NULL,
    is_read    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- comments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    activity_id INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL REFERENCES users(id)      ON DELETE CASCADE,
    content     TEXT    NOT NULL,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ------------------------------------------------------------
-- shared_items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shared_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id     INTEGER NOT NULL REFERENCES trips(id)  ON DELETE CASCADE,
    item_name   TEXT    NOT NULL,
    assigned_to INTEGER DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- currency_rates
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS currency_rates (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    from_currency TEXT NOT NULL CHECK(from_currency IN ('EGP','USD','EUR')),
    to_currency   TEXT NOT NULL CHECK(to_currency   IN ('EGP','USD','EUR')),
    rate          REAL NOT NULL,
    updated_at    TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(from_currency, to_currency)
);

INSERT OR IGNORE INTO currency_rates (from_currency, to_currency, rate) VALUES
('USD','EGP', 49.00), ('EUR','EGP', 53.00),
('EGP','USD', 0.0204), ('EUR','USD', 1.08),
('EGP','EUR', 0.0189), ('USD','EUR', 0.926);

-- ------------------------------------------------------------
-- sessions  (token-based auth)
--   Cookie holds: raw_token (base64url, 32 bytes)
--   DB stores:    SHA-256(raw_token)  ← never the real token
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT    NOT NULL UNIQUE,   -- SHA-256(raw_token)
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    expires_at TEXT    NOT NULL,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);
