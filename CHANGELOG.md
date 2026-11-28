# PeopleDisplay Changelog

> Automatisch gegenereerd — niet handmatig bewerken.

## v2.10.1 — 2026-11-28

- Verbeterd: het infoscherm gaat verder waar het de vorige keer was. Een kiosk opent het infoscherm na elke aanmelding opnieuw; voorheen begon het dan steeds bij de eerste dia, zodat de rest zelden in beeld kwam
- Nieuw: bij Infoscherm > Weergave kies je de volgorde van de dia's: op volgorde (verder waar het scherm was) of willekeurig (elke ronde anders)

## v2.10.0 — 2026-11-28

- Nieuw: tabblad 📺 Infoscherm bij Gebruikers met alle infoscherm-rechten op één plek: per onderdeel aan te vinken (Berichten, Nieuws, Weer, Agenda, Huisstijl, Schermen, Weergave, Koppelingen) en voor alle of voor gekozen locaties, met snelkeuzes Redacteur, Hoofdredacteur en Beheerder infoscherm. Werkt bij elke rol, ook bij een gewone gebruiker, zonder andere beheerrechten
- Nieuw: iedereen met infoscherm-rechten gebruikt dezelfde pagina Infoscherm en ziet alleen zijn eigen tabbladen en locaties; wat ook voor andere locaties geldt staat er met Alleen lezen bij
- Nieuw: achter elk bericht en elke bron staat op hoeveel infoschermen het te zien is, en bij het kiezen van locaties hoeveel schermen er per locatie zijn
- Nieuw: het tabblad Schermen toont de locaties van elk scherm (of Alle locaties) en een tabel met wat een scherm met één, meer of alle locaties laat zien, ook voor kiosks en multifunctionele gebouwen
- Gewijzigd: het admin-recht Infoscherm beheren is vervangen door de nieuwe rechten. Bestaande admins en redacteuren houden wat ze hadden; de pagina Infoscherm-berichten verwijst door naar de nieuwe pagina

## v2.9.0 — 2026-11-27

- Nieuw: tabblad Huisstijl bij Infoscherm. Stel voor de hele organisatie en per locatie (bijvoorbeeld per school) een logo, titel, hoofdkleur, accentkleur, achtergrondkleuren en lettertype in, met een voorbeeld en een controle of de tekst goed leesbaar blijft
- Nieuw: berichtkleuren Huisstijl en Accent: elk scherm toont het bericht in de kleuren van zijn eigen huisstijl. Nieuwe berichten krijgen standaard Huisstijl
- Nieuw: bij een bericht kies je waar de afbeelding staat (links, rechts, boven of over de hele dia), of hij de ruimte vult of helemaal zichtbaar is, en hoe groot hij is (30-70%), met een schets in het formulier. Liggende foto's hoeven niet meer afgekapt te worden
- Nieuw: tabblad Infoscherm bij Gebruikers: kies per gebruiker de rechten Berichten plaatsen en Huisstijl aanpassen, en voor welke locaties. Zo kan bijvoorbeeld een directeur de huisstijl van de eigen school instellen zonder de andere scholen te kunnen wijzigen
- Nieuw: bij Infoscherm > Schermen kies je per scherm welke huisstijl het gebruikt. Automatisch: een scherm met één locatie krijgt de huisstijl van die locatie, anders die van de organisatie
- Gewijzigd: de titel bovenaan het infoscherm staat nu bij Huisstijl in plaats van bij Weergave; de bestaande titel wordt automatisch overgenomen

## v2.8.2 — 2026-11-26

- Verbeterd: het inlogscherm toont het echte versienummer (was altijd v2.0) en werkt dat bij elke update vanzelf bij
- Nieuw: onderaan het gebruikersmenu (klik op je foto of initialen) staat het versienummer, zodat ook gebruikers zonder beheerrechten zien welke versie draait
- Verbeterd: na een update laadt het aanmeldscherm altijd de nieuwe app.js en style.css (geen oude versie meer uit de browsercache)
- Beveiliging: het oude, nergens gelinkte bestand admin/features_manage.php wordt na de update automatisch verwijderd; opslaan op die pagina wiste bijna alle instellingen van een gebruiker

## v2.8.1 — 2026-11-26

- Nieuw: bij Infoscherm > Schermen kies je per scherm of Vandaag jarig en BHV aanwezig getoond worden: Standaard (volgt Weergave), Aan of Uit. Zo kan het ene scherm de jarigen tonen en het andere niet
- Een scherm toont altijd alleen de jarigen en BHV'ers van de eigen locaties; dat staat nu ook in de uitleg bij Schermen

## v2.8.0 — 2026-11-26

- Nieuw: blok 'BHV aanwezig' in de zijbalk van het infoscherm met het aantal ingecheckte BHV'ers op de locatie(s) van het scherm, desgewenst met namen (voornaam en voorletter). Is er niemand, dan kleurt het blok rood. Aan te zetten bij Infoscherm > Weergave
- Nieuw: dia 'Vandaag jarig' en een melding in de nieuwsbalk. Alleen voor medewerkers bij wie een verjaardag (dag en maand, zonder jaar) is ingevuld bij Medewerkers; vanwege de privacy alleen met hun toestemming. Ook in de CSV-import en -export
- Nieuw: knop Voorbeeld per scherm bij Infoscherm > Schermen: zie precies wat een schermaccount toont, met de berichten, het nieuws, het weer en de BHV'ers van zijn locaties
- Verbeterd: de CSV-export werkt zonder waarschuwing op PHP 8.4

## v2.7.0 — 2026-11-25

- Nieuw: Website zonder feed bij Infoscherm > Nieuws. PeopleDisplay haalt de nieuwspagina van een website zelf op en neemt titel, korte tekst, afbeelding, datum en link over. Met 'Zoek berichten' zie je wat er gevonden wordt en kies je de juiste lijst; heeft de site toch een (verborgen) feed, zoals elke WordPress-site, dan wordt die voorgesteld
- Nieuw: voor lastige pagina's kun je de berichten zelf aanwijzen met eenvoudige CSS-selectors, met een testknop
- Nieuw: bij Gebruikers heeft een admin een eigen tabblad Rechten (met Alles aan / Alles uit) in plaats van een lijst onderaan Features
- Werkt niet voor pagina's achter een inlog, sociale media en sites die hun inhoud met JavaScript opbouwen; dat wordt bij 'Zoek berichten' gemeld

## v2.6.1 — 2026-11-25

- Verbeterd: een lang bericht op het infoscherm past nu altijd in beeld. Is de tekst te lang voor het scherm, dan wordt hij vanzelf kleiner, zodat de titel en 'Belangrijk' altijd zichtbaar blijven, ook op kleinere of staande schermen
- Nieuw: onder de tekst van een bericht staat een tekenteller met advies (met afbeelding ongeveer 250 tekens, zonder ongeveer 450), bij Beheer > Infoscherm en op de pagina Infoscherm-berichten

## v2.6.0 — 2026-11-24

- Nieuw: berichten, nieuwsbronnen, weerplaatsen, agenda's en koppelingen op het infoscherm gelden voor één, meer of alle locaties (vinkjes in plaats van één keuze). Bestaande instellingen worden automatisch overgenomen; een nieuwe locatienaam gaat automatisch mee
- Nieuw: berichtenredacteur voor het infoscherm — bijvoorbeeld receptie of conciërge plaatst en wijzigt berichten alleen voor de eigen locaties, via de knop 'Infoscherm-berichten' op het welkomstscherm. Aanzetten bij Gebruikers > Features. Berichten voor alle locaties blijven bij de beheerder en zijn voor de redacteur alleen te lezen
- Nieuw: Teams en de Berichten-API kunnen meer locaties meegeven ('locaties A, B' in Teams, "locations": [...] in de API)
- Beveiliging: elke installatie heeft een eigen geheime sleutel voor cronjobs (cron-job.org) in plaats van één gedeelde sleutel. De nieuwe pagina Beheer > Automatische taken toont alle cronjobs met de juiste links. De oude sleutel werkt na de update nog 30 dagen; het dashboard waarschuwt zolang een cronjob hem gebruikt
- Beveiliging: cron_reset_expired_sub_status.php controleert nu ook de sleutel bij een aanroep via internet; het logbestand van cron_endpoint.php staat in logs/

## v2.5.1 — 2026-11-24

- Opgelost: een agenda met veel geschiedenis (bijvoorbeeld een Google-agenda die al jaren loopt) gaf 'Bestand is te groot'. Agenda's tot 50 MB worden nu regel voor regel gelezen en oude afspraken worden daarbij direct overgeslagen, zodat het weinig geheugen kost; reeksen die al jaren lopen worden snel doorgerekend

## v2.5.0 — 2026-11-24

- Nieuw: Agenda op het infoscherm — koppel een agenda uit Outlook/Microsoft 365 of Google Agenda via een iCal-link (.ics). Afspraken staan in de zijbalk en één keer per ronde als agendadia; terugkerende, verplaatste en geannuleerde afspraken worden goed getoond, privé-afspraken niet
- Nieuw: Teams-koppeling — collega's zetten een bericht op het infoscherm door in een Teams-kanaal '@Infoscherm Titel' te typen (met 'tot vrijdag', 'belangrijk', 'lijst', 'stop 12', 'help'). Gratis, zonder Power Automate; werkt via een uitgaande webhook van Teams
- Nieuw: Berichten-API — eigen systemen en scripts plaatsen, wijzigen en verwijderen berichten met een eigen sleutel per koppeling (api/infoscreen_push.php)
- Nieuw: tabbladen Agenda en Koppelingen bij Infoscherm; bij Berichten staat of een bericht via Teams of de API kwam en van wie
- Beveiliging: van een API-sleutel wordt alleen een hash bewaard, het Teams-beveiligingstoken staat versleuteld, Teams-berichten worden op hun handtekening gecontroleerd en elke koppeling mag maximaal 120 berichten per uur plaatsen

## v2.4.0 — 2026-11-23

- Nieuw: Infoscherm — toon eigen berichten (met afbeelding, kleur, periode en locatie), nieuws uit RSS-feeds en het weer (Buienradar of Open-Meteo) op uw schermen, met datum, tijd en een nieuwsbalk. Beschikbaar in alle pakketten, ook Starter. Beheer via Dashboard → Infoscherm
- Nieuw: per scherm kiezen wat er bij inactiviteit verschijnt: Google Slides, het Infoscherm, of Google Slides met weer, mededelingen en nieuwsbalk eromheen. Een aanraking sluit het infoscherm weer
- Nieuw: een scherm dat alleen het infoscherm toont, via infoscherm.php of kiosk_login.php?token=…&page=infoscherm
- Nieuw: cronjob cron/cron_infoscreen.php (of cron_endpoint.php?action=infoscreen_refresh) om nieuws en weer elke 15 minuten bij te werken; zonder cronjob gebeurt dat zodra een scherm gegevens opvraagt
- Opgelost: de wachttijd voordat de presentatie start gebruikt nu de ingestelde seconden bij de gebruiker (stond vast op 60 seconden)
- Opgelost: de installatiewizard kon een nieuwe installatie niet afronden door commentaar in install.sql
- Beveiliging: gebruikersbeheer stuurt de wachtwoord-hash van een gebruiker niet meer naar de browser

## v2.3.1 — 2026-11-22

- Nieuw: tweestapsverificatie instellen werkt nu ook op een telefoon of tablet, waar je de QR-code niet op hetzelfde apparaat kunt scannen. Op een telefoon staat nu de knop 'Open in je authenticator-app' (voegt het account direct toe in Google Authenticator, Microsoft Authenticator, Authy of 1Password) en de knop 'Sleutel kopiëren' om de sleutel in de app te plakken. De QR-code blijft op een computer gewoon bovenaan staan en is op een telefoon met één tik alsnog te tonen
- Verbeterd: op de instelpagina van 2FA staat naast de QR-code een knop 'Sleutel kopiëren' in plaats van alleen de sleutel om over te typen

## v2.3.0 — 2026-11-21

- Nieuw: tweestapsverificatie (2FA) met een authenticator-app (Google Authenticator, Microsoft Authenticator, Authy, 1Password e.d.). Gebruikers stellen het zelf in via hun profiel of via de pagina Tweestapsverificatie: QR-code scannen, een code invullen en 10 herstelcodes bewaren. Bij het inloggen is na het wachtwoord een code uit de app nodig
- Nieuw: in Gebruikers beheren staat bij elke gebruiker het vinkje 'Tweestapsverificatie (2FA) verplicht' en met de knop '2FA verplichten' zet je dit in één keer aan voor gekozen rollen. Verplichte gebruikers krijgen 7 dagen uitstel; daarna moeten ze 2FA instellen om verder te kunnen. Kiosk- en tabletaccounts doen nooit mee
- Nieuw: 'Dit apparaat 30 dagen vertrouwen' bij het invullen van de code: op dat apparaat is dan 30 dagen geen code nodig. Dit staat los van 'Ingelogd blijven' en omzeilt 2FA niet: wie alleen 'ingelogd blijven' kiest, moet na uitloggen weer een code invullen. Vertrouwde apparaten kun je in het 2FA-scherm in één keer afmelden
- Nieuw: beheerders kunnen 2FA van een gebruiker resetten (telefoon kwijt en geen herstelcodes meer). Als noodoplossing werkt het bestand twofa_uit.txt in de hoofdmap op de server (via FTP aan te maken): zolang dat bestand er staat, is voor niemand een code nodig en toont het inlogscherm een waarschuwing
- Nieuw: wie 2FA nog moet instellen krijgt binnen het uitstel hooguit eens per 24 uur een herinnering, en direct na een verplichte wachtwoordwijziging; in Gebruikers beheren staat bij kiosk-accounts 'kiosk: geen 2FA'
- Beveiliging: de geheime sleutel van elke gebruiker staat versleuteld in de database (sleutelbestand data/twofa_key.php, dat bij de server hoort en niet in updates zit), herstelcodes worden alleen als hash bewaard en werken één keer, en een afgekeken code werkt niet nog eens. Foute codes tellen mee voor de automatische blokkade; een wachtwoordwijziging meldt alle vertrouwde apparaten af
- Fix: onder 'Wachtwoord wijzigen' op de profielpagina stond nog 'minimaal 8 karakters' terwijl het minimum 10 tekens is

## v2.2.0 — 2026-11-21

- Nieuw: verplicht een nieuw wachtwoord. In Gebruikers beheren staat nu per gebruiker het vinkje 'Gebruiker moet bij de eerstvolgende inlog een nieuw wachtwoord kiezen', en met de knop 'Wachtwoord wijzigen verplichten' zet je dit in één keer aan voor alle gebruikers van gekozen rollen (kiosk- en tabletaccounts worden overgeslagen). De gebruiker kiest zelf een nieuw wachtwoord voordat hij verder kan; wie al is ingelogd krijgt dit binnen 5 minuten
- Nieuw: automatische blokkade na te veel foute inlogpogingen (5 pogingen met dezelfde gebruikersnaam vanaf hetzelfde adres, daarna 15 minuten, bij herhaling langer tot maximaal 24 uur). Alle inlogpogingen worden 90 dagen bewaard (nieuwe tabel login_attempts)
- Nieuw: een nieuw wachtwoord moet minimaal 10 tekens zijn (was 8), overal waar een wachtwoord wordt ingesteld
- Nieuw: in Gebruikers beheren staat bij elke gebruiker wanneer die voor het laatst is ingelogd
- Beveiliging: bij het opslaan in Gebruikers beheren werd alles wat in het formulier stond, ook een nieuw wachtwoord, in leesbare tekst in een logbestand op de server gezet (users_manage_debug.log). Dat loggen is verwijderd en het oude logbestand wordt bij de update automatisch opgeruimd
- Beveiliging: bij elke inlog krijgt de sessie een nieuw ID (tegen sessie-overname); de cookies voor de sessie en voor 'ingelogd blijven' zijn alleen via HTTPS, niet leesbaar voor scripts en niet te gebruiken vanaf andere websites; een onverwachte fout bij inloggen toont geen interne foutmelding meer
- Fix: het tijdstip van de laatste inlog werd op sommige installaties niet bewaard (kolom last_login ontbrak); die wordt nu automatisch aangemaakt

## v2.1.18 — 2026-11-20

- Fix: bij een gebruiker die aan een medewerkerskaart is gekoppeld, toonde de locatiekeuze (Check-in op andere locatie) een verouderde lijst: een niet meer bestaande locatie (TEST) stond erin en een nieuwe locatie ontbrak. De lijst komt nu altijd uit de locaties in beheer

## v2.1.17 — 2026-11-20

- Beveiliging: in het BHV-overzicht (e-mail versturen) is de vaste keuze emailadres@email.com verwijderd. Voorheen ging het overzicht met namen, functies en BHV-status naar dat voorbeeldadres als je niets veranderde; nu vul je zelf een e-mailadres in

## v2.1.16 — 2026-11-18

- Beveiliging: de status van een medewerker wijzigen (aanmeldscherm en WiFi-incheck) kan alleen nog via POST vanaf de eigen website. Voorheen kon dat via een gewone link (GET), die een andere website of e-mail kon laten uitvoeren met de sessie van een ingelogde gebruiker
- Let op na het updaten: laat kiosk- en displayschermen één keer verversen (F5), zodat ze de nieuwe app.js laden

## v2.1.15 — 2026-11-18

- Beveiliging: het tijdelijke script reset_password.php (waarmee zonder inloggen elk wachtwoord kon worden gewijzigd) is uit het product gehaald en wordt bij het eerste verzoek na de update automatisch van de server verwijderd
- Beveiliging: email-proxy.php (BHV-overzicht per e-mail) vereist nu inloggen, alleen POST, een verzoek vanaf de eigen website en maximaal 10 e-mails per 10 minuten; voorheen kon iedereen via dit bestand e-mails laten versturen
- Beveiliging: de API's voor medewerkers, bezoekers, locaties, filters en knopnamen (o.a. employees_api, update_employee_status, get_employee, get_all_employees, get_todays_visitors, get_visitors_inside) geven alleen nog gegevens aan ingelogde gebruikers (gewone inlog of kiosk-inlog)
- Beveiliging: het bezoekersformulier geeft bij het kiezen van een contactpersoon geen e-mailadressen en statussen van medewerkers meer prijs
- Opgeruimd: ongebruikte en onbeveiligde bestanden verwijderd (admin/api/features_update, features, users_list, proxy, get-config en get_button_config, api/send_visitor_notification, de verouderde pagina user_permissions, mobile-status-view.js); oude hulpscripts zoals license_debug.php en db_check.php worden bij de update automatisch opgeruimd als ze nog op de server staan
- Nieuw in de tests: controle dat elke API inlog vraagt en dat er geen tijdelijke of debugscripts in het product komen

## v2.1.14 — 2026-11-13

- Fix: medewerkers exporteren\/importeren als CSV gaf ‘Unauthorized’ voor de rollen Medewerker Beheerder en Gebruiker Beheerder — werkt nu voor elke rol die de medewerkersbeheerpagina mag gebruiken (ook Admin en SuperAdmin)

## v2.1.13 — 2026-11-13

- Fix: foto uploaden/wijzigen bij een medewerker gaf ‘Geen rechten’ voor de rollen Medewerker Beheerder en Gebruiker Beheerder — werkt nu voor elke rol die de medewerkersbeheerpagina mag gebruiken (ook Admin en SuperAdmin)

## v2.1.12 — 2026-11-04

- Fix: een gekoppelde medewerkerskaart (Account → gekoppelde medewerker) werkte alleen voor rol User — werkt nu voor elke rol, ook Medewerker Beheerder, Gebruiker Beheerder, Admin en SuperAdmin
- Gekoppelde accounts landen na inloggen voortaan altijd direct op de eigen kaart in plaats van het dashboard of de locatieweergave; de Admin Dashboard-knop blijft gewoon bereikbaar

## v2.1.11 — 2026-11-04

- Nieuw: gebruikers kunnen gekoppeld worden aan hun eigen medewerkerskaart — toont bij inloggen automatisch alleen die kaart met IN/UIT-status, in plaats van de locatieweergave (werkt alleen voor rol User)
- Nieuw: foto-upload met automatische compressie bij medewerker toevoegen en bewerken
- Verbeterd: ‘Nieuwe Medewerker Toevoegen’ en het bewerkformulier in Medewerkers Beheren herontworpen naar compacte, overzichtelijke blokken (Basisgegevens, Organisatie, Foto, Zichtbaarheid, Opmerkingen)
- Verbeterd: filterbalk in Medewerkers Beheren omgezet naar compacte responsive layout
- Documentatie: gebruikers- en beheerdershandleiding (NL/EN) bijgewerkt met de nieuwe instellingen

## v2.1.10 — 2026-10-22

- Fix: install.php sloeg na het accepteren van de voorwaarden de licentiestap over en opende de databasestap met een foutmelding — elke stap wordt nu apart verwerkt
- Fix: installatie toont het juiste versienummer en aantal tabellen
- Fix: Bezoekers beheren toont weer naam, tijd en contactpersoon van bezoekers
- Fix: tijd van verwachte en binnen-zijnde bezoekers op het aanmeldscherm (stond op --:--)
- Fix: installatie-ZIP bevat de map tmp/badge_photos (stap 1 van de installatie gaf LET OP)
- Nieuw: gebruikers-, beheerders- en installatiehandleiding in het Nederlands en Engels (PDF en Word), met screenshots

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

