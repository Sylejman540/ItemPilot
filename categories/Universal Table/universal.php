<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <link rel="icon" href="/images/icon.png"/>
  <title>ItemPilot</title>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
  <!-- Register Form -->
  <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg" id="signup">
    <div class="flex gap-1 justify-center items-center">
      <img src="/ItemPilot/images/icon.png" alt="Icon" class="w-15 h-15">
      <h1 class="text-4xl font-bold text-center mb-8 mt-6">Unnamed record</h1></a>
    </div>
    <form action="insert_universal.php" method="POST" enctype="multipart/form-data" class="space-y-6">
      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Name</label>
        <input type="text" name="name" id="name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Notes</label>
        <input type="text" name="notes" id="notes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Assignee</label>
        <input type="assigne" name="assignee" id="assigne" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="attachment_summary" class="block text-gray-700 font-medium mb-2">Attachment Summary</label>
        <input id="attachment_summary" type="file" name="attachment_summary" accept="image/*" class="w-full border border-gray-300 rounded-lg p-2 text-smfile:bg-pink-100 file:border-0 file:rounded-md file:px-4 file:py-2 file:text-[#B5707D]">
      </div>

      <div>
        <button type="submit" name="universal" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition cursor-pointer">Create Table</button>
      </div>

      <div class="flex justify-center">
        <a href="insert_universal.php" class="text-center text-blue-500 underline">Go Back</a>
      </div>
    </form>
  </div>
</body>
</html>