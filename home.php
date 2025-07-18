<?php
 require_once "register/register.php";
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
<body class="bg-gray-100 overflow-x-hidden">
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
          <li><a href="#home" class="font-medium text-sm text-gray-700 hover:text-black">Home</a></li>
        </div>
        </div>
        <div class="flex gap-2 ml-6 mt-3 hover:bg-gray-200 rounded-md w-60 py-1 cursor-pointer px-2" id="events">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"  stroke-linejoin="round"><rect x="4" y="4" width="14" height="14" rx="2" /><rect x="8" y="8" width="14" height="14" rx="2" /></svg>
          <li><a href="#events" class="font-medium text-sm text-gray-700 hover:text-black">Events</a></li>
        </div>
        <div class="flex mt-3 ml-6 gap-2 hover:bg-gray-200 rounded-md w-60 py-1 cursor-pointer px-2" id="contact">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></svg>
          <li><a href="#contact" class="font-medium text-sm text-gray-700 hover:text-black">Contact Us</a></li>
        </div>
      </ul>
    </nav>

    <section class="ml-5 mt-30">
      <h6 class="text-sm text-gray-400 px-2">Upcoming Events</h6>
      <h3 class="text-sm font-medium text-black mt-4 hover:bg-gray-200 rounded-md w-60 py-1 cursor-pointer px-2">Coming Up</h3>
      <h3 class="text-sm font-medium text-black mt-2 hover:bg-gray-200 rounded-md w-60 py-1 cursor-pointer px-2">This Week’s Highlights</h3>
      <h3 class="text-sm font-medium text-black mt-2 hover:bg-gray-200 rounded-md w-60 py-1 cursor-pointer px-2">Next Milestones</h3>
      <h3 class="text-sm font-medium text-black mt-2 hover:bg-gray-200 rounded-md w-60 py-1 cursor-pointer px-2">Planned Features</h3>
    </section>

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
      <!-- The + Button -->
      <div class="flex justify-end" id="home">
        <button type="button" data-modal-target="categories" class="modal-open">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black cursor-pointer md:mt-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19" stroke-linecap="round" stroke-linejoin="round"/><line x1="5" y1="12" x2="19" y2="12" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>
    </header>

    <article class="ml-8 md:ml-15 md:mr-25">
      <h4 class="md:text-sm text-md font-medium mt-20 mb-5">Overview</h4>
      <div class="h-[1px] bg-gray-200 w-90 mt-2 md:hidden"></div>
      <!-- 4 Lines -->
      <div class="md:flex hidden justify-between">
        <div class="h-[1px] bg-gray-200 w-68 mt-2"></div>
        <div class="h-[1px] bg-gray-200 w-68 mt-2"></div>
        <div class="h-[1px] bg-gray-200 w-68 mt-2"></div>
        <div class="h-[1px] bg-gray-200 w-68 mt-2"></div>
      </div>

      <div class="md:flex md:justify-between">
        <div class="grid">
          <h3 class="text-md font-medium mt-5">Total Tables</h3>
          <h1 class="mt-3 text-gray-400">Unknown</h1>
          <span class="bg-green-200 text-green-600 py-1 px-3 w-15 rounded-xl text-sm mt-3">4.5%</span>
          <div class="h-[1px] bg-gray-200 w-90 mt-2 md:hidden"></div>
        </div>

        <div class="grid">
          <h3 class="text-md font-medium mt-5">Completed Tasks</h3>
          <h1 class="mt-3 text-gray-400">Unknown</h1>
          <span class="bg-pink-200 text-pink-600 py-1 px-3 w-15 rounded-xl text-sm mt-3">2.5%</span>
          <div class="h-[1px] bg-gray-200 w-90 mt-2 md:hidden"></div>
        </div>

        <div class="grid">
          <h3 class="text-md font-medium mt-5">New Records</h3>
          <h1 class="mt-3 text-gray-400">Unknown</h1>
          <span class="bg-yellow-200 text-yellow-600 py-1 px-3 w-15 rounded-xl text-sm mt-3">3.5%</span>
          <div class="h-[1px] bg-gray-200 w-90 mt-2 md:hidden"></div>
        </div>

        <div class="grid">
          <h3 class="text-md font-medium mt-5">Active Users</h3>
          <h1 class="mt-3 text-gray-400">Unknown</h1>
          <span class="bg-red-200 text-red-600 py-1 px-3 w-15 rounded-xl text-sm mt-3">0.5%</span>
          <div class="h-[1px] bg-gray-200 w-90 mt-2 md:hidden"></div>
        </div>
      </div>
    </article>

    <article class="md:ml-0 ml-8 md:mr-0 mr-8 md:px-50">
      <!-- Recents Tables -->
      <h2 class="text-md font-medium mt-20">Recents Tables</h2>
      <p class="text-gray-400 text-center mt-10">No tables avaiable, click on that "+" icon to add a table</p>
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

  <!-- Event Section --->
  <section class="bg-white rounded-md md:mt-5 md:mr-5 py-10 hidden w-full" id="event-right">
    <h4 class="text-lg font-medium md:mt-20 mb-5 md:px-50 text-center md:text-start">Events</h4>

    <div class="md:flex md:justify-between md:px-50 md:ml-0 md:mr-0 ml-4 mr-4">
      <div class="flex gap-5">
        <input type="search" placeholder="Search events..." class="rounded-lg px-2 border-1 border-gray-300 h-10 w-80">
        <select name="" id="" class="border-1 border-gray-300 rounded-lg px-2">
          <option value="name">Sort by name</option>
          <option value="date">Sort by date</option>
          <option value="status">Sort by status</option>
        </select>
      </div>

      <div class="flex justify-center md:block">
        <button class="bg-black text-white rounded-lg py-1 px-4 cursor-pointer md:mt-0 mt-5" type="submit">Check for more</button>
      </div>
    </div>

    <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50 md:mr-0 ml-4 mr-4"></div>

    <p class="text-center text-gray-400 mt-20">No available events..</p>
  </section>


  <!-- CATEGORIES MODAL -->
  <main id="categories" class="fixed inset-0 z-50 hidden max-w-md mx-auto p-12 overflow-auto shadow-md mt-10 mb-10 bg-white/100 ">
    <!-- Header -->
    <header class="flex justify-between">
      <h1 class="text-xl font-semibold">Choose a template</h1>
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 modal-close cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </header>

    <!-- Some Templates Will Be Shown/Add Here -->
    <section class="mt-20 space-y-5">
    <a href="categories/Universal Table/insert_universal.php">
      <!-- Universal Table -->
      <article class="flex justify-between items-center mb-4">
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

<script>
    const menuBtn       = document.getElementById('menuBtn');
    const sidebar       = document.getElementById('sidebar');
    const hamburgerIcon = document.getElementById('hamburgerIcon');
    const closeIcon     = document.getElementById('closeIcon');

    menuBtn.addEventListener('click', () => {
      // Toggle the 'hidden' class on <aside>
      const nowVisible = !sidebar.classList.toggle('hidden');

      // Swap icons: hide hamburger when open, show when closed
      hamburgerIcon.classList.toggle('hidden', nowVisible);
      closeIcon.classList.toggle('hidden', !nowVisible);
    });


    const home = document.getElementById("home");
    const homeRight = document.getElementById("home-right");
    const contact = document.getElementById('contact');
    const contactRight = document.getElementById("contact-right");
    const events = document.getElementById('events');
    const eventRight = document.getElementById("event-right");

    home.addEventListener('click', homePage);
    contact.addEventListener('click', contactPage);
    events.addEventListener('click', eventsPage);

    function homePage(){
      homeRight.style.display = "block";
      contactRight.style.display = "none";
      eventRight.style.display = "none";
    }

    function contactPage(){
      homeRight.style.display = "none";
      contactRight.style.display = "block";
      eventRight.style.display = "none";
    }

    function eventsPage(){
      homeRight.style.display = "none";
      contactRight.style.display = "none";
      eventRight.style.display = "block";
    }


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
