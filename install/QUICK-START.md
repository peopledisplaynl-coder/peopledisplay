# ⚡ QUICK START - People Display Installer

## 🔥 PROBLEEM OPGELOST!

De **visibleFields fout** is gefixed! Gebruik deze guide om je installatie werkend te krijgen.

---

## 📦 JE HEBT NU:

```
✅ /install/         - Complete installer wizard (10 bestanden)
✅ /admin/config_manage.php - Gefixte versie
```

---

## 🚀 NIEUWE INSTALLATIE (5 minuten)

```bash
# 1. Plaats bestanden
Kopieer naar: C:\xampp\htdocs\peopledisplay

# 2. Start XAMPP
Start Apache + MySQL

# 3. Open browser
http://localhost/peopledisplay/install/

# 4. Volg wizard (5 stappen)
Step 1: Checks ✓
Step 2: Database (root, leeg wachtwoord)
Step 3: Admin account
Step 4: Google Sheets (skip = OK)
Step 5: Klik "Beveilig Installatie"!

# 5. Login
http://localhost/peopledisplay/index.php
```

**Klaar! ✅**

---

## 🔧 BESTAANDE INSTALLATIE FIXEN (2 minuten)

```bash
# 1. BACKUP!
mysqldump -u root -p peopledisplay > backup.sql

# 2. Upload install directory naar je server

# 3. Run migration
http://localhost/peopledisplay/install/migrate.php

# 4. Check resultaat
http://localhost/peopledisplay/install/check-database.php

# 5. Test config
http://localhost/peopledisplay/admin/config_manage.php
```

**Error is weg! ✅**

---

## 🎯 WAT IS GEFIXED?

### VOOR (❌ Fout):
```
Config Beheer → Opslaan
❌ Error: Unknown column 'visibleFields'
```

### NA (✅ Werkt):
```
Config Beheer → Opslaan
✅ Success! Configuratie opgeslagen

Visible Fields → Features Beheer
✅ Per user configuratie
```

---

## 🔍 VERIFICATIE

Test dit na installatie/migration:

```bash
# 1. Login als admin
✅ Moet werken

# 2. Open Config Beheer
http://localhost/peopledisplay/admin/config_manage.php
✅ Moet openen zonder errors

# 3. Wijzig iets en klik Opslaan
✅ Moet opslaan zonder visibleFields error

# 4. Check database
http://localhost/peopledisplay/install/check-database.php
✅ Moet "Database is correct geïnstalleerd" tonen
```

---

## ⚠️ BELANGRIJKE STAPPEN

### 1. NA INSTALLATIE: Beveilig!
```bash
# Verwijder of hernoem install directory:
mv install/ install.bak/
# OF via installer: klik "Beveilig Installatie"
```

### 2. VOOR MIGRATION: Backup!
```bash
mysqldump -u root -p peopledisplay > backup_$(date +%Y%m%d).sql
```

---

## 🆘 PROBLEMEN?

### "visibleFields error" blijft
→ Run `install/migrate.php` nogmaals

### Database connectie fails
→ Check XAMPP MySQL is gestart  
→ Check credentials (root, leeg wachtwoord voor XAMPP)

### Permission denied
→ `chmod 755 peopledisplay/` (Linux)  
→ Check schrijfrechten (Windows)

---

## 📱 SUPPORT FILES

```
install/README.md              - Volledige documentatie
install/migrate.php            - Fix voor bestaande installaties
install/check-database.php     - Database verificatie
INSTALLATIE-SAMENVATTING.md    - Complete uitleg
```

---

## ✅ SUCCESS CHECKLIST

- [ ] Installer wizard compleet
- [ ] Admin kan inloggen
- [ ] Config Beheer opent
- [ ] Config Beheer kan opslaan (GEEN error!)
- [ ] Features Beheer beschikbaar
- [ ] Install directory verwijderd/beveiligd

**Als alles ✅ is: Perfect!**

---

## 🎉 Klaar!

Je systeem is nu **fully functional** zonder visibleFields errors!

**Volgende stappen:**
1. Configureer je locaties
2. Voeg users toe
3. Stel features per user in
4. Test met verschillende accounts

---

*Gemaakt: 7 november 2025*  
*People Display v3.0*
