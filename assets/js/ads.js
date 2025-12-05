// assets/js/ads.js - Ad system UI interactions

document.addEventListener('DOMContentLoaded', function() {
    // Package Selection
    initializePackageSelection();
    
    // Proof Upload Preview
    initializeProofPreview();
    
    // Admin Modal
    initializeAdminModal();
});

/**
 * Initialize package selection cards
 */
function initializePackageSelection() {
    const packages = document.querySelectorAll('.package-list .package');
    const packageInput = document.getElementById('packageInput');
    const summaryBox = document.getElementById('packageSummary');
    const psViews = document.getElementById('psViews');
    const psAmount = document.getElementById('psAmount');

    if (!packages.length) return;

    packages.forEach(el => {
        el.addEventListener('click', function() {
            // Deselect all
            packages.forEach(p => p.classList.remove('selected'));
            
            // Select clicked
            this.classList.add('selected');

            const code = this.dataset.value;
            const views = this.dataset.views;
            const amount = this.dataset.amount;

            if (packageInput) {
                packageInput.value = code;
            }

            if (summaryBox) {
                summaryBox.style.display = 'block';
                if (psViews) psViews.textContent = Number(views).toLocaleString();
                if (psAmount) psAmount.textContent = amount;
            }
        });
    });

    // Ensure package selected before submit
    const form = document.getElementById('createAdForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!packageInput || !packageInput.value) {
                e.preventDefault();
                alert('Please select an ad package');
                return false;
            }
        });
    }
}

/**
 * Initialize proof image preview
 */
function initializeProofPreview() {
    const proofInput = document.getElementById('proofInput');
    const previewBox = document.getElementById('previewBox');

    if (!proofInput) return;

    proofInput.addEventListener('change', function() {
        if (previewBox) previewBox.innerHTML = '';
        
        const file = this.files[0];
        if (!file) return;

        // Check file size
        if (file.size > 10 * 1024 * 1024) {
            alert('File too large (max 10MB)');
            this.value = '';
            return;
        }

        // Create preview
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.maxWidth = '200px';
            img.style.borderRadius = '6px';
            img.style.marginTop = '8px';
            if (previewBox) previewBox.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

/**
 * Initialize admin approval modal
 */
function initializeAdminModal() {
    const approveButtons = document.querySelectorAll('.approveBtn');
    const modal = document.getElementById('approveModal');
    const cancelBtn = document.getElementById('cancelApprove');
    const confirmBtn = document.getElementById('confirmApprove');
    const noteField = document.getElementById('adminNote');

    if (!approveButtons.length || !modal) return;

    let selectedAdId = null;

    approveButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            selectedAdId = e.currentTarget.dataset.adid;
            if (noteField) noteField.value = '';
            modal.classList.add('flex');
            modal.classList.remove('hidden');
        });
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', async function() {
            const note = noteField ? noteField.value : '';
            const token = document.querySelector('input[name="_token"]')?.value;

            if (!token) {
                alert('CSRF token missing');
                return;
            }

            try {
                const res = await fetch(`/api/ads/admin-approve.php?id=${selectedAdId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': token
                    },
                    body: new URLSearchParams({ note })
                });

                const json = await res.json();

                if (json.success) {
                    // Remove the ad card from list
                    const adCard = document.querySelector(`[data-adid='${selectedAdId}']`);
                    if (adCard) {
                        adCard.closest('.pending-card')?.remove();
                    }
                    alert(`Ad approved! Boost level: ${json.boost_level}/10`);
                } else {
                    alert('Error: ' + (json.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
    }

    // Close modal on background click
    modal?.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    });
}

/**
 * Submit ad creation form
 */
function submitAdForm() {
    const form = document.getElementById('createAdForm');
    if (!form) return;

    const bookId = document.getElementById('book_id')?.value;
    const packageInput = document.getElementById('packageInput')?.value;

    if (!bookId || !packageInput) {
        alert('Please select a book and package');
        return;
    }

    // Submit form - will POST to /api/ads/create
    form.submit();
}

/**
 * Submit proof upload form
 */
async function submitProofForm() {
    const form = document.getElementById('proofForm');
    if (!form) return;

    const formData = new FormData(form);
    const token = document.querySelector('input[name="_token"]')?.value;

    if (!token) {
        alert('CSRF token missing');
        return;
    }

    try {
        const res = await fetch(form.action || '/api/ads/upload-proof.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token },
            body: formData
        });

        const json = await res.json();

        if (json.success) {
            alert('Proof submitted! Admin will review it shortly.');
            // Reload to show new message
            location.reload();
        } else {
            alert('Error: ' + (json.error || 'Upload failed'));
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

/**
 * Format number with commas
 */
function formatNumber(num) {
    return Number(num).toLocaleString();
}
