(function () {
    'use strict';

    if (window.__userSettingsInitialized === true) {
        return;
    }

    window.__userSettingsInitialized = true;

    const appearanceClasses = {
        theme_mode: ['theme-light', 'theme-dark', 'theme-system'],
        accent_color: ['accent-teal', 'accent-blue', 'accent-green', 'accent-purple', 'accent-gray'],
        font_size: ['font-small', 'font-normal', 'font-large'],
        layout_density: ['density-comfortable', 'density-compact'],
        border_radius: ['radius-none', 'radius-small', 'radius-medium'],
        sidebar_mode: ['sidebar-expanded', 'sidebar-compact']
    };

    function switchSettingsTab(tabName) {
        document.querySelectorAll('[data-settings-tab]').forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-settings-tab') === tabName);
        });

        document.querySelectorAll('[data-settings-panel]').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-settings-panel') === tabName);
        });

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url.toString());
    }

    function removeClassGroup(classes) {
        classes.forEach(function (className) {
            document.body.classList.remove(className);
        });
    }

    function applyAppearanceValue(name, value) {
        if (!appearanceClasses[name]) {
            return;
        }

        removeClassGroup(appearanceClasses[name]);

        const className = {
            theme_mode: 'theme-' + value,
            accent_color: 'accent-' + value,
            font_size: 'font-' + value,
            layout_density: 'density-' + value,
            border_radius: 'radius-' + value,
            sidebar_mode: 'sidebar-' + value
        }[name];

        if (className) {
            document.body.classList.add(className);
        }
    }

    function previewAppearance(form) {
        if (!form) {
            return;
        }

        form.querySelectorAll('[data-appearance-control]').forEach(function (control) {
            applyAppearanceValue(control.name, control.value);
        });
    }

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-settings-back]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            if (window.history.length > 1) {
                event.preventDefault();
                window.history.back();
            }
        });
    });

    document.querySelectorAll('[data-settings-tab]').forEach(function (button) {
        button.addEventListener('click', function () {
            switchSettingsTab(button.getAttribute('data-settings-tab'));
        });
    });

        document.querySelectorAll('[data-account-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                const item = button.closest('.account-collapse-item');

                if (!item) {
                    return;
                }

                const isOpen = item.classList.contains('is-open');

                document.querySelectorAll('.account-collapse-item.is-open').forEach(function (openItem) {
                    if (openItem !== item) {
                        openItem.classList.remove('is-open');
                    }
                });

                item.classList.toggle('is-open', !isOpen);
            });
        });

        const appearanceForm = document.querySelector('[data-appearance-form]');

        if (appearanceForm) {
            appearanceForm.querySelectorAll('[data-appearance-control]').forEach(function (control) {
                control.addEventListener('change', function () {
                    previewAppearance(appearanceForm);
                });
            });
        }

        document.querySelectorAll('[data-reset-appearance-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const confirmed = window.confirm('Reset your appearance settings to default?');

                if (!confirmed) {
                    event.preventDefault();
                }
            });
        });
    });
})();