<?php

  session_start();
  require_once __DIR__ . '/db.php';
  require_once "register/register.php";
  $uid = $_SESSION['user_id'] ?? 0;

  // If a table_id is passed, load that instead of the default
  $tableId = isset($_GET['table_id']) ? (int)$_GET['table_id'] : null;

  if ($tableId) {
    $stmt = $conn->prepare("
      SELECT id,name,notes,assignee,status,attachment_summary
        FROM universal
      WHERE user_id = ? AND id = ?
    ");
    $stmt->bind_param('ii', $uid, $tableId);
  } else {
    $stmt = $conn->prepare("
      SELECT id,name,notes,assignee,status,attachment_summary
        FROM universal
      WHERE user_id = ?
    ORDER BY id ASC
    ");
    $stmt->bind_param('i', $uid);
  }

$stmt->execute();
$rows      = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hasRecord = !empty($rows);
$first     = $hasRecord ? $rows[0] : null;
$stmt->close();
  
    // 1️⃣ Total records in `universal`
  $totalRecords = $conn
    ->query("SELECT COUNT(*) FROM universal")
    ->fetch_row()[0];

  // 2️⃣ “Completed” entries (whatever you’re calling completed in your status column)
  $completed = $conn
    ->query("
      SELECT COUNT(*)
        FROM universal
       WHERE status = 'completed'
    ")
    ->fetch_row()[0];

  // 3️⃣ New records in the last 7 days
  $newLast7 = $conn
    ->query("
      SELECT COUNT(*)
        FROM universal
       WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")
    ->fetch_row()[0];

  // 4️⃣ Active users in the last 30 days (distinct user_id)
  $activeUsers = $conn
    ->query("
      SELECT COUNT(DISTINCT user_id)
        FROM universal
       WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")
    ->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en" class="overflow-x-hidden">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <title>ItemPilot</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <!-- Tailwind CDN -->
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <style>
    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="bg-gray-100">
    <button id="menuBtn" class="fixed top-1 left-2 z-50 p-2 md:hidden">
    <!-- Hamburger (☰) -->
    <svg id="hamburgerIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M4 6h16M4 12h16M4 18h16"/>  </svg>

    <!-- Close (✕), hidden by default -->
    <svg id="closeIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M6 18L18 6M6 6l12 12"/> </svg>
  </button>

  <main class="flex">
  <!-- Aside -->
  <aside id="sidebar" class="mt-5 md:block hidden">
    <!-- Logo will be put here -->
    <div class="flex hover:bg-gray-200 rounded-md w-60 py-1 cursor-pointer px-2 flex gap-9 ml-2 mb-2"> 
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 50" class="w-48 h-8"><polygon points="10,25 60,5 70,25 60,20 20,30" fill="black"/><text x="90" y="32"  font-family="Arial, sans-serif"  font-size="24"  font-weight="bold"  fill="black">ItemPilot</text></svg>
    </div>
    <!-- Line -->
    <div class="h-[1px] w-60 bg-gray-300"></div>
    <nav>
      <ul>
        <div class="flex gap-2 mt-5">
          <div class="w-[2px] h-5 bg-black mt-1"></div>
        <div class="hover:bg-gray-200 rounded-md w-60 py-1 cursor-pointer px-2 flex gap-2" id="home">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.75L12 3l9 6.75v10.5a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1-.75-.75V9.75z" /><path d="M9 21V12h6v9" /></svg>
          <li><a href="#home" class="font-medium text-sm text-gray-700 hover:text-black">Overview</a></li>
        </div>
        </div>
        <div class="relative group">
          <div id="events" class="flex items-center gap-2 ml-6 mt-3 px-2 py-1 w-60 rounded-md hover:bg-gray-200 cursor-pointer">
            <span class="font-medium text-sm text-gray-700 group-hover:text-black ml-8">Tables</span>
            <li id="openTable" class="ml-5">
              <svg width="18" height="18" viewBox="0 0 24 24" aria-label="Chevron down" role="img" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </li>
          </div>

          <div class="absolute left-6 top-full mt-1 w-60 bg-white rounded-md shadow-lg transition-all z-10 hidden" id="dropdown">
            <?php $res = $conn->query("SELECT id, title FROM universal WHERE user_id = {$uid} ORDER BY id ASC LIMIT 1");
              if ($res->num_rows):
              while ($row = $res->fetch_assoc()):
            ?>
              <li>
                <a href="#" id="universal" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                  <?= htmlspecialchars($row['title']) ?>
                </a>
              </li>
            <?php   
              endwhile;
              else:
            ?>
              <li class="px-4 py-2 italic text-gray-500">No tables yet.</li>
            <?php endif; ?>
          </div>
        </div>
        <div class="flex mt-3 ml-6 gap-2 hover:bg-gray-200 rounded-md w-60 py-1 cursor-pointer px-2" id="contact">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></svg>
          <li><a href="#contact" class="font-medium text-sm text-gray-700 hover:text-black">Contact Us</a></li>
        </div>
      </ul>
    </nav>  

    <section class="ml-5 mt-70">
      <div class="flex gap-2 hover:bg-gray-200 rounded-md w-60 cursor-pointer px-2 mt-2 mb-2">
        <a href="#" class="inline-block text-gray-400 hover:text-black">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mt-[5px] mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 8v4m0 4h.01"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>
        </a>
        <h3 class="text-sm font-medium text-black mt-1 mb-1">Support</h3>
      </div>
      <div class="flex gap-2 hover:bg-gray-200 rounded-md w-60 cursor-pointer px-2 mt-2 mb-2">
        <a href="#" class="inline-block text-gray-400 hover:text-black">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mt-1 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 11l1.553 3.106a2 2 0 001.788 1.121l3.352.49-2.425 2.363a2 2 0 00-.565 1.767l.574 3.341-3.003-1.579a2 2 0 00-1.846 0l-3.003 1.579.574-3.341a2 2 0 00-.565-1.767L9.607 16.717l3.352-.49a2 2 0 001.788-1.121L15 11zM5 11l.5 1a1 1 0 00.895.553l1.084.158-.786.766a1 1 0 00-.294.884l.194 1.13-.996-.522a1 1 0 00-.928 0l-1.236.646.196-1.136a1 1 0 00-.294-.884l-.786-.766 1.084-.158A1 1 0 005 11z"/></svg>
        </a>
        <h3 class="text-sm text-black mt-2 font-medium mb-1">ChangeLog</h3>
      </div>
    </section>
  </aside>

  <!-- Right Side -->
  <section class="bg-white rounded-md md:mt-5 md:mr-5 py-10 w-full" id="home-right">
    <!-- Header -->
    <header class="flex justify-between md:ml-0 md:mr-0 md:mt-0 ml-4 mr-4 md:px-50">
      <h1 class="text-lg font-semibold">Good Afternoon, sir<h1>
    </header>

    <article class="ml-8 md:ml-15 md:mr-25">
      <h4 class="md:text-sm text-md font-medium mt-20 mb-5">Overview</h4>

      <div class="md:flex md:justify-between">
        <!-- Total Records -->
        <div class="grid">
          <h3 class="text-md font-medium mt-5">Total Records</h3>
          <canvas id="chart1" data-value="<?php echo $totalRecords ?>" width="120" height="80"></canvas>
        </div>

        <!-- Completed -->
        <div class="grid">
          <h3 class="text-md font-medium mt-5">Completed</h3>
          <canvas id="chart2" data-value="<?php echo $completed ?>" width="120" height="80"></canvas>
        </div>

        <!-- New (7d) -->
        <div class="grid">
          <h3 class="text-md font-medium mt-5">New (7d)</h3>
          <canvas id="chart3" data-value="<?php echo $newLast7 ?>" width="120" height="80"></canvas>
        </div>

        <!-- Active Users (30d) -->
        <div class="grid">
          <h3 class="text-md font-medium mt-5">Active Users (30d)</h3>
          <canvas id="chart4" data-value="<?php echo $activeUsers ?>" width="120" height="80"></canvas>
        </div>
      </div>
    </article>
  </section>

  <!-- Contact Us -->
  <section class="bg-white rounded-md md:mt-5 md:mr-5 py-10 hidden w-full" id="contact-right">
      <h4 class="text-lg font-medium md:mt-20 mb-5 md:px-50 text-center md:text-start">Contact Us</h4>
      <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50 md:mr-0 ml-4 mr-4"></div>

      <!-- Contact Form Is Here -->
      <form action="" class="md:ml-0 md:mr-0 ml-4 mr-4">
        <div class="md:flex md:text-start text-center md:gap-50 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Name</h1>
            <p class="text-sm text-gray-600">Your full name so we know who’s reaching out.</p>
          </div>
          <input type="text" class="border-1 border-gray-400 h-8 rounded-lg w-100 md:mt-9 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
        <div class="md:flex md:text-start text-center md:gap-64 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Email Address</h1>
            <p class="text-sm text-gray-600">We’ll use this to reply to your message.</p>
          </div>
          <input type="email" placeholder="example@gmail.com" class="border-1 border-gray-400 h-8 rounded-lg w-100 md:mt-9 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] md:ml-50 bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
        <div class="md:flex md:text-start text-center md:gap-54 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Subject</h1>
            <p class="text-sm text-gray-600">A brief summary of your question or request.</p>
          </div>
          <input type="text" class="border-1 border-gray-400 h-20 rounded-lg w-100 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] md:ml-50 bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
        <div class="md:flex md:text-start text-center md:gap-82 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Message</h1>
            <p class="text-sm text-gray-600">Tell us how we can help you.</p>
          </div>
          <input type="text" class="border-1 border-gray-400 h-20 rounded-lg w-100 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] md:ml-50 bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
          
        <button class="bg-black text-white rounded-lg py-1 px-4 cursor-pointer mt-4 md:ml-50" type="submit">Reach Out</button>
      </form>
  </section>

  <!-- Tables Section --->
  <section class="bg-white rounded-md md:mt-5 md:mr-5 py-10 hidden w-full" id="event-right">
    <h4 class="text-lg font-medium md:mt-20 mb-5 md:px-50 text-center md:text-start">Tables</h4>

    <div class="md:flex md:justify-between md:px-50 md:ml-0 md:mr-0 ml-4 mr-4">
      <div class="flex gap-5">
        <input type="search" placeholder="Search tables..." class="rounded-lg px-2 border-1 border-gray-300 h-10 w-80">
        <select name="" id="" class="border-1 border-gray-300 rounded-lg px-2">
          <option value="name">Sort by name</option>
          <option value="date">Sort by date</option>
          <option value="status">Sort by status</option>
        </select>
      </div>

      <div class="flex justify-center md:block">
        <button class="bg-black text-white rounded-lg py-1 px-4 cursor-pointer md:mt-0 mt-5 modal-open" type="submit" data-modal-target="categories">Choose a template</button>
      </div>
    </div>

    <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50 md:mr-0 ml-4 mr-4"></div>
  </section>

  <!-- CATEGORIES MODAL -->
  <main id="categories" class="fixed inset-0 z-50 hidden max-w-md mx-auto p-12 overflow-auto shadow-md rounded-lg mt-5 mb-5 bg-white/100">
    <!-- Header -->
    <header class="flex justify-between">
      <h1 class="text-xl font-semibold">Choose a template</h1>
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 modal-close cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </header>

    <!-- Some Templates Will Be Shown/Add Here -->
    <section class="mt-20 space-y-5">
      <!-- Universal Table -->
      <article class="flex justify-between items-center mb-4" id="blank">
        <div class="border rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19" stroke-linecap="round" stroke-linejoin="round"/><line x1="5" y1="12" x2="19" y2="12" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg text-start">Start with a blank base</h1>
          <p class="text-sm text-gray-300 text-start">Create custom tables, fields, views</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>
      
      <!-- Groceries Table -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-yellow-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13a2 2 0 100 4 2 2 0 000-4m10 0a2 2 0 100 4 2 2 0 000-4"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Grocery List</h1>
          <p class="text-sm text-gray-300">Organize shopping list,for market</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Sales Strategy Table -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-blue-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V5m0 14v-3"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Sales Strategy</h1>
          <p class="text-sm text-gray-300">Unify sales, marketing, products...</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Blog Editorial Calendar -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-red-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Blog Editorial Calendar</h1>
          <p class="text-sm text-gray-300">Organize article ideas and flexibly</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Study Guides -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-blue-200 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 8.25h6M9 11.25h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 4.5h13.5a.75.75 0 01.75.75v14.25a.75.75 0 01-.75.75H5.25a.75.75 0 01-.75-.75V5.25a.75.75 0 01.75-.75z"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Study Guides</h1>
          <p class="text-sm text-gray-300">Create structured notes for subject</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Job Hunting -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-purple-600 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.5h19.5V18a2.25 2.25 0 01-2.25 2.25H4.5A2.25 2.25 0 012.25 18V7.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6a3.75 3.75 0 017.5 0v1.5"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Job Hunting</h1>
          <p class="text-sm text-gray-300">Stay organized during ur job search</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Home Remodel -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-orange-600 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l7-8 11 8v8a2 2 0 01-2 2H5a2 2 0 01-2-2v-8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 21V12h6v9"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Home Remodel</h1>
          <p class="text-sm text-gray-300">Manage all aspect of the remodeling</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Car Buying -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-gray-600 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13l1-5h16l1 5M5 16a2 2 0 100 4 2 2 0 000-4m14 0a2 2 0 100 4 2 2 0 000-4"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Car Buying</h1>
          <p class="text-sm text-gray-300">Monitor anique dealers, pricing and..</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Pet Meidcal History -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-pink-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 14a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0zm-3-4a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm9 0a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm-3-3a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v4m2-2h-4"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Pet Medical History</h1>
          <p class="text-sm text-gray-300">Track pet medications and appoint...</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Expense Tracking -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-pink-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="2" ry="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M2 11h20"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Expense Tracking</h1>
          <p class="text-sm text-gray-300">Capture and organize all reciepts a..</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
  // Grab elements safely
  const eventRight   = document.getElementById("event-right");
  const homeRight    = document.getElementById("home-right");
  const contactRight = document.getElementById("contact-right");
  const blank        = document.getElementById("blank");
  const universal    = document.getElementById("universal");

  const openTable = document.getElementById("openTable");
  openTable.addEventListener('click', tableOpened);

  function tableOpened(){
    const dropdown = document.getElementById("dropdown");

    dropdown.style.display = "block";
  }

  // Preserve the current page from the URL
  let currentPage = parseInt(new URLSearchParams(window.location.search).get("page")) || 1;

  // Utility to load a given page of the table via AJAX
  function loadTable(page) {
    fetch(`categories/Universal Table/insert_universal.php?page=${page}`)
      .then(r => r.text())
      .then(html => {
        eventRight.innerHTML   = html;
        if (homeRight)    homeRight.style.display    = "none";
        if (contactRight) contactRight.style.display = "none";
        eventRight.style.display = "block";
        currentPage = page;
      });
  }

  // 1. Template-item clicks
  document.querySelectorAll('.template-item').forEach(el => {
    el.addEventListener('click', () => {
      const id   = el.dataset.id;
      const name = el.dataset.name;
      const sel  = document.getElementById('selectedTemplate');
      if (sel) sel.textContent = name;
      window.location.href = `home.php?table_id=${id}&page=${currentPage}`;
    });
  });

  // 2. Sidebar menu toggle
  const menuBtn = document.getElementById('menuBtn');
  if (menuBtn) {
    const sidebar       = document.getElementById('sidebar');
    const hamburgerIcon = document.getElementById('hamburgerIcon');
    const closeIcon     = document.getElementById('closeIcon');
    menuBtn.addEventListener('click', () => {
      const nowVisible = !sidebar.classList.toggle('hidden');
      if (hamburgerIcon && closeIcon) {
        hamburgerIcon.classList.toggle('hidden', nowVisible);
        closeIcon.classList.toggle('hidden', !nowVisible);
      }
    });
  }

  // 3. Section tabs
  const homeTab    = document.getElementById("home");
  const contactTab = document.getElementById("contact");
  const eventsTab  = document.getElementById("events");

  function homePage(){
    if (homeRight)    homeRight.style.display    = "block";
    if (contactRight) contactRight.style.display = "none";
    if (eventRight)   eventRight.style.display   = "none";
  }
  function contactPage(){
    if (homeRight)    homeRight.style.display    = "none";
    if (contactRight) contactRight.style.display = "block";
    if (eventRight)   eventRight.style.display   = "none";
  }
  function eventsPage(){
    if (homeRight)    homeRight.style.display    = "none";
    if (contactRight) contactRight.style.display = "none";
    if (eventRight)   eventRight.style.display   = "block";
  }

  if (homeTab)    homeTab.addEventListener('click', homePage);
  if (contactTab) contactTab.addEventListener('click', contactPage);
  if (eventsTab)  eventsTab.addEventListener('click', eventsPage);

  // 4. Open modals via data-modal-target
  document.querySelectorAll('[data-modal-target]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const tgt = document.getElementById(btn.dataset.modalTarget);
      if (tgt) tgt.classList.remove('hidden');
    });
  });

  // 5. Close modals (.modal-close)
  document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.fixed');
      if (modal) modal.classList.add('hidden');
    });
  });

  // 6. Dismiss register/login overlays
  ['register-modal','login-modal'].forEach(id => {
    const modal = document.getElementById(id);
    if (modal) {
      modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.add('hidden');
      });
    }
  });

  // 7 & 8. Load insert_universal via AJAX on “blank” or “universal” clicks
  [blank, universal].forEach(el => {
    if (el && eventRight) {
      el.addEventListener("click", e => {
        e.preventDefault();
        const categories = document.getElementById("categories");
        if (categories) categories.classList.add("hidden");
        loadTable(currentPage);
      });
    }
  });

  // 9. Intercept pagination links inside eventRight
  document.body.addEventListener('click', e => {
    // pagination links
    const pg = e.target.closest('.pagination a');
    if (pg) {
      e.preventDefault();
      const url = new URL(pg.href, window.location.origin);
      const p   = parseInt(url.searchParams.get('page')) || 1;
      loadTable(p);
      return;
    }

    // Open “Add New” form
    const addBtn = e.target.closest('#addIcon');
    if (addBtn) {
      e.preventDefault();
      const addForm = document.getElementById('addForm');
      if (addForm) addForm.classList.remove('hidden');
    }

    // Close “Add New”
    const closeAdd = e.target.closest('[data-close-add]');
    if (closeAdd) {
      const addForm = document.getElementById('addForm');
      if (addForm) addForm.classList.add('hidden');
    }

    // Edit Title modal
    const editBtn = e.target.closest('#openForm');
    if (editBtn) {
      e.preventDefault();
      const wrap = document.getElementById('editFormWrapper');
      if (wrap) wrap.classList.remove('hidden');
    }
    const closeModal = e.target.closest('[data-close-modal]');
    if (closeModal) {
      const wrap = document.getElementById('editFormWrapper');
      if (wrap) wrap.classList.add('hidden');
    }

    // THEAD form
    const openThead = e.target.closest('#openTbodyForm');
    if (openThead) {
      e.preventDefault();
      const theadF = document.getElementById('theadForm');
      if (theadF) theadF.classList.remove('hidden');
    }
    const closeTheadBtn = e.target.closest('[data-close-thead]');
    if (closeTheadBtn) {
      const theadF = document.getElementById('theadForm');
      if (theadF) theadF.classList.add('hidden');
      const tbodyF = document.getElementById('tbodyForm');
      if (tbodyF) tbodyF.classList.add('hidden');
    }

    // TBODY form
    const openTbody = e.target.closest('#openTheadForm');
    if (openTbody) {
      e.preventDefault();
      const tbodyF = document.getElementById('tbodyForm');
      if (tbodyF) tbodyF.classList.remove('hidden');
    }
  });
  // Auto-load table if redirected from edit_thead.php
  const shouldAutoload = new URLSearchParams(window.location.search).get("autoload");
  if (shouldAutoload) {
    loadTable(currentPage);
  }

})();
</script>

</body>
</html>
