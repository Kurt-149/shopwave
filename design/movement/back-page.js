function goBack() {
    const referrer = document.referrer;
    
    // Check if referrer is from your own site
    if (referrer && referrer.includes(window.location.host)) {
        history.back();
    } else {
        // Go to home if came from external site
        window.location.href = '/index.php';
    }
}