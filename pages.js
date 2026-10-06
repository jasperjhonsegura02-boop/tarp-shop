const home = u => (u.role === 'admin' ? 'admin.html' : 'my-orders.html');
const fail = err => alert(err.message);

const pages = {
  home() {},
  login() {
    formSubmit('#form', async fd => {
      const r = await api('auth', 'login', { method: 'POST', body: Object.fromEntries(fd) });
      location.href = home(r.user);
    });
  },
  register() {
    formSubmit('#form', async fd => {
      const r = await api('auth', 'register', { method: 'POST', body: Object.fromEntries(fd) });
      location.href = home(r.user);
    });
  },

  async admin() {
    const d = await api('admin', 'dashboard');
    $('#stats').innerHTML =
      STATUSES.map(s => `<div class="stat"><span>${d.counts[s]}</span>${s}</div>`).join('') +
      `<div class="stat hazard"><span>${d.rush}</span>Active rush orders</div>
       <div class="stat"><span>${peso(d.today)}</span>Income today</div>
       <div class="stat"><span>${peso(d.month)}</span>Income this month</div>`;
    $('#rows').innerHTML = d.due.map(o => {
      const l = daysLeft(o.deadline);
      return `<tr><td>#${o.id} ${esc(o.theme)}, ${esc(o.size)} ${o.is_rush == 1 ? '<span class="rush">Rush</span>' : ''}</td>
        <td>${esc(o.cname)}</td><td>${esc(o.deadline)} <small class="${l.late ? 'late' : 'muted'}">${l.text}</small></td>
        <td><span class="badge s-${o.status.replace(' ', '-')}">${o.status}</span></td></tr>`;
    }).join('') || '<tr><td colspan="4" class="muted">No active orders.</td></tr>';
  },

  adminreports() {
    const tabs = document.querySelectorAll('.tabs a');
    const load = async p => {
      tabs.forEach(t => t.classList.toggle('on', t.dataset.p === p));
      const { rows } = await api('admin', 'report', { params: { period: p } });
      const max = Math.max(1, ...rows.map(r => Number(r.total)));
      $('#rows').innerHTML = rows.map(r => `<tr><td>${esc(r.label)}</td><td>${r.n}</td><td>${peso(r.total)}</td>
        <td class="barcell"><span class="bar" style="width:${Math.round(r.total / max * 100)}%"></span></td></tr>`).join('')
        || '<tr><td colspan="4" class="muted">No paid orders yet. Mark orders as paid on the Orders page.</td></tr>';
    };
    tabs.forEach(t => t.addEventListener('click', e => { e.preventDefault(); load(t.dataset.p).catch(fail); }));
    load('week');
  },
};

