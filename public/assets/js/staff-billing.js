(function () {
    'use strict';

    function money(value) {
        const amount = Number(value || 0);
        return '₱' + amount.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function toNumber(value) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function calculateBillingPreview() {
        const rows = document.querySelectorAll('.billing-item-row');
        const discountInput = document.getElementById('discount_amount');

        let subtotal = 0;

        rows.forEach(function (row) {
            const qtyInput = row.querySelector('.item-qty');
            const priceInput = row.querySelector('.item-price');
            const totalCell = row.querySelector('.row-total');

            const qty = Math.max(1, parseInt(qtyInput ? qtyInput.value : '1', 10) || 1);
            const price = Math.max(0, toNumber(priceInput ? priceInput.value : '0'));
            const rowTotal = qty * price;

            subtotal += rowTotal;

            if (totalCell) {
                totalCell.textContent = money(rowTotal);
            }
        });

        const discount = Math.max(0, toNumber(discountInput ? discountInput.value : '0'));
        const safeDiscount = Math.min(discount, subtotal);
        const total = Math.max(0, subtotal - safeDiscount);

        const subtotalEl = document.getElementById('previewSubtotal');
        const discountEl = document.getElementById('previewDiscount');
        const totalEl = document.getElementById('previewTotal');
        const balanceEl = document.getElementById('previewBalance');

        if (subtotalEl) subtotalEl.textContent = money(subtotal);
        if (discountEl) discountEl.textContent = money(safeDiscount);
        if (totalEl) totalEl.textContent = money(total);
        if (balanceEl) balanceEl.textContent = money(total);
    }

    function createItemRow() {
        const tr = document.createElement('tr');
        tr.className = 'billing-item-row';

        tr.innerHTML = [
            '<td>',
                '<input type="hidden" name="service_id[]" value="">',
                '<input type="text" name="item_name[]" placeholder="Service / Procedure" required>',
            '</td>',
            '<td>',
                '<input type="text" name="description[]" placeholder="Optional">',
            '</td>',
            '<td>',
                '<input class="item-qty" type="number" name="quantity[]" min="1" value="1" required>',
            '</td>',
            '<td>',
                '<input class="item-price" type="number" step="0.01" min="0" name="unit_price[]" value="0.00" required>',
            '</td>',
            '<td class="row-total">₱0.00</td>',
            '<td>',
                '<button type="button" class="btn btn-small btn-danger remove-item">Remove</button>',
            '</td>'
        ].join('');

        return tr;
    }

    function bindItemEvents(container) {
        container.addEventListener('input', function (event) {
            if (
                event.target.classList.contains('item-qty') ||
                event.target.classList.contains('item-price') ||
                event.target.id === 'discount_amount'
            ) {
                calculateBillingPreview();
            }
        });

        container.addEventListener('click', function (event) {
            if (!event.target.classList.contains('remove-item')) {
                return;
            }

            const rows = document.querySelectorAll('.billing-item-row');

            if (rows.length <= 1) {
                alert('At least one billing item is required.');
                return;
            }

            event.target.closest('tr').remove();
            calculateBillingPreview();
        });
    }

    function bindCreateForm() {
        const form = document.getElementById('billingCreateForm');
        const addButton = document.getElementById('addBillingItem');
        const tbody = document.getElementById('billingItemsBody');
        const patientSelect = document.getElementById('patient_id');
        const appointmentSelect = document.getElementById('appointment_id');
        const discountInput = document.getElementById('discount_amount');

        if (!form || !tbody) {
            return;
        }

        bindItemEvents(form);

        if (addButton) {
            addButton.addEventListener('click', function () {
                tbody.appendChild(createItemRow());
                calculateBillingPreview();
            });
        }

        if (discountInput) {
            discountInput.addEventListener('input', calculateBillingPreview);
        }

        if (patientSelect) {
            patientSelect.addEventListener('change', function () {
                const baseUrl = patientSelect.getAttribute('data-base-url') || '';
                const patientId = patientSelect.value;

                if (patientId) {
                    window.location.href = baseUrl + '/staff/billing/create?patient_id=' + encodeURIComponent(patientId);
                }
            });
        }

        if (appointmentSelect) {
            appointmentSelect.addEventListener('change', function () {
                const baseUrl = appointmentSelect.getAttribute('data-base-url') || '';
                const patientId = patientSelect ? patientSelect.value : '';
                const appointmentId = appointmentSelect.value;

                let url = baseUrl + '/staff/billing/create';

                if (patientId) {
                    url += '?patient_id=' + encodeURIComponent(patientId);
                }

                if (appointmentId) {
                    url += (patientId ? '&' : '?') + 'appointment_id=' + encodeURIComponent(appointmentId);
                }

                window.location.href = url;
            });
        }

        form.addEventListener('submit', function (event) {
            const patientId = patientSelect ? patientSelect.value : '';
            const names = form.querySelectorAll('input[name="item_name[]"]');
            let hasItem = false;

            names.forEach(function (input) {
                if (input.value.trim() !== '') {
                    hasItem = true;
                }
            });

            if (!patientId) {
                event.preventDefault();
                alert('Please select a patient.');
                return;
            }

            if (!hasItem) {
                event.preventDefault();
                alert('Please add at least one billing item.');
            }
        });

        calculateBillingPreview();
    }

    document.addEventListener('DOMContentLoaded', bindCreateForm);
})();