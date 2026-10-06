const imgUrl = (id, dl) => `api/file.php?t=img&id=${id}${dl ? '&download=1' : ''}`;
const proofUrl = id => `api/file.php?t=proof&id=${id}`;
const respLabel = { pending: 'Waiting for approval', approved: 'Approved', changes: 'Changes requested' };

const photos = (o, dl) => o.images.length
  ? `<div class="thumbs">${o.images.map(i => `<figure><a href="${imgUrl(i.id)}" target="_blank"><img src="${imgUrl(i.id)}" alt="${esc(i.original_name)}" loading="lazy"></a>${dl ? `<a class="dl" href="${imgUrl(i.id, 1)}">Download</a>` : ''}</figure>`).join('')}</div>` : '';

// newest layout first; only the newest unanswered one gets the approve / change buttons (customer view)
const proofsHtml = (o, customer) => o.proofs.slice().reverse().map((p, k) => `
  <div class="proof"><a href="${proofUrl(p.id)}" target="_blank"><img src="${proofUrl(p.id)}" alt="Layout ${o.proofs.length - k}"></a>
  <div><strong>Layout ${o.proofs.length - k}</strong> <span class="badge r-${p.response}">${respLabel[p.response]}</span>
    ${p.note ? `<p>${esc(p.note)}</p>` : ''}${p.feedback ? `<p class="muted">Customer: ${esc(p.feedback)}</p>` : ''}
    ${customer && p.response === 'pending' && k === 0 ? `<div class="respond" data-proof="${p.id}">
      <textarea rows="2" placeholder="What should we change? (required if you request changes)"></textarea>
      <button class="btn small" data-approve>Approve layout</button> <button class="btn small ghost" data-changes>Request changes</button></div>` : ''}
  </div></div>`).join('');

Object.assign(pages, {
    async order() {
    const o = await api('orders', 'options');
    const th = $('#theme'), sz = $('#size'), rush = $('#rush'), price = $('#price'), bd = $('#birthday'), ev = $('#event_date'), dl = $('#deadline');
    th.innerHTML = o.themes.map(t => `<option>${esc(t)}</option>`).join('');
    sz.innerHTML = o.sizes.map(s => `<option value="${esc(s.label)}" data-price="${s.price}">${esc(s.label)} (${peso(s.price)})</option>`).join('');
    $('#rushlabel').textContent = `Rush order (+${Math.round(o.rush_rate * 100)}%)`;
    $('#fee').textContent = `+${peso(o.layout_fee)}`;
    ev.min = dl.min = todayStr();
    const sync = () => {
      const layout = document.querySelector('[name=service]:checked').value === 'layout';
      document.querySelectorAll('.event-only').forEach(el => (el.hidden = !layout));
      ev.required = layout;
      dl.max = layout ? ev.value : '';
      bd.hidden = !layout || th.value !== 'Birthday';
      bd.querySelectorAll('input[name^=celebrant]').forEach(i => (i.required = !bd.hidden));
      const base = Number(sz.selectedOptions[0]?.dataset.price || 0) + (layout ? o.layout_fee : 0);
      price.textContent = peso(base * (rush.checked ? 1 + o.rush_rate : 1));
    };
    $('#form').addEventListener('change', sync);
    ev.addEventListener('change', () => (dl.max = ev.value));
    sync();
    formSubmit('#form', async fd => {
      await api('orders', 'create', { method: 'POST', body: fd });
      location.href = 'my-orders.html?placed=1';
    });
  },

  async myorders() {
    if (location.search.includes('placed')) show($('#msg'), 'Order placed. The status will update here as we work on it.', 'ok');
    const list = $('#list');
    const load = async () => {
      const { orders } = await api('orders', 'mine');
      list.innerHTML = orders.map(o => {
        const flow = flowFor(o), i = flow.indexOf(o.status);
        return `<article class="card order">
          <div class="row"><h2>#${o.id} ${esc(o.theme)} tarp, ${esc(o.size)}</h2>${o.is_rush == 1 ? '<span class="rush">Rush</span>' : ''}</div>
          <p class="muted">${o.service === 'layout' ? 'Layout first' : 'Print only'} · Event ${esc(o.event_date)} · Needed by ${esc(o.deadline)} · ${peso(o.price)} · ${o.paid_at ? 'Paid' : 'Unpaid'}</p>
          ${o.celebrant_name ? `<p>For ${esc(o.celebrant_name)}, turning ${esc(o.celebrant_age)}</p>` : ''}
          ${o.status === 'For approval' ? '<p class="notice">Your layout is ready. Please review it below and approve it, or ask for changes. We start printing after you approve.</p>' : ''}
          <ol class="track">${flow.map((s, k) => `<li class="${k <= i ? 'done' : ''}">${s}</li>`).join('')}</ol>
          ${photos(o, false)}${proofsHtml(o, true)}</article>`;
      }).join('') || '<p class="muted">No orders yet. <a href="order.html">Place your first order</a>.</p>';
    };
    list.addEventListener('click', e => {
      const box = e.target.closest('.respond'), approve = e.target.matches('[data-approve]');
      if (!box || !(approve || e.target.matches('[data-changes]'))) return;
      api('orders', 'proof_respond', { method: 'POST', body: { proof_id: box.dataset.proof, response: approve ? 'approved' : 'changes', feedback: box.querySelector('textarea').value } })
        .then(load).catch(fail);
    });
    load();
  },

  async adminorders() {
    const sel = $('#status'), srt = $('#sort'), rows = $('#rows'), dlg = $('#dlg');
    let data = {};
    const row = o => {
      const l = daysLeft(o.deadline);
      return `<tr class="${o.is_rush == 1 ? 'is-rush' : ''}">
        <td>#${o.id} ${esc(o.theme)}, ${esc(o.size)} ${o.is_rush == 1 ? '<span class="rush">Rush</span>' : ''}<br>
          <small class="muted">${o.service === 'layout' ? 'Layout first' : 'Print only'} · ${o.images.length} photo${o.images.length === 1 ? '' : 's'}</small><br>
          <button class="btn small ghost" data-open="${o.id}">Open photos and details</button></td>
        <td>${esc(o.cname)}<br><small class="muted">${esc(o.phone)}</small></td>
        <td>${esc(o.event_date)}</td>
        <td>${esc(o.deadline)}${o.status === 'Claimed' ? '' : `<br><small class="${l.late ? 'late' : 'muted'}">${l.text}</small>`}</td>
        <td>${peso(o.price)}<br><button class="btn small ${o.paid_at ? '' : 'ghost'}" data-pay="${o.id}">${o.paid_at ? 'Paid' : 'Mark paid'}</button></td>
        <td><select data-status="${o.id}">${flowFor(o).map(s => `<option ${s === o.status ? 'selected' : ''} ${s === 'For approval' || s === 'Approved' ? 'disabled' : ''}>${s}</option>`).join('')}</select></td></tr>`;
    };
    const openOrder = id => {
      const o = data[id]; if (!o) return;
      dlg.querySelector('.body').innerHTML = `
        <h2>#${o.id} ${esc(o.theme)} tarp, ${esc(o.size)} ${o.is_rush == 1 ? '<span class="rush">Rush</span>' : ''}</h2>
        <p>${esc(o.cname)} · ${esc(o.phone)}<br>Event ${esc(o.event_date)} · Needed by ${esc(o.deadline)} · ${o.service === 'layout' ? 'Layout first' : 'Print only'} · <span class="badge s-${o.status.replace(' ', '-')}">${o.status}</span></p>
        ${o.celebrant_name ? `<p>Celebrant: ${esc(o.celebrant_name)}, ${esc(o.celebrant_age)}</p>` : ''}
        ${o.notes ? `<p>Notes: ${esc(o.notes)}</p>` : ''}
        <h3>Customer photos</h3>${photos(o, true) || '<p class="muted">No photos uploaded.</p>'}
        ${o.service === 'layout' ? `<h3>Layout proofs</h3>${proofsHtml(o, false) || '<p class="muted">No layout sent yet.</p>'}
          <form class="proofform" data-id="${o.id}">
            <label>Layout image to send<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required></label>
            <label>Note to customer (optional)<input name="note"></label>
            <button class="btn small">Send layout for approval</button></form>` : ''}`;
      if (!dlg.open) dlg.showModal();
    };
    let current = 0;
    const load = async () => {
      const { orders } = await api('orders', 'list', { params: { status: sel.value, sort: srt.value } });
      data = Object.fromEntries(orders.map(o => [o.id, o]));
      rows.innerHTML = orders.map(row).join('') || '<tr><td colspan="6" class="muted">No orders match this filter.</td></tr>';
      if (dlg.open) openOrder(current);
    };
    sel.addEventListener('change', () => load().catch(fail));
    srt.addEventListener('change', () => load().catch(fail));
    rows.addEventListener('change', e => {
      if (e.target.dataset.status === undefined) return;
      api('orders', 'update', { method: 'POST', body: { id: e.target.dataset.status, status: e.target.value } })
        .then(load).catch(err => { fail(err); load(); });
    });
    rows.addEventListener('click', e => {
      const pay = e.target.closest('[data-pay]'), op = e.target.closest('[data-open]');
      if (pay) api('orders', 'update', { method: 'POST', body: { id: pay.dataset.pay, pay: true } }).then(load).catch(fail);
      if (op) { current = op.dataset.open; openOrder(current); }
    });
    dlg.addEventListener('click', e => { if (e.target.matches('[data-close]')) dlg.close(); });
    dlg.addEventListener('submit', e => {
      e.preventDefault();
      const f = e.target, fd = new FormData(f); fd.append('order_id', f.dataset.id);
      f.querySelector('button').disabled = true;
      api('orders', 'proof_upload', { method: 'POST', body: fd }).then(load).catch(err => { fail(err); f.querySelector('button').disabled = false; });
    });
    load();
  },

  async adminpricing() {
    const box = $('#sizes'), msg = $('#msg');
    const row = (s, isNew) => `<tr data-id="${s.id}">
      <td><input class="l" value="${esc(s.label)}" placeholder="e.g. 3x5 ft" aria-label="Size name"></td>
      <td><input class="p" type="number" min="1" step="0.01" value="${esc(s.price)}" placeholder="Price" aria-label="Price"></td>
      <td><label class="check"><input class="a" type="checkbox" ${s.active == 1 ? 'checked' : ''}>Available</label></td>
      <td><button class="btn small" data-save>${isNew ? 'Add size' : 'Save'}</button> ${isNew ? '' : '<button class="btn small ghost" data-del>Delete</button>'}</td></tr>`;
    const load = async () => {
      const d = await api('admin', 'pricing');
      box.innerHTML = d.sizes.map(s => row(s)).join('') + row({ id: 0, label: '', price: '', active: 1 }, true);
      $('#rush').value = Math.round(d.rush_rate * 100); $('#layout_fee').value = d.layout_fee;
    };
    box.addEventListener('click', async e => {
      const tr = e.target.closest('tr'); if (!tr) return;
      try {
        if (e.target.matches('[data-save]')) {
          await api('admin', 'size_save', { method: 'POST', body: { id: tr.dataset.id, label: tr.querySelector('.l').value, price: tr.querySelector('.p').value, active: tr.querySelector('.a').checked } });
          show(msg, 'Size saved.', 'ok'); load();
        }
        if (e.target.matches('[data-del]') && confirm('Delete this size? Past orders keep their size and price.')) {
          await api('admin', 'size_delete', { method: 'POST', body: { id: tr.dataset.id } });
          show(msg, 'Size deleted.', 'ok'); load();
        }
      } catch (err) { show(msg, err.message); }
    });
    formSubmit('#settings', async fd => {
      await api('admin', 'settings_save', { method: 'POST', body: Object.fromEntries(fd) });
      show(msg, 'Settings saved.', 'ok');
    });
    load();
  },
});
