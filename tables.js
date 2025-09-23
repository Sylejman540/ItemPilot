(function() {
  const eventRight   = document.getElementById("event-right");
  const homeRight    = document.getElementById("home-right");
  const contactRight = document.getElementById("contact-right");
  const insightRight = document.getElementById("insight-right");
  const blank        = document.getElementById("blank");        // "Create New" button
  const universal    = document.getElementById("universal");    // optional "Open current table" button
  const dropdown     = document.getElementById("dropdown");     // the tables list (UL)
  const sales        = document.getElementById("sales-strategy");  // sales template card
  const strategy     = document.getElementById("strategy");       // optional open strategy button
  const groceries    = document.getElementById("groceries");
  const football     = document.getElementById("football");
  const footballBtn  = document.getElementById("club");
  const applicant    = document.getElementById("applicant-tracker");
  const applicantTracker = document.getElementById("applicant-tracker");

  let currentPage    = parseInt(new URLSearchParams(window.location.search).get("page")) || 1;
  let currentId      = parseInt(new URLSearchParams(window.location.search).get("table_id")) || null;
  let currentSalesId = null;

  // -------- core loaders (Universal) --------
  function loadTable(tableId, page = 1) {
    if (!tableId) return;
    currentId = tableId;
    fetch(`categories/Universal%20Table/insert_universal.php?page=${page}&table_id=${tableId}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  function newTable(page = 1) {
    fetch(`categories/Universal%20Table/insert_universal.php?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  // -------- actions: create/open universal --------
  if (blank && eventRight) {
    blank.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newTable(1);
    });
  }

  if (universal && eventRight) {
    universal.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      const idFromBtn = parseInt(universal.dataset.tableId || "", 10);
      loadTable(!isNaN(idFromBtn) ? idFromBtn : currentId, currentPage || 1);
    });
  }

  // -------- SALES STRATEGY loaders --------
  function loadStrategy(salesId, page = 1) {
    if (!salesId) return;
    currentSalesId = salesId;
    fetch(`categories/Dresses/insert_dresses.php?page=${page}&table_id=${salesId}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  function newStrategy(page = 1) {
    fetch(`categories/Dresses/insert_dresses.php?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  if (sales && eventRight) {
    sales.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newStrategy(1);
    });
  }

  if (strategy && eventRight) {
    strategy.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      const idFromBtn = parseInt(strategy.dataset.tableId || "", 10);
      loadStrategy(!isNaN(idFromBtn) ? idFromBtn : currentSalesId, currentPage || 1);
    });
  }

  const G_PATH = 'categories/Groceries%20Table/insert_groceries.php';

  function loadGroceriesTable(groceryId, page = 1) {
    if (!groceryId) return;
    currentId = groceryId;
    fetch(`${G_PATH}?page=${page}&table_id=${groceryId}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)   homeRight.style.display = "none";
        if (eventRight)  eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  function newGroceriesTable(page = 1) {
    fetch(`${G_PATH}?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)   homeRight.style.display = "none";
        if (eventRight)  eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  if (groceries && eventRight) {
    groceries.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newGroceriesTable(1);
    });
  }

  // 2) Open existing (from sidebar/list links with class="js-groceries-link")
  document.addEventListener("click", e => {
    const link = e.target.closest(".js-groceries-link");
    if (!link || !eventRight) return;

    e.preventDefault();
    document.getElementById("categories")?.classList.add("hidden");

    const idFromBtn = parseInt(link.dataset.tableId || "", 10);
    loadGroceriesTable(!Number.isNaN(idFromBtn) ? idFromBtn : currentId, currentPage || 1);
  });

  // -------- FOOTBALL loaders --------
  function loadFootball(footballId, page = 1) {
    if (!footballId) return;
    currentFootballId = footballId;
    fetch(`categories/Football%20Table/insert_football.php?page=${page}&table_id=${footballId}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight)   eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  function newFootball(page = 1) {
    fetch(`categories/Football%20Table/insert_football.php?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight)   eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  if (football && eventRight) {
    football.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newFootball(1);
    });
  }

  if (footballBtn && eventRight) {
    footballBtn.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      const idFromBtn = parseInt(footballBtn.dataset.tableId || "", 10);
      loadFootball(!isNaN(idFromBtn) ? idFromBtn : currentFootballId, currentPage || 1);
    });
  }

  // -------- APPLICANT TRACKER loaders --------
  function loadApplicant(applicantId, page = 1) {
    if (!applicantId) return;
    currentApplicantId = applicantId;
    fetch(`categories/Applicants%20Table/insert_applicant.php?page=${page}&table_id=${applicantId}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  function newApplicant(page = 1) {
    fetch(`categories/Applicants%20Table/insert_applicant.php?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  if (applicant && eventRight) {
    applicant.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newApplicant(1);
    });
  }

  if (applicantTracker && eventRight) {
    applicantTracker.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      const idFromBtn = parseInt(applicantTracker.dataset.tableId || "", 10);
      loadApplicant(!isNaN(idFromBtn) ? idFromBtn : currentApplicantId, currentPage || 1);
    });
  }

  // -------- Dropdown delegation (detect src) --------
  if (dropdown && eventRight) {
    dropdown.addEventListener("click", e => {
      const link = e.target.closest(".js-table-link, .js-strategy-link, .js-groceries-link, .js-football-link, .js-applicants-link");
      if (!link) return;
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");

      const tableId = parseInt(link.dataset.tableId || "", 10);
      const src     = link.dataset.src;

      if (!isNaN(tableId)) {
        if (src === "dresses_table") {
          loadStrategy(tableId, 1);
        } else if(src === "groceries_table"){
          loadGroceriesTable(tableId, 1);
        } else if(src === "football_table"){
          loadFootball(tableId, 1);
        } else if(src === "applicants_table"){
          loadApplicant(tableId, 1);
        }else {
          loadTable(tableId, 1);
        }
      }
    });
  }

  // -------- template picker --------
  document.querySelectorAll('.template-item').forEach(el => {
    el.addEventListener('click', () => {
      const id   = el.dataset.id;
      const name = el.dataset.name;
      const sel  = document.getElementById('selectedTemplate');
      if (sel) sel.textContent = name;
      window.location.href = `home.php?table_id=${id}&page=${currentPage}`;
    });
  });

  // -------- profile dropdown --------
  const profileDropdown = document.getElementById('profile-dropdown');
  const profileDropdownMenu = document.getElementById('profile-dropdown-menu');
  if (profileDropdown && profileDropdownMenu) {
    profileDropdown.addEventListener('click', (event) => {
      event.stopPropagation();
      profileDropdownMenu.style.display =
        profileDropdownMenu.style.display === "block" ? "none" : "block";
    });
    document.body.addEventListener('click', () => {
      profileDropdownMenu.style.display = "none";
    });
  }

  // -------- success message fadeout --------
  document.addEventListener("DOMContentLoaded", () => {
    const msg = document.getElementById("success-message");
    if (msg && !msg.classList.contains("hidden")) {
      setTimeout(() => {
        msg.style.opacity = "0";
        setTimeout(() => msg.remove(), 500);
      }, 3000);
    }
  });
  
  // const showTemplates = document.getElementById('showTemplates');

  //   showTemplates.addEventListener('click', () =>{
  //   const templates = document.getElementById('templates');

  //   templates.style.display = 'block';
  // })

  // -------- tabs --------
  const homeTab    = document.getElementById("home");
  const contactTab = document.getElementById("contact");
  const eventsTab  = document.getElementById("events");
  const insightTab = document.getElementById("insight");
  const manageTab  = document.getElementById("manageTab");

  function show(el) { if (el) el.style.display = "block"; }
  function hide(el) { if (el) el.style.display = "none"; }

  function resetScroll() {
    window.scrollTo({ top: 0, behavior: "smooth" });
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
    [document.querySelector('#main'), document.querySelector('.main'),
     document.querySelector('.content'), document.getElementById("account"),
     homeRight, contactRight, eventRight].filter(Boolean)
     .forEach(el => el.scrollTop = 0);
  }

  if (homeTab) homeTab.addEventListener('click', (e) => {
    e.preventDefault?.();
    show(homeRight); hide(contactRight); hide(eventRight); hide(insightRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (contactTab) contactTab.addEventListener('click', (e) => {
    e.preventDefault?.();
    show(contactRight); hide(homeRight); hide(eventRight); hide(insightRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (eventsTab) eventsTab.addEventListener("click", (e) => {
    e.preventDefault?.();
    show(eventRight); hide(homeRight); hide(contactRight); hide(insightRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (insightTab) insightTab.addEventListener("click", (e) => {
    e.preventDefault?.();
    show(insightRight); hide(homeRight); hide(eventRight); hide(contactRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (manageTab) manageTab.addEventListener("click", (e) => {
    show(document.getElementById("account")); hide(homeRight); hide(eventRight); hide(contactRight); hide(insightRight);
    requestAnimationFrame(resetScroll);
  })

  // -------- modals --------
  document.querySelectorAll('[data-modal-target]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const tgt = document.getElementById(btn.dataset.modalTarget);
      if (tgt) tgt.classList.remove('hidden');
    });
  });
  document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.fixed');
      if (modal) modal.classList.add('hidden');
    });
  });

  // -------- page-level delegation --------
  document.body.addEventListener('click', e => {
    const pg = e.target.closest('.pagination a');
    if (pg) {
      e.preventDefault();
      const url = new URL(pg.href, window.location.origin);
      const p   = parseInt(url.searchParams.get('page')) || 1;
      if (pg.closest('.strategy-section')) {
        loadStrategy(currentSalesId, p);
      } else if(pg.closest('.grocery')){ 
        loadGroceriesTable(currentId, p);
      } else if(pg.closest('.football')){
        loadFootball(currentFootballId, p);
      } else if(pg.closest('.applicants')){
        loadApplicant(currentApplicantId, p);
      } else {
        loadTable(currentId, p);
      }
      return;
    }
    const addBtn = e.target.closest('#addIcon');
    if (addBtn) {
      e.preventDefault();
      document.getElementById('addForm')?.classList.remove('hidden');
    }
    const closeAdd = e.target.closest('[data-close-add]');
    if (closeAdd) document.getElementById('addForm')?.classList.add('hidden');
  });

  // Enter submits form (leave everything else as-is)
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const form = e.target.closest('form');
    if (!form) return;
    if (e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return; // optional
    e.preventDefault();
    form.requestSubmit(); // or form.submit() if you don't want validation
  });

  document.addEventListener('change', (e) => {
    const s = e.target.closest('select[data-autosave="1"]');
    if (s) s.form?.requestSubmit();
  });


  // -------- autosave status --------
  document.addEventListener('change', (e) => {
    if (e.target.matches('select.status--autosave') &&
        ['To Do', 'In Progress', 'Done'].includes(e.target.value)) {
      e.target.form?.submit();
    }
  });
  
  // -------- dropdown open/close --------
$(function () {
  const $arrowBtn = $('#tablesItem');
  const $dd       = $('#dropdown');
  const $chev     = $('#tablesItem .chev');
  const KEY       = 'tablesDropdownState:v1';

  function open(skipAnim = false) {
    if (skipAnim) {
      $dd.stop(true,true).show().removeClass('hidden');
    } else {
      if ($dd.is(':visible')) return;
      $dd.stop(true,true).slideDown(160, () => $dd.removeClass('hidden'));
    }
    $chev.addClass('rotate-90');
    $arrowBtn.attr('aria-expanded','true');
    localStorage.setItem(KEY, 'open');
  }

  function close(skipAnim = false) {
    if (skipAnim) {
      $dd.stop(true,true).hide().addClass('hidden');
    } else {
      if (!$dd.is(':visible')) return;
      $dd.stop(true,true).slideUp(160, () => $dd.addClass('hidden'));
    }
    $chev.removeClass('rotate-90');
    $arrowBtn.attr('aria-expanded','false');
    localStorage.setItem(KEY, 'closed');
  }

  $arrowBtn.on('click', e => {
    e.preventDefault(); e.stopPropagation();
    $dd.is(':visible') ? close() : open();
  });

  $(document).on('click', e => {
    if (!$(e.target).closest('#dropdown,#tablesItem').length) close();
  });

  $(document).on('keydown', e => { if (e.key === 'Escape') close(); });

  // Restore last state without animation
  const saved = localStorage.getItem(KEY);
  if (saved === 'open') open(true);
  else close(true); // default closed
});


  // -------- autoload --------
  const params = new URLSearchParams(window.location.search);
  const shouldAutoload = params.get("autoload");
  const tableIdFromUrl = parseInt(params.get("table_id")) || null;
  const tableType      = params.get("type");

  if (shouldAutoload && tableIdFromUrl) {
    if (tableType === "dresses") {
      loadStrategy(tableIdFromUrl, currentPage);
    } else if (tableType === "groceries") {
      loadGroceriesTable(tableIdFromUrl, currentPage); 
    }else if(tableType === "football"){
      loadFootball(tableIdFromUrl, currentPage);
    }else if(tableType === "applicant"){
      loadApplicant(tableIdFromUrl, currentPage); 
    } else {
      loadTable(tableIdFromUrl, currentPage); // universal
    }
  }
})();