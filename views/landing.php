<?php
require_once __DIR__ . '/layout.php';
start_layout('Triply - Group Trips Made Effortless', ['shell' => 'public', 'pageTitle' => 'Triply - Group Trips Made Effortless']);
?>

<!-- Hero Section -->
<section class="relative min-h-[921px] flex items-center justify-center py-20 px-6 overflow-hidden">
  <div class="absolute inset-0 z-0">
    <img class="w-full h-full object-cover opacity-40" alt="" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCETQkmPfJxXR-OcNpzwTHAE_aTOSzYQ-E6KcXtY8taEhJpTULYS1IWE6Ki3_79qiTWA3jkqLNl-18IyEba8VjHqs83vhnLpWchMqV3MRhed22B0Ypehx34gxC_fAc8HkzKjQxhtatzLuAvk3hZMnXPrOFRDrDz7V8aB-Vov4SQN1uA0FrUKJ9GaltT8g3hdb0vSf8lSib0zoNlGT_vurR7Cm-VeVbUItRrVQ4m51MeAlxOUvAipTh4fhBJkQodUWe580IamD4Rg7kd"/>
    <div class="absolute inset-0 bg-gradient-to-b from-[#111125]/20 via-[#111125]/80 to-[#111125]"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-purple-900/20 to-transparent"></div>
  </div>
  <div class="container relative z-10 text-center max-w-4xl">
    <h1 class="font-h1 text-h1 text-on-surface mb-6 tracking-tight">Group Trips Made Effortless</h1>
    <p class="font-body-lg text-body-lg text-on-surface-variant mb-10 max-w-2xl mx-auto">
      The all-in-one planning tool for modern travelers. Coordinate itineraries, split expenses, and manage documents in one premium space.
    </p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
      <a class="bg-[#A855F7] text-white font-bold py-4 px-10 rounded-xl hover:scale-105 shadow-[0_0_20px_rgba(168,85,247,0.3)] transition-all no-underline" href="/?page=register">
        Get Started
      </a>
      <a class="border-2 border-[#6C3DD3] text-white font-bold py-4 px-10 rounded-xl hover:bg-white/5 transition-all no-underline cursor-pointer" onclick="openDemoModal(event)">
        Watch Demo
      </a>
    </div>
  </div>
</section>

<div id="demo-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;align-items:center;justify-content:center;padding:20px" onclick="closeDemoModal(event)">
  <div style="position:relative;width:100%;max-width:960px" onclick="event.stopPropagation()">
    <button onclick="closeDemoModal()" style="position:absolute;top:-40px;right:0;background:transparent;border:none;color:#fff;font-size:32px;cursor:pointer;line-height:1" aria-label="Close">×</button>
    <video id="demo-video" controls style="width:100%;border-radius:12px;background:#000" preload="none">
      <source src="/public/assets/DogSayHello.mp4" type="video/mp4">
      Your browser does not support the video tag.
    </video>
  </div>
</div>

<script>
  function openDemoModal(e) {
    if (e) e.preventDefault();
    const m = document.getElementById('demo-modal');
    m.style.display = 'flex';
    const v = document.getElementById('demo-video');
    v.currentTime = 0;
    v.play().catch(() => {});
  }
  function closeDemoModal(e) {
    if (e && e.target.id !== 'demo-modal' && e.type !== 'click') return;
    const m = document.getElementById('demo-modal');
    m.style.display = 'none';
    const v = document.getElementById('demo-video');
    v.pause();
  }
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('demo-modal').style.display === 'flex') closeDemoModal();
  });
</script>

<!-- Features Section -->
<section class="py-32 bg-surface-container-lowest">
  <div class="container">
    <div class="text-center mb-20">
      <span class="text-primary font-label-sm uppercase tracking-widest">Premium Features</span>
      <h2 class="font-h2 text-h2 text-on-surface mt-4">Redefining the Journey</h2>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <div class="glass-card p-8 rounded-xl transition-all duration-300 group">
        <div class="w-14 h-14 bg-primary-container/20 rounded-lg flex items-center justify-center mb-6 border border-primary/30">
          <i class="fa-regular fa-calendar text-[28px] text-primary"></i>
        </div>
        <h3 class="font-h3 text-h3 text-on-surface mb-4">Seamless Itineraries</h3>
        <p class="font-body-md text-on-surface-variant leading-relaxed">
          Drag-and-drop planning that syncs across every guest's device in real-time. Never lose track of a reservation again.
        </p>
      </div>
      <div class="glass-card p-8 rounded-xl transition-all duration-300 group">
        <div class="w-14 h-14 bg-primary-container/20 rounded-lg flex items-center justify-center mb-6 border border-primary/30">
          <i class="fa-solid fa-money-bill-wave text-[28px] text-primary"></i>
        </div>
        <h3 class="font-h3 text-h3 text-on-surface mb-4">Shared Expenses</h3>
        <p class="font-body-md text-on-surface-variant leading-relaxed">
          Automatic bill splitting and transparent tracking. Focus on the experience while Triply handles the math.
        </p>
      </div>
      <div class="glass-card p-8 rounded-xl transition-all duration-300 group">
        <div class="w-14 h-14 bg-primary-container/20 rounded-lg flex items-center justify-center mb-6 border border-primary/30">
          <i class="fa-regular fa-file-lines text-[28px] text-primary"></i>
        </div>
        <h3 class="font-h3 text-h3 text-on-surface mb-4">Secure Documents</h3>
        <p class="font-body-md text-on-surface-variant leading-relaxed">
          Encrypted vault for passports, visas, and flight tickets. Accessible offline for when you're off the grid.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="bg-surface-container-low pt-20 pb-10 border-t border-white/5">
  <div class="container">
    <div class="pt-10 border-t border-white/5 text-center font-label-sm text-on-surface-variant">
      © 2024 Triply Inc. All rights reserved. Designed for elite coordination.
    </div>
  </div>
</footer>

<?php end_layout(); ?>

