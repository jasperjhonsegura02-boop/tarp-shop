const STATUSES = ['Pending', 'Designing', 'For approval', 'Approved', 'Printing', 'Ready', 'Claimed'];
const PRINT_FLOW = ['Pending', 'Printing', 'Ready', 'Claimed'];
const flowFor = o => (o.service === 'layout' ? STATUSES : PRINT_FLOW);
const $ = s => document.querySelector(s);

async function api(file, action, { method = 'GET', body, params = {} } = {}) {
  const q = new URLSearchParams({ action, ...params });
  const opt = { method, credentials: 'same-origin' };
  if (body instanceof FormData) opt.body = body;
  else if (body) { opt.body = JSON.stringify(body); opt.headers = { 'Content-Type': 'application/json' }; }
  const r = await fetch(`api/${file}.php?${q}`, opt);
  let data;
  try { data = await r.json(); }
  catch { throw new Error('The server did not return data. Open this site at http://localhost/tarp-shop/ with Apache running, not by double-clicking the file.'); }
  if (!r.ok) throw new Error(data.error || 'Something went wrong.');
  return data;
}
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const todayStr = () => { const d = new Date(); return new Date(d - d.getTimezoneOffset() * 6e4).toISOString().slice(0, 10); };
function daysLeft(date) {
  const t = new Date(); t.setHours(0, 0, 0, 0);
  const n = Math.round((new Date(date + 'T00:00:00') - t) / 864e5);
  if (n < 0) return { text: `${-n} day${n < -1 ? 's' : ''} overdue`, late: true };
  if (n === 0) return { text: 'Due today', late: false };
  return { text: `${n} day${n > 1 ? 's' : ''} left`, late: false };
}
function show(el, msg, cls = 'alert') { el.className = cls; el.textContent = msg; el.hidden = false; }
function formSubmit(sel, fn) {
  const f = $(sel), msg = $('#msg');
  f.addEventListener('submit', async e => {
    e.preventDefault(); msg.hidden = true;
    const b = f.querySelector('button'); b.disabled = true;
    try { await fn(new FormData(f)); } catch (err) { show(msg, err.message); }
    b.disabled = false;
  });
}
