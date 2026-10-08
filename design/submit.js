function increaseQty() {
    const input = document.getElementById('quantity');
    if (!input) return;
    const max = parseInt(input.getAttribute('max')) || 999;
    const current = parseInt(input.value) || 1;
    if (current < max) {
        input.value = current + 1;
    } else {
        alert('Maximum stock reached: ' + max);
    }
}

function decreaseQty() {
    const input = document.getElementById('quantity');
    if (!input) return;
    const min = parseInt(input.getAttribute('min')) || 1;
    const current = parseInt(input.value) || 1;
    if (current > min) input.value = current - 1;
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
        alert('Maximum stock available: ' + max);
    }
}

function addToCart(productId, btnEl) {
    const quantityInput = document.getElementById('quantity');
    if (!quantityInput) { alert('Quantity input not found'); return; }

    const quantity = parseInt(quantityInput.value);
    const max = parseInt(quantityInput.getAttribute('max'));

    if (isNaN(quantity) || quantity < 1) { alert('Please enter a valid quantity'); return; }
    if (quantity > max) { alert('Maximum stock available: ' + max); return; }

    const selectedColor = document.querySelector('.color-btn.selected')?.dataset.value || '';
    const selectedSize  = document.querySelector('.size-btn.selected')?.dataset.value  || '';

    const addButton = btnEl || document.querySelector('.btn-add-cart');
    const originalHTML = addButton ? addButton.innerHTML : '';
    if (addButton) { addButton.disabled = true; addButton.innerHTML = 'Adding...'; }

    fetch('../backend/add-to-cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}&quantity=${quantity}&selected_color=${encodeURIComponent(selectedColor)}&selected_size=${encodeURIComponent(selectedSize)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Product added to cart!');
            if (typeof updateCartCount === 'function') updateCartCount();
        } else {
            alert(data.message || 'Failed to add to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding to cart');
    })
    .finally(() => {
        if (addButton) { addButton.disabled = false; addButton.innerHTML = originalHTML; }
    });
}

function buyNow(productId) {
    const quantityInput = document.getElementById('quantity');
    if (!quantityInput) { alert('Quantity input not found'); return; }

    const quantity = parseInt(quantityInput.value);
    const max = parseInt(quantityInput.getAttribute('max'));

    if (isNaN(quantity) || quantity < 1) { alert('Please enter a valid quantity'); return; }
    if (quantity > max) { alert('Maximum stock available: ' + max); return; }

    const selectedColor = document.querySelector('.color-btn.selected')?.dataset.value || '';
    const selectedSize  = document.querySelector('.size-btn.selected')?.dataset.value  || '';

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
            if (!comment || comment.trim().length < 10) { alert('Please write a review of at least 10 characters'); return; }
            if (comment.length > 1000) { alert('Review must not exceed 1000 characters'); return; }
            const rating = formData.get('rating');
            if (!rating) { alert('Please select a rating'); return; }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';

            fetch('../backend/submit-review.php', { method: 'POST', body: formData })
            .then(response => { if (!response.ok) throw new Error('Network response was not ok'); return response.json(); })
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Review submitted successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to submit review');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting your review. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }
});