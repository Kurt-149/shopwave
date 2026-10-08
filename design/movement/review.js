// edit review
function editReview(reviewId) {
    // Fetch current review data
    fetch(`../backend/get-review.php?review_id=${reviewId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showEditModal(data.review);
            } else {
                alert(data.message || 'Failed to load review');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load review');
        });
}

function showEditModal(review) {
    // Create modal HTML
    const modal = document.createElement('div');
    modal.className = 'review-edit-modal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeEditModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Your Review</h3>
                <button class="modal-close" onclick="closeEditModal()">×</button>
            </div>
            <form id="editReviewForm" class="edit-review-form">
                <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                <input type="hidden" name="review_id" value="${review.id}">
                
                <div class="form-group">
                    <label>Your Rating</label>
                    <div class="star-rating-input">
                        ${[5, 4, 3, 2, 1].map(star => `
                            <input type="radio" name="rating" id="edit-star${star}" value="${star}" 
                                ${review.rating == star ? 'checked' : ''}>
                            <label for="edit-star${star}">★</label>
                        `).join('')}
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit-comment">Your Review</label>
                    <textarea id="edit-comment" name="comment" rows="5" 
                        minlength="10" maxlength="1000" required>${review.comment}</textarea>
                    <small class="char-count">
                        <span id="edit-char-count">${review.comment.length}</span>/1000 characters
                    </small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Character counter
    const textarea = modal.querySelector('#edit-comment');
    const charCount = modal.querySelector('#edit-char-count');
    textarea.addEventListener('input', () => {
        charCount.textContent = textarea.value.length;
    });
    
    // Handle form submission
    const form = modal.querySelector('#editReviewForm');
    form.addEventListener('submit', handleEditReviewSubmit);
}

function handleEditReviewSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    // Validate
    const comment = formData.get('comment');
    if (comment.length < 10) {
        alert('Review must be at least 10 characters');
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';
    
    fetch('../backend/edit-review.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeEditModal();
                location.reload(); // Reload to show updated review
            } else {
                alert(data.message || 'Failed to update review');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating your review');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
}

function closeEditModal() {
    const modal = document.querySelector('.review-edit-modal');
    if (modal) {
        modal.remove();
    }
}
// delete review
function deleteReview(reviewId) {
    if (!confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('review_id', reviewId);
    
    fetch('../backend/delete-review.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload(); // Reload to remove deleted review
            } else {
                alert(data.message || 'Failed to delete review');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting your review');
        });
}

// vote on review
function voteReview(reviewId, voteType) {
    console.log('voteReview called:', reviewId, voteType);
    
    // Validate inputs
    if (!reviewId || !voteType) {
        alert('Invalid vote parameters');
        return;
    }
    
    const formData = new FormData();
    formData.append('review_id', reviewId);
    formData.append('vote_type', voteType);
    
    fetch('../backend/vote-review.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Vote response:', data);
            
            if (data.success) {
                // Update the vote counts in the UI
                updateVoteCounts(reviewId, data.helpful_count, data.not_helpful_count, data.action);
                
                // Show feedback message
                if (data.action === 'added') {
                    showVoteFeedback('Thank you for your feedback!', 'success');
                } else if (data.action === 'removed') {
                    showVoteFeedback('Vote removed', 'info');
                } else if (data.action === 'updated') {
                    showVoteFeedback('Vote updated', 'info');
                }
            } else {
                alert(data.message || 'Failed to record vote');
            }
        })
        .catch(error => {
            console.error('Vote error:', error);
            alert('An error occurred while voting. Please try again.');
        });
}

function updateVoteCounts(reviewId, helpfulCount, notHelpfulCount, action) {
    const voteSection = document.querySelector(`[data-review-id="${reviewId}"] .review-helpful`);
    if (!voteSection) return;
    
    const helpfulBtn = voteSection.querySelector('.vote-helpful');
    const notHelpfulBtn = voteSection.querySelector('.vote-not-helpful');
    
    if (helpfulBtn) {
        const voteCountSpan = helpfulBtn.querySelector('.vote-count');
        if (voteCountSpan) {
            voteCountSpan.textContent = helpfulCount;
        }
        
        // Update button state
        if (action === 'added' || action === 'updated') {
            helpfulBtn.classList.add('voted');
            if (notHelpfulBtn) notHelpfulBtn.classList.remove('voted');
        } else if (action === 'removed') {
            helpfulBtn.classList.remove('voted');
        }
    }
}

// Show temporary feedback message
function showVoteFeedback(message, type = 'success') {
    const feedback = document.createElement('div');
    feedback.className = `vote-feedback vote-feedback-${type}`;
    feedback.textContent = message;
    feedback.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${type === 'success' ? '#22c55e' : '#3b82f6'};
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(feedback);
    
    setTimeout(() => {
        feedback.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => feedback.remove(), 300);
    }, 2000);
}

// ========================================
// ADMIN REPLY TO REVIEW
// ========================================

function replyToReview(reviewId) {
    const modal = document.createElement('div');
    modal.className = 'review-edit-modal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeReplyModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reply to Review</h3>
                <button class="modal-close" onclick="closeReplyModal()">×</button>
            </div>
            <form id="replyReviewForm" class="reply-review-form">
                <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                <input type="hidden" name="review_id" value="${reviewId}">
                
                <div class="form-group">
                    <label for="admin-reply">Your Reply</label>
                    <textarea id="admin-reply" name="admin_reply" rows="5" 
                        minlength="5" maxlength="1000" required 
                        placeholder="Thank you for your feedback..."></textarea>
                    <small class="char-count">
                        <span id="reply-char-count">0</span>/1000 characters
                    </small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeReplyModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Post Reply</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Character counter
    const textarea = modal.querySelector('#admin-reply');
    const charCount = modal.querySelector('#reply-char-count');
    textarea.addEventListener('input', () => {
        charCount.textContent = textarea.value.length;
    });
    
    // Handle form submission
    const form = modal.querySelector('#replyReviewForm');
    form.addEventListener('submit', handleReplySubmit);
}

function handleReplySubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Posting...';
    
    fetch('../backend/admin-reply-review.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeReplyModal();
                location.reload();
            } else {
                alert(data.message || 'Failed to post reply');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while posting reply');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
}

function closeReplyModal() {
    const modal = document.querySelector('.review-edit-modal');
    if (modal) {
        modal.remove();
    }
}

// UTILITY FUNCTIONS

function getCsrfToken() {
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.getAttribute('content');
    }
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    if (tokenInput) {
        return tokenInput.value;
    }
    console.warn('CSRF token not found! Please add <meta name="csrf-token" content="<?php echo $csrfToken; ?>"> to the page head.');
    return '';
}

// INITIALIZATION
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers for vote buttons
    document.querySelectorAll('.vote-helpful').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent any default action
            
            const reviewId = this.getAttribute('data-review-id');
            if (!reviewId) {
                console.error('Review ID not found');
                return;
            }
            
            console.log('Voting helpful on review:', reviewId);
            voteReview(reviewId, 'helpful');
        });
    });
    
    document.querySelectorAll('.vote-not-helpful').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const reviewId = this.getAttribute('data-review-id');
            if (!reviewId) {
                console.error('Review ID not found');
                return;
            }
            
            console.log('Voting not helpful on review:', reviewId);
            voteReview(reviewId, 'not_helpful');
        });
    });
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closeEditModal();
        closeReplyModal();
    }
});

// admin reply fuction
function editAdminReply(reviewId, currentReply) {
    const modal = document.createElement('div');
    modal.className = 'review-edit-modal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeEditReplyModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Store Owner's Reply</h3>
                <button class="modal-close" onclick="closeEditReplyModal()">×</button>
            </div>
            <form id="editAdminReplyForm" class="reply-review-form">
                <input type="hidden" name="csrf_token" value="${getCsrfToken()}">
                <input type="hidden" name="review_id" value="${reviewId}">
                
                <div class="form-group">
                    <label for="admin-reply-edit">Your Reply</label>
                    <textarea id="admin-reply-edit" name="admin_reply" rows="5" 
                        minlength="5" maxlength="1000" required>${currentReply}</textarea>
                    <small class="char-count">
                        <span id="reply-edit-char-count">${currentReply.length}</span>/1000 characters
                    </small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeEditReplyModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Update Reply</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Character counter
    const textarea = modal.querySelector('#admin-reply-edit');
    const charCount = modal.querySelector('#reply-edit-char-count');
    textarea.addEventListener('input', () => {
        charCount.textContent = textarea.value.length;
    });
    
    // Handle form submission
    const form = modal.querySelector('#editAdminReplyForm');
    form.addEventListener('submit', handleEditAdminReplySubmit);
}

function handleEditAdminReplySubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Updating...';
    
    fetch('../backend/admin-reply-review.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeEditReplyModal();
                location.reload();
            } else {
                alert(data.message || 'Failed to update reply');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating reply');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
}

function closeEditReplyModal() {
    const modal = document.querySelector('.review-edit-modal');
    if (modal) {
        modal.remove();
    }
}

// Helper function to get CSRF token
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : '';
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
        closeReplyModal();
    }
});
// ========================================
// SUBMIT NEW REVIEW
// ========================================

document.addEventListener('DOMContentLoaded', function () {
    const reviewForm = document.getElementById('reviewForm');
    if (!reviewForm) return;

    reviewForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const submitBtn = reviewForm.querySelector('.submit-review-btn');
        const originalText = submitBtn ? submitBtn.textContent : 'Submit Review';

        // Validate rating
        const rating = reviewForm.querySelector('input[name="rating"]:checked');
        if (!rating) {
            showReviewError('Please select a star rating.');
            return;
        }

        // Validate comment
        const comment = reviewForm.querySelector('textarea[name="comment"]').value.trim();
        if (comment.length < 10) {
            showReviewError('Your review must be at least 10 characters.');
            return;
        }
        if (comment.length > 1000) {
            showReviewError('Your review must not exceed 1000 characters.');
            return;
        }

        // Disable button during submit
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        }

        clearReviewError();

        const formData = new FormData(reviewForm);

        fetch('../backend/submit-review.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Replace the form with a thank-you message then reload
                const writeSection = reviewForm.closest('.write-review-section');
                if (writeSection) {
                    writeSection.innerHTML = `
                        <div class="already-reviewed" style="text-align:center; padding: 1rem;">
                            <p>✓ ${data.message}</p>
                        </div>`;
                }
                // Reload after short delay so user sees the message
                setTimeout(() => location.reload(), 1500);
            } else {
                showReviewError(data.message || 'Failed to submit review. Please try again.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        })
        .catch(() => {
            showReviewError('A network error occurred. Please try again.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    });
});

function showReviewError(message) {
    let errEl = document.getElementById('review-form-error');
    if (!errEl) {
        errEl = document.createElement('p');
        errEl.id = 'review-form-error';
        errEl.style.cssText = 'color:#ef4444; font-size:0.85rem; margin-top:0.5rem; font-weight:600;';
        const form = document.getElementById('reviewForm');
        if (form) form.appendChild(errEl);
    }
    errEl.textContent = message;
}

function clearReviewError() {
    const errEl = document.getElementById('review-form-error');
    if (errEl) errEl.textContent = '';
}

// ========================================
// ADMIN DELETE REVIEW
// ========================================

function adminDeleteReview(reviewId) {
    if (!confirm('Remove this review? This cannot be undone.')) return;

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('review_id', reviewId);

    fetch('../backend/delete-review.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the review card from the DOM instantly
            const reviewEl = document.querySelector(`[data-review-id="${reviewId}"]`);
            if (reviewEl) {
                const item = reviewEl.closest('.review-item');
                if (item) {
                    item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    item.style.opacity = '0';
                    item.style.transform = 'translateY(-8px)';
                    setTimeout(() => { item.remove(); }, 300);
                }
            } else {
                location.reload();
            }
            if (typeof showToast === 'function') showToast('Review removed.', 'success');
        } else {
            alert(data.message || 'Failed to remove review.');
        }
    })
    .catch(() => alert('A network error occurred. Please try again.'));
}

// ========================================
// ADMIN DELETE STORE OWNER REPLY
// ========================================

function deleteAdminReply(reviewId) {
    if (!confirm('Delete the Store Owner\'s reply? This cannot be undone.')) return;

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('review_id', reviewId);

    fetch('../backend/delete-admin-reply.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the reply block from DOM without full reload
            const reviewEl = document.querySelector(`[data-review-id="${reviewId}"]`);
            const replySection = reviewEl
                ? reviewEl.closest('.review-item')?.querySelector('.admin-reply-section')
                : null;

            if (replySection) {
                replySection.style.transition = 'opacity 0.3s ease';
                replySection.style.opacity = '0';
                setTimeout(() => {
                    replySection.remove();
                    // Show the "Reply as Store Owner" button again
                    const actions = reviewEl.closest('.review-item')?.querySelector('.review-actions');
                    if (actions && !actions.querySelector('.reply-btn')) {
                        const replyBtn = document.createElement('button');
                        replyBtn.className = 'review-action-btn reply-btn';
                        replyBtn.setAttribute('onclick', `replyToReview(${reviewId})`);
                        replyBtn.innerHTML = 'Reply as Store Owner';
                        actions.insertBefore(replyBtn, actions.firstChild);
                    }
                }, 300);
            } else {
                location.reload();
            }

            if (typeof showToast === 'function') showToast('Reply deleted.', 'success');
        } else {
            alert(data.message || 'Failed to delete reply.');
        }
    })
    .catch(() => alert('A network error occurred. Please try again.'));
}