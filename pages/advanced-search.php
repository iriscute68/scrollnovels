<?php
// pages/advanced-search.php - Advanced story search page
require_once __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';

$page_title = 'Search Stories';
$query = $_GET['q'] ?? '';
$genre = $_GET['genre'] ?? '';
$sort = $_GET['sort'] ?? 'latest';
$current_page = (int)($_GET['page'] ?? 1);
?>

<link rel="stylesheet" href="<?= asset_url('css/site-theme.compiled.css') ?>">

<main class="max-w-7xl mx-auto p-6">
    <div class="mb-8">
        <h1 class="text-4xl font-bold mb-2 text-emerald-400">Search Stories</h1>
        <p class="text-gray-300">Find your next favorite story</p>
    </div>

    <!-- Search Form -->
    <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 mb-8">
        <form id="search-form" class="space-y-4">
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label for="search-query" class="block text-sm font-medium mb-2 text-gray-300">Search Query</label>
                    <input type="text" id="search-query" name="q" placeholder="Story title, author, keywords..." 
                           value="<?= htmlspecialchars($query) ?>" 
                           class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md focus:ring-2 focus:ring-emerald-500 text-white">
                </div>
                
                <div>
                    <label for="sort" class="block text-sm font-medium mb-2 text-gray-300">Sort By</label>
                    <select id="sort" name="sort" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md focus:ring-2 focus:ring-emerald-500 text-white">
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newly Added</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-700 rounded-md font-semibold text-white transition">
                        🔍 Search
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Section -->
    <div id="results-container" class="space-y-4">
        <div class="text-center py-12 text-gray-500">
            <p>Enter search terms above to find stories</p>
        </div>
    </div>

    <!-- Pagination -->
    <div id="pagination" class="mt-8 flex justify-center gap-2"></div>
</main>

<script>
const searchForm = document.getElementById('search-form');
const resultsContainer = document.getElementById('results-container');
const paginationContainer = document.getElementById('pagination');

async function performSearch(page = 1) {
    const query = document.getElementById('search-query').value.trim();
    const sort = document.getElementById('sort').value;
    
    if (!query) {
        resultsContainer.innerHTML = '<div class="text-center py-12 text-gray-500"><p>Enter search terms to find stories</p></div>';
        return;
    }
    
    resultsContainer.innerHTML = '<div class="text-center py-12"><p class="text-gray-400">Searching...</p></div>';
    
    try {
        const url = new URL('<?= site_url('/api/search-stories-user.php') ?>');
        url.searchParams.set('q', query);
        url.searchParams.set('sort', sort);
        url.searchParams.set('page', page);
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            if (data.data && data.data.length > 0) {
                resultsContainer.innerHTML = `
                    <div class="grid md:grid-cols-3 lg:grid-cols-4 gap-4">
                        ${data.data.map(story => `
                            <a href="<?= site_url('/pages/book.php') ?>?id=${story.id}" class="group">
                                <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden hover:border-emerald-500 transition h-full flex flex-col">
                                    ${story.cover_image ? `<img src="${htmlEscape(story.cover_image)}" alt="" class="w-full h-40 object-cover group-hover:opacity-80 transition">` : '<div class="w-full h-40 bg-gray-700"></div>'}
                                    <div class="p-4 flex flex-col flex-1">
                                        <h3 class="font-bold text-white group-hover:text-emerald-400 mb-1">${htmlEscape(story.title)}</h3>
                                        <p class="text-sm text-gray-400 mb-2">by ${htmlEscape(story.author_name)}</p>
                                        <div class="text-xs text-gray-500 space-y-1 mb-2 flex-1">
                                            <p>Chapters: ${story.chapter_count || 0}</p>
                                            <p>Views: ${story.views || 0}</p>
                                            <p>Rating: ${story.avg_rating ? story.avg_rating.toFixed(1) : 'N/A'} ⭐</p>
                                        </div>
                                        <p class="text-xs text-gray-600 line-clamp-2">${htmlEscape(story.description || 'No description')}</p>
                                    </div>
                                </div>
                            </a>
                        `).join('')}
                    </div>
                `;
                
                // Pagination
                if (data.pagination.pages > 1) {
                    let paginationHtml = '';
                    for (let i = 1; i <= data.pagination.pages; i++) {
                        if (i === page) {
                            paginationHtml += `<span class="px-4 py-2 bg-emerald-600 text-white rounded">${i}</span>`;
                        } else {
                            paginationHtml += `<button onclick="performSearch(${i})" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded transition">${i}</button>`;
                        }
                    }
                    paginationContainer.innerHTML = paginationHtml;
                } else {
                    paginationContainer.innerHTML = '';
                }
            } else {
                resultsContainer.innerHTML = '<div class="text-center py-12 text-gray-500"><p>No stories found. Try different search terms.</p></div>';
                paginationContainer.innerHTML = '';
            }
        } else {
            resultsContainer.innerHTML = '<div class="text-center py-12 text-red-400"><p>Search failed. Please try again.</p></div>';
        }
    } catch (error) {
        console.error('Search error:', error);
        resultsContainer.innerHTML = '<div class="text-center py-12 text-red-400"><p>Error performing search</p></div>';
    }
}

function htmlEscape(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

searchForm.addEventListener('submit', function(e) {
    e.preventDefault();
    performSearch(1);
});

// Auto search if query provided
<?php if ($query): ?>
performSearch(<?= $current_page ?>);
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

