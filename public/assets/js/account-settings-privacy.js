(function () {
    if (window.__accountPrivacySettingsInitialized === true) {
        return;
    }

    window.__accountPrivacySettingsInitialized = true;

    const config = window.DENTAL_ACCOUNT_PRIVACY || {};
    const baseUrl = config.baseUrl || '/DentalClinic/public';

    const accountForm = document.getElementById('accountSettingsForm');
    const privacyForm = document.getElementById('privacySettingsForm');
    const messageBox = document.getElementById('accountPrivacyMessage');

    function showMessage(type, message) {
        if (!messageBox) {
            return;
        }

        messageBox.classList.remove('is-hidden', 'success', 'error');
        messageBox.classList.add(type === 'success' ? 'success' : 'error');
        messageBox.textContent = message || 'Something went wrong.';
        messageBox.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }

    function updateCsrfTokens(token) {
        if (!token) {
            return;
        }

        document.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = token;
        });

        window.DENTAL_ACCOUNT_PRIVACY.csrfToken = token;
    }

    async function submitForm(form, endpoint, successFallback) {
        if (!form) {
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);

        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(baseUrl + endpoint, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const raw = await response.text();
            let data = null;

            try {
                data = JSON.parse(raw);
            } catch (error) {
                showMessage('error', 'Invalid server response.');
                return;
            }

            if (!response.ok || !data.success) {
                showMessage('error', data.message || 'Unable to save changes.');
                updateCsrfTokens(data.csrf_token);
                return;
            }

            updateCsrfTokens(data.csrf_token);
            showMessage('success', data.message || successFallback);

            const passwordFields = form.querySelectorAll(
                'input[name="current_password"], input[name="new_password"], input[name="confirm_new_password"]'
            );

            passwordFields.forEach(function (input) {
                input.value = '';
            });
        } catch (error) {
            showMessage('error', 'Unable to connect to the server.');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    }

    if (accountForm) {
        accountForm.addEventListener('submit', function (event) {
            event.preventDefault();

            submitForm(
                accountForm,
                '/settings/account',
                'Account settings saved successfully.'
            );
        });
    }

    if (privacyForm) {
        privacyForm.addEventListener('submit', function (event) {
            event.preventDefault();

            submitForm(
                privacyForm,
                '/settings/privacy',
                'Privacy settings saved successfully.'
            );
        });
    }
})();