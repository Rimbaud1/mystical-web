<?php
/* includes/navbar.php — dynamic navigation bar */
require_once __DIR__ . '/auth.php';
$user = current_user();           // null if guest
?>
<!-- NAVBAR -->
<nav class="bg-gray-900/80 backdrop-blur-md border-b border-amber-500/20 py-4 fixed w-full z-20">
  <div class="container mx-auto px-4 flex justify-between items-center">
      <!-- Logo -->
      <a href="index.php" class="flex items-center">
          <i class="fas fa-dungeon text-2xl text-amber-500 mr-2"></i>
          <span class="text-xl font-bold text-gradient">Mystical&nbsp;Dungeons</span>
      </a>

      <!-- Desktop links -->
      <div class="hidden md:flex items-center space-x-6">
          <a href="index.php"        class="text-gray-300 hover:text-amber-400"><i class="fas fa-home mr-1"></i>Home</a>
          <a href="play.php"         class="text-gray-300 hover:text-amber-400"><i class="fas fa-play mr-1"></i>Play</a>
          <a href="create_map.php"   class="text-gray-300 hover:text-amber-400"><i class="fas fa-map mr-1"></i>Create</a>

          <?php if ($user): ?>
              <!-- Account dropdown (click to toggle) -->
              <div class="relative">
                  <button id="accountBtn"
                          class="text-gray-300 hover:text-amber-400 flex items-center focus:outline-none">
                      <i class="fas fa-user mr-1"></i>Account
                      <i class="fas fa-chevron-down ml-1 text-xs"></i>
                  </button>

                  <!-- Menu -->
                  <div id="accountMenu"
                       class="absolute right-0 mt-2 w-44 bg-gray-800 border border-amber-500/30
                              rounded-lg shadow-lg hidden">
                      <a href="profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">Profile</a>
                      <a href="credits.html" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">Credits</a>
                      <?php if ($user['role'] === 'admin'): ?>
                        <a href="admin.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">Admin</a>
                      <?php endif; ?>
                      <a href="logout.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">Logout</a>
                    
                  </div>
              </div>
          <?php else: ?>
              <a href="login-signup.php" class="text-gray-300 hover:text-amber-400">
                  <i class="fas fa-sign-in-alt mr-1"></i>Login
              </a>
          <?php endif; ?>
      </div>

      <!-- Mobile burger (not implemented) -->
      <button class="md:hidden text-gray-300"><i class="fas fa-bars text-xl"></i></button>
  </div>
</nav>
<!-- Spacer so content isn’t hidden under fixed nav -->
<div class="pt-20"></div>

<!-- Toggle script (vanilla JS) -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn   = document.getElementById('accountBtn');
    const menu  = document.getElementById('accountMenu');
    if (!btn || !menu) return;

    btn.addEventListener('click', e => {
        e.stopPropagation();
        menu.classList.toggle('hidden');
    });

    // Close when clicking outside
    document.addEventListener('click', () => menu.classList.add('hidden'));
});
</script>
