 <!-- Header -->
  <header class="flex md:hidden justify-between md:bg-white bg-slate-800 md:px-10 py-[19px] md:py-3 px-3">
      <!-- Left Side Of The Header -->
      <article class="flex items-center gap-4">
        <button aria-label="Search" class="text-white md:text-black">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="7" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
        </button>

        <button id="menuBtn" class="top-1 left-2 z-50 md:hidden text-white md:text-black">
          <!-- Hamburger (☰) -->
          <svg id="hamburgerIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M4 6h16M4 12h16M4 18h16"/>  </svg>

          <!-- Close (✕), hidden by default -->
          <svg id="closeIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M6 18L18 6M6 6l12 12"/> </svg>
        </button>
      </article>

      <!-- Right Side Of The Header -->
      <article class="flex items-center gap-8">
          <!-- three-dot menu (horizontal) -->
          <button class="p-2 text-gray-200 hover:text-white">
          <svg class="md:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 6" width="18" height="6" fill="currentColor" aria-label="More options" role="img">
            <circle cx="3"  cy="3" r="3"/>
            <circle cx="12" cy="3" r="3"/>
            <circle cx="21" cy="3" r="3"/>
          </svg>
          </button>

          <button aria-label="Notifications" class="relative md:block hidden">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          <!-- badge -->
          <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-rose-500 rounded-full">5</span>
        </button>

        <button aria-label="Messages" class="relative hidden md:block">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
          <!-- badge -->
          <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-emerald-500 rounded-full">3</span>
        </button>
            
          <!-- Profile -->
      <div class="rounded-full bg-gray-200 w-10 h-10 py-2 px-4 md:block hidden">S</div>
    </article>
  </header>
      
  <main class="flex">
    <!-- Aside -->
    <aside id="sidebar" class="w-60 md:block hidden bg-[#263544] overflow-x-none min-h-screen bg-slate-800">
    <a href="/ItemPilot/home.php">
      <div class="flex items-center gap-2 px-4 py-4">
        <!-- Logo icon -->
        <img src="images/icon(1).png" alt="Pilota logo" class="h-6 w-6 md:h-8 md:w-8 shrink-0"/>

        <!-- Brand name -->
        <span class="text-white font-semibold text-lg tracking-wide">Pilota</span>
      </div>
    </a>
      <nav>
        <ul>
          <!-- DASHBOARD -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-5" id="home">
            <svg class="w-4 h-4 mt-[2px] text-gray-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M3 11l9-7 9 7" />   
            <path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10" />
            <path d="M9 21V12h6v9" />          
            </svg>
            <li><a href="#home" class="text-gray-400">Dashboard</a></li>
          </div>
          <!-- TABLES -->
          <button class="w-60 px-2 ml-4 mt-3 py-1 flex items-center justify-start">
            <div id="events" class="select-none">
              <span class="flex justify-center items-center gap-2 text-gray-400">
                <!-- icon -->
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                  <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span class="text-gray-400">Tables</span>
              </span>
            </div>
            <div id="tablesItem" type="button">
              <svg class="chev text-gray-400 w-4 h-4 transition-transform ml-10" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M6 9l6 6 6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            </button>
            <!-- submenu (INDENTED, NOT absolute) -->
            <ul id="dropdown" class="hidden pl-8 mt-1 space-y-1">
              <?php
              $stmt = $conn->prepare("SELECT table_id, table_title FROM `tables` WHERE user_id = ? ORDER BY table_id ASC");
              $stmt->bind_param('i', $uid);
              $stmt->execute();
              $res = $stmt->get_result();
              if ($res && $res->num_rows):
                while ($row = $res->fetch_assoc()):
              ?>
                <li class="flex justify-between mr-5">
                  <a href="#" class="js-table-link block px-4 py-2 text-gray-300 hover:text-white" data-table-id="<?= (int)$row['table_id'] ?>"><?= htmlspecialchars($row['table_title'] ?? '') ?></a>
                  <a href="categories/Universal Table/delete_table.php?table_id=<?= (int)$row['table_id'] ?>" onclick="return confirm('Are you sure you want to delete this entire table?');" class="text-red-500 hover:text-red-700 mt-2"><i class="fas fa-trash-alt"></i></a>
                </li>
              <?php
              endwhile;
              else:
              ?>
              <li class="px-4 py-2 italic text-gray-400">No tables yet.</li>
            <?php endif; ?>
            </ul>
          </div>
          <!-- CONTACT US -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="contact">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2z"/>
            </svg>
            <li><a href="#contact" class="text-gray-400">Contact Us</a></li>
          </div>
          <!-- DATA TOOLS -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="data-tools">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/>
            </svg>
            <li><a href="#data-tools" class="text-gray-400">Data Tools</a></li>
          </div>
          <!-- INSIGHTS -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="insights">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <polyline points="3 3 21 3 21 21"/><line x1="3" y1="17" x2="21" y2="17"/>
              <line x1="3" y1="11" x2="21" y2="11"/>
            </svg>
            <li><a href="#insights" class="text-gray-400">Insights</a></li>
          </div>
          <!-- USER TOOLS -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="user-tools">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            <li><a href="#user-tools" class="text-gray-400">User Tools</a></li>
          </div>
          <!-- SETTINGS -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="settings">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="3"/>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            <li><a href="#settings" class="text-gray-400">Settings</a></li>
          </div>  
        </ul>
      </nav>  
    </aside>