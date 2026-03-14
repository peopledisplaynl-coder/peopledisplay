# PeopleDisplay v2.0

Real-time employee presence tracking for schools, childcare organizations, and offices.

## Features

- **Real-time presence tracking** — IN/OUT status with timestamp per employee
- **Multiple locations** — filter by office, branch, or department
- **Department management** — organize employees by team
- **BHV tracking** — emergency response personnel always visible
- **Sub-status buttons** — configurable extra states (Pause, Working from Home, Vacation, etc.)
- **Visitor registration** — digital visitor logbook with email notifications
- **Token-based kiosk check-in** — unattended devices with secure auto-login
- **Role-based access control** — superadmin, admin, user roles
- **Presentation mode** — auto-start Google Slides after idle period
- **WiFi auto check-in** — detect location by IP range
- **Audit logging** — full history of all status changes
- **PWA support** — installable as app on mobile/tablet
- **Mobile responsive** — works on any screen size
- **Name display options** — first name, last name, or full name
- **Sort by name or status** — IN employees shown first

## 🔐 Licentie Systeem

PeopleDisplay v2.0 vereist een geldige licentie voor gebruik.

### Beschikbare Pakketten

**Starter** — 
- 3 admin gebruikers, 10 medewerkers, 1 locatie, 3 afdelingen
- Basis features: IN/OUT tracking, BHV overzicht, sub-status

**Professional** — 
- 5 admin gebruikers, 25 medewerkers, 3 locaties, 6 afdelingen
- Inclusief: Bezoekersbeheer, locatie override

**Business** — 
- 10 admin gebruikers, 60 medewerkers, 6 locaties, 10 afdelingen
- Inclusief: Kiosk modus, API-toegang, alle Professional features


**Enterprise**
- 25 admin gebruikers, 	120	medewerkers,  locaties,  afdelingen
Inclusief: Kiosk modus, API-toegang, alle Professional features

**Corporate**
- 50 admin gebruikers,	250 medewerkers,  locaties,  afdelingen
Inclusief: Kiosk modus, API-toegang, alle Professional features

**Unlimited**
- Onbeperkt admin gebruikers, 	Onbeperkt medewerkers,  locaties,  afdelingen
Inclusief: Kiosk modus, API-toegang, alle Professional features


Volledige feature vergelijking: [peopledisplay.nl/prijzen](https://peopledisplay.nl/prijzen)

### Licentie Activatie

1. Koop een pakket via [peopledisplay.nl/prijzen](https://peopledisplay.nl/prijzen)
2. Ontvang licentiecode via e-mail (format: `PDIS-XXXX-XXXX-XXXX`)
3. Voer code in tijdens installatie stap 3
4. Licentie wordt gebonden aan uw domein

**Belangrijk:**
- Licentie werkt alleen op het geactiveerde domein
- Transfer mogelijk via deactivatie → nieuwe activatie op ander domein
- Eenmalige betaling, geen abonnement

---

## System Requirements

- PHP 8.0 or higher (PHP 8.1+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite **or** Nginx with PHP-FPM
- PHP extensions: `pdo`, `pdo_mysql`, `json`, `session`
- Minimum 64 MB PHP memory limit
- Writable `tmp/` directory

## Quick Installation (10 minutes)

1. Upload all files to your web server
2. Open `https://yoursite.com/install.php`
3. Follow the 8-step wizard (license key required at step 3)
4. Log in at `https://yoursite.com/login.php`

> The installer locks itself automatically after completion.

## Detailed Installation

### Step 1 — Upload Files

Extract the ZIP and upload the entire contents to your web server via FTP/SFTP.

- **Root install**: upload to `public_html/` or `httpdocs/`
- **Subdirectory install**: upload to `public_html/peopledisplay/`

### Step 2 — Create a Database

Create a new MySQL database in your hosting control panel (cPanel, Plesk, Strato, etc.) and note:
- Database host (usually `localhost`)
- Database name
- Database username
- Database password

The database user needs: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `INDEX`, `DROP` privileges.

### Step 3 — Run the Installer

Open your browser and navigate to:
```
https://yoursite.com/install.php
```

The wizard will guide you through 8 steps:
1. **Systeemcheck** — PHP version and required extensions
2. **Gebruiksvoorwaarden** — Accept EULA (NL/EN)
3. **Licentie** — Enter and validate your license key
4. **Database** — Configure database connection
5. **Schema** — Import 30 database tables
6. **Admin Account** — Create administrator credentials
7. **Afronden** — Write config file, activate license
8. **Klaar** — Installation complete

### Step 4 — Log In

After installation completes, log in at:
```
https://yoursite.com/login.php
```

Use the admin credentials you created during installation.

### Step 5 — Configure

In the Admin Dashboard:
1. **Locations** → Add your office locations
2. **Departments** → Add departments/teams
3. **Employees** → Add employees (or import via CSV)
4. **Features** → Enable features per user
5. **Config** → Set button names, display options

## Supported Hosting Platforms

Tested and working on:

| Platform | Status |
|----------|--------|
| Strato webhosting | ✅ Verified |
| Mijndomein hosting | ✅ Verified |
| cPanel hosting | ✅ Compatible |
| Plesk hosting | ✅ Compatible |
| XAMPP (local dev) | ✅ Compatible |
| Standard LAMP/LEMP | ✅ Compatible |

## File Structure

```
peopledisplay/
├── install.php              ← Web installer wizard
├── install.sql              ← Database schema (28 tables)
├── config.example.php       ← Configuration template
├── index.php                ← Main employee display
├── login.php                ← Authentication
├── logout.php
├── overzicht.php            ← Overview / summary page
├── visitor_register.php     ← Visitor self-registration
├── visitor_checkin.php      ← Visitor check-in
├── visitor_checkout.php     ← Visitor check-out
├── kiosk_login.php          ← Token-based kiosk login
├── app.js                   ← Main application JavaScript
├── style.css                ← Main styles
│
├── admin/                   ← Admin panel pages
│   ├── dashboard.php
│   ├── employees_manage.php
│   ├── locations_manage.php
│   ├── afdelingen_manage.php
│   ├── users_manage.php
│   ├── features_manage.php
│   ├── tokens_manage.php
│   ├── visitors_manage.php
│   ├── audit_log.php
│   ├── online_users.php
│   └── api/                 ← Admin API endpoints (JSON)
│
├── api/                     ← Public API endpoints (JSON)
├── includes/                ← Shared PHP: db, auth, config, email
├── cron/                    ← Scheduled task scripts
├── bhv-print/               ← Emergency roster print module
├── user/                    ← User profile page
├── tcpdf/                   ← PDF library (badge generation)
├── tmp/                     ← Runtime: sessions, badge photos
├── uploads/                 ← User profile photos
└── logs/                    ← Application logs
```

## Configuration

The installer creates `admin/db_config.php` automatically. If you need to edit it:

```php
<?php
define('DB_HOST',     'localhost');
define('DB_NAME',     'your_database');
define('DB_USER',     'your_username');
define('DB_PASS',     'your_password');
define('DB_CHARSET',  'utf8mb4');
```

The application auto-detects its base path and site URL from the server environment. No manual URL configuration is required.

## Cron Jobs (Optional)

For automatic features, configure these cron jobs on your server:

```
# Reset expired sub-statuses every 5 minutes
*/5 * * * * curl -s https://yoursite.com/cron_endpoint.php?action=reset_sub_status

# Auto check-out employees at end of day
0 18 * * * curl -s https://yoursite.com/cron_endpoint.php?action=auto_checkout

# Clean up old visitor records (daily)
0 2 * * * curl -s https://yoursite.com/cron_endpoint.php?action=cleanup
```

## Troubleshooting

### "Too many redirects" error
- Clear browser cookies and cache
- Verify `admin/db_config.php` has correct database credentials
- Check that `tmp/sessions/` directory is writable (or use PHP's default session path)

### Database connection failed
- Verify credentials in `admin/db_config.php`
- Check the database server hostname (some hosts use a specific hostname, not `localhost`)
- Ensure the database user has the required privileges

### 500 Internal Server Error
- Check PHP error log on your server
- Verify `.htaccess` is supported (some hosts require `AllowOverride All` in Apache config)
- Check file permissions (PHP files: 644, directories: 755)

### Status changes don't persist
- Open browser DevTools → Network tab
- Click an IN/OUT button and check the API response for `admin/api/employees_api.php`
- Response should be clean JSON: `{"success":true,...}`
- If you see PHP warnings before the JSON, check your PHP error log

### Installer already locked
Navigate to `https://yoursite.com/install.php` — it will show a "locked" page with a link to the application. The lock file is at `install/.installed`.

## Security

- Delete or restrict access to `install.php` after installation (it locks itself automatically)
- Keep `admin/db_config.php` outside of public git repositories
- Use HTTPS on production servers
- Change the default admin password immediately after installation
- Regularly review `admin/audit_log.php` for unexpected activity

## License

PeopleDisplay v2.0.0 is commercial software. A valid license key is required for installation and use.

- License terms: see `eula_nl.txt` (Dutch) or `eula_en.txt` (English)
- Each license is bound to a single domain
- Licenses are non-transferable without deactivation on the original domain
- Redistribution, resale, or sublicensing is strictly prohibited

For licensing questions: support@peopledisplay.nl
Purchase a license: [peopledisplay.nl/prijzen](https://peopledisplay.nl/prijzen)

© 2026 PeopleDisplay — All rights reserved

## Version

**Version 2.0.0** — License System Release
Compatible with: Strato, Mijndomein, cPanel, Plesk
