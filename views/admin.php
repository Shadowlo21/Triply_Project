<?php

$current = Auth::current();
if (!$current || $current->getRole() !== 'admin') {
  header('Location: /?page=dashboard');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Admin Panel');
?>

<div id="alert-box"></div>

<div class="grid-4 mb-4" id="admin-stats">
  <div class="card">
    <div class="stat-value text-gray-500" id="s-users">—</div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="card">
    <div class="stat-value text-gray-500" id="s-trips">—</div>
    <div class="stat-label">Total Trips</div>
  </div>
  <div class="card">
    <div class="stat-value text-gray-500" id="s-expenses">—</div>
    <div class="stat-label">Expenses Logged</div>
  </div>
  <div class="card">
    <div class="stat-value text-gray-500" id="s-polls">—</div>
    <div class="stat-label">Polls Created</div>
  </div>
</div>

<div class="grid-2">


  <div class="card">
    <div class="card-header">
      <h3>Users</h3>
      <input type="text" id="user-search" class="form-control" placeholder="Search email…" style="width:180px" oninput="filterUsers()">
    </div>
    <div id="users-wrap">
      <div class="empty-state text-sm text-gray-600">Loading…</div>
    </div>
  </div>


  <div class="card">
    <div class="card-header">
      <h3>All Trips</h3>
    </div>
    <div id="trips-wrap">
      <div class="empty-state text-sm text-gray-600">Loading…</div>
    </div>
  </div>

</div>

<div class="card mt-4">
  <div class="card-header">
    <h3>Active Sessions</h3>
    <button class="btn btn-danger btn-sm" onclick="purgeExpired()">Purge Expired</button>
  </div>
  <div id="sessions-wrap">
    <div class="empty-state text-sm text-gray-600">Loading…</div>
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

  (async () => {
    const me = await API.get('auth', {
      action: 'me'
    });
    if (me.success) { currentAdminId = me.data.id; currentAdminEmail = me.data.email; }
    loadStats();
  })();

  async function loadStats() {
    const [uRes, tRes, eRes, pRes] = await Promise.all([
      API.get('admin', {
        action: 'users'
      }),
      API.get('admin', {
        action: 'trips'
      }),
      API.get('admin', {
        action: 'stats'
      }),
      API.get('admin', {
        action: 'stats'
      }),
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
      document.getElementById('s-expenses').textContent = eRes.data.expense_count ?? '—';
      document.getElementById('s-polls').textContent = eRes.data.poll_count ?? '—';
    }
    loadSessions();
  }

  function renderUsers(users) {
    const wrap = document.getElementById('users-wrap');
    if (!users.length) {
      wrap.innerHTML = '<div class="empty-state text-sm text-gray-600">No users.</div>';
      return;
    }
    const roleColor = {
      admin: 'badge-red',
      leader: 'badge-blue',
      member: 'badge-gray'
    };
    wrap.innerHTML = `<div class="table-wrap"><table>
    <thead><tr><th>Email</th><th>Role</th><th>Joined</th><th></th></tr></thead>
    <tbody>${users.map(u => `
      <tr>
        <td class="text-gray-400">${escHtml(u.email)}</td>
        <td><span class="badge ${roleColor[u.role] || 'badge-gray'}">${escHtml(u.role)}</span></td>
        <td class="text-sm text-gray-500">${fmtDate(u.created_at)}</td>
        <td style="display:flex;gap:4px;flex-wrap:wrap">
          ${u.id === currentAdminId
            ? '<span class="text-gray-500 text-sm">(you)</span>'
            : (u.role === 'admin' && currentAdminEmail !== 'admin@admin.com')
              ? '<span class="text-gray-500 text-sm">owner only</span>'
              : `<select class="form-control" style="width:90px;padding:2px 4px;font-size:12px" onchange="setRole(${u.id}, this)">
                  <option value="member" ${u.role==='member'?'selected':''}>member</option>
                  <option value="leader" ${u.role==='leader'?'selected':''}>leader</option>
                  <option value="admin"  ${u.role==='admin' ?'selected':''}>admin</option>
                </select>
                <button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id},'${escHtml(u.email)}')">Del</button>`
          }
        </td>
      </tr>`).join('')}
    </tbody></table></div>`;
  }

  async function setRole(userId, sel) {
    const newRole = sel.value;
    const res = await API.post('admin', {
      action: 'set_role',
      user_id: userId,
      role: newRole
    });
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
    const res = await API.post('admin', {
      action: 'delete_user',
      user_id: deleteUserId
    });
    closeModal('modal-del-user');
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) loadStats();
  }

  function renderTrips(trips) {
    const wrap = document.getElementById('trips-wrap');
    if (!trips.length) {
      wrap.innerHTML = '<div class="empty-state text-sm text-gray-600">No trips.</div>';
      return;
    }
    wrap.innerHTML = `<div class="table-wrap"><table>
    <thead><tr><th>Title</th><th>Dest</th><th>Status</th><th>Created</th></tr></thead>
    <tbody>${trips.map(t => `
      <tr>
        <td class="text-gray-400"><strong>${escHtml(t.title)}</strong></td>
        <td class="text-gray-400">${escHtml(t.destination)}</td>
        <td><span class="badge ${t.status === 'active' ? 'badge-green' : 'badge-gray'}">${escHtml(t.status)}</span></td>
        <td class="text-sm text-gray-400">${fmtDate(t.created_at)}</td>
      </tr>`).join('')}
    </tbody></table></div>`;
  }

  async function loadSessions() {
    const res = await API.get('admin', {
      action: 'sessions'
    });
    const wrap = document.getElementById('sessions-wrap');
    if (!res.success || !res.data.length) {
      wrap.innerHTML = '<div class="empty-state text-sm text-gray-600">No active sessions.</div>';
      return;
    }
    wrap.innerHTML = `<div class="table-wrap"><table>
    <thead><tr><th>User</th><th>Expires</th><th>Created</th></tr></thead>
    <tbody>${res.data.map(s => `
      <tr>
        <td class="text-gray-400">${escHtml(s.email || s.user_id)}</td>
        <td class="text-sm text-gray-400">${fmtDateTime(s.expires_at)}</td>
        <td class="text-sm text-gray-400">${fmtDateTime(s.created_at)}</td>
      </tr>`).join('')}
    </tbody></table></div>`;
  }

  async function purgeExpired() {
    const res = await API.post('admin', {
      action: 'purge_sessions'
    });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) loadSessions();
  }
</script>

<?php end_layout(); ?>