 <!-- Header -->
<main class="flex text-base font-[Open_Sans] overflow-x-hidden overflow-y-hidden">
  <!-- Aside -->
  <aside id="sidebar" class="w-75 block bg-[#263544] min-h-screen">
    <a href="/ItemPilot/home.php">
      <div class="flex items-center gap-2 px-4">
        <!-- Logo icon -->
        <img src="images/logo(4).png" alt="" class="w-30 h-30">
      </div>
    </a>
      <nav>
       <ul class="text-md">
          <!-- ===== GENERAL ===== -->
          <li class="px-6 mt-2 mb-2 text-xs font-semibold tracking-wider text-white uppercase">
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

          <ul id="dropdown" class="hidden pl-8 space-y-1">
            <?php
              // Combine both tables into one result set
              $sql = "
                SELECT table_id, table_title, 'tables' AS src
                FROM tables
                WHERE user_id = ?
                UNION ALL
                SELECT table_id, table_title, 'sales_table' AS src
                FROM sales_table
                WHERE user_id = ?
                ORDER BY table_id ASC
              ";
              $stmt = $conn->prepare($sql);
              $stmt->bind_param('ii', $uid, $uid);
              $stmt->execute();
              $res = $stmt->get_result();

              if ($res && $res->num_rows):
                while ($row = $res->fetch_assoc()):
                  $src  = $row['src']; // which table it came from
                  $tid  = (int)$row['table_id'];
                  $name = htmlspecialchars($row['table_title'] ?? '');
            ?>
              <li class="flex justify-between mr-5 navitem hover:text-white text-[#A7B6CC]">
                <a href="#" class="js-table-link block px-4 py-2 <?= $src === 'sales_table' ? 'js-strategy-link' : '' ?>"  data-table-id="<?= $tid ?>" data-src="<?= $src ?>">
                  <?= $name ?>
                </a>

                <a
                  href="categories/<?= $src === 'sales_table' ? 'Sales Strategy' : 'Universal Table' ?>/delete_table.php?table_id=<?= $tid ?>"
                  onclick="return confirm('Are you sure you want to delete this entire table?');"
                  class="text-red-500 hover:text-red-700 mt-2"
                >
                <button class="text-gray-400 hover:text-red-500 transition mt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" 
                      fill="none" viewBox="0 0 24 24" 
                      stroke-width="1.8" stroke="currentColor" 
                      class="w-5 h-5">
                    <path stroke-linecap="round" 
                          stroke-linejoin="round" 
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
            <?php endif;
              $stmt->close();
            ?>
          </ul>

          <!-- INSIGHTS -->
          <div class="w-70 py-3 px-6 flex justify-start gap-5 sidebar text-[#A7B6CC] hover:text-white cursor-not-allowed" id="insights">
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
        </ul>
      </nav>  
    </aside>

<style>
  #sidebar {
    transition: margin-left 0.3s ease-in-out;
  }
  #sidebar.hidden {
    margin-left: -250px; /* adjust to your sidebar width */
  }

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