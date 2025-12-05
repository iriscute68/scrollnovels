<?php
// story/create.php - Create new story
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../components/navbar.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php?return=/story/create.php');
    exit;
}

$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Story | Scroll Novels</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 0.75rem; border: 1px solid #d4af37; 
            border-radius: 0.375rem; background: rgba(18, 10, 42, 0.9); color: #fff;
            font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; border-color: #f0e68c; box-shadow: 0 0 10px rgba(212, 175, 55, 0.2);
        }
        .checkbox-group { display: flex; gap: 1rem; flex-wrap: wrap; }
        .checkbox-item { display: flex; align-items: center; gap: 0.5rem; }
        .checkbox-item input { margin: 0; }
    </style>
</head>
<body class="bg-background text-foreground">
    <?php render_navbar(); ?>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold mb-8">📖 Start Your Story</h1>

            <form id="storyForm" class="card p-8 space-y-6">
                <!-- Title -->
                <div class="form-group">
                    <label for="title">Story Title *</label>
                    <input type="text" id="title" name="title" placeholder="Enter an engaging title"
                        maxlength="200" required>
                    <small class="text-muted-foreground">Max 200 characters</small>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="description">Synopsis *</label>
                    <textarea id="description" name="description" rows="4" 
                        placeholder="Describe your story in a few sentences..."
                        maxlength="1000" required></textarea>
                    <small class="text-muted-foreground">Max 1000 characters</small>
                </div>

                <!-- Genre -->
                <div class="form-group">
                    <label>Genre *</label>
                    <select name="genre" required>
                        <option value="">Select a genre</option>
                        <option value="Fantasy">Fantasy</option>
                        <option value="Romance">Romance</option>
                        <option value="Mystery">Mystery</option>
                        <option value="Science Fiction">Science Fiction</option>
                        <option value="Thriller">Thriller</option>
                        <option value="Historical">Historical</option>
                        <option value="Horror">Horror</option>
                        <option value="Comedy">Comedy</option>
                        <option value="Drama">Drama</option>
                        <option value="Adventure">Adventure</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Cover Image -->
                <div class="form-group">
                    <label for="cover">Cover Image</label>
                    <input type="file" id="cover" name="cover" accept="image/*">
                    <small class="text-muted-foreground">PNG, JPG. Max 5MB</small>
                </div>

                <!-- Maturity Rating -->
                <div class="form-group">
                    <label>Maturity Rating *</label>
                    <select name="maturity_rating" required>
                        <option value="G">✓ General - All ages</option>
                        <option value="PG">PG - Some content may be unsuitable for children</option>
                        <option value="PG-13">PG-13 - Some material may be inappropriate</option>
                        <option value="R">R - Restricted, some adult content</option>
                        <option value="NC-17">NC-17 - Adult only</option>
                    </select>
                </div>

                <!-- Story Tags -->
                <div class="form-group">
                    <label for="tags">Tags (comma-separated)</label>
                    <input type="text" id="tags" name="tags" 
                        placeholder="e.g., magic, adventure, enemies-to-lovers"
                        maxlength="500">
                </div>

                <!-- Options -->
                <div class="form-group space-y-3">
                    <div class="checkbox-item">
                        <input type="checkbox" id="allow_comments" name="allow_comments" checked>
                        <label for="allow_comments" style="margin-bottom: 0;">Allow comments</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="is_competition_eligible" name="is_competition_eligible">
                        <label for="is_competition_eligible" style="margin-bottom: 0;">
                            Eligible for competitions
                        </label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="has_paywall" name="has_paywall">
                        <label for="has_paywall" style="margin-bottom: 0;">
                            Enable paywall (charge per chapter)
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 justify-end">
                    <a href="/stories/" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Story</button>
                </div>
            </form>
        </div>
    </main>

    <script>
    document.getElementById('storyForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);
        
        // Handle file upload
        if (formData.get('cover').size > 0) {
            const file = formData.get('cover');
            if (file.size > 5 * 1024 * 1024) {
                alert('Cover image must be under 5MB');
                return;
            }
        }

        const data = {
            title: formData.get('title'),
            description: formData.get('description'),
            genre: formData.get('genre'),
            maturity_rating: formData.get('maturity_rating'),
            tags: formData.get('tags'),
            allow_comments: formData.has('allow_comments') ? 1 : 0,
            is_competition_eligible: formData.has('is_competition_eligible') ? 1 : 0,
            has_paywall: formData.has('has_paywall') ? 1 : 0
        };

        try {
            const res = await fetch('/ajax/create_story.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await res.json();
            if (result.ok) {
                alert('Story created! Add your first chapter to get started.');
                window.location.href = '/story/chapter_edit.php?story_id=' + result.story_id;
            } else {
                alert('Error: ' + (result.message || 'Unknown error'));
            }
        } catch (err) {
            alert('Error creating story: ' + err.message);
        }
    });
    </script>
</body>
</html>
