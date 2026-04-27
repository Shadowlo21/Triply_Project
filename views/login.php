<?php
require_once __DIR__ . '/layout.php';
start_layout('Login', ['shell' => 'auth', 'pageTitle' => 'Triply | Login']);
?>

<div class="absolute inset-0 z-0 overflow-hidden">
  <div class="absolute -top-1/2 -left-1/4 w-full h-full bg-primary/10 rounded-full blur-[120px]"></div>
  <div class="absolute -bottom-1/2 -right-1/4 w-full h-full bg-inverse-primary/10 rounded-full blur-[120px]"></div>
  <div class="absolute inset-0 opacity-15 bg-cover bg-center" style="background-image:url('https://lh3.googleusercontent.com/aida-public/AB6AXuCyC8S4fxWo6dsyyIXceV61Gcvz_yQRu33LcTXIOfZUvoJvKOIDAxqqyBiBKJJChm3vFJ5o1RcFIb1BnS9eEzgVn7eDcMTCkbD-nzjj-eAsrtdf3MQvCOvI1rVC_sejkaTZZviXk0331_xgYidDSVM1_XS8Jb9MvHSolCFEC6rfawWwv0uqFN4awIydChbZi8YEbI8tPSi26WKAVncl_fm1q4yopjsbNhud71Q3jL9UiQ3ZqSz5VnrMzYgvcbpgs_SZMWVW2KKXftZX')"></div>
</div>

<div class="container relative z-10">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5 col-xl-4">
      <div class="mb-12 text-center">
        <div class="inline-flex items-center justify-center p-3 rounded-xl bg-surface-container-high border border-white/5 mb-4 glow-ambient">
          <i class="fa-solid fa-plane-departure text-primary text-3xl"></i>
        </div>
        <h1 class="font-h1 text-h2 text-white tracking-tighter">Triply</h1>
      </div>

      <div class="glass-card rounded-xl p-8 md:p-10 glow-ambient transition-all duration-500 hover:border-primary/30">
        <div class="text-center mb-8">
          <h2 class="font-h2 text-h3 text-white mb-2">Welcome Back</h2>
          <p class="text-on-surface-variant font-body-md">Sign in to continue your journey</p>
        </div>

        <div id="alert-box"></div>
        <form id="login-form" class="space-y-6">
          <div class="space-y-2">
            <label class="font-label-sm text-surface-bright uppercase tracking-widest block px-1" for="email">Email Address</label>
            <div class="relative">
              <i class="fa-regular fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-outline"></i>
              <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg py-3.5 pl-12 pr-4 text-black focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all duration-300 placeholder:text-gray-600" id="email" name="email" placeholder="concierge@triply.com" type="email" required autofocus />
            </div>
          </div>

          <div class="space-y-2">
            <div class="flex justify-between items-center px-1">
              <label class="font-label-sm text-surface-bright uppercase tracking-widest" for="password">Password</label>
              <a class="font-label-sm text-primary hover:text-white transition-colors" href="#" onclick="return false;">Forgot password?</a>
            </div>
            <div class="relative">
              <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-outline"></i>
              <input class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg py-3.5 pl-12 pr-4 text-black focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all duration-300 placeholder:text-gray-600" id="password" name="password" placeholder="••••••••" type="password" required />
            </div>
          </div>

          <button class="w-full bg-primary hover:bg-inverse-primary text-on-primary font-bold py-4 rounded-lg transition-all duration-300 glow-button hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2" type="submit" id="submit-btn">
            <span>Log In</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
        </form>

        <div class="mt-8 pt-8 border-t border-white/5 text-center">
          <p class="text-on-surface-variant font-body-md">
            Don't have an account?
            <a class="text-primary font-bold hover:underline transition-all underline-offset-4 ml-1" href="/?page=register">Create an account</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.getElementById('login-form').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    setLoading(btn, true);
    const fd = new FormData(e.target);
    const res = await API.post('auth', {
      action: 'login',
      email: fd.get('email'),
      password: fd.get('password')
    });
    setLoading(btn, false);
    if (res.success) {
      location.href = '/?page=dashboard';
    } else {
      showAlert('#alert-box', res.message || 'Login failed.');
    }
  });
</script>

<?php end_layout(); ?>