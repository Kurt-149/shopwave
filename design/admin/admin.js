document.addEventListener('DOMContentLoaded', function() {
    addNavLabels();
    setActiveNavItem();
    initNavigationHandlers();
    autoDismissAlerts();
});
function addNavLabels() {
    const navItems = document.querySelectorAll('.admin-nav-item');
    navItems.forEach(item => {
        const labelEl = item.querySelector('span:not(.admin-nav-icon)');
        if (labelEl) {
            item.setAttribute('data-label', labelEl.textContent.trim());
        }
    });
}
function setActiveNavItem() {
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    const navItems = document.querySelectorAll('.admin-nav-item');
    navItems.forEach(item => {
        item.classList.remove('active');
        const href = item.getAttribute('href');
        if (href) {
            const hrefPage = href.split('/').pop().split('?')[0];
            if (hrefPage === currentPage) {
                item.classList.add('active');
                // Scroll active item into view in the bottom nav on mobile
                if (window.innerWidth <= 768) {
                    setTimeout(() => {
                        item.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }, 100);
                }
            }
        }
    });
}
function initNavigationHandlers() {
    const navItems = document.querySelectorAll('.admin-nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
        });
    });
}
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}
function autoDismissAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}
function generatePagination(currentPage, totalItems, itemsPerPage, baseUrl) {
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    if (totalPages <= 1) return '';
    let html = '<div class="pagination">';
    html += `<span class="pagination-info">Page ${currentPage} of ${totalPages}</span>`;
    if (currentPage > 1) {
        html += `<a href="${baseUrl}page=${currentPage - 1}" class="pagination-btn">‹ Prev</a>`;
    } else {
        html += `<span class="pagination-btn disabled">‹ Prev</span>`;
    }
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    if (startPage > 1) {
        html += `<a href="${baseUrl}page=1" class="pagination-btn">1</a>`;
        if (startPage > 2) html += `<span class="pagination-btn disabled">...</span>`;
    }
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            html += `<span class="pagination-btn active">${i}</span>`;
        } else {
            html += `<a href="${baseUrl}page=${i}" class="pagination-btn">${i}</a>`;
        }
    }
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span class="pagination-btn disabled">...</span>`;
        html += `<a href="${baseUrl}page=${totalPages}" class="pagination-btn">${totalPages}</a>`;
    }
    if (currentPage < totalPages) {
        html += `<a href="${baseUrl}page=${currentPage + 1}" class="pagination-btn">Next ›</a>`;
    } else {
        html += `<span class="pagination-btn disabled">Next ›</span>`;
    }
    html += '</div>';
    return html;
}
function buildUrl(params) {
    const url = new URL(window.location.href);
    Object.keys(params).forEach(key => {
        if (params[key]) {
            url.searchParams.set(key, params[key]);
        } else {
            url.searchParams.delete(key);
        }
    });
    return url.toString();
}