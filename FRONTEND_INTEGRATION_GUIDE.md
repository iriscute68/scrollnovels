# Frontend Integration Guide

Complete guide for integrating the Point System API with your frontend application.

## Quick Setup

### 1. API Base URL Configuration

```javascript
// config/api.js
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:3000/api/v1';

export const apiClient = {
  points: {
    getBalance: (token) => 
      fetch(`${API_BASE_URL}/me/points`, {
        headers: { 'Authorization': `Bearer ${token}` }
      }).then(r => r.json()),
    
    getTransactions: (token, limit = 20, offset = 0) =>
      fetch(`${API_BASE_URL}/me/points/transactions?limit=${limit}&offset=${offset}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      }).then(r => r.json()),
    
    support: (token, bookId, points, pointType) =>
      fetch(`${API_BASE_URL}/books/${bookId}/support`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ points, point_type: pointType })
      }).then(r => r.json())
  },

  oauth: {
    getPatreonUrl: () =>
      fetch(`${API_BASE_URL}/oauth/patreon/url`).then(r => r.json()),
    
    linkPatreon: (token, code, state) =>
      fetch(`${API_BASE_URL}/oauth/patreon/callback`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ code, state })
      }).then(r => r.json()),
    
    getPatreonLink: (token) =>
      fetch(`${API_BASE_URL}/me/patreon`, {
        headers: { 'Authorization': `Bearer ${token}` }
      }).then(r => r.json()),
    
    unlinkPatreon: (token) =>
      fetch(`${API_BASE_URL}/me/patreon`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      }).then(r => r.json())
  },

  guides: {
    listAll: () =>
      fetch(`${API_BASE_URL}/guides`).then(r => r.json()),
    
    getGuide: (slug) =>
      fetch(`${API_BASE_URL}/guides/${slug}`).then(r => r.json())
  },

  rankings: {
    get: (period = 'weekly', limit = 50) =>
      fetch(`${API_BASE_URL}/rankings?period=${period}&limit=${limit}`).then(r => r.json())
  }
};
```

### 2. Points Balance Hook

```javascript
// hooks/usePointsBalance.js
import { useState, useEffect } from 'react';
import { apiClient } from '../config/api';

export function usePointsBalance(token) {
  const [balance, setBalance] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!token) {
      setLoading(false);
      return;
    }

    apiClient.points.getBalance(token)
      .then(data => {
        if (data.success) {
          setBalance(data.data);
        } else {
          setError(data.error);
        }
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, [token]);

  return { balance, loading, error };
}
```

### 3. Points Display Component

```javascript
// components/PointsDisplay.js
import React from 'react';
import { usePointsBalance } from '../hooks/usePointsBalance';

export function PointsDisplay({ token }) {
  const { balance, loading } = usePointsBalance(token);

  if (loading) return <div>Loading points...</div>;
  if (!balance) return <div>No points</div>;

  return (
    <div className="points-display">
      <div className="point-type free">
        <span className="label">Free Points</span>
        <span className="value">{balance.free_points}</span>
      </div>
      <div className="point-type premium">
        <span className="label">Premium Points</span>
        <span className="value">{balance.premium_points}</span>
      </div>
      <div className="point-type patreon">
        <span className="label">Patreon Points</span>
        <span className="value">{balance.patreon_points}</span>
      </div>
      <div className="total">
        <strong>Total: {balance.total_points}</strong>
      </div>
    </div>
  );
}
```

### 4. Support Book Modal

```javascript
// components/SupportModal.js
import React, { useState } from 'react';
import { apiClient } from '../config/api';

export function SupportModal({ token, bookId, onClose }) {
  const [points, setPoints] = useState(0);
  const [pointType, setPointType] = useState('free');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSupport = async () => {
    setLoading(true);
    setError(null);

    try {
      const result = await apiClient.points.support(token, bookId, points, pointType);
      
      if (result.success) {
        alert('Book supported successfully!');
        onClose();
      } else {
        setError(result.error);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="modal">
      <div className="modal-content">
        <h2>Support This Book</h2>
        
        <div className="form-group">
          <label>Point Type</label>
          <select value={pointType} onChange={(e) => setPointType(e.target.value)}>
            <option value="free">Free Points (1x)</option>
            <option value="premium">Premium Points (2x)</option>
            <option value="patreon">Patreon Points (3x)</option>
          </select>
          <small className="multiplier-info">
            {pointType === 'free' && 'Your support will give 1x points to author'}
            {pointType === 'premium' && 'Your support will give 2x points to author'}
            {pointType === 'patreon' && 'Your support will give 3x points to author'}
          </small>
        </div>

        <div className="form-group">
          <label>Points to Support ({pointType === 'free' ? '1x' : pointType === 'premium' ? '2x' : '3x'})</label>
          <input 
            type="number" 
            value={points} 
            onChange={(e) => setPoints(parseInt(e.target.value) || 0)}
            min="1"
            max="100000"
          />
        </div>

        <div className="info">
          <p>Author will receive: <strong>{points * (pointType === 'free' ? 1 : pointType === 'premium' ? 2 : 3)} points</strong></p>
        </div>

        {error && <div className="error">{error}</div>}

        <div className="buttons">
          <button onClick={onClose}>Cancel</button>
          <button 
            onClick={handleSupport} 
            disabled={loading || points < 1}
          >
            {loading ? 'Supporting...' : 'Support'}
          </button>
        </div>
      </div>
    </div>
  );
}
```

### 5. Patreon Connect Component

```javascript
// components/PatreonConnect.js
import React, { useState, useEffect } from 'react';
import { apiClient } from '../config/api';

export function PatreonConnect({ token }) {
  const [linked, setLinked] = useState(null);
  const [pattreonInfo, setPatreonInfo] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkPatreonLink();
  }, [token]);

  const checkPatreonLink = async () => {
    const result = await apiClient.oauth.getPatreonLink(token);
    if (result.success) {
      setLinked(result.linked);
      if (result.linked) {
        setPatreonInfo(result.data);
      }
    }
    setLoading(false);
  };

  const handleConnect = async () => {
    const urlResult = await apiClient.oauth.getPatreonUrl();
    window.location.href = urlResult.url;
  };

  const handleDisconnect = async () => {
    await apiClient.oauth.unlinkPatreon(token);
    setLinked(false);
    setPatreonInfo(null);
  };

  if (loading) return <div>Loading Patreon info...</div>;

  if (linked && pattreonInfo) {
    return (
      <div className="patreon-linked">
        <h3>Patreon Connected ✓</h3>
        <p>Tier: {pattreonInfo.tier_name}</p>
        <p>Monthly Reward: Next on {new Date(pattreonInfo.next_reward_date).toLocaleDateString()}</p>
        <button onClick={handleDisconnect}>Disconnect Patreon</button>
      </div>
    );
  }

  return (
    <div className="patreon-connect">
      <h3>Connect Patreon</h3>
      <p>Link your Patreon account to earn monthly points!</p>
      <button onClick={handleConnect}>Connect Patreon</button>
    </div>
  );
}
```

### 6. Rankings Component

```javascript
// components/Rankings.js
import React, { useState, useEffect } from 'react';
import { apiClient } from '../config/api';

export function Rankings() {
  const [period, setPeriod] = useState('weekly');
  const [rankings, setRankings] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    apiClient.rankings.get(period)
      .then(data => {
        if (data.success) {
          setRankings(data.data);
        }
      })
      .finally(() => setLoading(false));
  }, [period]);

  if (loading) return <div>Loading rankings...</div>;

  return (
    <div className="rankings">
      <div className="period-selector">
        {['daily', 'weekly', 'monthly', 'all_time'].map(p => (
          <button
            key={p}
            className={period === p ? 'active' : ''}
            onClick={() => setPeriod(p)}
          >
            {p.replace('_', ' ').toUpperCase()}
          </button>
        ))}
      </div>

      <table>
        <thead>
          <tr>
            <th>Rank</th>
            <th>Book</th>
            <th>Author</th>
            <th>Supporters</th>
            <th>Points</th>
          </tr>
        </thead>
        <tbody>
          {rankings.map((book) => (
            <tr key={book.id}>
              <td>#{book.rank}</td>
              <td>
                <a href={`/book/${book.slug}`}>{book.title}</a>
              </td>
              <td>{book.author}</td>
              <td>{book.supporter_count}</td>
              <td>{book.total_support_points.toLocaleString()}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
```

### 7. Guide Viewer Component

```javascript
// components/GuideViewer.js
import React, { useState, useEffect } from 'react';
import { apiClient } from '../config/api';
import ReactMarkdown from 'react-markdown';

export function GuideViewer({ slug }) {
  const [guide, setGuide] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiClient.guides.getGuide(slug)
      .then(data => {
        if (data.success) {
          setGuide(data.data);
        }
      })
      .finally(() => setLoading(false));
  }, [slug]);

  if (loading) return <div>Loading guide...</div>;
  if (!guide) return <div>Guide not found</div>;

  return (
    <div className="guide-viewer">
      <h1>{guide.title}</h1>
      <p className="description">{guide.description}</p>

      <div className="guide-content">
        <ReactMarkdown>{guide.content}</ReactMarkdown>
      </div>

      {guide.sections && guide.sections.length > 0 && (
        <div className="sections">
          {guide.sections.map((section) => (
            <section key={section.id} className="guide-section">
              <h2>{section.title}</h2>
              <ReactMarkdown>{section.content}</ReactMarkdown>
            </section>
          ))}
        </div>
      )}

      {guide.images && guide.images.length > 0 && (
        <div className="gallery">
          {guide.images.map((img) => (
            <figure key={img.id}>
              <img src={img.image_url} alt={img.alt_text} />
              {img.caption && <figcaption>{img.caption}</figcaption>}
            </figure>
          ))}
        </div>
      )}
    </div>
  );
}
```

### 8. Admin Guide Editor

```javascript
// components/AdminGuideEditor.js
import React, { useState, useEffect } from 'react';
import { apiClient } from '../config/api';

export function AdminGuideEditor({ token, guideId }) {
  const [guide, setGuide] = useState(null);
  const [sections, setSections] = useState([]);
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    setSaving(true);
    try {
      // Call admin API endpoint
      const result = await fetch(`/api/v1/admin/guides/${guideId}`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ title, content })
      }).then(r => r.json());

      if (result.success) {
        alert('Guide saved successfully!');
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="admin-editor">
      <h2>Edit Guide</h2>
      
      <input 
        type="text" 
        placeholder="Guide Title"
        value={title}
        onChange={(e) => setTitle(e.target.value)}
      />
      
      <textarea
        placeholder="Guide content (markdown)"
        value={content}
        onChange={(e) => setContent(e.target.value)}
        rows="10"
      />

      <button 
        onClick={handleSave}
        disabled={saving}
      >
        {saving ? 'Saving...' : 'Save Guide'}
      </button>
    </div>
  );
}
```

## Environment Variables

Create `.env.local` in your React app:

```
REACT_APP_API_URL=http://localhost:3000/api/v1
REACT_APP_PATREON_CLIENT_ID=your_client_id
```

## Styling Example

```css
/* styles/points.css */

.points-display {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
  padding: 1rem;
  background: #f5f5f5;
  border-radius: 8px;
}

.point-type {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 1rem;
  background: white;
  border-radius: 6px;
  border-left: 4px solid #007bff;
}

.point-type.premium {
  border-left-color: #ffc107;
}

.point-type.patreon {
  border-left-color: #ff5722;
}

.point-type .value {
  font-size: 1.5rem;
  font-weight: bold;
  color: #333;
}

.support-button {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: transform 0.2s;
}

.support-button:hover {
  transform: translateY(-2px);
}

.rankings table {
  width: 100%;
  border-collapse: collapse;
}

.rankings th,
.rankings td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.rankings tr:hover {
  background: #f5f5f5;
}
```

## Testing the Integration

### 1. Test Points Balance
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:3000/api/v1/me/points
```

### 2. Test Support
```bash
curl -X POST http://localhost:3000/api/v1/books/BOOK_ID/support \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"points": 100, "point_type": "free"}'
```

### 3. Test Rankings
```bash
curl http://localhost:3000/api/v1/rankings?period=weekly
```

### 4. Test Guide
```bash
curl http://localhost:3000/api/v1/guides/how-points-work
```

## Troubleshooting

### CORS Errors
- Ensure FRONTEND_URL in .env matches your frontend origin
- Add frontend URL to CORS whitelist

### Token Issues
- Check token hasn't expired
- Verify JWT_SECRET is configured
- Ensure token format: "Bearer <token>"

### Points Not Updating
- Check user has points balance entry
- Verify point_type is valid (free, premium, patreon)
- Check browser console for API errors
