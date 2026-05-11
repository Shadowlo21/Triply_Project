<?php

$current = Auth::current();
if (!$current || $current->getRole() !== 'admin') {
  header('Location: /?page=dashboard');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Admin Panel');
?>

<style>
  .admin-hero {
    background: linear-gradient(135deg, rgba(168,85,247,0.15) 0%, rgba(108,61,211,0.05) 100%);
    border: 1px solid rgba(168,85,247,0.2);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 24px;
  }
  .admin-hero h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.5px;
  }
  .admin-hero p {
    margin: 6px 0 0;
    color: #a78bfa;
    font-size: 13px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-weight: 600;
  }

  .stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }
  .stat-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all .2s ease;
  }
  .stat-card:hover {
    border-color: rgba(168,85,247,0.4);
    transform: translateY(-2px);
  }
  .stat-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
  }
  .stat-icon.blue   { background: rgba(59,130,246,0.15); color: #60a5fa; }
  .stat-icon.green  { background: rgba(16,185,129,0.15); color: #34d399; }
  .stat-icon.purple { background: rgba(168,85,247,0.15); color: #c084fc; }
  .stat-icon.amber  { background: rgba(245,158,11,0.15); color: #fbbf24; }

  .stat-content .stat-value {
    font-size: 26px;
    font-weight: 700;
    color: #fff;
    line-height: 1.1;
  }
  .stat-content .stat-label {
    font-size: 12px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
  }

  .tab-nav {
    display: flex;
    gap: 4px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 20px;
    overflow-x: auto;
  }
  .tab-btn {
    padding: 12px 20px;
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all .15s ease;
    white-space: nowrap;
    display: flex; align-items: center; gap: 8px;
  }
  .tab-btn:hover { color: #fff; }
  .tab-btn.active {
    color: #c084fc;
    border-bottom-color: #c084fc;
  }
  .tab-btn .badge-count {
    background: rgba(245,158,11,0.2);
    color: #fbbf24;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
  }

  .tab-panel { display: none; }
  .tab-panel.active { display: block; }

  .data-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    overflow: hidden;
  }
  .data-card-header {
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    gap: 12px;
    flex-wrap: wrap;
  }
  .data-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #fff;
  }
</style>

<div id="alert-box"></div>

<div class="admin-hero">
  <p>Control Center</p>
  <h1>Admin Panel</h1>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
    <div class="stat-content">
      <div class="stat-value" id="s-users">—</div>
      <div class="stat-label">Total Users</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-route"></i></div>
    <div class="stat-content">
      <div class="stat-value" id="s-trips">—</div>
      <div class="stat-label">Total Trips</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fa-solid fa-wallet"></i></div>
    <div class="stat-content">
      <div class="stat-value" id="s-expenses">—</div>
      <div class="stat-label">Expenses Logged</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber"><i class="fa-solid fa-file-circle-question"></i></div>
    <div class="stat-content">
      <div class="stat-value" id="s-pending-docs">—</div>
      <div class="stat-label">Docs Pending Review</div>
    </div>
  </div>
</div>

<div class="tab-nav">
  <button class="tab-btn active" onclick="switchTab('users')">
    <i class="fa-solid fa-user-group"></i> Users
  </button>
  <button class="tab-btn" onclick="switchTab('trips')">
    <i class="fa-solid fa-suitcase-rolling"></i> Trips
  </button>
  <button class="tab-btn" onclick="switchTab('docs')">
    <i class="fa-solid fa-file-shield"></i> Documents
    <span class="badge-count" id="pending-docs-badge" style="display:none">0</span>
  </button>
  <button class="tab-btn" onclick="switchTab('sessions')">
    <i class="fa-solid fa-key"></i> Sessions
  </button>
</div>

<div class="tab-panel active" id="tab-users">
  <div class="data-card">
    <div class="data-card-header">
      <h3>Users Management</h3>
      <input type="text" id="user-search" class="form-control" placeholder="🔍 Search by email…" style="max-width:280px" oninput="filterUsers()">
    </div>
    <div id="users-wrap">
      <div class="empty-state text-sm text-gray-600" style="padding:40px;text-align:center">Loading…</div>
    </div>
  </div>
</div>

<div class="tab-panel" id="tab-trips">
  <div class="data-card">
    <div class="data-card-header">
      <h3>All Trips</h3>
    </div>
    <div id="trips-wrap">
      <div class="empty-state text-sm text-gray-600" style="padding:40px;text-align:center">Loading…</div>
    </div>
  </div>
</div>

<div class="tab-panel" id="tab-docs">
  <div class="data-card">
    <div class="data-card-header">
      <h3>Document Verification Queue</h3>
    </div>
    <div id="docs-verify-wrap">
      <div class="empty-state text-sm text-gray-600" style="padding:40px;text-align:center">Loading…</div>
    </div>
  </div>
</div>

<div class="tab-panel" id="tab-sessions">
  <div class="data-card">
    <div class="data-card-header">
      <h3>Active Sessions</h3>
      <button class="btn btn-danger btn-sm" onclick="purgeExpired()">
        <i class="fa-solid fa-broom"></i> Purge Expired
      </button>
    </div>
    <div id="sessions-wrap">
      <div class="empty-state text-sm text-gray-600" style="padding:40px;text-align:center">Loading…</div>
    </div>
  </div>
</div>

<div class="modal-overlay hidden" id="modal-reject-doc">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Reject Document</h3><button class="modal-close" onclick="closeModal('modal-reject-doc')">×</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Reason (optional — shown to user)</label>
        <textarea id="reject-note" class="form-control" rows="3" placeholder="e.g. Image is blurry, please re-upload…"></textarea>
      </div>
      <div style="display:flex;gap:8px;margin-top:12px">
        <button class="btn btn-danger" onclick="confirmReject()">Reject</button>
        <button class="btn btn-secondary" onclick="closeModal('modal-reject-doc')">Cancel</button>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay hidden" id="modal-del-user">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Delete User</h3><button class="modal-close" onclick="closeModal('modal-del-user')">×</button>
    </div>
    <div class="modal-body">
      <p>Are you sure you want to delete <strong id="del-user-email"></strong>? This cannot be undone.</p>
      <div style="display:flex;gap:8px;margin-top:16px">
        <button class="btn btn-danger" onclick="confirmDeleteUser()">Yes, Delete</button>
        <button class="btn btn-secondary" onclick="closeModal('modal-del-user')">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script>
  let allUsers = [];
  let deleteUserId = null;
  let currentAdminId = null;
  let currentAdminEmail = null;

  function switchTab(name) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.currentTarget.classList.add('active');
  }

  (async () => {
    const me = await API.get('auth', { action: 'me' });
    if (me.success) { currentAdminId = me.data.id; currentAdminEmail = me.data.email; }
    loadStats();
  })();

  async function loadStats() {
    const [uRes, tRes, eRes] = await Promise.all([
      API.get('admin', { action: 'users' }),
      API.get('admin', { action: 'trips' }),
      API.get('admin', { action: 'stats' }),
    ]);

    if (uRes.success) {
      allUsers = uRes.data;
      document.getElementById('s-users').textContent = uRes.data.length;
      renderUsers(uRes.data);
    }
    if (tRes.success) {
      document.getElementById('s-trips').textContent = tRes.data.length;
      renderTrips(tRes.data);
    }
    if (eRes.success && eRes.data) {
      document.getElementById('s-expenses').textContent     = eRes.data.expense_count ?? '—';
      const pendingCount = eRes.data.pending_docs ?? 0;
      document.getElementById('s-pending-docs').textContent = pendingCount;
      const badge = document.getElementById('pending-docs-badge');
      if (pendingCount > 0) {
        badge.textContent = pendingCount;
        badge.style.display = '';
      }
    }
    loadPendingDocs();
    loadSessions();
  }

  function renderUsers(users) {
    const wrap = document.getElementById('users-wrap');
    if (!users.length) {
      wrap.innerHTML = '<div class="empty-state text-sm text-gray-600" style="padding:40px;text-align:center">No users.</div>';
      return;
    }
    const roleColor = { admin: 'badge-red', leader: 'badge-blue', member: 'badge-gray' };
    wrap.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>${users.map(u => `
        <tr>
          <td class="text-gray-300">${escHtml(u.email)}</td>
          <td><span class="badge ${roleColor[u.role] || 'badge-gray'}">${escHtml(u.role)}</span></td>
          <td class="text-sm text-gray-500">${fmtDate(u.created_at)}</td>
          <td style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
            ${u.id === currentAdminId
              ? '<span class="text-gray-500 text-sm">(you)</span>'
              : (u.role === 'admin' && currentAdminEmail !== 'admin@admin.com')
                ? '<span class="text-gray-500 text-sm">owner only</span>'
                : `<select class="form-control" style="width:100px;padding:4px 8px;font-size:12px" onchange="setRole(${u.id}, this)">
                    <option value="member" ${u.role==='member'?'selected':''}>member</option>
                    <option value="leader" ${u.role==='leader'?'selected':''}>leader</option>
                    <option value="admin"  ${u.role==='admin' ?'selected':''}>admin</option>
                  </select>
                  <button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id},'${escHtml(u.email)}')" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>`
            }
          </td>
        </tr>`).join('')}
      </tbody></table></div>`;
  }

  async function setRole(userId, sel) {
    const newRole = sel.value;
    const res = await API.post('admin', { action: 'set_role', user_id: userId, role: newRole });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) loadStats();
  }

  function filterUsers() {
    const q = document.getElementById('user-search').value.toLowerCase();
    renderUsers(allUsers.filter(u => u.email.toLowerCase().includes(q)));
  }

  function deleteUser(id, email) {
    deleteUserId = id;
    document.getElementById('del-user-email').textContent = email;
    openModal('modal-del-user');
  }

  async function confirmDeleteUser() {
    const res = await API.post('admin', { action: 'delete_user', user_id: deleteUserId });
    closeModal('modal-del-user');
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) loadStats();
  }

  function renderTrips(trips) {
    const wrap = document.getElementById('trips-wrap');
    if (!trips.length) {
      wrap.innerHTML = '<div class="empty-state text-sm text-gray-600" style="padding:40px;text-align:center">No trips.</div>';
      return;
    }
    const statusColor = {
      active: 'badge-green', planning: 'badge-yellow',
      completed: 'badge-blue', settled: 'badge-gray',
    };
    wrap.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Title</th><th>Destination</th><th>Status</th><th>Created</th></tr></thead>
      <tbody>${trips.map(t => `
        <tr>
          <td class="text-gray-300"><strong>${escHtml(t.title)}</strong></td>
          <td class="text-gray-400">${escHtml(t.destination)}</td>
          <td><span class="badge ${statusColor[t.status] || 'badge-gray'}">${escHtml(t.status)}</span></td>
          <td class="text-sm text-gray-500">${fmtDate(t.created_at)}</td>
        </tr>`).join('')}
      </tbody></table></div>`;
  }

  async function loadSessions() {
    const res = await API.get('admin', { action: 'sessions' });
    const wrap = document.getElementById('sessions-wrap');
    if (!res.success || !res.data.length) {
      wrap.innerHTML = '<div class="empty-state text-sm text-gray-600" style="padding:40px;text-align:center">No active sessions.</div>';
      return;
    }
    wrap.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>User</th><th>Created</th><th>Expires</th></tr></thead>
      <tbody>${res.data.map(s => `
        <tr>
          <td class="text-gray-300">${escHtml(s.email || s.user_id)}</td>
          <td class="text-sm text-gray-500">${fmtDateTime(s.created_at)}</td>
          <td class="text-sm text-gray-500">${fmtDateTime(s.expires_at)}</td>
        </tr>`).join('')}
      </tbody></table></div>`;
  }

  async function purgeExpired() {
    const res = await API.post('admin', { action: 'purge_sessions' });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) loadSessions();
  }

  const docTypeLabel = { passport: 'Passport', national_id: 'National ID', license: "Driver's License", other: 'Other' };
  const docStatusBadge = {
    pending:  '<span class="badge badge-yellow">Under Review</span>',
    verified: '<span class="badge badge-green">✓ Verified</span>',
    rejected: '<span class="badge badge-red">✗ Rejected</span>',
  };
  let _allPendingDocs = [];

  async function loadPendingDocs() {
    const res  = await API.get('documents', { action: 'pending_docs' });
    const wrap = document.getElementById('docs-verify-wrap');
    _allPendingDocs = res.data || [];

    if (!_allPendingDocs.length) {
      wrap.innerHTML = '<div class="empty-state text-sm text-gray-600" style="padding:40px;text-align:center">No documents pending review.</div>';
      return;
    }

    const userIds = [...new Set(_allPendingDocs.map(d => d.user_id))];
    const uRes    = await API.get('admin', { action: 'users' });
    const userMap = {};
    (uRes.data || []).forEach(u => { userMap[u.id] = u.email; });

    wrap.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>User</th><th>Type</th><th>File</th><th>Uploaded</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>${_allPendingDocs.map(d => `
        <tr>
          <td class="text-sm text-gray-300">${escHtml(userMap[d.user_id] || '#'+d.user_id)}</td>
          <td><span class="badge badge-blue">${escHtml(docTypeLabel[d.type] || d.type)}</span></td>
          <td class="text-sm text-gray-400">${escHtml(d.original_name || '—')}</td>
          <td class="text-sm text-gray-500">${fmtDate(d.uploaded_at)}</td>
          <td>${docStatusBadge[d.status] || d.status}</td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a class="btn btn-secondary btn-sm" href="/api/documents.php?action=download_profile&doc_id=${d.id}" target="_blank" title="View">
              <i class="fa-solid fa-eye"></i>
            </a>
            <button class="btn btn-primary btn-sm" onclick="reviewDoc(${d.id},'verified')" title="Verify">
              <i class="fa-solid fa-check"></i>
            </button>
            <button class="btn btn-danger btn-sm" onclick="openRejectModal(${d.id})" title="Reject">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </td>
        </tr>`).join('')}
      </tbody></table></div>`;
  }

  async function reviewDoc(docId, status, note = '') {
    const res = await API.post('documents', { action: 'verify_profile', doc_id: docId, status, note });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) { loadPendingDocs(); loadStats(); }
  }

  let _rejectDocId = null;
  function openRejectModal(docId) {
    _rejectDocId = docId;
    document.getElementById('reject-note').value = '';
    openModal('modal-reject-doc');
  }

  async function confirmReject() {
    const note = document.getElementById('reject-note').value.trim();
    const res  = await API.post('documents', { action: 'verify_profile', doc_id: _rejectDocId, status: 'rejected', note });
    closeModal('modal-reject-doc');
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) { loadPendingDocs(); loadStats(); }
  }
</script>

<?php end_layout(); ?>
