import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale
} from 'chart.js';
import { Doughnut, Line } from 'react-chartjs-2';

ChartJS.register(ArcElement, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale);

const API_BASE = 'http://localhost:8000';
const LAST_CATEGORY_KEY = 'mini-fin:last-category';

export default function App() {
  const amountRef = useRef(null);
  const [categories, setCategories] = useState([]);
  const [dailyTrend, setDailyTrend] = useState([]);
  const [categorySummary, setCategorySummary] = useState([]);
  const [todayStats, setTodayStats] = useState({ count: 0, total: 0 });
  const [dayExpenses, setDayExpenses] = useState([]);
  const [selectedDay, setSelectedDay] = useState(() => new Date().toISOString().slice(0, 10));
  const [toast, setToast] = useState('');
  const [loading, setLoading] = useState(false);

  const lastCategory = useMemo(() => window.localStorage.getItem(LAST_CATEGORY_KEY), []);

  const [form, setForm] = useState({
    amount: '',
    category_id: lastCategory ? Number(lastCategory) : '',
    note: '',
    occurred_at: defaultDateTimeValue()
  });

  useEffect(() => {
    amountRef.current?.focus();
    refreshAll();
  }, []);

  const refreshAll = async () => {
    setLoading(true);
    await Promise.all([loadCategories(), loadDailyTrend(), loadCategorySummary(), loadDay(selectedDay)]);
    await loadToday();
    setLoading(false);
  };

  const loadCategories = async () => {
    const res = await apiGet('/categories');
    setCategories(res.data || []);
  };

  const loadDailyTrend = async () => {
    const res = await apiGet('/summary/daily?days=7');
    setDailyTrend(res.data || []);
  };

  const loadCategorySummary = async () => {
    const res = await apiGet('/summary/categories?days=30');
    setCategorySummary(res.data || []);
  };

  const loadToday = async () => {
    const today = new Date().toISOString().slice(0, 10);
    const res = await apiGet(`/expenses?from=${today}`);
    const data = res.data || [];
    const total = data.reduce((sum, e) => sum + Number(e.amount || 0), 0);
    setTodayStats({ count: data.length, total });
  };

  const loadDay = useCallback(
    async (day) => {
      const from = `${day}T00:00:00`;
      const to = `${day}T23:59:59`;
      const res = await apiGet(`/expenses?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
      setDayExpenses((res.data || []).slice(0, 20));
      setSelectedDay(day);
    },
    [setDayExpenses, setSelectedDay]
  );

  const handleDayClick = useCallback(
    async (items, event) => {
      if (!items || items.length === 0) return;
      const pointIndex = items[0].index;
      const point = dailyTrend[pointIndex];
      if (!point) return;
      await loadDay(point.day);
    },
    [dailyTrend, loadDay]
  );
  };

  const handleChange = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!form.amount || !form.category_id) return;

    await apiPost('/expenses', {
      amount: Number(form.amount),
      category_id: Number(form.category_id),
      note: form.note,
      occurred_at: form.occurred_at
    });

    window.localStorage.setItem(LAST_CATEGORY_KEY, String(form.category_id));
    setToast('Expense saved ✓');
    setTimeout(() => setToast(''), 2200);
    setForm((prev) => ({
      ...prev,
      amount: '',
      note: '',
      category_id: Number(form.category_id),
      occurred_at: defaultDateTimeValue()
    }));
    await loadDailyTrend();
    await loadCategorySummary();
    await loadToday();
  };

  const trendChart = useMemo(() => buildTrendChart(dailyTrend, handleDayClick), [dailyTrend, handleDayClick]);
  const categoryChart = useMemo(() => buildCategoryChart(categorySummary), [categorySummary]);

  return (
    <div className="page">
      <header className="header">
        <div>
          <p className="eyebrow">Spending awareness</p>
          <h1>Your Spending This Week</h1>
        </div>
        <button className="ghost" onClick={refreshAll} disabled={loading}>
          {loading ? 'Refreshing...' : 'Refresh data'}
        </button>
      </header>

      <div className="grid two">
        <NudgeCard stats={todayStats} />
        <QuickAddForm
          amountRef={amountRef}
          categories={categories}
          form={form}
          onChange={handleChange}
          onSubmit={handleSubmit}
          toast={toast}
        />
      </div>

      <div className="grid two">
        <Card title="Your Spending This Week">
          {dailyTrend.length === 0 ? (
            <EmptyState message="No spending logged yet." />
          ) : (
            <div className="chart">
              <Line data={trendChart.data} options={trendChart.options} />
            </div>
          )}
        </Card>

        <Card title="Where Your Money Went">
          {categorySummary.length === 0 ? (
            <EmptyState message="Add a few expenses to see your category breakdown." />
          ) : (
            <div className="chart">
              <Doughnut data={categoryChart.data} options={categoryChart.options} />
            </div>
          )}
        </Card>
      </div>

      <Card title="Today">
        {dayExpenses.length === 0 ? (
          <EmptyState message="Select a day on the chart to view its entries." />
        ) : (
          <ul className="list">
            <div className="muted" style={{ marginBottom: 8 }}>
              Entries for {selectedDay}
            </div>
            {dayExpenses.map((item) => (
              <li key={item.id} className="list-row">
                <div>
                  <div className="label">{item.note || 'Expense'}</div>
                  <div className="muted">
                    {item.category_name || 'Category'} · {formatTime(item.occurred_at)}
                  </div>
                </div>
                <div className="amount">Rp {formatNumber(item.amount)}</div>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}

function NudgeCard({ stats }) {
  const hasToday = stats.count > 0;
  const message = hasToday
    ? `You've logged ${stats.count} expense${stats.count > 1 ? 's' : ''} today. Today’s spending so far: Rp ${formatNumber(stats.total)}`
    : "You haven't logged any expenses today. Add your first one to start tracking.";

  return (
    <Card>
      <div className="nudge">
        <div className="dot" />
        <p className="nudge-text">{message}</p>
      </div>
    </Card>
  );
}

function QuickAddForm({ categories, form, onChange, onSubmit, toast, amountRef }) {
  return (
    <Card title="Quick add">
      <form className="form" onSubmit={onSubmit}>
        <label>
          Amount
          <input
            ref={amountRef}
            type="number"
            min="0"
            step="1000"
            placeholder="e.g., 45000"
            value={form.amount}
            onChange={(e) => onChange('amount', e.target.value)}
            required
            autoFocus
          />
        </label>
        <label>
          Category
          <select value={form.category_id} onChange={(e) => onChange('category_id', e.target.value)} required>
            <option value="">Select category</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          Note <span className="muted">(optional)</span>
          <input
            type="text"
            placeholder="What was this for?"
            value={form.note}
            onChange={(e) => onChange('note', e.target.value)}
          />
        </label>
        <label>
          Date & time
          <input
            type="datetime-local"
            value={form.occurred_at}
            onChange={(e) => onChange('occurred_at', e.target.value)}
            required
          />
        </label>
        <div className="actions">
          <button type="submit">Save expense</button>
          {toast && <span className="toast">{toast}</span>}
        </div>
      </form>
    </Card>
  );
}

function Card({ title, children }) {
  return (
    <section className="card">
      {title && <div className="card-title">{title}</div>}
      {children}
    </section>
  );
}

function EmptyState({ message }) {
  return <div className="empty">{message}</div>;
}

function formatNumber(num) {
  return Number(num || 0).toLocaleString('id-ID');
}

function formatTime(value) {
  const date = new Date(value);
  return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function defaultDateTimeValue() {
  const now = new Date();
  const tzOffset = now.getTimezoneOffset() * 60000;
  return new Date(Date.now() - tzOffset).toISOString().slice(0, 16);
}

function buildTrendChart(points, onPointClick) {
  const labels = points.map((p) => p.day);
  const data = points.map((p) => Number(p.total || 0));
  return {
    data: {
      labels,
      datasets: [
        {
          label: 'Daily spend',
          data,
          borderColor: '#2563eb',
          backgroundColor: 'rgba(37,99,235,0.1)',
          tension: 0.3,
          fill: true,
          pointRadius: 3
        }
      ]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { ticks: { callback: (v) => `Rp ${formatNumber(v)}` } } },
      responsive: true,
      maintainAspectRatio: false,
      onClick: (_, elements) => onPointClick && onPointClick(elements)
    }
  };
}

function buildCategoryChart(summary) {
  const labels = summary.map((c) => c.name || 'Uncategorized');
  const data = summary.map((c) => Number(c.total || 0));
  const colors = summary.map((c) => c.color || '#94a3b8');
  return {
    data: {
      labels,
      datasets: [
        {
          data,
          backgroundColor: colors,
          borderWidth: 0
        }
      ]
    },
    options: {
      plugins: { legend: { position: 'right' } },
      responsive: true,
      maintainAspectRatio: false
    }
  };
}

async function apiGet(path) {
  const res = await fetch(API_BASE + path);
  if (!res.ok) {
    throw new Error(`GET ${path} failed`);
  }
  return res.json();
}

async function apiPost(path, body) {
  const res = await fetch(API_BASE + path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.error || `POST ${path} failed`);
  }
  return res.json();
}
