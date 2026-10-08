let currentPages = {
    'to-ship': 1,
    'to-receive': 1,
    'completed': 1,
    'orders': 1,
    'reviews': 1,
    'notifications': 1
};

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getNotificationSvg(iconName) {
    const svgs = {
        'order': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 3H5L5.4 5M5.4 5H21L17 13H7L5.4 5ZM5.4 5L3 3M7 13L5 21M7 13H17M5 21H19M5 21L7 13M19 21C19.5304 21 20.0391 20.7893 20.4142 20.4142C20.7893 20.0391 21 19.5304 21 19C21 18.4696 20.7893 17.9609 20.4142 17.5858C20.0391 17.2107 19.5304 17 19 17C18.4696 17 17.9609 17.2107 17.5858 17.5858C17.2107 17.9609 17 18.4696 17 19C17 19.5304 17.2107 20.0391 17.5858 20.4142C17.9609 20.7893 18.4696 21 19 21ZM9 19C9 19.5304 8.78929 20.0391 8.41421 20.4142C8.03914 20.7893 7.53043 21 7 21C6.46957 21 5.96086 20.7893 5.58579 20.4142C5.21071 20.0391 5 19.5304 5 19C5 18.4696 5.21071 17.9609 5.58579 17.5858C5.96086 17.2107 6.46957 17 7 17C7.53043 17 8.03914 17.2107 8.41421 17.5858C8.78929 17.9609 9 18.4696 9 19Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
        'promo': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L15 9H22L16 14L19 21L12 16.5L5 21L8 14L2 9H9L12 2Z" fill="currentColor"/></svg>`,
        'review': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L15 9H22L16 14L19 21L12 16.5L5 21L8 14L2 9H9L12 2Z" fill="currentColor"/></svg>`,
        'alert': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 8V12M12 16H12.01M3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>`,
        'pending': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M12 6V12L16 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>`,
        'processing': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2V6M12 18V22M22 12H18M6 12H2M4.93 4.93L7.76 7.76M16.24 16.24L19.07 19.07M16.24 7.76L19.07 4.93M4.93 19.07L7.76 16.24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>`,
        'shipped': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 3H5L5.4 5M5.4 5H21L17 13H7L5.4 5ZM5.4 5L3 3M7 13L5 21M7 13H17M5 21H19M5 21L7 13M19 21C19.5304 21 20.0391 20.7893 20.4142 20.4142C20.7893 20.0391 21 19.5304 21 19C21 18.4696 20.7893 17.9609 20.4142 17.5858C20.0391 17.2107 19.5304 17 19 17C18.4696 17 17.9609 17.2107 17.5858 17.5858C17.2107 17.9609 17 18.4696 17 19C17 19.5304 17.2107 20.0391 17.5858 20.4142C17.9609 20.7893 18.4696 21 19 21ZM9 19C9 19.5304 8.78929 20.0391 8.41421 20.4142C8.03914 20.7893 7.53043 21 7 21C6.46957 21 5.96086 20.7893 5.58579 20.4142C5.21071 20.0391 5 19.5304 5 19C5 18.4696 5.21071 17.9609 5.58579 17.5858C5.96086 17.2107 6.46957 17 7 17C7.53043 17 8.03914 17.2107 8.41421 17.5858C8.78929 17.9609 9 18.4696 9 19Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
        'delivered': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.709 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><polyline points="22 4 12 14 9 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
        'cancelled': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/></svg>`
    };
    return svgs[iconName] || svgs['order'];
}

function showSection(sectionName) {
    document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
    const selected = document.getElementById(`section-${sectionName}`);
    if (selected) selected.classList.add('active');
    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
    const activeNav = document.querySelector(`[href="#${sectionName}"]`);
    if (activeNav) activeNav.classList.add('active');
    loadSectionData(sectionName);
    history.pushState(null, null, `#${sectionName}`);
}

function loadSectionData(sectionName) {
    switch (sectionName) {
        case 'to-ship': loadOrders('pending', 1); break;
        case 'to-receive': loadOrders('shipped', 1); break;
        case 'completed': loadOrders('delivered', 1); break;
        case 'orders': loadOrders('all', 1); break;
        case 'reviews': loadReviews(1); break;
        case 'notifications': loadNotifications(1); break;
    }
}

function loadOrders(status, page) {
    page = page || 1;
    const map = {
        pending: 'toShipOrders',
        shipped: 'toReceiveOrders',
        delivered: 'completedOrders',
        all: 'allOrders'
    };
    const container = document.getElementById(map[status]);
    if (!container) return;
    const section = status === 'all' ? 'orders' :
                    status === 'pending' ? 'to-ship' :
                    status === 'shipped' ? 'to-receive' : 'completed';
    currentPages[section] = page;
    container.innerHTML = '<div class="loading">Loading orders...</div>';
    fetch(`../backend/get-order.php?status=${status}&page=${page}`, { credentials: 'include' })
        .then(res => {
            if (!res.ok) return res.json().catch(() => null).then(b => { throw new Error(`HTTP ${res.status}: ${b?.message || res.statusText}`); });
            return res.json();
        })
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to load orders');
            let html = '';
            if (Array.isArray(data.orders) && data.orders.length > 0) {
                if (data.pagination) {
                    html += `<div class="section-count">Total ${getStatusLabel(status)}: ${data.pagination.total_orders}</div>`;
                }
                html += data.orders.map(createOrderCard).join('');
                if (data.pagination && data.pagination.total_pages > 1) {
                    html += createPagination(data.pagination, status, section);
                }
            } else {
                html = emptyState(`No ${getStatusLabel(status).toLowerCase()} found`);
            }
            container.innerHTML = html;
        })
        .catch(err => {
            console.error('Load orders error:', err);
            container.innerHTML = emptyState('Error loading orders. Please try again.');
        });
}

function createPagination(pagination, status, section) {
    const { current_page, total_pages } = pagination;
    if (total_pages <= 1) return '';
    let html = '<div class="pagination">';
    html += `<span class="pagination-info">Page ${current_page} of ${total_pages}</span>`;
    html += current_page > 1
        ? `<button class="pagination-btn" onclick="loadOrders('${status}', ${current_page - 1})">‹ Previous</button>`
        : '<button class="pagination-btn disabled" disabled>‹ Previous</button>';
    html += current_page < total_pages
        ? `<button class="pagination-btn" onclick="loadOrders('${status}', ${current_page + 1})">Next ›</button>`
        : '<button class="pagination-btn disabled" disabled>Next ›</button>';
    html += '</div>';
    return html;
}

function getStatusLabel(status) {
    return { pending: 'Pending Orders', shipped: 'Orders to Receive', delivered: 'Completed Orders', all: 'Orders' }[status] || 'Orders';
}

function createOrderCard(order) {
    const items = Array.isArray(order.items) ? order.items : [];
    const orderNumber = order.order_number || `ORDER-${order.id || 'UNKNOWN'}`;
    let itemsHtml = '';
    if (items.length > 0) {
        for (let i = 0; i < items.length; i++) {
            itemsHtml += createOrderItem(items[i]);
        }
    } else {
        itemsHtml = '<div class="empty-text">No items in this order</div>';
    }
    return `
        <div class="order-card">
            <div class="order-header">
                <div>
                    <div class="order-id">Order #${escapeHtml(orderNumber)}</div>
                    <div class="order-date">${formatDate(order.created_at)}</div>
                </div>
                <span class="order-status status-${order.status}">${capitalizeStatus(order.status)}</span>
            </div>
            <div class="order-items">
                ${itemsHtml}
            </div>
            <div class="order-footer">
                <div class="order-total">P${formatPrice(order.total_amount)}</div>
                <button class="btn-review" onclick="viewOrderDetails('${escapeHtml(orderNumber)}')">View Details</button>
            </div>
        </div>
    `;
}

function createOrderItem(item) {
    const variants = [item.selected_color, item.selected_size].filter(Boolean).join(' / ');
    const hasDiscount = item.original_price && parseFloat(item.original_price) > parseFloat(item.price);
    return `
        <div class="order-item">
            <div class="order-item-image">
                ${item.image_url
                    ? `<img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.product_name)}" onerror="this.style.display='none';this.parentElement.classList.add('no-image')">`
                    : getImagePlaceholder()}
            </div>
            <div class="order-item-details">
                <div class="order-item-name">${escapeHtml(item.product_name || 'Unknown Product')}</div>
                ${variants ? `<div class="order-item-variant-row"><span class="order-item-variant-label">Color:</span><div class="order-item-variant">${escapeHtml(variants)}</div></div>` : ''}
                <div class="order-item-qty">Quantity: ${parseInt(item.quantity) || 0}</div>
            </div>
            <div class="order-item-price">
                <span class="order-item-current">P${formatPrice(item.price)}</span>
                ${hasDiscount ? `<div class="order-item-original">P${formatPrice(item.original_price)}</div>` : ''}
            </div>
        </div>
    `;
}

function getImagePlaceholder() {
    return `<div class="no-image-placeholder"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#cbd5e1"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm40-80h480L570-480 450-320l-90-120-120 160Zm-40 80v-560 560Z"/></svg></div>`;
}

function loadReviews(page) {
    page = page || 1;
    const container = document.getElementById('reviewsList');
    if (!container) return;
    currentPages['reviews'] = page;
    container.innerHTML = '<div class="loading">Loading reviews...</div>';
    fetch(`../backend/get-user-review.php?page=${page}`, { credentials: 'include' })
        .then(res => {
            if (!res.ok) return res.json().catch(() => null).then(b => { throw new Error(`HTTP ${res.status}: ${b?.message || res.statusText}`); });
            return res.json();
        })
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to load reviews');
            let html = '';
            if (Array.isArray(data.reviews) && data.reviews.length > 0) {
                if (data.pagination) {
                    html += `<div class="section-count">Total Reviews: ${data.pagination.total_reviews}</div>`;
                }
                html += data.reviews.map(createReviewCard).join('');
                if (data.pagination && data.pagination.total_pages > 1) {
                    html += createReviewPagination(data.pagination);
                }
            } else {
                html = emptyState('No reviews yet. Complete an order to leave a review!');
            }
            container.innerHTML = html;
        })
        .catch(err => {
            console.error('Load reviews error:', err);
            container.innerHTML = emptyState('Error loading reviews. Please try again.');
        });
}

function createReviewPagination(pagination) {
    const { current_page, total_pages } = pagination;
    if (total_pages <= 1) return '';
    let html = '<div class="pagination">';
    html += `<span class="pagination-info">Page ${current_page} of ${total_pages}</span>`;
    html += current_page > 1
        ? `<button class="pagination-btn" onclick="loadReviews(${current_page - 1})">‹ Previous</button>`
        : '<button class="pagination-btn disabled" disabled>‹ Previous</button>';
    html += current_page < total_pages
        ? `<button class="pagination-btn" onclick="loadReviews(${current_page + 1})">Next ›</button>`
        : '<button class="pagination-btn disabled" disabled>Next ›</button>';
    html += '</div>';
    return html;
}

function createReviewCard(review) {
    const rating = parseInt(review.rating) || 0;
    const stars = Array.from({ length: 5 }, (_, i) =>
        `<span class="star ${i < rating ? 'filled' : ''}">★</span>`
    ).join('');
    let statusBadge = '';
    if (review.status === 'pending') statusBadge = '<span class="review-status-pending">Pending Approval</span>';
    if (review.status === 'rejected') statusBadge = '<span class="review-status-rejected">Rejected</span>';
    let verifiedBadge = '';
    if (review.verified_purchase) {
        verifiedBadge = '<span class="verified-badge" style="background: #10b98120; color: #10b981; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; margin-left: 8px;">✓ Purchased</span>';
    }
    return `
        <div class="review-card">
            <div class="review-header">
                <div class="review-rating">${stars}${statusBadge}</div>
                <div class="review-actions">
                    <div class="review-date">${formatDate(review.created_at)}</div>
                    <button class="btn-view-review" onclick="viewReviewOnProduct(${review.product_id}, ${review.id})">
                        <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                            <path d="M480-320q75 0 127.5-52.5T660-500q0-75-52.5-127.5T480-680q-75 0-127.5 52.5T300-500q0 75 52.5 127.5T480-320Zm0-72q-45 0-76.5-31.5T372-500q0-45 31.5-76.5T480-608q45 0 76.5 31.5T588-500q0 45-31.5 76.5T480-392Zm0 192q-146 0-266-81.5T40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200Z"/>
                        </svg>
                        View
                    </button>
                </div>
            </div>
            <div class="review-product-name">
                ${review.image_url ? `<img src="${escapeHtml(review.image_url)}" alt="${escapeHtml(review.product_name)}" class="review-product-image" onerror="this.style.display='none'">` : ''}
                <strong>${escapeHtml(review.product_name || 'Unknown Product')}</strong>
            </div>
            <div class="review-text">${escapeHtml(review.comment || 'No comment provided')}</div>
        </div>
    `;
}

function viewReviewOnProduct(productId, reviewId) {
    window.location.href = `product-details.php?id=${productId}#review-${reviewId}`;
}

function loadNotifications(page, perPage) {
    page = page || 1;
    perPage = perPage || 10;
    const container = document.getElementById('notificationsContent');
    if (!container) return;
    currentPages['notifications'] = page;
    container.innerHTML = '<div class="loading">Loading notifications...</div>';
    const _notifBase = (typeof window.APP_ROOT !== 'undefined' && window.APP_ROOT) ? window.APP_ROOT : window.location.origin;
    fetch(_notifBase + `/backend/get-notifications.php?page=${page}&per_page=${perPage}`, { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed');
            const notifs = data.notifications || [];
            const pg = data.pagination || null;
            if (!notifs.length) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">🔕</div><p class="empty-text">No notifications yet</p></div>';
                return;
            }
            let html = pg ? `<div class="section-count">Total Notifications: ${pg.total_notifications}</div>` : '';
            html += '<div class="notif-full-list">';
            notifs.forEach(function(n) {
                const isUnread = !n.is_read;
                const icon = n.icon || 'order';
                const iconColor = n.icon_color || '#6b7280';
                const statusLabel = n.status_label || null;
                const statusColor = n.status_color || null;
                const messageText = escapeHtml(n.message);
                const timeAgoText = n.time_ago || '';
                let orderNumber = null;
                const orderMatch = n.raw_message ? n.raw_message.match(/(ORD-\d{8}-[A-Z0-9]{8})/) : null;
                if (orderMatch) {
                    orderNumber = orderMatch[1];
                } else {
                    const genericMatch = n.message.match(/(ORD-\d{8}-[A-Z0-9]{8})/);
                    if (genericMatch) orderNumber = genericMatch[1];
                }
                let statusBadgeHtml = '';
                if (statusLabel) {
                    statusBadgeHtml = `<span class="notif-status-badge" style="background: ${statusColor}20; color: ${statusColor}; border: 1px solid ${statusColor}40; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin-left: 8px;">${escapeHtml(statusLabel)}</span>`;
                }
                html += `<div class="notif-full-item ${isUnread ? 'unread' : ''}" data-notification-id="${n.id}" data-notif-id="${n.id}" data-msg="${messageText.replace(/"/g, '&quot;')}" data-status="${escapeHtml(statusLabel || '').replace(/"/g, '&quot;')}" data-order="${escapeHtml(orderNumber || '').replace(/"/g, '&quot;')}" id="notification-${n.id}">
                    <div class="notif-full-icon" style="background: ${iconColor}15; color: ${iconColor};">${getNotificationSvg(icon)}</div>
                    <div class="notif-full-body">
                        <div class="notif-full-top-row">
                            <div class="notif-full-msg">${messageText}</div>
                            ${statusBadgeHtml ? `<div class="notif-full-badge-wrap">${statusBadgeHtml}</div>` : ''}
                        </div>
                        <div class="notif-full-time">${escapeHtml(timeAgoText)}</div>
                    </div>
                    <div class="notif-full-dot ${isUnread ? '' : 'read'}"></div>
                </div>`;
            });
            html += '</div>';
            if (pg && pg.total_pages > 1) {
                const cur = pg.current_page, tot = pg.total_pages;
                html += '<div class="pagination">'
                     + `<span class="pagination-info">Page ${cur} of ${tot}</span>`
                     + (cur > 1 ? `<button class="pagination-btn" onclick="loadNotifications(${cur - 1})">‹ Previous</button>`
                                : '<button class="pagination-btn disabled" disabled>‹ Previous</button>')
                     + (cur < tot ? `<button class="pagination-btn" onclick="loadNotifications(${cur + 1})">Next ›</button>`
                                  : '<button class="pagination-btn disabled" disabled>Next ›</button>')
                     + '</div>';
            }
            container.innerHTML = html;
            document.querySelectorAll('.notif-full-item').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const notifId = this.getAttribute('data-notification-id');
                    const message = this.getAttribute('data-msg');
                    const statusLabel = this.getAttribute('data-status');
                    const orderNumber = this.getAttribute('data-order');
                    if (notifId) {
                        openNotificationModal(parseInt(notifId), message, statusLabel, orderNumber);
                    }
                });
            });
        })
        .catch(function(err) {
            console.error('Load notifications error:', err);
            container.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><p class="empty-text">Error loading notifications. Please try again.</p></div>';
        });
}

function loadNotificationsWithCallback(page, perPage, callback) {
    page = page || 1;
    perPage = perPage || 100;
    const container = document.getElementById('notificationsContent');
    if (!container) {
        if (typeof callback === 'function') callback();
        return;
    }
    currentPages['notifications'] = page;
    container.innerHTML = '<div class="loading">Loading notifications...</div>';
    const backendBase = (typeof window.APP_ROOT !== 'undefined' && window.APP_ROOT) ? window.APP_ROOT : window.location.origin;
    fetch(backendBase + `/backend/get-notifications.php?page=${page}&per_page=${perPage}`, { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed');
            const notifs = data.notifications || [];
            const pg = data.pagination || null;
            if (!notifs.length) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">🔕</div><p class="empty-text">No notifications yet</p></div>';
                if (typeof callback === 'function') callback();
                return;
            }
            let html = pg ? `<div class="section-count">Total Notifications: ${pg.total_notifications}</div>` : '';
            html += '<div class="notif-full-list">';
            notifs.forEach(function(n) {
                const isUnread = !n.is_read;
                const icon = n.icon || 'order';
                const iconColor = n.icon_color || '#6b7280';
                const statusLabel = n.status_label || null;
                const statusColor = n.status_color || null;
                const messageText = escapeHtml(n.message);
                const timeAgoText = n.time_ago || '';
                let orderNumber = null;
                const orderMatch = n.raw_message ? n.raw_message.match(/(ORD-\d{8}-[A-Z0-9]{8})/) : null;
                if (orderMatch) {
                    orderNumber = orderMatch[1];
                } else {
                    const genericMatch = n.message.match(/(ORD-\d{8}-[A-Z0-9]{8})/);
                    if (genericMatch) orderNumber = genericMatch[1];
                }
                let statusBadgeHtml = '';
                if (statusLabel) {
                    statusBadgeHtml = `<span class="notif-status-badge" style="background: ${statusColor}20; color: ${statusColor}; border: 1px solid ${statusColor}40; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin-left: 8px;">${escapeHtml(statusLabel)}</span>`;
                }
                html += `<div class="notif-full-item ${isUnread ? 'unread' : ''}" data-notification-id="${n.id}" data-notif-id="${n.id}" data-msg="${messageText.replace(/"/g, '&quot;')}" data-status="${escapeHtml(statusLabel || '').replace(/"/g, '&quot;')}" data-order="${escapeHtml(orderNumber || '').replace(/"/g, '&quot;')}" id="notification-${n.id}">
                    <div class="notif-full-icon" style="background: ${iconColor}15; color: ${iconColor};">${getNotificationSvg(icon)}</div>
                    <div class="notif-full-body">
                        <div class="notif-full-top-row">
                            <div class="notif-full-msg">${messageText}</div>
                            ${statusBadgeHtml ? `<div class="notif-full-badge-wrap">${statusBadgeHtml}</div>` : ''}
                        </div>
                        <div class="notif-full-time">${escapeHtml(timeAgoText)}</div>
                    </div>
                    <div class="notif-full-dot ${isUnread ? '' : 'read'}"></div>
                </div>`;
            });
            html += '</div>';
            if (pg && pg.total_pages > 1) {
                const cur = pg.current_page, tot = pg.total_pages;
                html += '<div class="pagination">'
                     + `<span class="pagination-info">Page ${cur} of ${tot}</span>`
                     + (cur > 1 ? `<button class="pagination-btn" onclick="loadNotifications(${cur - 1})">‹ Previous</button>`
                                : '<button class="pagination-btn disabled" disabled>‹ Previous</button>')
                     + (cur < tot ? `<button class="pagination-btn" onclick="loadNotifications(${cur + 1})">Next ›</button>`
                                  : '<button class="pagination-btn disabled" disabled>Next ›</button>')
                     + '</div>';
            }
            container.innerHTML = html;
            document.querySelectorAll('.notif-full-item').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const notifId = this.getAttribute('data-notification-id');
                    const message = this.getAttribute('data-msg');
                    const statusLabel = this.getAttribute('data-status');
                    const orderNumber = this.getAttribute('data-order');
                    if (notifId) {
                        openNotificationModal(parseInt(notifId), message, statusLabel, orderNumber);
                    }
                });
            });
            if (typeof callback === 'function') callback();
        })
        .catch(function(err) {
            console.error('Load notifications error:', err);
            container.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><p class="empty-text">Error loading notifications. Please try again.</p></div>';
            if (typeof callback === 'function') callback();
        });
}

function showNotifReason(reason) {
    if (!reason) return;
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

function markNotifRead(id) {
    const backendBase = (typeof window.APP_ROOT !== 'undefined' && window.APP_ROOT) ? window.APP_ROOT : window.location.origin;
    fetch(backendBase + '/backend/mark-notifications-read.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    }).catch(() => {});
}

function openNotificationModal(notifId, message, statusLabel, orderNumber) {
    const el = document.getElementById('notification-' + notifId);
    if (el) {
        el.classList.remove('unread');
        const dot = el.querySelector('.notif-full-dot');
        if (dot) dot.classList.add('read');
    }
    markNotifRead(notifId);
    const stillUnread = document.querySelector('.notif-full-item.unread');
    if (!stillUnread) {
        document.querySelector('.notif-red-dot')?.remove();
        const navBadge = document.querySelector('a[href="#notifications"] .badge');
        if (navBadge) navBadge.remove();
    }
    document.querySelector('.notif-detail-modal')?.remove();
    let statusColor = '#64748b';
    if (statusLabel === 'Shipped') statusColor = '#8b5cf6';
    else if (statusLabel === 'Pending') statusColor = '#f59e0b';
    else if (statusLabel === 'Delivered') statusColor = '#10b981';
    else if (statusLabel === 'Completed') statusColor = '#10b981';
    else if (statusLabel === 'Processing') statusColor = '#3b82f6';
    else if (statusLabel === 'Cancelled') statusColor = '#ef4444';
    const modal = document.createElement('div');
    modal.className = 'notif-detail-modal';
    modal.innerHTML = `
        <div class="notif-detail-modal-content">
            <button class="notif-detail-modal-close" onclick="closeNotificationModal()">×</button>
            <div class="notif-detail-modal-body">
                <div class="notif-detail-message">${message || 'No message content'}</div>
                ${statusLabel ? `<div class="notif-detail-status"><span class="notif-status-badge" style="background:${statusColor}20;color:${statusColor};border:1px solid ${statusColor}40;padding:4px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;display:inline-block;">${escapeHtml(statusLabel)}</span></div>` : ''}
                ${orderNumber ? `<div class="notif-detail-order">Order #${escapeHtml(orderNumber)}</div>` : ''}
            </div>
            <div class="notif-detail-modal-footer">
                ${orderNumber ? `<button class="notif-detail-view-btn" onclick="viewOrderFromModal('${escapeHtml(orderNumber)}')">View Order</button>` : ''}
                <button class="notif-detail-close-btn" onclick="closeNotificationModal()">Close</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    modal.addEventListener('click', e => {
        if (e.target === modal) closeNotificationModal();
    });
}

function closeNotificationModal() {
    document.querySelector('.notif-detail-modal')?.remove();
    document.body.style.overflow = '';
}

function viewOrderFromModal(orderNumber) {
    closeNotificationModal();
    showSection('orders');
    function waitAndOpen() {
        const container = document.getElementById('allOrders');
        if (container && !container.innerHTML.includes('Loading')) {
            viewOrderDetails(orderNumber);
        } else {
            setTimeout(waitAndOpen, 300);
        }
    }
    waitAndOpen();
}

function handleNotificationClick(id, type, message) {
    markNotifRead(id);
    if (typeof closeNotifDropdown === 'function') closeNotifDropdown();
    showSection('notifications');
    function tryHighlight(attempts) {
        const el = document.getElementById('notification-' + id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('highlight');
            setTimeout(() => el.classList.remove('highlight'), 2000);
            const msg = el.getAttribute('data-msg') || '';
            const status = el.getAttribute('data-status') || '';
            const order = el.getAttribute('data-order') || '';
            openNotificationModal(id, msg, status, order);
        } else if (attempts > 0) {
            setTimeout(() => tryHighlight(attempts - 1), 200);
        }
    }
    setTimeout(() => tryHighlight(15), 300);
}

function viewOrderDetails(orderNumber) {
    if (!orderNumber) {
        alert('Invalid order number');
        return;
    }
    fetch(`../backend/get-order-details.php?order_number=${encodeURIComponent(orderNumber)}`, { credentials: 'include' })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            if (data.success && data.order) {
                showOrderDetailsModal(data.order);
            } else {
                alert(data.message || 'Order not found');
            }
        })
        .catch(err => {
            console.error('Error loading order details:', err);
            alert('Error loading order details. Please try again.');
        });
}

function showOrderDetailsModal(order) {
    document.querySelector('.order-modal')?.remove();
    const items = Array.isArray(order.items) && order.items.length > 0 ? order.items : [];
    let itemsHtml = '';
    if (items.length > 0) {
        itemsHtml = items.map(item => {
            const hasDiscount = item.original_price && parseFloat(item.original_price) > parseFloat(item.price);
            const discountPct = hasDiscount ? Math.round(((item.original_price - item.price) / item.original_price) * 100) : 0;
            const variants = [item.selected_color, item.selected_size].filter(Boolean).join(' / ');
            return `
                <div class="modal-order-item">
                    <div class="modal-item-image">
                        ${item.image_url ? `<img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.product_name)}">` : getImagePlaceholder()}
                    </div>
                    <div class="modal-item-details">
                        <div class="modal-item-name">${escapeHtml(item.product_name || 'Unknown Product')}</div>
                        ${variants ? `<div class="order-item-variant">${escapeHtml(variants)}</div>` : ''}
                        <div class="modal-item-qty">Quantity: ${parseInt(item.quantity) || 0}</div>
                        <div class="modal-item-pricing">
                            <span class="modal-price-current">P${formatPrice(item.price)}</span>
                            ${hasDiscount ? `<span class="modal-price-original">P${formatPrice(item.original_price)}</span><span class="modal-discount-badge">-${discountPct}%</span>` : ''}
                        </div>
                    </div>
                    <div class="modal-item-subtotal">P${formatPrice(item.subtotal)}</div>
                </div>
            `;
        }).join('');
    } else {
        itemsHtml = '<div class="empty-text">No items in this order</div>';
    }
    const shippingFee = parseFloat(order.shipping_fee) || 0;
    const subtotal = parseFloat(order.subtotal) || 0;
    const tax = parseFloat(order.tax_amount) || 0;
    const total = parseFloat(order.total_amount) || 0;
    const modal = document.createElement('div');
    modal.className = 'order-modal';
    modal.innerHTML = `
        <div class="order-modal-content">
            <div class="order-modal-header">
                <div>
                    <h2>Order Details</h2>
                    <p class="modal-order-number">#${escapeHtml(order.order_number)}</p>
                </div>
                <button class="order-modal-close" id="modalCloseBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                        <path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/>
                    </svg>
                </button>
            </div>
            <div class="order-modal-body">
                <div class="modal-meta-row">
                    <div class="modal-meta-item">
                        <span class="modal-meta-label">Date</span>
                        <span class="modal-meta-value">${formatDate(order.created_at)}</span>
                    </div>
                    <div class="modal-meta-item">
                        <span class="modal-meta-label">Status</span>
                        <span class="order-status status-${order.status}">${capitalizeStatus(order.status)}</span>
                    </div>
                    <div class="modal-meta-item">
                        <span class="modal-meta-label">Payment</span>
                        <span class="modal-meta-value">${order.payment_method ? order.payment_method.toUpperCase() : 'N/A'}</span>
                    </div>
                    <div class="modal-meta-item">
                        <span class="modal-meta-label">Pay Status</span>
                        <span class="modal-meta-value">${capitalizeStatus(order.payment_status || 'pending')}</span>
                    </div>
                </div>
                <div class="modal-section">
                    <h3>Shipping Information</h3>
                    <div class="modal-shipping-grid">
                        <div><span class="modal-meta-label">Name</span><span>${escapeHtml(order.shipping_name || 'N/A')}</span></div>
                        <div><span class="modal-meta-label">Phone</span><span>${escapeHtml(order.shipping_phone || 'N/A')}</span></div>
                        <div><span class="modal-meta-label">Email</span><span>${escapeHtml(order.shipping_email || 'N/A')}</span></div>
                        <div><span class="modal-meta-label">Address</span><span>${escapeHtml(order.shipping_address || 'N/A')}, ${escapeHtml(order.shipping_city || 'N/A')} ${escapeHtml(order.shipping_postal || '')}</span></div>
                    </div>
                </div>
                <div class="modal-section">
                    <h3>Items Ordered</h3>
                    <div class="modal-items-list">${itemsHtml}</div>
                </div>
                <div class="modal-price-breakdown">
                    <div class="modal-price-row">
                        <span>Subtotal</span>
                        <span>P${formatPrice(subtotal)}</span>
                    </div>
                    <div class="modal-price-row">
                        <span>Shipping Fee</span>
                        ${shippingFee === 0 ? '<span class="modal-free-ship">FREE</span>' : `<span>P${formatPrice(shippingFee)}</span>`}
                    </div>
                    <div class="modal-price-row">
                        <span>Tax (12% VAT)</span>
                        <span>P${formatPrice(tax)}</span>
                    </div>
                    <div class="modal-price-divider"></div>
                    <div class="modal-price-row modal-price-total">
                        <span>Total</span>
                        <span>P${formatPrice(total)}</span>
                    </div>
                </div>
                ${order.notes ? `<div class="modal-section"><h3>Order Notes</h3><p class="modal-notes">${escapeHtml(order.notes)}</p></div>` : ''}
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    modal.querySelector('#modalCloseBtn').addEventListener('click', () => {
        modal.remove();
        document.body.style.overflow = '';
    });
    modal.addEventListener('click', e => {
        if (e.target === modal) {
            modal.remove();
            document.body.style.overflow = '';
        }
    });
}

function confirmDeleteAccount() {
    if (!confirm('Are you absolutely sure you want to delete your account?\n\nThis action CANNOT be undone!\n\nAll your orders, reviews, and personal data will be PERMANENTLY deleted.')) return;
    if (prompt('Type "DELETE" in ALL CAPS to confirm account deletion:') !== 'DELETE') { alert('Account deletion cancelled'); return; }
    const password = prompt('Enter your password to confirm:');
    if (!password) { alert('Password required to delete account'); return; }
    const deleteBtn = document.querySelector('.danger-zone .btn-danger');
    if (deleteBtn) { deleteBtn.disabled = true; deleteBtn.textContent = 'Deleting...'; }
    fetch('../backend/delete-account.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'password=' + encodeURIComponent(password)
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert('Your account has been deleted. You will now be logged out.'); window.location.href = '../index.php'; }
            else { alert(data.message || 'Failed to delete account'); if (deleteBtn) { deleteBtn.disabled = false; deleteBtn.textContent = 'Delete Account'; } }
        })
        .catch(() => { alert('An error occurred'); if (deleteBtn) { deleteBtn.disabled = false; deleteBtn.textContent = 'Delete Account'; } });
}

function formatDate(dateString) {
    if (!dateString) return 'Unknown date';
    const date = new Date(dateString);
    return isNaN(date.getTime()) ? 'Invalid date' : date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatPrice(price) {
    const num = parseFloat(price);
    return isNaN(num) ? '0.00' : num.toFixed(2);
}

function capitalizeStatus(status) {
    if (!status) return 'Unknown';
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function emptyState(text) {
    return `<div class="empty-state"><div class="empty-icon">📦</div><p class="empty-text">${escapeHtml(text)}</p></div>`;
}

function openProfileImageLightbox() {
    const avatarImg = document.querySelector('.user-avatar img');
    if (!avatarImg) {
        alert('No profile image to display');
        return;
    }
    const lightbox = document.getElementById('profileImageLightbox');
    const lightboxImg = document.getElementById('profileLightboxImage');
    lightboxImg.src = avatarImg.src;
    lightbox.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeProfileImageLightbox() {
    const lightbox = document.getElementById('profileImageLightbox');
    lightbox.classList.remove('show');
    document.body.style.overflow = '';
}

function openEditProfileImageLightbox() {
    const previewImg = document.getElementById('profilePicturePreview');
    if (!previewImg || previewImg.tagName !== 'IMG') {
        alert('No profile image to display');
        return;
    }
    const lightbox = document.getElementById('profileImageLightbox');
    const lightboxImg = document.getElementById('profileLightboxImage');
    lightboxImg.src = previewImg.src;
    lightbox.classList.add('show');
    document.body.style.overflow = 'hidden';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProfileImageLightbox();
        closeNotificationModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const editForm = document.getElementById('editProfileForm');
    if (editForm) {
        const profilePictureInput = document.getElementById('profile_picture');
        const profilePicturePreview = document.getElementById('profilePicturePreview');
        if (profilePictureInput && profilePicturePreview) {
            profilePictureInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                if (!file.type.match('image.*')) { alert('Please select an image file'); this.value = ''; return; }
                if (file.size > 5 * 1024 * 1024) { alert('File size must be less than 5MB'); this.value = ''; return; }
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (profilePicturePreview.tagName === 'IMG') {
                        profilePicturePreview.src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Profile Picture';
                        img.id = 'profilePicturePreview';
                        profilePicturePreview.parentNode.replaceChild(img, profilePicturePreview);
                    }
                };
                reader.readAsDataURL(file);
            });
        }
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const newPassword = formData.get('new_password');
            const confirmPassword = formData.get('confirm_password');
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return;
            }
            if (newPassword && newPassword.length < 8) {
                alert('New password must be at least 8 characters');
                return;
            }
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';
            fetch('../backend/update-profile.php', { method: 'POST', credentials: 'include', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { alert('Profile updated successfully!'); location.reload(); }
                    else { alert(data.message || 'Failed to update profile'); submitBtn.disabled = false; submitBtn.textContent = originalText; }
                })
                .catch(() => { alert('An error occurred'); submitBtn.disabled = false; submitBtn.textContent = originalText; });
        });
    }
    const searchToggle = document.querySelector('.mobile-search-toggle');
    const mobileSearchBar = document.getElementById('mobileSearchBar');
    if (searchToggle && mobileSearchBar) {
        searchToggle.addEventListener('click', function() {
            const hidden = !mobileSearchBar.style.display || mobileSearchBar.style.display === 'none';
            mobileSearchBar.style.display = hidden ? 'block' : 'none';
            if (hidden) {
                mobileSearchBar.style.animation = 'slideDown 0.3s ease';
                const inp = mobileSearchBar.querySelector('input');
                if (inp) inp.focus();
            }
        });
    }
    const valid = ['profile', 'edit-profile', 'to-ship', 'to-receive', 'completed', 'orders', 'reviews', 'notifications', 'settings'];
    let hash = window.location.hash.replace('#', '');
    if (hash.startsWith('notif-') || hash.startsWith('notification-')) {
        const notifId = hash.replace('notif-', '').replace('notification-', '');
        document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
        const sec = document.getElementById('section-notifications');
        if (sec) sec.classList.add('active');
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        const nav = document.querySelector('[href="#notifications"]');
        if (nav) nav.classList.add('active');
        if (window.location.hash !== '#notifications') {
            history.pushState(null, null, '#notifications');
        }
        loadNotificationsWithCallback(1, 100, function() {
            const card = document.getElementById('notification-' + notifId);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.style.transition = 'background 0.5s';
                card.style.background = '#f0f9ff';
                setTimeout(function() {
                    card.style.background = '';
                    const msg = card.getAttribute('data-msg') || '';
                    const status = card.getAttribute('data-status') || '';
                    const order = card.getAttribute('data-order') || '';
                    openNotificationModal(parseInt(notifId), msg, status, order);
                }, 400);
            }
        });
    } else if (hash.startsWith('order-')) {
        const orderNumber = hash.replace('order-', '');
        showSection('orders');
        function waitForOrdersAndShowModal() {
            const ordersContainer = document.getElementById('allOrders');
            if (ordersContainer && !ordersContainer.innerHTML.includes('Loading')) {
                viewOrderDetails(orderNumber);
            } else {
                setTimeout(waitForOrdersAndShowModal, 300);
            }
        }
        setTimeout(waitForOrdersAndShowModal, 500);
    } else if (valid.indexOf(hash) !== -1) {
        showSection(hash);
    } else {
        showSection('profile');
    }
});