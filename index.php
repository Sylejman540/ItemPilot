<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <link rel="icon" href="images/icon.png"/>
  <title>ItemPilot</title>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
  <!-- Register Form -->
  <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg relative" id="signup">
  <!-- Header with logo + title -->
  <div class="flex flex-col items-center">
    <div class="flex gap-2 justify-center items-center">
      <img src="images/icon.png" alt="Icon" class="w-12 h-12">
      <a href="/ItemPilot/index.php" class="block cursor-pointer">
        <h1 class="text-4xl font-bold text-center text-[#263544]">Sign Up</h1>
      </a>
    </div>
    <p class="text-gray-500 text-sm mt-2">Join ItemPilot and start organizing smarter 🚀</p>
  </div>

  <!-- Form -->
  <form action="register/register.php" method="POST" class="space-y-6 mt-6">
    <!-- Name -->
    <div>
      <label for="name" class="block text-gray-700 font-medium mb-2">Name</label>
      <div class="relative">
        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
          <!-- User Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9.004 9.004 0 0112 15c2.386 0 4.553.936 6.121 2.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </span>
        <input type="text" name="name" id="name" placeholder="Enter your name" required 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
    </div>

    <!-- Email -->
    <div>
      <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
      <div class="relative">
        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
          <!-- Email Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12l-4-4-4 4m8 0l-4 4-4-4m16-4H4m16 4H4" />
          </svg>
        </span>
        <input type="email" name="email" id="email" placeholder="you@example.com" required 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      <?php
      $status = $_GET['status'] ?? null;
      if($status === 'invalid_email'){
        echo '<div class="text-red-500 text-start mt-2">Email is taken</div>';
      }
      ?>
    </div>

    <!-- Password -->
    <div>
      <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
      <div class="relative">
        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
          <!-- Lock Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.657 0 3 1.343 3 3v3H9v-3c0-1.657 1.343-3 3-3zM5 11V9a7 7 0 1114 0v2" />
          </svg>
        </span>
        <input type="password" name="password" id="password" placeholder="********" required 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
    </div>

    <!-- Submit -->
    <button type="submit" name="signup" class="w-full py-3 bg-[#263544] hover:bg-[#1d2a36] text-white font-semibold rounded-lg transition cursor-pointer">
      Create Account
    </button>
  </form>

  <!-- Footer -->
  <p class="text-center text-sm text-gray-500 mt-6 cursor-pointer" id="signup-button">
    Already have an account? 
    <span class="text-blue-600 hover:underline">Log in</span>
  </p>
  </div>


  <!-- Login Form -->
<div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg hidden" id="login">
  <!-- Header with logo + title -->
  <div class="flex flex-col items-center">
    <div class="flex gap-2 justify-center items-center">
      <img src="images/icon.png" alt="Icon" class="w-12 h-12">
      <a href="/ItemPilot/index.php" class="block cursor-pointer">
        <h1 class="text-4xl font-bold text-center text-[#263544]">Log In</h1>
      </a>
    </div>
    <p class="text-gray-500 text-sm mt-2">Welcome back 👋 Please enter your details</p>
  </div>

  <!-- Form -->
  <form action="register/login.php" method="POST" class="space-y-6 mt-6">
    <!-- Email -->
    <div>
      <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
      <div class="relative">
        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
          <!-- Email Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12l-4-4-4 4m8 0l-4 4-4-4m16-4H4m16 4H4" />
          </svg>
        </span>
        <input type="email" name="email" id="email" placeholder="you@example.com" required 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
    </div>

    <!-- Password -->
    <div>
      <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
      <div class="relative">
        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
          <!-- Lock Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.657 0 3 1.343 3 3v3H9v-3c0-1.657 1.343-3 3-3zM5 11V9a7 7 0 1114 0v2" />
          </svg>
        </span>
        <input type="password" name="password" id="password" placeholder="********" required 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
    </div>

    <!-- Error Handling -->
    <?php
      $status = $_GET['status'] ?? null;
      if($status === 'invalid_data'){
        echo '<div class="text-red-500 text-center mt-2">Please check your info, your password or email is wrong!</div>';
      }
    ?>

    <!-- Submit -->
    <button type="submit" name="login" class="w-full py-3 bg-[#263544] hover:bg-[#1d2a36] text-white font-semibold rounded-lg transition cursor-pointer">
      Login
    </button>
  </form>

  <!-- Footer -->
  <p class="text-center text-sm text-gray-500 mt-6 cursor-pointer" id="login-button">
    Don’t have an account? 
    <span class="text-blue-600 hover:underline">Sign In</span>
  </p>
</div>


  <script>
    const signup = document.getElementById("signup");
    const login = document.getElementById("login");
    const signButton = document.getElementById("signup-button");
    const loginButton = document.getElementById("login-button");

    // your existing click‑handlers
    signButton.addEventListener('click', function(){
      signup.style.display = "none";
      login.style.display  = "block";
    });
    loginButton.addEventListener('click', function(){
      signup.style.display = "block";
      login.style.display  = "none";
    });

    window.addEventListener('DOMContentLoaded', () => {
      const wantLogin = window.location.hash === '#login' || new URLSearchParams(window.location.search).get('status') === 'invalid_data';

      if (wantLogin) {
        signup.style.display = "none";
        login.style.display  = "block";
      }
    });
  </script>
</body>
</html>
