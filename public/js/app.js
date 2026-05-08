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
  csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  },

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

      const headers = {};
      if (method.toUpperCase() !== 'GET') {
        const token = this.csrfToken();
        if (token) headers['X-CSRF-Token'] = token;
      }

      const res = await fetch(url, { method, body: fetchBody, headers });
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
let _notifCache = [];

async function loadNotifications() {
  const res = await API.get('notifications', { action: 'list', unread_only: '1' });
  if (!res.success) return;
  _notifCache = res.data || [];
  const badge = document.getElementById('notif-count');
  if (badge) {
    badge.textContent = _notifCache.length;
    badge.style.display = _notifCache.length ? 'flex' : 'none';
  }
  const list = document.getElementById('notif-list');
  if (!list) return;
  if (!_notifCache.length) {
    list.innerHTML = '<div class="notif-item text-sm text-gray-500" style="padding:12px">No new notifications</div>';
    return;
  }
  list.innerHTML = _notifCache.map(n => `
    <div class="notif-item" onclick="openNotifModal(${n.id})" style="cursor:pointer;padding:10px 12px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:7px">
        <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${n.is_read ? 'transparent' : 'var(--danger)'};border:${n.is_read ? '1px solid var(--border)' : 'none'}"></span>
        <strong class="text-sm" style="color:${n.is_read ? 'var(--text-sm text-gray-500)' : 'var(--text)'}">${escHtml(n.title || 'Notification')}</strong>
      </div>
      <div class="text-sm text-gray-500" style="margin-top:3px;padding-left:15px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:230px">${escHtml(n.message)}</div>
      <div class="text-xs text-gray-500" style="margin-top:2px;padding-left:15px">${fmtDateTime(n.created_at)}</div>
    </div>`).join('');
}

function openNotifModal(id) {
  const n = _notifCache.find(x => x.id === id);
  if (!n) return;

  let modal = document.getElementById('_notif-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = '_notif-modal';
    modal.className = 'modal-overlay hidden';
    modal.innerHTML = `
      <div class="triply-modal">
        <div class="modal-header">
          <h3 id="_nm-title" style="display:flex;align-items:center;gap:8px"></h3>
          <button class="modal-close" onclick="closeModal('_notif-modal')">×</button>
        </div>
        <div class="modal-body">
          <p id="_nm-body" class="text-gray-300" style="white-space:pre-wrap;line-height:1.6"></p>
          <div id="_nm-time" class="text-sm text-gray-500 mt-3"></div>
        </div>
      </div>`;
    document.body.appendChild(modal);
  }

  const dot = n.is_read ? '' : '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--danger);margin-right:4px"></span>';
  document.getElementById('_nm-title').innerHTML = dot + escHtml(n.title || 'Notification');
  document.getElementById('_nm-body').textContent = n.message;
  document.getElementById('_nm-time').textContent = fmtDateTime(n.created_at);
  openModal('_notif-modal');

  if (!n.is_read) {
    API.post('notifications', { action: 'read', id }).then(() => {
      n.is_read = 1;
      loadNotifications();
    });
  }
}

async function markRead(id, el) {
  await API.post('notifications', { action: 'read', id });
  if (el) el.classList.remove('unread');
  loadNotifications();
}

async function markAllRead() {
  await API.post('notifications', { action: 'read_all' });
  loadNotifications();
}

function buildAlert(className, title, message) {
  const div = document.createElement('div');
  div.className = `alert ${className}`;
  div.innerHTML = `<strong>${escHtml(title)}</strong>${message ? '<br>' + escHtml(message) : ''}`;
  return div;
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
  document.querySelectorAll('.triply-navlink, .sidebar nav a').forEach(a => {
    const href = new URLSearchParams(a.search).get('page') || 'dashboard';
    if (href === page) a.classList.add('active');
  });
  if (document.getElementById('notif-btn')) loadNotifications();
});
