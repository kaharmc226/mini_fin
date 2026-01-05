PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  color TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  amount REAL NOT NULL,
  note TEXT,
  occurred_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_expenses_occurred_at ON expenses (occurred_at);
CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses (category_id);

-- Seed a few starter categories; users can add their own later.
INSERT OR IGNORE INTO categories (id, name, color) VALUES
  (1, 'Food & Drink', '#4f46e5'),
  (2, 'Transport', '#0ea5e9'),
  (3, 'Groceries', '#10b981'),
  (4, 'Bills & Utilities', '#f59e0b'),
  (5, 'Shopping', '#e11d48'),
  (6, 'Health', '#22c55e'),
  (7, 'Leisure', '#8b5cf6'),
  (8, 'Other', '#94a3b8');
