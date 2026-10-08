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