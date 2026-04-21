# People Display - WordPress-Style Installer

## 🎯 Wat is dit?

Een complete, gebruiksvriendelijke installer voor People Display v3.0, vergelijkbaar met WordPress/Joomla installers.

## ✨ Features

### Installer Wizard (5 Stappen)
1. **Requirements Check** - Controleert PHP versie, extensies, permissions
2. **Database Setup** - Maakt automatisch database en tabellen aan
3. **Admin Account** - Creëert eerste superadmin gebruiker
4. **Site Configuration** - Configureert Google Sheets integratie
5. **Complete** - Samenvatting en volgende stappen

### Database Structuur
- `users` - Gebruikers met role-based access en features JSON
- `config` - Site configuratie met Google Sheets settings
- `button_config` - Aanpasbare button instellingen

### Automatische Setup
- ✅ Database auto-create met UTF-8 support
- ✅ Config files genereren (config.php, db_config.php, includes/db.php)
- ✅ Migration script voor upgrades
- ✅ Production/localhost detectie
- ✅ Security checks en cleanup

## 📦 Installatie Instructies

### XAMPP Gebruikers (Localhost)

1. **Download en Extract**
   ```
   Plaats de 'peopledisplay' folder in:
   C:\xampp\htdocs\peopledisplay
   ```

2. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache en MySQL

3. **Run Installer**
   ```
   Browser: http://localhost/peopledisplay/install/
   ```

4. **Volg de 5 Stappen**
   - Stap 1: Alle checks moeten groen zijn
   - Stap 2: Gebruik standaard XAMPP instellingen:
     * Host: `localhost`
     * Database: `peopledisplay` (of eigen naam)
     * User: `root`
     * Password: (leeg laten)
     * Port: `3306`
   - Stap 3: Maak admin account aan
   - Stap 4: Configureer Google Sheets (of skip)
   - Stap 5: Beveilig installatie!

5. **Beveilig de Installatie**
   - Klik op "🔒 Beveilig Installatie" button
   - OF verwijder handmatig: `install/` folder

6. **Login**
   ```
   http://localhost/peopledisplay/index.php
   ```

### Production Server

1. **Upload Files**
   - Upload alle bestanden naar je webserver
   - Zorg voor schrijfrechten op folders

2. **Create Database** (optioneel - installer doet dit automatisch)
   ```sql
   CREATE DATABASE peopledisplay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Run Installer**
   ```
   https://jouwdomein.nl/install/
   ```

4. **Na Installatie**
   - Verwijder of hernoem `install/` directory!
   - Update BASE_URL in config.php indien nodig

## 🔧 Migreren van Oude Versie

Als je al een oudere versie hebt:

1. **Backup Database**
   ```sql
   mysqldump -u root -p peopledisplay > backup.sql
   ```

2. **Run Migration Script**
   ```
   http://localhost/peopledisplay/install/migrate.php
   ```

3. **Belangrijke Fixes in Migration:**
   - Verwijdert `visibleFields` kolom uit config tabel
   - Migreert visible fields naar user features JSON
   - Voegt `active` kolom toe aan users
   - Repareert JSON structuren
   - Voegt indexes toe

## ⚠️ VisibleFields Fix

### Het Probleem
Oude versies hadden een `visibleFields` kolom in de `config` tabel. Dit veroorzaakte de fout:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'visibleFields' in 'field list'
```

### De Oplossing
In v3.0 worden visible fields per user opgeslagen in de `features` JSON kolom:

```json
{
  "canViewAll": false,
  "visibleLocations": [],
  "visibleFields": {
    "locatie": true,
    "naam": true,
    "aanwezig": true,
    "status": true,
    "notities": true
  },
  "buttons": {
    "button1": true,
    "button2": true,
    "button3": false
  }
}
```

### Hoe te Fixen
1. Run `install/migrate.php` - dit verwijdert de oude kolom en migreert data
2. Gebruik `admin/features_manage.php` om per user visible fields te configureren
3. De `admin/config_manage.php` probeert niet meer om visibleFields op te slaan

## 📁 Bestandsstructuur

```
peopledisplay/
├── install/                    # Installer (VERWIJDER NA INSTALLATIE!)
│   ├── index.php              # Hoofdbestand installer wizard
│   ├── step1-requirements.php # Requirements check
│   ├── step2-database.php     # Database setup
│   ├── step3-admin.php        # Admin account
│   ├── step4-config.php       # Site configuratie
│   ├── step5-complete.php     # Voltooiing
│   └── migrate.php            # Migration script voor upgrades
│
├── admin/                      # Admin panel
│   ├── dashboard.php
│   ├── config_manage.php      # FIXED: Geen visibleFields meer!
│   ├── features_manage.php    # Per-user feature config
│   ├── locations_order.php    # Drag & drop locaties
│   ├── db_config.php          # (gegenereerd door installer)
│   └── api/                   # API endpoints
│
├── includes/
│   ├── db.php                 # (gegenereerd door installer)
│   └── locations_helper.php
│
├── user/
│   └── profile.php
│
├── config.php                 # (gegenereerd door installer)
├── index.php                  # Login pagina
├── frontpage.php              # Welkom pagina
├── overzicht.php              # Overzicht aanwezigen
├── app.js                     # Frontend logic
└── style.css                  # Styling
```

## 🎨 Features na Installatie

### Voor Superadmin
- ✅ Volledige toegang tot alle locaties
- ✅ Config management (Google Sheets, site settings)
- ✅ User management (toevoegen, bewerken, verwijderen)
- ✅ Features per user configureren
- ✅ Locaties sorteren (drag & drop)

### Voor Admin
- ✅ Toegang tot toegewezen locaties
- ✅ User features beheren
- ✅ Overzicht bekijken

### Voor User
- ✅ Toegang tot eigen locaties
- ✅ Alleen toegewezen velden zichtbaar
- ✅ Buttons on/off per user

## 🔐 Beveiliging

### Na Installatie VERPLICHT
1. **Verwijder install directory**
   ```bash
   rm -rf install/
   # OF hernoem naar install.bak
   ```

2. **Sterke wachtwoorden**
   - Gebruik minimaal 8 karakters
   - Mix van letters, cijfers, symbolen

3. **Database credentials**
   - Gebruik geen root account in productie
   - Maak dedicated database user aan

### Productie Best Practices
```php
// config.php - pas aan voor productie
if (!IS_LOCALHOST) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}
```

## 📊 Google Sheets Integratie

### Setup Stappen
1. Maak Google Sheet met kolommen:
   - Locatie
   - Naam
   - Aanwezig
   - Status
   - Notities

2. Extensions → Apps Script → Plak code:
```javascript
function doGet(e) {
  const sheet = SpreadsheetApp.getActiveSheet();
  const data = sheet.getDataRange().getValues();
  // ... zie volledige Apps Script code in docs
}

function doPost(e) {
  // Handle updates from People Display
}
```

3. Deploy as Web App
4. Kopieer URL naar config

### Test Connectie
```
Admin → Config Beheer → Test Connectie
```

## 🆘 Troubleshooting

### Fout: "Column 'visibleFields' not found"
**Oplossing:** Run `install/migrate.php`

### Database connectie mislukt
**Check:**
- Is MySQL gestart?
- Zijn credentials correct?
- Bestaat database?

### Permission denied errors
**Fix:**
```bash
chmod 755 peopledisplay/
chmod 755 peopledisplay/admin/
chmod 755 peopledisplay/includes/
```

### Installer toont "Already installed"
**Forceer opnieuw:**
```
http://localhost/peopledisplay/install/?force=1
```

### Config.php niet aangemaakt
**Check:**
- Schrijfrechten op root directory
- PHP errors in browser console
- Run installer opnieuw

## 📞 Support

### Logfiles Checken
```bash
# XAMPP
C:\xampp\apache\logs\error.log

# Linux
/var/log/apache2/error.log
```

### Database Handmatig Resetten
```sql
DROP DATABASE IF EXISTS peopledisplay;
-- Dan installer opnieuw runnen
```

## 🚀 Volgende Stappen na Installatie

1. **Login als admin**
2. **Configureer locaties** (Admin → Locaties Volgorde)
3. **Test Google Sheets** (Admin → Config Beheer)
4. **Voeg users toe** (Admin → Gebruikersbeheer)
5. **Stel features in** (Admin → Features Beheer)
6. **Test het systeem** met verschillende user accounts

## 📝 Database Schema Details

### Users Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100),
    role ENUM('user','admin','superadmin') DEFAULT 'user',
    active TINYINT(1) DEFAULT 1,
    features LONGTEXT CHECK (json_valid(features)),
    locations LONGTEXT CHECK (json_valid(locations)),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Config Table
```sql
CREATE TABLE config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scriptURL TEXT,
    sheetID VARCHAR(255),
    presentationID VARCHAR(255),
    allow_auto_fullscreen TINYINT(1) DEFAULT 0,
    locations LONGTEXT CHECK (json_valid(locations)),
    locations_order LONGTEXT CHECK (json_valid(locations_order)),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 🎉 Success!

Als alles goed is gegaan:
- ✅ Database is aangemaakt met 3 tabellen
- ✅ Config files zijn gegenereerd
- ✅ Eerste admin account is aangemaakt
- ✅ Systeem is klaar voor gebruik!

**Veel succes met People Display v3.0!** 🚀
