-- documents.db — file vault
PRAGMA foreign_keys = ON;
PRAGMA journal_mode  = WAL;

CREATE TABLE IF NOT EXISTS documents (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    trip_id     INTEGER NOT NULL,
    type        TEXT    NOT NULL DEFAULT 'other'
                        CHECK(type IN ('passport','ticket','visa','insurance','other')),
    stored_name TEXT    NOT NULL,
    visibility  TEXT    NOT NULL DEFAULT 'private'
                        CHECK(visibility IN ('private','group','leader')),
    metadata    TEXT    DEFAULT NULL,
    uploaded_at TEXT    NOT NULL DEFAULT (datetime('now'))
);
