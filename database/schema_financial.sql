-- financial.db — expenses, splits, settlements, currency rates
PRAGMA foreign_keys = ON;
PRAGMA journal_mode  = WAL;

CREATE TABLE IF NOT EXISTS expenses (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id           INTEGER NOT NULL,
    title             TEXT    NOT NULL,
    amount            REAL    NOT NULL,
    original_currency TEXT    NOT NULL DEFAULT 'EGP',
    converted_amount  REAL    NOT NULL,
    type              TEXT    NOT NULL DEFAULT 'general',
    paid_by           INTEGER NOT NULL,
    created_at        TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS expense_splits (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_id  INTEGER NOT NULL REFERENCES expenses(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL,
    amount      REAL    NOT NULL,
    percentage  REAL    DEFAULT NULL,
    is_settled  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS settlements (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id         INTEGER NOT NULL,
    from_user       INTEGER NOT NULL,
    to_user         INTEGER NOT NULL,
    amount          REAL    NOT NULL,
    approved_by_all INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS currency_rates (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    from_currency TEXT NOT NULL,
    to_currency   TEXT NOT NULL,
    rate          REAL NOT NULL,
    updated_at    TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(from_currency, to_currency)
);

INSERT OR IGNORE INTO currency_rates (from_currency, to_currency, rate) VALUES
('USD','EGP', 49.00), ('EUR','EGP', 53.00),
('EGP','USD', 0.0204), ('EUR','USD', 1.08),
('EGP','EUR', 0.0189), ('USD','EUR', 0.926);
