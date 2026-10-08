
function openProductModal(productId) {
    fetch(`../backend/get-product-details.php?id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayProductModal(data.product, data.reviews, data.relatedProducts);
            } else {
                alert('Failed to load product details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
}

function displayProductModal(product, reviews, relatedProducts) {
    const modalHTML = `
        <div class="product-modal-overlay" onclick="closeProductModal(event)">
            <div class="product-modal-content" onclick="event.stopPropagation()">
                <button class="modal-close-btn" onclick="closeProductModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                        <path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/>
                    </svg>
                </button>
                
                <!-- Product content here (similar to Structure 1) -->
                <div class="modal-body">
                    <!-- Same product detail layout as Structure 1 -->
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.body.style.overflow = 'hidden';
}

function closeProductModal(event) {
    if (!event || event.target.classList.contains('product-modal-overlay')) {
        const modal = document.querySelector('.product-modal-overlay');
        if (modal) {
            modal.remove();
            document.body.style.overflow = '';
        }
    }
}