window.SHOPWAVE = window.SHOPWAVE || {};

SHOPWAVE.toast = (function() {
    const styles = `
        .shopwave-toast {
            position: fixed; bottom: 24px; right: 24px;
            color: white; padding: 12px 24px;
            border-radius: 8px; z-index: 9999;
            font-weight: 600; animation: slideIn 0.3s ease;
            max-width: 320px;
        }
        .shopwave-toast.success { background: #22c55e; }
        .shopwave-toast.error { background: #ef4444; }
        .shopwave-toast.info { background: #3b82f6; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    
    const style = document.createElement('style');
    style.textContent = styles;
    document.head.appendChild(style);
    
    return function(message, type = 'success') {
        const existing = document.querySelector('.shopwave-toast');
        if (existing) existing.remove();
        
        const toast = document.createElement('div');
        toast.className = `shopwave-toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };
})();

window.showToast = SHOPWAVE.toast;