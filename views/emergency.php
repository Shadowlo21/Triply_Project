<?php

/**
 * Emergency Page
 * Emergency contact broadcaster for trip members
 * - Set personal emergency contact
 * - Trip leaders can broadcast emergency alerts
 */

require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Emergency');
?>

<div id="alert-box"></div>

<div class="grid-2">
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

  <div class="card">
    <div class="card-header">
      <h3>Emergency Broadcast</h3>
      <span class="badge badge-red mb-1">Trip Leaders Only</span>
    </div>
    <div class="card-body">
      <div id="broadcast-alert"></div>
      <p class="text-gray-500 mb-3">Send an emergency alert to all members of a trip. Use only in real emergencies.</p>

      <div class="form-group">
        <label>Select Trip</label>
        <select id="broadcast-trip" class="form-control">
          <option value="">— Select a trip —</option>
        </select>
      </div>

      <div class="form-group">
        <label>Emergency Message</label>
        <textarea id="broadcast-message" class="form-control" rows="4" placeholder="Enter emergency details... This will be sent to all trip members immediately."></textarea>
      </div>

      <button class="btn btn-danger btn-block" id="btn-broadcast" onclick="sendBroadcast()">
        <i class="fa-solid fa-triangle-exclamation"></i> Send Emergency Alert
      </button>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header">
    <h3>Trip Emergency Contacts</h3>
  </div>
  <div id="trip-contacts-list">
    <div class="empty-state">
      <div class="icon">👥</div>Select a trip to view member emergency contacts
    </div>
  </div>
</div>

<script>
  async function loadTrips() {
    const res = await API.get('trips', {
      action: 'list'
    });
    const sel = document.getElementById('broadcast-trip');
    (res.data || []).forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.title;
      sel.appendChild(opt);
    });
    sel.addEventListener('change', loadTripEmergencyContacts);
  }

  async function loadEmergencyContact() {
    const res = await API.get('emergency', {
      action: 'get_contact'
    });
    if (res.success && res.data) {
      const data = res.data;
      document.getElementById('emergency-name').value = data.emergency_name || '';
      document.getElementById('emergency-phone').value = data.emergency_phone || '';
      document.getElementById('emergency-relation').value = data.emergency_relation || '';
    }
  }

  document.getElementById('form-emergency-contact').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btn-save-contact');
    setLoading(btn, true);
    const fd = new FormData(e.target);
    const res = await API.post('emergency', {
      action: 'update_contact',
      emergency_name: fd.get('emergency_name'),
      emergency_phone: fd.get('emergency_phone'),
      emergency_relation: fd.get('emergency_relation')
    });
    setLoading(btn, false);
    showAlert('#contact-alert', res.message, res.success ? 'success' : 'error');
  });

  async function sendBroadcast() {
    const tripId = document.getElementById('broadcast-trip').value;
    const message = document.getElementById('broadcast-message').value.trim();

    if (!tripId) {
      showAlert('#broadcast-alert', 'Please select a trip.', 'error');
      return;
    }
    if (!message) {
      showAlert('#broadcast-alert', 'Please enter an emergency message.', 'error');
      return;
    }

    if (!confirm('WARNING: This will send an emergency alert to ALL trip members. Are you sure?')) {
      return;
    }

    const btn = document.getElementById('btn-broadcast');
    setLoading(btn, true);
    const res = await API.post('emergency', {
      action: 'broadcast',
      trip_id: tripId,
      message: message
    });
    setLoading(btn, false);
    showAlert('#broadcast-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) {
      document.getElementById('broadcast-message').value = '';
    }
  }

  async function loadTripEmergencyContacts() {
    const tripId = document.getElementById('broadcast-trip').value;
    if (!tripId) return;

    const res = await API.get('trips', {
      action: 'members',
      trip_id: tripId
    });
    const wrap = document.getElementById('trip-contacts-list');

    if (!res.success || !res.data.length) {
      wrap.innerHTML = '<div class="card empty-state"><div class="icon">👥</div>No members found.</div>';
      return;
    }

    wrap.innerHTML = `<div class="card"><div class="table-wrap"><table>
      <thead><tr><th>Member</th><th>Role</th><th>Emergency Contact</th></tr></thead>
      <tbody>${res.data.map(m => `
        <tr>
          <td class="text-sm text-gray-400">${escHtml(m.email)}</td>
          <td><span class="badge ${m.trip_role === 'leader' ? 'badge-blue' : 'badge-gray'}">${escHtml(m.trip_role)}</span></td>
          <td class="text-sm text-gray-400">Contact info available in profile</td>
        </tr>
      `).join('')}</tbody>
    </table></div></div>`;
  }

  loadTrips();
  loadEmergencyContact();
</script>

<?php end_layout(); ?>