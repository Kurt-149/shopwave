let notificationPages = {
    currentPage: 1
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Resolves backend URL regardless of which page this script is on.
// APP_ROOT is set by PHP on me-page.php: window.APP_ROOT = '<?php echo $root; ?>';
function _backendUrl(file) {
    const base = (typeof window.APP_ROOT !== 'undefined' && window.APP_ROOT)
        ? window.APP_ROOT
        : window.location.origin;
    return base + '/backend/' + file;
}

// ─── Bell dropdown toggle ─────────────────────────────────────────────────────

function toggleNotifDropdown(e) {
    e.preventDefault();
    e.stopPropagation();
    const dd = document.getElementById('notifDropdown');
    if (!dd) return;

    const isOpen = dd.classList.contains('open');
    dd.classList.toggle('open', !isOpen);

    if (!isOpen) {
        setTimeout(() => document.addEventListener('click', closeOnOutside), 10);
    } else {
        document.removeEventListener('click', closeOnOutside);
    }
}

function closeOnOutside(e) {
    const dd  = document.getElementById('notifDropdown');
    const btn = document.getElementById('notifBellBtn');
    if (!dd || !btn) return;
    if (!dd.contains(e.target) && !btn.contains(e.target)) {
        closeNotifDropdown();
    }
}

function closeNotifDropdown() {
    const dd = document.getElementById('notifDropdown');
    if (dd) dd.classList.remove('open');
    document.removeEventListener('click', closeOnOutside);
}

// ─── Mark read ────────────────────────────────────────────────────────────────

function markAllRead() {
    fetch(_backendUrl('mark-notifications-read.php'), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notif-dd-item.unread, .notif-full-item.unread').forEach(el => {
                el.classList.remove('unread');
                const dot = el.querySelector('.notif-dd-dot, .notif-full-dot');
                if (dot) dot.classList.add('read');
            });
            document.querySelector('.notif-red-dot')?.remove();
            document.querySelector('a[href="#notifications"] .badge')?.remove();

            if (typeof showToast === 'function') showToast('All notifications marked as read', 'success');

            const sec = document.getElementById('section-notifications');
            if (sec && sec.classList.contains('active') && typeof loadNotifications === 'function') {
                loadNotifications(1);
            }
        }
    })
    .catch(() => {});
}

function markNotifRead(id) {
    fetch(_backendUrl('mark-notifications-read.php'), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    }).catch(() => {});
}

// ─── Navigation to me-page notifications ─────────────────────────────────────

function handleNotificationClick(id) {
    markNotifRead(id);
    closeNotifDropdown();

    const base = (typeof window.APP_ROOT !== 'undefined' && window.APP_ROOT)
        ? window.APP_ROOT
        : window.location.origin;

    window.location.href = base + '/public/me-page.php#notif-' + id;
}

// ─── "View Reason" modal ──────────────────────────────────────────────────────

function showNotifReason(reason) {
    document.querySelector('.notif-reason-modal')?.remove();
    const modal = document.createElement('div');
    modal.className = 'notif-reason-modal';
    modal.innerHTML = `
        <div class="notif-reason-content">
            <div class="notif-reason-header">
                <span>Message from Admin</span>
                <button onclick="this.closest('.notif-reason-modal').remove(); document.body.style.overflow='';">✕</button>
            </div>
            <p class="notif-reason-text">${escapeHtml(reason)}</p>
        </div>`;
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    modal.addEventListener('click', e => {
        if (e.target === modal) { modal.remove(); document.body.style.overflow = ''; }
    });
}

// ─── Highlight a card in the full notifications list ──────────────────────────

function highlightNotification(id) {
    const el = document.getElementById('notification-' + id);
    if (!el) return;
    el.classList.add('highlight');
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => el.classList.remove('highlight'), 2000);
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    // Bell button
    const notifBtn = document.getElementById('notifBellBtn');
    if (notifBtn) {
        notifBtn.addEventListener('click', toggleNotifDropdown);
    }

    // Escape key closes dropdown
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeNotifDropdown();
    });

    // ── Event delegation for dropdown items ──
    // Handles clicks on .notif-dd-item and .notif-reason-btn safely,
    // without any inline onclick attributes that break on special characters.
    const dropdown = document.getElementById('notifDropdown');
    if (dropdown) {
        dropdown.addEventListener('click', function (e) {
            // "View Reason" button
            const reasonBtn = e.target.closest('.notif-reason-btn');
            if (reasonBtn) {
                e.stopPropagation();
                const item = reasonBtn.closest('.notif-dd-item');
                const note = item ? item.getAttribute('data-notif-note') : '';
                if (note) showNotifReason(note);
                return;
            }

            // Notification row click → navigate to me-page
            const item = e.target.closest('.notif-dd-item');
            if (item) {
                const id = item.getAttribute('data-notif-id');
                if (id) handleNotificationClick(parseInt(id, 10));
            }
        });
    }
});