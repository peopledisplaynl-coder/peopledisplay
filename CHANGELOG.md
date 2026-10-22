# PeopleDisplay Changelog

> Automatisch gegenereerd — niet handmatig bewerken.

## v2.1.9 — 2026-10-22

- Onderhoud: versienummers gelijkgetrokken (version.json, version.php en installer)
- Fix: installatie-wizard gebruikt nu hetzelfde, actuele databaseschema als install.php (home_locatie en continuity-kolommen zaten er nog niet in)
- Opgeruimd: ongebruikt voorbeeldbestand includes/config.json verwijderd
- Intern: ontwikkeldocumentatie, controles en deploy-script toegevoegd — geen functionele wijzigingen voor gebruikers

## v2.1.8 — 2026-09-16

- Fix: license_log tabel wordt nu automatisch aangemaakt op oudere installaties waar die ontbrak
- Fix: medewerkerkaarten met minimale featurebeheer-instellingen zijn niet langer onnodig groot
- Verbeterd: strakkere IN/UIT-knoppen en minder ruimte rond afdeling, locatie en Pion-knop

## v2.1.7 — 2026-09-16

- Nieuw: continuity-sleutel voor Unlimited- en Corporate-licenties — licentie blijft werken na verhuizing naar een ander domein of andere hosting
- Nieuw: restore.php — volledige back-up (database + bestanden) zelf terugzetten op een nieuwe omgeving
- Fix: licenties vielen in sommige gevallen terug op de Starter-limieten
- Fix: syntaxfout en ontbrekende inlogcontrole in de config-back-up hulpmiddelen

## v2.1.6 — 2026-07-18

- Nieuw: instelbare standaard sorteervolgorde voor medewerkerkaarten, met optionele sorteerknop
- Nieuw: instelbaar zoomniveau voor de kaarten, met optionele +/- knoppen
- Nieuw: zoekfilters op het aanmeldscherm worden na 1 minuut inactiviteit gewist
- Fix: update-melding biedt nu de kleine delta-update aan in plaats van de volledige installatie

## v2.1.5 — 2026-06-02

- Nieuw: schermen en PWA controleren elke 5 minuten (en bij terugkeer naar de app) of er een nieuwe versie is en herladen automatisch
- Nieuw: api/app-version.php
- Geen databasewijzigingen

## v2.1.4 — 2026-05-31

- Fix: medewerker die via de Pion op een andere locatie incheckte, kwam na uitchecken niet terug op zijn vaste locatie
- Nieuw: veld home_locatie — uitchecken zet de medewerker terug op de thuislocatie
- Migratie draait automatisch bij de eerste admin-login

## v2.1.3 — 2026-05-15

- Nieuw: IN/UIT-bord weergave — gesplitst scherm, status wisselen door op de naam te klikken, BHV-badges, filters op locatie en afdeling, sorteren, volledig scherm en automatisch verversen elke 30 seconden
- Per gebruiker aan te zetten via featurebeheer
- Geen databasewijzigingen

## v2.1.2 — 2026-05-06

- Nieuw: back-upmodule (database, bestanden of volledig) via admin/backup.php, met tegel op het dashboard

## v2.1.1 — 2026-04-16

- Fix: Header staat nu altijd vast bovenaan tijdens scrollen — sorteerknop altijd zichtbaar
- Fix: Locatiemenu plakt direct onder de vaste header bij scrollen
- Nieuw: Instelbare admin rechten per feature — SuperAdmin bepaalt wat een admin mag
- Nieuw: Admins kunnen per functie worden beperkt (locaties, kiosk, badges, audit log etc.)
- Fix: auth_helper.php include volgorde gecorrigeerd in alle admin pagina's

## v2.0.18 — 2026-03-26

- Fix: Foto op medewerkerkaart kan nu weer uitgezet worden via featurebeheer
- Fix: Locatie pion staat nu netjes onder de profielfoto in plaats van over de naam
- Fix: Locatie pion toont nu altijd actuele locaties inclusief nieuw toegevoegde locaties
- Fix: Medewerker reset correct naar originele locatie bij uitchecken
- Fix: Safari/iOS service worker redirect fout opgelost — niet meer onterecht uitgelogd
- Fix: Audit log werkt nu correct op alle servers
- Nieuw: Bezoekersregistratie formulier link toegevoegd aan admin dashboard

## v2.0.17 — 2026-03-25

- Fix: Locatie pion toont nu altijd actuele locaties uit de database
- Fix: Nieuwe locaties verschijnen direct in de locatiekeuze bij inchecken
- Fix: Medewerker reset correct naar originele locatie bij uitchecken
- Fix: Safari/iOS service worker redirect fout opgelost — niet meer onterecht uitgelogd
- Nieuw: Bezoekersregistratie formulier link toegevoegd aan admin dashboard
- Fix: Audit log werkt nu correct op alle servers

## v2.0.16 — 2026-03-23

- Verbetering: Filters blijven bewaard na opslaan in medewerkers- en gebruikersbeheer
- Verbetering: Filterbalk kleurt groen als er een filter actief is
- Fix: Paginas in admin beheer laden altijd vers na een wijziging — geen Ctrl+R meer nodig
- Fix: Update banner op dashboard blijft weg na wegklikken — ook na herladen
- Fix: Versienummer in footer van dashboard klopt nu altijd

## v2.0.15 — 2026-03-22

- Verbetering: PNG badge export volledig herschreven naar client-side Canvas — werkt nu op alle hosting omgevingen
- Fix: QR code en barcode staan niet meer over elkaar heen op de badge
- Fix: Logo wordt nu correct weergegeven op de badge header
- Verbetering: Tekst en profielfoto op badge zijn groter en beter leesbaar
- Fix: changelog_url blijft bewaard bij elke nieuwe update build

## v2.0.14 — 2026-03-22

- Fix: PNG badge export ZIP was leeg — tijdelijke bestanden worden nu correct opgeruimd na ZIP aanmaken

## v2.0.13 — 2026-03-22

- Bugfix: Uitgeschakelde Export als PNG knop ingeschakeld

## v2.0.12 — 2026-03-20

- Fix: Admin paginas worden niet meer gecached door de browser — geen Ctrl+R meer nodig na wijzigingen
- Nieuw: Badge export als PNG — individuele badge afbeeldingen per medewerker in ZIP bestand met QR/barcode

## v2.0.11 — 2026-03-20

- Fix: Fatale PHP fouten door dubbele functiedeclaraties in auth.php opgelost

## v2.0.10 — 2026-03-19

- Fix: HTTP 500 op users_manage en employees_manage opgelost — migraties draaien nu automatisch op alle adminpaginas

## v2.0.9 — 2026-03-19

- Fix: Fatale PHP fout in db.php opgelost waardoor de volledige site onbereikbaar was na de PWA sessie-fix

## v2.0.6 — 2026-03-18

- Gebruikersgroepen toegevoegd in gebruikersbeheer — admins kunnen gebruikers indelen in groepen per locatie

## v2.0.5 — 2026-03-16

- Fix: BHV Overzicht sluitknop toegevoegd voor kiosk mode gebruik

## v2.0.4 — 2026-03-16

- Kleine aanpassingen in update bestanden

## v2.0.3 — 2026-03-16

- Cache probleem gefixt
- Changelog bestand opnieuw aangemaakt met automatische koppeling

## v2.0.2 — 2026-03-16

- Fix: Sluitknop toegevoegd aan BHV overzicht voor kiosk modus
- Fix: BHV footer-knop gewijzigd van link naar popup-venster
- Fix: CSS footer-btn genormaliseerd voor button-elementen

## v2.0.0 — 2026-03-09

- Nieuw: Licentiesysteem met Starter, Professional en Business tiers
- Nieuw: 8-staps webinstaller met EULA-acceptatie en licentiesleutelvalidatie
- Nieuw: Automatisch updatecontrolesysteem via admin.peopledisplay.nl
- Nieuw: Bezoekersbeheer met e-mailmeldingen en token-gebaseerde in-/uitcheck
- Nieuw: Kiosk-modus met token-gebaseerde aanmelding
- Nieuw: Auditlog voor alle statuswijzigingen van medewerkers
- Nieuw: CSV import/export voor medewerkers
- Nieuw: Badge- en ID-kaartgeneratie (PDF via TCPDF)
- Nieuw: WiFi auto-inchecken op basis van IP-bereik
- Nieuw: PWA-ondersteuning — installeerbaar als app op mobiel/tablet
- Nieuw: Presentatiemodus met automatische start Google Slides
- Nieuw: Rol-gebaseerde toegang (superadmin, admin, gebruiker)
- Fix: Statusupdates blijven nu bewaard na paginanavigatie
- Fix: PHP-waarschuwingen corrumpeerden geen API JSON-antwoorden meer
- Fix: UI-kaarten bevriezen niet meer na statusupdate
- Fix: Auditlog HTTP 500 opgelost (created_at kolom)
- Fix: Redirect-loop op dashboard opgelost

## v1.0 — 2025-01-01

- Eerste release: basis medewerker IN/UIT registratie
- Beheerpaneel met meerdere locaties
- Initiele implementatie op Strato hosting

