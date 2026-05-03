<?php

require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
$userRole = $currentUser->getRole();
$canSend  = in_array($userRole, ['admin', 'leader']);

require_once __DIR__ . '/layout.php';
start_layout('Notification Center');
?>

<div id="alert-box"></div>

<?php if ($canSend): ?>
<div class="card mb-4">
  <div class="card-header">
    <h3>Send Notification</h3>
    <?php if ($userRole === 'admin'): ?>
      <span class="badge badge-red">Admin</span>
    <?php else: ?>
      <span class="badge badge-blue">Trip Leader</span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div id="send-alert"></div>

    <?php if ($userRole === 'admin'): ?>
    <div class="grid-2" style="gap:16px">

      <div style="border:1px solid var(--border);border-radius:8px;padding:16px">
        <h4 class="text-sm font-semibold text-gray-300 mb-3">Trip Members</h4>
        <div class="form-group">
          <label>Trip</label>
          <select id="trip-select-send" class="form-control">
            <option value="">— Select trip —</option>
          </select>
        </div>
        <div class="form-group">
          <label>Title</label>
          <input type="text" id="trip-title" class="form-control" placeholder="e.g. Schedule Change">
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea id="trip-message" class="form-control" rows="3" placeholder="Write your notification…"></textarea>
        </div>
        <button class="btn btn-primary btn-block" onclick="sendTripNotif()">Send to Trip Members</button>
      </div>

      <div style="border:1px solid var(--border);border-radius:8px;padding:16px">
        <h4 class="text-sm font-semibold text-gray-300 mb-3">System-Wide Broadcast</h4>
        <div class="form-group">
          <label>Target Audience</label>
          <select id="sys-target" class="form-control">
            <option value="all">All Users</option>
            <option value="admins">Admins Only</option>
            <option value="leaders">Leaders Only</option>
            <option value="members">Members Only</option>
          </select>
        </div>
        <div class="form-group">
          <label>Title</label>
          <input type="text" id="sys-title" class="form-control" placeholder="e.g. System Maintenance">
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea id="sys-message" class="form-control" rows="3" placeholder="Write your system notification…"></textarea>
        </div>
        <button class="btn btn-danger btn-block" onclick="sendSystemNotif()">Broadcast System Notification</button>
      </div>

    </div>
    <?php else: ?>
    <div class="form-group" style="max-width:340px">
      <label>Trip</label>
      <select id="trip-select-send" class="form-control">
        <option value="">— Select trip —</option>
      </select>
    </div>
    <div class="form-group" style="max-width:560px">
      <label>Title</label>
      <input type="text" id="trip-title" class="form-control" placeholder="e.g. Meeting Point Change">
    </div>
    <div class="form-group" style="max-width:560px">
      <label>Message</label>
      <textarea id="trip-message" class="form-control" rows="3" placeholder="Write your notification to all trip members…"></textarea>
    </div>
    <button class="btn btn-primary" onclick="sendTripNotif()">Send to Trip Members</button>
    <?php endif; ?>

  </div>
</div>
<?php endif; ?>

<!-- Notification inbox -->
<div class="card">
  <div class="card-header flex-between">
    <h3>Inbox</h3>
    <div style="display:flex;gap:8px">
      <button class="btn btn-secondary btn-sm" onclick="markAllReadPage()">Mark All Read</button>
      <button class="btn btn-danger btn-sm" onclick="clearAllNotifs()">Clear All</button>
    </div>
  </div>
  <div id="notif-inbox"></div>
</div>

<!-- Read modal -->
<div class="modal-overlay hidden" id="modal-notif-read">
  <div class="triply-modal">
    <div class="modal-header">
      <h3 id="mr-title" style="display:flex;align-items:center;gap:8px"></h3>
      <button class="modal-close" onclick="closeModal('modal-notif-read')">×</button>
    </div>
    <div class="modal-body">
      <p id="mr-body" class="text-gray-300" style="white-space:pre-wrap;line-height:1.7;font-size:15px"></p>
      <div id="mr-time" class="text-sm text-gray-500 mt-4"></div>
    </div>
  </div>
</div>

<script>
  let _pageNotifs = [];

  <?php if ($canSend): ?>
  async function loadSendTrips() {
    const res = await API.get('trips', { action: 'list' });
    const sel = document.getElementById('trip-select-send');
    (res.data || []).filter(t => t.my_status === 'accepted' || t.my_role === 'leader').forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.title;
      sel.appendChild(opt);
    });
  }
  loadSendTrips();

  async function sendTripNotif() {
    const tripId  = document.getElementById('trip-select-send').value;
    const title   = document.getElementById('trip-title').value.trim();
    const message = document.getElementById('trip-message').value.trim();
    if (!tripId)   { showAlert('#send-alert', 'Select a trip first.', 'error'); return; }
    if (!title)    { showAlert('#send-alert', 'Title is required.', 'error'); return; }
    if (!message)  { showAlert('#send-alert', 'Message cannot be empty.', 'error'); return; }

    const res = await API.post('notifications', { action: 'broadcast', trip_id: tripId, title, message });
    showAlert('#send-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) {
      document.getElementById('trip-title').value   = '';
      document.getElementById('trip-message').value = '';
    }
  }
  <?php endif; ?>

  <?php if ($userRole === 'admin'): ?>
  async function sendSystemNotif() {
    const target  = document.getElementById('sys-target').value;
    const title   = document.getElementById('sys-title').value.trim();
    const message = document.getElementById('sys-message').value.trim();
    if (!title)   { showAlert('#send-alert', 'Title is required.', 'error'); return; }
    if (!message) { showAlert('#send-alert', 'Message cannot be empty.', 'error'); return; }
    if (!confirm(`Broadcast "${title}" to "${target}"? This will notify multiple users.`)) return;

    const res = await API.post('notifications', { action: 'broadcast', target, title, message });
    showAlert('#send-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) {
      document.getElementById('sys-title').value   = '';
      document.getElementById('sys-message').value = '';
    }
  }
  <?php endif; ?>

  async function loadInbox() {
    const res  = await API.get('notifications', { action: 'list' });
    const wrap = document.getElementById('notif-inbox');
    _pageNotifs = res.data || [];

    if (!res.success || !_pageNotifs.length) {
      wrap.innerHTML = '<div class="empty-state"><div class="icon">🔔</div>No notifications yet.</div>';
      return;
    }

    wrap.innerHTML = _pageNotifs.map(n => `
      <div class="notif-row" data-id="${n.id}"
           style="display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border);transition:background .15s;${!n.is_read ? 'background:rgba(239,68,68,0.05)' : ''}">
        <span style="margin-top:0;width:10px;height:10px;border-radius:50%;flex-shrink:0;cursor:pointer;
                     background:${n.is_read ? 'var(--border)' : 'var(--danger)'};
                     box-shadow:${n.is_read ? 'none' : '0 0 6px rgba(239,68,68,0.6)'}"
              onclick="openNotif(${n.id})"></span>
        <div style="flex:1;min-width:0;cursor:pointer" onclick="openNotif(${n.id})">
          <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
            <strong style="font-size:14px;color:${n.is_read ? 'var(--text-muted)' : 'var(--text)'}">${escHtml(n.title || 'Notification')}</strong>
            <span class="text-xs text-gray-500" style="white-space:nowrap">${fmtDateTime(n.created_at)}</span>
          </div>
          <div class="text-sm text-gray-500 mt-1" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(n.message)}</div>
        </div>
        <button class="btn btn-danger btn-sm" style="flex-shrink:0;padding:4px 10px;font-size:12px"
                onclick="deleteNotif(${n.id})">✕</button>
      </div>`).join('');
  }

  function openNotif(id) {
    const n = _pageNotifs.find(x => x.id === id);
    if (!n) return;

    const dot = n.is_read
      ? ''
      : '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--danger);box-shadow:0 0 6px rgba(239,68,68,0.6)"></span>';
    document.getElementById('mr-title').innerHTML = dot + ' ' + escHtml(n.title || 'Notification');
    document.getElementById('mr-body').textContent  = n.message;
    document.getElementById('mr-time').textContent  = fmtDateTime(n.created_at);
    openModal('modal-notif-read');

    if (!n.is_read) {
      API.post('notifications', { action: 'read', id }).then(() => {
        n.is_read = 1;
        const row = document.querySelector(`.notif-row[data-id="${id}"]`);
        if (row) {
          row.style.background = '';
          const dot = row.querySelector('span');
          if (dot) { dot.style.background = 'var(--border)'; dot.style.boxShadow = 'none'; }
          const title = row.querySelector('strong');
          if (title) title.style.color = 'var(--text-muted)';
        }
        loadNotifications(); // refresh topbar badge
      });
    }
  }

  async function markAllReadPage() {
    const res = await API.post('notifications', { action: 'read_all' });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) { loadInbox(); loadNotifications(); }
  }

  async function deleteNotif(id) {
    const res = await API.post('notifications', { action: 'delete', id });
    if (res.success) {
      _pageNotifs = _pageNotifs.filter(n => n.id !== id);
      const row = document.querySelector(`.notif-row[data-id="${id}"]`);
      if (row) row.remove();
      if (!_pageNotifs.length) {
        document.getElementById('notif-inbox').innerHTML =
          '<div class="empty-state"><div class="icon">🔔</div>No notifications yet.</div>';
      }
      loadNotifications();
    }
  }

  async function clearAllNotifs() {
    if (!confirm('Delete all notifications? This cannot be undone.')) return;
    const res = await API.post('notifications', { action: 'delete_all' });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) { loadInbox(); loadNotifications(); }
  }

  loadInbox();
</script>

<?php end_layout(); ?>
