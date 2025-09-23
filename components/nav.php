<header id="appHeader2" class="fixed top-0 z-50 flex justify-between md:px-10 py-[19px] md:py-3 px-3 backdrop-blur-xl bg-transparent">
        <!-- Left Side Of The Header -->
        <article class="flex items-center gap-4">
          <button id="menuBtn" class="top-1 left-2 z-50 text-blue-900 cursor-pointer">
            <!-- Hamburger (☰) -->
             <svg id="hamburgerIcon" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <rect x="3" y="6"  width="18" height="2.5" rx="1.25" />
              <rect x="3" y="11" width="14" height="2.5" rx="1.25" />
              <rect x="3" y="16" width="10" height="2.5" rx="1.25" />
            </svg>
          </button>
        </article>
        <?php
          // --- Resolve user from session by id or email ---
          $id    = $_SESSION['id']    ?? $_SESSION['user_id'] ?? null;
          $email = $_SESSION['email'] ?? null;

          $user = null;
          if ($id !== null) {
            $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
          } elseif (!empty($email)) {
            $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
          }

          if (isset($stmt)) {
            $stmt->execute();
            $res  = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();
          }

          // --- Derive avatar label ---
          $label = 'U'; // fallback
          if ($user) {
            if (!empty($user['name'])) {
              $parts = preg_split('/\s+/', trim($user['name']));
              $label = strtoupper(substr($parts[0], 0, 1));
            } elseif (!empty($user['email'])) {
              $label = strtoupper(substr($user['email'], 0, 1));
            } elseif (!empty($user['id'])) {
              $label = 'U';
            }
          }

          // Notification badge (ensure variable exists)
          $notif_count = isset($notif_count) ? (int)$notif_count : 0;
        ?>
        <article style="position: relative; display: inline-block;">
          <!-- Trigger -->
          <button class="px-3 py-1 rounded-full bg-[#B5707D] text-white" id="profile-dropdown"> <?= htmlspecialchars($label) ?></button>

          <!-- Menu -->
          <section style="position: absolute; right: 0; z-index: 1000;" class="hidden bg-white text-black p-4 md:p-5 rounded-lg shadow-lg w-60 mt-2" id="profile-dropdown-menu">
            <!-- ACCOUNT -->
            <div class="px-1 py-2">
              <p class="text-xs font-semibold text-gray-400 tracking-wide uppercase">Account</p>
              <div class="py-2">
                <div class="mt-2 flex items-center">
                  <div class="w-8 h-8 bg-[#B5707D] rounded-full flex items-center justify-center text-white font-bold">
                    <?= htmlspecialchars($label) ?>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm font-medium text-gray-800">
                      <?= $user && !empty($user['name']) ? htmlspecialchars($user['name']) : 'User' ?>
                    </p>
                  </div>
                </div>

                <div class="mt-3 space-y-1">
                  <button id="manageTab" class="flex items-center px-2 py-1 text-sm text-gray-700 hover:bg-gray-100 rounded">
                    Manage account
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="w-4 h-4 ml-auto text-gray-400"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13.828 10.172a4 4 0 010 5.656m-1.414-1.414a2 2 0 112.828-2.828"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12h.01"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- HELP -->
            <div class="px-1 py-2 space-y-1 border-t border-gray-100">
              <a href="./dropdown/help.php" class="block px-2 py-1 text-sm text-gray-700 hover:bg-gray-100 rounded">
                Help
              </a>
            </div>

            <!-- LOG OUT -->
            <div class="px-1 py-2 bg-gray-50 border-t border-gray-100 rounded-b-lg">
              <form action="./register/logout.php" method="POST">
                <button type="submit" class="w-full text-left px-2 py-1 text-sm text-gray-700 hover:bg-gray-100 rounded">
                  Log out
                </button>
              </form>
            </div>
          </section>
        </article>
  </header>

<div id="success-message"
     class="fixed top-20 md:left-1/2 md:ml-0 ml-20 transform md:-translate-x-1/2
            px-6 py-3 rounded-lg shadow-lg text-sm font-medium
            transition-opacity duration-500
            <?php if (empty($_SESSION['flash'])): ?> hidden <?php endif; ?>
            <?php if (!empty($_SESSION['flash']) && str_starts_with($_SESSION['flash'], '✅')): ?>
              text-zinc-800 bg-zinc-50 border border-zinc-200
            <?php else: ?>
              text-red-800 bg-red-50 border border-red-200
            <?php endif; ?>">
  <?php if (!empty($_SESSION['flash'])): ?>
    <?= htmlspecialchars($_SESSION['flash']) ?>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>
</div>