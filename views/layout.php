<?php

$_user = Auth::current();

function start_layout(string $title, array $opts = []): void
{
  global $_user;
  $name = htmlspecialchars($_user ? $_user->getName() : 'Guest');
  $role = $_user ? $_user->getRole() : '';
  $pageTitle = $opts['pageTitle'] ?? $title;
  $shell = $opts['shell'] ?? ($_user ? 'app' : 'public');
  $GLOBALS['__layout_shell'] = $shell;
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(Auth::csrfToken() ?? '', ENT_QUOTES) ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Triply') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="/public/css/style.css">
    <script src="/public/js/app.js"></script>
  </head>

  <body class="font-body-md bg-triply-bg text-triply-text antialiased overflow-x-hidden">

    <?php if ($shell === 'public'): ?>
      <div class="public-shell">
        <header class="bg-black/80 backdrop-blur-md font-sans antialiased tracking-tight docked full-width top-0 sticky z-50 border-b border-purple-900/20 shadow-[0_0_20px_rgba(168,85,247,0.05)]">
          <div class="flex justify-between items-center h-16 px-6 w-full max-w-7xl mx-auto">
            <a class="text-2xl font-black tracking-tighter text-white no-underline" href="/">Triply</a>
            <nav class="hidden md:flex items-center gap-8">
              <a class="text-gray-400 hover:text-white transition-colors py-1" href="/?page=dashboard">Dashboard</a>
              <a class="text-gray-400 hover:text-white transition-colors py-1" href="/?page=trips">Trips</a>
              <a class="text-gray-400 hover:text-white transition-colors py-1" href="/?page=social">Social</a>
              <a class="text-gray-400 hover:text-white transition-colors py-1" href="/?page=financial">Financial</a>
            </nav>
            <div class="flex items-center gap-4">
              <?php if ($_user): ?>
                <button class="material-symbols-outlined text-gray-400 hover:text-white transition-all scale-95 active:scale-90" id="notif-btn" onclick="toggleNotifDropdown()" title="Notifications">notifications
                  <span class="notif-badge" id="notif-count" style="display:none">0</span>
                </button>
                <div id="notif-dropdown" class="notif-dropdown" style="display:none">
                  <div class="notif-header">
                    Notifications
                    <a href="#" onclick="markAllRead(); return false;">Mark all read</a>
                  </div>
                  <div id="notif-list" class="text-sm text-gray-300"></div>
                </div>
                <span class="text-sm"><?= $name ?></span>
                <a class="btn btn-primary btn-sm" href="/?page=dashboard">Open App</a>
              <?php else: ?>
                <a class="btn btn-secondary btn-sm" href="/?page=login">Log in</a>
                <a class="btn btn-primary btn-sm" href="/?page=register">Get started</a>
              <?php endif; ?>
            </div>
          </div>
        </header>
        <main class="min-h-screen">

        <?php elseif ($shell === 'auth'): ?>
          <main class="min-h-screen flex items-center justify-center relative overflow-hidden bg-triply-bg">

          <?php else: ?>
            <div class="app-shell flex min-h-screen">

              <aside id="sidebar" class="triply-sidenav hidden md:flex flex-col h-screen w-64 border-r border-white/10 sticky left-0 top-0 bg-[#1A1A2E]/90 backdrop-blur-xl z-50 shadow-2xl shadow-purple-900/20 font-sans text-sm font-medium">
                <div class="flex flex-col h-full py-8">
                  <div class="px-6 mb-10 flex items-center gap-3">
                    <div class="w-10 h-10 bg-[var(--color-primary)] rounded-lg flex items-center justify-center">
                      <i class="fa-solid fa-compass text-white"></i>
                    </div>
                    <div>
                      <h1 class="text-xl font-bold text-white leading-tight">Triply</h1>
                      <p class="text-[10px] uppercase tracking-widest text-purple-400 font-bold">Elite Concierge</p>
                    </div>
                  </div>
                  <?php $isAdmin = $_user && $_user->getRole() === 'admin'; ?>
                  <nav class="flex-1 space-y-1 px-2">
                    <?php if (!$isAdmin): ?>
                    <a class="triply-navlink text-gray-500 hover:text-white px-4 py-3 flex items-center gap-3 hover:bg-white/5 hover:translate-x-1 transition-all duration-200 ease-in-out" href="/?page=dashboard">
                      <i class="fa-solid fa-gauge-high"></i><span>Dashboard</span>
                    </a>
                    <?php endif; ?>
                    <a class="triply-navlink text-gray-500 hover:text-white px-4 py-3 flex items-center gap-3 hover:bg-white/5 hover:translate-x-1 transition-all duration-200 ease-in-out" href="/?page=trips">
                      <i class="fa-solid fa-route"></i><span>Trips</span>
                    </a>
                    <?php if (!$isAdmin): ?>
                    <a class="triply-navlink text-gray-500 hover:text-white px-4 py-3 flex items-center gap-3 hover:bg-white/5 hover:translate-x-1 transition-all duration-200 ease-in-out" href="/?page=social">
                      <i class="fa-solid fa-people-group"></i><span>Social</span>
                    </a>
                    <a class="triply-navlink text-gray-500 hover:text-white px-4 py-3 flex items-center gap-3 hover:bg-white/5 hover:translate-x-1 transition-all duration-200 ease-in-out" href="/?page=financial">
                      <i class="fa-solid fa-wallet"></i><span>Financial</span>
                    </a>
                    <a class="triply-navlink text-gray-500 hover:text-white px-4 py-3 flex items-center gap-3 hover:bg-white/5 hover:translate-x-1 transition-all duration-200 ease-in-out" href="/?page=documents">
                      <i class="fa-solid fa-folder-open"></i><span>Documents</span>
                    </a>
                    <a class="triply-navlink text-gray-500 hover:text-white px-4 py-3 flex items-center gap-3 hover:bg-white/5 hover:translate-x-1 transition-all duration-200 ease-in-out" href="/?page=itinerary">
                      <i class="fa-solid fa-calendar-days"></i><span>Itinerary</span>
                    </a>
                    <?php endif; ?>
                    <a class="triply-navlink text-gray-500 hover:text-white px-4 py-3 flex items-center gap-3 hover:bg-white/5 hover:translate-x-1 transition-all duration-200 ease-in-out" href="/?page=notifications">
                      <i class="fa-solid fa-bell"></i><span>Notifications</span>
                    </a>
                    <a class="triply-navlink text-gray-500 hover:text-white px-4 py-3 flex items-center gap-3 hover:bg-white/5 hover:translate-x-1 transition-all duration-200 ease-in-out" href="/?page=emergency">
                      <i class="fa-solid fa-triangle-exclamation"></i><span>Emergency</span>
                    </a>
                    <?php if ($isAdmin): ?>
                      <a class="triply-navlink text-gray-500 hover:text-white px-4 py-3 flex items-center gap-3 hover:bg-white/5 hover:translate-x-1 transition-all duration-200 ease-in-out" href="/?page=admin">
                        <i class="fa-solid fa-shield-halved"></i><span>Admin</span>
                      </a>
                    <?php endif; ?>
                  </nav>
                  <div class="px-4 mt-auto space-y-3">
                    <a href="/?page=profile" class="flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-3 py-2 hover:bg-white/10 transition-colors">
                      <div class="min-w-0">
                        <div class="text-white text-sm font-semibold truncate"><?= $name ?></div>
                        <div class="text-[10px] uppercase tracking-widest text-gray-400"><?= htmlspecialchars($role) ?></div>
                      </div>
                      <i class="fa-solid fa-user text-gray-400"></i>
                    </a>
                    <div class="flex gap-2">
                      <a href="/?page=profile" class="flex-1 btn btn-secondary btn-sm text-center">
                        <i class="fa-solid fa-user-gear"></i> Profile
                      </a>
                      <button class="btn btn-secondary btn-sm" onclick="logout(); return false;" title="Sign out">
                        <i class="fa-solid fa-right-from-bracket"></i>
                      </button>
                    </div>
                    <?php if ($_user && $_user->getRole() === 'leader'): ?>
                    <button class="w-full py-3 bg-[var(--color-primary)] text-white font-bold rounded-xl flex items-center justify-center gap-2 hover:scale-105 transition-transform active:scale-95 shadow-[0_0_20px_rgba(168,85,247,0.3)]" onclick="location.href='/?page=trips'">
                      <i class="fa-solid fa-plus"></i> New Trip
                    </button>
                    <?php endif; ?>
                  </div>
                </div>
              </aside>

              <div class="flex-1 flex flex-col min-w-0">
                <header class="triply-topbar bg-black/80 backdrop-blur-md sticky top-0 z-40 border-b border-purple-900/20 shadow-[0_0_20px_rgba(168,85,247,0.05)] h-16 w-full flex justify-between items-center px-6">
                  <div class="flex items-center gap-4 min-w-0">
                    <button class="md:hidden text-white/80 hover:text-white" type="button" onclick="Triply.toggleSidebar()">
                      <i class="fa-solid fa-bars"></i>
                    </button>
                    <h2 class="text-white text-lg font-bold truncate"><?= htmlspecialchars($title) ?></h2>
                  </div>
                  <div class="flex items-center gap-4">
                    <div class="relative">
                      <button class="p-2 text-gray-400 hover:text-white hover:bg-white/5 rounded-full transition-all" id="notif-btn" onclick="toggleNotifDropdown()" title="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notif-badge" id="notif-count" style="display:none">0</span>
                      </button>
                      <div id="notif-dropdown" class="notif-dropdown" style="display:none">
                        <div class="notif-header">
                          <span>Notifications</span>
                          <div>
                            <a href="#" onclick="markAllRead(); return false;">Mark all read</a>
                            <span class="mx-1">|</span>
                            <a href="/?page=notifications">View All</a>
                          </div>
                        </div>
                        <div id="notif-list" class="p-1 text-sm text-gray-300"></div>
                      </div>
                    </div>
                    <span class="hidden sm:block text-sm text-gray-300"><?= $name ?></span>
                  </div>
                </header>
                <div class="triply-page flex-1 p-6 md:p-10 max-w-7xl mx-auto w-full">
                <?php
              endif;
            }

            function end_layout(): void
            {
                ?>
                <?php $shell = $GLOBALS['__layout_shell'] ?? 'app'; ?>
                <?php if ($shell === 'auth'): ?>
          </main>
        <?php elseif ($shell === 'public'): ?>
        </main>
      </div>
    <?php else: ?>
      </div>
      </div>
      </div>
    <?php endif; ?>
    <script>
      async function logout() {
        await API.post('auth', {
          action: 'logout'
        });
        location.href = '/?page=login';
      }
    </script>
  </body>

  </html>
<?php
            }
