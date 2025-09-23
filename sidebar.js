
  const menuBtn   = document.getElementById('menuBtn');
  const sidebar   = document.getElementById('sidebar');
  const root      = document.documentElement;
  const appHeader = document.getElementById('appHeader');

  if (menuBtn && sidebar) {
  const OFFSET_PX = -2;
  const PEEK_PX   = 20;

  const isHidden = () => !sidebar.classList.contains('show');
  const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

  function sidebarWidth() {
    sidebar.classList.add('show'); // temporarily show
    const w = Math.ceil(sidebar.getBoundingClientRect().width);
    if (isHidden()) sidebar.classList.remove('show');
    return w;
  }

  function addBackdrop() {
    if (document.getElementById('sb-backdrop')) return;
    const el = document.createElement('div');
    el.id = 'sb-backdrop';
    el.addEventListener('click', closeSidebar);
    document.body.appendChild(el);
    document.body.classList.add('no-scroll');
    root.classList.add('mobile-dim');
  }

  function removeBackdrop() {
    const el = document.getElementById('sb-backdrop');
    if (el) el.remove();
    document.body.classList.remove('no-scroll');
    root.classList.remove('mobile-dim');
  }

  function openSidebar() {
    const w = sidebarWidth();
    sidebar.classList.add('show');
    root.style.setProperty('--sbw', Math.max(0, w - OFFSET_PX) + 'px');
    menuBtn.setAttribute('aria-expanded', 'true');
    if (isMobile()) addBackdrop();
    if (appHeader) {
      appHeader.classList.remove('w-[400px]');
      appHeader.classList.add('max-w-lg');
    }
    localStorage.setItem('sidebarState', 'open'); // ✅ remember
  }

  function closeSidebar() {
    const w = sidebarWidth();
    sidebar.classList.remove('show');
    root.style.setProperty('--sbw', '0px');
    menuBtn.setAttribute('aria-expanded', 'false');
    if (isMobile()) removeBackdrop();
    if (appHeader) {
      appHeader.classList.remove('max-w-lg');
      appHeader.classList.add('w-[400px]');
    }
    localStorage.setItem('sidebarState', 'closed'); // ✅ remember
  }

  menuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    isHidden() ? openSidebar() : closeSidebar();
  });

  // ✅ Restore state from history
  const savedState = localStorage.getItem('sidebarState');
  if (savedState === 'open') {
    openSidebar();
  } else if (savedState === 'closed') {
    closeSidebar();
  } else {
    // default if nothing saved yet
    closeSidebar(); // start closed (you can change to openSidebar() for desktop default)
  }

  // ✅ Handle redirect after insert/edit/delete
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("action") === "done") {
    if (isMobile()) {
      closeSidebar(); // auto-close mobile
    }
    // desktop stays as last saved
  }
}