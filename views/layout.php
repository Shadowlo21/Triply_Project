<?php

$_user = Auth::current();

function start_layout(string $title): void {
    global $_user;
    $name = htmlspecialchars($_user ? $_user->getName() : 'Guest');
    $role = $_user ? $_user->getRole() : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> — Triply</title>
  <link rel="stylesheet" href="/public/css/style.css">
  <script src="/public/js/app.js"></script>
</head>
<body>
<div class="app">

  <aside class="sidebar">
    <div class="sidebar-brand">
      <span>✈ Triply</span>
      <small>Group Travel Planner</small>
    </div>
    <nav>
      <a href="/?page=dashboard"><span class="icon">🏠</span> Dashboard</a>
      <a href="/?page=trips"><span class="icon">🗺</span> Trips</a>
      <a href="/?page=itinerary"><span class="icon">📅</span> Itinerary</a>
      <a href="/?page=financial"><span class="icon">💰</span> Financial</a>
      <a href="/?page=documents"><span class="icon">📁</span> Documents</a>
      <a href="/?page=social"><span class="icon">🗳</span> Polls</a>
      <?php if ($_user && $_user->getRole() === 'admin'): ?>
      <a href="/?page=admin"><span class="icon">⚙</span> Admin Panel</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <div><?= $name ?> <span class="badge badge-gray"><?= htmlspecialchars($role) ?></span></div>
      <a href="#" onclick="logout(); return false;">Sign out</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <h2><?= htmlspecialchars($title) ?></h2>
      <div class="topbar-right">
        <div style="position:relative">
          <button class="notif-btn" id="notif-btn" onclick="toggleNotifDropdown()" title="Notifications">
            🔔
            <span class="notif-badge" id="notif-count" style="display:none">0</span>
          </button>
          <div id="notif-dropdown" class="notif-dropdown" style="display:none">
            <div class="notif-header">
              Notifications
              <a href="#" onclick="markAllRead(); return false;">Mark all read</a>
            </div>
            <div id="notif-list"></div>
          </div>
        </div>
        <span class="text-muted text-sm"><?= $name ?></span>
      </div>
    </header>
    <div class="page">
<?php
}

function end_layout(): void {
?>
    </div>
  </div>
</div>
<script>
async function logout() {
  await API.post('auth', { action: 'logout' });
  location.href = '/?page=login';
}
</script>
</body>
</html>
<?php
}
