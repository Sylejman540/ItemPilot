<?php
require_once __DIR__ . '/../../db.php';
session_start();

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0) {
  header("Location: register/login.php");
  exit;
}

$action   = $_GET['action'] ?? null;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

/* ---------------------------
   Resolve current table_id
----------------------------*/
if ($action === 'create_blank') {
  // Create a new applicants_table row for this user
  $stmt = $conn->prepare("INSERT INTO applicants_table (user_id, created_at) VALUES (?, CURRENT_TIMESTAMP)");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $table_id = (int)$conn->insert_id;
  $stmt->close();
  $_SESSION['current_applicants_table_id'] = $table_id;

} elseif ($table_id > 0) {
  $_SESSION['current_applicants_table_id'] = $table_id;

} else {
  $table_id = (int)($_SESSION['current_applicants_table_id'] ?? 0);

  if ($table_id <= 0) {
    $q = $conn->prepare("SELECT table_id FROM applicants_table WHERE user_id = ? ORDER BY table_id DESC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute();
    $q->bind_result($latestId);
    $q->fetch();
    $q->close();
    $table_id = (int)$latestId;
  }
  if ($table_id <= 0) {
    $stmt = $conn->prepare("INSERT INTO applicants_table (user_id, created_at) VALUES (?, CURRENT_TIMESTAMP)");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $table_id = (int)$conn->insert_id;
    $stmt->close();
  }

  $_SESSION['current_applicants_table_id'] = $table_id;
}

/* ---------------------------
   Create / Update row (POST)
----------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id              = $_POST['id'] ?? '';
  $name            = trim($_POST['name'] ?? '');
  $stage           = trim($_POST['stage'] ?? '');
  $applying_for    = trim($_POST['applying_for'] ?? '');
  $email_address   = trim($_POST['email_address'] ?? '');
  $phone           = trim($_POST['phone'] ?? '');
  $interview_date  = trim($_POST['interview_date'] ?? ''); // expect 'YYYY-MM-DD' or ''
  $interviewer     = trim($_POST['interviewer'] ?? '');
  $interview_score = trim($_POST['interview_score'] ?? '');
  $notes           = trim($_POST['notes'] ?? '');

  // keep existing attachment if none uploaded
  $attachment = $_POST['existing_attachment'] ?? '';

  if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['attachment']['tmp_name'];
    $orig = basename($_FILES['attachment']['name']);

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
      die("Could not create uploads directory.");
    }
    $dest = $uploadDir . $orig;
    if (!move_uploaded_file($tmp, $dest)) {
      die("Failed to save uploaded file.");
    }
    $attachment = $orig;
  }

  // Normalized nullable values
  $interview_date_db  = ($interview_date === '') ? null : $interview_date;
  $interview_score_db = ($interview_score === '') ? null : $interview_score;

  if ($id === '' || $id === null) {
    // INSERT
    $stmt = $conn->prepare("
      INSERT INTO applicants
        (user_id, table_id, name, stage, applying_for, attachment, email_address, phone,
         interview_date, interviewer, interview_score, notes, created_at)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
      'iissssssssis',
      $uid, $table_id, $name, $stage, $applying_for, $attachment, $email_address, $phone,
      $interview_date_db, $interviewer, $interview_score_db, $notes
    );

  } else {
    // UPDATE
    $stmt = $conn->prepare("
      UPDATE applicants
         SET name            = ?,
             stage           = ?,
             applying_for    = ?,
             attachment      = ?,
             email_address   = ?,
             phone           = ?,
             interview_date  = ?,
             interviewer     = ?,
             interview_score = ?,
             notes           = ?
       WHERE id = ? AND table_id = ? AND user_id = ?
    ");
    $stmt->bind_param(
      'ssssssssssiii',
      $name, $stage, $applying_for, $attachment, $email_address, $phone,
      $interview_date_db, $interviewer, $interview_score_db, $notes,
      $id, $table_id, $uid
    );
  }

  $stmt->execute();
  $stmt->close();

  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;
}

/* ---------------------------
   Pagination + data fetch
----------------------------*/
$limit  = 10;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("SELECT COUNT(*) FROM applicants WHERE user_id = ? AND table_id = ?");
$countStmt->bind_param('ii', $uid, $table_id);
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

$dataStmt = $conn->prepare("
  SELECT id, name, stage, applying_for, attachment, email_address, phone,
         interview_date, interviewer, interview_score, notes
    FROM applicants
   WHERE user_id = ? AND table_id = ?
ORDER BY id ASC
   LIMIT ? OFFSET ?
");
$dataStmt->bind_param('iiii', $uid, $table_id, $limit, $offset);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

$hasRecord = count($rows) > 0;

/* ---------------------------
   Table head labels
----------------------------*/
$theadStmt = $conn->prepare("
  SELECT name, stage, applying_for, attachment, email_address, phone,
         interview_date, interviewer, interview_score, notes
    FROM applicants_thead
   WHERE user_id = ? AND table_id = ?
ORDER BY id DESC
   LIMIT 1
");
$theadStmt->bind_param('ii', $uid, $table_id);
$theadStmt->execute();
$thead = $theadStmt->get_result()->fetch_assoc();
$theadStmt->close();

/* ---------------------------
   Title (table name)
----------------------------*/
$titleStmt = $conn->prepare("
  SELECT table_title
    FROM applicants_table
   WHERE user_id = ? AND table_id = ?
   LIMIT 1
");
$titleStmt->bind_param('ii', $uid, $table_id);
$titleStmt->execute();
$titleRes = $titleStmt->get_result();
$tableTitleRow = $titleRes->fetch_assoc();
$titleStmt->close();
$tableTitle = $tableTitleRow['table_title'] ?? 'Untitled Applicants Table';
?>
  
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dresses</title>
</head>
<body>
<header id="appHeader"  class="absolute md:mt-13 mt-20 transition-all duration-300 ease-in-out" style="padding-left: 1.25rem; padding-right: 1.25rem;">
  <section class="flex mt-6 justify-between ml-3">
    <!-- Rename action to the title handler and encode the space -->
    <form action="/ItemPilot/categories/Applicants Table/edit.php" method="POST" class="flex gap-2">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input
        type="text"
        name="table_title"
        value="<?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>"
        class="w-full px-4 py-2 text-lg font-bold text-black rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"
        placeholder="Untitled sales table"
      />
    </form>

    <button id="addIcon" type="button"
            class="flex items-center gap-1 bg-blue-500 hover:bg-blue-400 py-[10px] cursor-pointer px-2 rounded-lg text-white">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      <span class="text-sm">New Record</span>
    </button>
  </section>


<main class="md:mt-0 mt-10 overflow-x-auto md:overflow-x-hidden" id="applicantsSection">
  <div class="mx-auto mt-12 mb-2 mr-5 bg-white p-4 md:p-8 lg:p-10 rounded-xl shadow-md border border-gray-100 md:w-full w-[94rem]">

    <div class="mb-3">
      <input type="search" placeholder="Search applicants…" data-rows=".applicant-row" data-count="#countA" data-scope="#applicantsSection" class="rounded-full pl-3 pr-3 border border-gray-200 h-10 w-96"/>
      <span id="countA" class="ml-2 text-xs text-gray-600"></span>
    </div>


    <?php
    // Prefill THEAD form
    $theadFetch = $conn->prepare("
      SELECT id, table_id, name, stage, applying_for, attachment, email_address, phone,
             interview_date, interviewer, interview_score, notes
        FROM applicants_thead
       WHERE user_id = ? AND table_id = ?
       ORDER BY id DESC
       LIMIT 1
    ");
    $theadFetch->bind_param('ii', $uid, $table_id);
    $theadFetch->execute();
    $res = $theadFetch->get_result();
    $headRow = $res && $res->num_rows ? $res->fetch_assoc() : ['id'=>0,'table_id'=>$table_id];
    $theadFetch->close();
    ?>

    <!-- THEAD -->
    <div class="universal-table" id="sales-<?= (int)$table_id ?>" data-table-id="<?= (int)$table_id ?>">
      <form action="/ItemPilot/categories/Applicants Table/edit_thead.php" method="post"
            class="w-full thead-form border-b border-gray-200" data-table-id="<?= (int)$table_id ?>">

        <input type="hidden" name="id" value="<?= (int)($headRow['id'] ?? 0) ?>">
        <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">

        <div class="flex text-xs gap-2 font-semibold text-black uppercase">
          <div class="w-1/12 p-2">
            <input name="name" value="<?= htmlspecialchars($headRow['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Name" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/12 p-2">
            <input name="stage" value="<?= htmlspecialchars($headRow['stage'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Stage" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/12 p-2">
            <input name="applying_for" value="<?= htmlspecialchars($headRow['applying_for'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Applying For" class="w-full bg-transparent whitespace-normal break-words border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/12 p-2">
            <input name="attachment" value="<?= htmlspecialchars($headRow['attachment'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Attachment" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/12 p-2">
            <input name="email_address" value="<?= htmlspecialchars($headRow['email_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Email Address" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/12 p-2">
            <input name="phone" value="<?= htmlspecialchars($headRow['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Phone" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/12 p-2">
            <input name="interview_date" value="<?= htmlspecialchars($headRow['interview_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Interview Date" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/12 p-2">
            <input name="interviewer" value="<?= htmlspecialchars($headRow['interviewer'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Interviewer" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/12 p-2">
            <input name="interview_score" value="<?= htmlspecialchars($headRow['interview_score'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Interview Score" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/12 p-2">
            <input name="notes" value="<?= htmlspecialchars($headRow['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Notes" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
        </div>
      </form>
    </div>

    <!-- TBODY -->
    <div class="md:w-full w-[92rem] divide-y divide-gray-200">
      <?php if ($hasRecord): foreach ($rows as $r): ?>
        <form method="POST" action="/ItemPilot/categories/Applicants Table/edit_tbody.php?id=<?= (int)$r['id'] ?>" enctype="multipart/form-data" class="applicant-row flex items-center border-b gap-2 border-gray-200 hover:bg-gray-50 text-sm">

          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
          <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($r['attachment'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <div class="w-1/12 p-2 text-gray-600" data-col="name">
            <input type="text" name="name" value="<?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/12 p-2 text-gray-600 text-xs font-semibold" data-col="stage">
            <?php
              $statusColors = [
                'No hire'       => 'bg-red-100 text-red-800',
                'Interviewing'  => 'bg-yellow-100 text-yellow-800',
                'Hire'        => 'bg-green-100 text-green-800',
                'Decision needed'        => 'bg-gray-100 text-gray-800',
              ];
              $colorClass = $statusColors[$r['stage'] ?? ''] ?? 'bg-white text-gray-900';
            ?>
            <select name="stage" data-autosave="1" style="appearance:none;" class="w-full px-2 py-1 rounded-xl status--autosave1 <?= $colorClass ?>">
              <option value="No hire"       <?= ($r['stage'] ?? '') === 'No hire' ? 'selected' : '' ?>>No hire</option>
              <option value="Interviewing" <?= ($r['stage'] ?? '') === 'Interviewing' ? 'selected' : '' ?>>Interviewing</option>
              <option value="Hire"        <?= ($r['stage'] ?? '') === 'Hire' ? 'selected' : '' ?>>Hire</option>
              <option value="Decision needed"        <?= ($r['stage'] ?? '') === 'Decision needed' ? 'selected' : '' ?>>Decision needed</option>
            </select>
          </div>

          <div class="w-1/12 p-2 text-gray-600" data-col="applying_for">
            <input type="text" name="applying_for" value="<?= htmlspecialchars($r['applying_for'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/12 p-2 text-gray-600 text-xs font-semibold" data-col="attachment">
            <?php if (!empty($r['attachment'])): ?>
              <img src="/ItemPilot/categories/Applicants Table/uploads/<?= htmlspecialchars($r['attachment'], ENT_QUOTES, 'UTF-8') ?>"
                   class="w-16 h-10 rounded-md" alt="Attachment">
            <?php else: ?>
              <span class="italic text-gray-400 ml-[5px]">📎 None</span>
            <?php endif; ?>
          </div>

          <div class="w-1/12 p-2 text-gray-600" data-col="email_address">
            <input type="text" name="email_address" value="<?= htmlspecialchars($r['email_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/12 p-2 text-gray-600" data-col="phone">
            <input type="text" name="phone" value="<?= htmlspecialchars($r['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/12 p-2 text-gray-600" data-col="interview_date">
            <input type="text" name="interview_date" value="<?= htmlspecialchars($r['interview_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/12 p-2 text-gray-600 whitespace-normal break-words" data-col="interviewer">
            <input type="text" name="interviewer" value="<?= htmlspecialchars($r['interviewer'] ?? '', ENT_QUOTES, 'UTF-8') ?>"  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/12 p-2 text-gray-600 text-xs font-semibold" data-col="interview_score">
            <?php
              $statusColors1 = [
                'Failed'       => 'bg-red-100 text-red-800',
                'Probably no hire'  => 'bg-yellow-100 text-yellow-800',
                'Worth consideration'        => 'bg-blue-100 text-blue-800',
                'Good candidate'        => 'bg-green-100 text-green-800',
                'Hire this person'        => 'bg-gray-100 text-gray-800',
              ];
              $colorClass1 = $statusColors1[$r['interview_score'] ?? ''] ?? 'bg-white text-gray-900';
            ?>
            <select name="interview_score" data-autosave="1" style="appearance:none;" class="w-full px-2 py-1 rounded-xl status--autosave <?= $colorClass1 ?>">
              <option value="Failed"       <?= ($r['interview_score'] ?? '') === 'Failed' ? 'selected' : '' ?>>Failed</option>
              <option value="Probably no hire" <?= ($r['interview_score'] ?? '') === 'Probably no hire' ? 'selected' : '' ?>>Probably no hire</option>
              <option value="Worth consideration"        <?= ($r['interview_score'] ?? '') === 'Worth consideration' ? 'selected' : '' ?>>Worth consideration</option>
              <option value="Good candidate"        <?= ($r['interview_score'] ?? '') === 'Good candidate' ? 'selected' : '' ?>>Good candidate</option>
              <option value="Hire this person"        <?= ($r['interview_score'] ?? '') === 'Hire this person' ? 'selected' : '' ?>>Hire this person</option>
            </select>
          </div>

          <div class="w-1/12 p-2 text-gray-600 whitespace-normal break-words" data-col="notes">
            <input type="text" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/12 flex items-center">
              <a href="/ItemPilot/categories/Applicants Table/delete.php?id=<?= (int)$r['id'] ?>&table_id=<?= (int)$table_id ?>"
                 onclick="return confirm('Are you sure?')"
                 class="inline-block py-1 px-6 text-red-500 hover:bg-red-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none" viewBox="0 0 24 24"
                     stroke-width="1.8" stroke="currentColor"
                     class="w-10 h-10 text-gray-500 hover:text-red-600 transition p-2 rounded">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 3h6m2 4H7l1 12h8l1-12z" />
                </svg>
              </a>
        </div>
        </form>
      <?php endforeach; else: ?>
        <div class="px-4 py-4 text-center text-gray-500 w-full border-b border-gray-300">
          No records found.
        </div>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="pagination applicants my-2 flex justify-start md:justify-center space-x-2">
        <?php if ($page > 1): ?>
          <a href="insert_applicant.php?page=<?= $page-1 ?>&table_id=<?= $salesId ?>"
            class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">
            « Prev
          </a>
        <?php endif; ?>

        <?php for ($i=1; $i<=$totalPages; $i++): ?>
          <a href="insert_applicant.php?page=<?= $i ?>&table_id=<?= $salesId ?>"
            class="px-3 py-1 border rounded transition
                    <?= $i===$page
                      ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                      : 'text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a href="insert_applicant.php?page=<?= $page+1 ?>&table_id=<?= $salesId ?>"
            class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">
            Next »
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</header>

  <div id="addForm" class="min-h-screen flex items-center justify-center p-2 hidden relative mt-13">
   <div class="bg-white w-full max-w-md p-5 rounded-2xl shadow-lg" id="signup">
      <div class="flex justify-between">
        <a href="#" data-close-add>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="6" y1="6" x2="18" y2="18" />
            <line x1="6" y1="18" x2="18" y2="6" />
          </svg>
        </a>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      </div>
      <form action="/ItemPilot/categories/Applicants Table/insert_applicant.php" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">

        <h1 class="w-full px-4 py-2 text-center text-2xl">
          <?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <div class="mt-5">
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['name'] ?? 'Name') ?></label>
          <input type="text" name="name" placeholder="<?= htmlspecialchars($thead['name'] ?? 'Name') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['stage'] ?? 'Stage') ?></label>
          <select name="stage" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="No hire">No hire</option>
            <option value="Interviewing">Interviewing</option>
            <option value="Hire">Hire</option>
            <option value="Decision needed">Decision needed</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['applying_for'] ?? 'Country') ?></label>
          <input type="text" name="applying_for" placeholder="<?= htmlspecialchars($thead['applying_for'] ?? 'Country') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['attachment'] ?? 'Attachment') ?></label>
          <input id="attachment_summary" type="file" name="attachment" accept="image/*" capture="environment" class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['email_address'] ?? 'Email Address') ?></label>
          <input type="text" name="email_address" placeholder="<?= htmlspecialchars($thead['email_address'] ?? 'Email Address') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['phone'] ?? 'Phone') ?></label>
          <input type="text" name="phone" placeholder="<?= htmlspecialchars($thead['phone'] ?? 'Phone') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['interview_date'] ?? 'Interview Date') ?></label>
          <input type="text" name="interview_date" placeholder="<?= htmlspecialchars($thead['interview_date'] ?? 'Interview Date') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['interview_score'] ?? 'Interview Score') ?></label>
          <select name="interview_score" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="Failed">Failed</option>
            <option value="Probably no hire">Probably no hire</option>
            <option value="Worth consideration">Worth consideration</option>
            <option value="Good candidate">Good candidate</option>
            <option value="Hire this person">Hire this person</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['notes'] ?? 'Notes') ?></label>
          <input type="text" name="notes" placeholder="<?= htmlspecialchars($thead['notes'] ?? 'Notes') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <div>
          <button type="submit" class="w-full py-3 bg-blue-500 hover:bg-blue-400 text-white font-semibold rounded-lg transition">
            Create New Record
          </button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
