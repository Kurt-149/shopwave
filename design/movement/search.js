
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.search');
    
    if (searchInput) {
        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
        
        // Optional: Add a search button
        const searchWrapper = searchInput.parentElement;
        if (searchWrapper && !searchWrapper.querySelector('.search-btn')) {
            const searchBtn = document.createElement('button');
            searchBtn.className = 'search-btn';
            searchBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff">
                    <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/>
                </svg>
            `;
            searchBtn.addEventListener('click', performSearch);
            searchWrapper.appendChild(searchBtn);
        }
    }
    
    function performSearch() {
        const query = searchInput.value.trim();
        if (query) {
            // Redirect to shop page with search query
            window.location.href = `shop.php?search=${encodeURIComponent(query)}`;
        } else {
            // If empty, just go to shop page
            window.location.href = 'shop.php';
        }
    }
});