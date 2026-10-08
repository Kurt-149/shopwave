<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'SHOPWAVE'; 
?>
<nav class="mobile-product-header">
    <a href="javascript:history.length > 1 ? history.back() : window.location.href='<?php echo $currentPage === 'index.php' ? '#' : 'index.php'; ?>'" 
       class="mph-back" aria-label="Go back">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
            <path d="M400-80 0-480l400-400 71 71-329 329 329 329-71 71Z" />
        </svg>
    </a>
    <span class="mph-title"><?php echo htmlspecialchars($pageTitle); ?></span>
    <div class="mph-right">
        <a href="cart.php" class="mph-cart" aria-label="Shopping cart">
            <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                <path d="M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Zm134 280h280-280Z" />
            </svg>
            <span class="mph-cart-badge cart-count-badge" style="display:none;">0</span>
        </a>
        <div class="mph-more-wrap" id="mphMoreWrap">
            <button class="mph-more-btn" onclick="window.Shopwave.ui.toggleMobileMenu('mphMoreWrap')" aria-label="More options">
                <span class="mph-dots"><span></span><span></span><span></span></span>
            </button>
            <div class="mph-dropdown" role="menu">
                <a href="index.php" role="menuitem">Home</a>
                <a href="shop.php" role="menuitem">Shop</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="me-page.php" role="menuitem">My Profile</a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="../admin/dashboard.php" role="menuitem">Admin</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="../authentication/login-page.php" role="menuitem">Login</a>
                    <a href="../authentication/sign-up.php" role="menuitem">Sign Up</a>
                <?php endif; ?>
                <div class="mph-divider"></div>
                <a href="#" onclick="window.Shopwave.ui.reportIssue(); return false;" class="mph-report" role="menuitem">Report Issue</a>
            </div>
        </div>
    </div>
</nav>