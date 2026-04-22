-- trips.db — trips, itinerary, notifications
PRAGMA foreign_keys = ON;
PRAGMA journal_mode  = WAL;

CREATE TABLE IF NOT EXISTS trips (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    title         TEXT    NOT NULL,
    destination   TEXT    NOT NULL,
    start_date    TEXT    NOT NULL,
    end_date      TEXT    NOT NULL,
    base_currency TEXT    NOT NULL DEFAULT 'EGP',
    budget_limit  REAL    DEFAULT NULL,
    created_by    INTEGER NOT NULL,
    status        TEXT    NOT NULL DEFAULT 'planning'
                          CHECK(status IN ('planning','active','completed','settled')),
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS trip_members (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id    INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL,
    role       TEXT    NOT NULL DEFAULT 'member'
                       CHECK(role IN ('member','leader')),
    can_edit   INTEGER NOT NULL DEFAULT 0,
    joined_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(trip_id, user_id)
);

CREATE TABLE IF NOT EXISTS activities (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id        INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
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
    created_by     INTEGER NOT NULL,
    created_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS activity_attendance (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    activity_id INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL,
    status      TEXT    NOT NULL DEFAULT 'pending'
                        CHECK(status IN ('in','out','pending')),
    UNIQUE(activity_id, user_id)
);

CREATE TABLE IF NOT EXISTS itinerary_versions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id     INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
    snapshot    BLOB    NOT NULL,
    changed_by  INTEGER NOT NULL,
    changed_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    note        TEXT    DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS comments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    activity_id INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL,
    content     TEXT    NOT NULL,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS shared_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id     INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
    item_name   TEXT    NOT NULL,
    assigned_to INTEGER DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    type       TEXT    NOT NULL
                       CHECK(type IN ('budget_alert','daily_briefing','poll_closed','invite','settlement')),
    message    TEXT    NOT NULL,
    is_read    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);
