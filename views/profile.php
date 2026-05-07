<?php
/**
 * User Profile Page
 * Allows users to view and update their profile information and change password
 */

require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Profile');
?>

<div id="alert-box"></div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h3>Profile Information</h3>
    </div>
    <div class="card-body">
      <div id="profile-alert"></div>
      <form id="form-profile">
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" id="profile-name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" id="profile-email" class="form-control" disabled>
          <small class="text-muted">Email cannot be changed</small>
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="tel" name="phone" id="profile-phone" class="form-control">
        </div>
        <div class="form-group">
          <label>Nationality</label>
          <input type="text" name="nationality" id="profile-nationality" class="form-control" maxlength="2" placeholder="EG">
        </div>
        <div class="form-group">
          <label>Emergency Contact</label>
          <input type="text" name="emergency_contact" id="profile-emergency" class="form-control" placeholder="Name and phone number">
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-save-profile">Save Changes</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>Change Password</h3>
    </div>
    <div class="card-body">
      <div id="password-alert"></div>
      <form id="form-password">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control" required minlength="8">
          <small class="text-muted">Minimum 8 characters</small>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-change-password">Change Password</button>
      </form>
    </div>
  </div>
</div>

<!-- My Profile Documents -->
<div class="card mt-4">
  <div class="card-header flex-between">
    <h3>My Documents</h3>
    <button class="btn btn-primary btn-sm" onclick="openModal('modal-upload-profile-doc')">+ Upload</button>
  </div>
  <div class="card-body">
    <p class="text-sm text-gray-500 mb-3">Upload permanent documents (Passport, National ID, License) once. Leaders can verify them so you can join trips that require them.</p>
    <div id="profile-docs-list"><div class="text-sm text-gray-500">Loading…</div></div>
  </div>
</div>

<!-- Upload profile doc modal -->
<div class="modal-overlay hidden" id="modal-upload-profile-doc">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Upload Profile Document</h3>
      <button class="modal-close" onclick="closeModal('modal-upload-profile-doc')">×</button>
    </div>
    <div class="modal-body">
      <div id="pdoc-alert"></div>
      <form id="form-upload-pdoc" enctype="multipart/form-data">
        <div class="form-group">
          <label>Document Type</label>
          <select name="type" class="form-control">
            <option value="passport">Passport</option>
            <option value="national_id">National ID</option>
            <option value="license">Driver's License</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>File (PDF or image, max 10 MB)</label>
          <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.docx" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-upload-pdoc">Upload</button>
      </form>
    </div>
  </div>
</div>

<script>
  async function loadProfile() {
    const res = await API.get('profile', { action: 'get' });
    if (!res.success) {
      showAlert('#alert-box', res.message, 'error');
      return;
    }
    const data = res.data;
    document.getElementById('profile-name').value = data.name || '';
    document.getElementById('profile-email').value = data.email || '';
    document.getElementById('profile-phone').value = data.phone || '';
    document.getElementById('profile-nationality').value = data.nationality || '';
    document.getElementById('profile-emergency').value = data.emergency_contact || '';
  }

  document.getElementById('form-profile').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btn-save-profile');
    setLoading(btn, true);
    const fd = new FormData(e.target);
    const res = await API.post('profile', {
      action: 'update',
      name: fd.get('name'),
      phone: fd.get('phone'),
      nationality: fd.get('nationality'),
      emergency_contact: fd.get('emergency_contact')
    });
    setLoading(btn, false);
    showAlert('#profile-alert', res.message, res.success ? 'success' : 'error');
  });

  document.getElementById('form-password').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if (fd.get('new_password') !== fd.get('confirm_password')) {
      showAlert('#password-alert', 'Passwords do not match.', 'error');
      return;
    }
    const btn = document.getElementById('btn-change-password');
    setLoading(btn, true);
    const res = await API.post('profile', {
      action: 'change_password',
      current_password: fd.get('current_password'),
      new_password: fd.get('new_password')
    });
    setLoading(btn, false);
    showAlert('#password-alert', res.message, res.success ? 'success' : 'error');
    if (res.success) e.target.reset();
  });

  const docTypeLabel = { passport: 'Passport', national_id: 'National ID', license: "Driver's License", other: 'Other' };

  async function loadProfileDocs() {
    const res  = await API.get('documents', { action: 'list_profile' });
    const wrap = document.getElementById('profile-docs-list');
    const docs = res.data || [];
    if (!docs.length) {
      wrap.innerHTML = '<div class="empty-state"><div class="icon">📄</div>No documents yet. Upload your passport or ID.</div>';
      return;
    }
    wrap.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Type</th><th>File</th><th>Uploaded</th><th>Status</th><th></th></tr></thead>
      <tbody>${docs.map(d => `
        <tr>
          <td><span class="badge badge-blue">${escHtml(docTypeLabel[d.type] || d.type)}</span></td>
          <td class="text-sm text-gray-400">${escHtml(d.original_name || '—')}</td>
          <td class="text-sm text-gray-500">${fmtDate(d.uploaded_at)}</td>
          <td>${d.status === 'verified'
              ? '<span class="badge badge-green">✓ Verified</span>'
              : d.status === 'rejected'
                ? `<span class="badge badge-red">✗ Rejected${d.review_note ? ' — '+escHtml(d.review_note) : ''}</span>`
                : '<span class="badge badge-yellow">Under Review</span>'}</td>
          <td><button class="btn btn-danger btn-sm" onclick="deleteProfileDoc(${d.id})">Delete</button></td>
        </tr>`).join('')}
      </tbody></table></div>`;
  }

  async function deleteProfileDoc(id) {
    if (!confirm('Delete this document?')) return;
    const res = await API.post('documents', { action: 'delete_profile', doc_id: id });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) loadProfileDocs();
  }

  document.getElementById('form-upload-pdoc').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btn-upload-pdoc');
    setLoading(btn, true);
    const fd = new FormData(e.target);
    fd.append('action', 'upload_profile');
    const res = await API.upload('documents', fd);
    setLoading(btn, false);
    if (res.success) {
      closeModal('modal-upload-profile-doc');
      e.target.reset();
      showAlert('#alert-box', 'Document uploaded!', 'success');
      loadProfileDocs();
    } else {
      showAlert('#pdoc-alert', res.message, 'error');
    }
  });

  loadProfileDocs();
  loadProfile();
</script>

<?php end_layout(); ?>
