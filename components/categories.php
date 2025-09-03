<!-- CATEGORIES MODAL -->
<main id="categories" class="fixed inset-0 z-50 hidden max-w-md mt-20 md:w-[100%] w-[90%] mx-auto my-10 bg-white rounded-lg shadow-md p-12 overflow-auto">
  <!-- Header -->
  <header class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
  <div class="flex items-center gap-2">
    <div class="w-8 h-8 flex items-center justify-center rounded-md bg-blue-100 text-blue-600">
      <!-- template icon -->
      <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
      </svg>
    </div>
    <div>
      <h1 class="text-lg font-semibold">Choose a template</h1>
      <p class="text-xs text-gray-500">Start from scratch or pick a base</p>
    </div>
  </div>
    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 cursor-pointer text-gray-500 modal-close hover:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
    </svg>
  </header>

  <!-- Some Templates Will Be Shown/Add Here -->
  <section class="mt-6 space-y-10">
    <!-- Universal Table -->
    <article class="flex justify-between items-center mb-4 hover:bg-gray-50 p-2 cursor-pointer rounded-md" id="blank">
        <div class="rounded-sm px-3 py-2 flex items-center justify-center bg-gray-100">
          <img src="images/categories/blank.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
          <h1 class="text-lg text-start">Start with a blank base</h1>
          <p class="text-sm text-gray-500 text-start">Create custom tables, fields, views</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>

    <!-- Sales Strategy Table -->
    <article class="flex justify-between items-center mb-4 hover:bg-gray-50 p-2 cursor-pointer rounded-md" id="sales-strategy">
        <div class="bg-blue-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <img src="images/categories/sales.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
          <h1 class="text-lg">Sales Strategy</h1>
          <p class="text-sm text-gray-500">Unify sales, marketing, products...</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>
      
    <!-- Groceries Table -->
    <article class="flex justify-between items-center mb-4 hover:bg-gray-50 p-2 rounded-md" id="groceries"> 
        <div class="bg-yellow-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <img src="images/categories/groceries.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
          <h1 class="text-lg">Grocery List</h1>
          <p class="text-sm text-gray-500">Organize shopping list,for market</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>

    <div class="mt-4 mb-6 p-3 rounded-md bg-amber-100 border border-amber-300 text-amber-800 text-sm font-medium" id="showTemplates">
      🚧 More Templates Comming Soon
    </div>
    <!-- Blog Editorial Calendar -->
    <article class="flex justify-between items-center mb-4 hover:bg-gray-50 p-2 cursor-not-allowed rounded-md">
        <div class="bg-red-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <img src="images/categories/calendar.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
          <h1 class="text-lg">Blog Editorial Calendar</h1>
          <p class="text-sm text-gray-500">Organize article ideas and flexibly</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>

    <!-- Study Guides -->
    <article class="flex justify-between items-center mb-4 hover:bg-gray-50 p-2 cursor-not-allowed rounded-md">
        <div class="bg-blue-200 rounded-sm px-3 py-2 flex items-center justify-center">
          <img src="images/categories/study.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
          <h1 class="text-lg">Study Guides</h1>
          <p class="text-sm text-gray-500">Create structured notes for sub...</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>

    <!-- Job Hunting -->
    <article class="flex justify-between items-center mb-4 hover:bg-gray-50 p-2 rounded-md cursor-not-allowed">
        <div class="bg-rose-200 rounded-sm px-3 py-2 flex items-center justify-center">
          <img src="images/categories/job.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
          <h1 class="text-lg">Job Hunting</h1>
          <p class="text-sm text-gray-500">Stay organized during ur job sea...</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>

    <!-- Home Remodel -->
    <article class="flex justify-between items-center mb-4 hover:bg-gray-50 p-2 cursor-not-allowed rounded-md">
        <div class="bg-green-300 rounded-sm px-3 py-2 flex items-center justify-center">
          <img src="images/categories/home.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
          <h1 class="text-lg">Home Remodel</h1>
          <p class="text-sm text-gray-500">Manage all aspect of the remodel...</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>

    <!-- Car Buying -->
    <article class="flex justify-between items-center mb-4 hover:bg-gray-50 p-2 cursor-not-allowed rounded-md">
        <div class="bg-gray-600 rounded-sm px-3 py-2 flex items-center justify-center">
          <img src="images/categories/car.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
          <h1 class="text-lg">Car Buying</h1>
          <p class="text-sm text-gray-500">Monitor antique dealers, pricing....</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>

    <!-- Pet Medical History -->
    <article class="flex justify-between items-center mb-4 hover:bg-gray-50 p-2 cursor-not-allowed rounded-md">
        <div class="bg-pink-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <img src="images/categories/pet.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
            <h1 class="text-lg">Pet Medical History</h1>
            <p class="text-sm text-gray-500">Track pet medications and appoi...</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>

    <!-- Expense Tracking -->
    <article class="flex justify-between items-center hover:bg-gray-50 p-2 cursor-not-allowed rounded-md">
        <div class="bg-pink-100 rounded-sm px-3 py-2 flex items-center justify-center">
          <img src="images/categories/expense.svg" alt="" class="w-6 h-6">
        </div>
        <div class="grid">
            <h1 class="text-lg">Expense Tracking</h1>
            <p class="text-sm text-gray-500">Capture and organize all reciepts..</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 5l7 7-7 7" /></svg>
    </article>
  </section>
  </div>
</div>
</main>
