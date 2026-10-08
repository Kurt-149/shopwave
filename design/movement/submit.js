/**
 * Quantity Management
 */
function increaseQty() {
    const input = document.getElementById('quantity');
    if (!input) return;
    
    const max = parseInt(input.getAttribute('max')) || 999;
    const current = parseInt(input.value) || 1;
    
    if (current < max) {
        input.value = current + 1;
    } else {
        showToast('Maximum stock reached: ' + max, 'error');
    }
}

function decreaseQty() {
    const input = document.getElementById('quantity');
    if (!input) return;
    
    const min = parseInt(input.getAttribute('min')) || 1;
    const current = parseInt(input.value) || 1;
    
    if (current > min) {
        input.value = current - 1;
    }
}

function validateQty() {
    const input = document.getElementById('quantity');
    if (!input) return;
    
    const min = parseInt(input.getAttribute('min')) || 1;
    const max = parseInt(input.getAttribute('max')) || 999;
    let value = parseInt(input.value);
    
    if (isNaN(value) || value < min) {
        input.value = min;
    } else if (value > max) {
        input.value = max;
        showToast('Maximum stock available: ' + max, 'error');
    }
}

/**
 * Cart Functions
 */
function addToCart(productId, btnEl) {
    const isLoggedIn = document.querySelector('.actions a[href="cart.php"]') !== null;
    
    if (!isLoggedIn) {
        showToast('Please login to add items to your cart', 'error');
        setTimeout(() => {
            window.location.href = '../authentication/login-page.php';
        }, 1500);
        return;
    }

    const quantityInput = document.getElementById('quantity');
    if (!quantityInput) {
        showToast('Quantity input not found', 'error');
        return;
    }

    const quantity = parseInt(quantityInput.value);
    const max = parseInt(quantityInput.getAttribute('max'));

    if (isNaN(quantity) || quantity < 1) {
        showToast('Please enter a valid quantity', 'error');
        return;
    }
    
    if (quantity > max) {
        showToast('Maximum stock available: ' + max, 'error');
        return;
    }

    const selectedColor = document.querySelector('.color-btn.selected')?.dataset.value || '';
    const selectedSize = document.querySelector('.size-btn.selected')?.dataset.value || '';

    const addButton = btnEl || document.querySelector('.btn-add-cart, .add-to-cart-btn');
    const originalHTML = addButton ? addButton.innerHTML : '';
    
    if (addButton) {
        addButton.disabled = true;
        addButton.innerHTML = 'Adding...';
        addButton.style.opacity = '0.6';
    }

    // Check if this is from shop page (no quantity input)
    const isShopPage = !document.getElementById('quantity');
    
    const body = isShopPage 
        ? `product_id=${productId}&quantity=1`
        : `product_id=${productId}&quantity=${quantity}&selected_color=${encodeURIComponent(selectedColor)}&selected_size=${encodeURIComponent(selectedSize)}`;

    fetch('../backend/add-to-cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Product added to cart!', 'success');
            updateCartCount();
            
            if (isShopPage) {
                setTimeout(() => {
                    window.location.href = 'cart.php';
                }, 1000);
            }
        } else {
            showToast(data.message || 'Failed to add to cart', 'error');
            if (addButton) {
                addButton.disabled = false;
                addButton.innerHTML = originalHTML;
                addButton.style.opacity = '1';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while adding to cart', 'error');
        if (addButton) {
            addButton.disabled = false;
            addButton.innerHTML = originalHTML;
            addButton.style.opacity = '1';
        }
    });
}

function buyNow(productId) {
    const isLoggedIn = document.querySelector('.actions a[href="cart.php"]') !== null;
    
    if (!isLoggedIn) {
        showToast('Please login to continue', 'error');
        setTimeout(() => {
            window.location.href = '../authentication/login-page.php';
        }, 1500);
        return;
    }

    const quantityInput = document.getElementById('quantity');
    if (!quantityInput) {
        showToast('Quantity input not found', 'error');
        return;
    }

    const quantity = parseInt(quantityInput.value);
    const max = parseInt(quantityInput.getAttribute('max'));

    if (isNaN(quantity) || quantity < 1) {
        showToast('Please enter a valid quantity', 'error');
        return;
    }
    
    if (quantity > max) {
        showToast('Maximum stock available: ' + max, 'error');
        return;
    }

    const selectedColor = document.querySelector('.color-btn.selected')?.dataset.value || '';
    const selectedSize = document.querySelector('.size-btn.selected')?.dataset.value || '';

    window.location.href = `checkout.php?product_id=${productId}&quantity=${quantity}&selected_color=${encodeURIComponent(selectedColor)}&selected_size=${encodeURIComponent(selectedSize)}`;
}

document.addEventListener('DOMContentLoaded', function() {
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('.submit-review-btn') || this.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            
            const originalText = submitBtn.textContent;
            const comment = formData.get('comment');
            const rating = formData.get('rating');

            if (!comment || comment.trim().length < 10) {
                showToast('Please write a review of at least 10 characters', 'error');
                return;
            }
            
            if (comment.length > 1000) {
                showToast('Review must not exceed 1000 characters', 'error');
                return;
            }
            
            if (!rating) {
                showToast('Please select a rating', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';

            fetch('../backend/submit-review.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('Review submitted successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Failed to submit review', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while submitting your review', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }
});