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

document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
});

setInterval(updateCartCount, 30000);