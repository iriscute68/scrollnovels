<?php
// Use the full-featured competitions management code
require_once __DIR__ . '/../competitions.php';
?>

<style>
.competitions-container {
    padding: 1.5rem;
}

.competitions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%);
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #d946ef;
}

.competitions-header h2 {
    color: #f3e8ff;
    font-size: 1.8rem;
    margin: 0;
    font-weight: 700;
}

.competitions-header button {
    background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.competitions-header button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.analytics-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid #334155;
}

.analytics-label {
    color: #94a3b8;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.analytics-value {
    color: #e0f2fe;
    font-size: 2rem;
    font-weight: 700;
}

.competitions-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
    gap: 1.5rem;
}

.competition-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid #334155;
    border-radius: 8px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.competition-card:hover {
    border-color: #64748b;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
}

.competition-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
    border-bottom: 1px solid #334155;
    padding-bottom: 1rem;
}

.competition-title {
    color: #f1f5f9;
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
}

.competition-status {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-active {
    background: linear-gradient(135deg, #065f46 0%, #047857 100%);
    color: #d1fae5;
}

.status-closed {
    background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%);
    color: #fee2e2;
}

.stats-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.stat-item {
    background: rgba(51, 65, 85, 0.3);
    padding: 0.75rem;
    border-radius: 6px;
}

.stat-label {
    color: #94a3b8;
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

.stat-value {
    color: #e0f2fe;
    font-size: 1.3rem;
    font-weight: 700;
}

.rating-badge {
    display: inline-block;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #78350f;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-weight: 700;
    margin-top: 0.5rem;
}

.top-book {
    background: rgba(30, 58, 138, 0.3);
    padding: 1rem;
    border-radius: 6px;
    margin-top: 1rem;
    border-left: 3px solid #60a5fa;
}

.top-book-title {
    color: #e0f2fe;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.top-book-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
    color: #cbd5e1;
}

.competition-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.btn-small {
    flex: 1;
    padding: 0.6rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-manage {
    background: #3b82f6;
    color: white;
}

.btn-manage:hover {
    background: #60a5fa;
}

.btn-stats {
    background: #8b5cf6;
    color: white;
}

.btn-stats:hover {
    background: #a78bfa;
}

.btn-end {
    background: #ef4444;
    color: white;
}

.btn-end:hover {
    background: #f87171;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #94a3b8;
}

@media (max-width: 768px) {
    .competitions-header {
        flex-direction: column;
        gap: 1rem;
    }

    .competitions-list {
        grid-template-columns: 1fr;
    }

    .stats-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="competitions-container">
    <!-- Dashboard Analytics -->
    <div style="margin-bottom: 2rem;">
        <h3 style="color: #e0f2fe; margin-top: 0;">Competition Analytics Dashboard</h3>
        <p style="color: #94a3b8;">Track and manage all writing competitions</p>
    </div>

    <div class="analytics-grid">
        <div class="analytics-card">
            <div class="analytics-label">Total Competitions</div>
            <div class="analytics-value">3</div>
        </div>
        <div class="analytics-card">
            <div class="analytics-label">Total Participants</div>
            <div class="analytics-value">4,444</div>
        </div>
        <div class="analytics-card">
            <div class="analytics-label">Total Entries</div>
            <div class="analytics-value">11,703</div>
        </div>
        <div class="analytics-card">
            <div class="analytics-label">Total Engagement</div>
            <div class="analytics-value">1.6M</div>
        </div>
    </div>

    <!-- Header -->
    <div class="competitions-header">
        <h2>🏆 Competitions</h2>
        <button onclick="createCompetition()">+ New Competition</button>
    </div>

    <!-- Competitions Grid -->
    <?php if (count($sample_competitions) > 0): ?>
        <div class="competitions-list">
            <?php foreach ($sample_competitions as $comp): ?>
                <div class="competition-card">
                    <div class="competition-header">
                        <div>
                            <h3 class="competition-title"><?= htmlspecialchars($comp['title']) ?></h3>
                            <span class="competition-status status-<?= $comp['status'] ?>">
                                <?= ucfirst($comp['status']) ?>
                                <?= $comp['status'] === 'active' ? '📈 Trending Up' : '→ Stable' ?>
                            </span>
                        </div>
                    </div>

                    <div class="stats-row">
                        <div class="stat-item">
                            <div class="stat-label">Participants</div>
                            <div class="stat-value"><?= number_format($comp['participants']) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Total Entries</div>
                            <div class="stat-value"><?= number_format($comp['entries']) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Books Entered</div>
                            <div class="stat-value"><?= $comp['books'] ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Avg Rating</div>
                            <div class="stat-value"><?= $comp['avg_rating'] ?>/5.0</div>
                        </div>
                    </div>

                    <div class="stats-row">
                        <div class="stat-item">
                            <div class="stat-label">Total Readers</div>
                            <div class="stat-value"><?= number_format($comp['readers']) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Total Clicks</div>
                            <div class="stat-value"><?= number_format($comp['clicks']) ?></div>
                        </div>
                    </div>

                    <div style="text-align: center; color: #94a3b8; font-size: 0.9rem; margin: 0.5rem 0;">
                        Avg Engagement: <strong style="color: #e0f2fe;"><?= number_format(intval($comp['clicks'] / $comp['books'])) ?></strong> per book
                    </div>

                    <div class="top-book">
                        <h4 class="top-book-title">🏅 Top Performing Book</h4>
                        <div style="color: #e0f2fe; font-weight: 700; margin-bottom: 0.5rem;">
                            <?= htmlspecialchars($comp['top_book']) ?>
                        </div>
                        <div class="top-book-stats">
                            <span>⭐ <?= $comp['top_rating'] ?>/5.0</span>
                            <span>👁️ <?= number_format(intval($comp['readers'] * 0.3)) ?> Readers</span>
                            <span>🔗 <?= number_format(intval($comp['clicks'] * 0.3)) ?> Clicks</span>
                        </div>
                    </div>

                    <div class="competition-actions">
                        <button class="btn-small btn-manage" onclick="manageCompetition(<?= $comp['id'] ?>)">Manage</button>
                        <button class="btn-small btn-stats" onclick="viewStats(<?= $comp['id'] ?>)">Stats</button>
                        <?php if ($comp['status'] === 'active'): ?>
                            <button class="btn-small btn-end" onclick="endCompetition(<?= $comp['id'] ?>)">End</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>No competitions yet. Create your first one!</p>
        </div>
    <?php endif; ?>
</div>

<script>
function createCompetition() {
    alert('Create new competition - feature coming soon');
}

function manageCompetition(id) {
    alert('Manage competition ' + id);
}

function viewStats(id) {
    alert('View stats for competition ' + id);
}

function endCompetition(id) {
    if (confirm('Are you sure you want to end this competition?')) {
        alert('Competition ended');
    }
}
</script>
