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
    <div class="flex justify-between">
      <a href="insert_universal.php">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="6" x2="18" y2="18" /><line x1="6" y1="18" x2="18" y2="6" /></svg>
      </a>

      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
    </div>

    <h1 class="text-4xl font-bold text-center mb-8 mt-6">Unnamed record</h1></a>
    
    <form action="insert_universal.php" method="POST" enctype="multipart/form-data" class="space-y-6">
      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Name</label>
        <input type="text" name="name" id="name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      
      <div>
        <label for="notes" class="block text-gray-700 font-medium mb-2" contenteditable="true">Notes</label>
        <input type="text" name="notes" id="notes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="assignee" class="block text-gray-700 font-medium mb-2" contenteditable="true">Assignee</label>
        <input type="text" name="assignee" id="assignee" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="status" class="block text-gray-700 font-medium mb-2">Status</label>
        <select type="text" name="status" id="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition">
          <option name="do" id="do">To Do</option>
          <option name="progress" id="progress">In Progress</option>
          <option name="done" id="done">Done</option>
        </select>
      </div>
      
      <div>
        <label for="attachment_summary" class="block text-gray-700 font-medium mb-2">Attachment</label>
        <input id="attachment_summary" type="file" name="attachment_summary" accept="image/*" class="w-full border border-gray-300 rounded-lg p-2 text-smfile:bg-pink-100 file:border-0 file:rounded-md file:px-4 file:py-2 file:text-[#B5707D]">
      </div>

      <div>
        <button type="submit" name="universal" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition cursor-pointer">Create Table</button>
      </div>
    </form>
  </div>
</body>
</html>