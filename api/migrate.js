import { Pool } from 'pg';

const connectionString =
  process.env.POSTGRES_PRISMA_URL || process.env.POSTGRES_URL || process.env.DATABASE_URL;
if (!connectionString) {
  console.error('No Postgres connection string found. Set POSTGRES_URL or DATABASE_URL.');
  process.exit(1);
}

const pool = new Pool({
  connectionString,
  ssl: connectionString.includes('sslmode=') ? false : { rejectUnauthorized: false }
});

const query = (text, params) => pool.query(text, params);

async function migrate() {
  await query(
    `CREATE TABLE IF NOT EXISTS categories (
      id BIGSERIAL PRIMARY KEY,
      name TEXT NOT NULL UNIQUE,
      color TEXT,
      created_at TIMESTAMPTZ DEFAULT NOW()
    )`
  );

  await query(
    `CREATE TABLE IF NOT EXISTS expenses (
      id BIGSERIAL PRIMARY KEY,
      category_id BIGINT REFERENCES categories(id) ON DELETE SET NULL,
      amount DOUBLE PRECISION NOT NULL,
      note TEXT,
      occurred_at TIMESTAMPTZ NOT NULL,
      created_at TIMESTAMPTZ DEFAULT NOW()
    )`
  );

  await query(`CREATE INDEX IF NOT EXISTS idx_expenses_occurred_at ON expenses (occurred_at DESC)`);
  await query(`CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses (category_id)`);

  await query(
    `INSERT INTO categories (id, name, color) VALUES
      (1, 'Food & Drink', '#4f46e5'),
      (2, 'Transport', '#0ea5e9'),
      (3, 'Groceries', '#10b981'),
      (4, 'Bills & Utilities', '#f59e0b'),
      (5, 'Shopping', '#e11d48'),
      (6, 'Health', '#22c55e'),
      (7, 'Leisure', '#8b5cf6'),
      (8, 'Other', '#94a3b8')
    ON CONFLICT (id) DO NOTHING`
  );

  await query(
    `SELECT setval(pg_get_serial_sequence('categories','id'), COALESCE((SELECT MAX(id) FROM categories), 1))`
  );

  console.log('Migration completed.');
  await pool.end();
}

migrate().catch((err) => {
  console.error(err);
  process.exit(1);
});
