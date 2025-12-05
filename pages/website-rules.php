<?php
$page_title = "Rules & Guidelines - Scroll Novels";
$currentPage = 'rules';
// Try to load editable rules from site_contents table (admin/settings.php)
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
try {
    $s = $pdo->prepare('SELECT v FROM site_contents WHERE k = ? LIMIT 1');
    $s->execute(['website_rules']);
    $editable_rules = $s->fetchColumn();
} catch (Exception $e) {
    $editable_rules = null;
}
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="max-w-7xl mx-auto px-4 py-12">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
        <h1 class="text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">Rules & Guidelines</h1>

        <!-- Rules Categories -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-emerald-50 dark:bg-emerald-900/30 p-6 rounded-lg border border-emerald-200 dark:border-emerald-800">
                <div class="text-3xl mb-4">📝</div>
                <h2 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-2">Content Guidelines</h2>
                <ul class="space-y-2 text-gray-700 dark:text-gray-300">
                    <li>• Original content only</li>
                    <li>• No plagiarism</li>
                    <li>• No explicit sexual content unless marked as 18+</li>
                    <li>• Clear content warnings</li>
                    <li>• Prioritize stories with smart or strong female leads</li>
                    <li>• Most protagonists should be women; male-lead stories are allowed but fewer and with limited romance focus</li>
                </ul>
            </div>

            <div class="bg-emerald-50 dark:bg-emerald-900/30 p-6 rounded-lg border border-emerald-200 dark:border-emerald-800">
                <div class="text-3xl mb-4">🤝</div>
                <h2 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-2">Community Rules</h2>
                <ul class="space-y-2 text-gray-700 dark:text-gray-300">
                    <li>• Be respectful to all members</li>
                    <li>• No harassment or bullying</li>
                    <li>• Constructive feedback only</li>
                    <li>• Report violations promptly</li>
                </ul>
            </div>

            <div class="bg-emerald-50 dark:bg-emerald-900/30 p-6 rounded-lg border border-emerald-200 dark:border-emerald-800">
                <div class="text-3xl mb-4">📚</div>
                <h2 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-2">Content We Love</h2>
                <ul class="space-y-2 text-gray-700 dark:text-gray-300">
                    <li>• Smart, cunning female leads</li>
                    <li>• Plot-focused FL stories (less romance)</li>
                    <li>• FLs with clear goals & ambitions</li>
                    <li>• Strong character development</li>
                </ul>
            </div>

            <div class="bg-emerald-50 dark:bg-emerald-900/30 p-6 rounded-lg border border-emerald-200 dark:border-emerald-800">
                <div class="text-3xl mb-4">💼</div>
                <h2 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-2">Professional Conduct</h2>
                <ul class="space-y-2 text-gray-700 dark:text-gray-300">
                    <li>• Honor commitments</li>
                    <li>• Clear communication</li>
                    <li>• Timely responses</li>
                    <li>• Professional behavior</li>
                </ul>
            </div>
        </div>

        <!-- Detailed Guidelines: prefer editable content from admin/settings.php -->
        <div class="space-y-6">
            <?php if (!empty($editable_rules)): ?>
                <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
                    <?= $editable_rules ?>
                </div>
            <?php else: ?>
                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">A. GENERAL COMMUNITY RULES</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• All users must respect each other at all times.</p>
                        <p>• Harassment, bullying, or targeted abuse is prohibited.</p>
                        <p>• No hate speech against any race, gender, nationality, religion, or identity.</p>
                        <p>• Users must not impersonate admins, moderators, or authors.</p>
                        <p>• No spamming or flooding comment sections.</p>
                        <p>• No excessive self-promotion outside designated areas.</p>
                        <p>• No posting malicious links, phishing pages, or harmful files.</p>
                        <p>• Users must not manipulate website algorithms through bots or fake accounts.</p>
                        <p>• No multi-account abuse to gain unfair advantages.</p>
                        <p>• Do not threaten or encourage violence, harm, or self-harm.</p>
                        <p>• Do not reveal another user's private or personal information.</p>
                        <p>• No doxxing, stalking, or collecting personal data of others.</p>
                        <p>• Report any harmful or suspicious activity to moderators.</p>
                        <p>• Follow the directions of moderators without argument.</p>
                        <p>• Evading bans or penalties using new accounts is forbidden.</p>
                        <p>• Respect all copyright and intellectual property laws.</p>
                        <p>• Do not attempt to hack, exploit, or reverse engineer the website.</p>
                        <p>• No automated scraping of books or chapters.</p>
                        <p>• Avoid excessive profanity in public areas not marked NSFW.</p>
                        <p>• Users under 16 are not allowed; users 13–17 must follow safe content rules.</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">B. AUTHOR RULES – GENERAL WRITING CONDUCT</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• Authors must publish only content they own or have rights to.</p>
                        <p>• Plagiarism and fully writing with ai is strictly forbidden and will result in account termination.</p>
                        <p>• Authors must not repost stories stolen from other platforms.</p>
                        <p>• Authors must not copy another author's characters without permission.</p>
                        <p>• Any collaboration must list all contributors clearly.</p>
                        <p>• Authors must keep chapter updates honest — no fake "updates" with no content.</p>
                        <p>• Authors should respond respectfully to readers.</p>
                        <p>• No attacking or insulting readers in author notes or comments.</p>
                        <p>• Author profiles must not contain offensive or hateful imagery.</p>
                        <p>• Authors must not use their position to harass or control fans.</p>
                        <p>• Ghostwriting is allowed only with full transparency in author notes.</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">C. CONTENT RULES – TAGGING & ACCURACY</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• No misleading tags. Content should match tags accurately.</p>
                        <p>• NSFW content must be properly labeled.</p>
                        <p>• Authors must not use tags to mislead readers into clicking.</p>
                        <p>• All warnings (violence, gore, sexual themes) must be clearly posted.</p>
                        <p>• Authors must not encourage illegal activities.</p>
                        <p>• Authors must not use real individuals' names without consent.</p>
                        <p>• Paid content must not be misleading or incomplete.</p>
                        <p>• Stories that are dropped or discontinued should be marked as such.</p>
                        <p>• Author notes should not exceed chapter content in length.</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">D. PROHIBITED CONTENT (STRICT GUIDELINES)</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p><strong>These are banned across the entire platform:</strong></p>
                        <p>• Any sexual content involving minors (loli/shota)</p>
                        <p>• Any depiction of pedophilia, grooming, or minor exploitation</p>
                        <p>• Glorification, fetishization, or erotic portrayal of rape or sexual violence</p>
                        <p>• Depictions of rape may appear only as non-erotic, condemnable narrative events with warnings</p>
                        <p>• No sexual violence used for "fan service" or arousal</p>
                        <p>• No bestiality or sexual content involving animals</p>
                        <p>• No necrophilia</p>
                        <p>• No incest used as erotic material</p>
                        <p>• No real-world hate propaganda</p>
                        <p>• No extremism, terrorist praise, or recruitment themes</p>
                        <p>• No graphic gore created for fetish or shock value</p>
                        <p>• No fetish content framed as a story (inflation, vore, etc.)</p>
                        <p>• No underage pregnancy used sexually</p>
                        <p>• No sexual transformation of minors</p>
                        <p>• No sexualized AI/robot child models</p>
                        <p>• No snuff, torture porn, or extreme shock erotica</p>
                        <p>• No "revenge porn" style stories</p>
                        <p>• No real-world personal revenge lists disguised as fiction</p>
                        <p>• No explicit sexual content in stories marked under 18+</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">E. ALLOWED BUT RESTRICTED CONTENT (MUST BE TAGGED)</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• Non-erotic romance involving characters 18+</p>
                        <p>• Fade-to-black adult scenes</p>
                        <p>• Strong language (tag: "Strong Language")</p>
                        <p>• Moderate violence (tag: "Violence")</p>
                        <p>• Heavy gore (tag: "Gore")</p>
                        <p>• Psychological trauma themes</p>
                        <p>• Mental health struggles</p>
                        <p>• Horror themes</p>
                        <p>• Dark fantasy with mature elements</p>
                        <p>• Non-graphic depictions of death</p>
                        <p>• War and political drama if fictional</p>
                        <p>• Drug use (tag: "Substance Use")</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">E.1. HIGHLY RECOMMENDED CONTENT</h3>
                    <p class="text-gray-700 dark:text-gray-300 mb-4">We encourage authors to create and promote stories featuring the following themes and protagonists:</p>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p><strong>Primary Focus - Strong Female Protagonists (Non-Romance):</strong></p>
                        <p>• Fantasy, adventure, and action stories with female leads (high priority)</p>
                        <p>• Sci-fi and dystopian stories with strong female protagonists</p>
                        <p>• Mystery, thriller, and crime stories with female leads</p>
                        <p>• Coming-of-age stories centered on girls' journeys and experiences</p>
                        <p>• Stories featuring women in leadership and powerful positions</p>
                        <p>• Stories exploring women's personal growth and self-discovery</p>
                        <p>• Stories with minimal romance focus (plot-driven, not romance-driven)</p>
                        
                        <p><strong>Secondary Focus - LGBTQ+ Representation:</strong></p>
                        <p>• LGBTQ+ fantasy, adventure, and drama with diverse characters</p>
                        <p>• Sapphic fiction (lesbian/WLW - Women Loving Women) with depth beyond romance</p>
                        <p>• GL/Yuri fiction (Girls' Love) exploring diverse relationships</p>
                        <p>• LGBTQ+ stories with strong character development</p>
                        
                        <p><strong>Also Welcome - Male Lead Stories:</strong></p>
                        <p>• Male protagonist stories are allowed but secondary to female-focused content</p>
                        <p>• Encourage male leads in non-traditional roles and genres</p>
                        
                        <p><strong>Why we recommend these:</strong></p>
                        <p>• They provide strong, diverse role models for all readers</p>
                        <p>• They help build a more inclusive community</p>
                        <p>• They celebrate underrepresented voices and perspectives</p>
                        <p>• They offer readers complex, character-driven narratives</p>
                        <p>• They attract readers seeking stories beyond typical romance</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">F. FANFICTION RULES</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• Only write fanfiction for fictional series you admire.</p>
                        <p>• No fanfiction about real celebrities, real influencers, or real people.</p>
                        <p>• Fanfiction must clearly state the original universe.</p>
                        <p>• Authors must not claim ownership of characters they did not create.</p>
                        <p>• Tag fanfics correctly (Fanfic / Crossover).</p>
                        <p>• Crossovers must list both universes involved.</p>
                        <p>• No smut using underage characters from an existing franchise.</p>
                        <p>• NSFW fanfiction must only include adult characters (18+).</p>
                        <p>• No rewriting real events or tragedies for fanfic.</p>
                        <p>• Do not defame original authors or creators in your notes.</p>
                        <p>• Fanfic must transform or expand the source material — not copy it verbatim.</p>
                        <p>• Do not copy chapters from existing novels or manga.</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">G. AI CONTENT RULES (IMPORTANT)</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p><strong>No AI-generated stories or chapters are allowed.</strong></p>
                        <p>AI-assisted writing is allowed only for:</p>
                        <p>• Grammar correction</p>
                        <p>• Sentence polishing</p>
                        <p>• Idea brainstorming</p>
                        <p>• Rewriting what YOU wrote</p>
                        <p><strong>You must write the main story content yourself.</strong></p>
                        <p>• If AI was used for small edits, you must disclose "Edited with AI assistance."</p>
                        
                        <p class="mt-4"><strong>📷 BOOK COVER SUGGESTIONS:</strong></p>
                        <p>• We recommend hiring artists for your book cover images</p>
                        <p>• Less use of real people for book covers is encouraged</p>
                        <p>• Consider using game character design tools like <strong>Infinity Nikki</strong>, <strong>Where Winds Meet</strong>, and <strong>Love and Deepspace</strong> to design and edit your covers</p>
                        <p>• Visit our <a href="https://vgen.co/StudioSoulo" target="_blank" class="text-emerald-600 dark:text-emerald-400 underline hover:text-emerald-700">🎨 Find Artist</a> page to connect with our website artists at <strong>Studio Soulo</strong> for professional cover commissions</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">H. COMMENT & REVIEW RULES</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• No review bombing.</p>
                        <p>• No coordinated harassment campaigns.</p>
                        <p>• Reviews must address the story—not the author personally.</p>
                        <p>• No leaving sexual or violent threats in comments.</p>
                        <p>• No posting spoilers without spoiler tags.</p>
                        <p>• No obscene ASCII art or copypastas.</p>
                        <p>• No spamming the author for updates.</p>
                        <p>• Constructive criticism is allowed; insults are not.</p>
                        <p>• Do not promote your story in someone else's comment section.</p>
                        <p>• No heated arguments—seek moderation help.</p>
                        <p>• Refrain from political or religious arguments.</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">I. PROFILE & AVATAR RULES</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• No explicit profile pictures.</p>
                        <p>• No gore or disturbing avatars.</p>
                        <p>• No impersonation of staff or authors.</p>
                        <p>• No usernames with slurs or hateful phrases.</p>
                        <p>• No promoting illegal activities in your bio.</p>
                        <p>• No sexually suggestive content visible to minors.</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">J. PUBLISHING RULES FOR BOOKS</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• Titles must not contain hate speech or sexual content.</p>
                        <p>• Book covers must be SFW or censored versions.</p>
                        <p>• Tags must match your content.</p>
                        <p>• Chapters must contain actual writing—not placeholders.</p>
                        <p>• No spamming empty chapters to boost stats.</p>
                        <p>• No reposting the same story multiple times.</p>
                        <p>• Books must be placed in the correct genre.</p>
                        <p>• All major content warnings must be added to the description.</p>
                        <p>• You must update your tags if the direction of the story changes.</p>
                        <p>• No misleading readers with fake chapter titles.</p>
                        <p>• No abusing the trending system.</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">K. NSFW RULES</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• Only adults (18+) may publish or read NSFW works.</p>
                        <p>• NSFW content must be behind an 18+ warning page.</p>
                        <p>• No sharing explicit content with minor accounts.</p>
                        <p>• No extreme or illegal sexual content (see prohibited section).</p>
                        <p>• Authors must mark NSFW scenes clearly.</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">L. MODERATION GUIDELINES (FOR STAFF)</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• Mods may remove any story violating safety rules.</p>
                        <p>• Mods may suspend accounts that endanger the community.</p>
                        <p>• Mods must remain neutral and treat all users fairly.</p>
                        <p>• Mods may delete hateful or illegal content immediately.</p>
                        <p>• Mods may request proof of authorship in plagiarism cases.</p>
                        <p>• Mods may lock threads if arguments escalate.</p>
                        <p>• Mods may restrict NSFW areas to verified adults only.</p>
                    </div>
                </div>

                <div class="border-b border-emerald-200 dark:border-emerald-800 pb-6">
                    <h3 class="text-xl font-semibold text-emerald-700 dark:text-emerald-400 mb-4">M. PENALTIES FOR VIOLATIONS</h3>
                    <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 space-y-2 text-sm">
                        <p>• Warning for minor offenses.</p>
                        <p>• Temporary suspension for harassment or spam.</p>
                        <p>• Removal of stories violating content rules.</p>
                        <p>• Permanent ban for pedophilia, rape glorification, or extremism.</p>
                        <p>• Permanent ban for plagiarism.</p>
                        <p>• Permanent ban for AI-written stories disguised as original.</p>
                        <p>• Permanent ban for repeated rule-breaking.</p>
                        <p>• Legal action may be pursued for severe copyright abuse.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Links -->
        <div class="mt-8 flex flex-wrap gap-4">
            <a href="<?= site_url('/pages/website-rules.php') ?>" class="inline-flex items-center px-4 py-2 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 rounded-lg hover:bg-emerald-200 dark:hover:bg-emerald-800/50 transition duration-300">
                <span class="mr-2">📋</span> Full Guidelines
            </a>
            <a href="<?= site_url('/pages/contact.php?report=violation') ?>" class="inline-flex items-center px-4 py-2 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 rounded-lg hover:bg-emerald-200 dark:hover:bg-emerald-800/50 transition duration-300">
                <span class="mr-2">⚠️</span> Report Violation
            </a>
            <a href="<?= site_url('/pages/contact.php') ?>" class="inline-flex items-center px-4 py-2 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 rounded-lg hover:bg-emerald-200 dark:hover:bg-emerald-800/50 transition duration-300">
                <span class="mr-2">💬</span> Contact Support
            </a>
        </div>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
