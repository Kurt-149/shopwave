function addToCart(productId) {
    // First, try the actual add to cart - let the backend handle login check
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    
    button.disabled = true;
    button.innerHTML = 'Adding...';
    button.style.opacity = '0.6';
    
    fetch('../backend/add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}&quantity=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Product added to cart!');
            window.location.href = 'cart.php';
        } else {
            // If backend says not logged in, redirect to login
            if (data.message && data.message.includes('login')) {
                window.location.href = '../authentication/login-page.php';
            } else {
                alert(data.message || 'Failed to add to cart');
                button.disabled = false;
                button.innerHTML = originalText;
                button.style.opacity = '1';
            }
        }
    })
    .catch(error => {
        console.error('Error adding to cart:', error);
        alert('An error occurred. Please try again.');
        button.disabled = false;
        button.innerHTML = originalText;
        button.style.opacity = '1';
    });
}
function updateCartCount() {
    fetch('../backend/get-count-cart.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.cart_count > 0) {
                const cartIcon = document.querySelector('a[href="cart.php"]');
                if (cartIcon) {
                    let badge = cartIcon.querySelector('.cart-badge');
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'cart-badge';
                        cartIcon.style.position = 'relative';
                        cartIcon.appendChild(badge);
                    }
                    badge.textContent = data.cart_count;
                    badge.style.cssText = `
                        position: absolute;
                        top: -5px;
                        right: -5px;
                        background: #ef4444;
                        color: white;
                        border-radius: 50%;
                        width: 20px;
                        height: 20px;
                        font-size: 12px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: bold;
                    `;
                }
            }
        })
        .catch(error => {
            console.error('Error updating cart count:', error);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
});