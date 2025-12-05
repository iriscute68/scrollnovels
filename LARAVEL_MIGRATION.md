# ScrollNovels - Laravel + PHP Hybrid Project

Your PHP project is now wrapped in a Laravel structure while maintaining all existing functionality.

## Project Structure

```
scrollnovels/
├── app/                          # Laravel application code
│   ├── Http/Controllers/         # PHP controllers
│   └── Models/                   # PHP models
├── public/                       # Web root (entry point)
│   └── index.php                 # Routes to your PHP files
├── resources/                    # Views and assets
├── routes/                       # Laravel routes (optional)
├── storage/                      # Logs and cache
├── admin/                        # Your admin PHP files
├── api/                          # Your API endpoints
├── blog/                         # Your blog PHP files
├── includes/                     # PHP utilities and functions
├── components/                   # Reusable PHP components
├── config.php                    # Site configuration
└── index.php                     # Your main PHP file
```

## How It Works

1. **Entry Point**: `public/index.php` - Routes incoming requests to your PHP files
2. **Routing**: Maps URLs to your existing PHP structure:
   - `/` → `index.php` (home)
   - `/admin` → `admin/index.php`
   - `/api/reviews/submit` → `api/reviews/submit-review.php`
   - `/blog` → `blog/index.php`
   - etc.

3. **Your PHP Code**: Unchanged - all existing PHP files work as before
4. **Laravel Structure**: Optional - can gradually add Laravel features

## Running the Project

### Development Server
```bash
php -S localhost:8000 -t public
```

### On XAMPP
- Access via: `http://localhost/scrollnovels/`
- Make sure Apache is configured to route to `public/` directory

### .htaccess (for Apache)
Create `public/.htaccess`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /scrollnovels/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?request=$1 [L,QSA]
</IfModule>
```

## Environment Configuration

Edit `.env` to configure:
- Database credentials (MySQL)
- Site URL and name
- API settings
- Any custom variables your PHP code needs

```env
APP_NAME=ScrollNovels
APP_URL=http://localhost/scrollnovels
DB_DATABASE=scroll_novels
DB_USERNAME=root
DB_PASSWORD=
```

## Features

✅ All existing PHP functionality preserved
✅ Database connectivity maintained
✅ Admin panel works
✅ Review system works
✅ API endpoints work
✅ Blog system works
✅ Background image & styling intact

## Next Steps (Optional)

You can gradually migrate to Laravel while keeping your PHP working:

1. **Add Laravel Routes**: Define new routes in `routes/web.php`
2. **Create Controllers**: Move PHP logic to Laravel controllers in `app/Http/Controllers/`
3. **Create Models**: Convert database queries to Laravel Eloquent models
4. **Migrate Views**: Move PHP views to Blade templates

Or keep it as-is - your PHP project will continue to work with Laravel's directory structure.

## Database

- Host: `localhost`
- Database: `scroll_novels`
- User: `root`
- Password: (empty by default)

Connection details are in `config/db.php`

## Support

All your existing PHP files are in their original locations and work unchanged.
The Laravel structure is there to support gradual modernization when ready.
