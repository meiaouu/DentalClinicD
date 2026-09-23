(function () {
    'use strict';

    const chart = document.querySelector('[data-image-odontogram]');
    const form = document.getElementById('dentalChartForm');

    const selectedToothInput = document.getElementById('selectedToothInput');
    const selectedSurfaceInput = document.getElementById('selectedSurfaceInput');
    const selectedToothLabel = document.getElementById('selectedToothLabel');
    const selectedToothType = document.getElementById('selectedToothType');

    const surfaceButtons = document.querySelectorAll('.surface-button');
    const saveButton = document.querySelector('.save-chart-button');
    const chartMessage = document.getElementById('chartMessage');

    if (!chart || !form || !selectedToothInput || !selectedSurfaceInput || !selectedToothLabel) {
        return;
    }

    function clean(value) {
        return String(value || '').trim();
    }

    function clearMessage() {
        if (!chartMessage) {
            return;
        }

        chartMessage.hidden = true;
        chartMessage.textContent = '';
        chartMessage.classList.remove('chart-alert-success', 'chart-alert-error');
    }

    function showMessage(message, type) {
        if (!chartMessage) {
            return;
        }

        chartMessage.hidden = false;
        chartMessage.textContent = message;
        chartMessage.classList.remove('chart-alert-success', 'chart-alert-error');

        if (type === 'error') {
            chartMessage.classList.add('chart-alert-error');
        }

        if (type === 'success') {
            chartMessage.classList.add('chart-alert-success');
        }
    }

    function selectedProcedure() {
        return form.querySelector('input[name="procedure_name"]:checked');
    }

    function validateSaveButton() {
        const hasTooth = clean(selectedToothInput.value) !== '';
        const hasSurface = clean(selectedSurfaceInput.value) !== '';
        const hasProcedure = Boolean(selectedProcedure());

        if (saveButton) {
            saveButton.disabled = !(hasTooth && hasSurface && hasProcedure);
        }
    }

    function resetSurfaceSelection() {
        selectedSurfaceInput.value = '';

        surfaceButtons.forEach(function (button) {
            button.classList.remove('is-selected');
        });
    }

    function selectTooth(button) {
        const toothNumber = clean(button.getAttribute('data-tooth'));
        const toothType = clean(button.getAttribute('data-tooth-type'));

        if (toothNumber === '') {
            return;
        }

        chart.querySelectorAll('[data-tooth]').forEach(function (item) {
            item.classList.remove('is-selected');
        });

        button.classList.add('is-selected');

        selectedToothInput.value = toothNumber;
        selectedToothLabel.textContent = toothNumber;

        if (selectedToothType) {
            selectedToothType.textContent = toothType !== '' ? toothType : '—';
        }

        resetSurfaceSelection();
        clearMessage();
        validateSaveButton();
    }

    chart.querySelectorAll('[data-tooth]').forEach(function (button) {
        button.addEventListener('click', function () {
            selectTooth(button);
        });

        button.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            selectTooth(button);
        });
    });

    surfaceButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const surface = clean(button.getAttribute('data-surface'));

            if (surface === '') {
                return;
            }

            surfaceButtons.forEach(function (item) {
                item.classList.remove('is-selected');
            });

            button.classList.add('is-selected');
            selectedSurfaceInput.value = surface;

            clearMessage();
            validateSaveButton();
        });
    });

    form.querySelectorAll('input[name="procedure_name"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            clearMessage();
            validateSaveButton();
        });
    });

    form.addEventListener('submit', function (event) {
        if (clean(selectedToothInput.value) === '') {
            event.preventDefault();
            showMessage('Please select a tooth first.', 'error');
            return;
        }

        if (clean(selectedSurfaceInput.value) === '') {
            event.preventDefault();
            showMessage('Please select a tooth surface.', 'error');
            return;
        }

        if (!selectedProcedure()) {
            event.preventDefault();
            showMessage('Please select a dental procedure.', 'error');
            return;
        }

        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';
        }
    });

    const firstTooth = chart.querySelector('.image-tooth-button.has-record') ||
        chart.querySelector('.image-tooth-button');

    if (firstTooth) {
        selectTooth(firstTooth);
    }

    validateSaveButton();
})();