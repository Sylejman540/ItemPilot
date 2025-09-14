<style>
  /* keep the header stretched between sidebar and right edge */
  #appHeader3 {
    top: 0;
    left: var(--sbw);
    right: 0;
    width: auto;
  }

  /* consistent KPI icon tile */
  .kpi-icon {
    width: 40px;              /* w-10 */
    height: 40px;             /* h-10 */
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: .5rem;     /* rounded-lg */
  }
</style>

<header id="appHeader3"
        class="absolute md:mt-15 mt-13 transition-all duration-300 ease-in-out">
  <section id="home-right" class="w-full">
    <article class="mt-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- KPI row -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

        <!-- Total Tables -->
        <div class="bg-white p-6 rounded-xl shadow flex items-center justify-between min-h-[110px]">
          <div>
            <h4 class="text-sm font-medium text-gray-500">Total Tables</h4>
            <p class="text-2xl font-semibold text-gray-900"><?= $totalTables ?></p>
          </div>
          <div class="kpi-icon bg-blue-100 text-blue-600" aria-hidden="true">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </div>
        </div>

        <!-- Total Records -->
        <div class="bg-white p-6 rounded-xl shadow flex items-center justify-between min-h-[110px]">
          <div>
            <h4 class="text-sm font-medium text-gray-500">Total Records</h4>
            <p class="text-2xl font-semibold text-gray-900"><?= $totalRecords ?></p>
          </div>
          <div class="kpi-icon bg-green-100 text-green-600" aria-hidden="true">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m4-4H8"/>
            </svg>
          </div>
        </div>

        <!-- Active This Month -->
        <div class="bg-white p-6 rounded-xl shadow flex items-center justify-between min-h-[110px]">
          <div>
            <h4 class="text-sm font-medium text-gray-500">Active This Month</h4>
            <p class="text-2xl font-semibold text-gray-900"><?= $activeThisMonth ?></p>
          </div>
          <div class="kpi-icon bg-yellow-100 text-yellow-600" aria-hidden="true">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
        </div>

        <!-- Completed Tasks -->
        <div class="bg-white p-6 rounded-xl shadow flex items-center justify-between min-h-[110px]">
          <div>
            <h4 class="text-sm font-medium text-gray-500">Completed Tasks</h4>
            <p class="text-2xl font-semibold text-gray-900"><?= $completedTasks ?></p>
          </div>
          <div class="kpi-icon bg-purple-100 text-purple-600" aria-hidden="true">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
          </div>
        </div>
      </div>

      <!-- Row 1 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">Total Tables</h3>
            <button aria-label="Total Tables actions"
                    class="p-2 rounded-md hover:bg-gray-100">
              <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <circle cx="4" cy="10" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="16" cy="10" r="2"/>
              </svg>
            </button>
          </div>
          <div id="areaChart" class="min-h-[330px]"></div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">Table Titles</h3>
            <button aria-label="Table Titles actions"
                    class="p-2 rounded-md hover:bg-gray-100">
              <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <circle cx="4" cy="10" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="16" cy="10" r="2"/>
              </svg>
            </button>
          </div>
          <div id="polarAreaChart" class="min-h-[330px]"></div>
        </div>
      </div>

      <!-- Row 2 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">Tables Per Month</h3>
            <button aria-label="Tables Per Month actions"
                    class="p-2 rounded-md hover:bg-gray-100">
              <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <circle cx="4" cy="10" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="16" cy="10" r="2"/>
              </svg>
            </button>
          </div>
          <div id="barChart" class="min-h-[330px]"></div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">Status</h3>
            <button aria-label="Status actions"
                    class="p-2 rounded-md hover:bg-gray-100">
              <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <circle cx="4" cy="10" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="16" cy="10" r="2"/>
              </svg>
            </button>
          </div>
          <div id="radarChart" class="min-h-[330px]"></div>
        </div>
      </div>

      <!-- Row 3 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">All Records</h3>
            <button aria-label="All Records actions"
                    class="p-2 rounded-md hover:bg-gray-100">
              <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <circle cx="4" cy="10" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="16" cy="10" r="2"/>
              </svg>
            </button>
          </div>
          <div id="gradientLineChart" class="min-h-[330px]"></div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">Status</h3>
            <button aria-label="Pie Status actions"
                    class="p-2 rounded-md hover:bg-gray-100">
              <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <circle cx="4" cy="10" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="16" cy="10" r="2"/>
              </svg>
            </button>
          </div>
          <div id="pieChart" class="min-h-[330px]"></div>
        </div>
      </div>

    </article>
  </section>
</header>
