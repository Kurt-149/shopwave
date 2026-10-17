function increaseQty() {
    const input = document.getElementById('quantity');
    if (!input) return;
    
    if (input.disabled) {
        showToast('This product is out of stock', 'error');
        return;
    }
    
    const max = parseInt(input.getAttribute('max')) || 999;
    const current = parseInt(input.value) || 1;
    
    if (max <= 0) {
        showToast('This product is out of stock', 'error');
        return;
    }
    
    if (current < max) {
        input.value = current + 1;
    } else {
        showToast('Maximum stock reached: ' + max, 'error');
    }
}

function decreaseQty() {
    const input = document.getElementById('quantity');
    if (!input) return;
    
    if (input.disabled) {
        showToast('This product is out of stock', 'error');
        return;
    }
    
    const max = parseInt(input.getAttribute('max')) || 999;
    const min = parseInt(input.getAttribute('min')) || 1;
    const current = parseInt(input.value) || 1;
    
    if (max <= 0) {
        showToast('This product is out of stock', 'error');
        return;
    }
    
    if (current > min) {
        input.value = current - 1;
    }
}

function validateQty() {
    const input = document.getElementById('quantity');
    if (!input) return;
    
    if (input.disabled) return;
    
    const min = parseInt(input.getAttribute('min')) || 1;
    const max = parseInt(input.getAttribute('max')) || 999;
    let value = parseInt(input.value);
    
    if (max <= 0) {
        input.value = 0;
        disableOutOfStockControls();
        return;
    }
    
    if (isNaN(value) || value < min) {
        input.value = min;
    } else if (value > max) {
        input.value = max;
        showToast('Maximum stock available: ' + max, 'error');
    }
}

function addToCart(productId, btnEl) {
    const quantityInput = document.getElementById('quantity');
    if (!quantityInput) return;

    const max = parseInt(quantityInput.getAttribute('max'));

    if (max <= 0) {
        showToast(window.VARIANT_STOCK ? 'Sold out in this colour or size' : 'This product is out of stock', 'error');
        return;
    }

    const quantity = parseInt(quantityInput.value);

    if (isNaN(quantity) || quantity < 1) {
        showToast('Please enter a valid quantity', 'error');
        return;
    }
    
    if (quantity > max) {
        showToast('Maximum stock available: ' + max, 'error');
        return;
    }

    const hasColors = document.querySelectorAll('.color-btn').length > 0;
    const hasSizes  = document.querySelectorAll('.size-btn').length > 0;

    const selectedColor = document.querySelector('.color-btn.selected')?.dataset.value || '';
    const selectedSize  = document.querySelector('.size-btn.selected')?.dataset.value  || '';

    if (hasColors && !selectedColor) {
        showToast('Please select a color', 'error');
        return;
    }
    if (hasSizes && !selectedSize) {
        showToast('Please select a size', 'error');
        return;
    }

    const addButton = btnEl || document.querySelector('.btn-add-cart');
    const originalHTML = addButton ? addButton.innerHTML : '';
    
    if (addButton) {
        addButton.disabled = true;
        addButton.innerHTML = '⏳ Adding...';
    }

    fetch('../backend/add-to-cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}&quantity=${quantity}&selected_color=${encodeURIComponent(selectedColor)}&selected_size=${encodeURIComponent(selectedSize)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Added to cart!', 'success');
            if (typeof updateCartCount === 'function') updateCartCount();
        } else {
            showToast(data.message || 'Failed to add to cart', 'error');
        }
    })
    .catch(() => showToast('An error occurred. Please try again.', 'error'))
    .finally(() => {
        if (addButton) {
            addButton.disabled = false;
            addButton.innerHTML = originalHTML;
        }
    });
}

function buyNow(productId) {
    const quantityInput = document.getElementById('quantity');
    if (!quantityInput) return;

    const max = parseInt(quantityInput.getAttribute('max'));

    if (max <= 0) {
        showToast('This product is out of stock', 'error');
        return;
    }

    const quantity = parseInt(quantityInput.value);

    if (isNaN(quantity) || quantity < 1) {
        showToast('Please enter a valid quantity', 'error');
        return;
    }
    
    if (quantity > max) {
        showToast('Maximum stock available: ' + max, 'error');
        return;
    }

    const hasColors = document.querySelectorAll('.color-btn').length > 0;
    const hasSizes  = document.querySelectorAll('.size-btn').length > 0;

    const selectedColor = document.querySelector('.color-btn.selected')?.dataset.value || '';
    const selectedSize  = document.querySelector('.size-btn.selected')?.dataset.value  || '';

    if (hasColors && !selectedColor) {
        showToast('Please select a color', 'error');
        return;
    }
    if (hasSizes && !selectedSize) {
        showToast('Please select a size', 'error');
        return;
    }

    window.location.href = `checkout.php?product_id=${productId}&quantity=${quantity}&selected_color=${encodeURIComponent(selectedColor)}&selected_size=${encodeURIComponent(selectedSize)}`;
}

function toggleDropdown(wrapId) {
    const wrap = document.getElementById(wrapId);
    const isOpen = wrap.classList.toggle('open');
    if (isOpen) {
        setTimeout(() => {
            document.addEventListener('click', function closeHandler(e) {
                if (!wrap.contains(e.target)) {
                    wrap.classList.remove('open');
                    document.removeEventListener('click', closeHandler);
                }
            });
        }, 10);
    }
}

let _defaultProductImage = '';

function swapMainImage(newSrc) {
    const mainImg = document.getElementById('mainProductImage');
    if (!mainImg) return;
    const target = newSrc || _defaultProductImage;
    if (!target || mainImg.src === target) return;
    mainImg.style.transition = 'opacity 0.2s ease, transform 0.3s ease';
    mainImg.style.opacity = '0';
    mainImg.style.transform = 'translateY(-6px)';
    setTimeout(() => {
        mainImg.src = target;
        const restore = () => {
            mainImg.style.opacity = '1';
            mainImg.style.transform = 'translateY(0)';
        };
        mainImg.onload  = restore;
        mainImg.onerror = restore;
    }, 200);
}

function selectVariant(btn, type, triggerTextId, dropdownId, wrapId) {
    const group = btn.closest('.variant-choices, .variant-dropdown');
    group.querySelectorAll('.variant-btn').forEach(b => {
        b.classList.remove('selected');
        const check = b.querySelector('.variant-check');
        if (check) check.style.opacity = '0';
    });
    btn.classList.add('selected');
    const check = btn.querySelector('.variant-check');
    if (check) check.style.opacity = '1';
    const value = btn.dataset.value;
    refreshVariantAvailability();
    if (triggerTextId) {
        document.getElementById(triggerTextId).textContent = value;
    }
    if (wrapId) {
        document.getElementById(wrapId).classList.remove('open');
    }
    if (type === 'color') {
        const imageSrc = (btn.dataset.image && btn.dataset.image.trim() !== '')
            ? btn.dataset.image
            : _defaultProductImage;
        swapMainImage(imageSrc);
    }
}
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

function mphReportItem() {
    const wrap = document.getElementById('mphMoreWrap');
    if (wrap) wrap.classList.remove('open');
    showToast('Thank you — this item has been reported.', 'info');
}

function openImageLightbox(src) {
    const imgSrc = src || document.getElementById('mainProductImage')?.src;
    if (!imgSrc) return;
    const lightbox = document.getElementById('imageLightbox');
    const img = document.getElementById('lightboxImage');
    img.src = imgSrc;
    lightbox.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeImageLightbox() {
    document.getElementById('imageLightbox').classList.remove('open');
    document.body.style.overflow = '';
}

function disableOutOfStockControls() {
    const qtyInput = document.getElementById('quantity');
    const minusBtn = document.querySelector('.qty-btn:first-child');
    const plusBtn = document.querySelector('.qty-btn:last-child');
    const addBtn = document.querySelector('.btn-add-cart');
    const buyBtn = document.querySelector('.btn-buy-now');
    const stockStatus = document.querySelector('.stock-status');
    
    if (qtyInput) {
        qtyInput.disabled = true;
        qtyInput.value = 0;
    }
    
    if (minusBtn) {
        minusBtn.disabled = true;
    }
    
    if (plusBtn) {
        plusBtn.disabled = true;
    }
    
    if (addBtn) {
        addBtn.disabled = true;
        addBtn.innerHTML = 'Sold Out';
    }
    
    if (buyBtn) {
        buyBtn.disabled = true;
        buyBtn.innerHTML = 'Sold Out';
    }
    
    if (stockStatus) {
        stockStatus.innerHTML = '<span class="out-of-stock">❌ Out of Stock</span>';
    }
}

function applyMobileColorDropdown() {
    const isMobile = window.innerWidth <= 900;
    const colorRow = document.querySelector('.variant-row:has(.color-btn)');
    if (!colorRow) return;

    const inlineWrap = colorRow.querySelector('.variant-choices');
    const dropdownWrap = colorRow.querySelector('.variant-dropdown-wrap');

    if (!inlineWrap) return;

    const colorBtns = inlineWrap.querySelectorAll('.color-btn');
    const colorCount = colorBtns.length;

    if (isMobile && colorCount >= 3) {
        if (!dropdownWrap) {
            const selectedBtn = inlineWrap.querySelector('.color-btn.selected');
            const selectedValue = selectedBtn ? selectedBtn.dataset.value : null;

            const wrap = document.createElement('div');
            wrap.className = 'variant-dropdown-wrap';
            wrap.id = 'colorDropdownWrapMobile';

            const triggerText = selectedValue || ('See all ' + colorCount + ' colors');
            wrap.innerHTML = `
                <button class="variant-trigger" onclick="toggleDropdown('colorDropdownWrapMobile')" id="colorTriggerMobile" type="button">
                    <span id="colorTriggerTextMobile">${triggerText}</span>
                    <span class="trigger-arrow">▼</span>
                </button>
                <div class="variant-dropdown" id="colorDropdownMobile"></div>
            `;

            const dropdownContainer = wrap.querySelector('#colorDropdownMobile');
            colorBtns.forEach(btn => {
                const clone = btn.cloneNode(true);
                clone.setAttribute('onclick', "selectVariant(this, 'color', 'colorTriggerTextMobile', 'colorDropdownMobile', 'colorDropdownWrapMobile')");
                dropdownContainer.appendChild(clone);
            });

            inlineWrap.style.display = 'none';
            colorRow.appendChild(wrap);
        } else {
            inlineWrap.style.display = 'none';
            dropdownWrap.style.display = '';
        }
    } else {
        if (inlineWrap) inlineWrap.style.display = '';
        const mobileWrap = document.getElementById('colorDropdownWrapMobile');
        if (mobileWrap) mobileWrap.style.display = 'none';
        if (dropdownWrap && dropdownWrap.id !== 'colorDropdownWrapMobile') {
            dropdownWrap.style.display = colorCount >= 3 ? '' : '';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const mainImg = document.getElementById('mainProductImage');
    if (mainImg) _defaultProductImage = mainImg.src;

    const qtyInput = document.getElementById('quantity');
    if (qtyInput) {
        qtyInput.addEventListener('change', validateQty);
        qtyInput.addEventListener('keyup', validateQty);
        
        const max = parseInt(qtyInput.getAttribute('max')) || 999;
        if (max <= 0) {
            disableOutOfStockControls();
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeImageLightbox();
    });

    applyMobileColorDropdown();

    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applyMobileColorDropdown, 100);
    });
});
// ─── Stock per colour and size ───────────────────────────────────────────────
// window.VARIANT_STOCK is set by product-details.php when the product keeps stock per option.
// Options with no stock in the current combination are greyed out, and the quantity and
// buttons follow the selected colour and size.
function variantStockTotal(color, size) {
    const rows = window.VARIANT_STOCK;
    if (!rows) return null;
    return rows
        .filter(r => (color === null || r.color === color) && (size === null || r.size === size))
        .reduce((sum, r) => sum + Number(r.stock), 0);
}

function refreshVariantAvailability() {
    const rows = window.VARIANT_STOCK;
    const qty = document.getElementById('quantity');
    if (!rows || !qty) return;

    // A product with no stock at all is already handled by the server-rendered sold-out state.
    if (qty.dataset.totalMax === undefined) qty.dataset.totalMax = qty.getAttribute('max') || '0';
    if (Number(qty.dataset.totalMax) === 0) return;

    const colorBtns = document.querySelectorAll('.color-btn');
    const sizeBtns  = document.querySelectorAll('.size-btn');
    const hasColors = colorBtns.length > 0;
    const hasSizes  = sizeBtns.length > 0;
    const color = document.querySelector('.color-btn.selected')?.dataset.value ?? '';
    const size  = document.querySelector('.size-btn.selected')?.dataset.value ?? '';

    // The option being viewed is never greyed out; the status line explains a sold-out pair.
    colorBtns.forEach(btn => btn.classList.toggle('is-soldout',
        !btn.classList.contains('selected') && variantStockTotal(btn.dataset.value, size || null) === 0));
    sizeBtns.forEach(btn => btn.classList.toggle('is-soldout',
        !btn.classList.contains('selected') && variantStockTotal(color || null, btn.dataset.value) === 0));

    const addBtn = document.querySelector('.btn-add-cart');
    const buyBtn = document.querySelector('.btn-buy-now');
    const status = document.querySelector('.stock-status');
    const qtyBtns = document.querySelectorAll('.qty-btn');
    const actionBtns = [addBtn, buyBtn].filter(Boolean);
    if (addBtn && !addBtn.dataset.label) addBtn.dataset.label = addBtn.innerHTML;
    if (buyBtn && !buyBtn.dataset.label) buyBtn.dataset.label = buyBtn.innerHTML;

    const colorNeeded = hasColors && !color;
    const sizeNeeded  = hasSizes && !size;
    if (colorNeeded || sizeNeeded) {
        // Not every option is chosen yet: the product total is the limit.
        qty.max = qty.dataset.totalMax;
        qty.disabled = false;
        qtyBtns.forEach(b => b.disabled = false);
        actionBtns.forEach(b => { b.disabled = false; b.innerHTML = b.dataset.label; });
        return;
    }

    const left = variantStockTotal(hasColors ? color : null, hasSizes ? size : null);

    if (left === 0) {
        qty.max = '0';
        qty.value = 0;
        qty.disabled = true;
        qtyBtns.forEach(b => b.disabled = true);
        actionBtns.forEach(b => { b.disabled = true; b.innerHTML = 'Sold Out'; });
        if (status) status.innerHTML = '<span class="out-of-stock">❌ Sold out in this colour or size</span>';
        return;
    }

    qty.max = String(left);
    qty.disabled = false;
    qtyBtns.forEach(b => b.disabled = false);
    if (Number(qty.value) > left || Number(qty.value) < 1) qty.value = 1;
    actionBtns.forEach(b => { b.disabled = false; b.innerHTML = b.dataset.label; });
    if (status) {
        status.innerHTML = left > 10
            ? '<span class="in-stock">In Stock (' + left + ' available)</span>'
            : '<span class="low-stock">Low Stock — Only ' + left + ' left</span>';
    }
}

refreshVariantAvailability();