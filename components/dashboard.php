<style>
  #appHeader3{
  top: 0;
  left: var(--sbw); 
  right: 0;    
  width: auto;              
}
</style>

<header id="appHeader3"  class="absolute md:mt-13 mt-10 transition-all duration-300 ease-in-out"   style="padding-left: 1.25rem; padding-right: 1.25rem;">
<section class="bg-gray-100 w-full px-4 sm:px-6 lg:px-8 py-10" id="home-right">
  <article class="mt-10">
  <!-- Header KPIs -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
  
  <!-- Total Tables -->
  <div class="bg-white p-6 rounded-xl shadow flex items-center justify-between">
    <div>
      <h4 class="text-sm font-medium text-gray-500">Total Tables</h4>
      <p class="text-2xl font-bold text-gray-900"><?= $totalTables ?></p>
    </div>
    <div class="bg-blue-100 p-3 rounded-lg">
      <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" 
           viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </div>
  </div>

  <!-- Total Records -->
  <div class="bg-white p-6 rounded-xl shadow flex items-center justify-between">
    <div>
      <h4 class="text-sm font-medium text-gray-500">Total Records</h4>
      <p class="text-2xl font-bold text-gray-900"><?= $totalRecords ?></p>
    </div>
    <div class="bg-green-100 p-3 rounded-lg">
      <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2" 
           viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m4-4H8"/>
      </svg>
    </div>
  </div>

  <!-- Active This Month -->
  <div class="bg-white p-6 rounded-xl shadow flex items-center justify-between">
    <div>
      <h4 class="text-sm font-medium text-gray-500">Active This Month</h4>
      <p class="text-2xl font-bold text-gray-900"><?= $activeThisMonth ?></p>
    </div>
    <div class="bg-yellow-100 p-3 rounded-lg">
      <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" stroke-width="2" 
           viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
      </svg>
    </div>
  </div>

  <!-- Completed Tasks -->
  <div class="bg-white p-6 rounded-xl shadow flex items-center justify-between">
    <div>
      <h4 class="text-sm font-medium text-gray-500">Completed Tasks</h4>
      <p class="text-2xl font-bold text-gray-900"><?= $completedTasks ?></p>
    </div>
    <div class="bg-purple-100 p-3 rounded-lg">
      <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" 
           viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
    </div>
  </div>
</div>

    </div>
    <!-- Row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
      <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
        <h3 class="text-base font-semibold mb-4">Total Tables</h3>
        <div id="areaChart" class="min-h-[330px]"></div>
      </div>
      <div class="bg-white p-6 rounded-xl shadow">
        <h3 class="text-base font-semibold mb-4">Table Titles</h3>
        <div id="polarAreaChart" class="min-h-[330px]"></div>
      </div>
    </div>

    <!-- Row 2 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
      <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
        <h3 class="text-base font-semibold mb-4">Tables Per Month</h3>
        <div id="barChart" class="min-h-[330px]"></div>
      </div>
      <div class="bg-white p-6 rounded-xl shadow">
        <h3 class="text-base font-semibold mb-4">Status</h3>
        <div id="radarChart" class="min-h-[330px]"></div>
      </div>
    </div>

    <!-- Row 3 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
        <h3 class="text-base font-semibold mb-4">All Records</h3>
        <div id="gradientLineChart" class="min-h-[330px]"></div>
      </div>
      <div class="bg-white p-6 rounded-xl shadow">
        <h3 class="text-base font-semibold mb-4">Status</h3>
        <div id="pieChart" class="min-h-[330px]"></div>
      </div>
    </div>

  </article>
</section>
</header>