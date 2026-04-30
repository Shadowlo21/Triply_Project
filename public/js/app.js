// Shared utilities
const API = {
  async post(endpoint, data = {}) {
    return Triply.fetch(endpoint, { method: 'POST', data });
  },
  async get(endpoint, params = {}) {
    return Triply.fetch(endpoint, { method: 'GET', params });
  },
  async upload(endpoint, formData) {
    return Triply.fetch(endpoint, { method: 'POST', body: formData });
  }
};

// Shared Triply helpers (required)
const Triply = {
  async fetch(endpoint, { method = 'GET', params = {}, data = {}, body = null } = {}) {
    try {
      let url = `/api/${endpoint}.php`;
      if (params && Object.keys(params).length) {
        const qs = new URLSearchParams(params).toString();
        url += `?${qs}`;
      }

      let fetchBody = body;
      if (!fetchBody && method.toUpperCase() !== 'GET') {
        const fd = new FormData();
        for (const [k, v] of Object.entries(data || {})) {
          if (Array.isArray(v)) v.forEach(item => fd.append(k + '[]', item));
          else if (v !== undefined && v !== null) fd.append(k, v);
        }
        fetchBody = fd;
      }

      const res = await fetch(url, { method, body: fetchBody });
      const json = await res.json().catch(() => null);
      return json || { success: false, message: 'Invalid server response.' };
    } catch (e) {
      return { success: false, message: e?.message || 'Network error.' };
    }
  },

  toggleSidebar() {
    const el = document.getElementById('sidebar');
    if (!el) return;
    el.classList.toggle('open');
  },
};

function showAlert(container, msg, type = 'error') {
  const el = document.querySelector(container);
  if (!el) return;
  el.innerHTML = `<div class="alert alert-${type}">${escHtml(msg)}</div>`;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  setTimeout(() => { el.innerHTML = ''; }, 5000);
}

function escHtml(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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

function openModal(id) {
  const el = document.getElementById(id);
  if (!el) { console.error('Modal not found:', id); return; }
  el.classList.remove('hidden');
  el.style.display = 'flex';
  el.style.alignItems = 'center';
  el.style.justifyContent = 'center';
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.add('hidden');
  el.style.display = 'none';
  document.body.style.overflow = '';
}

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

// Active sidebar linkVDVD
document.addEventListener('DOMContentLoaded', () => {
  const page = new URLSearchParams(location.search).get('page') || 'dashboard';
  document.querySelectorAll('.triply-navlink, .sidebar nav a').forEach(a => {
    const href = new URLSearchParams(a.search).get('page') || 'dashboard';
    if (href === page) a.classList.add('active');
  });
  if (document.getElementById('notif-btn')) loadNotifications();
});
