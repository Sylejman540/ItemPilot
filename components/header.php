 <!-- Header -->
<main class="flex text-base font-[Open_Sans]">
  <!-- Aside -->
  <aside id="sidebar" class="md:w-75 w-65 block bg-[#263544] min-h-screen">
<!-- Sidebar brand -->
<a href="/ItemPilot/home.php" class="flex items-center gap-3 px-4 py-4">
  <img src="images/icon.png" alt="an2table"
       class="h-7 w-auto md:h-8 block shrink-0 select-none" />
  <span class="brand-text normal-case antialiased leading-none tracking-tight">
    an2table
  </span>
</a>


<style>
  /* Sidebar brand text – force weight & color */
.brand-text{
  color:#A7B6CC !important;
  font-weight:400 !important;     /* not bold */
  font-size:17px;                 /* matches your spec */
  line-height:1 !important;
  letter-spacing:-0.01em;
}
/* Optional hover */
a:hover .brand-text{ color:#B8C6DD; }

</style>
      <nav>
       <ul class="text-md">
          <!-- ===== GENERAL ===== -->
          <li class="px-6 mb-2 text-xs font-semibold tracking-wider text-white uppercase">
            General
          </li>
          <!-- DASHBOARD -->
          <div class="w-70 py-3 cursor-pointer px-6 flex justify-start gap-5 sidebar text-[#A7B6CC] hover:text-white" id="home">
            <svg class="w-4 h-4 mt-[3px]" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <path d="M3 11l9-7 9 7" />
              <path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10" />
              <path d="M9 21V12h6v9" />
            </svg>
            <li><a href="#home">Dashboard</a></li>
          </div>

          <!-- ===== DATA ===== -->
          <li class="px-6 mt-6 mb-2 text-xs font-semibold tracking-wider text-white uppercase">
            Data
          </li>
          <!-- TABLES -->
          <button class="w-[100%] px-6 mt-3 py-3 cursor-pointer flex items-center justify-between sidebar text-[#A7B6CC] hover:text-white">
            <div>
              <span class="select-none flex justify-center items-center gap-5" id="events">
                <!-- icon -->
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="3" y1="10" x2="21" y2="10" /></svg>
                <span>Tables</span>
              </span>
            </div>

            <div id="tablesItem" type="button">
              <svg
                class="chev w-4 h-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
              >
                <path
                  d="M9 18l6-6-6-6"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </div>
          </button>

          <ul id="dropdown" class="hidden pl-8 space-y-1">
          <?php
          // Combine all five into one list (each with user_id filter)
          $sql = "
            SELECT table_id, table_title, 'tables' AS src
            FROM tables
            WHERE user_id = ?
            UNION ALL
            SELECT table_id, table_title, 'dresses_table' AS src
            FROM dresses_table
            WHERE user_id = ?
            UNION ALL
            SELECT table_id, table_title, 'groceries_table' AS src
            FROM groceries_table
            WHERE user_id = ?
            UNION ALL
            SELECT table_id, table_title, 'football_table' AS src
            FROM football_table
            WHERE user_id = ?           -- ✅ added
            UNION ALL
            SELECT table_id, table_title, 'applicants_table' AS src
            FROM applicants_table
            WHERE user_id = ?
            ORDER BY table_id ASC
          ";

          $stmt = $conn->prepare($sql);
          $stmt->bind_param('iiiii', $uid, $uid, $uid, $uid, $uid); // ✅ 5 types for 5 values
          $stmt->execute();
          $res = $stmt->get_result();

          if ($res && $res->num_rows):
            while ($row = $res->fetch_assoc()):
              $src  = $row['src'];
              $tid  = (int)$row['table_id'];
              $name = htmlspecialchars($row['table_title'] ?? '', ENT_QUOTES, 'UTF-8');

              // Map src -> folder name (URL-encoded spaces)
              if ($src === 'dresses_table') {
                $dir = 'Dresses';
                $extraClass = 'js-strategy-link';
              } elseif ($src === 'groceries_table') {
                $dir = 'Groceries%20Table';
                $extraClass = 'js-groceries-link';
              } elseif ($src === 'football_table') {
                $dir = 'Football%20Table';
                $extraClass = 'js-football-link';
              } elseif ($src === 'applicants_table') {
                $dir = 'Applicants%20Table';
                $extraClass = 'js-applicants-link';
              } else {
                $dir = 'Universal%20Table';
                $extraClass = 'js-table-link';
              }
          ?>
            <li class="flex justify-between mr-5 navitem hover:text-white text-[#A7B6CC]">
              <a href="#"
                class="block px-4 py-2 <?= $extraClass ?>"
                data-table-id="<?= $tid ?>"
                data-src="<?= $src ?>">
                <?= $name ?>
              </a>

              <a href="categories/<?= $dir ?>/delete_table.php?table_id=<?= $tid ?>"
                onclick="return confirm('Are you sure you want to delete this entire table?');"
                class="text-red-500 hover:text-red-700 mt-2">
                <button class="text-gray-400 hover:text-red-500 transition mt-1" title="Delete table">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                      viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"
                      class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 3h6m2 4H7l1 12h8l1-12z" />
                  </svg>
                </button>
              </a>
            </li>
          <?php
            endwhile;
          else:
          ?>
            <li class="px-4 py-2 italic text-[#A7B6CC]">No tables yet.</li>
          <?php
          endif;
          $stmt->close();
          ?>
          </ul>

          <!-- INSIGHTS -->
          <div class="w-70 py-3 px-6 flex justify-start gap-5 sidebar text-[#A7B6CC] hover:text-white cursor-not-allowed">
            <svg class="w-4 h-4 mt-[3px]" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <path d="M3 3h18v18H3z"/>
              <path d="M8 9h8M8 13h5M8 17h8"/>
            </svg>
            <li><a href="#insights" class="cursor-not-allowed">Insights</a></li>
          </div>

          <!-- Tester -->
          <div class="hidden" id="insight"></div>

          <!-- ===== USER ===== -->
          <li class="px-6 mt-6 mb-2 text-xs font-semibold tracking-wider text-white uppercase">
            User
          </li>
          <!-- CONTACT US -->
          <div class="w-70 py-3 cursor-pointer px-6 flex justify-start gap-5 sidebar text-[#A7B6CC] hover:text-white" id="contact">
            <svg class="w-4 h-4 mt-[3px]" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <li><a href="#contact">Contact</a></li>
          </div>
        </ul>
      </nav>  
    </aside>

<style>
/* Smooth off-canvas without setting width */
#sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;        /* stick to left edge, full height */
  transform: translateX(-100%);      /* hide by shifting its own width */
  transition: transform 320ms cubic-bezier(.22,.61,.36,1), opacity 200ms linear;
  opacity: .98;
   overflow-y: auto;
  overflow-x: hidden;
  will-change: transform;
  backface-visibility: hidden;
  contain: layout paint;
}

/* Visible */
#sidebar.show {
  transform: translateX(0);
  opacity: 1;
}

/* Optional compatibility class if you still toggle .hidden somewhere */
#sidebar.hidden { transform: translateX(-100%); }

/* Hover effects – avoid border jank */
.sidebar { transition: background-color 160ms cubic-bezier(.22,.61,.36,1); }
.sidebar:hover {
  background-color: #1d2b36;
  color: white;
  width: 100%;
  box-shadow: inset 3px 0 0 0 #3b82f6; /* visual left bar without layout shift */
}

.navitem {
  transition: background-color 160ms cubic-bezier(.22,.61,.36,1), box-shadow 160ms cubic-bezier(.22,.61,.36,1);
}
.navitem:hover {
  box-shadow: inset 2px 0 0 0 #3b82f6;
}

/* Backdrop (mobile/overlay) */
#sidebar-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.35);
  opacity: 0; pointer-events: none;
  transition: opacity 200ms cubic-bezier(.22,.61,.36,1);
}
#sidebar-backdrop.show {
  opacity: 1; pointer-events: auto;
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
  #sidebar, .sidebar, .navitem, #sidebar-backdrop { transition: none !important; }
}

</style>