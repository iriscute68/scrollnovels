// server/routes/guides.js - Admin Guide Management

const express = require('express');
const router = express.Router();
const { pool } = require('../db');
const { authMiddleware } = require('../middleware/auth');
const multer = require('multer');
const path = require('path');

// Admin-only middleware
const adminOnly = (req, res, next) => {
  if (req.user?.role !== 'admin') {
    return res.status(403).json({ error: 'Unauthorized - admin only' });
  }
  next();
};

// File upload configuration
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, path.join(__dirname, '../public/uploads/guides'));
  },
  filename: (req, file, cb) => {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    cb(null, 'guide-' + uniqueSuffix + path.extname(file.originalname));
  }
});

const upload = multer({ 
  storage,
  limits: { fileSize: 10 * 1024 * 1024 }, // 10MB max
  fileFilter: (req, file, cb) => {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (allowedTypes.includes(file.mimetype)) {
      cb(null, true);
    } else {
      cb(new Error('Invalid file type'));
    }
  }
});

/**
 * GET /api/v1/guides
 * Get all published guide pages (public endpoint)
 */
router.get('/guides', async (req, res) => {
  try {
    const result = await pool.query(
      `SELECT id, slug, title, description, created_at
       FROM guide_pages
       WHERE published = true
       ORDER BY order_index ASC`
    );

    res.json({
      success: true,
      data: result.rows
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get guides' });
  }
});

/**
 * GET /api/v1/guides/:slug
 * Get full guide page with sections and images (public endpoint)
 */
router.get('/guides/:slug', async (req, res) => {
  try {
    const { slug } = req.params;

    const pageResult = await pool.query(
      `SELECT id, slug, title, description, content, published, order_index
       FROM guide_pages
       WHERE slug = $1 AND published = true`,
      [slug]
    );

    if (pageResult.rows.length === 0) {
      return res.status(404).json({ error: 'Guide not found' });
    }

    const page = pageResult.rows[0];

    // Get sections
    const sectionsResult = await pool.query(
      `SELECT id, title, content, order_index
       FROM guide_sections
       WHERE guide_id = $1
       ORDER BY order_index ASC`,
      [page.id]
    );

    // Get images
    const imagesResult = await pool.query(
      `SELECT id, image_url, caption, alt_text, order_index
       FROM guide_images
       WHERE guide_id = $1
       ORDER BY order_index ASC`,
      [page.id]
    );

    res.json({
      success: true,
      data: {
        ...page,
        sections: sectionsResult.rows,
        images: imagesResult.rows
      }
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get guide' });
  }
});

/**
 * GET /api/v1/admin/guides
 * Get all guide pages (admin - all statuses)
 */
router.get('/admin/guides', authMiddleware, adminOnly, async (req, res) => {
  try {
    const result = await pool.query(
      `SELECT g.id, g.slug, g.title, g.description, g.published, g.order_index,
              g.created_at, g.updated_at,
              u1.username as created_by_name, u2.username as updated_by_name
       FROM guide_pages g
       LEFT JOIN users u1 ON g.created_by = u1.id
       LEFT JOIN users u2 ON g.updated_by = u2.id
       ORDER BY g.order_index ASC`
    );

    res.json({
      success: true,
      data: result.rows
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get guides' });
  }
});

/**
 * POST /api/v1/admin/guides
 * Create new guide page
 */
router.post('/admin/guides', authMiddleware, adminOnly, async (req, res) => {
  const { slug, title, description, content, order_index } = req.body;

  if (!slug || !title) {
    return res.status(400).json({ error: 'slug and title are required' });
  }

  try {
    const result = await pool.query(
      `INSERT INTO guide_pages (slug, title, description, content, order_index, created_by, updated_by)
       VALUES ($1, $2, $3, $4, $5, $6, $7)
       RETURNING *`,
      [slug, title, description || '', content || '', order_index || 0, req.user.id, req.user.id]
    );

    res.status(201).json({
      success: true,
      data: result.rows[0]
    });
  } catch (err) {
    console.error(err);
    if (err.code === '23505') { // Unique violation
      return res.status(400).json({ error: 'Slug already exists' });
    }
    res.status(500).json({ error: 'Failed to create guide' });
  }
});

/**
 * PUT /api/v1/admin/guides/:id
 * Update guide page
 */
router.put('/admin/guides/:id', authMiddleware, adminOnly, async (req, res) => {
  const { id } = req.params;
  const { title, description, content, order_index, published } = req.body;

  try {
    const result = await pool.query(
      `UPDATE guide_pages
       SET title = COALESCE($1, title),
           description = COALESCE($2, description),
           content = COALESCE($3, content),
           order_index = COALESCE($4, order_index),
           published = COALESCE($5, published),
           updated_by = $6,
           updated_at = now()
       WHERE id = $7
       RETURNING *`,
      [title, description, content, order_index, published, req.user.id, id]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Guide not found' });
    }

    res.json({
      success: true,
      data: result.rows[0]
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to update guide' });
  }
});

/**
 * DELETE /api/v1/admin/guides/:id
 * Delete guide page (and cascade delete sections/images)
 */
router.delete('/admin/guides/:id', authMiddleware, adminOnly, async (req, res) => {
  const { id } = req.params;
  const client = await pool.connect();

  try {
    await client.query('BEGIN');

    // Delete images first
    await client.query(`DELETE FROM guide_images WHERE guide_id = $1`, [id]);

    // Delete sections
    await client.query(`DELETE FROM guide_sections WHERE guide_id = $1`, [id]);

    // Delete guide
    const result = await client.query(
      `DELETE FROM guide_pages WHERE id = $1 RETURNING *`,
      [id]
    );

    if (result.rows.length === 0) {
      await client.query('ROLLBACK');
      return res.status(404).json({ error: 'Guide not found' });
    }

    await client.query('COMMIT');

    res.json({
      success: true,
      message: 'Guide deleted'
    });
  } catch (err) {
    await client.query('ROLLBACK');
    console.error(err);
    res.status(500).json({ error: 'Failed to delete guide' });
  } finally {
    client.release();
  }
});

/**
 * POST /api/v1/admin/guides/:guideId/sections
 * Add section to guide
 */
router.post('/admin/guides/:guideId/sections', authMiddleware, adminOnly, async (req, res) => {
  const { guideId } = req.params;
  const { title, content, order_index } = req.body;

  try {
    const result = await pool.query(
      `INSERT INTO guide_sections (guide_id, title, content, order_index)
       VALUES ($1, $2, $3, $4)
       RETURNING *`,
      [guideId, title || '', content || '', order_index || 0]
    );

    res.status(201).json({
      success: true,
      data: result.rows[0]
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to create section' });
  }
});

/**
 * PUT /api/v1/admin/guides/sections/:sectionId
 * Update guide section
 */
router.put('/admin/guides/sections/:sectionId', authMiddleware, adminOnly, async (req, res) => {
  const { sectionId } = req.params;
  const { title, content, order_index } = req.body;

  try {
    const result = await pool.query(
      `UPDATE guide_sections
       SET title = COALESCE($1, title),
           content = COALESCE($2, content),
           order_index = COALESCE($3, order_index)
       WHERE id = $4
       RETURNING *`,
      [title, content, order_index, sectionId]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Section not found' });
    }

    res.json({
      success: true,
      data: result.rows[0]
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to update section' });
  }
});

/**
 * DELETE /api/v1/admin/guides/sections/:sectionId
 * Delete guide section
 */
router.delete('/admin/guides/sections/:sectionId', authMiddleware, adminOnly, async (req, res) => {
  const { sectionId } = req.params;

  try {
    const result = await pool.query(
      `DELETE FROM guide_sections WHERE id = $1 RETURNING *`,
      [sectionId]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Section not found' });
    }

    res.json({
      success: true,
      message: 'Section deleted'
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to delete section' });
  }
});

/**
 * POST /api/v1/admin/guides/:guideId/images
 * Upload image to guide
 */
router.post('/admin/guides/:guideId/images', 
  authMiddleware, 
  adminOnly, 
  upload.single('image'), 
  async (req, res) => {
    const { guideId } = req.params;
    const { caption, alt_text, order_index } = req.body;

    if (!req.file) {
      return res.status(400).json({ error: 'No file uploaded' });
    }

    try {
      const image_url = `/uploads/guides/${req.file.filename}`;

      const result = await pool.query(
        `INSERT INTO guide_images (guide_id, image_url, caption, alt_text, order_index)
         VALUES ($1, $2, $3, $4, $5)
         RETURNING *`,
        [guideId, image_url, caption || '', alt_text || '', order_index || 0]
      );

      res.status(201).json({
        success: true,
        data: result.rows[0]
      });
    } catch (err) {
      console.error(err);
      res.status(500).json({ error: 'Failed to save image' });
    }
  }
);

/**
 * PUT /api/v1/admin/guides/images/:imageId
 * Update image metadata
 */
router.put('/admin/guides/images/:imageId', authMiddleware, adminOnly, async (req, res) => {
  const { imageId } = req.params;
  const { caption, alt_text, order_index } = req.body;

  try {
    const result = await pool.query(
      `UPDATE guide_images
       SET caption = COALESCE($1, caption),
           alt_text = COALESCE($2, alt_text),
           order_index = COALESCE($3, order_index)
       WHERE id = $4
       RETURNING *`,
      [caption, alt_text, order_index, imageId]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Image not found' });
    }

    res.json({
      success: true,
      data: result.rows[0]
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to update image' });
  }
});

/**
 * DELETE /api/v1/admin/guides/images/:imageId
 * Delete image
 */
router.delete('/admin/guides/images/:imageId', authMiddleware, adminOnly, async (req, res) => {
  const { imageId } = req.params;

  try {
    const result = await pool.query(
      `DELETE FROM guide_images WHERE id = $1 RETURNING image_url`,
      [imageId]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Image not found' });
    }

    // Optional: Delete file from disk
    // fs.unlinkSync(path.join(__dirname, '../public', result.rows[0].image_url));

    res.json({
      success: true,
      message: 'Image deleted'
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to delete image' });
  }
});

/**
 * POST /api/v1/admin/guides/:id/publish
 * Publish/unpublish guide
 */
router.post('/admin/guides/:id/publish', authMiddleware, adminOnly, async (req, res) => {
  const { id } = req.params;
  const { published } = req.body;

  try {
    const result = await pool.query(
      `UPDATE guide_pages
       SET published = $1, updated_by = $2, updated_at = now()
       WHERE id = $3
       RETURNING *`,
      [published, req.user.id, id]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Guide not found' });
    }

    res.json({
      success: true,
      data: result.rows[0]
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to update guide status' });
  }
});

module.exports = router;
