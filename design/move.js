document.addEventListener('DOMContentLoaded', () => {
    const track = document.querySelector('.track');
    const next = document.querySelector('.next');
    const prev = document.querySelector('.prev');
    
    // Exit if carousel elements don't exist on this page
    if (!track || !next || !prev) {
        return; // Silently exit - no error
    }

    // Make sure track has width
    if (track.clientWidth === 0) {
        // Try again after a short delay
        setTimeout(() => {
            if (track.clientWidth > 0) {
                const scrollAmount = track.clientWidth * 0.8;
                setupCarousel(track, next, prev, scrollAmount);
            }
        }, 100);
        return;
    }

    const scrollAmount = track.clientWidth * 0.8;
    setupCarousel(track, next, prev, scrollAmount);
});

function setupCarousel(track, next, prev, scrollAmount) {
    next.addEventListener('click', () => {
        track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    });

    prev.addEventListener('click', () => {
        track.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    });
}