(async () => {
  const page = document.body.dataset.page, role = document.body.dataset.role;
  let user = null;
  try { ({ user } = await api('auth', 'me')); }
  catch (e) { $('main').insertAdjacentHTML('afterbegin', `<p class="alert">${esc(e.message)}</p>`); return; }
  if (role && (!user || user.role !== role)) { location.href = user ? home(user) : 'login.html'; return; }
  if (!role && user) { location.href = home(user); return; }
  $('#nav').innerHTML = !user
    ? '<a href="login.html">Log in</a><a href="register.html">Sign up</a>'
    : user.role === 'admin'
      ? '<a href="admin.html">Dashboard</a><a href="admin-orders.html">Orders</a><a href="admin-reports.html">Income</a><a href="admin-pricing.html">Pricing</a><a href="#" id="logout">Log out</a>'
      : '<a href="order.html">New order</a><a href="my-orders.html">My orders</a><a href="#" id="logout">Log out</a>';
  $('#logout')?.addEventListener('click', async e => {
    e.preventDefault(); await api('auth', 'logout', { method: 'POST' }); location.href = 'login.html';
  });
  try { await pages[page]?.(user); } catch (e) { show($('#msg') || $('main').insertAdjacentElement('afterbegin', document.createElement('p')), e.message); }
})();
