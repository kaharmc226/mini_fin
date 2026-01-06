import express from 'express';
import cors from 'cors';
import { sql } from '@vercel/postgres';
import { DateTime } from 'luxon';

const app = express();
const PORT = process.env.PORT || 8000;
const corsOrigin = process.env.API_CORS_ORIGIN || '*';

app.use(cors({ origin: corsOrigin === '*' ? true : corsOrigin }));
app.use(express.json());

app.get('/api/health', (req, res) => {
  res.json({ status: 'ok' });
});

app.get('/api/categories', async (req, res) => {
  try {
    const { rows } = await sql`SELECT id, name, color, created_at FROM categories ORDER BY name ASC`;
    res.json({ data: rows });
  } catch (err) {
    sendError(res, err);
  }
});

app.post('/api/categories', async (req, res) => {
  const { name, color } = req.body || {};
  if (!name || !name.trim()) return badRequest(res, 'Category name is required.');
  try {
    const { rows } = await sql`
      INSERT INTO categories (name, color)
      VALUES (${name.trim()}, ${color || null})
      ON CONFLICT (name) DO NOTHING
      RETURNING id, name, color
    `;
    if (rows.length === 0) return badRequest(res, 'Category already exists.');
    res.status(201).json(rows[0]);
  } catch (err) {
    sendError(res, err);
  }
});

app.get('/api/expenses', async (req, res) => {
  const { from, to } = req.query;
  const where = [];
  const params = [];

  if (from) {
    params.push(from);
    where.push(`e.occurred_at >= $${params.length}`);
  }
  if (to) {
    params.push(to);
    where.push(`e.occurred_at <= $${params.length}`);
  }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const query = `
    SELECT e.id, e.amount, e.note, e.occurred_at, e.created_at, e.category_id,
           c.name AS category_name, c.color AS category_color
    FROM expenses e
    LEFT JOIN categories c ON e.category_id = c.id
    ${whereSql}
    ORDER BY e.occurred_at DESC, e.id DESC
  `;
  try {
    const { rows } = await sql.unsafe(query, params);
    res.json({ data: rows });
  } catch (err) {
    sendError(res, err);
  }
});

app.post('/api/expenses', async (req, res) => {
  const { amount, category_id, note, occurred_at } = req.body || {};
  if (!amount || Number(amount) <= 0) return badRequest(res, 'Amount must be greater than zero.');
  if (!category_id) return badRequest(res, 'Category is required.');
  if (!occurred_at) return badRequest(res, 'occurred_at is required (ISO date or datetime).');
  try {
    const { rows } = await sql`
      INSERT INTO expenses (amount, category_id, note, occurred_at)
      VALUES (${amount}, ${category_id}, ${note || null}, ${occurred_at})
      RETURNING id
    `;
    res.status(201).json(rows[0]);
  } catch (err) {
    sendError(res, err);
  }
});

app.get('/api/summary/daily', async (req, res) => {
  const days = Math.max(1, Number(req.query.days) || 7);
  const from = DateTime.now().minus({ days }).startOf('day').toISO();
  try {
    const { rows } = await sql`
      SELECT date_trunc('day', occurred_at) AS day, SUM(amount)::float AS total
      FROM expenses
      WHERE occurred_at >= ${from}
      GROUP BY day
      ORDER BY day ASC
    `;
    res.json({ data: rows.map((r) => ({ day: DateTime.fromJSDate(r.day).toISODate(), total: r.total })) });
  } catch (err) {
    sendError(res, err);
  }
});

app.get('/api/summary/categories', async (req, res) => {
  const days = Math.max(1, Number(req.query.days) || 30);
  const from = DateTime.now().minus({ days }).startOf('day').toISO();
  try {
    const { rows } = await sql`
      SELECT c.id, c.name, c.color, SUM(e.amount)::float AS total
      FROM expenses e
      LEFT JOIN categories c ON e.category_id = c.id
      WHERE e.occurred_at >= ${from}
      GROUP BY c.id, c.name, c.color
      ORDER BY total DESC
    `;
    res.json({ data: rows });
  } catch (err) {
    sendError(res, err);
  }
});

app.get('/api/summary/monthly', async (req, res) => {
  const { month } = req.query; // YYYY-MM
  const start = month ? DateTime.fromISO(month + '-01', { zone: 'utc' }) : DateTime.now().startOf('month');
  const end = start.endOf('month');
  try {
    const daily = await sql`
      SELECT date_trunc('day', occurred_at) AS day, SUM(amount)::float AS total
      FROM expenses
      WHERE occurred_at BETWEEN ${start.toISO()} AND ${end.toISO()}
      GROUP BY day
      ORDER BY day ASC
    `;
    const categories = await sql`
      SELECT c.id, c.name, c.color, SUM(e.amount)::float AS total
      FROM expenses e
      LEFT JOIN categories c ON e.category_id = c.id
      WHERE e.occurred_at BETWEEN ${start.toISO()} AND ${end.toISO()}
      GROUP BY c.id, c.name, c.color
      ORDER BY total DESC
    `;
    const totals = await sql`
      SELECT SUM(amount)::float AS total, COUNT(DISTINCT date_trunc('day', occurred_at)) AS days
      FROM expenses
      WHERE occurred_at BETWEEN ${start.toISO()} AND ${end.toISO()}
    `;
    const total = totals.rows[0]?.total || 0;
    const daysInMonth = start.daysInMonth;
    res.json({
      data: {
        month: start.toFormat('yyyy-MM'),
        daily: daily.rows.map((r) => ({ day: DateTime.fromJSDate(r.day).toISODate(), total: r.total })),
        categories: categories.rows,
        total,
        average_per_day: daysInMonth ? total / daysInMonth : 0,
        days_with_data: Number(totals.rows[0]?.days || 0),
      },
    });
  } catch (err) {
    sendError(res, err);
  }
});

function badRequest(res, message) {
  res.status(400).json({ error: message });
}

function sendError(res, err) {
  console.error(err);
  res.status(500).json({ error: 'Server error' });
}

// Local dev
if (process.env.VERCEL !== '1' && process.env.NODE_ENV !== 'production') {
  app.listen(PORT, () => {
    console.log(`API listening on http://localhost:${PORT}`);
  });
}

export default app;
