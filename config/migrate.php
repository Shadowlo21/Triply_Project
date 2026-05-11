<?php

function runMigrations(): void
{
    Database::getInstance('documents')->exec("
        CREATE TABLE IF NOT EXISTS profile_documents (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER NOT NULL,
            type        TEXT    NOT NULL CHECK(type IN ('passport','national_id','license','other')),
            stored_name TEXT    NOT NULL,
            metadata    TEXT,
            status      TEXT    NOT NULL DEFAULT 'pending'
                                CHECK(status IN ('pending','verified','rejected')),
            reviewed_by INTEGER DEFAULT NULL,
            review_note TEXT    DEFAULT NULL,
            uploaded_at TEXT    NOT NULL DEFAULT (datetime('now'))
        )
    ");
    try {
        Database::getInstance('documents')->exec(
            "ALTER TABLE profile_documents ADD COLUMN status TEXT NOT NULL DEFAULT 'pending'"
        );
    } catch (\Throwable $ignored) {}
    try {
        Database::getInstance('documents')->exec(
            "ALTER TABLE profile_documents ADD COLUMN reviewed_by INTEGER DEFAULT NULL"
        );
    } catch (\Throwable $ignored) {}
    try {
        Database::getInstance('documents')->exec(
            "ALTER TABLE profile_documents ADD COLUMN review_note TEXT DEFAULT NULL"
        );
    } catch (\Throwable $ignored) {}

    try {
        Database::getInstance('trips')->exec(
            "ALTER TABLE trips ADD COLUMN required_docs TEXT DEFAULT NULL"
        );
    } catch (\Throwable $ignored) {}

    Database::getInstance('accounts')->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier  TEXT NOT NULL,
            attempt_at  TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    Database::getInstance('accounts')->exec(
        "CREATE INDEX IF NOT EXISTS idx_login_attempts_id_at ON login_attempts(identifier, attempt_at)"
    );

    Database::getInstance('accounts')->exec("
        CREATE TABLE IF NOT EXISTS rate_events (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            scope      TEXT NOT NULL,
            event_at   TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    Database::getInstance('accounts')->exec(
        "CREATE INDEX IF NOT EXISTS idx_rate_events_scope_at ON rate_events(scope, event_at)"
    );

    try {
        Database::getInstance('trips')->exec(
            "ALTER TABLE trips ADD COLUMN last_invite_all_at TEXT DEFAULT NULL"
        );
    } catch (\Throwable $ignored) {}

    try {
        Database::getInstance('accounts')->exec(
            "ALTER TABLE sessions ADD COLUMN csrf_token TEXT DEFAULT NULL"
        );
    } catch (\Throwable $ignored) {}
}

runMigrations();
