document.addEventListener('DOMContentLoaded', function () {
    const searchToggle = document.querySelector('.mobile-search-toggle');
    const mobileSearchBar = document.getElementById('mobileSearchBar');
    if (searchToggle && mobileSearchBar) {
        searchToggle.addEventListener('click', function () {
            const isHidden = mobileSearchBar.style.display === 'none' || !mobileSearchBar.style.display;
            mobileSearchBar.style.display = isHidden ? 'block' : 'none';
            if (isHidden) {
                mobileSearchBar.style.animation = 'slideDown 0.3s ease';
                mobileSearchBar.querySelector('input').focus();
            }
        });
    }
});

document.getElementById('checkoutForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = document.getElementById('placeOrderBtn');
    const originalHTML = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" style="animation:spin 1s linear infinite;">
            <path d="M480-80q-82 0-155-31.5t-127.5-86Q143-252 111.5-325T80-480q0-83 31.5-155.5t86-127Q252-817 325-848.5T480-880q83 0 155.5 31.5t127 86q54.5 54.5 86 127T880-480q0 82-31.5 155t-86 127.5q-54.5 72.5-127 104T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/>
        </svg>
        Processing...
    `;

    fetch('../backend/process-order.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('checkoutForm').reset();
                alert(data.message);
                window.location.href = 'order-success.php?order=' + data.order_number;
            } else {
                alert(data.message || 'Failed to process order. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            }
        })
        .catch(() => {
            alert('An error occurred while processing your order. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHTML;
        });
});