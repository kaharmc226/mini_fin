import { sql } from '@vercel/postgres';

async function migrate() {
  await sql`CREATE TABLE IF NOT EXISTS categories (
    id BIGSERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    color TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
  )`;

  await sql`CREATE TABLE IF NOT EXISTS expenses (
    id BIGSERIAL PRIMARY KEY,
    category_id BIGINT REFERENCES categories(id) ON DELETE SET NULL,
    amount DOUBLE PRECISION NOT NULL,
    note TEXT,
    occurred_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
  )`;

  await sql`CREATE INDEX IF NOT EXISTS idx_expenses_occurred_at ON expenses (occurred_at DESC)`;
  await sql`CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses (category_id)`;

  await sql`INSERT INTO categories (id, name, color) VALUES
    (1, 'Food & Drink', '#4f46e5'),
    (2, 'Transport', '#0ea5e9'),
    (3, 'Groceries', '#10b981'),
    (4, 'Bills & Utilities', '#f59e0b'),
    (5, 'Shopping', '#e11d48'),
    (6, 'Health', '#22c55e'),
    (7, 'Leisure', '#8b5cf6'),
    (8, 'Other', '#94a3b8')
  ON CONFLICT (id) DO NOTHING`;

  await sql`SELECT setval(pg_get_serial_sequence('categories','id'), COALESCE((SELECT MAX(id) FROM categories), 1))`;

  console.log('Migration completed.');
}

migrate().catch((err) => {
  console.error(err);
  process.exit(1);
});
