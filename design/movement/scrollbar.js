
(function() {
    // Get scrollbar width
    function getScrollbarWidth() {
        const outer = document.createElement('div');
        outer.style.visibility = 'hidden';
        outer.style.overflow = 'scroll';
        document.body.appendChild(outer);
        
        const inner = document.createElement('div');
        outer.appendChild(inner);
        
        const scrollbarWidth = outer.offsetWidth - inner.offsetWidth;
        outer.parentNode.removeChild(outer);
        
        return scrollbarWidth;
    }
    
    // Add padding to body when scrollbar is NOT present
    function adjustForScrollbar() {
        const hasScrollbar = document.body.scrollHeight > window.innerHeight;
        const scrollbarWidth = getScrollbarWidth();
        
        if (!hasScrollbar && scrollbarWidth > 0) {
            document.body.style.paddingRight = scrollbarWidth + 'px';
        } else {
            document.body.style.paddingRight = '0';
        }
    }
    
    // Run on page load
    window.addEventListener('load', adjustForScrollbar);
    window.addEventListener('resize', adjustForScrollbar);
})();