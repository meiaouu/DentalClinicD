(function () {
    'use strict';

    if (window.__staffNotificationInitialized === true) {
        return;
    }

    window.__staffNotificationInitialized = true;

    const POLL_INTERVAL_MS = 5000;

    let pollingTimer = null;
    let isLoading = false;
    let latestNotifications = [];
    let activeFilter = 'all';

    function getElements() {
        return {
            box: document.querySelector('.staff-notify-box'),
            button: document.getElementById('staffNotifyButton'),
            count: document.getElementById('staffNotifyCount'),
            dropdown: document.getElementById('staffNotifyDropdown'),
            list: document.getElementById('staffNotifyList'),
            tabAll: document.getElementById('staffNotifyTabAll'),
            tabUnread: document.getElementById('staffNotifyTabUnread'),
            footer: document.getElementById('staffNotifyFooter')
        };
    }

    function getConfig() {
        const box = getElements().box;

        return {
            latestUrl: box ? box.dataset.latestUrl : '/DentalClinic/public/staff/notifications/latest',
            readUrl: box ? box.dataset.readUrl : '/DentalClinic/public/staff/notifications/read',
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

    function closeProfileDropdown() {
        const profileButton = document.getElementById('staffProfileButton');
        const profileDropdown = document.getElementById('staffProfileDropdown');

        if (!profileButton || !profileDropdown) {
            return;
        }

        profileDropdown.style.display = 'none';
        profileButton.classList.remove('is-open');
        profileButton.setAttribute('aria-expanded', 'false');
    }

    function closeSidebar() {
        const sidebarToggle = document.getElementById('staffSidebarToggle');

        document.body.classList.remove('staff-sidebar-open');

        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleDropdown() {
        closeProfileDropdown();
        closeSidebar();

        if (isDropdownOpen()) {
            closeDropdown();
            return;
        }

        openDropdown();
        loadLatestNotifications();
    }

    function updateUnreadBadge(count) {
        const countEl = getElements().count;

        if (!countEl) {
            return;
        }

        const unreadCount = Number(count || 0);

        countEl.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
        countEl.style.display = unreadCount > 0 ? 'inline-block' : 'none';
        countEl.dataset.unreadCount = String(unreadCount);
    }

    function showMessage(message) {
        const list = getElements().list;

        if (!list) {
            return;
        }

        list.innerHTML = '';

        const div = document.createElement('div');
        div.className = 'staff-notify-empty';
        div.textContent = message;

        list.appendChild(div);
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
            type.includes('guest') ||
            type.includes('appointment_request_created') ||
            type.includes('new_guest_request') ||
            title.includes('guest appointment request') ||
            message.includes('guest')
        ) {
            return 'new-request';
        }

        if (
            type.includes('patient_request') ||
            type.includes('new_patient_request') ||
            title.includes('patient appointment request') ||
            message.includes('patient request')
        ) {
            return 'patient-request';
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
            type.includes('updated') ||
            title.includes('rescheduled') ||
            title.includes('updated') ||
            message.includes('rescheduled') ||
            message.includes('updated')
        ) {
            return 'rescheduled';
        }

        if (
            type.includes('confirmed_today') ||
            type.includes('today') ||
            title.includes("today's appointments") ||
            title.includes('today appointment') ||
            message.includes('for today')
        ) {
            return 'confirmed-today';
        }

        if (
            type.includes('confirm') ||
            title.includes('confirmed') ||
            message.includes('confirmed')
        ) {
            return 'confirmed-today';
        }

        if (
            type.includes('checked_in') ||
            type.includes('check_in') ||
            title.includes('checked in') ||
            message.includes('checked in')
        ) {
            return 'checked-in';
        }

        if (
            type.includes('message') ||
            type.includes('chat') ||
            title.includes('message') ||
            title.includes('chat') ||
            message.includes('sent a new clinic message')
        ) {
            return 'message';
        }

        if (
            type.includes('availability') ||
            title.includes('availability') ||
            message.includes('availability')
        ) {
            return 'availability';
        }

        if (
            type.includes('reject') ||
            title.includes('rejected') ||
            message.includes('rejected')
        ) {
            return 'rejected';
        }

        if (
            title.includes('new appointment request') ||
            message.includes('submitted a request')
        ) {
            return 'new-request';
        }

        return 'default';
    }

    function getNotificationIconClass(typeKey) {
        if (typeKey === 'new-request') {
            return 'icon-new-request';
        }

        if (typeKey === 'patient-request') {
            return 'icon-patient-request';
        }

        if (typeKey === 'cancelled') {
            return 'icon-cancelled';
        }

        if (typeKey === 'rescheduled') {
            return 'icon-rescheduled';
        }

        if (typeKey === 'confirmed-today') {
            return 'icon-confirmed-today';
        }

        if (typeKey === 'checked-in') {
            return 'icon-checked-in';
        }

        if (typeKey === 'message') {
            return 'icon-message';
        }

        if (typeKey === 'availability') {
            return 'icon-availability';
        }

        if (typeKey === 'rejected') {
            return 'icon-rejected';
        }

        return 'icon-default';
    }

    function getNotificationIconSvg(typeKey) {
        if (typeKey === 'new-request') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>'
                + '<circle cx="9" cy="7" r="4"></circle>'
                + '<path d="M19 8v6"></path>'
                + '<path d="M22 11h-6"></path>'
                + '</svg>';
        }

        if (typeKey === 'patient-request') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>'
                + '<circle cx="12" cy="7" r="4"></circle>'
                + '<path d="M9 11l2 2 4-5"></path>'
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

        if (typeKey === 'confirmed-today') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<rect x="3" y="4" width="18" height="17" rx="3"></rect>'
                + '<path d="M8 2v4"></path>'
                + '<path d="M16 2v4"></path>'
                + '<path d="M3 9h18"></path>'
                + '<path d="M8.5 14.5l2.5 2.5 4.5-5"></path>'
                + '</svg>';
        }

        if (typeKey === 'checked-in') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<path d="M9 11l3 3L22 4"></path>'
                + '<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>'
                + '</svg>';
        }

        if (typeKey === 'message') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8z"></path>'
                + '<path d="M8 9h8"></path>'
                + '<path d="M8 13h5"></path>'
                + '</svg>';
        }

        if (typeKey === 'availability') {
            return ''
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">'
                + '<path d="M12 2v4"></path>'
                + '<path d="M12 18v4"></path>'
                + '<path d="M4.93 4.93l2.83 2.83"></path>'
                + '<path d="M16.24 16.24l2.83 2.83"></path>'
                + '<path d="M2 12h4"></path>'
                + '<path d="M18 12h4"></path>'
                + '<path d="M4.93 19.07l2.83-2.83"></path>'
                + '<path d="M16.24 7.76l2.83-2.83"></path>'
                + '<circle cx="12" cy="12" r="3"></circle>'
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

    function createNotificationItem(item) {
        const button = document.createElement('button');

        const notificationId = Number(item.notification_id || 0);
        const isRead = Number(item.is_read || 0) === 1;
        const linkUrl = item.link_url || item.target_url || '';
        const titleText = item.title || 'Notification';
        const messageText = item.message || '';
        const typeKey = getNotificationTypeKey(item);

        button.type = 'button';
        button.className = 'staff-notify-item' + (isRead ? '' : ' unread');
        button.dataset.notificationId = String(notificationId);
        button.dataset.linkUrl = linkUrl;
        button.dataset.isRead = isRead ? '1' : '0';

        const avatar = document.createElement('div');
        avatar.className = 'staff-notify-avatar ' + getNotificationIconClass(typeKey);
        avatar.innerHTML = getNotificationIconSvg(typeKey);

        const avatarBadge = document.createElement('span');
        avatarBadge.className = 'staff-notify-avatar-badge';
        avatarBadge.innerHTML = notificationBadgeSvg();
        avatar.appendChild(avatarBadge);

        const content = document.createElement('div');
        content.className = 'staff-notify-content';

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
        dot.className = 'staff-notify-dot';

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
        const elements = getElements();
        const list = elements.list;
        const footer = elements.footer;

        if (!list) {
            return;
        }

        const notifications = filteredNotifications();

        list.innerHTML = '';

        if (footer) {
            footer.style.display = activeFilter === 'all' ? 'block' : 'none';
        }

        if (!Array.isArray(notifications) || notifications.length === 0) {
            showMessage(activeFilter === 'unread' ? 'No unread notifications.' : 'No notifications.');
            return;
        }

        notifications.forEach(function (item) {
            list.appendChild(createNotificationItem(item));
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

    async function loadLatestNotifications() {
        const elements = getElements();
        const config = getConfig();

        if (!elements.box || isLoading) {
            return;
        }

        isLoading = true;

        try {
            const response = await fetch(config.latestUrl, {
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

            updateUnreadBadge(data.unread_count || 0);
            renderNotifications();
        } catch (error) {
            console.error('Staff notification load error:', error);
            showMessage('Notification request failed.');
        } finally {
            isLoading = false;
        }
    }

    async function markNotificationRead(notificationId, fallbackLinkUrl) {
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
            const response = await fetch(config.readUrl, {
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
                    updateUnreadBadge(data.unread_count || 0);

                    if (data.link_url) {
                        window.location.href = data.link_url;
                        return;
                    }
                }
            }
        } catch (error) {
            console.error('Staff notification read error:', error);
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
        markNotificationRead(notificationId, linkUrl);
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
            const notificationItem = event.target.closest('.staff-notify-item');

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

            if (activeItem && activeItem.classList.contains('staff-notify-item')) {
                event.preventDefault();
                handleNotificationClick(activeItem);
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
                return;
            }

            loadLatestNotifications();
            startPolling();
        });
    }

    function startPolling() {
        if (pollingTimer !== null) {
            return;
        }

        pollingTimer = window.setInterval(function () {
            if (!document.hidden) {
                loadLatestNotifications();
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

    function initStaffNotifications() {
        const elements = getElements();

        if (!elements.box || !elements.button || !elements.dropdown || !elements.list || !elements.count) {
            return;
        }

        bindEvents();
        loadLatestNotifications();
        startPolling();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStaffNotifications);
    } else {
        initStaffNotifications();
    }
})();