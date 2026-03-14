# PeopleDisplay — Installation Guide

## Before You Start

You will need:
- FTP/SFTP access to your web server (or a file manager in your hosting control panel)
- A MySQL/MariaDB database with credentials
- A web browser

The installation takes approximately 5 minutes.

---

## Part 1: Upload Files

### Option A — FTP/SFTP

1. Unzip the PeopleDisplay package on your computer
2. Open your FTP client (FileZilla, WinSCP, Cyberduck, etc.)
3. Connect to your server
4. Navigate to your web root:
   - cPanel: `public_html/`
   - Plesk: `httpdocs/`
   - Strato: `html/` or root of your domain folder
5. Upload the entire contents of the ZIP (not the folder itself)
6. Wait for upload to complete

**For a subdirectory install** (e.g., `yoursite.com/people/`):
- Create the subdirectory first
- Upload all files into that subdirectory

### Option B — Hosting File Manager

Most control panels (cPanel, Plesk, Strato) have a web-based file manager:
1. Log in to your hosting control panel
2. Open File Manager
3. Navigate to your web root
4. Use "Upload" to upload the ZIP file
5. Extract the ZIP in-place

---

## Part 2: Create a Database

### cPanel Hosting

1. Log in to cPanel
2. Go to **Databases** → **MySQL Databases**
3. Under "Create New Database", enter a name (e.g., `peopledisplay`) → **Create Database**
4. Under "MySQL Users", create a new user with a strong password → **Create User**
5. Under "Add User To Database", select your user and database → **Add** → Grant **All Privileges**
6. Note your credentials:
   - Host: `localhost`
   - Database: `youraccount_peopledisplay`
   - Username: `youraccount_dbuser`
   - Password: (as set)

### Plesk Hosting

1. Log in to Plesk
2. Go to **Databases** → **Add Database**
3. Enter database name and create a database user
4. Note the connection details (host is usually `localhost`)

### Strato Hosting

1. Log in to your Strato customer account
2. Go to **Pakketbeheer** → **MySQL-databases**
3. Create a new database
4. Note the database server hostname (Strato uses a specific server name, not `localhost`)
5. Create database user and set password

### XAMPP (Local Development)

1. Open phpMyAdmin at `http://localhost/phpmyadmin`
2. Click **New** in the left panel
3. Enter database name (e.g., `peopledisplay`) → **Create**
4. No separate user needed — use `root` with empty password (local only)

---

## Part 3: Run the Installer

1. Open your browser
2. Navigate to: `https://yoursite.com/install.php`
   (or `https://yoursite.com/people/install.php` for subdirectory install)

### Step 1 — System Check

The installer checks:
- PHP version (7.4+ required)
- Required extensions: PDO, pdo_mysql, json, session
- Directory write permissions

**If you see warnings:**
- PHP < 7.4: Contact your hosting provider to upgrade
- Missing extension: Contact your hosting provider
- Directory not writable: Set `tmp/` to permissions 755 via FTP

Click **Continue** to proceed.

### Step 2 — Database Configuration

Enter your database credentials:

| Field | Value |
|-------|-------|
| Database Host | `localhost` (or your host's specific server name) |
| Database Name | The database you created |
| Username | The database user |
| Password | The database user's password |

Click **Test Connection** to verify, then **Continue**.

### Step 3 — Import Schema

The installer imports the complete database schema (28 tables).

This step is automatic. Click **Import Database** and wait for confirmation.

**If this step fails:**
- Verify the database user has `CREATE TABLE` privileges
- Check that the database is empty (no conflicting tables)

### Step 4 — Create Admin Account

Enter your administrator credentials:

| Field | Notes |
|-------|-------|
| Username | Your login name (alphanumeric, no spaces) |
| Password | Minimum 8 characters |
| Display Name | Your name as shown in the admin panel |
| Email | Used for notifications (optional) |

This creates a `superadmin` account with full access.

### Step 5 — Finalize

The installer writes `admin/db_config.php` with your database credentials.

Click **Complete Installation**.

### Step 6 — Done

Installation is complete. The installer is now locked — it cannot be run again.

Click **Go to Application** or **Admin Dashboard**.

---

## Part 4: First Login

1. Navigate to `https://yoursite.com/login.php`
2. Enter your admin username and password
3. You are redirected to `admin/dashboard.php`

---

## Part 5: Initial Configuration

After logging in, configure the system:

### 5.1 Add Locations

1. Go to **Admin** → **Locations** (`admin/locations_manage.php`)
2. Click **Add Location**
3. Enter the location name (e.g., "Kantoor", "Thuiswerken", "Vestiging Rotterdam")
4. Repeat for each physical location

### 5.2 Add Departments

1. Go to **Admin** → **Departments** (`admin/afdelingen_manage.php`)
2. Click **Add Department**
3. Enter department name (e.g., "Leerkrachten", "Ondersteuning", "Directie")

### 5.3 Add Employees

**Manually:**
1. Go to **Admin** → **Employees** (`admin/employees_manage.php`)
2. Click **Add Employee**
3. Fill in: first name, last name, function, department, location, BHV status
4. Upload a profile photo (optional)

**Via CSV import:**
1. Go to **Admin** → **Employees**
2. Click **Import CSV**
3. Download the template, fill it in, upload

### 5.4 Configure Status Buttons

1. Go to **Admin** → **Configuration** (`admin/config_manage.php`)
2. Set custom names for the 3 extra status buttons
3. Enable "Ask until time" to prompt for duration when button is pressed

### 5.5 Create Additional Users

1. Go to **Admin** → **Users** (`admin/users_manage.php`)
2. Click **Add User**
3. Assign role: `user` (view only), `admin` (manage), or `superadmin` (full access)
4. Set which features are enabled for this user

---

## Part 6: Display Setup

### Main Display (check-in screen)

The main display is at `https://yoursite.com/index.php` (or just `https://yoursite.com/`).

This is the screen shown on a wall-mounted TV or tablet where employees tap IN/OUT.

**Recommended setup:**
- Open in Chrome/Firefox in fullscreen mode (F11)
- Set browser to auto-start fullscreen
- Use a Kiosk token for unattended devices (see below)

### Overview Screen

The overview at `https://yoursite.com/overzicht.php` shows all employees currently IN, sorted by name or status. Useful for a second monitor or printout.

### Kiosk Mode (unattended devices)

For devices that should auto-login without a password:

1. Go to **Admin** → **Kiosk Tokens** (`admin/kiosk_tokens_manage.php`)
2. Create a new token for the device
3. Copy the token URL (format: `https://yoursite.com/kiosk_login.php?token=XXX`)
4. Open this URL on the kiosk device and bookmark it

---

## Part 7: Visitor Registration (Optional)

The visitor registration system allows visitors to check in and out digitally.

**For visitors:**
- Registration: `https://yoursite.com/visitor_register.php`
- Check-in: `https://yoursite.com/visitor_checkin.php`
- Check-out: `https://yoursite.com/visitor_checkout.php`

**For administrators:**
- Manage visitors: `admin/visitors_manage.php`
- Email notification settings: `admin/visitor_email_config.php`

**To enable email notifications:**
1. Configure SMTP in `includes/email_config.php`
2. Set the fallback notification email in Admin → Configuration

---

## Part 8: Cron Jobs (Optional)

Automated tasks require cron job access on your server.

### Strato/cPanel

In your hosting control panel, add cron jobs:

```
# Reset expired sub-statuses every 5 minutes
*/5 * * * * curl -s "https://yoursite.com/cron_endpoint.php?action=reset_sub_status" > /dev/null 2>&1

# Auto check-out at end of working day (18:00)
0 18 * * * curl -s "https://yoursite.com/cron_endpoint.php?action=auto_checkout" > /dev/null 2>&1

# Daily cleanup (2:00 AM)
0 2 * * * curl -s "https://yoursite.com/cron_endpoint.php?action=cleanup" > /dev/null 2>&1
```

### Without Cron Access

Sub-status auto-expiry runs automatically on every API call (no cron needed for this).
Other scheduled tasks will not run without cron, but the core system works normally.

---

## Troubleshooting

### The page shows "403 Forbidden"

The web server cannot access the files. Check:
- File permissions: PHP files should be `644`, directories `755`
- `.htaccess` file is present in the root
- Apache `AllowOverride All` is set (for XAMPP, edit `httpd.conf`)

### The installer shows a blank page

PHP errors are being suppressed. Check the PHP error log:
- cPanel: **Error Log** in File Manager or Logs section
- XAMPP: `xampp/apache/logs/error.log`

Common cause: PHP syntax error from incompatible PHP version.

### "Could not write db_config.php"

The installer cannot write the config file. Fix by:
1. Via FTP: create an empty file `admin/db_config.php` and set permissions to `666`
2. Or: manually copy `config.example.php` to `admin/db_config.php` and edit credentials

### Status updates don't save

1. Open browser DevTools (F12) → Network tab
2. Click an IN/OUT button
3. Find the `employees_api.php` request
4. Check the response — it should be: `{"success":true,...}`
5. If you see PHP warnings before the JSON, check the PHP error log and fix the reported errors

### Login redirects back to login page

Session is not persisting. Possible causes:
- `tmp/sessions/` not writable — solution: delete this directory and let PHP use its default session path
- Cookie blocked by browser — check browser privacy settings
- Wrong domain in session configuration

For Strato/Plesk/cPanel, sessions use the server's default path — this works automatically.

---

## Uninstalling

1. Delete all PeopleDisplay files from your server
2. Drop the database (or all 15 PeopleDisplay tables) in phpMyAdmin
3. Done — no other changes made to your server

---

## Getting Help

Check the README.md for a feature overview and quick-start instructions.

For the audit log and user activity tracking, see `admin/audit_log.php` in the admin panel.
