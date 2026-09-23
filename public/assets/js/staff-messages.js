(function () {
    'use strict';

    const shell = document.getElementById('messagesShell');
    const searchInput = document.getElementById('messageSearchInput');
    const conversationItems = document.querySelectorAll('[data-thread-item]');
    const emptyFilter = document.getElementById('messageNoFilterResult');
    const conversationMessages = document.getElementById('conversationMessages');

    const openDrawerBtn = document.getElementById('openDetailsDrawer');
    const closeDrawerBtn = document.getElementById('closeDetailsDrawer');
    const detailsDrawer = document.getElementById('detailsDrawer');

    const createModal = document.getElementById('createMessageModal');
    const openCreateModalBtn = document.getElementById('openCreateMessageModal');
    const closeCreateModalBtn = document.getElementById('closeCreateMessageModal');
    const cancelCreateModalBtn = document.getElementById('cancelCreateMessageModal');

    const patientSearchInput = document.getElementById('patientSearchInput');
    const patientSelect = document.getElementById('patientSelect');

    const composer = document.getElementById('staffMessageComposer');
    const attachmentInput = document.getElementById('messageAttachmentInput');
    const selectedFileName = document.getElementById('selectedFileName');

    function setHidden(element, hidden) {
        if (!element) {
            return;
        }

        element.hidden = hidden;
    }

    function filterConversations() {
        const search = searchInput ? searchInput.value.trim().toLowerCase() : '';
        let visibleCount = 0;

        conversationItems.forEach(function (item) {
            const haystack = item.getAttribute('data-search') || '';
            const shouldShow = search === '' || haystack.indexOf(search) !== -1;

            item.hidden = !shouldShow;

            if (shouldShow) {
                visibleCount++;
            }
        });

        setHidden(emptyFilter, visibleCount > 0);
    }

    function openDetailsDrawer() {
        if (!shell || !detailsDrawer) {
            return;
        }

        shell.classList.add('drawer-open');
        detailsDrawer.setAttribute('aria-hidden', 'false');

        if (openDrawerBtn) {
            openDrawerBtn.classList.add('active');
        }
    }

    function closeDetailsDrawer() {
        if (!shell || !detailsDrawer) {
            return;
        }

        shell.classList.remove('drawer-open');
        detailsDrawer.setAttribute('aria-hidden', 'true');

        if (openDrawerBtn) {
            openDrawerBtn.classList.remove('active');
        }
    }

    function openCreateModal() {
        if (!createModal) {
            return;
        }

        createModal.classList.add('open');
        createModal.setAttribute('aria-hidden', 'false');

        if (patientSearchInput) {
            patientSearchInput.focus();
        }
    }

    function closeCreateModal() {
        if (!createModal) {
            return;
        }

        createModal.classList.remove('open');
        createModal.setAttribute('aria-hidden', 'true');
    }

    function filterPatients() {
        if (!patientSearchInput || !patientSelect) {
            return;
        }

        const search = patientSearchInput.value.trim().toLowerCase();
        const options = patientSelect.querySelectorAll('option');

        options.forEach(function (option) {
            if (option.value === '') {
                option.hidden = false;
                return;
            }

            const haystack = option.getAttribute('data-search') || option.textContent.toLowerCase();
            option.hidden = search !== '' && haystack.indexOf(search) === -1;
        });
    }

    function insertTextToComposer(text) {
        if (!composer || !text) {
            return;
        }

        composer.value = text;
        composer.focus();
    }









(function () {
    'use strict';

    const createModal = document.getElementById('createMessageModal');
    const openCreateModal = document.getElementById('openCreateMessageModal');
    const closeCreateModal = document.getElementById('closeCreateMessageModal');
    const cancelCreateModal = document.getElementById('cancelCreateMessageModal');

    const patientSearchInput = document.getElementById('patientSearchInput');
    const patientOptions = document.querySelectorAll('[data-patient-option]');
    const patientNoResult = document.getElementById('patientNoResult');

    const selectedPatientId = document.getElementById('selectedPatientId');
    const selectedPatientBox = document.getElementById('selectedPatientBox');
    const selectedPatientName = document.getElementById('selectedPatientName');
    const selectedPatientMode = document.getElementById('selectedPatientMode');
    const createSubmitBtn = document.getElementById('createMessageSubmitBtn');

    function openModal() {
        if (!createModal) {
            return;
        }

        createModal.classList.add('open');
        createModal.setAttribute('aria-hidden', 'false');

        if (patientSearchInput) {
            patientSearchInput.focus();
        }
    }

    function closeModal() {
        if (!createModal) {
            return;
        }

        createModal.classList.remove('open');
        createModal.setAttribute('aria-hidden', 'true');
    }

    function filterPatients() {
        const search = patientSearchInput
            ? patientSearchInput.value.trim().toLowerCase()
            : '';

        let visibleCount = 0;

        patientOptions.forEach(function (option) {
            const haystack = option.getAttribute('data-search') || '';
            const shouldShow = search === '' || haystack.indexOf(search) !== -1;

            option.hidden = !shouldShow;

            if (shouldShow) {
                visibleCount++;
            }
        });

        if (patientNoResult) {
            patientNoResult.hidden = visibleCount > 0;
        }
    }

    function selectPatient(option) {
        const patientId = option.getAttribute('data-patient-id') || '';
        const patientName = option.getAttribute('data-patient-name') || '';
        const existingConversationId = parseInt(option.getAttribute('data-existing-conversation-id') || '0', 10);

        patientOptions.forEach(function (item) {
            item.classList.remove('selected');
        });

        option.classList.add('selected');

        if (selectedPatientId) {
            selectedPatientId.value = patientId;
        }

        if (selectedPatientName) {
            selectedPatientName.textContent = patientName;
        }

        if (selectedPatientMode) {
            selectedPatientMode.textContent = existingConversationId > 0
                ? '(Existing conversation)'
                : '(New conversation)';
        }

        if (selectedPatientBox) {
            selectedPatientBox.hidden = false;
        }

        if (createSubmitBtn) {
            createSubmitBtn.textContent = existingConversationId > 0
                ? 'Send'
                : 'Start Conversation';
        }
    }

    if (openCreateModal) {
        openCreateModal.addEventListener('click', openModal);
    }

    if (closeCreateModal) {
        closeCreateModal.addEventListener('click', closeModal);
    }

    if (cancelCreateModal) {
        cancelCreateModal.addEventListener('click', closeModal);
    }

    if (createModal) {
        createModal.addEventListener('click', function (event) {
            if (event.target === createModal) {
                closeModal();
            }
        });
    }

    if (patientSearchInput) {
        patientSearchInput.addEventListener('input', filterPatients);
    }

    patientOptions.forEach(function (option) {
        option.addEventListener('click', function () {
            selectPatient(option);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    const createForm = document.querySelector('.create-message-form');

    if (createForm) {
        createForm.addEventListener('submit', function (event) {
            if (!selectedPatientId || selectedPatientId.value === '') {
                event.preventDefault();
                alert('Please select a registered patient first.');
            }
        });
    }
})();



    function updateSelectedFileName() {
        if (!attachmentInput || !selectedFileName) {
            return;
        }

        const file = attachmentInput.files && attachmentInput.files.length > 0
            ? attachmentInput.files[0]
            : null;

        if (!file) {
            selectedFileName.textContent = '';
            selectedFileName.hidden = true;
            return;
        }

        selectedFileName.textContent = 'Selected file: ' + file.name;
        selectedFileName.hidden = false;
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterConversations);
    }

    if (openDrawerBtn) {
        openDrawerBtn.addEventListener('click', function () {
            if (shell && shell.classList.contains('drawer-open')) {
                closeDetailsDrawer();
                return;
            }

            openDetailsDrawer();
        });
    }

    if (closeDrawerBtn) {
        closeDrawerBtn.addEventListener('click', closeDetailsDrawer);
    }

    if (openCreateModalBtn) {
        openCreateModalBtn.addEventListener('click', openCreateModal);
    }

    if (closeCreateModalBtn) {
        closeCreateModalBtn.addEventListener('click', closeCreateModal);
    }

    if (cancelCreateModalBtn) {
        cancelCreateModalBtn.addEventListener('click', closeCreateModal);
    }

    if (createModal) {
        createModal.addEventListener('click', function (event) {
            if (event.target === createModal) {
                closeCreateModal();
            }
        });
    }

    if (patientSearchInput) {
        patientSearchInput.addEventListener('input', filterPatients);
    }

    if (attachmentInput) {
        attachmentInput.addEventListener('change', updateSelectedFileName);
    }

    document.querySelectorAll('[data-insert-text]').forEach(function (button) {
        button.addEventListener('click', function () {
            insertTextToComposer(button.getAttribute('data-insert-text') || '');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeCreateModal();
            closeDetailsDrawer();
        }
    });

    if (conversationMessages) {
        conversationMessages.scrollTop = conversationMessages.scrollHeight;
    }
})();