<?php

require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Notifications');
?>

<div id="alert-box"></div>

<div class="card">
  <div class="card-header flex-between">
    <h3>All Notifications</h3>
    <button class="btn btn-secondary btn-sm" onclick="markAllRead()">Mark All Read</button>
  </div>
  <div id="notifications-list">
    <div class="empty-state">
      <div class="icon">🔔</div>Loading notifications...
    </div>
  </div>
</div>

<script>
  async function loadAllNotifications() {
    const res = await API.get('notifications', {
      action: 'list'
    });
    const list = document.getElementById('notifications-list');
    if (!res.success) {
      list.innerHTML = `<div class="alert alert-error">${escHtml(res.message)}</div>`;
      return;
    }
    const items = res.data || [];
    if (!items.length) {
      list.innerHTML = '<div class="card empty-state"><div class="icon">🔔</div>No notifications yet.</div>';
      return;
    }
    list.innerHTML = items.map(n => `
      <div class="notification-item ${n.is_read ? 'read' : 'unread'}" data-id="${n.id}" style="padding: 16px; border-bottom: 1px solid var(--border); ${!n.is_read ? 'background: rgba(168, 85, 247, 0.05);' : ''}">
        <div class="flex-between">
          <div style="flex: 1;">
            <div class="text-gray-300">${escHtml(n.message)}</div>
            <div class="text-sm text-gray-500 mt-2">${fmtDateTime(n.created_at)}</div>
          </div>
          ${!n.is_read ? `<button class="btn btn-primary btn-sm ml-2" onclick="markNotificationRead(${n.id})">Mark Read</button>` : ''}
        </div>
      </div>
    `).join('');
  }

  async function markNotificationRead(id) {
    const res = await API.post('notifications', {
      action: 'read',
      id
    });
    if (res.success) {
      const el = document.querySelector(`[data-id="${id}"]`);
      if (el) {
        el.classList.remove('unread');
        el.classList.add('read');
        el.style.background = '';
        const btn = el.querySelector('button');
        if (btn) btn.remove();
      }
      loadNotifications();
    }
  }

  async function markAllRead() {
    const res = await API.post('notifications', {
      action: 'read_all'
    });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) {
      loadAllNotifications();
      loadNotifications();
    }
  }

  loadAllNotifications();
</script>

<?php end_layout(); ?>