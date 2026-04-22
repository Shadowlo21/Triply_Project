-- social.db — polls, options, votes
PRAGMA foreign_keys = ON;
PRAGMA journal_mode  = WAL;

CREATE TABLE IF NOT EXISTS polls (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id      INTEGER NOT NULL,
    question     TEXT    NOT NULL,
    type         TEXT    NOT NULL DEFAULT 'general'
                         CHECK(type IN ('destination','activity','general','must_have')),
    is_anonymous INTEGER NOT NULL DEFAULT 0,
    deadline     TEXT    DEFAULT NULL,
    status       TEXT    NOT NULL DEFAULT 'open'
                         CHECK(status IN ('open','closed')),
    created_by   INTEGER NOT NULL,
    created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS poll_options (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    poll_id     INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
    option_text TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS votes (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    poll_id   INTEGER NOT NULL REFERENCES polls(id)         ON DELETE CASCADE,
    option_id INTEGER NOT NULL REFERENCES poll_options(id)  ON DELETE CASCADE,
    user_id   INTEGER NOT NULL,
    cast_at   TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(poll_id, user_id)
);
