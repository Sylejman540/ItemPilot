<!-- amCharts 5 -->
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

<section class="bg-gray-100 rounded-md w-full" id="home-right">
        
<article class="mx-5 md:mx-12 lg:mx-16 mb-10">
  <!-- Section header -->
  <div class="flex items-center gap-3 mt-[100px] mb-5">
    <img src="images/home-dashboard.png" alt="Home Dashboard" class="w-10 h-10 rounded-full">
    <h4 class="md:text-sm text-base font-medium text-gray-600">Overview</h4>
  </div>

  <!-- KPIs and Line chart -->
  <div class="grid lg:grid-cols-3 gap-6">
    <!-- Line chart -->
    <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
      <h3 class="text-lg font-semibold mb-6">Deals Over Time</h3>
      <div id="dealsChart" class="min-h-[330px]"></div>
    </div>

    <!-- KPI cards -->
    <div class="flex flex-col gap-6">
      <?php foreach ($kpis as $k): ?>
        <div class="bg-white p-6 rounded-xl shadow flex justify-between items-center">
          <div>
            <div class="text-sm text-gray-500"><?= htmlspecialchars($k['metric']) ?></div>
            <div class="text-2xl font-bold <?= $k['color'] ?>">
              <?= !empty($k['isPct']) ? $k['value'].'%' : number_format($k['value']) ?>
            </div>
            <div class="text-xs text-gray-500"><?= htmlspecialchars($k['dateRange']) ?></div>
          </div>
          <div class="w-12 h-12 rounded-full flex items-center justify-center
            <?php echo str_contains($k['color'],'blue') ? 'bg-blue-500/20 text-blue-600' :
                        (str_contains($k['color'],'emerald') ? 'bg-emerald-500/20 text-emerald-600' :
                        (str_contains($k['color'],'amber') ? 'bg-amber-500/20 text-amber-600' : 'bg-gray-200')); ?>">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="9"></circle>
              <path d="M12 8v4l2 2"></path>
            </svg>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Donut + Bar + Radial -->
  <div class="grid lg:grid-cols-3 gap-6 mt-8">
    <div class="bg-white p-6 rounded-xl shadow">
      <h3 class="text-lg font-semibold mb-6">Status Breakdown</h3>
      <div id="statusChart" class="min-h-[330px]"></div>
    </div>
    <div class="bg-white p-6 rounded-xl shadow">
      <h3 class="text-lg font-semibold mb-6">Records by Assignee</h3>
      <div id="assigneeChart" class="min-h-[330px]"></div>
    </div>
    <div class="bg-white p-6 rounded-xl shadow">
      <h3 class="text-lg font-semibold mb-6">Completion Rate</h3>
      <div id="completionChart" class="min-h-[330px]"></div>
    </div>
  </div>

  <!-- Progress bars -->
  <div class="mt-8 bg-white p-6 rounded-xl shadow">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
      <?php foreach ($progress as $p): ?>
        <div>
          <div class="font-medium"><?= htmlspecialchars($p['metric']) ?></div>
          <div class="text-lg font-bold">
            <?= !empty($p['isPct']) ? $p['value'].'%' : number_format($p['value']) ?>
            <span class="<?= $p['delta'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?> text-sm pl-1">
              <?= ($p['delta'] >= 0 ? '+' : '').$p['delta'] ?>%
            </span>
          </div>
          <div class="h-2 bg-gray-200 rounded mt-2 relative overflow-hidden">
            <div class="h-2 <?= $p['bar'] ?> absolute left-0 top-0" style="width:<?= $p['pct'] ?>%"></div>
            <div class="absolute -top-1.5 w-3 h-3 rounded-full border border-white bg-white shadow"
                 style="left: calc(max(0%, min(100%, <?= (int)$p['pct'] ?>%)) - 6px);"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

<div id="dealsChart"></div>
<div id="statusChart"></div>
<div id="assigneeChart"></div>
<div id="completionChart"></div>
<div id="dealsBurnupChart"></div>
<div id="dealsMAChart"></div>
<div id="weekdayChart"></div>
<div id="monthlyChart"></div>
<div id="dealsSparkline"></div>

</article>
</section>

