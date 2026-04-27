<?php
require_once __DIR__ . '/layout.php';
start_layout('Register', ['shell' => 'auth', 'pageTitle' => 'Triply - Start Your Adventure']);
?>

<div class="purple-glow -top-20 -left-20"></div>
<div class="purple-glow -bottom-20 -right-20"></div>

<main class="container py-20 flex justify-center items-center relative z-10">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <div class="inline-flex items-center gap-2 mb-4">
        <i class="fa-solid fa-compass text-primary text-3xl"></i>
        <h1 class="font-h1 text-h1 tracking-tighter text-on-surface">Triply</h1>
      </div>
    </div>

    <div class="glass-card rounded-xl p-8 md:p-10">
      <div class="mb-8 text-center">
        <h2 class="font-h2 text-h2 text-on-surface mb-2">Start Your Adventure</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">Experience the elite world of concierge travel.</p>
      </div>

      <div id="alert-box"></div>
      <form id="reg-form" class="space-y-6">
        <div class="space-y-2">
          <label class="font-label-sm text-label-sm text-on-surface-variant block uppercase tracking-wider" for="name">Full Name</label>
          <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-3 text-black focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all placeholder:text-gray-600" id="name" name="name" placeholder="Alexander Wright" type="text" required autofocus />
        </div>

        <div class="space-y-2">
          <label class="font-label-sm text-label-sm text-on-surface-variant block uppercase tracking-wider" for="email">Email Address</label>
          <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-3 text-black focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all placeholder:text-gray-600" id="email" name="email" placeholder="alex@concierge.luxury" type="email" required />
        </div>

        <div class="space-y-2">
          <label class="font-label-sm text-label-sm text-on-surface-variant block uppercase tracking-wider" for="phone">Phone</label>
          <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-3 text-black focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all placeholder:text-gray-600" id="phone" name="phone" placeholder="+20 1xx xxx xxxx" type="tel" required />
        </div>

        <div class="space-y-2">
          <label class="font-label-sm text-label-sm text-on-surface-variant block uppercase tracking-wider" for="nationality">Nationality</label>
          <select class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-3 text-black focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" id="nationality" name="nationality" required>
            <option value="">— Select country —</option>
            <option value="EG">🇪🇬 Egypt</option>
            <option value="SA">🇸🇦 Saudi Arabia</option>
            <option value="AE">🇦🇪 UAE</option>
            <option value="US">🇺🇸 United States</option>
            <option value="GB">🇬🇧 United Kingdom</option>
            <option value="DE">🇩🇪 Germany</option>
            <option value="FR">🇫🇷 France</option>
            <option value="IT">🇮🇹 Italy</option>
            <option value="CA">🇨🇦 Canada</option>
            <option value="AU">🇦🇺 Australia</option>
            <option value="JP">🇯🇵 Japan</option>
            <option value="CN">🇨🇳 China</option>
            <option value="KR">🇰🇷 South Korea</option>
            <option value="OTHER">Other</option>
          </select>
        </div>

        <div class="space-y-2">
          <label class="font-label-sm text-label-sm text-on-surface-variant block uppercase tracking-wider" for="pwd">Password</label>
          <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-3 text-black focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all placeholder:text-gray-600" id="pwd" name="password" placeholder="••••••••••••" type="password" required autocomplete="new-password" />
          <ul class="pwd-req">
            <li id="req-len">At least 8 characters</li>
            <li id="req-upper">At least 1 uppercase letter</li>
            <li id="req-num">At least 1 number</li>
            <li id="req-special">At least 1 special character (!@#$…)</li>
          </ul>
        </div>

        <button class="w-full bg-primary-container text-white font-bold py-4 rounded-lg shadow-[0_0_20px_rgba(168,85,247,0.3)] hover:shadow-[0_0_30px_rgba(168,85,247,0.5)] hover:scale-[1.02] active:scale-[0.98] transition-all duration-300" type="submit" id="submit-btn">
          Sign Up
        </button>
      </form>

      <div class="mt-10 text-center">
        <p class="font-body-md text-on-surface-variant">
          Already have an account?
          <a class="text-primary font-bold hover:text-primary/80 transition-colors ml-1" href="/?page=login">Log in</a>
        </p>
      </div>
    </div>
  </div>
</main>
<script>
  const pwdInput = document.getElementById('pwd');
  const rules = {
    'req-len': p => p.length >= 8,
    'req-upper': p => /[A-Z]/.test(p),
    'req-num': p => /[0-9]/.test(p),
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

    const name = (fd.get('name') || '').trim();

    const res = await API.post('auth', {
      action: 'register',
      name,
      email: fd.get('email'),
      phone: fd.get('phone'),
      nationality: fd.get('nationality'),
      password: pwd,
    });

    setLoading(btn, false);
    if (res.success) {
      location.href = '/?page=login';
    } else {
      showAlert('#alert-box', res.message || 'Registration failed.');
    }
  });
</script>
<?php end_layout(); ?>