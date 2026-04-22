<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register — Triply</title>
  <link rel="stylesheet" href="/public/css/style.css">
  <style>
    .pwd-req { list-style: none; padding: 0; margin: 6px 0 0; font-size: 12px; }
    .pwd-req li { padding: 2px 0; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
    .pwd-req li::before { content: '○'; font-size: 10px; }
    .pwd-req li.ok { color: var(--success); }
    .pwd-req li.ok::before { content: '✓'; }
    .auth-card { max-width: 480px; }
  </style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>✈ Triply</h1>
    <p class="subtitle">Create your account</p>
    <div id="alert-box"></div>
    <form id="reg-form">
      <div class="grid-2">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="first_name" class="form-control" required autofocus autocomplete="given-name">
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" class="form-control" required autocomplete="family-name">
        </div>
      </div>
      <div class="form-group">
        <label>Nationality</label>
        <select name="nationality" class="form-control" required>
          <option value="">— Select country —</option>
          <option value="EG">🇪🇬 Egypt</option>
          <option value="SA">🇸🇦 Saudi Arabia</option>
          <option value="AE">🇦🇪 UAE</option>
          <option value="JO">🇯🇴 Jordan</option>
          <option value="LB">🇱🇧 Lebanon</option>
          <option value="SY">🇸🇾 Syria</option>
          <option value="IQ">🇮🇶 Iraq</option>
          <option value="KW">🇰🇼 Kuwait</option>
          <option value="QA">🇶🇦 Qatar</option>
          <option value="BH">🇧🇭 Bahrain</option>
          <option value="OM">🇴🇲 Oman</option>
          <option value="YE">🇾🇪 Yemen</option>
          <option value="MA">🇲🇦 Morocco</option>
          <option value="TN">🇹🇳 Tunisia</option>
          <option value="DZ">🇩🇿 Algeria</option>
          <option value="LY">🇱🇾 Libya</option>
          <option value="SD">🇸🇩 Sudan</option>
          <option value="PS">🇵🇸 Palestine</option>
          <option value="US">🇺🇸 United States</option>
          <option value="GB">🇬🇧 United Kingdom</option>
          <option value="DE">🇩🇪 Germany</option>
          <option value="FR">🇫🇷 France</option>
          <option value="IT">🇮🇹 Italy</option>
          <option value="ES">🇪🇸 Spain</option>
          <option value="CA">🇨🇦 Canada</option>
          <option value="AU">🇦🇺 Australia</option>
          <option value="JP">🇯🇵 Japan</option>
          <option value="CN">🇨🇳 China</option>
          <option value="IN">🇮🇳 India</option>
          <option value="TR">🇹🇷 Turkey</option>
          <option value="PK">🇵🇰 Pakistan</option>
          <option value="NG">🇳🇬 Nigeria</option>
          <option value="ZA">🇿🇦 South Africa</option>
          <option value="BR">🇧🇷 Brazil</option>
          <option value="MX">🇲🇽 Mexico</option>
          <option value="RU">🇷🇺 Russia</option>
          <option value="KR">🇰🇷 South Korea</option>
          <option value="OTHER">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required autocomplete="email">
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="tel" name="phone" class="form-control" required autocomplete="tel" placeholder="+20 1xx xxx xxxx">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" id="pwd" name="password" class="form-control" required autocomplete="new-password">
        <ul class="pwd-req">
          <li id="req-len">At least 8 characters</li>
          <li id="req-upper">At least 1 uppercase letter</li>
          <li id="req-num">At least 1 number</li>
          <li id="req-special">At least 1 special character (!@#$…)</li>
        </ul>
      </div>
      <button type="submit" class="btn btn-primary btn-block mt-3" id="submit-btn">Create Account</button>
    </form>
    <p class="mt-3 text-sm text-muted" style="text-align:center">
      Already have an account? <a href="/?page=login">Sign in</a>
    </p>
  </div>
</div>
<script src="/public/js/app.js"></script>
<script>
const pwdInput = document.getElementById('pwd');
const rules = {
  'req-len':     p => p.length >= 8,
  'req-upper':   p => /[A-Z]/.test(p),
  'req-num':     p => /[0-9]/.test(p),
  'req-special': p => /[^A-Za-z0-9]/.test(p),
};

pwdInput.addEventListener('input', () => {
  const val = pwdInput.value;
  for (const [id, check] of Object.entries(rules)) {
    document.getElementById(id).classList.toggle('ok', check(val));
  }
});

document.getElementById('reg-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const pwd = fd.get('password');

  // Validate all password rules
  const failed = Object.entries(rules).find(([, check]) => !check(pwd));
  if (failed) {
    showAlert('#alert-box', 'Password does not meet all requirements.');
    return;
  }

  const btn = document.getElementById('submit-btn');
  setLoading(btn, true);

  const firstName = fd.get('first_name').trim();
  const lastName  = fd.get('last_name').trim();

  const res = await API.post('auth', {
    action:      'register',
    name:        firstName + ' ' + lastName,
    email:       fd.get('email'),
    phone:       fd.get('phone'),
    nationality: fd.get('nationality'),
    password:    pwd,
  });

  setLoading(btn, false);
  if (res.success) {
    location.href = '/?page=login';
  } else {
    showAlert('#alert-box', res.message || 'Registration failed.');
  }
});
</script>
</body>
</html>
