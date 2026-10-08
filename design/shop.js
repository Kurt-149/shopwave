let clickCount = 0;
let lastClickTime = 0;
const RATE_LIMIT = 10;
const RATE_WINDOW = 60000;

function saveRateLimitState() {
    sessionStorage.setItem('cartClickCount', clickCount.toString());
    sessionStorage.setItem('cartLastClickTime', lastClickTime.toString());
}

function loadRateLimitState() {
    clickCount = parseInt(sessionStorage.getItem('cartClickCount')) || 0;
    lastClickTime = parseInt(sessionStorage.getItem('cartLastClickTime')) || 0;
}

function updateRateLimitIndicator() {
    const now = Date.now();
    let indicator = document.getElementById('rateLimitIndicator');
    
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'rateLimitIndicator';
        indicator.className = 'rate-limit-indicator';
        document.body.appendChild(indicator);
    }
    
    if (now - lastClickTime > RATE_WINDOW) {
        clickCount = 0;
        saveRateLimitState();
    }
    
    const remainingClicks = Math.max(0, RATE_LIMIT - clickCount);
    const timeElapsed = now - lastClickTime;
    const timeRemaining = Math.max(0, RATE_WINDOW - timeElapsed);
    const percentElapsed = Math.min(100, (timeElapsed / RATE_WINDOW) * 100);
    
    if (clickCount >= RATE_LIMIT) {
        indicator.style.display = 'flex';
        indicator.className = 'rate-limit-indicator error';
        indicator.innerHTML = `<span>Too many attempts. Wait ${Math.ceil(timeRemaining/1000)}s</span>
                              <div class="rate-limit-progress">
                                  <div class="rate-limit-fill" style="width: ${percentElapsed}%;"></div>
                              </div>`;
    } else if (clickCount > RATE_LIMIT - 3) {
        indicator.style.display = 'flex';
        indicator.className = 'rate-limit-indicator warning';
        indicator.innerHTML = `<span>Slow down (${remainingClicks} left)</span>
                              <div class="rate-limit-progress">
                                  <div class="rate-limit-fill" style="width: ${percentElapsed}%;"></div>
                              </div>`;
    } else {
        indicator.style.display = 'none';
    }
}

function addToCart(productId, button) {
    loadRateLimitState();
    
    const now = Date.now();
    
    if (now - lastClickTime > RATE_WINDOW) {
        clickCount = 0;
        saveRateLimitState();
    }
    
    if (clickCount >= RATE_LIMIT) {
        const timeRemaining = Math.ceil((RATE_WINDOW - (now - lastClickTime)) / 1000);
        showToast(`Too many attempts. Please wait ${timeRemaining} seconds.`, 'error');
        updateRateLimitIndicator();
        return;
    }
    
    if (button.disabled) return;
    
    const stock = parseInt(button.dataset.stock || '0');
    if (stock <= 0) {
        showToast('This product is out of stock', 'error');
        return;
    }
    
    clickCount++;
    lastClickTime = now;
    saveRateLimitState();
    updateRateLimitIndicator();
    
    const originalHTML = button.innerHTML;
    const originalDisabled = button.disabled;
    
    button.disabled = true;
    button.innerHTML = 'Adding...';
    button.style.opacity = '0.6';
    
    const timeoutId = setTimeout(() => {
        button.disabled = false;
        button.innerHTML = originalHTML;
        button.style.opacity = '1';
        showToast('Request timed out. Please try again.', 'error');
    }, 10000);
    
    let backendPath = '../backend/add-to-cart.php';
    if (!window.location.pathname.includes('/public/')) {
        backendPath = 'backend/add-to-cart.php';
    }
    
    fetch(backendPath, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `product_id=${productId}&quantity=1`,
        credentials: 'same-origin'
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Product added to cart!', 'success');
            updateCartCount();
            
            document.querySelectorAll('.cart-count-badge, .mph-cart-badge, .badge.cart-count-badge').forEach(badge => {
                badge.textContent = data.cart_count;
                badge.style.display = data.cart_count > 0 ? 'flex' : 'none';
            });
        } else {
            if (data.login_required) {
                showToast('Please login to add items to cart', 'error');
                setTimeout(() => {
                    let loginPath = '../authentication/login-page.php';
                    if (!window.location.pathname.includes('/public/')) {
                        loginPath = 'authentication/login-page.php';
                    }
                    window.location.href = loginPath;
                }, 1500);
            } else {
                showToast(data.message || 'Failed to add to cart', 'error');
            }
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Error adding to cart:', error);
        showToast('An error occurred. Please try again.', 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalHTML;
        button.style.opacity = '1';
    });
}

function updateCartCount() {
    let backendPath = '../backend/get-count-cart.php';
    if (!window.location.pathname.includes('/public/')) {
        backendPath = 'backend/get-count-cart.php';
    }

    fetch(backendPath, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const count = data.cart_count;
            document.querySelectorAll('.cart-count-badge, .mph-cart-badge, .badge.cart-count-badge, .cart-badge').forEach(badge => {
                badge.textContent = count;
                badge.style.display = count === 0 ? 'none' : 'flex';
            });
        }
    })
    .catch(error => console.error('Error updating cart count:', error));
}

function toggleMobileSearch() {
    const mobileSearchBar = document.getElementById('mobileSearchBar');
    if (!mobileSearchBar) return;
    
    const isHidden = mobileSearchBar.style.display === 'none' || !mobileSearchBar.style.display;
    mobileSearchBar.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        mobileSearchBar.style.animation = 'slideDown 0.3s ease';
        const searchInput = mobileSearchBar.querySelector('input');
        if (searchInput) searchInput.focus();
    }
}

function toggleFilterPanel() {
    const filterPanel = document.getElementById('filterPanel');
    const filterBtn = document.getElementById('filterToggleBtn');
    if (!filterPanel || !filterBtn) return;
    
    const isOpen = filterPanel.classList.contains('open');
    filterPanel.classList.toggle('open', !isOpen);
    filterBtn.classList.toggle('open', !isOpen);
}

document.addEventListener('DOMContentLoaded', function() {
    loadRateLimitState();
    updateCartCount();
    setInterval(updateCartCount, 30000);

    const searchToggle = document.querySelector('.mobile-search-toggle');
    if (searchToggle) {
        searchToggle.addEventListener('click', toggleMobileSearch);
    }

    const filterBtn = document.getElementById('filterToggleBtn');
    if (filterBtn) {
        filterBtn.addEventListener('click', toggleFilterPanel);
    }

    document.addEventListener('click', function(event) {
        const filterPanel = document.getElementById('filterPanel');
        const filterBtn = document.getElementById('filterToggleBtn');
        
        if (filterPanel && filterBtn && filterPanel.classList.contains('open')) {
            if (!filterPanel.contains(event.target) && !filterBtn.contains(event.target)) {
                filterPanel.classList.remove('open');
                filterBtn.classList.remove('open');
            }
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.getElementById('filterPanel')?.classList.remove('open');
            document.getElementById('filterToggleBtn')?.classList.remove('open');
            document.getElementById('mobileSearchBar').style.display = 'none';
        }
    });
});

const style = document.createElement('style');
style.textContent = `
    .toast-notification {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #22c55e;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        font-weight: 600;
        font-size: 14px;
        animation: slideIn 0.3s ease;
        max-width: 320px;
    }
    .toast-notification.error {
        background: #ef4444;
    }
    .toast-notification.info {
        background: #3b82f6;
    }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .rate-limit-indicator {
        position: fixed;
        bottom: 24px;
        left: 24px;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 8px 16px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        z-index: 9998;
        display: none;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
    }
    .rate-limit-indicator.warning {
        background: rgba(245,158,11,0.9);
    }
    .rate-limit-indicator.error {
        background: rgba(239,68,68,0.9);
    }
    .rate-limit-progress {
        width: 60px;
        height: 4px;
        background: rgba(255,255,255,0.3);
        border-radius: 2px;
        overflow: hidden;
    }
    .rate-limit-fill {
        height: 100%;
        background: white;
        transition: width 1s linear;
    }
`;
document.head.appendChild(style);

window.showToast = window.showToast || function(message, type = 'success') {
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
};