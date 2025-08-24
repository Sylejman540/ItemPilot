 <!-- Header -->
<main class="flex text-base font-[Open_Sans] overflow-x-hidden overflow-y-hidden">
  <!-- Aside -->
  <aside id="sidebar" class="w-75 block bg-[#263544] min-h-screen">
    <a href="/ItemPilot/home.php">
      <div class="flex items-center gap-2 px-4 py-4">
        <!-- Logo icon -->
        <img src="images/logo.png" alt="" class="w-15 h-15">

        <!-- Brand name -->
        <span class="text-white font-semibold text-lg tracking-wide">Pilota</span>
      </div>
    </a>
      <nav>
       <ul class="text-md">
          <!-- ===== GENERAL ===== -->
          <li class="px-6 mt-6 mb-2 text-xs font-semibold tracking-wider text-white uppercase">
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
          <button class="w-70 px-6 mt-3 py-3 flex items-center justify-start sidebar text-[#A7B6CC] hover:text-white" id="events">
            <div>
              <span class="select-none flex justify-center items-center gap-5">
                <!-- icon -->
                <svg
                  class="w-4 h-4"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                >
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>

                <span>Tables</span>
              </span>
            </div>

            <div id="tablesItem" type="button">
              <svg
                class="chev w-4 h-4 transition-transform ml-15"
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

          <!-- submenu (INDENTED, NOT absolute) -->
          <ul id="dropdown" class="hidden pl-8 space-y-1">
            <?php
              $stmt = $conn->prepare("SELECT table_id, table_title FROM tables WHERE user_id = ? ORDER BY table_id ASC");
              $stmt->bind_param('i', $uid);
              $stmt->execute();
              $res = $stmt->get_result();
              if ($res && $res->num_rows):
                while ($row = $res->fetch_assoc()):
            ?>
              <li class="flex justify-between mr-5 navitem hover:text-white text-[#A7B6CC]">
                <a
                  href="#"
                  class="js-table-link block px-4 py-2"
                  data-table-id="<?= (int)$row['table_id'] ?>"
                >
                  <?= htmlspecialchars($row['table_title'] ?? '') ?>
                </a>

                <a
                  href="categories/Universal Table/delete_table.php?table_id=<?= (int)$row['table_id'] ?>"
                  onclick="return confirm('Are you sure you want to delete this entire table?');"
                  class="text-red-500 hover:text-red-700 mt-2"
                >
                  <i class="fas fa-trash-alt"></i>
                </a>
              </li>
            <?php
                endwhile;
              else:
            ?>
              <li class="px-4 py-2 italic">No tables yet.</li>
            <?php endif; ?>
          </ul>

          <!-- DATA TOOLS -->
          <div class="w-70 py-3 cursor-pointer px-6 flex justify-start gap-5 sidebar text-[#A7B6CC] hover:text-white" id="data-tools">
            <svg class="w-4 h-4 mt-[3px]" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <line x1="12" y1="20" x2="12" y2="10"/>
              <line x1="18" y1="20" x2="18" y2="4"/>
              <line x1="6" y1="20" x2="6" y2="16"/>
            </svg>
            <li><a href="#data-tools">Data Tools</a></li>
          </div>

          <!-- INSIGHTS -->
          <div class="w-70 py-3 cursor-pointer px-6 flex justify-start gap-5 sidebar text-[#A7B6CC] hover:text-white" id="insights">
            <svg class="w-4 h-4 mt-[3px]" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <path d="M3 3h18v18H3z"/>
              <path d="M8 9h8M8 13h5M8 17h8"/>
            </svg>
            <li><a href="#insights">Insights</a></li>
          </div>

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
          <!-- USER TOOLS -->
          <div class="w-70 py-3 cursor-pointer px-6 flex justify-start gap-5 sidebar text-[#A7B6CC] hover:text-white" id="user-tools">
            <svg class="w-4 h-4 mt-[3px]" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <circle cx="12" cy="7" r="4"/>
              <path d="M6 21v-2a6 6 0 0 1 12 0v2"/>
            </svg>
            <li><a href="#user-tools">User Tools</a></li>
          </div>

          <!-- ===== SETTINGS ===== -->
          <li class="px-6 mt-6 mb-2 text-xs font-semibold tracking-wider text-white uppercase">
            Settings
          </li>
          <div class="w-70 py-3 cursor-pointer px-6 flex justify-start gap-5 sidebar text-[#A7B6CC] hover:text-white" id="settings">
            <svg class="w-4 h-4 mt-[3px]" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="3"/>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33
                      1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51
                      1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06
                      a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09
                      a1.65 1.65 0 0 0 1.51-1c.1-.33.1-.67 0-1a1.65 1.65 0 0 0-.33-1.82l-.06-.06
                      a2 2 0 1 1 2.83-2.83l.06.06c.5.5 1.18.67 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3
                      a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.09c.64.34 1.32.17 1.82-.33l.06-.06
                      a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9
                      a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            <li><a href="#settings">Settings</a></li>
          </div>
        </ul>
      </nav>  
    </aside>

<style>
  .sidebar{
      transition: background-color 0.3s ease;
  }
  .sidebar:hover {
    background-color: #1d2b36ff;
    transition: background-color 0.3s ease;
    color: white;
    border-left: 3px solid #3b82f6; /* Tailwind blue-500 */
  }

  .navitem:hover {
    transition: background-color 0.3s ease;
    border-left: 2px solid #3b82f6; /* Tailwind blue-500 */
  }
</style>