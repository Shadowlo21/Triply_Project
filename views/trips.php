<?php
require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Trips');
?>

<div id="alert-box"></div>

<div class="flex-between mb-4">
  <div></div>
  <button class="btn btn-primary" onclick="openModal('modal-create-trip')">+ New Trip</button>
</div>

<div id="trips-list"></div>

<div id="trip-detail" style="display: none" class="mt-4">
  <div class="card">
    <div class="card-header">
      <h3 id="detail-title" style="display:flex;padding-right:16px">Trip Details</h3>
      <div style="display:flex;gap:16px">
        <button class="btn btn-secondary btn-sm" onclick="openModal('modal-invite')">Invite Member</button>
        <button class="btn btn-secondary btn-sm" id="btn-budget" onclick="openModal('modal-budget')">Set Budget</button>
      </div>
    </div>
    <div id="detail-body"></div>
    <div class="mt-4">
      <h4 class="mb-2 text-gray-400">Members</h4>
      <div id="members-list"></div>
    </div>
  </div>
</div>

<div class="modal-overlay hidden" id="modal-create-trip">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Create New Trip</h3>
      <button class="modal-close" onclick="closeModal('modal-create-trip')">×</button>
    </div>
    <div class="modal-body">
      <div id="create-alert"></div>
      <form id="form-create-trip">
        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
        <div class="form-group"><label>Destination</label><input type="text" name="destination" class="form-control" required></div>
        <div class="grid-2">
          <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" required></div>
          <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" required></div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label>Currency</label>
            <select name="base_currency" class="form-control">
              <option value="EGP">EGP</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
              <option value="GBP">GBP</option>
            </select>
          </div>
          <div class="form-group"><label>Budget Limit (optional)</label><input type="number" name="budget_limit" class="form-control" min="0" step="0.01"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-create">Create Trip</button>
      </form>
    </div>
  </div>
</div>

<div class="modal-overlay hidden" id="modal-invite">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Invite Member</h3>
      <button class="modal-close" onclick="closeModal('modal-invite')">×</button>
    </div>
    <div class="modal-body">
      <div id="invite-alert"></div>
      <div class="form-group"><label>Email</label><input type="email" id="invite-email" class="form-control" required></div>
      <button class="btn btn-primary btn-block" onclick="sendInvite()">Send Invite</button>
    </div>
  </div>
</div>

<div class="modal-overlay hidden" id="modal-budget">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Set Budget Limit</h3>
      <button class="modal-close" onclick="closeModal('modal-budget')">×</button>
    </div>
    <div class="modal-body">
      <div id="budget-alert"></div>
      <div class="form-group"><label>Budget Limit</label><input type="number" id="budget-amount" class="form-control" min="0" step="0.01"></div>
      <button class="btn btn-primary btn-block" onclick="saveBudget()">Save</button>
    </div>
  </div>
</div>

<script>
  let currentTripId = null;

  async function loadTrips() {
    const res = await API.get('trips', {
      action: 'list'
    });
    const el = document.getElementById('trips-list');
    if (!res.success || !res.data.length) {
      el.innerHTML = '<div class="card empty-state"><div class="icon">🗺</div>No trips yet. Create one to get started!</div>';
      return;
    }
    el.innerHTML = `<div class="card"><div class="table-wrap"><table>
      <thead><tr><th>Trip</th><th>Destination</th><th>Dates</th><th>Role</th><th>Status</th><th></th></tr></thead>
      <tbody>${res.data.map(t => `
        <tr>
          <td class="text-gray-400"><strong>${escHtml(t.title)}</strong></td>
          <td class="text-gray-400">${escHtml(t.destination)}</td>
          <td class="text-sm text-gray-400">${fmtDate(t.start_date)} – ${fmtDate(t.end_date)}</td>
          <td><span class="badge ${t.my_role === 'leader' ? 'badge-blue' : 'badge-gray'}">${escHtml(t.my_role)}</span></td>
          <td><span class="badge ${t.status === 'active' ? 'badge-green' : 'badge-gray'}">${escHtml(t.status)}</span></td>
          <td><button class="btn btn-secondary btn-sm" 
                data-action="show-trip"
                data-id="${t.id}" 
                data-title="${escHtml(t.title)}" 
                data-role="${t.my_role}">Open</button></td>
        </tr>`).join('')}
      </tbody>
    </table></div></div>`;
  }

  async function showTrip(id, title, role) {
    currentTripId = id;
    document.getElementById('detail-title').textContent = title;
    document.getElementById('trip-detail').style.display = 'block';
    document.getElementById('btn-budget').style.display = role === 'leader' ? 'inline-flex' : 'none';

    const res = await API.get('trips', {
      action: 'get',
      trip_id: id
    });
    if (res.success) {
      const t = res.data;
      document.getElementById('detail-body').innerHTML = `
        <div class="grid-3 mt-2">
          <div><div class="text-sm text-gray-600">Destination</div><strong class="text-gray-400">${escHtml(t.destination)}</strong></div>
          <div><div class="text-sm text-gray-600">Dates</div><strong class="text-gray-400">${fmtDate(t.start_date)} – ${fmtDate(t.end_date)}</strong></div>
          <div><div class="text-sm text-gray-600">Budget</div><strong class="text-gray-400">${t.budget_limit ? t.budget_limit + ' ' + t.base_currency : 'Not set'}</strong></div>
        </div>
        <div class="mt-3" style="display:flex;align-items:center;gap:12px">
          <span class="text-sm text-gray-600">Status:</span>
          <select id="trip-status-select" class="form-control" style="width:auto" onchange="updateTripStatus(this.value)">
            <option value="planning" ${t.status === 'planning' ? 'selected' : ''}>Planning</option>
            <option value="active"   ${t.status === 'active'   ? 'selected' : ''}>Active</option>
            <option value="completed"${t.status === 'completed'? 'selected' : ''}>Completed</option>
            <option value="settled"  ${t.status === 'settled'  ? 'selected' : ''}>Settled</option>
          </select>
        </div>`;
    }

    const mRes = await API.get('trips', {
      action: 'members',
      trip_id: id
    });
    if (mRes.success) {
      document.getElementById('members-list').innerHTML = `<div class="table-wrap"><table>
        <thead><tr><th>Email</th><th>Role</th><th>Can Edit</th></tr></thead>
        <tbody>${(mRes.data || []).map(m => `
          <tr>
            <td class="text-gray-400">${escHtml(m.email)}</td>
            <td><span class="badge ${m.trip_role === 'leader' ? 'badge-blue' : 'badge-gray'}">${escHtml(m.trip_role)}</span></td>
            <td>${m.can_edit ? '✅' : '—'}</td>
          </tr>`).join('')}
        </tbody>
      </table></div>`;
    }

    document.getElementById('trip-detail').scrollIntoView({
      behavior: 'smooth'
    });
  }

  async function updateTripStatus(status) {
    if (!currentTripId) return;
    const res = await API.post('trips', {
      action: 'update_status',
      trip_id: currentTripId,
      status: status
    });

    if (res.success) {
      showAlert('#status-alert', 'Trip status updated successfully', 'success');
      loadTrips();
    } else {
      showAlert('#status-alert', res.message, 'error');
    }
  }

  async function sendInvite() {
    const email = document.getElementById('invite-email').value.trim();
    if (!email || !currentTripId) return;
    const res = await API.post('trips', {
      action: 'invite',
      trip_id: currentTripId,
      email
    });
    showAlert('#invite-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) showTrip(currentTripId, document.getElementById('detail-title').textContent, 'leader');
  }

  async function saveBudget() {
    const amt = document.getElementById('budget-amount').value;
    if (!amt || !currentTripId) return;
    const res = await API.post('trips', {
      action: 'set_budget',
      trip_id: currentTripId,
      budget_limit: amt
    });
    showAlert('#budget-alert', res.message, res.success ? 'success' : 'error');
  }

  document.addEventListener('DOMContentLoaded', function() {

    document.getElementById('trips-list').addEventListener('click', function(e) {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;

      const detail = document.getElementById('trip-detail');
      const id = btn.dataset.id;

      if (btn.dataset.action === 'show-trip') {
        if (detail.dataset.openTripId === id && detail.style.display !== 'none') {
          detail.style.display = 'none';
          detail.dataset.openTripId = '';
          return;
        }
        detail.dataset.openTripId = id;
        showTrip(id, btn.dataset.title, btn.dataset.role);
      }
    });

    document.getElementById('form-create-trip').addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('btn-create');
      setLoading(btn, true);
      const fd = new FormData(e.target);
      const res = await API.post('trips', {
        action: 'create',
        title: fd.get('title'),
        destination: fd.get('destination'),
        start_date: fd.get('start_date'),
        end_date: fd.get('end_date'),
        base_currency: fd.get('base_currency'),
        budget_limit: fd.get('budget_limit'),
      });
      setLoading(btn, false);
      if (res.success) {
        closeModal('modal-create-trip');
        e.target.reset();
        showAlert('#alert-box', 'Trip created!', 'success');
        loadTrips();
      } else {
        showAlert('#create-alert', res.message, 'error');
      }
    });

    loadTrips();
  });
</script>

<?php end_layout(); ?>