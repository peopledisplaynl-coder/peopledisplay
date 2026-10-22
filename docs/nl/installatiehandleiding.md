---
title: "PeopleDisplay — Installatiehandleiding"
subtitle: "Versie 2.1.10"
lang: nl-NL
---

# Introductie

Deze handleiding beschrijft hoe je PeopleDisplay op je eigen webhosting installeert, de automatische taken (cronjobs) instelt, een licentie activeert, bijwerkt naar een nieuwe versie en verhuist naar een andere hosting.

PeopleDisplay werkt op gewone gedeelde hosting, zoals Strato, Cloud86, TransIP, Antagonist, cPanel- of Plesk-pakketten. Je hebt geen eigen server of SSH-toegang nodig: alles gaat via FTP en je browser.

# Wat heb je nodig?

- Webhosting met **PHP 8.0 of hoger** (8.2 of nieuwer aanbevolen), met de extensies `pdo_mysql`, `json`, `curl` en `gd` (voor badges en foto's).
- Een **MySQL 5.7+ of MariaDB 10.3+** database, met de naam, gebruikersnaam en het wachtwoord bij de hand.
- **FTP- of SFTP-toegang**, bijvoorbeeld met FileZilla.
- Minimaal **50 MB** schijfruimte, plus ruimte voor foto's en back-ups.
- Bij voorkeur een eigen (sub)domein met **HTTPS**, bijvoorbeeld `aanmelden.jouwschool.nl`.

# Stap 1 — Downloaden

Download de nieuwste versie via [peopledisplay.nl/download.php](https://peopledisplay.nl/download.php). De Starter-versie is gratis. Je krijgt het bestand `peopledisplay-starter.zip`.

Alle versies staan ook op GitHub: [github.com/peopledisplaynl-coder/peopledisplay/releases](https://github.com/peopledisplaynl-coder/peopledisplay/releases).

# Stap 2 — Database aanmaken

Maak in het beheerpaneel van je hosting een nieuwe database aan. Noteer:

- de **databaseserver** (vaak `localhost`; sommige hostingpartijen gebruiken een eigen adres, dat staat dan bij de databasegegevens),
- de **databasenaam**,
- de **gebruikersnaam** en het **wachtwoord**.

# Stap 3 — Bestanden uploaden

1. Pak `peopledisplay-starter.zip` uit op je computer.
2. Verbind met FTP met je hosting en open de map van het (sub)domein waarop PeopleDisplay moet komen.
3. Upload **alle** bestanden en mappen uit de ZIP naar die map. De bestanden `index.php` en `install.php` moeten direct in de map van het domein staan.
4. Controleer dat de mappen `admin`, `uploads/profiles`, `logs`, `tmp` (met `tmp/badge_photos` en `tmp/sessions`) en `backups` schrijfbaar zijn (rechten 755). Bij de meeste hostingpakketten is dat al zo; de wizard controleert het in stap 1.

# Stap 4 — De installatie-wizard

Open in je browser `https://jouwdomein.nl/install.php`. De wizard leidt je in acht stappen door de installatie.

## 1. Welkom — systeemcontrole

De wizard controleert de PHP-versie, de benodigde extensies en de schrijfrechten. Staat er bij een map **LET OP**, maak die map dan via FTP schrijfbaar (rechten 755) en vernieuw de pagina. Klik daarna op **Volgende: Voorwaarden**.

![Stap 1: systeemcontrole](../images/install-1-controle.png)

## 2. Voorwaarden

Lees de gebruiksvoorwaarden (Nederlands of Engels), vink aan dat je akkoord gaat en klik op **Akkoord & Verder: Licentie**.

![Stap 2: voorwaarden](../images/install-2-voorwaarden.png)

## 3. Licentie

Heb je een licentiecode (voor Professional t/m Unlimited)? Vul hem in en klik op **Valideer & Verder**. Wil je de gratis Starter-versie, klik dan op **Doorgaan als Starter (gratis)**. Een licentie kun je later ook nog activeren.

![Stap 3: licentie](../images/install-3-licentie.png)

## 4. Database

Vul de databasegegevens uit stap 2 in en klik op **Verbinding testen & Volgende**. Bestaat de database nog niet, dan probeert de wizard hem aan te maken.

![Stap 4: database](../images/install-4-database.png)

## 5. Schema

Klik op **Database tabellen aanmaken**. De wizard maakt alle tabellen aan. Bestaande tabellen worden niet overschreven.

![Stap 5: tabellen aanmaken](../images/install-5-tabellen.png)

## 6. Beheerder

Maak het eerste beheerdersaccount aan: gebruikersnaam, weergavenaam, e-mailadres en een wachtwoord van minimaal 8 tekens (twee keer). Klik op **Account aanmaken**. Dit account krijgt de rol **SuperAdmin**.

![Stap 6: beheerdersaccount](../images/install-6-beheerder.png)

## 7. Afronden

Klik op **Installatie voltooien**. De wizard schrijft de databaseverbinding naar `admin/db_config.php` en activeert je licentie.

![Stap 7: afronden](../images/install-7-voltooien.png)

> **Kan de wizard `admin/db_config.php` niet schrijven?** Dan toont hij de inhoud. Maak dat bestand dan zelf aan via FTP en klik op **Voltooien**.

## 8. Klaar

PeopleDisplay is geïnstalleerd. De wizard vergrendelt zichzelf: `install.php` kan niet opnieuw worden gestart.

![Stap 8: klaar](../images/install-8-klaar.png)

# Stap 5 — Eerste inrichting

Log in met het beheerdersaccount. Je komt op het admin-dashboard. Richt PeopleDisplay daarna in deze volgorde in (zie de Beheerdershandleiding):

1. **Locaties** — je vestigingen.
2. **Afdelingen** — je teams of afdelingen.
3. **Medewerkers** — handmatig of via **Import CSV**.
4. **Gebruikers** — accounts voor collega's en voor gedeelde schermen, met de juiste locaties en features.
5. **Systeemconfiguratie** — de namen van de extra knoppen.
6. Eventueel **kiosk tokens** voor tablets en schermen.

# Stap 6 — Automatische taken (cronjobs)

Sommige taken moeten op vaste tijden draaien: extra statussen terugzetten als hun einddatum verstreken is, iedereen 's avonds op UIT zetten, bezoekers uitchecken en oude gegevens opruimen. Zonder cronjobs werkt PeopleDisplay gewoon, maar gebeurt dit niet automatisch.

| Script | Wanneer | Wat het doet |
|---|---|---|
| `cron/cron_reset_expired_sub_status.php` | elke 5 minuten | Zet extra statussen terug waarvan de einddatum voorbij is |
| `cron/daily_reset_cron.php` | elke dag 23:00 | Zet alle medewerkers op UIT |
| `cron/cron_auto_checkout.php` | elke dag 23:55 | Checkt bezoekers uit (behalve meerdaagse bezoeken) |
| `cron/cron_auto_delete.php` | elke dag 02:00 | Verwijdert bezoekersgegevens na 7 dagen (AVG) |
| `cron/cleanup_tokens_cron.php` | elke dag 02:00 | Ruimt verlopen "blijf ingelogd"-tokens op |

## Via het beheerpaneel van je hosting (aanbevolen)

Bijna elke hostingpartij heeft een onderdeel **Cronjobs** of **Geplande taken**. Maak per script een taak aan die PHP uitvoert, bijvoorbeeld:

```
/usr/bin/php /home/jouwaccount/aanmelden.jouwschool.nl/cron/daily_reset_cron.php
```

Het juiste pad naar PHP en naar je map vind je in de helpteksten van je hosting.

## Via cron-job.org (als je hosting geen cronjobs heeft)

Met de gratis dienst [cron-job.org](https://cron-job.org) roep je de scripts op vaste tijden aan via internet. Maak per script een taak met het webadres, bijvoorbeeld:

```
https://aanmelden.jouwschool.nl/cron/daily_reset_cron.php?key=JOUW-SLEUTEL
```

De sleutel (`key`) staat bovenin het script. Scripts die via internet worden aangeroepen, weigeren een verzoek zonder de juiste sleutel.

# Licentie activeren

Gebruik je de gratis Starter-versie, dan hoef je niets te doen. Heb je een licentie gekocht:

1. Log in als SuperAdmin en open **Licentiebeheer** in het admin-dashboard.
2. Klik op **Nieuwe Licentie Activeren** en vul je licentiecode in.
3. De licentie wordt gekoppeld aan het domein van deze installatie.

Bij een Unlimited- of Corporate-licentie verschijnt in Licentiebeheer ook een **continuity-sleutel**. Bewaar die goed: daarmee neem je je licentie mee naar een ander domein of andere hosting.

# Bijwerken naar een nieuwe versie

Op het admin-dashboard verschijnt een melding als er een nieuwe versie is.

1. Maak eerst een **back-up** via **Backup aanmaken** (type *Volledige backup*).
2. Klik in de melding op **Update downloaden**. Het ZIP-bestand bevat alleen de gewijzigde bestanden, in een map met het versienummer.
3. Pak het uit en upload de inhoud van die map met FTP naar je PeopleDisplay-map. Overschrijf de bestaande bestanden.
4. Open het admin-dashboard. Databasewijzigingen worden automatisch doorgevoerd; rechtsboven zie je **Migraties toegepast**.

Je instellingen (`admin/db_config.php`), foto's, uploads en gegevens worden door een update nooit overschreven. Open schermen en geïnstalleerde apps laden de nieuwe versie binnen enkele minuten vanzelf.

# Verhuizen naar een andere hosting

Met `restore.php` zet je een volledige back-up terug op een nieuwe hosting of een nieuw domein, zonder hulp van buitenaf.

1. Maak op de oude omgeving een **Volledige backup** en download het ZIP-bestand.
2. Upload de bestanden van PeopleDisplay naar de nieuwe hosting (zoals bij een nieuwe installatie), maar start `install.php` **niet**.
3. Maak op de nieuwe hosting een lege database aan.
4. Open `https://nieuwdomein.nl/restore.php`. Kies het back-upbestand, vul de nieuwe databasegegevens in en bevestig.
5. Log in en controleer of alles er is.
6. Heb je een Unlimited- of Corporate-licentie, wissel dan op `https://nieuwdomein.nl/activate_license.php` je **continuity-sleutel** in. Andere licenties activeer je opnieuw via Licentiebeheer.

`restore.php` werkt alleen zolang er nog geen installatie op de nieuwe omgeving staat, en vergrendelt zichzelf daarna.

# Problemen oplossen

**Witte pagina of "500 Internal Server Error".**
Bekijk het PHP-foutlog in het beheerpaneel van je hosting. Controleer of de PHP-versie 8.0 of hoger is en of het bestand `.htaccess` is meegekomen bij het uploaden (het begint met een punt en wordt soms verborgen).

**"DB config not found" of "DB error".**
`admin/db_config.php` ontbreekt of bevat verkeerde gegevens. Controleer de databasegegevens bij je hosting en pas het bestand aan via FTP.

**"Too many redirects" of steeds opnieuw inloggen.**
Wis de cookies van de site in je browser. Controleer dat de map `tmp/sessions` schrijfbaar is.

**De installatie is vergrendeld, maar ik wil opnieuw installeren.**
Verwijder via FTP het bestand `install/.installed` en `admin/db_config.php`, en leeg de database. Let op: dan ben je alle gegevens kwijt.

**Statuswijzigingen worden niet bewaard.**
Open in de browser de ontwikkelaarshulpmiddelen (F12), tabblad *Netwerk*, en klik op IN of UIT. Het antwoord van `employees_api.php` moet beginnen met `{"success":true`. Staan daar PHP-waarschuwingen voor, kijk dan in het PHP-foutlog.

**Er komen geen e-mails aan.**
Controleer de spammap en vraag je hosting of uitgaande e-mail via PHP is toegestaan.
