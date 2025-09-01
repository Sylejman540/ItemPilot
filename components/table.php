<!-- Tables Section -->
<style>
  #appHeader3 {
    top: 0;
    left: var(--sbw);
    right: 0;
    width: auto;
  }
</style>

<header id="appHeader3" class="absolute md:mt-13 mt-20 transition-all duration-300 ease-in-out px-5">
  <section class="bg-gray-100 rounded-md hidden w-full mb-5" id="event-right">

    <!-- Header -->
    <div class="md:flex md:justify-between md:px-8 ml-4 mr-4 mt-20 md:mb-10 mb-5">
      <div class="flex gap-4">
        <!-- ✅ Search -->
        <input type="search" placeholder="Search tables..."
          class="rounded-lg px-3 border bg-white border-gray-300 h-10 w-80 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" />

        <!-- ✅ Sort -->
        <select
          class="border border-gray-300 bg-white rounded-lg px-3 h-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="name">Sort by name</option>
          <option value="date">Sort by date</option>
          <option value="status">Sort by status</option>
        </select>
      </div>

      <div class="flex justify-center md:block mt-5 md:mt-0">
        <button
          class="bg-blue-600 cursor-pointer hover:bg-blue-500 text-white rounded-lg py-2 px-2 shadow-sm transition modal-open"
          type="button" data-modal-target="categories">Choose a template</button>
      </div>
    </div>

    <!-- Tables Card -->
    <div class="md:ml-10 md:mr-10 ml-4 mr-4">
      <!-- Table List -->
      <ul class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">

        <?php
          // ---- UNIVERSAL TABLES ----
          $stmt = $conn->prepare("SELECT table_id, table_title, created_at FROM tables WHERE user_id = ? ORDER BY table_id ASC");
          $stmt->bind_param('i', $uid);
          $stmt->execute();
          $res = $stmt->get_result();

          while ($row = $res->fetch_assoc()):
            $tid        = (int)$row['table_id'];
            $title      = htmlspecialchars($row['table_title'] ?? 'Untitled Table', ENT_QUOTES, 'UTF-8');
            $createdFmt = $row['created_at'] ? date('M j, Y · H:i', strtotime($row['created_at'])) : '—';
            $createdTs  = $row['created_at'] ? strtotime($row['created_at']) : 0;
            $href       = "/ItemPilot/home.php?autoload=1&table_id={$tid}";
        ?>
        <!-- Universal Card -->
        <li class="bg-white rounded-xl shadow-sm hover:shadow-md transition cursor-pointer border border-gray-200"
            data-name="<?= strtolower($title) ?>"
            data-date="<?= $createdTs ?>"
            data-status="Blank Base">
          <a href="<?= $href ?>" class="block p-5 space-y-3">
            <div class="flex items-center gap-3">
              <img src="images/categories/blank.svg" alt="" class="w-10 h-10">
              <div>
                <h3 class="font-semibold text-gray-800"><?= $title ?></h3>
                <p class="text-sm text-gray-500"><?= $createdFmt ?></p>
                <p class="text-xs text-gray-400">Placeholder for records count</p>
              </div>
            </div>

            <div class="flex justify-between items-center pt-3 border-t">
              <span class="px-3 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Blank Base</span>
              <button class="text-gray-400 hover:text-gray-600 transition">
                <i class="fa-solid fa-ellipsis-vertical"></i>
              </button>
            </div>
          </a>
        </li>
        <?php endwhile; $stmt->close(); ?>


        <?php
          // ---- SALES STRATEGY TABLES ----
          $stmt = $conn->prepare("SELECT table_id, table_title, created_at FROM sales_table WHERE user_id = ? ORDER BY table_id ASC");
          $stmt->bind_param('i', $uid);
          $stmt->execute();
          $res = $stmt->get_result();

          while ($row = $res->fetch_assoc()):
            $sid        = (int)$row['table_id'];
            $title      = htmlspecialchars($row['table_title'] ?? 'Untitled Sales', ENT_QUOTES, 'UTF-8');
            $createdFmt = $row['created_at'] ? date('M j, Y · H:i', strtotime($row['created_at'])) : '—';
            $createdTs  = $row['created_at'] ? strtotime($row['created_at']) : 0;
            $href       = "/ItemPilot/home.php?autoload=1&type=sales&table_id={$sid}";
        ?>
        <!-- Sales Card -->
        <li class="bg-white rounded-xl shadow-sm hover:shadow-md transition cursor-pointer border border-gray-200"
            data-name="<?= strtolower($title) ?>"
            data-date="<?= $createdTs ?>"
            data-status="Sales Strategy">
          <a href="<?= $href ?>" class="block p-5 space-y-3">
            <div class="flex items-center gap-3">
              <img src="images/categories/sales.svg" alt="" class="w-10 h-10">
              <div>
                <h3 class="font-semibold text-gray-800"><?= $title ?></h3>
                <p class="text-sm text-gray-500"><?= $createdFmt ?></p>
                <p class="text-xs text-gray-400">Placeholder for sales data</p>
              </div>
            </div>

            <div class="flex justify-between items-center pt-3 border-t">
              <span class="px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-700">Sales Strategy</span>
              <button class="text-gray-400 hover:text-gray-600 transition">
                <i class="fa-solid fa-ellipsis-vertical"></i>
              </button>
            </div>
          </a>
        </li>
        <?php endwhile; $stmt->close(); ?>


        <?php
          // ---- GROCERIES TABLES ----
          $stmt = $conn->prepare("SELECT table_id, table_title, created_at FROM groceries_table WHERE user_id = ? ORDER BY table_id ASC");
          $stmt->bind_param('i', $uid);
          $stmt->execute();
          $res = $stmt->get_result();

          while ($row = $res->fetch_assoc()):
            $gid        = (int)$row['table_id'];
            $title      = htmlspecialchars($row['table_title'] ?? 'Untitled Groceries', ENT_QUOTES, 'UTF-8');
            $createdFmt = $row['created_at'] ? date('M j, Y · H:i', strtotime($row['created_at'])) : '—';
            $createdTs  = $row['created_at'] ? strtotime($row['created_at']) : 0;
            $href       = "/ItemPilot/home.php?autoload=1&type=groceries&table_id={$gid}";
        ?>
        <!-- Groceries Card -->
        <li class="bg-white rounded-xl shadow-sm hover:shadow-md transition cursor-pointer border border-gray-200"
            data-name="<?= strtolower($title) ?>"
            data-date="<?= $createdTs ?>"
            data-status="Grocery List">
          <a href="<?= $href ?>" class="block p-5 space-y-3">
            <div class="flex items-center gap-3">
              <img src="images/categories/groceries.svg" alt="" class="w-10 h-10">
              <div>
                <h3 class="font-semibold text-gray-800"><?= $title ?></h3>
                <p class="text-sm text-gray-500"><?= $createdFmt ?></p>
                <p class="text-xs text-gray-400">Placeholder for grocery items</p>
              </div>
            </div>

            <div class="flex justify-between items-center pt-3 border-t">
              <span class="px-3 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Grocery List</span>
              <button class="text-gray-400 hover:text-gray-600 transition">
                <i class="fa-solid fa-ellipsis-vertical"></i>
              </button>
            </div>
          </a>
        </li>
        <?php endwhile; $stmt->close(); ?>

      </ul>
    </div>
  </section>
</header>
