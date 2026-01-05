import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { BrowserRouter as Router, Routes, Route, NavLink } from 'react-router-dom';
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  BarElement
} from 'chart.js';
import { Doughnut, Line, Bar } from 'react-chartjs-2';

ChartJS.register(ArcElement, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, BarElement);

const API_BASE = 'http://localhost:8000';
const LAST_CATEGORY_KEY = 'mini-fin:last-category';

export default function App() {
  return (
    <Router>
      <div className="app-shell">
        <TopNav />
        <main className="page">
          <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/reports" element={<Reports />} />
          </Routes>
        </main>
      </div>
    </Router>
  );
}

function TopNav() {
  return (
    <nav className="top-nav">
      <div className="brand">Mini Fin</div>
      <div className="nav-links">
        <NavLink to="/" end>
          Dashboard
        </NavLink>
        <NavLink to="/reports">Reports</NavLink>
      </div>
    </nav>
  );
}

// Dashboard page: quick add, daily nudge, weekly trend, categories, entries.
function Dashboard() {
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
    async (items) => {
      if (!items || items.length === 0) return;
      const pointIndex = items[0].index;
      const point = dailyTrend[pointIndex];
      if (!point) return;
      await loadDay(point.day);
    },
    [dailyTrend, loadDay]
  );

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
    setToast('Expense saved');
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
    <>
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

      <Card title="Entries">
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
                    {item.category_name || 'Category'} - {formatTime(item.occurred_at)}
                  </div>
                </div>
                <div className="amount">Rp {formatNumber(item.amount)}</div>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </>
  );
}

// Reports page: month overview with daily bars and category breakdown.
function Reports() {
  const [month, setMonth] = useState(() => monthKey(new Date()));
  const [summary, setSummary] = useState({ daily: [], categories: [], total: 0, average_per_day: 0 });
  const [selectedDay, setSelectedDay] = useState(null);
  const [dayExpenses, setDayExpenses] = useState([]);

  useEffect(() => {
    loadMonth(month);
  }, [month]);

  const loadMonth = async (monthKeyValue) => {
    const res = await apiGet(`/summary/monthly?month=${monthKeyValue}`);
    setSummary(res.data || { daily: [], categories: [], total: 0, average_per_day: 0 });
    setSelectedDay(null);
    setDayExpenses([]);
  };

  const changeMonth = (delta) => {
    const [year, mon] = month.split('-').map((v) => Number(v));
    const next = new Date(year, mon - 1 + delta, 1);
    setMonth(monthKey(next));
  };

  const handleDayClick = async (items) => {
    if (!items || items.length === 0) return;
    const idx = items[0].index;
    const point = summary.daily[idx];
    if (!point) return;
    const day = point.day;
    const from = `${day}T00:00:00`;
    const to = `${day}T23:59:59`;
    const res = await apiGet(`/expenses?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
    setSelectedDay(day);
    setDayExpenses(res.data || []);
  };

  const dailyChart = useMemo(() => buildMonthBarChart(summary.daily, handleDayClick), [summary.daily]);
  const categoryChart = useMemo(() => buildCategoryChart(summary.categories), [summary.categories]);

  return (
    <>
      <header className="header">
        <div>
          <p className="eyebrow">Monthly overview</p>
          <h1>Reports</h1>
        </div>
        <div className="month-switcher">
          <button className="ghost" onClick={() => changeMonth(-1)}>{'<'}</button>
          <div className="month-label">{prettyMonth(month)}</div>
          <button className="ghost" onClick={() => changeMonth(1)}>{'>'}</button>
        </div>
      </header>

      <div className="grid two">
        <Card title="Month total">
          <div className="stat">
            <div className="stat-label">Total</div>
            <div className="stat-value">Rp {formatNumber(summary.total)}</div>
          </div>
          <div className="stat">
            <div className="stat-label">Average per day</div>
            <div className="stat-value">Rp {formatNumber(summary.average_per_day)}</div>
          </div>
        </Card>
        <Card title="Categories this month">
          {summary.categories.length === 0 ? (
            <EmptyState message="No data for this month yet." />
          ) : (
            <div className="chart">
              <Doughnut data={categoryChart.data} options={categoryChart.options} />
            </div>
          )}
        </Card>
      </div>

      <Card title="Daily totals">
        {summary.daily.length === 0 ? (
          <EmptyState message="No spending logged for this month." />
        ) : (
          <div className="chart">
            <Bar data={dailyChart.data} options={dailyChart.options} />
          </div>
        )}
      </Card>

      <Card title={selectedDay ? `Entries for ${selectedDay}` : 'Entries'}>
        {selectedDay === null || dayExpenses.length === 0 ? (
          <EmptyState message="Click a day bar to see its entries." />
        ) : (
          <ul className="list">
            {dayExpenses.map((item) => (
              <li key={item.id} className="list-row">
                <div>
                  <div className="label">{item.note || 'Expense'}</div>
                  <div className="muted">
                    {item.category_name || 'Category'} - {formatTime(item.occurred_at)}
                  </div>
                </div>
                <div className="amount">Rp {formatNumber(item.amount)}</div>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </>
  );
}

function NudgeCard({ stats }) {
  const hasToday = stats.count > 0;
  const message = hasToday
    ? `You've logged ${stats.count} expense${stats.count > 1 ? 's' : ''} today. Today's spending so far: Rp ${formatNumber(
        stats.total
      )}`
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

function monthKey(date) {
  const d = typeof date === 'string' ? new Date(date) : date;
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function prettyMonth(monthStr) {
  const [y, m] = monthStr.split('-').map((v) => Number(v));
  return new Date(y, m - 1, 1).toLocaleDateString('en', { month: 'long', year: 'numeric' });
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

function buildMonthBarChart(points, onPointClick) {
  const labels = points.map((p) => p.day.split('-')[2]); // day number
  const data = points.map((p) => Number(p.total || 0));
  return {
    data: {
      labels,
      datasets: [
        {
          label: 'Daily total',
          data,
          backgroundColor: '#2563eb'
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
