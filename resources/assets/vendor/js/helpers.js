window.Helpers = window.Helpers || {
  isSmallScreen() {
    return window.innerWidth < 1200;
  },
  scrollToActive() {},
  toggleCollapsed() {
    document.documentElement.classList.toggle('layout-menu-collapsed');
  },
  setAutoUpdate() {},
  setCollapsed(collapsed = true) {
    document.documentElement.classList.toggle('layout-menu-collapsed', collapsed);
  },
  initPasswordToggle() {
    document.querySelectorAll('.form-password-toggle .input-group-text').forEach(trigger => {
      trigger.addEventListener('click', () => {
        const input = trigger.closest('.input-group')?.querySelector('input');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
      });
    });
  },
  initSpeechToText() {},
};
