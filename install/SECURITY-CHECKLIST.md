# ============================================
# PEOPLEDISPLAY SECURITY CHECKLIST
# ============================================

## 📦 BESTANDSLOCATIES:

Upload deze .htaccess bestanden naar de juiste locaties:

1. **/.htaccess** (root)
   - Basis beveiliging + PWA support
   - Force HTTPS (uncomment in productie)
   - Browser caching
   - GZIP compressie

2. **/admin/.htaccess**
   - Extra beveiliging admin area
   - Blokkeer db_config.php
   - No-cache headers
   - SQL injection bescherming

3. **/uploads/.htaccess**
   - Blokkeer PHP executie
   - Alleen images/PDFs toestaan
   - Voorkom malware uploads

4. **/install/.htaccess**
   - Bescherm installer
   - NA INSTALLATIE: Uncomment RequireAll block

---

## ✅ POST-INSTALLATIE CHECKLIST:

### 1. Installer beveiligen
```bash
# Optie A: Verwijder installer (aanbevolen)
rm -rf /install/

# Optie B: Hernoem installer
mv /install/ /install_backup_20251115/

# Optie C: Blokkeer via .htaccess
# Edit /install/.htaccess en uncomment de RequireAll block
```

### 2. Database beveiliging
- [ ] Check dat `db_config.php` niet direct toegankelijk is
- [ ] Test: `https://jouwdomein.nl/admin/db_config.php` → moet 403 geven
- [ ] Gebruik sterke database wachtwoorden
- [ ] Beperk database privileges (geen DROP/CREATE rechten voor productie)

### 3. Bestandspermissies (via SSH/FTP)
```bash
# Directories: 755
chmod 755 /admin/
chmod 755 /uploads/
chmod 755 /user/
chmod 755 /includes/

# PHP files: 644
chmod 644 index.php
chmod 644 admin/*.php
chmod 644 includes/*.php

# Config bestanden: 640 (alleen owner en groep)
chmod 640 admin/db_config.php
chmod 640 includes/db.php

# Upload directories: 755 (writable)
chmod 755 uploads/profiles/
chmod 755 tmp/sessions/
```

### 4. Session beveiliging
- [ ] Check `tmp/sessions/` bestaat en is writable
- [ ] Permissions: `chmod 700 tmp/sessions/`
- [ ] Session files zijn niet publiek toegankelijk

### 5. HTTPS forceren (productie)
Edit `/.htaccess` en uncomment deze regels:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 6. Error reporting (productie)
In `/includes/db.php` check dat:
```php
if($isLocal){
    error_reporting(E_ALL);
    ini_set("display_errors","1");
}
// Anders geen errors tonen aan gebruikers
```

### 7. Backup strategie
- [ ] Maak dagelijkse database backups
- [ ] Test restore procedure
- [ ] Bewaar backups BUITEN webroot
- [ ] Gebruik `/admin/backups/` folder (beschermd via .htaccess)

### 8. PWA bestanden (als je PWA geïnstalleerd hebt)
- [ ] `manifest.json` in root
- [ ] `service-worker.js` in root
- [ ] Icons in `/images/icons/`
- [ ] Check dat service-worker NIET gecached wordt

### 9. Google Apps Script beveiliging
- [ ] Script heeft NIET "Anyone" access
- [ ] Script heeft alleen "Anyone with the link"
- [ ] Sheet heeft juiste permissions (niet publiek)

### 10. User management
- [ ] Verwijder test accounts
- [ ] Force password change voor default admin
- [ ] Check user roles zijn correct
- [ ] Test dat users alleen hun eigen data zien

---

## 🔒 EXTRA BEVEILIGINGSOPTIES:

### IP Whitelisting (admin area)
Edit `/admin/.htaccess` en uncomment:
```apache
<RequireAll>
    Require all denied
    Require ip 123.456.789.0  # Jouw kantoor IP
    Require ip 98.765.432.1   # Jouw thuis IP
</RequireAll>
```

### Basic Auth (extra login voor admin)
```bash
# Maak .htpasswd bestand
htpasswd -c /pad/buiten/webroot/.htpasswd admin_user

# In /admin/.htaccess toevoegen:
AuthType Basic
AuthName "Admin Area"
AuthUserFile /pad/buiten/webroot/.htpasswd
Require valid-user
```

### Rate Limiting
Als server dit ondersteunt, enable in `.htaccess`:
```apache
<IfModule mod_ratelimit.c>
    SetOutputFilter RATE_LIMIT
    SetEnv rate-limit 400
</IfModule>
```

---

## 🚨 SECURITY MONITORING:

### Error logs checken
```bash
# Via SSH
tail -f /path/to/error_log

# Via cPanel
# → Error Logs → Select domain
```

### Verdachte activiteit:
- Veel 403/404 errors
- SQL injection pogingen in logs
- Onbekende user agents
- Requests naar `/admin/` van vreemde IPs

---

## 📱 PWA SPECIFIC SECURITY:

### Service Worker
- [ ] Check dat `service-worker.js` up-to-date is
- [ ] Test dat updates werken (versie nummer verhogen)
- [ ] Check dat sensitieve data NIET in cache zit

### Manifest.json
- [ ] `start_url` is correct
- [ ] `scope` is beperkt tot jouw domein
- [ ] Icons zijn geoptimaliseerd

---

## 🧪 SECURITY TESTS:

### Online scanners (gebruik met voorzichtigheid):
1. **SSL Test**: https://www.ssllabs.com/ssltest/
2. **Headers Check**: https://securityheaders.com/
3. **Observatory**: https://observatory.mozilla.org/

### Handmatige tests:
```bash
# Test of config bestanden geblokkeerd zijn
curl https://jouwdomein.nl/admin/db_config.php
# Verwacht: 403 Forbidden

# Test of directory listing uit staat
curl https://jouwdomein.nl/uploads/
# Verwacht: 403 Forbidden

# Test HTTPS redirect (als enabled)
curl -I http://jouwdomein.nl/
# Verwacht: 301 redirect naar https://
```

---

## 📞 BIJ SECURITY INCIDENT:

1. **Neem site offline** (maintenance mode)
2. **Check error logs** voor intrusion patterns
3. **Reset alle wachtwoorden**
4. **Check database** op onbekende users/wijzigingen
5. **Scan bestanden** op malware
6. **Restore vanuit backup** (als nodig)
7. **Update alle software**
8. **Versterk beveiliging** (IP whitelist, 2FA, etc.)

---

## ✅ FINAL CHECKS VOOR GO-LIVE:

- [ ] Alle .htaccess bestanden geüpload
- [ ] HTTPS geforceerd
- [ ] Installer verwijderd/geblokkeerd
- [ ] db_config.php niet toegankelijk
- [ ] Error logs leeg (geen kritieke fouten)
- [ ] Backup systeem werkt
- [ ] Test login (admin + user)
- [ ] Test alle functionaliteit
- [ ] PWA installeert correct (als applicable)
- [ ] Performance test (Google PageSpeed)

---

**Laatste update: 15 november 2025**
**Versie: 2.0**
