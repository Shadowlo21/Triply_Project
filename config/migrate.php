<?php

function runMigrations(): void
{
    // profile_documents table in documents.db
    Database::getInstance('documents')->exec("
        CREATE TABLE IF NOT EXISTS profile_documents (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER NOT NULL,
            type        TEXT    NOT NULL CHECK(type IN ('passport','national_id','license','other')),
            stored_name TEXT    NOT NULL,
            metadata    TEXT,
            is_verified INTEGER NOT NULL DEFAULT 0,
            verified_by INTEGER DEFAULT NULL,
            uploaded_at TEXT    NOT NULL DEFAULT (datetime('now'))
        )
    ");

    // required_docs column on trips
    try {
        Database::getInstance('trips')->exec(
            "ALTER TABLE trips ADD COLUMN required_docs TEXT DEFAULT NULL"
        );
    } catch (\Throwable $ignored) {}
}

runMigrations();
