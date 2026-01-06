import { sql } from '@vercel/postgres';

async function migrate() {
  await sql`CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    color TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
  )`;

  await sql`CREATE TABLE IF NOT EXISTS expenses (
    id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    amount NUMERIC NOT NULL,
    note TEXT,
    occurred_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
  )`;

  await sql`CREATE INDEX IF NOT EXISTS idx_expenses_occurred_at ON expenses (occurred_at DESC)`;
  await sql`CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses (category_id)`;

  await sql`INSERT INTO categories (name, color) VALUES
    ('Food & Drink', '#4f46e5'),
    ('Transport', '#0ea5e9'),
    ('Groceries', '#10b981'),
    ('Bills & Utilities', '#f59e0b'),
    ('Shopping', '#e11d48'),
    ('Health', '#22c55e'),
    ('Leisure', '#8b5cf6'),
    ('Other', '#94a3b8')
  ON CONFLICT (name) DO NOTHING`;

  console.log('Migration completed.');
}

migrate().catch((err) => {
  console.error(err);
  process.exit(1);
});
