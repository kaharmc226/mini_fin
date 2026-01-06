import { sql } from '@vercel/postgres';
import { DateTime } from 'luxon';

async function seed() {
  // Clear existing expenses only (keep categories)
  await sql`DELETE FROM expenses`;

  const categories = await sql`SELECT id, name FROM categories ORDER BY id`;
  const categoryIds = categories.rows.map((c) => c.id);
  if (categoryIds.length === 0) {
    console.log('No categories found; run migrate first.');
    return;
  }

  const now = DateTime.now();
  const entries = [];
  const days = 60;

  for (let i = 0; i < 200; i++) {
    const dayOffset = Math.floor(Math.random() * days);
    const date = now.minus({ days: dayOffset }).set({ hour: rand(7, 22), minute: rand(0, 59) });
    const amount = rand(10, 200) * 1000;
    const category = categoryIds[Math.floor(Math.random() * categoryIds.length)];
    const note = sampleNote(category);
    entries.push({ amount, category_id: category, note, occurred_at: date.toISO() });
  }

  for (const e of entries) {
    await sql`
      INSERT INTO expenses (amount, category_id, note, occurred_at)
      VALUES (${e.amount}, ${e.category_id}, ${e.note}, ${e.occurred_at})
    `;
  }
  console.log(`Seeded ${entries.length} expenses.`);
}

function rand(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function sampleNote(categoryId) {
  const notes = {
    1: ['Coffee - Kopi Kenangan', 'Lunch - Warung', 'Dinner - Ayam Bakar', 'Snacks - Mart'],
    2: ['Ride-hailing - Grab', 'Bus ticket', 'Fuel - Pertamina'],
    3: ['Groceries - Supermarket', 'Market run', 'Fresh produce'],
    4: ['Electricity bill', 'Internet bill', 'Water bill'],
    5: ['Clothes - Online shop', 'Shoes', 'Accessories'],
    6: ['Pharmacy - Vitamins', 'Clinic visit', 'Supplements'],
    7: ['Movie night', 'Streaming subscription', 'Games top-up'],
    8: ['Misc purchase', 'Gift', 'Other'],
  };
  const pool = notes[categoryId] || ['Expense'];
  return pool[Math.floor(Math.random() * pool.length)];
}

seed().catch((err) => {
  console.error(err);
  process.exit(1);
});
