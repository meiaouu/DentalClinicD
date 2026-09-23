(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form.matches('[data-confirm]')) {
            return;
        }

        const message = form.getAttribute('data-confirm') || 'Continue?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
})();