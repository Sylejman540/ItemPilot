(function ($) {
  if (!window.jQuery) return;

  // ---- helpers ----
  const debounce = (fn, ms = 120) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  const cellText = ($cell) => {
    const $c = $cell.find('input,textarea,select');
    if ($c.length) {
      if ($c.is('select')) {
        const opt = $c[0].options[$c[0].selectedIndex];
        return String(opt ? opt.text : $c.val() ?? '');
      }
      return String($c.val() ?? '');
    }
    return String(($cell.text() ?? '').trim());
  };
  const highlightCell = ($cell, q) => {
    const $c = $cell.find('input,textarea,select');
    const t = cellText($cell).toLowerCase();
    const hit = q && t.includes(q.toLowerCase());
    $cell.removeClass('cell-hit'); $c.removeClass('ctrl-hit');
    if (q && hit) ($c.length ? $c.addClass('ctrl-hit') : $cell.addClass('cell-hit'));
  };

  // ---- row id helpers (for dedupe across pages) ----
  function rowId($row) { const v = $row.find('input[name=id]').val(); return v ? String(v) : null; }
  function collectExistingIds($container, rowsSel) {
    const ids = new Set();
    $container.find(rowsSel).each(function () { const id = rowId($(this)); if (id) ids.add(id); });
    return ids;
  }

  // ---- load & merge rows from pagination ----
  async function loadAllPages($scope, rowsSel, $container) {
    if ($scope.data('allPagesLoaded')) return;
    const $pag = $scope.find('.pagination a[href]');
    if (!$pag.length) { $scope.data('allPagesLoaded', true); return; }

    const urls = [...new Set($pag.map((_, a) => a.href).get())];
    const existing = collectExistingIds($container, rowsSel);

    for (const url of urls) {
      try {
        const html = await fetch(url, { credentials: 'same-origin' }).then(r => r.text());
        const doc  = new DOMParser().parseFromString(html, 'text/html');
        doc.querySelectorAll(rowsSel).forEach(node => {
          const $row = $(node);
          const id = rowId($row);
          if (!id || existing.has(id)) return;
          existing.add(id);
          $container.append($row);
        });
      } catch (e) { console.error('Failed to fetch page', url, e); }
    }
    $scope.data('allPagesLoaded', true);
  }

  // ---- filter ----
  function runFilter($input) {
    const rowsSel  = $input.data('rows');      // e.g. ".sales-row" or ".universal-row"
    const countSel = $input.data('count');     // optional counter selector
    const scopeSel = $input.data('scope');     // optional scope root
    const $scope   = scopeSel ? $(scopeSel) : $(document);
    const $rows    = $scope.find(rowsSel);
    if (!$rows.length) { if (countSel) $(countSel).text(''); return; }

    const q = String($input.val() ?? '').trim();
    let visible = 0;

    // RELIABLE container: parent of first row (don’t hardcode class combos)
    const $container = $rows.first().parent();

    const maybeLoad = q ? loadAllPages($scope, rowsSel, $container) : Promise.resolve();

    Promise.resolve(maybeLoad).then(() => {
      const $allRows = $scope.find(rowsSel);
      const ql = q.toLowerCase();

      $allRows.each(function () {
        const $r = $(this);
        const $cells = $r.find('[data-col]');
        const hay = $cells.map((_, c) => cellText($(c))).get().join(' ').toLowerCase();
        const match = q ? hay.includes(ql) : true;

        // Hide/show WHOLE row, robust even without Tailwind
        if (match) { $r.removeClass('hidden').show(); visible++; }
        else       { $r.addClass('hidden').hide(); }

        // optional highlight
        $cells.each(function () { highlightCell($(this), match ? q : ''); });
      });

      if (countSel) $(countSel).text(q ? (visible ? `${visible} match${visible === 1 ? '' : 'es'}` : 'No matches') : '');
      $scope.find('.pagination').toggleClass('hidden', !!q);
    });
  }

  const runFilterDebounced = debounce(runFilter, 120);

  // public API
  window.TableSearch = {
    run($input) { runFilter($input); },
    refreshAll() { $('[data-rows]').each(function () { runFilter($(this)); }); }
  };

  // events
  $(document).on('input', '[data-rows]', function () { runFilterDebounced($(this)); });
  $(document).on('keydown', '[data-rows]', function (e) {
    if (e.key === 'Escape') { $(this).val(''); runFilter($(this)); e.stopPropagation(); }
  });

  // initial run + observe DOM changes
  $(function () { window.TableSearch.refreshAll(); });
  const obs = new MutationObserver(debounce(() => window.TableSearch.refreshAll(), 150));
  obs.observe(document.documentElement || document.body, { childList: true, subtree: true });

})(window.jQuery);


(function () {
  // parse numbers from "$20", "20$", "20.50", "20,50", etc.
  function toNumber(v){
    const s = String(v || '').replace(/\s/g,'');
    const m = s.match(/-?\d+(?:[.,]\d+)?/);
    return m ? parseFloat(m[0].replace(',', '.')) : 0;
  }
  // pick currency + whether it’s prefix ($20) or suffix (20$). Default: "$" suffix.
  function currencyInfo(sample){
    const s = String(sample || '').trim();
    const lead = s.match(/^([$€£])/);
    const tail = s.match(/([$€£])$/);
    if (lead) return { sym: lead[1], pos: 'prefix' };
    if (tail) return { sym: tail[1], pos: 'suffix' };
    return { sym: '$', pos: 'suffix' };
  }
  function fmt(n, a='', b=''){
    const r = Math.round(n * 100) / 100;
    const str = Number.isInteger(r) ? r.toFixed(0) : r.toFixed(2);
    const {sym,pos} = currencyInfo(a) || currencyInfo(b);
    return pos === 'prefix' ? (sym + str) : (str.replace(/\.00$/,'') + sym);
  }

  function wireAddModal(form){
    const pri = form.querySelector('#priorityAdd') || form.querySelector('input[name="priority"]');
    const own = form.querySelector('#ownerAdd')    || form.querySelector('input[name="owner"]');
    const fit = form.querySelector('#deadlineAdd'); // hidden input
    if (!pri || !own || !fit) return;

    const recalc = () => {
      const p = toNumber(pri.value);
      const o = toNumber(own.value);
      fit.value = (p || o) ? fmt(p - o, pri.value, own.value) : '';
    };
    ['input','change'].forEach(ev => {
      pri.addEventListener(ev, recalc);
      own.addEventListener(ev, recalc);
    });
    form.addEventListener('submit', recalc);
    recalc();
  }

  function wireRow(row){
    const pri = row.querySelector('input[name="priority"]');
    const own = row.querySelector('input[name="owner"]');
    const fit = row.querySelector('input[name="deadline"]'); // read-only
    if (!pri || !own || !fit) return;

    const recalc = () => {
      const p = toNumber(pri.value);
      const o = toNumber(own.value);
      fit.value = (p || o) ? fmt(p - o, pri.value, own.value) : '';
    };
    ['input','change'].forEach(ev => {
      pri.addEventListener(ev, recalc);
      own.addEventListener(ev, recalc);
    });
    // ensure correct on initial render
    recalc();
  }

  function wire(root){
    const addForm = root.querySelector('#addSalesForm');
    if (addForm) wireAddModal(addForm);
    root.querySelectorAll('.sales-row').forEach(wireRow);
  }

  // Run now
  wire(document);

  // Expose for AJAX-injected content:
  window.initProfitCalc = function(root=document){ wire(root); };

  // If you already use initSalesEnhancements(root), call us from there:
  if (typeof window.initSalesEnhancements === 'function') {
    const oldEnh = window.initSalesEnhancements;
    window.initSalesEnhancements = function(root=document){
      oldEnh(root);
      wire(root);
    };
  }
})();

(function () {
  const form = document.querySelector('#addForm');
  if (!form) return;

  const price   = form.querySelector('[name="priority"]'); // Price
  const cost    = form.querySelector('[name="owner"]');    // Material Cost
  const profit  = form.querySelector('[name="deadline"]'); // Profit

  function parseMoney(s) {
    s = String(s || '');
    const m = s.match(/-?\d+(?:[.,]\d+)?/);
    return m ? parseFloat(m[0].replace(',', '.')) : 0;
  }
  function pickSymbol(a, b) {
    for (const v of [a, b]) {
      const m = String(v || '').match(/([$€£])/);
      if (m) return m[1];
    }
    return ''; // no symbol
  }
  function formatMoney(n, a, b) {
    const sym = pickSymbol(a, b);
    return (sym ? sym : '') + n.toFixed(2);
  }

  function updateProfit() {
    const a = price.value, b = cost.value;
    if (!a && !b) { profit.value = ''; return; }
    const p = parseMoney(a) - parseMoney(b);
    profit.value = formatMoney(p, a, b);
  }

  ['input', 'change', 'blur', 'paste'].forEach(ev => {
    price.addEventListener(ev, updateProfit);
    cost.addEventListener(ev, updateProfit);
  });

  // initialize once
  updateProfit();
})();

document.body.addEventListener('click', e => {
  // --- Add Column popover (works for dynamically injected HTML) ---
  const addBtnCol = e.target.closest('#addColumnBtn');
  const pop       = document.getElementById('addColumnPop');

  if (addBtnCol && pop) {
    e.preventDefault();
    e.stopPropagation();
    pop.classList.toggle('hidden');     // Tailwind-friendly show/hide
    console.log('clicked');
    return;                             // avoid other handlers interfering
  }
  // click outside closes
  if (pop && !e.target.closest('#addColumnPop, #addColumnBtn')) {
    pop.classList.add('hidden');
  }

  // --- keep your existing code below ---
  const pg = e.target.closest('.pagination a');
  if (pg) { /* ... */ return; }
  const addBtn = e.target.closest('#addIcon');
  if (addBtn) { /* ... */ }
  const closeAdd = e.target.closest('[data-close-add]');
  if (closeAdd) { pop.classList.add('hidden'); }
});   

document.body.addEventListener('click', e => {
  const addBtnCol = e.target.closest('#addDeleteBtn');
  const pop       = document.getElementById('addDeletePop');

  if (addBtnCol && pop) {
    e.preventDefault();
    e.stopPropagation();
    pop.classList.toggle('hidden');  
    actionMenuList.classList.toggle('hidden');
    console.log('clicked');
    return;              
  }
  if (pop && !e.target.closest('#addDeletePop, #addDeleteBtn')) {
    pop.classList.add('hidden');
    actionMenuList.classList.add('hidden');
  }

  const pg = e.target.closest('.pagination a');
  if (pg) {  return; }
  const addBtn = e.target.closest('#addIcon');
  if (addBtn) { }
  const closeAdd = e.target.closest('[data-close-add]');
  if (closeAdd) { pop.classList.add('hidden'); }
}); 

document.addEventListener('DOMContentLoaded', () => {
  // if the modal contains no field rows, hide the trigger
  const hasFields = document.querySelector('#addDeletePop input[name^="extra_field_"]') !== null;
  const btn = document.getElementById('addDeleteBtn');
  if (btn && !hasFields) btn.classList.add('hidden');
});

// Action menu (uses different IDs than your addColumn/addDelete popovers)
document.body.addEventListener('click', e => {
  const trigger = e.target.closest('#actionMenuBtn');
  const menu    = document.getElementById('actionMenuList');

  if (trigger && menu) {
    e.preventDefault();
    e.stopPropagation();
    menu.classList.toggle('hidden');   // Tailwind-friendly show/hide
    return;
  }

  // click outside closes
  if (menu && !e.target.closest('#actionMenuList, #actionMenuBtn')) {
    menu.classList.add('hidden');
  }

  // optional close button inside the menu
  const close = e.target.closest('[data-close-action-menu]');
  if (close && menu) menu.classList.add('hidden');
});

// Global row selector (used in multiple modules)


(() => {
  // Prevent double-binding if this file gets included twice
  if (window.__IP_AJAX_BOUND__) return;
  window.__IP_AJAX_BOUND__ = true;

  // ---------- Selectors ----------
  const AJAX_FORMS =
    'form.thead-form, form.applicant-row, form.football-row, form.sales-row, form.universal-row, form.groceries-row, form.new-record-form';

  // Only the inline-row forms (used for autosave); keep in sync with AJAX_FORMS
  const ROW_FORMS =
    'form.applicant-row, form.football-row, form.sales-row, form.universal-row, form.groceries-row';

    window.ROW_SEL = '.applicant-row, .football-row, .sales-row, .universal-row, .groceries-row';
  // ---------- Helpers ----------
  const norm = (s) => (s || '').toString().trim().toLowerCase();

  function statusClasses(s) {
    const t = norm(s);
    if (t === 'done')
      return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 ring-1 ring-green-200';
    if (t === 'in progress' || t === 'in-progress')
      return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 ring-1 ring-yellow-200';
    return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 ring-1 ring-gray-200';
  }

  function applyStatusColor(selectEl, status) {
    if (!selectEl) return;
    const t = norm(status);
    selectEl.classList.remove(
      'bg-red-100','text-red-800',
      'bg-yellow-100','text-yellow-800',
      'bg-green-100','text-green-800',
      'bg-white','text-gray-900'
    );
    if (t === 'done') {
      selectEl.classList.add('bg-green-100','text-green-800');
    } else if (t === 'in progress' || t === 'in-progress') {
      selectEl.classList.add('bg-yellow-100','text-yellow-800');
    } else {
      // treat anything else as "To Do"
      selectEl.classList.add('bg-red-100','text-red-800');
    }
  }

// ---- FOOTBALL POSITIONS ----
function positionClasses(pos) {
  const t = norm(pos);
  switch (t) {
    case 'goalkeeper':
      return 'bg-green-100 text-green-800';
    case 'sweeper':
      return 'bg-yellow-100 text-yellow-800';
    case 'fullback':
      return 'bg-blue-100 text-blue-800';
    case 'midfielder':
      return 'bg-cyan-100 text-cyan-800';
    case 'forward striker':
      return 'bg-rose-100 text-rose-800';
    default:
      return 'bg-white text-gray-900';
  }
}

function applyPositionColor(selectEl, pos) {
  if (!selectEl) return;
  // reset to base select styling
  selectEl.className = 'w-full px-2 py-1 rounded-xl';
  // add position color classes
  selectEl.classList.add(...positionClasses(pos).split(' '));
}


  // ---- GROCERIES DEPARTMENTS ----
function departmentClasses(dep) {
  const t = norm(dep);
  switch (t) {
    case 'produce':
      return 'bg-green-100 text-green-800';
    case 'bakery':
      return 'bg-yellow-100 text-yellow-800';
    case 'dairy':
      return 'bg-blue-100 text-blue-800';
    case 'frozen':
      return 'bg-cyan-100 text-cyan-800';
    case 'meat/seafood':
    case 'meat-seafood':
      return 'bg-rose-100 text-rose-800';
    case 'dry goods':
    case 'dry-goods':
      return 'bg-amber-100 text-amber-800';
    case 'household':
      return 'bg-gray-100 text-gray-800';
    default:
      return 'bg-white text-gray-900';
  }
}

function applyDepartmentColor(selectEl, dep) {
  if (!selectEl) return;
  // reset base classes
  selectEl.className = 'w-full px-2 py-1 rounded-xl';
  // add department color classes
  selectEl.classList.add(...departmentClasses(dep).split(' '));
}

function stageClasses(stage) {
  const t = norm(stage);
  if (t === 'applied') return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700';
  if (t === 'interviewing') return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700';
  if (t === 'hire') return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700';
  if (t === 'no hire' || t === 'rejected') return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700';
  if (t === 'decision needed') return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700';
  return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white text-gray-900';
}


function applyStageColor(selectEl, stage) {
  if (!selectEl) return;
  // keep base styles intact
  selectEl.classList.remove(
    'bg-gray-100','text-gray-700','ring-gray-200',
    'bg-blue-100','text-blue-700','ring-blue-200',
    'bg-green-100','text-green-700','ring-green-200',
    'bg-red-100','text-red-700','ring-red-200'
  );
  selectEl.classList.add(...stageClasses(stage).split(' ')
    .filter(c => c.startsWith('bg-') || c.startsWith('text-') || c.startsWith('ring-')));
}


 function scoreClasses(score) {
  const t = norm(score);
  if (t === 'failed') 
    return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700';
  if (t === 'probably no hire') 
    return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700';
  if (t === 'worth consideration') 
    return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700';
  if (t === 'good candidate') 
    return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700';
  if (t === 'hire this person') 
    return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700';
  
  return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700';
}

function applyScoreColor(selectEl, score) {
  if (!selectEl) return;
  // remove old colors
  selectEl.classList.remove(
    'bg-red-100','text-red-700',
    'bg-orange-100','text-orange-700',
    'bg-yellow-100','text-yellow-700',
    'bg-blue-100','text-blue-700',
    'bg-green-100','text-green-700',
    'bg-gray-100','text-gray-700'
  );
  // add new
  selectEl.classList.add(...scoreClasses(score).split(' ')
    .filter(c => c.startsWith('bg-') || c.startsWith('text-') || c.startsWith('ring-')));
}


  function findRowsWrap(tableId) {
    let wrap = document.querySelector(`#rows-${tableId}`);
    if (wrap) return wrap;
    const head = document.querySelector(`#ut-${tableId}`);
    if (head && head.nextElementSibling?.classList?.contains('divide-y')) {
      return head.nextElementSibling;
    }
    return document.querySelector('.w-full.divide-y, .divide-y');
  }

  function hideEmptyState(wrap) {
    wrap?.querySelectorAll('[data-empty], .empty-state, .text-center.text-gray-500')
        .forEach(el => el.remove());
  }

  function extractTableId(form, fallback) {
    const fromInput = form.querySelector('[name="table_id"]')?.value;
    return Number(fromInput || fallback || 0) || 0;
  }

  function insertOrReplaceRow(form, html, tableId) {
    const currentRow = form.closest(ROW_SEL);

    if (currentRow) {
      // EDIT: replace in place
      currentRow.insertAdjacentHTML('beforebegin', html);
      const inserted = currentRow.previousElementSibling;
      currentRow.remove();
      const sel = inserted?.querySelector('select[name="status"]');
      if (sel) applyStatusColor(sel, sel.value);
      return;
    }

    // CREATE: prepend (assume DESC order)
    const wrap = findRowsWrap(tableId);
    if (!wrap) return;
    if (!wrap.querySelector(ROW_SEL)) {}
    hideEmptyState(wrap);
    wrap.insertAdjacentHTML('beforeend', html);
    const inserted = wrap.lastElementChild;
    const sel = inserted?.querySelector('select[name="status"]');
    if (sel) applyStatusColor(sel, sel.value);
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...options,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        ...(options.headers || {}),
      },
    });

    // If server redirected (likely PHP didn't detect AJAX)
    if (res.redirected || res.type === 'opaqueredirect') {
      throw new Error('Server redirected (AJAX not detected).');
    }

    const text = await res.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch {
      // Not JSON → likely an HTML error / login page etc.
      throw new Error('Unexpected non-JSON response from server.');
    }

    if (!res.ok || (data && data.ok === false)) {
      const msg = (data && (data.error || data.message)) || `Server error (${res.status})`;
      throw new Error(msg);
    }
    return data ?? { ok: res.ok };
  }

  // ---------- Toast ----------
  let toastEl;
  function toast(msg, bad) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.style.cssText =
        'position:fixed;bottom:16px;right:16px;padding:10px 14px;border-radius:10px;color:#fff;font-size:12px;z-index:9999;' +
        'box-shadow:0 4px 14px rgba(0,0,0,.2);transition:opacity .2s;opacity:0';
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.style.background = bad ? '#dc2626' : '#16a34a';
    toastEl.style.opacity = '1';
    setTimeout(() => { toastEl.style.opacity = '0'; }, 1400);
  }

  // ---------- SUBMIT (create + edit) ----------
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form.matches(AJAX_FORMS)) return;

    e.preventDefault();

    // Busy guard
    if (form.dataset.busy === '1') return;
    form.dataset.busy = '1';

    const btn = form.querySelector('[type="submit"]');
    const btnText = btn?.textContent;
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

    try {
      const url = form.getAttribute('action') || window.location.href;
      const method = (form.getAttribute('method') || 'POST').toUpperCase();
      const body = new FormData(form);

      const data = await fetchJSON(url, { method, body });

      // Prefer `row_html`/`table_id` from server; fall back to DOM
      const rowHtml = data.row_html || data.rowHtml || '';
      const tId = data.table_id ?? data.tableId ?? extractTableId(form, 0);

if (rowHtml) {
  insertOrReplaceRow(form, rowHtml, tId);
} else {
  // Fallback: minimally update existing row in place
  const row = form.closest(ROW_SEL);
  if (row) {
    // ---------- STATUS ----------
    const newStatus =
      data.status ??
      form.querySelector('[name="status"]')?.value ??
      row.querySelector('[name="status"]')?.value;
    const badge = row.querySelector('.status-badge');
    if (badge && newStatus) {
      badge.textContent = newStatus;
      badge.className = 'status-badge ' + statusClasses(newStatus);
    }
    const statusSel = row.querySelector('select[name="status"]');
    if (statusSel) applyStatusColor(statusSel, newStatus);

    // ---------- POSITION (football) ----------
    const newPos =
      data.position ??
      form.querySelector('[name="position"]')?.value ??
      row.querySelector('[name="position"]')?.value;
    const posSel = row.querySelector('select[name="position"]');
    if (posSel) applyPositionColor(posSel, newPos);

    // ---------- DEPARTMENT (groceries) ----------
    const newDep =
      data.department ??
      form.querySelector('[name="department"]')?.value ??
      row.querySelector('[name="department"]')?.value;
    const depSel = row.querySelector('select[name="department"]');
    if (depSel) applyDepartmentColor(depSel, newDep);

    // ---------- STAGE (applicants) ----------
    const newStage =
      data.stage ??
      form.querySelector('[name="stage"]')?.value ??
      row.querySelector('[name="stage"]')?.value;
    const stageSel = row.querySelector('select[name="stage"]');
    if (stageSel) applyStageColor(stageSel, newStage);

    // ---------- INTERVIEW SCORE (applicants) ----------
    const newScore =
      data.interview_score ??
      form.querySelector('[name="interview_score"]')?.value ??
      row.querySelector('[name="interview_score"]')?.value;
    const scoreSel = row.querySelector('select[name="interview_score"]');
    if (scoreSel) applyScoreColor(scoreSel, newScore);

    // ---------- ATTACHMENT ----------
    if (data.attachment_url) {
      const link = row.querySelector('[data-attachment]');
      if (link) {
        link.href = data.attachment_url;
        link.classList.remove('hidden');
      }
    }
  }
}


      // If it was the modal "new-record" form, reset and close modal
      if (form.classList.contains('new-record-form')) {
        form.reset();
        document.querySelector('#addForm [data-close-add]')?.click?.();
      }

      toast('Saved');
    } catch (err) {
      console.error(err);
      toast(err.message || 'Save failed', true);
    } finally {
      form.dataset.busy = '0';
      if (btn) { btn.disabled = false; btn.textContent = btnText; }
    }
  });

  // ---------- AUTOSAVE (rows only) ----------
  // Triggers when a field with data-autosave or any checkbox changes.
  document.addEventListener('change', (e) => {
    const t = e.target;
    const form = t.closest(ROW_FORMS);
    if (!form) return;

    if (t.name === 'status') {
      applyStatusColor(t, t.value); // instant recolor
    }

    if (t.hasAttribute('data-autosave') || t.type === 'checkbox') {
      // Prefer requestSubmit; fall back to dispatching a submit event (NOT form.submit, which bypasses listeners)
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        const ev = new Event('submit', { bubbles: true, cancelable: true });
        form.dispatchEvent(ev);
      }
    }
  });

  // ---------- DELETE ----------
  document.addEventListener('click', async (e) => {
    const a = e.target.closest('a[href*="delete_"], a[href*="/delete.php"], button[data-delete-url]');
    if (!a) return;

    e.preventDefault();
    if (!confirm('Delete this item?')) return;

    const url    = a.tagName === 'A' ? a.href : a.getAttribute('data-delete-url');
    const method = (a.dataset.method || 'POST').toUpperCase();

    try {
      const data = await fetchJSON(url, { method });
      const row  = a.closest(ROW_SEL);
      const wrap = row?.parentElement;
      row?.remove();

      if (wrap && !wrap.querySelector(ROW_SEL)) {
        const empty = document.createElement('div');
        empty.className = 'empty-state px-4 py-4 text-center text-gray-500 w-full border-b border-gray-300';
        empty.setAttribute('data-empty', '1');
        empty.textContent = 'No records found.';
        wrap.appendChild(empty);
      }

      toast('Deleted');
    } catch (err) {
      console.error(err);
      toast(err.message || 'Delete failed', true);
    }
  });

  // ---------- Modal open/close ----------
  const addBtn  = document.getElementById('addIcon');
  const addForm = document.getElementById('addForm');
  addBtn?.addEventListener('click', () => addForm?.classList.remove('hidden'));
  addForm?.querySelectorAll('[data-close-add]')?.forEach(el =>
    el.addEventListener('click', (e) => {
      e.preventDefault();
      addForm.classList.add('hidden');
    })
  );
})();

// =============== GLOBAL SIDEBAR SYNC =================

// 0) Guard: requires jQuery
if (!window.jQuery) { console.error('Sidebar script: jQuery not found'); }

// Keep track of the currently open table (for re-highlighting)
window.currentTable = { id: null, src: null };

// Expose a small API you can call from anywhere if needed:
//   window.notifyTablesUpdated();
window.notifyTablesUpdated = function notifyTablesUpdated() { refreshSidebar(); };

// 1) Reload ONLY the #dropdown list from the current page (no full reload)
function refreshSidebar() {
  var base = window.location.href.split('#')[0];
  // Pull just the children of #dropdown and swap them in
  $('#dropdown').load(base + ' #dropdown > *', function (_res, status, xhr) {
    if (status !== 'success') {
      console.error('Sidebar refresh failed:', xhr && (xhr.status + ' ' + xhr.statusText));
      return;
    }
    reapplyActive();
  });
}

// 2) Reapply highlight after refresh (uses window.currentTable)
function reapplyActive() {
  if (!window.currentTable || !window.currentTable.id) return;
  var sel = '#dropdown a[data-table-id="' + window.currentTable.id + '"][data-src="' + window.currentTable.src + '"]';
  var $a  = $(sel);
  $('#dropdown .navitem').removeClass('text-white').addClass('text-[#A7B6CC]');
  if ($a.length) $a.closest('li').removeClass('text-[#A7B6CC]').addClass('text-white');
}

// 3) Open table via AJAX (adjust to your loader if different)
$(document).off('click.sidebarOpen', '#dropdown a[data-table-id]')
.on('click.sidebarOpen', '#dropdown a[data-table-id]', function (e) {
  e.preventDefault();
  var $a  = $(this);
  var id  = parseInt($a.data('table-id'), 10);
  var src = String($a.data('src') || '');

  window.currentTable = { id: id, src: src };
  // highlight immediately for snappy UX
  $('#dropdown .navitem').removeClass('text-white').addClass('text-[#A7B6CC]');
  $a.closest('li').removeClass('text-[#A7B6CC]').addClass('text-white');

  // If you already have a loader, call it here. Otherwise emit an event.
  // Example: loadTableIntoMain(src, id);
  $(document).trigger('table:open', { id: id, src: src });
});

// 4) Intercept DELETE links and do them via AJAX, then refresh
$(document).off('click.sidebarDelete', '#dropdown a[href*="delete_table.php"]')
.on('click.sidebarDelete', '#dropdown a[href*="delete_table.php"]', function (e) {
  e.preventDefault();
  var url = this.href;


  // If your app needs a CSRF token, add it here (example):
  // var token = $('meta[name="csrf-token"]').attr('content');

  $.ajax({
    url: url,
    method: 'POST',
    data: { _method: 'DELETE' } // or include token: token
  })
  .done(function () {
    // Clear selection if we deleted the current one
    if (window.currentTable.id && url.indexOf('table_id=' + window.currentTable.id) !== -1) {
      window.currentTable = { id: null, src: null };
    }
    refreshSidebar();
  })
  .fail(function (xhr) {
    alert('Delete failed: ' + (xhr.responseText || 'Unknown error'));
  });
});

// 5) Auto-refresh the sidebar after ANY AJAX call that looks like CRUD
//    (works even if different pages/forms do the requests)
$(document).off('ajaxSuccess.sidebarCrud')
.on('ajaxSuccess.sidebarCrud', function (_evt, _xhr, settings) {
  try {
    var u = settings && settings.url ? settings.url.toLowerCase() : '';
    if (!u) return;

    // List here the endpoints that mutate tables
    // (add/adjust to match your actual paths)
    var isCrud =
      u.indexOf('create_table.php') !== -1 ||
      u.indexOf('rename_table.php') !== -1 ||
      u.indexOf('delete_table.php') !== -1 ||
      u.indexOf('edit_table_title.php') !== -1 ||
      u.indexOf('insert_table.php') !== -1;

    if (isCrud) refreshSidebar();
  } catch (e) {
    console.warn('ajaxSuccess sidebar hook error:', e);
  }
});

// 6) Initial sync (in case the HTML was cached or server updated)
$(function(){ refreshSidebar(); });

$(document)
  // Submit on Enter without reloading
  .off('keydown.renameTitle', '.rename-table-input')
  .on('keydown.renameTitle', '.rename-table-input', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      $(this).closest('form').trigger('submit');
    }
  })

  // Auto-submit on blur (optional—remove if you want explicit Save only)
  .off('blur.renameTitle', '.rename-table-input')
  .on('blur.renameTitle', '.rename-table-input', function(){
    const $form = $(this).closest('form');
    // Avoid double submit if already submitting
    if ($form.data('submitting')) return;
    $form.trigger('submit');
  })

  // AJAX submit
  .off('submit.renameTitle', '.rename-table-form')
  .on('submit.renameTitle', '.rename-table-form', function(e){
    e.preventDefault();
    const $form = $(this);
    const data = $form.serialize();
    const url  = $form.attr('action');

    $form.data('submitting', true);

    $.post(url, data)
      .done(function(res){
        // Optional: if your endpoint returns JSON {table_id, src, title}
        try {
          if (typeof res === 'string') res = JSON.parse(res);
        } catch (_) {}

        // Update any on-page title text instantly (nice UX)
        if (res && res.title) {
          $('.js-current-table-title').text(res.title);
        } else {
          // or read from the input
          $('.js-current-table-title').text($form.find('[name="table_title"]').val());
        }

        // Remember the active table so highlight re-applies after sidebar refresh
        if (window.currentTable && !window.currentTable.id) {
          window.currentTable = {
            id: parseInt($form.find('[name="table_id"]').val(), 10),
            src: String($form.find('[name="src"]').val() || '')
          };
        }

        // Refresh the sidebar (uses the helper you already installed)
        if (typeof window.notifyTablesUpdated === 'function') {
          window.notifyTablesUpdated();
        } else {
          // fallback if helper not present
          $('#dropdown').load(location.href.split('#')[0] + ' #dropdown > *');
        }
      })
      .fail(function(xhr){
        alert('Rename failed: ' + (xhr.responseText || 'Unknown error'));
      })
      .always(function(){
        $form.data('submitting', false);
      });
  });

  // Create "blank" table without full reload (works with your current PHP)
$(document)
  .off('click.createBlank', '#blank')
  .on('click.createBlank', '#blank', function (e) {
    e.preventDefault();

    const $el = $(this);
    if ($el.data('loading')) return;
    $el.data('loading', true).addClass('opacity-50 pointer-events-none');

    // Your resolver only checks $_GET['action'], so use GET:
    const url = window.location.pathname + '?action=create_blank';

    $.get(url)
      .done(function (html) {
        // Parse the returned page HTML and swap in the fresh sidebar
        const $dom = $('<div>').append($.parseHTML(html));
        const $newItems = $dom.find('#dropdown').children();
        if ($newItems.length) $('#dropdown').html($newItems);

        // Find the newest table in the "tables" group (max id)
        let newestId = null;
        $('#dropdown a[data-src="tables"][data-table-id]').each(function () {
          const id = parseInt(this.getAttribute('data-table-id'), 10);
          if (!Number.isNaN(id)) newestId = (newestId === null || id > newestId) ? id : newestId;
        });

        if (newestId == null) {
          console.warn('Could not detect new table_id; sidebar refreshed though.');
          $('.js-current-table-title').text('Untitled');
          return;
        }

        // Remember & highlight
        window.currentTable = { id: newestId, src: 'tables' };
        const $a = $(`#dropdown a[data-src="tables"][data-table-id="${newestId}"]`);
        $('#dropdown .navitem').removeClass('text-white').addClass('text-[#A7B6CC]');
        $a.closest('li').removeClass('text-[#A7B6CC]').addClass('text-white');

        // Dashboard title (your PHP doesn’t set it on create, so set placeholder)
        $('.js-current-table-title').text('Untitled');

        // Load the new table into main (use your existing loader)
        if (typeof loadTableIntoMain === 'function') {
          loadTableIntoMain('tables', newestId);
        } else {
          $(document).trigger('table:open', { id: newestId, src: 'tables' });
        }
      })
      .fail(function (xhr) {
        console.error('Create (GET) failed', { status: xhr.status, text: xhr.statusText, body: xhr.responseText });
        alert('Create failed: ' + (xhr.responseText || (xhr.status + ' ' + xhr.statusText)));
      })
      .always(function () {
        $el.data('loading', false).removeClass('opacity-50 pointer-events-none');
      });
  });
// ======== PART 1: helpers to refresh pieces from current page ========

// Re-pull only the sidebar <li> items
function refreshSidebarOnly(htmlDoc) {
  // if we already have the full HTML, use it; otherwise fetch the page
  if (htmlDoc) {
    const $new = $(htmlDoc).find('#dropdown').children();
    if ($new.length) $('#dropdown').html($new);
    return $.Deferred().resolve().promise();
  }
  return $.get(window.location.href.split('#')[0]).done(function (html) {
    const $new = $('<div>').append($.parseHTML(html)).find('#dropdown').children();
    if ($new.length) $('#dropdown').html($new);
  });
}

// Re-pull only the dashboard grid (the <ul class="grid ..."> inside #event-right)
function refreshDashboardOnly(htmlDoc) {
  if (htmlDoc) {
    const $new = $(htmlDoc).find('#event-right ul.grid').children();
    if ($new.length) $('#event-right ul.grid').html($new);
    return $.Deferred().resolve().promise();
  }
  return $.get(window.location.href.split('#')[0]).done(function (html) {
    const $new = $('<div>').append($.parseHTML(html)).find('#event-right ul.grid').children();
    if ($new.length) $('#event-right ul.grid').html($new);
  });
}

// Convenience: refresh both
function refreshSidebarAndDashboard(htmlDoc) {
  if (htmlDoc) {
    refreshSidebarOnly(htmlDoc);
    refreshDashboardOnly(htmlDoc);
    return;
  }
  $.when(refreshSidebarOnly(), refreshDashboardOnly());
}

// Keep track of current selection for highlight re-apply
let currentTable = window.currentTable || { id: null, src: null };

function reapplyActive() {
  if (!currentTable.id) return;
  $('#dropdown .navitem').removeClass('text-white').addClass('text-[#A7B6CC]');
  const sel = `#dropdown a[data-src="${currentTable.src}"][data-table-id="${currentTable.id}"]`;
  const $a  = $(sel);
  if ($a.length) $a.closest('li').removeClass('text-[#A7B6CC]').addClass('text-white');
}

// ======== PART 2: Create (Blank) — no full reload, uses GET (your PHP reads $_GET) ========

$(document)
  .off('click.createBlank', '#blank')
  .on('click.createBlank', '#blank', function (e) {
    e.preventDefault();

    const $el = $(this);
    if ($el.data('loading')) return;
    $el.data('loading', true).addClass('opacity-50 pointer-events-none');

    const url = window.location.pathname + '?action=create_blank';

    // Use GET because your PHP checks ONLY $_GET['action']
    $.get(url)
      .done(function (html) {
        // Parse returned full HTML once
        const $doc = $('<div>').append($.parseHTML(html));

        // 1) Replace sidebar items
        const $newSidebarItems = $doc.find('#dropdown').children();
        if ($newSidebarItems.length) $('#dropdown').html($newSidebarItems);

        // 2) Replace dashboard grid cards
        const $newGridItems = $doc.find('#event-right ul.grid').children();
        if ($newGridItems.length) $('#event-right ul.grid').html($newGridItems);

        // 3) Detect the newest Universal table id (largest id among src="tables")
        let newestId = null;
        $('#dropdown a[data-src="tables"][data-table-id]').each(function () {
          const id = parseInt(this.getAttribute('data-table-id'), 10);
          if (!Number.isNaN(id)) {
            if (newestId === null || id > newestId) newestId = id;
          }
        });
        if (newestId !== null) {
          currentTable = window.currentTable = { id: newestId, src: 'tables' };
          reapplyActive();
          // 4) Update dashboard title immediately (placeholder, since PHP doesn't set it on create)
          $('.js-current-table-title').text('Untitled');
          // 5) Load the new table into main area (your existing loader/event)
          if (typeof loadTableIntoMain === 'function') {
            loadTableIntoMain('tables', newestId);
          } else {
            $(document).trigger('table:open', { id: newestId, src: 'tables' });
          }
        } else {
          console.warn('Create: could not detect the new table id. Sidebar/grid still refreshed.');
        }
      })
      .fail(function (xhr) {
        console.error('Create GET failed', { status: xhr.status, text: xhr.statusText, body: xhr.responseText });
        alert('Create failed: ' + (xhr.responseText || (xhr.status + ' ' + xhr.statusText)));
      })
      .always(function () {
        $el.data('loading', false).removeClass('opacity-50 pointer-events-none');
      });
  });

// ======== PART 3: Auto-refresh after any CRUD AJAX you already have ========

$(document).off('ajaxSuccess.tablesSync')
.on('ajaxSuccess.tablesSync', function (_evt, _xhr, settings) {
  try {
    const u = (settings && settings.url ? settings.url : '').toLowerCase();
    if (!u) return;
    // Add/adjust endpoints you use:
    const looksLikeCrud =
      u.includes('create_blank') ||
      u.includes('create_table.php') ||
      u.includes('rename_table.php') ||
      u.includes('delete_table.php') ||
      u.includes('edit_table_title.php');

    if (looksLikeCrud) {
      // Pull fresh sidebar + dashboard
      refreshSidebarAndDashboard();
      // Reapply highlight shortly after DOM swap
      setTimeout(reapplyActive, 0);
    }
  } catch (e) {
    console.warn('ajaxSuccess.tablesSync error:', e);
  }
});

// Optional: on page load, ensure dashboard list is fresh (and reapply selection if any)
$(function () {
  // no hard refresh needed; if you want an initial sync uncomment:
  // refreshSidebarAndDashboard();
  reapplyActive();
});

function updateTitleEverywhere(tableId, src, newTitle) {
  if (!tableId || !newTitle) return;

  // 1) Header title (detail view)
  $('.js-current-table-title').text(newTitle);

  // 2) Sidebar item text
  const selSidebar = `#dropdown a[data-table-id="${tableId}"][data-src="${src||'tables'}"]`;
  const $a = $(selSidebar);
  if ($a.length) $a.text(newTitle);

  // 3) Dashboard cards grid (update the <h3> and the filterable data-name)
  //    We match cards by their <a href> because your markup already has that.
  //    Covers: /home.php?autoload=1&table_id=ID  (Universal)
  //            /home.php?autoload=1&type=XXX&table_id=ID (Other categories)
  const hrefNeedle = `table_id=${tableId}`;
  $('#event-right ul.grid a[href*="' + hrefNeedle + '"]').each(function () {
    const $card = $(this).closest('li');
    $card.find('h3').text(newTitle);
    $card.attr('data-name', String(newTitle).toLowerCase());
  });
}

// ---- Helper to read src if your form doesn't include it ----
function inferSrcFallback() {
  // Prefer the current selection if you track it
  if (window.currentTable && window.currentTable.src) return window.currentTable.src;
  // Fallback to 'tables' (Universal)
  return 'tables';
}

// ---- Intercept the rename form submit (no full reload) ----
// Expected form fields: table_id (hidden), table_title (text input)
// Optional: src (hidden) — if not present we infer 'tables'
$(document)
  .off('submit.renameTitle', '.rename-table-form')
  .on('submit.renameTitle', '.rename-table-form', function (e) {
    e.preventDefault();

    const $form  = $(this);
    if ($form.data('submitting')) return;
    $form.data('submitting', true);

    const tableId  = parseInt($form.find('[name="table_id"]').val(), 10);
    const src      = String($form.find('[name="src"]').val() || inferSrcFallback());
    const newTitle = String($form.find('[name="table_title"]').val() || '').trim();
    const url      = $form.attr('action');

    if (!tableId || !newTitle) { $form.data('submitting', false); return; }

    $.post(url, $form.serialize())
      .done(function (res) {
        // Try to parse JSON, but we don't rely on it — we already know the new title.
        try { if (typeof res === 'string') res = JSON.parse(res); } catch (_) {}
        updateTitleEverywhere(tableId, src, newTitle);

        // Keep your sidebar/grid in sync if you also want server-sourced re-render later:
        if (typeof window.notifyTablesUpdated === 'function') window.notifyTablesUpdated();

        // Remember current selection so the sidebar highlight persists
        window.currentTable = { id: tableId, src: src };
      })
      .fail(function (xhr) {
        alert('Rename failed: ' + (xhr.responseText || 'Unknown error'));
      })
      .always(function () {
        $form.data('submitting', false);
      });
  });

// ---- Nice UX: submit on Enter and/or on blur ----
$(document)
  .off('keydown.renameTitle', '.rename-table-input')
  .on('keydown.renameTitle', '.rename-table-input', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); $(this).closest('form').trigger('submit'); }
  })
  .off('blur.renameTitle', '.rename-table-input')
  .on('blur.renameTitle',  '.rename-table-input', function () {
    const $form = $(this).closest('form');
    if (!$form.data('submitting')) $form.trigger('submit');
  });

// ---- Safety net: if rename happens elsewhere via AJAX, catch it globally ----
$(document).off('ajaxSuccess.renameHook')
.on('ajaxSuccess.renameHook', function (_evt, _xhr, settings) {
  const u = (settings && settings.url || '').toLowerCase();
  if (!u) return;
  if (u.includes('rename_table.php') || u.includes('edit_table_title.php')) {
    // Try to extract table_id and title from the sent data string
    const d = settings.data || '';
    const idMatch = d.match(/(?:^|&|\?)table_id=(\d+)/);
    const titleMatch = d.match(/(?:^|&|\?)table_title=([^&]+)/);
    const srcMatch = d.match(/(?:^|&|\?)src=([^&]+)/);

    const tableId  = idMatch ? parseInt(idMatch[1], 10) : (window.currentTable && window.currentTable.id);
    const newTitle = titleMatch ? decodeURIComponent(titleMatch[1].replace(/\+/g, ' ')) : null;
    const src      = srcMatch ? decodeURIComponent(srcMatch[1]) : inferSrcFallback();

    if (tableId && newTitle) updateTitleEverywhere(tableId, src, newTitle);
  }
});

(function () {
  // ---------- helpers ----------
  function bumpCols(selector) {
    document.querySelectorAll(selector).forEach(el => {
      const st = el.getAttribute('style') || '';
      const m = st.match(/--cols:\s*(\d+)/);
      const current = m ? parseInt(m[1], 10) : 0;
      if (current > 0) {
        el.style.setProperty('--cols', current + 1);
      }
    });
  }

  function closeAddFieldModal() {
    document.getElementById('addColumnPop')?.classList.add('hidden');
    document.getElementById('addDeletePop')?.classList.add('hidden');
    document.getElementById('actionMenuList')?.classList.add('hidden');
  }

  // ---------- inject helpers ----------
  function injectIntoThead(tableId, theadHTML) {
    const theadForm = document.querySelector(`form.thead-form[data-table-id="${tableId}"]`);
    if (!theadForm) return;
    const grid = theadForm.querySelector('.app-grid');
    if (!grid) return;

    const actionCell = grid.lastElementChild;
    if (actionCell && actionCell.childElementCount === 0) {
      actionCell.insertAdjacentHTML('beforebegin', theadHTML);
    } else {
      grid.insertAdjacentHTML('beforeend', theadHTML);
    }
  }

  function injectIntoRows(cellHTML) {
    document.querySelectorAll(ROW_SEL).forEach(row => {
      const dynCell = row.querySelector('[data-col="dyn"]');
      if (!dynCell) return;

      const trashCell = row.querySelector('.delete-field-btn')?.closest('td, div');
      if (trashCell && trashCell.parentElement === row) {
        trashCell.insertAdjacentHTML('beforebegin', cellHTML);
      } else {
        dynCell.insertAdjacentHTML('beforeend', cellHTML);
      }
    });
  }

  function injectIntoCreateForm(fid, fname, createHTML) {
    const html = createHTML || `
      <div class="dynamic-field" data-field-id="${fid}">
        <label class="block text-sm font-medium text-gray-700 mb-1">${fname}</label>
        <input type="text" name="extra_field_${fid}" placeholder="${fname}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>
    `;

    document.querySelectorAll('#addForm form.new-record-form').forEach(f => {
      const submit = f.querySelector('button[type="submit"], [type="submit"]');
      if (submit && submit.parentElement) {
        submit.parentElement.insertAdjacentHTML('beforebegin', html);
      } else {
        f.insertAdjacentHTML('beforeend', html);
      }
    });
  }

  function injectIntoDeleteModal(tableId, fid, fname) {
    const deleteList = document.querySelector('#addDeletePop .divide-y');
    if (!deleteList) return;
    const rowHTML = `
      <div class="flex items-center justify-between gap-2 px-3 py-2 hover:bg-gray-50 transition" data-field-row>
        <input type="text" readonly value="${fname}" 
               class="w-full bg-transparent border-none px-1 py-1 text-sm text-gray-900 pointer-events-none focus:outline-none" />
        <button type="button"
                class="delete-field-btn inline-flex items-center justify-center rounded-md p-1.5 text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                data-id="${fid}"
                data-table-id="${tableId}"
                data-delete-url="/ItemPilot/categories/Universal%20Table/delete_fields.php?id=${fid}&table_id=${tableId}">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/>
          </svg>
        </button>
      </div>
    `;
    deleteList.insertAdjacentHTML('beforeend', rowHTML);
  }

  // ---------- ADD FIELD ----------
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form.matches('.add-field-form')) return;

    e.preventDefault();
    if (form.dataset.submitting === '1') return;
    form.dataset.submitting = '1';

    try {
      const fd = new FormData(form);
      const res = await fetch(form.action, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Add field failed');

      // 1) THEAD
      injectIntoThead(data.table_id, data.thead_html);

      // 2) TBODY
      injectIntoRows(data.cell_html_template);

      // 3) CREATE FORM
      injectIntoCreateForm(data.field_id, data.field_name, data.create_input_html);

      // 4) DELETE MODAL
      injectIntoDeleteModal(data.table_id, data.field_id, data.field_name);

      // 5) Bump cols
      bumpCols(ROW_SEL);
      bumpCols(`form.thead-form .app-grid`);

      // 6) Close + reset
      closeAddFieldModal();
      form.reset();

    } catch (err) {
      console.error(err);
      alert(err.message || 'Something went wrong while adding the field.');
    } finally {
      form.dataset.submitting = '0';
    }
  });

  // modal open/close
  const addBtn = document.getElementById('addFieldsBtn');
  const addPop = document.getElementById('addColumnPop');
  if (addBtn && addPop) {
    addBtn.addEventListener('click', () => addPop.classList.remove('hidden'));
  }
  document.querySelectorAll('[data-close-add]').forEach(btn => {
    btn.addEventListener('click', () => {
      closeAddFieldModal();
    });
  });
})();

// ---------- polyfill ----------
if (!window.CSS || !CSS.escape) {
  window.CSS = window.CSS || {};
  CSS.escape = CSS.escape || function (s) {
    return String(s).replace(/([!"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~])/g, '\\$1');
  };
}


// ---------- DELETE FIELD ----------
(() => {
  if (window.__IP_DELETE_FIELDS_BOUND__) return;
  window.__IP_DELETE_FIELDS_BOUND__ = true;

  // --- Helper: remove field inputs/cells from all rows ---
  function removeFieldFromRows(tableId, fieldId, fieldName) {
    let removed = false;

    document.querySelectorAll(`${ROW_SEL} [data-col="dyn"]`).forEach(dynCell => {
      const parentRow = dynCell.closest(ROW_SEL);
      const rowTableId = parentRow?.dataset.tableId;
      if (String(rowTableId) !== String(tableId)) return; // skip other tables

      // Locate input
      let inp = fieldId
        ? dynCell.querySelector(`input[name="extra_field_${fieldId}"]`)
        : dynCell.querySelector(`input[name="dyn[${CSS.escape(fieldName)}]"]`);

      if (!inp && fieldName) {
        inp = [...dynCell.querySelectorAll('input[name^="dyn["], input[name^="extra_field_"]')].find(el =>
          el.name.replace(/^dyn\[|\]$/g, '') === fieldName
        );
      }

      if (inp) {
        inp.remove();
        removed = true;
      }

      // Remove wrapper if empty
      const cell = inp?.closest('[data-field-id], td, div, span') ||
                   dynCell.querySelector(`[data-field-id="${fieldId}"]`) ||
                   dynCell.querySelector(`[data-field-name="${CSS.escape(fieldName)}"]`);
      if (cell && cell.children.length === 0) {
        cell.remove();
        removed = true;
      }
    });

    return removed;
  }

  function removeFieldEverywhere(tableId, fieldId, fieldName) {
    if (!tableId) return;

    let removedSomething = false;

    // 1) THEAD
    const theadForm = document.querySelector(`form.thead-form[data-table-id="${tableId}"]`);
    if (theadForm) {
      const headerCell = theadForm.querySelector(`[data-field-id="${fieldId}"]`)
                       || theadForm.querySelector(`[data-field-name="${CSS.escape(fieldName)}"]`)
                       || theadForm.querySelector(`.p-2 input[name="extra_field_${fieldId}"]`)?.closest('.p-2');
      if (headerCell) {
        headerCell.remove();
        removedSomething = true;
      }
    }

    // 2) TBODY
    if (removeFieldFromRows(tableId, fieldId, fieldName)) {
      removedSomething = true;
    }

    // 3) CREATE FORM
    document.querySelectorAll('#addForm form.new-record-form').forEach(form => {
      const wrapper = form.querySelector(`.dynamic-field[data-field-id="${fieldId}"]`)
                   || form.querySelector(`.dynamic-field[data-field-name="${CSS.escape(fieldName)}"]`)
                   || form.querySelector(`[name="extra_field_${fieldId}"]`)?.closest('.dynamic-field, div');
      if (wrapper) {
        wrapper.remove();
        removedSomething = true;
      }
    });

    // 4) DELETE MODAL
    document.querySelectorAll('#addDeletePop [data-field-row]').forEach(row => {
      const btn = row.querySelector('.delete-field-btn');
      const input = row.querySelector('input[readonly]');
      if ((btn && btn.dataset.id == fieldId) || (input && input.value.trim() === fieldName)) {
        row.remove();
        removedSomething = true;
      }
    });

    // 5) Adjust grid cols if needed
    if (removedSomething) {
      document.querySelectorAll(ROW_SEL).forEach(el => {
        const cur = parseInt(getComputedStyle(el).getPropertyValue('--cols') || '0', 10) || 0;
        if (cur > 0) el.style.setProperty('--cols', String(cur - 1));
      });
      document.querySelectorAll('form.thead-form .app-grid').forEach(grid => {
        const cur = parseInt(getComputedStyle(grid).getPropertyValue('--cols') || '0', 10) || 0;
        if (cur > 0) grid.style.setProperty('--cols', String(cur - 1));
      });
    }
  }

  // --- Endpoint resolver
  function getDeleteEndpoint(tableId) {
    const theadForm = document.querySelector(`form.thead-form[data-table-id="${tableId}"]`);
    const src = theadForm?.dataset?.src || 'Universal%20Table';
    return `/ItemPilot/categories/${src}/delete_fields.php`;
  }

  async function ajaxJSON(url, opts = {}) {
    const res  = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', ...(opts.headers || {}) },
      ...opts
    });
    const txt  = await res.text();
    let data   = null;
    try { data = txt ? JSON.parse(txt) : null; } catch {}
    if (!res.ok || !data || data.ok === false) {
      throw new Error((data && (data.error || data.message)) || `HTTP ${res.status}`);
    }
    return data;
  }

  // --- Click listener for delete
  document.addEventListener('click', async (e) => {
    const el =
      e.target.closest('#addDeletePop a[href*="/delete_fields.php"]') ||
      e.target.closest('#addDeletePop button.delete-field-btn');
    if (!el) return;

    e.preventDefault();
    if (!confirm('Delete this field?')) return;

    let rowEl = el.closest('[data-field-row]') || el.closest('.flex');
    const hintedName  = (rowEl?.querySelector('input[readonly]')?.value || '').trim();
    const hintedId    = parseInt(el.dataset.id || (el.href?.match(/[?&]id=(\d+)/)?.[1]) || '0', 10);
    const hintedTid   = parseInt(el.dataset.tableId || '0', 10);

    try {
      let data;
      if (el.tagName === 'A') {
        data = await ajaxJSON(el.href);
      } else {
        const body = new URLSearchParams({ id: String(hintedId), table_id: String(hintedTid) });
        data = await ajaxJSON(getDeleteEndpoint(hintedTid), {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });
      }

      const tableId = data.table_id || hintedTid;
      const fieldId = data.field_id || hintedId;
      const colKey  = data.field_name || hintedName;

      removeFieldEverywhere(tableId, fieldId, colKey);

      rowEl?.remove();
      if (!document.querySelector('#addDeletePop [data-field-row]')) {
        document.getElementById('addDeletePop')?.classList.add('hidden');
        document.getElementById('actionMenuList')?.classList.add('hidden');
      }
    } catch (err) {
      console.error(err);
      if (hintedName) {
        removeFieldEverywhere(hintedTid, hintedId, hintedName);
        rowEl?.remove();
      } else {
        alert(err.message || 'Delete failed');
      }
    }
  });
})();

