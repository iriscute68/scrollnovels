<?php
// pages/become-verified.php - Form for requesting verification

session_start();
require_once dirname(__FILE__) . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Ensure verification_requests table exists with full schema
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS verification_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        verification_type ENUM('artist') NOT NULL,
        description LONGTEXT NOT NULL,
        proof_images JSON,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        reviewed_by INT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_user_id (user_id),
        INDEX idx_type (verification_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // Table already exists
}

// Ensure author_profiles table exists for storing profile data
try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS author_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        bio TEXT,
        specialties VARCHAR(500),
        service_rate DECIMAL(10, 2),
        avg_rating DECIMAL(3, 2) DEFAULT 0,
        total_ratings INT DEFAULT 0,
        portfolio_items JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_rating (avg_rating)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // Table already exists
}

// Get current verification status
$stmt = $pdo->prepare("
    SELECT * FROM verification_requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$requests = $stmt->fetchAll();

?>
<?php
    $page_title = 'Become Verified - ' . (defined('SITE_NAME') ? SITE_NAME : 'Scroll Novels');
    require_once __DIR__ . '/../includes/header.php';
?>

<style>
.verification-container {
    max-width: 900px;
    margin: 40px auto;
    padding: 20px;
}

.verification-header {
    text-align: center;
    margin-bottom: 40px;
}

.verification-header h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #1a1a1a;
    font-weight: bold;
}

.verification-header h1 {
    color: #059669;
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.verification-header p {
    font-size: 1.1rem;
    color: #666;
}

.verification-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 2px solid #e0e0e0;
}

.tab-btn {
    padding: 12px 20px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 16px;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    color: #333;
    background: #f5f5f5;
}

.tab-btn.active {
    color: #059669;
    border-bottom-color: #059669;
    font-weight: bold;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.form-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.info-box {
    background: #f0f9ff;
    border-left: 4px solid #059669;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 4px;
}

.info-box h3 {
    margin-top: 0;
    color: #059669;
    font-size: 1.2rem;
    margin-bottom: 15px;
}

.info-box ul {
    margin: 0;
    padding-left: 20px;
    color: #333;
}

.info-box li {
    margin-bottom: 8px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group textarea,
.form-group input[type="text"],
.form-group input[type="email"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
    font-size: 16px;
    box-sizing: border-box;
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #999;
    font-size: 0.9rem;
}

.file-upload-area {
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #fafafa;
}

.file-upload-area:hover {
    border-color: #059669;
    background: #f0f9ff;
}

.file-upload-area.dragover {
    border-color: #059669;
    background: #e0f7f1;
}

.file-upload-area p {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 18px;
    font-weight: 500;
}

.file-upload-area small {
    color: #999;
    display: block;
}

.uploaded-files {
    margin-top: 15px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.file-item {
    display: inline-block;
    background: #f0f9ff;
    border: 1px solid #059669;
    padding: 10px 15px;
    border-radius: 4px;
    font-size: 0.9rem;
    color: #333;
}

.file-item .remove {
    margin-left: 10px;
    cursor: pointer;
    color: #d32f2f;
    font-weight: bold;
}

.submit-btn {
    background: #059669;
    color: white;
    border: none;
    padding: 14px 30px;
    border-radius: 4px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
}

.submit-btn:hover {
    background: #047857;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

.submit-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.loading {
    display: none;
    text-align: center;
    padding: 20px;
    color: #059669;
}

.loading.active {
    display: block;
}

.message {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    display: none;
}

.message.success {
    display: block;
    background: #e8f5e9;
    border: 1px solid #4caf50;
    color: #2e7d32;
}

.message.error {
    display: block;
    background: #ffebee;
    border: 1px solid #f44336;
    color: #c62828;
}

.request-status {
    background: #f5f5f5;
    border-left: 4px solid #ddd;
    padding: 20px;
    margin-bottom: 15px;
    border-radius: 4px;
}

.request-status.approved {
    border-left-color: #4caf50;
    background: #e8f5e9;
}

.request-status.rejected {
    border-left-color: #f44336;
    background: #ffebee;
}

.request-status.pending {
    border-left-color: #ff9800;
    background: #fff3e0;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: bold;
    margin-left: 10px;
}

.status-badge.approved {
    background: #4caf50;
    color: white;
}

.status-badge.rejected {
    background: #f44336;
    color: white;
}

.status-badge.pending {
    background: #ff9800;
    color: white;
}

@media (max-width: 768px) {
    .verification-container {
        padding: 10px;
    }
    
    .verification-header h1 {
        font-size: 1.8rem;
    }
    
    .verification-tabs {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        flex: 1;
        min-width: 100px;
    }
    
    .form-card {
        padding: 20px;
    }
}
</style>

    <div class="verification-container">
        <div class="verification-header">
            <h1>Become Verified</h1>
            <p>Showcase your credentials and get verified as an artist</p>
        </div>

        <div class="verification-tabs">
            <button class="tab-btn active" data-tab="artist">Verify as Artist</button>
            <button class="tab-btn" data-tab="status">Request History</button>
        </div>

        <!-- Artist Verification Tab -->
        <div id="artist" class="tab-content active">
            <div class="form-card">
                <div class="info-box">
                    <h3>⭐ Verified Artist Benefits</h3>
                    <ul>
                        <li>Get a verified badge on your profile</li>
                        <li>Increased visibility in artist directory</li>
                        <li>Access to artist-exclusive features</li>
                        <li>Higher priority support</li>
                    </ul>
                </div>

                <form id="artistForm" class="verification-form" data-type="artist">
                    <div class="message" id="artistMessage"></div>

                    <div class="form-group">
                        <label for="artist_bio">About Your Work</label>
                        <textarea id="artist_bio" name="description" placeholder="Tell us about your art style, experience, and why you should be verified..." style="background: #fff; color: #333; border: 1px solid #ddd; padding: 10px; border-radius: 5px; min-height: 120px; font-family: Arial; font-size: 14px; width: 100%; box-sizing: border-box;" required></textarea>
                        <small>Minimum 50 characters</small>
                    </div>

                    <div class="form-group">
                        <label>Proof of Your Work</label>
                        <div class="file-upload-area" id="artistDropZone">
                            <p>📁 Click to upload or drag files here</p>
                            <small>Upload artwork samples, portfolio links, or credentials (JPG, PNG, GIF, PDF - Max 5MB each)</small>
                        </div>
                        <input type="file" id="artistFiles" name="proof_images" multiple accept="image/*,.pdf" style="display: none;">
                        <div class="uploaded-files" id="artistFilesList"></div>
                    </div>

                    <button type="submit" class="submit-btn">Submit Artist Verification Request</button>
                    <div class="loading" id="artistLoading">
                        <p>Submitting your request...</p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Status Tab -->
        <div id="status" class="tab-content">
            <div class="form-card">
                <h2>Your Verification Request History</h2>
                <?php if (count($requests) > 0): ?>
                    <?php foreach ($requests as $req): ?>
                        <div class="request-status <?php echo $req['status']; ?>">
                            <strong>
                                <?php echo ucfirst($req['verification_type']); ?> Verification
                                <span class="status-badge <?php echo $req['status']; ?>">
                                    <?php echo ucfirst($req['status']); ?>
                                </span>
                            </strong>
                            <p style="margin: 10px 0 5px 0; color: #666;">
                                Submitted on <?php echo date('F j, Y', strtotime($req['created_at'])); ?>
                            </p>
                            <?php if ($req['status'] !== 'pending' && !empty($req['admin_notes'])): ?>
                                <p style="margin: 5px 0 0 0; color: #333;">
                                    <strong>Notes:</strong> <?php echo htmlspecialchars($req['admin_notes']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 40px 0;">
                        You haven't submitted any verification requests yet. Start above to get verified!
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(tabName).classList.add('active');
            });
        });

        // File upload handling
        function setupFileUpload(formId, dropZoneId, fileInputId, filesListId) {
            const form = document.getElementById(formId);
            const dropZone = document.getElementById(dropZoneId);
            const fileInput = document.getElementById(fileInputId);
            const filesList = document.getElementById(filesListId);
            let uploadedFiles = [];

            // Click to upload
            dropZone.addEventListener('click', () => fileInput.click());

            // Drag and drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });

            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
            });

            function handleFiles(files) {
                for (let file of files) {
                    if (uploadedFiles.length >= 5) {
                        alert('Maximum 5 files allowed');
                        break;
                    }
                    uploadedFiles.push(file);
                }
                updateFilesList();
            }

            function updateFilesList() {
                filesList.innerHTML = '';
                uploadedFiles.forEach((file, index) => {
                    const div = document.createElement('div');
                    div.className = 'file-preview';

                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            div.appendChild(img);
                        };
                        reader.readAsDataURL(file);
                    } else {
                        div.innerHTML = '<p style="padding: 20px; text-align: center;">📄 ' + file.name + '</p>';
                    }

                    const removeBtn = document.createElement('button');
                    removeBtn.className = 'file-remove';
                    removeBtn.innerHTML = '✕';
                    removeBtn.type = 'button';
                    removeBtn.onclick = (e) => {
                        e.preventDefault();
                        uploadedFiles.splice(index, 1);
                        updateFilesList();
                    };
                    div.appendChild(removeBtn);
                    filesList.appendChild(div);
                });
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const description = form.querySelector('textarea').value;
                if (description.length < 50) {
                    showMessage(formId + 'Message', 'Please provide at least 50 characters', 'error');
                    return;
                }

                // Show confirmation popup
                const verificationType = form.dataset.type.charAt(0).toUpperCase() + form.dataset.type.slice(1);
                const confirmMessage = `Confirm your ${verificationType} verification request?\n\nPlease review the following:\n\n✓ Your description and proof documents\n✓ Verification type: ${verificationType}\n\nOnce submitted, moderators will review your request within 48 hours.`;
                
                if (!confirm(confirmMessage)) {
                    return; // User cancelled
                }

                const loading = document.getElementById(formId.replace('Form', '') + 'Loading');
                const message = document.getElementById(formId.replace('Form', '') + 'Message');
                loading.classList.add('show');

                const formData = new FormData();
                formData.append('action', 'submit_request');
                formData.append('verification_type', form.dataset.type);
                formData.append('description', description);
                uploadedFiles.forEach(file => {
                    formData.append('proof_images[]', file);
                });

                try {
                    const response = await fetch('<?php echo SITE_URL; ?>/api/request-verification.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    loading.classList.remove('show');

                    if (data.success) {
                        showMessage(formId + 'Message', data.message, 'success');
                        form.reset();
                        uploadedFiles = [];
                        updateFilesList();
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showMessage(formId + 'Message', data.message, 'error');
                    }
                } catch (err) {
                    loading.classList.remove('show');
                    showMessage(formId + 'Message', 'An error occurred. Please try again.', 'error');
                }
            });
        }

        function showMessage(elementId, text, type) {
            const msg = document.getElementById(elementId);
            msg.textContent = text;
            msg.className = 'message show ' + type;
            setTimeout(() => msg.classList.remove('show'), 5000);
        }

        // Initialize file uploads - only artist form now
        setupFileUpload('artistForm', 'artistDropZone', 'artistFiles', 'artistFilesList');
    </script>
</body>
</html>

