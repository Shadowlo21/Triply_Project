<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — Triply</title>
  <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>✈ Triply</h1>
    <p class="subtitle">Sign in to your account</p>
    <div id="alert-box"></div>
    <form id="login-form">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block mt-3" id="submit-btn">Sign In</button>
    </form>
    <p class="mt-3 text-sm text-muted" style="text-align:center">
      No account? <a href="/?page=register">Register</a>
    </p>
  </div>
</div>
<script src="/public/js/app.js"></script>
<script>
document.getElementById('login-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('submit-btn');
  setLoading(btn, true);
  const fd = new FormData(e.target);
  const res = await API.post('auth', { action: 'login', email: fd.get('email'), password: fd.get('password') });
  setLoading(btn, false);
  if (res.success) {
    location.href = '/?page=dashboard';
  } else {
    showAlert('#alert-box', res.message || 'Login failed.');
  }
});
</script>
</body>
</html>
