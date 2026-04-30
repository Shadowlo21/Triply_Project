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

  loadProfile();
</script>

<?php end_layout(); ?>
