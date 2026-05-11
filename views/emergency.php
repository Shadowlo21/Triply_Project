<?php

require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
$canViewTripContacts = !in_array($currentUser->getRole(), ['member', 'admin'], true);
require_once __DIR__ . '/layout.php';
start_layout('Emergency');
?>

<div id="alert-box"></div>

<div class="<?= $canViewTripContacts ? 'grid-2' : '' ?>">
  <div class="card">
    <div class="card-header">
      <h3>My Emergency Contact</h3>
    </div>
    <div class="card-body">
      <div id="contact-alert"></div>
      <form id="form-emergency-contact">
        <div class="form-group">
          <label>Emergency Contact Name</label>
          <input type="text" name="emergency_name" id="emergency-name" class="form-control" placeholder="e.g. John Doe">
        </div>
        <div class="form-group">
          <label>Emergency Contact Phone</label>
          <input type="tel" name="emergency_phone" id="emergency-phone" class="form-control" placeholder="e.g. +1 234 567 8900">
        </div>
        <div class="form-group">
          <label>Relationship</label>
          <input type="text" name="emergency_relation" id="emergency-relation" class="form-control" placeholder="e.g. Spouse, Parent, Friend">
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-save-contact">Save Contact</button>
      </form>
    </div>
  </div>

  <?php if ($canViewTripContacts): ?>
  <div class="card">
    <div class="card-header">
      <h3>Trip Emergency Contacts</h3>
    </div>
    <div class="card-body">
      <div class="form-group mb-3">
        <label>Select Trip</label>
        <select id="ec-trip-select" class="form-control" onchange="loadTripEmergencyContacts()">
          <option value="">— Select a trip —</option>
        </select>
      </div>
      <div id="trip-contacts-list">
        <div class="empty-state">
          <div class="icon">👥</div>
          <p class="text-gray-500 text-sm">Select a trip to view member emergency contacts.</p>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
  async function loadTrips() {
    const res = await API.get('trips', { action: 'list' });
    const sel = document.getElementById('ec-trip-select');
    (res.data || []).filter(t => t.my_status === 'accepted').forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.title;
      sel.appendChild(opt);
    });
  }

  async function loadEmergencyContact() {
    const res = await API.get('emergency', { action: 'get_contact' });
    if (res.success && res.data) {
      const d = res.data;
      document.getElementById('emergency-name').value     = d.emergency_name     || '';
      document.getElementById('emergency-phone').value    = d.emergency_phone    || '';
      document.getElementById('emergency-relation').value = d.emergency_relation || '';
    }
  }

  document.getElementById('form-emergency-contact').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btn-save-contact');
    setLoading(btn, true);
    const fd = new FormData(e.target);
    const res = await API.post('emergency', {
      action:             'update_contact',
      emergency_name:     fd.get('emergency_name'),
      emergency_phone:    fd.get('emergency_phone'),
      emergency_relation: fd.get('emergency_relation'),
    });
    setLoading(btn, false);
    showAlert('#contact-alert', res.message, res.success ? 'success' : 'error');
  });

  async function loadTripEmergencyContacts() {
    const tripId = document.getElementById('ec-trip-select').value;
    const wrap   = document.getElementById('trip-contacts-list');
    if (!tripId) {
      wrap.innerHTML = '<div class="empty-state"><div class="icon">👥</div><p class="text-gray-500 text-sm">Select a trip.</p></div>';
      return;
    }
    wrap.innerHTML = '<div class="text-sm text-gray-500">Loading…</div>';

    const res = await API.get('emergency', { action: 'get_trip_contacts', trip_id: tripId });
    if (!res.success) {
      wrap.innerHTML = `<div class="alert alert-error">${escHtml(res.message)}</div>`;
      return;
    }
    const members = res.data || [];
    if (!members.length) {
      wrap.innerHTML = '<div class="empty-state"><div class="icon">👥</div>No members found.</div>';
      return;
    }

    wrap.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Member</th><th>Role</th><th>Contact Name</th><th>Phone</th><th>Relation</th></tr></thead>
      <tbody>${members.map(m => `
        <tr>
          <td class="text-sm text-gray-400">${escHtml(m.name || m.email)}</td>
          <td><span class="badge ${m.trip_role === 'leader' ? 'badge-blue' : 'badge-gray'}">${escHtml(m.trip_role)}</span></td>
          <td class="text-sm text-gray-400">${escHtml(m.emergency_name  || '—')}</td>
          <td class="text-sm text-gray-400">${escHtml(m.emergency_phone || '—')}</td>
          <td class="text-sm text-gray-400">${escHtml(m.emergency_relation || '—')}</td>
        </tr>`).join('')}
      </tbody>
    </table></div>`;
  }

  <?php if ($canViewTripContacts): ?>loadTrips();<?php endif; ?>
  loadEmergencyContact();
</script>

<?php end_layout(); ?>
