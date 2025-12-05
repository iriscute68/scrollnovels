-- Competitions System Database Schema
-- Run this to set up all tables needed for the competitions feature

-- Competitions table
CREATE TABLE IF NOT EXISTS competitions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    banner_image VARCHAR(500),
    theme VARCHAR(100),
    category VARCHAR(50),
    requirements_json JSON,
    prize_info JSON,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    max_participants INT DEFAULT NULL,
    status ENUM('upcoming', 'active', 'ended') DEFAULT 'upcoming',
    judging_type ENUM('public_voting', 'judges', 'hybrid') DEFAULT 'public_voting',
    featured BOOLEAN DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_dates (start_date, end_date),
    KEY idx_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Competition entries (books submitted to competitions)
CREATE TABLE IF NOT EXISTS competition_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    competition_id INT NOT NULL,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    validated BOOLEAN DEFAULT 0,
    validation_notes TEXT,
    score_public FLOAT DEFAULT 0,
    score_judges FLOAT DEFAULT 0,
    total_score FLOAT DEFAULT 0,
    rank INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entry (competition_id, book_id),
    KEY idx_competition (competition_id),
    KEY idx_book (book_id),
    KEY idx_user (user_id),
    KEY idx_status (status),
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES stories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Competition votes/support
CREATE TABLE IF NOT EXISTS competition_votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entry_id INT NOT NULL,
    user_id INT NOT NULL,
    points_spent INT DEFAULT 0,
    vote_value INT DEFAULT 1,
    vote_type ENUM('upvote', 'support', 'favorite') DEFAULT 'upvote',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (entry_id, user_id, vote_type),
    KEY idx_entry (entry_id),
    KEY idx_user (user_id),
    KEY idx_date (created_at),
    FOREIGN KEY (entry_id) REFERENCES competition_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Competition judges
CREATE TABLE IF NOT EXISTS competition_judges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    competition_id INT NOT NULL,
    judge_user_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_judge (competition_id, judge_user_id),
    KEY idx_competition (competition_id),
    KEY idx_judge (judge_user_id),
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (judge_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Judge scores
CREATE TABLE IF NOT EXISTS competition_judge_scores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entry_id INT NOT NULL,
    judge_user_id INT NOT NULL,
    writing_score INT,
    plot_score INT,
    creativity_score INT,
    characters_score INT,
    grammar_score INT,
    total_score FLOAT,
    feedback TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_judge_score (entry_id, judge_user_id),
    KEY idx_entry (entry_id),
    KEY idx_judge (judge_user_id),
    FOREIGN KEY (entry_id) REFERENCES competition_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (judge_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Competition winners/rankings
CREATE TABLE IF NOT EXISTS competition_rankings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    competition_id INT NOT NULL,
    entry_id INT NOT NULL,
    placement INT,
    category VARCHAR(50),
    award_type ENUM('grand_winner', 'second_place', 'third_place', 'finalist', 'top_10', 'judges_pick', 'readers_choice', 'special_award') DEFAULT 'finalist',
    prize_amount DECIMAL(10, 2),
    badge_type VARCHAR(50),
    announcement_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_competition (competition_id),
    KEY idx_entry (entry_id),
    KEY idx_placement (placement),
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (entry_id) REFERENCES competition_entries(id) ON DELETE CASCADE
);

-- Competition badges (awarded to books)
CREATE TABLE IF NOT EXISTS competition_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    badge_type ENUM('winner', 'finalist', 'top_10', 'participant') DEFAULT 'participant',
    competition_id INT,
    placement INT,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_book (book_id),
    KEY idx_competition (competition_id),
    UNIQUE KEY unique_badge (book_id, competition_id, badge_type),
    FOREIGN KEY (book_id) REFERENCES stories(id) ON DELETE CASCADE,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE SET NULL
);

-- Alter stories table to add competition support (if not exists)
ALTER TABLE stories ADD COLUMN IF NOT EXISTS competition_id INT;
ALTER TABLE stories ADD COLUMN IF NOT EXISTS competition_tags VARCHAR(500);
ALTER TABLE stories ADD KEY IF NOT EXISTS idx_competition (competition_id);
