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
      <a href="/ItemPilot/index.php" class="block cursor-pointer"><h1 class="text-4xl font-bold text-center mb-8 mt-6">Sign<span style="color: #BCF07F">Up</span></h1></a>
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
      <button type="submit" name="signup" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition cursor-pointer">Create Account</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-6">Already have an account?<a href="/login" class="text-blue-600 hover:underline">Log in</a></p>
  </div>

  <!-- Login Form -->
  <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg hidden" id="login">
    <div class="flex gap-1 justify-center items-center">
      <img src="images/icon.png" alt="Icon" class="w-15 h-15">
      <a href="/ItemPilot/index.php" class="block cursor-pointer"><h1 class="text-4xl font-bold text-center mb-8 mt-6">Log<span style="color: #BCF07F">In</span></h1></a>
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
        echo '<div class="text-red-500 text-start mt-2">Please check your info, your password or email is wrong!</div>';
      }
      ?>
      <button type="submit" name="login" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition cursor-pointer">Login</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-6">Don't have an account?<a href="/login" class="text-blue-600 hover:underline">Signup in</a></p>
  </div>

  <script src="main.js"></script>
</body>
</html>
