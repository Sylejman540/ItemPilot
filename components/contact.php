  <!-- Contact Us -->
  <section class="bg-gray-100 rounded-md hidden w-full" id="contact-right">
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
    
    <div class="flex gap-2 md:px-50 mt-20">
      <img src="images/contact.png" alt="Contact" class="w-10 h-10 rounded-full mr-3">
      <h4 class="md:text-sm text-md font-medium mt-3 text-gray-600">Contact Us</h4>
    </div>
    <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50 md:mr-0 ml-4 mr-4"></div>

      <!-- Contact Form Is Here -->
      <form action="" class="md:ml-0 md:mr-0 ml-4 mr-4">
        <div class="md:flex md:text-start text-center md:gap-50 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Name</h1>
            <p class="text-sm text-gray-600">Your full name so we know who’s reaching out.</p>
          </div>
          <input type="text" class="border-1 bg-white border-gray-400 h-8 rounded-lg w-100 md:mt-9 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
        <div class="md:flex md:text-start text-center md:gap-64 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Email Address</h1>
            <p class="text-sm text-gray-600">We’ll use this to reply to your message.</p>
          </div>
          <input type="email" class="border-1 bg-white border-gray-400 h-8 rounded-lg w-100 md:mt-9 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] md:ml-50 bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
        <div class="md:flex md:text-start text-center md:gap-54 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Subject</h1>
            <p class="text-sm text-gray-600">A brief summary of your question or request.</p>
          </div>
          <input type="text" class="border-1 border-gray-400 h-20 bg-white rounded-lg w-100 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] md:ml-50 bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
        <div class="md:flex md:text-start text-center md:gap-82 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Message</h1>
            <p class="text-sm text-gray-600">Tell us how we can help you.</p>
          </div>
          <input type="text" class="border-1 border-gray-400 h-20 bg-white rounded-lg w-100 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] md:ml-50 bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
          
        <button class="bg-blue-600 hover:bg-blue-500 mb-5 text-white rounded-lg py-1 px-4 cursor-pointer mt-4 md:ml-50" type="submit">Reach Out</button>
      </form>
  </section>