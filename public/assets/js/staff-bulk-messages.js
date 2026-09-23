(function () {
    'use strict';

    const form = document.getElementById('bulkReminderForm');
    const selectAll = document.getElementById('selectAllAppointments');
    const selectedCount = document.getElementById('selectedAppointmentCount');
    const previewButton = document.getElementById('previewSelectedMessage');
    const reminderTypeSelect = document.getElementById('sendReminderType');

    const modal = document.getElementById('bulkPreviewModal');
    const closeModal = document.getElementById('closeBulkPreviewModal');
    const previewMessage = document.getElementById('bulkPreviewMessage');

    function getCheckboxes() {
        return Array.from(document.querySelectorAll('.appointment-checkbox'));
    }

    function getSelectedCheckboxes() {
        return getCheckboxes().filter(function (checkbox) {
            return checkbox.checked && !checkbox.disabled;
        });
    }

    function updateSelectedCount() {
        if (!selectedCount) {
            return;
        }

        selectedCount.textContent = String(getSelectedCheckboxes().length);
    }

    function updateSelectAllState() {
        if (!selectAll) {
            return;
        }

        const checkboxes = getCheckboxes().filter(function (checkbox) {
            return !checkbox.disabled;
        });

        const selected = checkboxes.filter(function (checkbox) {
            return checkbox.checked;
        });

        selectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
    }

    function openModal() {
        if (!modal) {
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function hideModal() {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function requireReminderType() {
        if (!reminderTypeSelect || reminderTypeSelect.value.trim() === '') {
            alert('Please select the reminder type to send.');
            return '';
        }

        return reminderTypeSelect.value.trim();
    }

    function firstSelectedAppointmentId() {
        const selected = getSelectedCheckboxes();

        if (!selected.length) {
            return 0;
        }

        return parseInt(selected[0].value, 10) || 0;
    }

    getCheckboxes().forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            updateSelectedCount();
            updateSelectAllState();
        });
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            getCheckboxes().forEach(function (checkbox) {
                if (!checkbox.disabled) {
                    checkbox.checked = selectAll.checked;
                }
            });

            updateSelectedCount();
            updateSelectAllState();
        });
    }

    if (previewButton && form) {
        previewButton.addEventListener('click', function () {
            const appointmentId = firstSelectedAppointmentId();

            if (!appointmentId) {
                alert('Please select at least one appointment to preview.');
                return;
            }

            const reminderType = requireReminderType();

            if (!reminderType) {
                return;
            }

            const previewUrl = form.getAttribute('data-preview-url') || '';
            const url = previewUrl + '?appointment_id=' + encodeURIComponent(String(appointmentId)) +
                '&reminder_type=' + encodeURIComponent(reminderType);

            if (previewMessage) {
                previewMessage.textContent = 'Loading preview...';
            }

            openModal();

            fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!previewMessage) {
                        return;
                    }

                    if (!data || data.ok !== true) {
                        previewMessage.textContent = data && data.message
                            ? data.message
                            : 'Unable to load message preview.';
                        return;
                    }

                    previewMessage.textContent = data.message || '';
                })
                .catch(function () {
                    if (previewMessage) {
                        previewMessage.textContent = 'Unable to load message preview.';
                    }
                });
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            const selected = getSelectedCheckboxes();

            if (!selected.length) {
                event.preventDefault();
                alert('Please select at least one appointment.');
                return;
            }

            const reminderType = requireReminderType();

            if (!reminderType) {
                event.preventDefault();
                return;
            }

            const confirmed = confirm(
                'Send ' + selected.length + ' appointment reminder(s)? Duplicate reminders will be skipped.'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        });
    }

    if (closeModal) {
        closeModal.addEventListener('click', hideModal);
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                hideModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hideModal();
        }
    });

    updateSelectedCount();
    updateSelectAllState();
})();