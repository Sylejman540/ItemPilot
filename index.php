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
  <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg" id="signup">
    <div class="flex gap-1 justify-center items-center">
      <img src="images/icon.png" alt="Icon" class="w-15 h-15">
      <a href="/ItemPilot/index.php" class="block cursor-pointer"><h1 class="text-4xl font-bold text-center mb-8 mt-6">Sign Up</h1></a>
    </div>
    <form action="register/register.php" method="POST" class="space-y-6">
      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Name</label>
        <input type="text" name="name" id="name" placeholder="Enter your name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      <div>
        <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
        <input type="email" name="email" id="email" placeholder="you@example.com" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        <?php
        $status = $_GET['status'] ?? null;
        if($status === 'invalid_email'){
          echo '<div class="text-red-500 text-start mt-2">Email is taken</div>';
        }
        ?>
      </div>
      <div>
        <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
        <input type="password" name="password" id="password" placeholder="********" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      <button type="submit" name="signup" class="w-full py-3 bg-black text-white font-semibold rounded-lg transition cursor-pointer">Create Account</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-6 cursor-pointer" id="signup-button">Already have an account?<span class="text-blue-600 hover:underline">Log in</span></p>
  </div>

  <!-- Login Form -->
  <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg hidden" id="login">
    <div class="flex gap-1 justify-center items-center">
      <img src="images/icon.png" alt="Icon" class="w-15 h-15">
      <a href="/ItemPilot/index.php" class="block cursor-pointer"><h1 class="text-4xl font-bold text-center mb-8 mt-6">Log In</h1></a>
    </div>
    <form action="register/login.php" method="POST" class="space-y-6">
      <div>
        <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
        <input type="email" name="email" id="email" placeholder="you@example.com" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      <div>
        <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
        <input type="password" name="password" id="password" placeholder="********" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <?php
        $status = $_GET['status'] ?? null;
        if($status === 'invalid_data'){
          echo '<div class="text-red-500 text-center mt-2">Please check your info, your password or email is wrong!</div>';
        }
      ?>

      <button type="submit" name="login" class="w-full py-3 bg-black text-white font-semibold rounded-lg transition cursor-pointer">Login</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-6 cursor-pointer" id="login-button">Don't have an account?<span class="text-blue-600 hover:underline">Sign In</span></p>
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
