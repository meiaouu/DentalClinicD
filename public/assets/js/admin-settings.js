(function () {
    'use strict';

    const menuLinks = document.querySelectorAll('.settings-menu a');
    const sections = document.querySelectorAll('.settings-card[id]');

    function setActiveLink() {
        let activeId = '';

        sections.forEach(function (section) {
            const rect = section.getBoundingClientRect();

            if (rect.top <= 120) {
                activeId = section.id;
            }
        });

        menuLinks.forEach(function (link) {
            link.classList.toggle('active', link.getAttribute('href') === '#' + activeId);
        });
    }

    document.addEventListener('scroll', setActiveLink, { passive: true });
    window.addEventListener('load', setActiveLink);

    document.addEventListener('change', function (event) {
        const input = event.target;

        if (!input.matches('input[type="file"]')) {
            return;
        }

        const file = input.files && input.files[0];

        if (!file) {
            return;
        }

        const maxBytes = 3 * 1024 * 1024;
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/x-icon'];

        if (!allowed.includes(file.type)) {
            alert('Only JPG, PNG, WEBP, GIF, or ICO files are allowed.');
            input.value = '';
            return;
        }

        if (file.size > maxBytes) {
            alert('Image must not exceed 3MB.');
            input.value = '';
        }
    });
})();