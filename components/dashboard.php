  <!-- amCharts 5 -->
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

    <section class="bg-gray-100 rounded-md w-full" id="home-right">
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

      <article class="md:ml-15 md:mr-25 ml-5 mr-5 mb-10">
        <div class="flex mt-10 mb-5">
          <img src="images/home-dashboard.png" alt="Home Dashboard" class="w-10 h-10 rounded-full mr-3">
          <h4 class="md:text-sm text-md font-medium mt-3 text-gray-600">Overview</h4>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold mb-6">Deals Analytics</h3>
            <div id="dealsChart" style="height:330px;"></div>
          </div>

          <div class="flex flex-col gap-6">
            <?php foreach ($kpis as $k): ?>
              <div class="bg-white p-6 rounded-xl shadow flex justify-between items-center">
                <div>
                  <div class="text-sm text-gray-500"><?= $k['metric'] ?></div>
                  <div class="text-2xl font-bold <?= $k['color'] ?>">
                    <?= $k['isPct'] ?? false ? $k['value'].'%' : number_format($k['value']) ?>
                  </div>
                  <div class="text-xs text-gray-500"><?= $k['dateRange'] ?></div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center
                  <?php echo str_contains($k['color'],'blue')   ? 'bg-blue-500/20 text-blue-600'   :
                  (str_contains($k['color'],'emerald') ? 'bg-emerald-500/20 text-emerald-600' :
                  (str_contains($k['color'],'amber')   ? 'bg-amber-500/20 text-amber-600' : 'bg-gray-200'));?>">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="9"></circle>
                  <path d="M12 8v4l2 2"></path>
                  </svg>
                </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mt-8 bg-white p-6 rounded-xl shadow">
            <div class="grid lg:grid-cols-4 gap-8">
              <?php foreach ($progress as $i => $p): ?>
                <div>
                  <div class="font-medium"><?= $p['metric'] ?></div>
                  <div class="text-lg font-bold">
                  <?= $p['isPct'] ?? false ? $p['value'].'%' : number_format($p['value']) ?>
                  <span class="<?= $p['delta'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?> text-sm pl-1">
                  <?= $p['delta'] >= 0 ? '+' : '' ?><?= $p['delta'] ?>%
                  </span>
                </div>
                <div class="h-2 bg-gray-200 rounded mt-2 relative overflow-hidden">
                  <div class="h-2 <?= $p['bar'] ?> absolute left-0 top-0" style="width:<?= $p['pct'] ?>%"></div>
                  <div class="absolute -top-1.5 left-[calc(<?= $p['pct'] ?>%-6px)] w-3 h-3 rounded-full border border-white bg-white shadow"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </article>
      </section>  