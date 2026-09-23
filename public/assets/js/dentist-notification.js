(function () {
    'use strict';

    if (window.__dentistNotificationInitialized === true) {
        return;
    }

    window.__dentistNotificationInitialized = true;

    const POLL_INTERVAL_MS = 15000;

    let pollingTimer = null;
    let isLoading = false;
    let latestNotifications = [];
    let activeFilter = 'all';

    function getElements() {
        return {
            box: document.querySelector('.dentist-notify-box'),
            button: document.getElementById('dentistNotificationButton'),
            count: document.getElementById('dentistNotificationCount'),
            dropdown: document.getElementById('dentistNotificationList'),
            body: document.getElementById('dentistNotificationBody'),
            tabAll: document.getElementById('dentistNotifyTabAll'),
            tabUnread: document.getElementById('dentistNotifyTabUnread'),
            footer: document.getElementById('dentistNotifyFooter')
        };
    }

    function getConfig() {
        const box = getElements().box;

        return {
            indexUrl: box ? box.dataset.indexUrl : '/DentalClinic/public/dentist/notifications',
            markOneReadUrl: box ? box.dataset.markOneReadUrl : '/DentalClinic/public/dentist/notifications/mark-one-read',
            csrfToken: box ? box.dataset.csrfToken : ''
        };
    }

    function isDropdownOpen() {
        const dropdown = getElements().dropdown;
        return dropdown && dropdown.style.display === 'block';
    }

    function openDropdown() {
        const elements = getElements();

        if (!elements.dropdown) {
            return;
        }

        elements.dropdown.style.display = 'block';

        if (elements.button) {
            elements.button.classList.add('is-open');
            elements.button.setAttribute('aria-expanded', 'true');
        }
    }

    function closeDropdown() {
        const elements = getElements();

        if (!elements.dropdown) {
            return;
        }

        elements.dropdown.style.display = 'none';

        if (elements.button) {
            elements.button.classList.remove('is-open');
            elements.button.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleDropdown() {
        const profileButton = document.getElementById('dentistProfileButton');
        const profileDropdown = document.getElementById('dentistProfileDropdown');

        if (profileButton && profileDropdown) {
            profileDropdown.style.display = 'none';
            profileButton.classList.remove('is-open');
            profileButton.setAttribute('aria-expanded', 'false');
        }

        if (isDropdownOpen()) {
            closeDropdown();
            return;
        }

        openDropdown();
        loadDentistNotifications();
    }

    function updateBadge(unreadCount) {
        const countEl = getElements().count;

        if (!countEl) {
            return;
        }

        const count = Number(unreadCount || 0);

        countEl.textContent = count > 99 ? '99+' : String(count);
        countEl.style.display = count > 0 ? 'inline-block' : 'none';
        countEl.dataset.unreadCount = String(count);
    }

    function showMessage(message) {
        const body = getElements().body;

        if (!body) {
            return;
        }

        body.innerHTML = '';

        const div = document.createElement('div');
        div.className = 'dentist-notify-empty';
        div.textContent = message;

        body.appendChild(div);
    }

    function formatTimeAgo(value) {
        if (!value) {
            return '';
        }

        const date = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        const seconds = Math.floor((Date.now() - date.getTime()) / 1000);

        if (seconds < 60) {
            return 'Just now';
        }

        const minutes = Math.floor(seconds / 60);

        if (minutes < 60) {
            return minutes + 'm';
        }

        const hours = Math.floor(minutes / 60);

        if (hours < 24) {
            return hours + 'h';
        }

        const days = Math.floor(hours / 24);

        return days + 'd';
    }

    function notificationBadgeSvg() {
        return ''
            + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
            + '<path d="M15 17H9"></path>'
            + '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"></path>'
            + '<path d="M13.73 21a2 2 0 0 1-3.46 0"></path>'
            + '</svg>';
    }

    function getNotificationTypeKey(item) {
        const type = String(item.type || '').toLowerCase();
        const title = String(item.title || '').toLowerCase();
        const message = String(item.message || '').toLowerCase();

        if (
            type.includes('today_appointments_summary') ||
            title.includes("today's appointments") ||
            title.includes('today appointments')
        ) {
            return 'today';
        }

        if (
            type.includes('cancel') ||
            title.includes('cancelled') ||
            title.includes('canceled') ||
            message.includes('cancelled') ||
            message.includes('canceled')
        ) {
            return 'cancelled';
        }

        if (
            type.includes('reschedule') ||
            title.includes('rescheduled') ||
            message.includes('rescheduled')
        ) {
            return 'rescheduled';
        }

        if (
            type.includes('reject') ||
            title.includes('rejected') ||
            message.includes('rejected')
        ) {
            return 'rejected';
        }

        if (
            type.includes('confirm') ||
            type.includes('assigned') ||
            title.includes('confirmed') ||
            title.includes('assigned') ||
            message.includes('confirmed')
        ) {
            return 'confirmed';
        }

        return 'default';
    }

    function getNotificationIconSvg(typeKey) {
        if (typeKey === 'today') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<rect x="3" y="4" width="18" height="17" rx="3"></rect>'
                + '<path d="M8 2v4"></path>'
                + '<path d="M16 2v4"></path>'
                + '<path d="M3 9h18"></path>'
                + '<path d="M12 13v4"></path>'
                + '<path d="M10 15h4"></path>'
                + '</svg>';
        }

        if (typeKey === 'confirmed') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<rect x="3" y="4" width="18" height="17" rx="3"></rect>'
                + '<path d="M8 2v4"></path>'
                + '<path d="M16 2v4"></path>'
                + '<path d="M3 9h18"></path>'
                + '<path d="M8.5 14.5l2.5 2.5 4.5-5"></path>'
                + '</svg>';
        }

        if (typeKey === 'cancelled') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<rect x="3" y="4" width="18" height="17" rx="3"></rect>'
                + '<path d="M8 2v4"></path>'
                + '<path d="M16 2v4"></path>'
                + '<path d="M3 9h18"></path>'
                + '<path d="M9 13l6 6"></path>'
                + '<path d="M15 13l-6 6"></path>'
                + '</svg>';
        }

        if (typeKey === 'rescheduled') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<rect x="3" y="4" width="18" height="17" rx="3"></rect>'
                + '<path d="M8 2v4"></path>'
                + '<path d="M16 2v4"></path>'
                + '<path d="M3 9h18"></path>'
                + '<path d="M9 17h6"></path>'
                + '<path d="M13 14l3 3-3 3"></path>'
                + '<path d="M11 20l-3-3 3-3"></path>'
                + '</svg>';
        }

        if (typeKey === 'rejected') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<circle cx="12" cy="12" r="9"></circle>'
                + '<path d="M8.5 8.5l7 7"></path>'
                + '<path d="M15.5 8.5l-7 7"></path>'
                + '</svg>';
        }

        return ''
            + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
            + '<path d="M15 17H9"></path>'
            + '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"></path>'
            + '<path d="M13.73 21a2 2 0 0 1-3.46 0"></path>'
            + '</svg>';
    }

    function getNotificationIconClass(typeKey) {
        if (typeKey === 'today') {
            return 'icon-today';
        }

        if (typeKey === 'confirmed') {
            return 'icon-confirmed';
        }

        if (typeKey === 'cancelled') {
            return 'icon-cancelled';
        }

        if (typeKey === 'rescheduled') {
            return 'icon-rescheduled';
        }

        if (typeKey === 'rejected') {
            return 'icon-rejected';
        }

        return 'icon-default';
    }

    function createNotificationItem(item) {
        const button = document.createElement('button');

        const notificationId = Number(item.notification_id || 0);
        const isRead = Number(item.is_read || 0) === 1;
        const linkUrl = item.target_url || item.link_url || '';
        const titleText = item.title || 'Notification';
        const messageText = item.message || '';
        const typeKey = getNotificationTypeKey(item);

        button.type = 'button';
        button.className = 'dentist-notify-item' + (isRead ? '' : ' unread');
        button.dataset.notificationId = String(notificationId);
        button.dataset.linkUrl = linkUrl;
        button.dataset.isRead = isRead ? '1' : '0';

        const avatar = document.createElement('div');
        avatar.className = 'dentist-notify-avatar ' + getNotificationIconClass(typeKey);
        avatar.innerHTML = getNotificationIconSvg(typeKey);

        const avatarBadge = document.createElement('span');
        avatarBadge.className = 'dentist-notify-avatar-badge';
        avatarBadge.innerHTML = notificationBadgeSvg();
        avatar.appendChild(avatarBadge);

        const content = document.createElement('div');
        content.className = 'dentist-notify-content';

        const title = document.createElement('strong');
        title.textContent = titleText + ' ';

        const message = document.createElement('span');
        message.textContent = messageText;

        const time = document.createElement('small');
        time.textContent = formatTimeAgo(item.created_at);

        content.appendChild(title);
        content.appendChild(message);
        content.appendChild(time);

        const dot = document.createElement('span');
        dot.className = 'dentist-notify-dot';

        button.appendChild(avatar);
        button.appendChild(content);
        button.appendChild(dot);

        return button;
    }

    function filteredNotifications() {
        if (activeFilter === 'unread') {
            return latestNotifications.filter(function (item) {
                return Number(item.is_read || 0) === 0;
            });
        }

        return latestNotifications;
    }

    function renderNotifications() {
        const body = getElements().body;
        const footer = getElements().footer;

        if (!body) {
            return;
        }

        const notifications = filteredNotifications();

        body.innerHTML = '';

        if (footer) {
            footer.style.display = activeFilter === 'all' ? 'block' : 'none';
        }

        if (!Array.isArray(notifications) || notifications.length === 0) {
            showMessage(activeFilter === 'unread' ? 'No unread notifications.' : 'No notifications.');
            return;
        }

        notifications.forEach(function (item) {
            body.appendChild(createNotificationItem(item));
        });
    }

    function setActiveFilter(filter) {
        const elements = getElements();

        activeFilter = filter === 'unread' ? 'unread' : 'all';

        if (elements.tabAll) {
            elements.tabAll.classList.toggle('is-active', activeFilter === 'all');
        }

        if (elements.tabUnread) {
            elements.tabUnread.classList.toggle('is-active', activeFilter === 'unread');
        }

        renderNotifications();
    }

    async function loadDentistNotifications() {
        const elements = getElements();
        const config = getConfig();

        if (!elements.box || isLoading) {
            return;
        }

        isLoading = true;

        try {
            const response = await fetch(config.indexUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.status === 401 || response.status === 403) {
                stopPolling();
                showMessage('You are not allowed to view notifications.');
                return;
            }

            if (!response.ok) {
                showMessage('Unable to load notifications.');
                return;
            }

            const data = await response.json();

            latestNotifications = Array.isArray(data.notifications) ? data.notifications : [];

            updateBadge(data.unread_count || 0);
            renderNotifications();
        } catch (error) {
            console.error('Dentist notification load error:', error);
            showMessage('Notification request failed.');
        } finally {
            isLoading = false;
        }
    }

    async function markSingleNotificationRead(notificationId, fallbackLinkUrl) {
        const config = getConfig();

        if (!notificationId || notificationId <= 0) {
            if (fallbackLinkUrl) {
                window.location.href = fallbackLinkUrl;
            }
            return;
        }

        if (!config.csrfToken) {
            if (fallbackLinkUrl) {
                window.location.href = fallbackLinkUrl;
            }
            return;
        }

        const body = new URLSearchParams();
        body.append('_csrf_token', config.csrfToken);
        body.append('notification_id', String(notificationId));

        try {
            const response = await fetch(config.markOneReadUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            });

            if (response.ok) {
                const data = await response.json();

                if (data.success) {
                    updateBadge(data.unread_count || 0);

                    if (fallbackLinkUrl) {
                        window.location.href = fallbackLinkUrl;
                        return;
                    }
                }
            }
        } catch (error) {
            console.error('Single notification read error:', error);
        }

        if (fallbackLinkUrl) {
            window.location.href = fallbackLinkUrl;
        }
    }

    function markLocalRead(notificationId) {
        latestNotifications = latestNotifications.map(function (item) {
            if (Number(item.notification_id || 0) === Number(notificationId)) {
                item.is_read = 1;
            }

            return item;
        });

        renderNotifications();
    }

    function handleNotificationClick(item) {
        if (!item) {
            return;
        }

        const notificationId = Number(item.dataset.notificationId || 0);
        const linkUrl = item.dataset.linkUrl || '';

        item.classList.remove('unread');
        item.dataset.isRead = '1';

        markLocalRead(notificationId);
        markSingleNotificationRead(notificationId, linkUrl);
    }

    function bindEvents() {
        const elements = getElements();

        if (!elements.button || !elements.dropdown) {
            return;
        }

        elements.button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            toggleDropdown();
        });

        if (elements.tabAll) {
            elements.tabAll.addEventListener('click', function () {
                setActiveFilter('all');
            });
        }

        if (elements.tabUnread) {
            elements.tabUnread.addEventListener('click', function () {
                setActiveFilter('unread');
            });
        }

        document.addEventListener('click', function (event) {
            const current = getElements();

            if (!current.button || !current.dropdown) {
                return;
            }

            const clickedButton = current.button.contains(event.target);
            const clickedDropdown = current.dropdown.contains(event.target);
            const notificationItem = event.target.closest('.dentist-notify-item');

            if (notificationItem && clickedDropdown) {
                event.preventDefault();
                event.stopPropagation();
                handleNotificationClick(notificationItem);
                return;
            }

            if (!clickedButton && !clickedDropdown) {
                closeDropdown();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDropdown();
            }

            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            const activeItem = document.activeElement;

            if (activeItem && activeItem.classList.contains('dentist-notify-item')) {
                event.preventDefault();
                handleNotificationClick(activeItem);
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
                return;
            }

            loadDentistNotifications();
            startPolling();
        });
    }

    function startPolling() {
        if (pollingTimer !== null) {
            return;
        }

        pollingTimer = window.setInterval(function () {
            if (!document.hidden) {
                loadDentistNotifications();
            }
        }, POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (pollingTimer === null) {
            return;
        }

        window.clearInterval(pollingTimer);
        pollingTimer = null;
    }

    function initDentistNotifications() {
        const elements = getElements();

        if (!elements.box || !elements.button || !elements.dropdown || !elements.body || !elements.count) {
            return;
        }

        bindEvents();
        loadDentistNotifications();
        startPolling();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDentistNotifications);
    } else {
        initDentistNotifications();
    }
})();