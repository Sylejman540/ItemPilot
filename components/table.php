<!-- Tables Section -->
<section class="bg-gray-100 rounded-md hidden w-full mb-5" id="event-right">

  <!-- Header -->
  <div class="md:flex md:justify-between md:px-8 ml-4 mr-4 mt-20 md:mb-10 mb-5">
    <div class="flex gap-4">
      <input type="search" placeholder="Search tables..."
        class="rounded-lg px-3 border bg-white border-gray-300 h-10 w-80 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" />
      <select class="border border-gray-300 bg-white rounded-lg px-3 h-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        <option value="name">Sort by name</option>
        <option value="date">Sort by date</option>
        <option value="status">Sort by status</option>
      </select>
    </div>

    <div class="flex justify-center md:block mt-5 md:mt-0">
      <button class="bg-blue-600 cursor-pointer hover:bg-blue-500 text-white rounded-lg py-2 px-5 shadow-sm transition modal-open" type="button" data-modal-target="categories">Choose a template</button>
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
          $tid   = (int)$row['table_id'];
          $title = htmlspecialchars($row['table_title'] ?? 'Untitled Table');
          $createdFmt = $row['created_at'] ? date('M j, Y · H:i', strtotime($row['created_at'])) : '—';
          $href  = "home.php?table_id={$tid}&page=1&type=universal";
      ?>
      <!-- Universal Card -->
      <li class="bg-white rounded-xl shadow-sm hover:shadow-md transition cursor-pointer border border-gray-200">
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
          $sid   = (int)$row['table_id'];
          $title = htmlspecialchars($row['table_title'] ?? 'Untitled Sales');
          $createdFmt = $row['created_at'] ? date('M j, Y · H:i', strtotime($row['created_at'])) : '—';
          $href  = "home.php?table_id={$sid}&page=1&type=sales";
      ?>
      <!-- Sales Card -->
      <li class="bg-white rounded-xl shadow-sm hover:shadow-md transition cursor-pointer border border-gray-200">
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
    </ul>
  </div>
</section>
