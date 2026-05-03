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
            status      TEXT    NOT NULL DEFAULT 'pending'
                                CHECK(status IN ('pending','verified','rejected')),
            reviewed_by INTEGER DEFAULT NULL,
            review_note TEXT    DEFAULT NULL,
            uploaded_at TEXT    NOT NULL DEFAULT (datetime('now'))
        )
    ");
    // Migrate old is_verified column → status (if table existed before)
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

    // required_docs column on trips
    try {
        Database::getInstance('trips')->exec(
            "ALTER TABLE trips ADD COLUMN required_docs TEXT DEFAULT NULL"
        );
    } catch (\Throwable $ignored) {}
}

runMigrations();
