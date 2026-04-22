// Shared utilities
const API = {
  async post(endpoint, data = {}) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(data)) {
      if (Array.isArray(v)) v.forEach(item => fd.append(k + '[]', item));
      else fd.append(k, v);
    }
    const res = await fetch(`/api/${endpoint}.php`, { method: 'POST', body: fd });
    return res.json();
  },
  async get(endpoint, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`/api/${endpoint}.php${qs ? '?' + qs : ''}`);
    return res.json();
  },
  async upload(endpoint, formData) {
    const res = await fetch(`/api/${endpoint}.php`, { method: 'POST', body: formData });
    return res.json();
  }
};

function showAlert(container, msg, type = 'error') {
  const el = document.querySelector(container);
  if (!el) return;
  el.innerHTML = `<div class="alert alert-${type}">${escHtml(msg)}</div>`;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  setTimeout(() => { el.innerHTML = ''; }, 5000);
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtDate(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtDateTime(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function setLoading(btn, yes) {
  if (yes) {
    btn.dataset.label = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span>';
    btn.disabled = true;
  } else {
    btn.innerHTML = btn.dataset.label || btn.innerHTML;
    btn.disabled = false;
  }
}

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// Close modal on overlay click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) e.target.classList.add('hidden');
});

// Notifications
async function loadNotifications() {
  const res = await API.get('notifications', { action: 'list' });
  if (!res.success) return;
  const items = res.data;
  const badge = document.getElementById('notif-count');
  if (badge) {
    badge.textContent = items.length;
    badge.style.display = items.length ? 'flex' : 'none';
  }
  const list = document.getElementById('notif-list');
  if (!list) return;
  if (!items.length) {
    list.innerHTML = '<div class="notif-item text-muted">No new notifications</div>';
    return;
  }
  list.innerHTML = items.map(n => `
    <div class="notif-item unread" onclick="markRead(${n.id}, this)">
      <div>${escHtml(n.message)}</div>
      <div class="text-sm text-muted mt-1">${fmtDateTime(n.created_at)}</div>
    </div>`).join('');
}

async function markRead(id, el) {
  await API.post('notifications', { action: 'read', id });
  el.classList.remove('unread');
  loadNotifications();
}

async function markAllRead() {
  await API.post('notifications', { action: 'read_all' });
  loadNotifications();
}

function toggleNotifDropdown() {
  const dd = document.getElementById('notif-dropdown');
  if (!dd) return;
  const hidden = dd.style.display === 'none' || !dd.style.display;
  dd.style.display = hidden ? 'block' : 'none';
  if (hidden) loadNotifications();
}

document.addEventListener('click', e => {
  const dd = document.getElementById('notif-dropdown');
  const btn = document.getElementById('notif-btn');
  if (dd && btn && !dd.contains(e.target) && !btn.contains(e.target)) {
    dd.style.display = 'none';
  }
});

// Active sidebar link
document.addEventListener('DOMContentLoaded', () => {
  const page = new URLSearchParams(location.search).get('page') || 'dashboard';
  document.querySelectorAll('.sidebar nav a').forEach(a => {
    const href = new URLSearchParams(a.search).get('page') || 'dashboard';
    if (href === page) a.classList.add('active');
  });
  if (document.getElementById('notif-btn')) loadNotifications();
});
