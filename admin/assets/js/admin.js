(function () {
  const btn = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('adminSidebar');
  if (!btn || !sidebar) return;

  btn.addEventListener('click', () => {
    const collapsed = document.body.classList.toggle('adm-sidebar-collapsed');
    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  });
})();