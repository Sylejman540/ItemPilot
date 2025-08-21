
<!-- Tables Section --->
<section class="bg-gray-100 rounded-md hidden w-full" id="event-right">
    <!-- Header -->
    <header class="md:flex hidden justify-between md:bg-white bg-slate-800 md:px-10 py-[19px] md:py-3 px-3">
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
        <article class="flex items-center gap-8 mr-9">
          <!-- three-dot menu (horizontal) -->
          <button class="p-2 text-gray-200 hover:text-white" id="threeDots">
            <svg class="md:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 6" width="18" height="6" fill="currentColor" aria-label="More options" role="img">
              <circle cx="3"  cy="3" r="3"/>
              <circle cx="12" cy="3" r="3"/>
              <circle cx="21" cy="3" r="3"/>
            </svg>
          </button>
          <div class="flex items-center gap-8 mr-9" id="mobileNav">  
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
          </div>
        </article>
    </header>

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

    <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50 md:mr-0 ml-4 mr-4"></div>
    
    <div class="bg-white rounded-lg md:ml-10 md:mr-15 py-10 md:mt-0 mt-5 overflow-x-auto mb-5">
      <div class="flex gap-1 ml-5 mb-3">
        <img src="images/table.png" alt="Contact" class="w-10 h-10 rounded-full">
        <h4 class="md:text-sm text-md font-medium mt-3 text-gray-600">All Tables</h4>
      </div>

      <table class="w-300 text-sm ml-5 mr-5">
        <!-- invisible header just to lock column widths -->
        <thead class="sr-only">
          <tr>
            <th>Name</th><th>Notes</th><th>Assignee</th><th>Status</th><th>Attachment</th>
          </tr>
        </thead>

        <tbody>
        <?php
          $res = $conn->query("SELECT id, name, notes, assignee, status, attachment_summary FROM universal WHERE user_id = {$uid} ORDER BY id ASC");
          if ($res->num_rows):
          while ($row = $res->fetch_assoc()):
        ?>
          <tr class="border-b border-gray-200 hover:bg-gray-100 cursor-pointer">
            <!-- Name -->
            <td class="px-4 py-2 text-gray-900 whitespace-nowrap">
              <?= htmlspecialchars($row['name']) ?>
            </td>

            <!-- Notes -->
            <td class="px-4 py-2 text-gray-700">
              <?= htmlspecialchars($row['notes']) ?>
            </td>

            <!-- Assignee -->
            <td class="px-4 py-2 text-gray-900 whitespace-nowrap">
              <?= htmlspecialchars($row['assignee']) ?>
            </td>

            <!-- Status badge -->
            <td class="px-4 py-2">
              <?php
                $badge = match ($row['status']) {
                  'Done'        => 'bg-green-100 text-green-800',
                  'In Progress' => 'bg-yellow-100 text-yellow-800',
                  default       => 'bg-gray-100 text-gray-800',
                };
              ?>
              <span class="inline-block px-2 py-1 rounded-full <?= $badge ?>">
                <?= htmlspecialchars($row['status']) ?>
              </span>
            </td>

            <!-- Attachment preview -->
            <td class="px-4 py-2 text-gray-500">
              <?php if ($row['attachment_summary']): ?>
                <img src="/ItemPilot/categories/Universal Table/uploads/<?= htmlspecialchars($row['attachment_summary']) ?>"
                    class="w-20 h-10 rounded-md object-cover" alt="Attachment">
              <?php else: ?>
                <span class="italic text-gray-400">None</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php
            endwhile;
          else:
        ?>
          <tr>
            <td colspan="5" class="px-4 py-4 text-center italic text-gray-400">
              No tables yet.
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
</section>