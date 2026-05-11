<?php
require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
$myRole = $currentUser->getRole(); // system role
require_once __DIR__ . '/layout.php';
start_layout('Trips');
?>

<div id="alert-box"></div>

<?php if ($myRole === 'leader'): ?>
<div class="flex-between mb-4">
  <div></div>
  <button class="btn btn-primary" onclick="openModal('modal-create-trip')">+ New Trip</button>
</div>
<?php endif; ?>

<div id="trips-list"></div>

<!-- Open panel -->
<div id="trip-detail" style="display:none" class="mt-4">
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:8px">
      <h3 id="detail-title">Trip Details</h3>
      <div id="detail-actions" style="display:flex;gap:8px;flex-wrap:wrap"></div>
    </div>
    <div id="detail-body"></div>
    <div class="mt-4">
      <h4 class="mb-2 text-gray-400">Members</h4>
      <div id="members-list"></div>
    </div>
  </div>
</div>

<!-- Create modal (leaders + admins only) -->
<?php if ($myRole === 'leader'): ?>
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
          <div class="form-group"><label>Pickup / Departure Point</label><input type="text" name="departure_point" class="form-control" placeholder="e.g. Cairo Airport — Terminal 2"></div>
          <div class="form-group"><label>Departure Time</label><input type="time" name="departure_time" class="form-control"></div>
        </div>
        <div class="grid-3">
          <div class="form-group">
            <label>Currency</label>
            <select name="base_currency" class="form-control">
              <option value="EGP">EGP</option><option value="USD">USD</option>
              <option value="EUR">EUR</option><option value="GBP">GBP</option>
            </select>
          </div>
          <div class="form-group"><label>Budget Limit (optional)</label><input type="number" name="budget_limit" class="form-control" min="0" step="0.01"></div>
          <div class="form-group"><label>Max Slots</label><input type="number" name="max_slots" class="form-control" min="1" step="1" value="20"></div>
        </div>
        <div class="form-group">
          <label>Required Documents to Join</label>
          <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:4px">
            <label style="display:flex;align-items:center;gap:6px;font-weight:normal"><input type="checkbox" name="required_docs[]" value="passport"> Passport</label>
            <label style="display:flex;align-items:center;gap:6px;font-weight:normal"><input type="checkbox" name="required_docs[]" value="national_id"> National ID</label>
            <label style="display:flex;align-items:center;gap:6px;font-weight:normal"><input type="checkbox" name="required_docs[]" value="license"> Driver's License</label>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-create">Create Trip</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Required Docs modal -->
<div class="modal-overlay hidden" id="modal-req-docs">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Set Required Documents</h3>
      <button class="modal-close" onclick="closeModal('modal-req-docs')">×</button>
    </div>
    <div class="modal-body">
      <div id="req-docs-alert"></div>
      <p class="text-sm text-gray-500 mb-3">Members must have these verified in their Profile before they can join this trip.</p>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <input type="checkbox" id="req-passport" value="passport"> Passport
        </label>
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <input type="checkbox" id="req-national-id" value="national_id"> National ID
        </label>
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" id="req-license" value="license"> Driver's License
        </label>
      </div>
      <button class="btn btn-primary btn-block mt-3" onclick="saveRequiredDocs()">Save Requirements</button>
    </div>
  </div>
</div>

<!-- Invite modal -->
<div class="modal-overlay hidden" id="modal-invite">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Invite Member</h3>
      <button class="modal-close" onclick="closeModal('modal-invite')">×</button>
    </div>
    <div class="modal-body">
      <div id="invite-alert"></div>
      <div class="form-group"><label>Email</label><input type="email" id="invite-email" class="form-control"></div>
      <button class="btn btn-primary btn-block" onclick="sendInvite()">Send Invite</button>
      <div style="display:flex;align-items:center;gap:8px;margin:16px 0;color:var(--text-muted)">
        <hr style="flex:1;border-color:var(--border)"><span class="text-sm">or</span><hr style="flex:1;border-color:var(--border)">
      </div>
      <button class="btn btn-secondary btn-block" onclick="inviteAll()">📢 Invite All Users</button>
      <p class="text-sm text-gray-500 mt-2">Sends a pending invite to every registered user not already in this trip.</p>
    </div>
  </div>
</div>

<!-- Budget modal -->
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

<!-- Edit Trip modal -->
<div class="modal-overlay hidden" id="modal-edit-trip">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Edit Trip</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-trip')">×</button>
    </div>
    <div class="modal-body">
      <div id="edit-alert"></div>
      <form id="form-edit-trip">
        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
        <div class="form-group"><label>Destination</label><input type="text" name="destination" class="form-control" required></div>
        <div class="grid-2">
          <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" required></div>
          <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" required></div>
        </div>
        <div class="grid-2">
          <div class="form-group"><label>Pickup / Departure Point</label><input type="text" name="departure_point" class="form-control"></div>
          <div class="form-group"><label>Departure Time</label><input type="time" name="departure_time" class="form-control"></div>
        </div>
        <div class="grid-3">
          <div class="form-group">
            <label>Currency</label>
            <select name="base_currency" class="form-control">
              <option value="EGP">EGP</option><option value="USD">USD</option>
              <option value="EUR">EUR</option><option value="GBP">GBP</option>
            </select>
          </div>
          <div class="form-group"><label>Budget Limit</label><input type="number" name="budget_limit" class="form-control" min="0" step="0.01"></div>
          <div class="form-group"><label>Max Slots</label><input type="number" name="max_slots" class="form-control" min="1" step="1"></div>
        </div>
        <div class="form-group">
          <label>Required Documents to Join</label>
          <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:4px">
            <label style="display:flex;align-items:center;gap:6px;font-weight:normal"><input type="checkbox" name="edit_required_docs[]" value="passport"> Passport</label>
            <label style="display:flex;align-items:center;gap:6px;font-weight:normal"><input type="checkbox" name="edit_required_docs[]" value="national_id"> National ID</label>
            <label style="display:flex;align-items:center;gap:6px;font-weight:normal"><input type="checkbox" name="edit_required_docs[]" value="license"> Driver's License</label>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-edit-trip">Save Changes</button>
      </form>
    </div>
  </div>
</div>

<script>
  let tripsData    = {};
  let currentTripId = null;
  const mySystemRole = <?= json_encode($myRole) ?>;

  const statusColor = {
    active: 'badge-green', planning: 'badge-yellow',
    completed: 'badge-blue', settled: 'badge-red'
  };

  async function loadTrips() {
    const res = await API.get('trips', { action: 'list' });
    const el  = document.getElementById('trips-list');
    if (!res.success || !res.data.length) {
      el.innerHTML = '<div class="card empty-state"><div class="icon">🗺</div>No trips yet. Create one or wait for an invite!</div>';
      return;
    }
    tripsData = {};
    res.data.forEach(t => { tripsData[t.id] = t; });

    el.innerHTML = `<div class="card"><div class="table-wrap"><table>
      <thead><tr><th>Trip</th><th>Destination</th><th>Dates</th><th>Role</th><th>Status</th><th>Leader</th><th>Slots</th><th></th></tr></thead>
      <tbody>${res.data.map(t => {
        const isPending  = t.my_status === 'pending';
        const isNonMember = !t.my_status;
        const statusBadge = isPending
          ? '<span class="badge badge-yellow">Pending Invite</span>'
          : `<span class="badge ${statusColor[t.status] || 'badge-gray'}">${escHtml(t.status)}</span>`;
        const slots = t.max_slots
          ? `<span class="${t.member_count >= t.max_slots ? 'text-danger' : 'text-gray-400'}">${t.member_count}/${t.max_slots}</span>`
          : `<span class="text-gray-500">${t.member_count}</span>`;
        return `<tr>
          <td><strong class="text-gray-300">${escHtml(t.title)}</strong></td>
          <td class="text-gray-400">${escHtml(t.destination)}</td>
          <td class="text-sm text-gray-400">${fmtDate(t.start_date)} – ${fmtDate(t.end_date)}</td>
          <td>${isNonMember ? '<span class="badge badge-gray">—</span>' : `<span class="badge ${t.my_role === 'leader' ? 'badge-blue' : 'badge-gray'}">${escHtml(t.my_role)}</span>`}</td>
          <td>${statusBadge}</td>
          <td class="text-sm text-gray-400">${escHtml(t.creator_name || '—')}</td>
          <td class="text-sm">${slots}</td>
          <td><button class="btn btn-secondary btn-sm" onclick="showTrip(${t.id})">Open</button></td>
        </tr>`;
      }).join('')}
      </tbody></table></div></div>`;
  }

  async function showTrip(id) {
    const detail = document.getElementById('trip-detail');
    if (detail.dataset.openId === String(id) && detail.style.display !== 'none') {
      detail.style.display = 'none';
      detail.dataset.openId = '';
      return;
    }
    detail.dataset.openId = String(id);
    currentTripId = id;

    const t = tripsData[id];
    document.getElementById('detail-title').textContent = t.title;

    // Status section
    const canChangeStatus = (mySystemRole === 'admin') ||
      (t.my_status === 'accepted' && t.my_role === 'leader');
    const statusHtml = canChangeStatus
      ? `<div style="display:flex;align-items:center;gap:10px;margin-top:12px">
           <span class="text-sm text-gray-500">Status:</span>
           <select id="trip-status-select" class="form-control" style="width:auto" onchange="updateTripStatus(this.value)">
             <option value="planning"  ${t.status==='planning'  ?'selected':''}>Planning</option>
             <option value="active"    ${t.status==='active'    ?'selected':''}>Active</option>
             <option value="completed" ${t.status==='completed' ?'selected':''}>Completed</option>
             <option value="settled"   ${t.status==='settled'   ?'selected':''}>Settled</option>
           </select>
         </div>`
      : `<div style="margin-top:12px"><span class="text-sm text-gray-500">Status: </span>
           <span class="badge ${statusColor[t.status]||'badge-gray'}">${escHtml(t.status)}</span></div>`;

    const reqDocs   = t.required_docs ? JSON.parse(t.required_docs) : [];
    const docLabels = { passport: 'Passport', national_id: 'National ID', license: "Driver's License" };
    const reqHtml   = reqDocs.length
      ? `<div style="margin-top:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
           <span class="text-sm text-gray-500">Required Docs:</span>
           ${reqDocs.map(d => `<span class="badge badge-yellow">${escHtml(docLabels[d]||d)}</span>`).join('')}
         </div>`
      : '';

    document.getElementById('detail-body').innerHTML = `
      <div class="grid-3 mt-3">
        <div><div class="text-sm text-gray-500">Destination</div><strong class="text-gray-300">${escHtml(t.destination)}</strong></div>
        <div><div class="text-sm text-gray-500">Dates</div><strong class="text-gray-300">${fmtDate(t.start_date)} – ${fmtDate(t.end_date)}</strong></div>
        <div><div class="text-sm text-gray-500">Budget</div><strong class="text-gray-300">${t.budget_limit ? t.budget_limit+' '+t.base_currency : 'Not set'}</strong></div>
        <div><div class="text-sm text-gray-500">Leader</div><strong class="text-gray-300">${escHtml(t.creator_name||'—')}</strong></div>
        <div><div class="text-sm text-gray-500">Members</div><strong class="text-gray-300">${t.member_count}${t.max_slots ? ' / '+t.max_slots : ''}</strong></div>
        <div><div class="text-sm text-gray-500">Currency</div><strong class="text-gray-300">${escHtml(t.base_currency)}</strong></div>
        ${t.departure_point ? `<div><div class="text-sm text-gray-500">📍 Pickup Point</div><strong class="text-gray-300">${escHtml(t.departure_point)}</strong></div>` : ''}
        ${t.departure_time  ? `<div><div class="text-sm text-gray-500">🕐 Departure Time</div><strong class="text-gray-300">${escHtml(t.departure_time)}</strong></div>` : ''}
      </div>
      ${reqHtml}
      ${statusHtml}
      <div id="status-alert" class="mt-2"></div>`;

    // Action buttons
    const btns = document.getElementById('detail-actions');
    btns.innerHTML = '';

    const canManage = mySystemRole === 'admin' || mySystemRole === 'leader' || t.my_role === 'leader';

    if (!t.my_status && canManage) {
      btns.innerHTML += `<button class="btn btn-secondary btn-sm" onclick="openEditTripModal(${id})">✎ Edit</button>`;
      btns.innerHTML += `<button class="btn btn-secondary btn-sm" onclick="openModal('modal-invite')">Invite Member</button>`;
      btns.innerHTML += `<button class="btn btn-secondary btn-sm" onclick="openModal('modal-budget')">Set Budget</button>`;
      btns.innerHTML += `<button class="btn btn-secondary btn-sm" onclick="openReqDocsModal(${id})">Requirements</button>`;
      btns.innerHTML += `<button class="btn btn-danger btn-sm" onclick="cancelTrip(${id})">Cancel Trip</button>`;
    } else if (!t.my_status) {
      const isFull = t.max_slots && t.member_count >= t.max_slots;
      btns.innerHTML = isFull
        ? '<span class="text-sm text-danger">This trip is full.</span>'
        : `<button class="btn btn-primary btn-sm" onclick="joinTrip(${id})">Join Trip</button>`;
    } else if (t.my_status === 'pending') {
      btns.innerHTML = `
        <button class="btn btn-primary btn-sm" onclick="acceptInvite(${id})">Join Trip</button>
        <button class="btn btn-danger  btn-sm" onclick="declineInvite(${id})">Decline</button>`;
    } else {
      if (canManage) {
        btns.innerHTML += `<button class="btn btn-secondary btn-sm" onclick="openEditTripModal(${id})">✎ Edit</button>`;
        btns.innerHTML += `<button class="btn btn-secondary btn-sm" onclick="openModal('modal-invite')">Invite Member</button>`;
        btns.innerHTML += `<button class="btn btn-secondary btn-sm" onclick="openModal('modal-budget')">Set Budget</button>`;
        btns.innerHTML += `<button class="btn btn-secondary btn-sm" onclick="openReqDocsModal(${id})">Requirements</button>`;
        btns.innerHTML += `<button class="btn btn-danger btn-sm" onclick="cancelTrip(${id})">Cancel Trip</button>`;
        if (t.my_role !== 'leader') {
          btns.innerHTML += `<button class="btn btn-warning btn-sm" onclick="leaveTrip(${id})">Leave Trip</button>`;
        }
      } else {
        btns.innerHTML += `<button class="btn btn-danger btn-sm" onclick="leaveTrip(${id})">Leave Trip</button>`;
      }
    }

    // Members list — members/pending of this trip, or admins
    const mWrap = document.getElementById('members-list');
    if (!t.my_status && mySystemRole !== 'admin') {
      mWrap.innerHTML = '<div class="text-sm text-gray-500 mt-2">Join this trip to see members.</div>';
    } else {
      const mRes = await API.get('trips', { action: 'members', trip_id: id });
      if (mRes.success && mRes.data.length) {
        mWrap.innerHTML = `<div class="table-wrap"><table>
          <thead><tr><th>Name</th><th>Role</th></tr></thead>
          <tbody>${mRes.data.map(m => `
            <tr>
              <td class="text-gray-300">${escHtml(m.name || m.email)}</td>
              <td><span class="badge ${m.trip_role==='leader'?'badge-blue':'badge-gray'}">${escHtml(m.trip_role)}</span></td>
            </tr>`).join('')}
          </tbody></table></div>`;
      } else {
        mWrap.innerHTML = '<div class="text-sm text-gray-500 mt-2">No members yet.</div>';
      }
    }

    detail.style.display = 'block';
    detail.scrollIntoView({ behavior: 'smooth' });
  }

  async function acceptInvite(tripId) {
    const res = await API.post('trips', { action: 'accept_invite', trip_id: tripId });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) { loadTrips(); document.getElementById('trip-detail').style.display = 'none'; }
  }

  async function joinTrip(tripId) {
    const res = await API.post('trips', { action: 'join', trip_id: tripId });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) { loadTrips(); document.getElementById('trip-detail').style.display = 'none'; }
  }

  async function declineInvite(tripId) {
    const res = await API.post('trips', { action: 'decline_invite', trip_id: tripId });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) { loadTrips(); document.getElementById('trip-detail').style.display = 'none'; }
  }

  async function leaveTrip(tripId) {
    if (!confirm('Leave this trip? You will need a new invite to rejoin.')) return;
    const res = await API.post('trips', { action: 'leave', trip_id: tripId });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) { loadTrips(); document.getElementById('trip-detail').style.display = 'none'; }
  }

  async function cancelTrip(tripId) {
    if (!confirm('Cancel this trip permanently? This will delete ALL trip data and notify all members.')) return;
    const res = await API.post('trips', { action: 'cancel', trip_id: tripId });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) { loadTrips(); document.getElementById('trip-detail').style.display = 'none'; }
  }

  async function updateTripStatus(status) {
    if (!currentTripId) return;
    const res = await API.post('trips', { action: 'update_status', trip_id: currentTripId, status });
    showAlert('#status-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) { tripsData[currentTripId].status = status; loadTrips(); }
  }

  async function sendInvite() {
    const email = document.getElementById('invite-email').value.trim();
    if (!email || !currentTripId) return;
    const res = await API.post('trips', { action: 'invite', trip_id: currentTripId, email });
    showAlert('#invite-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) {
      document.getElementById('invite-email').value = '';
      showTrip(currentTripId);
    }
  }

  async function inviteAll() {
    if (!currentTripId) return;
    if (!confirm('Send a pending invite to ALL registered users not already in this trip?')) return;
    const res = await API.post('trips', { action: 'invite_all', trip_id: currentTripId });
    showAlert('#invite-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) showTrip(currentTripId);
  }

  async function saveBudget() {
    const amt = document.getElementById('budget-amount').value;
    if (!amt || !currentTripId) return;
    const res = await API.post('trips', { action: 'set_budget', trip_id: currentTripId, budget_limit: amt });
    showAlert('#budget-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) closeModal('modal-budget');
  }

  function openEditTripModal(tripId) {
    currentTripId = tripId;
    const t = tripsData[tripId];
    if (!t) return;
    const f = document.getElementById('form-edit-trip');
    f.title.value           = t.title || '';
    f.destination.value     = t.destination || '';
    f.start_date.value      = (t.start_date || '').slice(0, 10);
    f.end_date.value        = (t.end_date || '').slice(0, 10);
    f.departure_point.value = t.departure_point || '';
    f.departure_time.value  = t.departure_time || '';
    f.base_currency.value   = t.base_currency || 'EGP';
    f.budget_limit.value    = t.budget_limit || '';
    f.max_slots.value       = t.max_slots || '';
    const current = t.required_docs ? JSON.parse(t.required_docs) : [];
    f.querySelectorAll('input[name="edit_required_docs[]"]').forEach(c => { c.checked = current.includes(c.value); });
    openModal('modal-edit-trip');
  }

  document.getElementById('form-edit-trip').addEventListener('submit', async e => {
    e.preventDefault();
    if (!currentTripId) return;
    const btn = document.getElementById('btn-edit-trip');
    setLoading(btn, true);
    const fd = new FormData(e.target);
    const res = await API.post('trips', {
      action:          'update',
      trip_id:         currentTripId,
      title:           fd.get('title'),
      destination:     fd.get('destination'),
      start_date:      fd.get('start_date'),
      end_date:        fd.get('end_date'),
      departure_point: fd.get('departure_point'),
      departure_time:  fd.get('departure_time'),
      base_currency:   fd.get('base_currency'),
      budget_limit:    fd.get('budget_limit'),
      max_slots:       fd.get('max_slots'),
      required_docs:   fd.getAll('edit_required_docs[]'),
    });
    setLoading(btn, false);
    if (res.success) {
      closeModal('modal-edit-trip');
      showAlert('#alert-box', 'Trip updated.', 'success');
      await loadTrips();
      showTrip(currentTripId);
    } else {
      showAlert('#edit-alert', res.message, 'error');
    }
  });

  function openReqDocsModal(tripId) {
    currentTripId = tripId;
    const t = tripsData[tripId];
    const current = t && t.required_docs ? JSON.parse(t.required_docs) : [];
    document.getElementById('req-passport').checked   = current.includes('passport');
    document.getElementById('req-national-id').checked = current.includes('national_id');
    document.getElementById('req-license').checked    = current.includes('license');
    openModal('modal-req-docs');
  }

  async function saveRequiredDocs() {
    if (!currentTripId) return;
    const docs = [];
    if (document.getElementById('req-passport').checked)    docs.push('passport');
    if (document.getElementById('req-national-id').checked) docs.push('national_id');
    if (document.getElementById('req-license').checked)     docs.push('license');
    const res = await API.post('trips', { action: 'set_required_docs', trip_id: currentTripId, required_docs: docs });
    showAlert('#req-docs-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) {
      tripsData[currentTripId].required_docs = docs.length ? JSON.stringify(docs) : null;
      closeModal('modal-req-docs');
      showTrip(currentTripId);
    }
  }

  const _createForm = document.getElementById('form-create-trip');
  if (_createForm) {
    _createForm.addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('btn-create');
      setLoading(btn, true);
      const fd  = new FormData(e.target);
      const res = await API.post('trips', {
        action:          'create',
        title:           fd.get('title'),
        destination:     fd.get('destination'),
        start_date:      fd.get('start_date'),
        end_date:        fd.get('end_date'),
        base_currency:   fd.get('base_currency'),
        budget_limit:    fd.get('budget_limit'),
        max_slots:       fd.get('max_slots'),
        departure_point: fd.get('departure_point'),
        departure_time:  fd.get('departure_time'),
        required_docs:   fd.getAll('required_docs[]'),
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
  }

  // Allow pending members to view members list (bypass viewTrip accepted-only check)
  // The members action in api/trips.php now has its own check
  loadTrips();
</script>

<?php end_layout(); ?>
