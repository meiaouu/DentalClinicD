(function () {
    'use strict';

    const entries = Array.isArray(window.DENTAL_CHART_ENTRIES)
        ? window.DENTAL_CHART_ENTRIES
        : [];

    const chart = document.getElementById('dentalChart');
    const form = document.getElementById('dentalChartForm');
    const selectedToothInput = document.getElementById('selectedToothInput');
    const selectedSurfaceInput = document.getElementById('selectedSurfaceInput');
    const selectedToothLabel = document.getElementById('selectedToothLabel');
    const selectedToothHistory = document.getElementById('selectedToothHistory');
    const saveButton = form ? form.querySelector('.save-chart-button') : null;
    const messageBox = document.getElementById('chartMessage');

    if (!chart || !form || !saveButton) {
        return;
    }

    function showMessage(message, type) {
        if (!messageBox) {
            return;
        }

        messageBox.hidden = false;
        messageBox.textContent = message;
        messageBox.className = 'chart-alert ' + (type === 'success' ? 'chart-alert-success' : 'chart-alert-error');

        window.setTimeout(function () {
            messageBox.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }, 80);
    }

    function statusClass(status) {
        switch (status) {
            case 'performed':
                return 'performed';
            case 'completed':
                return 'completed';
            case 'cancelled':
                return 'cancelled';
            default:
                return 'planned';
        }
    }

    function entriesForTooth(toothNumber) {
        return entries.filter(function (entry) {
            return parseInt(entry.tooth_number, 10) === parseInt(toothNumber, 10);
        });
    }

    function buildTooltip(toothEntries) {
        if (!toothEntries.length) {
            return 'No procedures recorded.';
        }

        return toothEntries.map(function (entry) {
            return [
                entry.procedure_name || '',
                entry.surface || '',
                entry.status ? entry.status.charAt(0).toUpperCase() + entry.status.slice(1) : ''
            ].filter(Boolean).join(' - ');
        }).join('\n');
    }

    function renderSelectedHistory(toothNumber) {
        const toothEntries = entriesForTooth(toothNumber);

        selectedToothHistory.innerHTML = '';

        if (!toothEntries.length) {
            const empty = document.createElement('p');
            empty.className = 'empty-note';
            empty.textContent = 'No records for this tooth yet.';
            selectedToothHistory.appendChild(empty);
            return;
        }

        toothEntries.forEach(function (entry) {
            const item = document.createElement('div');
            item.className = 'history-item';

            const main = document.createElement('div');

            const title = document.createElement('strong');
            title.textContent = 'Tooth ' + String(entry.tooth_number || '');

            const details = document.createElement('span');
            details.textContent = [
                entry.procedure_name,
                entry.surface,
                entry.status ? entry.status.charAt(0).toUpperCase() + entry.status.slice(1) : ''
            ].filter(Boolean).join(' · ');

            main.appendChild(title);
            main.appendChild(details);

            item.appendChild(main);

            if (entry.notes) {
                const notes = document.createElement('p');
                notes.className = 'history-notes';
                notes.textContent = String(entry.notes);
                item.appendChild(notes);
            }

            const date = document.createElement('small');
            date.textContent = String(entry.created_at || '');

            item.appendChild(date);
            selectedToothHistory.appendChild(item);
        });
    }

    function updateToothIndicators(toothNumber) {
        const toothButton = chart.querySelector('.tooth-button[data-tooth="' + toothNumber + '"]');

        if (!toothButton) {
            return;
        }

        const toothEntries = entriesForTooth(toothNumber);
        const tooltip = buildTooltip(toothEntries);

        toothButton.dataset.tooltip = tooltip;
        toothButton.title = tooltip;

        const indicatorWrap = toothButton.querySelector('.tooth-indicators');

        if (!indicatorWrap) {
            return;
        }

        indicatorWrap.innerHTML = '';

        toothEntries.slice(0, 3).forEach(function (entry) {
            const dot = document.createElement('span');
            dot.className = 'tooth-indicator tooth-indicator-' + statusClass(entry.status);
            dot.title = [entry.procedure_name, entry.surface].filter(Boolean).join(' - ');
            indicatorWrap.appendChild(dot);
        });
    }

    function getSelectedProcedure() {
        return form.querySelector('[name="procedure_name"]:checked');
    }

    function validateFormState() {
        const hasTooth = selectedToothInput.value.trim() !== '';
        const hasSurface = selectedSurfaceInput.value.trim() !== '';
        const hasProcedure = !!getSelectedProcedure();

        saveButton.disabled = !(hasTooth && hasSurface && hasProcedure);
    }

    chart.addEventListener('click', function (event) {
        const toothButton = event.target.closest('.tooth-button');

        if (!toothButton) {
            return;
        }

        chart.querySelectorAll('.tooth-button').forEach(function (button) {
            button.classList.remove('is-selected');
        });

        toothButton.classList.add('is-selected');

        const toothNumber = toothButton.dataset.tooth;
        selectedToothInput.value = toothNumber;
        selectedToothLabel.textContent = toothNumber;

        renderSelectedHistory(toothNumber);
        validateFormState();
    });

    document.querySelectorAll('.surface-button').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('.surface-button').forEach(function (item) {
                item.classList.remove('is-selected');
            });

            button.classList.add('is-selected');
            selectedSurfaceInput.value = button.dataset.surface || '';

            validateFormState();
        });
    });

    form.querySelectorAll('[name="procedure_name"]').forEach(function (radio) {
        radio.addEventListener('change', validateFormState);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!selectedToothInput.value) {
            showMessage('Please select a tooth number.', 'error');
            return;
        }

        if (!selectedSurfaceInput.value) {
            showMessage('Please select a tooth surface.', 'error');
            return;
        }

        if (!getSelectedProcedure()) {
            showMessage('Please select a procedure.', 'error');
            return;
        }

        const formData = new FormData(form);

        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    if (!response.ok || !json.success) {
                        throw new Error(json.message || 'Unable to save dental chart entry.');
                    }

                    return json;
                });
            })
            .then(function (json) {
                if (json.entry) {
                    entries.unshift(json.entry);
                    updateToothIndicators(json.entry.tooth_number);
                    renderSelectedHistory(json.entry.tooth_number);
                }

                form.querySelectorAll('[name="procedure_name"]').forEach(function (radio) {
                    radio.checked = false;
                });

                const status = form.querySelector('[name="status"]');
                const notes = form.querySelector('[name="notes"]');

                if (status) {
                    status.value = 'planned';
                }

                if (notes) {
                    notes.value = '';
                }

                showMessage(json.message || 'Dental chart entry saved successfully.', 'success');
            })
            .catch(function (error) {
                showMessage(error.message, 'error');
            })
            .finally(function () {
                saveButton.textContent = 'Save Dental Chart Entry';
                validateFormState();
            });
    });

    validateFormState();
})();