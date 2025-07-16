<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <link rel="icon" href="images/icon.png"/>
  <title>ItemPilot</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <!-- Tailwind CDN -->
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <style>
    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="md:ml-10 md:mr-10 ml-5 mr-5">
  <main>
    <div class="flex justify-between" id="home">
      <img src="images/logo.png" alt="Logo" class="w-20 h-20 md:mt-0 mt-3">

    <div class="flex gap-5 md:mt-2">
      <button id="profileButton" class="w-8 h-8 bg-[#B5707D] text-white md:mt-5 mt-8 rounded-full mt-1 flex items-center justify-center font-semibold relative">
        <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?>
      </button>

      <button type="button" data-modal-target="categories" class="modal-open">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black cursor-pointer md:mt-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19" stroke-linecap="round" stroke-linejoin="round"/><line x1="5" y1="12" x2="19" y2="12" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
    </div>
  </div>


  <section>
    <h1 class="text-xl mt-20 ml-5">Recents / Starred</h1>
    <div class="bg-blue-500 h-1 w-[100%]"></div>
  </section>

  <!-- CATEGORIES MODAL -->
  <main id="categories" class="fixed inset-0 z-50 hidden max-w-md mx-auto p-12 overflow-auto shadow-md mt-10 mb-10 backdrop-blur-md">
    <!-- Header -->
    <header class="flex justify-between">
      <h1 class="text-xl font-semibold">Choose a template</h1>
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 modal-close cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </header>

    <!-- Some Templates Will Be Shown/Add Here -->
    <section class="mt-20 space-y-5">
    <a href="categories/Universal Table/insert_universal.php">
      <!-- Universal Table -->
      <article class="flex justify-between items-center">
        <div class="border rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19" stroke-linecap="round" stroke-linejoin="round"/><line x1="5" y1="12" x2="19" y2="12" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg text-start">Start with a blank base</h1>
          <p class="text-sm text-gray-300 text-start">Create custom tables, fields, views</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>
    </a>
      <!-- Groceries Table -->
      <article class="flex justify-between items-center">
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
      <article class="flex justify-between items-center">
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
      <article class="flex justify-between items-center">
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
      <article class="flex justify-between items-center">
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
      <article class="flex justify-between items-center">
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
      <article class="flex justify-between items-center">
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
      <article class="flex justify-between items-center">
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
      <article class="flex justify-between items-center">
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
      <article class="flex justify-between items-center">
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

  <script>
    document.querySelectorAll('[data-modal-target]').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const tgt = document.getElementById(btn.dataset.modalTarget);
        tgt?.classList.remove('hidden');
      });
    });

    document.querySelectorAll('.modal-close').forEach(btn => {
      btn.addEventListener('click', () => {
        btn.closest('.fixed')?.classList.add('hidden');
      });
    });

    ['register-modal', 'login-modal'].forEach(id => {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          modal.classList.add('hidden');
        }
      });
    });
  </script>
</body>
</html>
