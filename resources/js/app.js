const menuToggle = document.getElementById('mobile-menu-toggle');
const sidebar = document.getElementById('app-sidebar');

if (menuToggle && sidebar) {
    const closeMenu = () => {
        sidebar.classList.remove('is-open');
        menuToggle.setAttribute('aria-expanded', 'false');
    };
    menuToggle.addEventListener('click', () => {
        const isOpen = sidebar.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', String(isOpen));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeMenu();
            menuToggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            closeMenu();
        }
    });
    window.matchMedia('(min-width: 64rem)').addEventListener('change', closeMenu);
}
