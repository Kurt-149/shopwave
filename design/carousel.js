
document.addEventListener('DOMContentLoaded', () => {
    const track = document.querySelector('.track');
    const slides = document.querySelector('.slides');
    const slideElements = document.querySelectorAll('.slide');
    const next = document.querySelector('.next');
    const prev = document.querySelector('.prev');
    
    if (!track || !slides || !slideElements.length) {
        console.warn('Carousel elements not found');
        return;
    }
    
    let currentSlide = 0;
    const totalSlides = slideElements.length;
    let autoRotateInterval;
    const AUTO_ROTATE_DELAY = 5000; // 5 seconds
    
    // Calculate slide width
    function getSlideWidth() {
        return slideElements[0].offsetWidth;
    }
    
    // Go to specific slide
    function goToSlide(index) {
        if (index < 0) {
            currentSlide = totalSlides - 1;
        } else if (index >= totalSlides) {
            currentSlide = 0;
        } else {
            currentSlide = index;
        }
        
        const slideWidth = getSlideWidth();
        const offset = -currentSlide * slideWidth;
        slides.style.transform = `translateX(${offset}px)`;
        
        // Update indicators if they exist
        updateIndicators();
    }
    
    // Next slide
    function nextSlide() {
        goToSlide(currentSlide + 1);
    }
    
    // Previous slide
    function prevSlide() {
        goToSlide(currentSlide - 1);
    }
    
    // Update slide indicators
    function updateIndicators() {
        const indicators = document.querySelectorAll('.carousel-indicator');
        indicators.forEach((indicator, index) => {
            if (index === currentSlide) {
                indicator.classList.add('active');
            } else {
                indicator.classList.remove('active');
            }
        });
    }
    
    // Start auto-rotation
    function startAutoRotate() {
        stopAutoRotate(); // Clear any existing interval
        autoRotateInterval = setInterval(nextSlide, AUTO_ROTATE_DELAY);
    }
    
    // Stop auto-rotation
    function stopAutoRotate() {
        if (autoRotateInterval) {
            clearInterval(autoRotateInterval);
            autoRotateInterval = null;
        }
    }
    
    // Event listeners
    if (next) {
        next.addEventListener('click', () => {
            nextSlide();
            startAutoRotate(); // Restart auto-rotation after manual control
        });
    }
    
    if (prev) {
        prev.addEventListener('click', () => {
            prevSlide();
            startAutoRotate(); // Restart auto-rotation after manual control
        });
    }
    
    // Pause on hover
    track.addEventListener('mouseenter', stopAutoRotate);
    track.addEventListener('mouseleave', startAutoRotate);
    
    // Touch/swipe support
    let touchStartX = 0;
    let touchEndX = 0;
    
    track.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
        stopAutoRotate();
    });
    
    track.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
        startAutoRotate();
    });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartX - touchEndX;
        
        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                nextSlide(); // Swipe left
            } else {
                prevSlide(); // Swipe right
            }
        }
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
            prevSlide();
            startAutoRotate();
        } else if (e.key === 'ArrowRight') {
            nextSlide();
            startAutoRotate();
        }
    });
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            goToSlide(currentSlide); 
        }, 250);
    });
    function createIndicators() {
        const indicatorsContainer = document.querySelector('.carousel-indicators');
        if (!indicatorsContainer && totalSlides > 1) {
            const container = document.createElement('div');
            container.className = 'carousel-indicators';
            
            for (let i = 0; i < totalSlides; i++) {
                const indicator = document.createElement('button');
                indicator.className = 'carousel-indicator';
                indicator.setAttribute('aria-label', `Go to slide ${i + 1}`);
                indicator.addEventListener('click', () => {
                    goToSlide(i);
                    startAutoRotate();
                });
                container.appendChild(indicator);
            }
            
            track.appendChild(container);
            updateIndicators();
        }
    }
    
    // Initialize
    createIndicators();
    goToSlide(0);
    startAutoRotate();
});