
<!-- Tables Section --->
<section class="bg-gray-100 rounded-md hidden w-full h-screen" id="event-right">

    <div class="md:flex md:justify-between md:px-50 md:ml-0 md:mr-0 ml-4 mr-4 mt-20 md:mb-15 mb-5">
      <div class="flex gap-5">
        <input type="search" placeholder="Search tables..." class="rounded-lg px-2 border-1 bg-white border-gray-300 h-10 w-80">
        <select name="" id="" class="border-1 border-gray-300 bg-white rounded-lg px-2">
          <option value="name">Sort by name</option>
          <option value="date">Sort by date</option>
          <option value="status">Sort by status</option>
        </select>
      </div>

      <div class="flex justify-center md:block">
        <button class="bg-blue-600 hover:bg-blue-500 text-white rounded-lg py-2 px-4 cursor-pointer md:mt-0 mt-5 modal-open" type="submit" data-modal-target="categories">Choose a template</button>
      </div>
    </div>

    
    <div class="bg-white rounded-md shadow-sm ring-1 ring-gray-100 max-w-4xl mx-auto overflow-hidden p-4 px-10">
      <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-100">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
          <i class="fa-solid fa-table text-sm"></i>
        </span>
        <h2 class="text-gray-700 font-semibold text-base">All Tables</h2>
      </div>

      <ul class="divide-y divide-gray-100">
        <?php
          $stmt = $conn->prepare("SELECT table_id, table_title, created_at FROM tables WHERE user_id = ? ORDER BY table_id ASC");
          $stmt->bind_param('i', $uid);
          $stmt->execute();
          $res = $stmt->get_result();
          if ($res && $res->num_rows):
            while ($row = $res->fetch_assoc()):
              $tid   = (int)$row['table_id'];
              $title = htmlspecialchars($row['table_title'] ?? 'Untitled');
              $href  = "home.php?table_id={$tid}&page=1&autoload=1";
              $createdFmt = $row['created_at'] ? date('M j, Y · H:i', strtotime($row['created_at'])) : '—';
        ?>
        <li class="group hover:bg-slate-50 cursor-pointer" data-href="<?= $href ?>">
          <a href="<?= $href ?>" class="flex items-center gap-3 px-4 py-3">
            <span class="h-8 w-8 inline-flex items-center justify-center rounded-md bg-slate-100 text-slate-500 group-hover:bg-blue-50 group-hover:text-blue-600">
              <i class="fa-regular fa-square-check"></i>
            </span>
            <div class="min-w-0 flex-1">
              <div class="flex items-center justify-between gap-4">
                <span class="truncate text-slate-800 group-hover:text-slate-900"><?= $title ?></span>
                <span class="shrink-0 text-xs text-slate-500 group-hover:text-slate-700"><?= $createdFmt ?></span>
              </div>
            </div>
            <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-slate-400"></i>
          </a>
        </li>
        <?php endwhile; else: ?>
        <li class="px-6 py-10 text-center text-slate-500">
          No tables yet. <a href="#" id="createFirst" class="text-blue-600 hover:underline">Create one</a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
</section>