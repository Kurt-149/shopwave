document.addEventListener('DOMContentLoaded', function () {
    // ─── Mobile search toggle ───────────────────────────────────────────────
    const searchToggle = document.querySelector('.mobile-search-toggle');
    const mobileSearchBar = document.getElementById('mobileSearchBar');
    if (searchToggle && mobileSearchBar) {
        searchToggle.addEventListener('click', function () {
            const isHidden = mobileSearchBar.style.display === 'none' || !mobileSearchBar.style.display;
            mobileSearchBar.style.display = isHidden ? 'block' : 'none';
            if (isHidden) {
                mobileSearchBar.style.animation = 'slideDown 0.3s ease';
                mobileSearchBar.querySelector('input')?.focus();
            }
        });
    }

    // ─── Checkout button validation ─────────────────────────────────────────
    const checkoutBtns = document.querySelectorAll('.btn-checkout, .sticky-checkout-btn');
    checkoutBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Check if there are any variant dropdowns in the cart
            const cartItems = document.querySelectorAll('.cart-item');
            let missingVariants = [];
            
            cartItems.forEach(item => {
                const colorWrap = item.querySelector('.cart-variant-wrap[id^="colorWrap"]');
                const sizeWrap = item.querySelector('.cart-variant-wrap[id^="sizeWrap"]');
                const itemName = item.querySelector('.item-name a')?.textContent || 'Item';
                
                // Check if color is required but not selected
                if (colorWrap) {
                    const colorLabel = colorWrap.querySelector('.color-label');
                    if (colorLabel && (colorLabel.textContent === 'Select color' || colorLabel.textContent === '')) {
                        missingVariants.push(`${itemName} - Color required`);
                    }
                }
                
                // Check if size is required but not selected
                if (sizeWrap) {
                    const sizeLabel = sizeWrap.querySelector('.size-label');
                    if (sizeLabel && (sizeLabel.textContent === 'Select size' || sizeLabel.textContent === '')) {
                        missingVariants.push(`${itemName} - Size required`);
                    }
                }
            });
            
            if (missingVariants.length > 0) {
                e.preventDefault();
                let message;
                if (missingVariants.length === 1) {
                    message = missingVariants[0];
                } else {
                    message = `${missingVariants.length} items need variants selected. Please select all options before checkout.`;
                }
                showToast(message, 'error');
            }
        });
    });

    // ─── Suggested products pagination ─────────────────────────────────────
    const ITEMS_PER_PAGE = 9;
    const cards = Array.from(document.querySelectorAll('#suggestedGrid .suggested-card'));
    const pagination = document.getElementById('suggestedPagination');
    if (cards.length > 0 && pagination) {
        const totalPages = Math.ceil(cards.length / ITEMS_PER_PAGE);

        function showPage(page) {
            cards.forEach((card, i) => {
                const start = (page - 1) * ITEMS_PER_PAGE;
                card.style.display = (i >= start && i < start + ITEMS_PER_PAGE) ? '' : 'none';
            });
            pagination.querySelectorAll('.suggested-page-btn').forEach(btn => {
                btn.classList.toggle('active', parseInt(btn.dataset.page) === page);
            });
        }

        if (totalPages > 1) {
            pagination.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = 'suggested-page-btn' + (i === 1 ? ' active' : '');
                btn.dataset.page = i;
                btn.textContent = i;
                btn.type = 'button';
                btn.addEventListener('click', () => showPage(i));
                pagination.appendChild(btn);
            }
        }
        showPage(1);
    }

    // ─── Global event delegation ────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) {
            // Close variant dropdowns on outside click
            document.querySelectorAll('.cart-variant-wrap.open').forEach(w => w.classList.remove('open'));
            return;
        }

        const action = btn.dataset.action;

        switch (action) {

            case 'go-back':
                if (history.length > 1) history.back();
                else window.location.href = 'shop.php';
                break;

            case 'toggle-edit':
                toggleEditMode();
                break;

            case 'toggle-more':
                toggleMobileMore();
                break;

            case 'clear-all':
                clearAllItems();
                break;

            case 'remove':
                removeFromCart(btn.dataset.cartId);
                break;

            case 'update-qty':
                updateQuantity(
                    btn.dataset.cartId,
                    parseInt(btn.dataset.qty),
                    parseInt(btn.dataset.stock)
                );
                break;

            case 'toggle-variant':
                toggleCartVariant(btn);
                break;

            case 'change-color':
                changeCartColor(btn.dataset.cartId, btn.dataset.value, btn);
                break;

            case 'change-size':
                changeCartSize(btn.dataset.cartId, btn.dataset.value, btn);
                break;

            case 'open-lightbox':
                e.preventDefault();
                e.stopPropagation();
                openCartLightbox(btn.dataset.src);
                break;

            case 'close-lightbox':
                closeCartLightbox();
                break;

            case 'delete-selected':
                deleteSelectedItems();
                break;
        }
    });

    // ─── Select-all checkbox ────────────────────────────────────────────────
    const selectAllChk = document.getElementById('selectAllChk');
    if (selectAllChk) {
        selectAllChk.addEventListener('change', function () {
            document.querySelectorAll('.item-checkbox').forEach(c => c.checked = this.checked);
        });
    }

    // ─── Lightbox: click backdrop to close ──────────────────────────────────
    const lightbox = document.getElementById('cartLightbox');
    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox) closeCartLightbox();
        });
    }

    // ─── Keyboard: Escape closes lightbox ───────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeCartLightbox();
    });
});

// ─── Toggle mobile more menu ────────────────────────────────────────────────
function toggleMobileMore() {
    const wrap = document.getElementById('mphMoreWrap');
    if (!wrap) return;
    const isOpen = wrap.classList.toggle('open');
    if (isOpen) {
        setTimeout(() => {
            document.addEventListener('click', function closeMphHandler(e) {
                if (!wrap.contains(e.target)) {
                    wrap.classList.remove('open');
                    document.removeEventListener('click', closeMphHandler);
                }
            });
        }, 10);
    }
}

// ─── Edit mode ──────────────────────────────────────────────────────────────
let editMode = false;
function toggleEditMode() {
    editMode = !editMode;
    const label     = document.getElementById('mphEditLabel');
    const bar       = document.getElementById('editModeBar');
    const checkboxes = document.querySelectorAll('.item-select-wrap');
    const selectAll = document.getElementById('selectAllChk');

    if (editMode) {
        if (label) label.textContent = 'Done';
        if (bar)   bar.style.display = 'flex';
        checkboxes.forEach(c => c.style.display = 'flex');
        document.querySelectorAll('.cart-item').forEach(el => el.classList.add('edit-mode'));
    } else {
        if (label) label.textContent = 'Edit';
        if (bar)   bar.style.display = 'none';
        checkboxes.forEach(c => c.style.display = 'none');
        document.querySelectorAll('.item-checkbox').forEach(c => c.checked = false);
        if (selectAll) selectAll.checked = false;
        document.querySelectorAll('.cart-item').forEach(el => {
            el.classList.remove('edit-mode');
            // Restore the action buttons hidden by CSS edit-mode rule
            const actions = el.querySelector('.item-actions');
            if (actions) actions.style.display = '';
        });
    }
}

// ─── Delete selected ────────────────────────────────────────────────────────
function deleteSelectedItems() {
    const checked = Array.from(document.querySelectorAll('.item-checkbox:checked'));
    if (checked.length === 0) { alert('Select at least one item to delete.'); return; }
    if (!confirm(`Remove ${checked.length} item(s) from your cart?`)) return;
    let done = 0;
    checked.forEach(c => {
        fetch('../backend/remove-from-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `cart_id=${c.value}`
        }).finally(() => { if (++done === checked.length) location.reload(); });
    });
}

// ─── Clear all ──────────────────────────────────────────────────────────────
function clearAllItems() {
    const ids = Array.from(document.querySelectorAll('.cart-item')).map(el => el.dataset.cartId);
    if (!ids.length) return;
    if (!confirm(`Remove all ${ids.length} item(s) from your cart?`)) return;
    let done = 0;
    ids.forEach(id => {
        fetch('../backend/remove-from-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `cart_id=${id}`
        }).finally(() => { if (++done === ids.length) location.reload(); });
    });
}

// ─── Update quantity ────────────────────────────────────────────────────────
function updateQuantity(cartId, newQuantity, maxStock) {
    if (newQuantity < 1) {
        if (confirm('Remove this item from your cart?')) {
            removeFromCart(cartId);
        }
        return;
    }
    if (newQuantity > maxStock) {
        alert('Maximum stock available: ' + maxStock);
        return;
    }

    const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
    if (!cartItem) return;
    cartItem.style.opacity = '0.5';

    fetch('../backend/update-cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `cart_id=${cartId}&quantity=${newQuantity}`
    })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        if (data.success) {
            const qtyInput = cartItem.querySelector('.qty-input');
            if (qtyInput) qtyInput.value = newQuantity;

            const price = parseFloat(cartItem.querySelector('.item-price')?.textContent.replace(/[P,]/g, '')) || 0;
            const itemSubtotal = cartItem.querySelector('.subtotal-amount');
            if (itemSubtotal) itemSubtotal.textContent = 'P' + (price * newQuantity).toFixed(2);

            // Update data-qty on the two qty buttons
            const btns = cartItem.querySelectorAll('[data-action="update-qty"]');
            if (btns.length >= 2) {
                btns[0].dataset.qty = newQuantity - 1;
                btns[1].dataset.qty = newQuantity + 1;
            }

            updateCartTotals(data);
            cartItem.style.opacity = '1';
        } else {
            alert(data.message || 'Failed to update cart');
            cartItem.style.opacity = '1';
        }
    })
    .catch(() => {
        alert('An error occurred while updating your cart. Please try again.');
        cartItem.style.opacity = '1';
    });
}

// ─── Remove single item ──────────────────────────────────────────────────────
function removeFromCart(cartId) {
    if (!confirm('Are you sure you want to remove this item from your cart?')) return;

    const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
    if (!cartItem) return;
    cartItem.style.opacity = '0.5';

    fetch('../backend/remove-from-cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `cart_id=${cartId}`
    })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        if (data.success) {
            cartItem.style.transition = 'all 0.3s ease';
            cartItem.style.transform = 'translateX(-100%)';
            cartItem.style.opacity = '0';
            setTimeout(() => location.reload(), 300);
        } else {
            alert(data.message || 'Failed to remove item');
            cartItem.style.opacity = '1';
        }
    })
    .catch(() => {
        alert('An error occurred while removing the item. Please try again.');
        cartItem.style.opacity = '1';
    });
}

// ─── Variant dropdown toggle ─────────────────────────────────────────────────
function toggleCartVariant(btn) {
    const wrap = btn.closest('.cart-variant-wrap');
    const isOpen = wrap.classList.toggle('open');
    if (isOpen) {
        document.querySelectorAll('.cart-variant-wrap.open').forEach(w => {
            if (w !== wrap) w.classList.remove('open');
        });
    }
}

// ─── Change size ─────────────────────────────────────────────────────────────
function changeCartSize(cartId, newSize, btn) {
    const wrap = btn.closest('.cart-variant-wrap');
    wrap.classList.remove('open');
    const label = wrap.querySelector('.size-label');
    if (label) label.textContent = newSize;
    wrap.querySelectorAll('.cart-variant-option').forEach(o => {
        o.classList.toggle('selected', o.dataset.value === newSize);
    });
    fetch('../backend/update-cart-variant.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `cart_id=${cartId}&selected_size=${encodeURIComponent(newSize)}`
    }).then(r => r.json()).then(data => {
        if (!data.success) alert('Failed to update size. Please try again.');
    }).catch(() => {});
}

// ─── Change color ────────────────────────────────────────────────────────────
function changeCartColor(cartId, newColor, btn) {
    const wrap = btn.closest('.cart-variant-wrap');
    wrap.classList.remove('open');
    const label = wrap.querySelector('.color-label');
    if (label) label.textContent = newColor;
    wrap.querySelectorAll('.cart-variant-option').forEach(o => {
        o.classList.toggle('selected', o.dataset.value === newColor);
    });
    fetch('../backend/update-cart-color.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `cart_id=${cartId}&selected_color=${encodeURIComponent(newColor)}`
    }).then(r => r.json()).then(data => {
        if (!data.success) alert('Failed to update color. Please try again.');
    }).catch(() => {});
}

// ─── Lightbox ────────────────────────────────────────────────────────────────
function openCartLightbox(src) {
    const lightbox = document.getElementById('cartLightbox');
    document.getElementById('cartLightboxImg').src = src;
    lightbox.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeCartLightbox() {
    const lightbox = document.getElementById('cartLightbox');
    if (lightbox) lightbox.classList.remove('open');
    document.body.style.overflow = '';
}

// ─── Update totals after fetch ───────────────────────────────────────────────
function updateCartTotals(data) {
    const subtotalEl = document.getElementById('summary-subtotal');
    if (subtotalEl && data.subtotal !== undefined)
        subtotalEl.textContent = 'P' + parseFloat(data.subtotal).toFixed(2);

    const shippingEl = document.getElementById('summary-shipping');
    if (shippingEl && data.shipping !== undefined) {
        if (data.shipping === 0) {
            shippingEl.textContent = 'FREE';
            shippingEl.classList.add('free-shipping');
        } else {
            shippingEl.textContent = 'P' + parseFloat(data.shipping).toFixed(2);
            shippingEl.classList.remove('free-shipping');
        }
    }

    const promoMsg = document.querySelector('.shipping-promo');
    if (promoMsg) {
        if (data.shipping > 0 && data.subtotal < 1000) {
            const remaining = (1000 - data.subtotal).toFixed(2);
            promoMsg.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#fb923c">
                    <path d="M440-280h80v-240h-80v240Zm40-320q17 0 28.5-11.5T520-640q0-17-11.5-28.5T480-680q-17 0-28.5 11.5T440-640q0 17 11.5 28.5T480-600Zm0 520q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/>
                </svg>
                Add P${remaining} more for FREE shipping!`;
            promoMsg.style.display = 'flex';
        } else {
            promoMsg.style.display = 'none';
        }
    }

    const taxEl = document.getElementById('summary-tax');
    if (taxEl && data.tax !== undefined)
        taxEl.textContent = 'P' + parseFloat(data.tax).toFixed(2);

    const totalEl = document.getElementById('summary-total-amount');
    if (totalEl && data.total !== undefined)
        totalEl.textContent = 'P' + parseFloat(data.total).toFixed(2);

    const stickyTotal = document.getElementById('sticky-total');
    if (stickyTotal && data.total !== undefined)
        stickyTotal.textContent = 'P' + parseFloat(data.total).toFixed(2);

    if (data.cart_count !== undefined) updateCartBadge(data.cart_count);
}

// ─── Cart badge ──────────────────────────────────────────────────────────────
function updateCartBadge(count) {
    const badge = document.querySelector('.cart-count-badge') || document.querySelector('.cart-badge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count === 0 ? 'none' : 'flex';
    }
}