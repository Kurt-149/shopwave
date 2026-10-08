function addToCart(productId) {
    // Add to cart functionality
    alert('Product ' + productId + ' added to cart!');
}

// Price input validation - enforce limits
const priceInputs = document.querySelectorAll('.price-input');
const MAX_PRICE = 100000;
const MIN_PRICE = 0;

priceInputs.forEach(input => {
    input.addEventListener('input', function () {
        let value = parseFloat(this.value);

        if (isNaN(value)) return;

        if (value > MAX_PRICE) {
            this.value = MAX_PRICE;
        } else if (value < MIN_PRICE) {
            this.value = MIN_PRICE;
        }
    });
});

