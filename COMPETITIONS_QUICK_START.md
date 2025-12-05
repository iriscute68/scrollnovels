# Competitions System - Quick Start Guide

## 🚀 QUICK SETUP (2 minutes)

### Step 1: Initialize Database
Open in browser:
```
http://localhost/scrollnovels/admin/setup-competitions.php
```
✅ Should see: "✓ Competitions system setup complete!"

### Step 2: View Competitions
```
http://localhost/scrollnovels/pages/competitions.php
```

### Step 3: Join a Competition
- Click "Join" button on any ACTIVE competition (green badge)
- System redirects to story creation
- Create your story entry
- Automatically registered in competition

---

## 📖 CURRENT USER FLOW

### For Readers/Authors:
```
competitions.php (Browse all)
    ↓
    ├→ View Details → competition-details.php (See rules, entries, countdown)
    │                      ↓
    │                   Join (if active)
    │                      ↓
    └→ Join → write-story.php (Create entry with competition)
                              ↓
                         Book created & registered
```

---

## 🗄️ DATABASE INFO

All tables created in `scroll_novels` database:

| Table | Purpose | Rows |
|-------|---------|------|
| competitions | Competition listings | Editable |
| competition_entries | Submitted books | Auto-populated |
| competition_votes | Reader votes | User actions |
| competition_judges | Judge assignments | Admin controlled |
| competition_judge_scores | Judge ratings | Admin data |
| competition_rankings | Final results | Admin generated |
| competition_badges | Winner badges | Auto-awarded |

---

## 📋 FILES CREATED/MODIFIED

### NEW FILES ✨
```
/pages/competitions.php                 - Main competition listings page
/pages/competition-details.php          - Single competition detail view
/admin/setup-competitions.php           - Database initialization
/database/competitions-schema.sql       - SQL table definitions
/COMPETITIONS_IMPLEMENTATION.md         - Full documentation
```

### MODIFIED FILES 🔧
```
/pages/write-story.php                  - Added competition support
```

---

## 🎯 SAMPLE COMPETITIONS (Fallback Data)

These show automatically if database is empty:

1. **Summer Writing Challenge** (Fantasy)
   - Prize: $500
   - Dates: June 1 - Aug 31, 2025
   - Status: ACTIVE

2. **Romance Novel Contest** (Romance)
   - Prize: $750
   - Dates: Dec 1, 2025 - Feb 28, 2026
   - Status: UPCOMING

3. **Sci-Fi Odyssey Challenge** (Sci-Fi)
   - Prize: $1000
   - Dates: Jan 1 - Apr 30, 2025
   - Status: ENDED

---

## ✅ WORKING FEATURES

### User-Facing
- [x] Browse competitions with filters (All/Active/Upcoming/Ended)
- [x] View detailed competition information
- [x] See competition requirements as checklist
- [x] View prize breakdown
- [x] See live countdown timer
- [x] Browse submitted entries
- [x] Join active competitions
- [x] Automatic story registration

### Technical
- [x] Database schema with 7 tables
- [x] Responsive design (mobile/tablet/desktop)
- [x] Dark mode support
- [x] Sample data fallback
- [x] Error handling
- [x] User authentication checks

---

## ⏳ COMING SOON (Database Ready)

### For Users
- [ ] Vote on competition entries
- [ ] Support authors with points
- [ ] View competition leaderboard
- [ ] See personal ranking

### For Judges
- [ ] Submit judge scores (1-10 for: writing, plot, creativity, characters, grammar)
- [ ] Leave feedback on entries
- [ ] Compare scores with other judges

### For Winners
- [ ] View winner announcements
- [ ] See placement badges
- [ ] Get featured on homepage
- [ ] Receive prize payment

### For Admins
- [ ] Create new competitions
- [ ] Edit/delete competitions
- [ ] Approve/reject entries
- [ ] Assign judges
- [ ] View statistics
- [ ] Announce winners
- [ ] Award badges

---

## 🎨 STYLING & DESIGN

- ✅ Modern gradient headers (Emerald green theme)
- ✅ Card-based layout
- ✅ Color-coded status badges:
  - 🟢 ACTIVE (Green)
  - 🔵 UPCOMING (Blue)
  - ⚪ ENDED (Gray)
- ✅ Smooth hover animations
- ✅ Full dark mode support
- ✅ Fully responsive (1-3 columns)
- ✅ Touch-friendly buttons

---

## 🔗 IMPORTANT URLS

| Page | URL | Purpose |
|------|-----|---------|
| Competitions | /pages/competitions.php | Browse all |
| Details | /pages/competition-details.php?id=1 | View single |
| Join | /pages/write-story.php?competition=1 | Submit entry |
| Setup | /admin/setup-competitions.php | Initialize DB |

---

## 🐛 TROUBLESHOOTING

**Problem:** Can't see competitions
- Solution: Run `/admin/setup-competitions.php` first

**Problem:** Join button not showing
- Solution: Only shows for ACTIVE competitions (green badge)

**Problem:** Error when joining
- Solution: Make sure you're logged in (redirects to login if not)

**Problem:** Database tables not created
- Solution: Check MySQL permissions, or manually run `/database/competitions-schema.sql`

---

## 📊 DATABASE QUERIES REFERENCE

### Get active competitions right now:
```sql
SELECT * FROM competitions 
WHERE status = 'active' 
AND start_date <= NOW() 
AND end_date >= NOW()
ORDER BY end_date ASC;
```

### Get entries for a competition with scores:
```sql
SELECT ce.*, s.title, u.username, COUNT(cv.id) as votes
FROM competition_entries ce
JOIN stories s ON ce.book_id = s.id
JOIN users u ON ce.user_id = u.id
LEFT JOIN competition_votes cv ON ce.id = cv.entry_id
WHERE ce.competition_id = 1
GROUP BY ce.id
ORDER BY ce.total_score DESC;
```

### Get judge scores for an entry:
```sql
SELECT * FROM competition_judge_scores
WHERE entry_id = 1
ORDER BY submitted_at DESC;
```

---

## 🎊 READY TO USE!

The system is fully functional and ready for users to:
1. ✅ Browse competitions
2. ✅ Join active competitions  
3. ✅ Submit their stories
4. ✅ View all entries

Admin and judging features are database-ready and will be added next.

**Start here:** http://localhost/scrollnovels/pages/competitions.php
