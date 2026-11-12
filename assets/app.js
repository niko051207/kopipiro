/**
 * Cart drawer.
 *
 * Adds products without leaving the page, keeps quantities and totals in sync,
 * and can place the order right inside the drawer. Everything is priced on the
 * server (cart-api.php); this file only renders what the server returns.
 */
(() => {
  const drawer = document.querySelector('[data-cart-drawer]');
  if (!drawer) return;

  const overlay = document.querySelector('[data-cart-overlay]');
  const itemsEl = drawer.querySelector('[data-cart-items]');
  const emptyEl = drawer.querySelector('[data-cart-empty]');
  const footEl = drawer.querySelector('[data-cart-foot]');
  const successEl = drawer.querySelector('[data-cart-success]');
  const form = drawer.querySelector('[data-cart-checkout]');
  const nameInput = form.querySelector('[name="customer_name"]');
  const phoneInput = form.querySelector('[name="phone"]');
  const errorEl = drawer.querySelector('[data-cart-error]');
  const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  // The drawer markup ships hidden. Only reveal it when the drawer stylesheet
  // is actually active, so a stale cache can never dump it on top of the page.
  const styled = getComputedStyle(drawer).position === 'fixed';
  if (styled) {
    drawer.hidden = false;
    overlay.hidden = false;
  }

  let state = null;
  let lastFocus = null;
  let toastTimer = null;

  const rupiah = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
  const escapeHtml = value => String(value).replace(/[&<>"']/g, ch => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[ch]));

  function setBadge(count) {
    document.querySelectorAll('[data-cart-count]').forEach(el => { el.textContent = count; });
  }

  function openDrawer() {
    if (!styled) return;
    lastFocus = document.activeElement;
    drawer.hidden = false;
    overlay.hidden = false;
    drawer.classList.add('open');
    overlay.classList.add('open');
    document.body.classList.add('cart-open');
    drawer.setAttribute('aria-hidden', 'false');
    const close = drawer.querySelector('[data-cart-close]');
    if (close) close.focus();
  }

  function closeDrawer() {
    drawer.classList.remove('open');
    overlay.classList.remove('open');
    document.body.classList.remove('cart-open');
    drawer.setAttribute('aria-hidden', 'true');
    successEl.hidden = true;
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  function toast(message, isError) {
    let el = document.querySelector('.cart-toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'cart-toast';
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.toggle('error', Boolean(isError));
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 2400);
  }

  function itemMarkup(item) {
    return `<div class="cart-item">
      <img src="${escapeHtml(item.image || 'assets/img/coffee.svg')}" alt="">
      <div class="cart-item-info">
        <strong>${escapeHtml(item.name)}</strong>
        <span>${rupiah(item.unit_price)}</span>
        <div class="cart-qty">
          <button type="button" data-cart-dec="${item.id}" aria-label="Kurangi ${escapeHtml(item.name)}">−</button>
          <span>${item.quantity}</span>
          <button type="button" data-cart-inc="${item.id}" aria-label="Tambah ${escapeHtml(item.name)}">+</button>
        </div>
      </div>
      <div class="cart-item-side">
        <strong>${rupiah(item.line_total)}</strong>
        <button type="button" class="cart-remove" data-cart-remove="${item.id}">Hapus</button>
      </div>
    </div>`;
  }

  function render(cart) {
    state = cart;
    setBadge(cart.count);
    successEl.hidden = true;
    errorEl.hidden = true;

    if (!cart.items.length) {
      itemsEl.innerHTML = '';
      emptyEl.hidden = false;
      footEl.hidden = true;
      return;
    }

    emptyEl.hidden = true;
    footEl.hidden = false;
    itemsEl.innerHTML = cart.items.map(itemMarkup).join('');

    drawer.querySelector('[data-cart-subtotal]').textContent = rupiah(cart.subtotal);
    drawer.querySelector('[data-cart-total]').textContent = rupiah(cart.total);
    drawer.querySelector('[data-cart-discount]').textContent = '− ' + rupiah(cart.discount);
    drawer.querySelector('[data-cart-discount-row]').hidden = cart.discount <= 0;

    nameInput.readOnly = cart.member;
    if (cart.member) {
      nameInput.value = cart.user_name;
    }

    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.textContent = 'Pesan sekarang · ' + rupiah(cart.total);
  }

  function showSuccess(order) {
    itemsEl.innerHTML = '';
    emptyEl.hidden = true;
    footEl.hidden = true;
    successEl.hidden = false;
    drawer.querySelector('[data-cart-order-id]').textContent = '#' + order.id;
    drawer.querySelector('[data-cart-order-total]').textContent = rupiah(order.total);
    phoneInput.value = '';
  }

  async function request(method, payload) {
    const headers = { Accept: 'application/json', 'X-CSRF-Token': csrf };
    if (payload) headers['Content-Type'] = 'application/json';
    const response = await fetch('cart-api.php', {
      method,
      headers,
      body: payload ? JSON.stringify(payload) : undefined,
      credentials: 'same-origin'
    });
    let data = {};
    try {
      data = await response.json();
    } catch (error) {
      data = {};
    }
    if (!response.ok) {
      throw new Error(data.message || 'Terjadi kesalahan. Coba lagi.');
    }
    return data;
  }

  async function mutate(action, id, quantity) {
    try {
      const data = await request('POST', { action, product_id: id, quantity });
      render(data.cart);
      return data;
    } catch (error) {
      toast(error.message, true);
      throw error;
    }
  }

  document.querySelectorAll('form.js-add-to-cart').forEach(formEl => {
    formEl.addEventListener('submit', async event => {
      if (!styled) return;
      event.preventDefault();
      const button = formEl.querySelector('button');
      const id = Number(formEl.querySelector('[name="product_id"]').value);
      const wasEmpty = !state || state.count === 0;
      if (button) {
        button.classList.add('is-pop');
        button.disabled = true;
      }
      try {
        const data = await mutate('add', id, 1);
        const added = data && data.cart ? data.cart.items.find(entry => entry.id === id) : null;
        // Only the first item opens the drawer so the visitor learns where the
        // cart lives. Later adds stay on the page with a short confirmation.
        if (wasEmpty) openDrawer();
        toast(added ? added.name + ' ditambahkan ke keranjang' : 'Ditambahkan ke keranjang');
      } catch (error) {
        // mutate() already reported the error in a toast.
      } finally {
        if (button) {
          button.disabled = false;
          setTimeout(() => button.classList.remove('is-pop'), 320);
        }
      }
    });
  });

  drawer.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;

    const inc = button.getAttribute('data-cart-inc');
    const dec = button.getAttribute('data-cart-dec');
    const remove = button.getAttribute('data-cart-remove');
    if (!state) return;

    if (inc !== null) {
      const item = state.items.find(entry => entry.id === Number(inc));
      if (item) mutate('set', item.id, item.quantity + 1).catch(() => {});
    } else if (dec !== null) {
      const item = state.items.find(entry => entry.id === Number(dec));
      if (item) mutate('set', item.id, item.quantity - 1).catch(() => {});
    } else if (remove !== null) {
      mutate('remove', Number(remove), 0).catch(() => {});
    }
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    errorEl.hidden = true;
    const submit = form.querySelector('button[type="submit"]');
    const label = submit.textContent;
    submit.disabled = true;
    submit.textContent = 'Memproses...';
    try {
      const data = await request('POST', {
        action: 'checkout',
        customer_name: nameInput.value,
        phone: phoneInput.value
      });
      render(data.cart);
      showSuccess(data.order);
      toast('Pesanan diterima');
    } catch (error) {
      errorEl.textContent = error.message;
      errorEl.hidden = false;
    } finally {
      submit.disabled = false;
      submit.textContent = label;
    }
  });

  document.addEventListener('click', event => {
    if (event.target.closest('[data-cart-open]')) {
      if (!styled) return;
      event.preventDefault();
      openDrawer();
    } else if (event.target.closest('[data-cart-close]')) {
      event.preventDefault();
      closeDrawer();
    }
  });
  overlay.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && drawer.classList.contains('open')) closeDrawer();
  });

  // checkout.php sends old links here with ?cart=open so the drawer takes over.
  const wantsCart = new URLSearchParams(window.location.search).get('cart') === 'open';

  // Sync the badge with the server and drop products that became unavailable.
  request('GET')
    .then(data => {
      if (data.cart) render(data.cart);
      if (wantsCart) {
        openDrawer();
        if (window.history && history.replaceState) {
          const url = new URL(window.location.href);
          url.searchParams.delete('cart');
          history.replaceState(null, '', url.pathname + url.search + url.hash);
        }
      }
    })
    .catch(() => {});
})();
