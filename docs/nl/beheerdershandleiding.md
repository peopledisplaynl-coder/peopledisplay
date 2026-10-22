---
title: "PeopleDisplay — Beheerdershandleiding"
subtitle: "Versie 2.1.10"
lang: nl-NL
---

# Introductie

Deze handleiding is voor beheerders van PeopleDisplay: de mensen die medewerkers, gebruikers, locaties en instellingen beheren. Hoe het aanmeldscherm zelf werkt, staat in de **Gebruikershandleiding**. Het installeren en bijwerken op je eigen hosting staat in de **Installatiehandleiding**.

> **Over de voorbeelden** — De screenshots zijn gemaakt in een demo-omgeving met verzonnen namen, locaties en een Unlimited-licentie. Met een Starter-licentie zie je sommige onderdelen niet (zie [Licentie](#licentie)).

## Medewerkers en gebruikers

PeopleDisplay kent twee soorten personen. Het is handig om het verschil goed te kennen:

| | **Medewerker** | **Gebruiker** |
|---|---|---|
| Wat is het? | Iemand met een kaart op het aanmeldscherm | Een account waarmee iemand inlogt |
| Beheren via | **Medewerkers** | **Gebruikers** |
| Voorbeeld | Juf Anna, conciërge Daan | Het account van de receptie, het infoscherm in de hal, de beheerder |

Een gedeeld scherm in de hal is dus één *gebruiker* die de kaarten van veel *medewerkers* toont.

## Rollen

| Rol | Mag |
|---|---|
| **User** | Het aanmeldscherm, overzicht en eigen profiel gebruiken |
| **Medewerker Beheerder** | Daarnaast medewerkers beheren |
| **Gebruiker Beheerder** | Daarnaast medewerkers en gebruikers beheren |
| **Admin** | Het admin-dashboard gebruiken; een SuperAdmin kan per functie beperken wat een admin mag |
| **SuperAdmin** | Alles, inclusief back-ups, licentie en andere beheerders |

# Het admin-dashboard

Beheerders komen na het inloggen direct op het admin-dashboard. Vanaf het aanmeldscherm kom je er via de knop **Admin Dashboard** onderaan, of via **Beheer** op het welkomstscherm.

![Het admin-dashboard](../images/dashboard.png)

Het dashboard is verdeeld in vier groepen:

- **Navigatie** — naar het welkomstscherm, het aanmeldscherm en het overzicht.
- **Personeelsbeheer** — medewerkers, gebruikers, de badge generator en bulk acties.
- **Bezoekers & Locaties** — bezoekers, bezoekers-e-mails, het registratieformulier, locaties en afdelingen (en hun volgorde).
- **Systeem Configuratie** — online gebruikers, systeemconfiguratie, kiosk tokens, audit log, sub-status datums, licentiebeheer en back-up.

Rechtsboven zie je je rol en de melding **Migraties toegepast** of **Database schema gecontroleerd**: bij elk bezoek aan het dashboard controleert PeopleDisplay of de database bij de geïnstalleerde versie past, en past die zo nodig zelf aan. Onderaan staat de versie.

Is er een nieuwe versie beschikbaar, dan verschijnt bovenaan een updatemelding (zie [Updates](#updates)).

# Medewerkers

Via **Medewerkers** beheer je alle kaarten op het aanmeldscherm.

![Medewerkers beheren](../images/medewerkers.png)

Boven de lijst zoek en filter je op naam, locatie, afdeling, BHV en foto. **Toon inactief** laat ook medewerkers zien die zijn verwijderd. Rechts zie je hoeveel medewerkers er getoond worden. Klik op een kolomkop om te sorteren.

## Medewerker toevoegen

Klik op **Nieuwe Medewerker Toevoegen**.

![Een nieuwe medewerker toevoegen](../images/medewerker-nieuw.png)

1. Vul **voornaam**, **achternaam**, **afdeling** en **locatie** in (verplicht).
2. Vul eventueel e-mailadres, telefoon en functie in. Het e-mailadres wordt gebruikt voor bezoekersmeldingen.
3. Bij **Zichtbaar op locaties** bepaal je waar de kaart verschijnt. Met **Zichtbaar op ALLE locaties** staat de kaart overal, ongeacht de huidige locatie.
4. Vink **BHV'er** aan voor medewerkers met een BHV-taak.
5. Klik op **Opslaan**.

## Medewerker bewerken

Klik op **Bewerken** in de rij van de medewerker. Het formulier opent direct onder die rij.

![Een medewerker bewerken](../images/medewerker-bewerken.png)

Hier stel je ook de foto in en of de medewerker met de **Pion (📍)** op een andere locatie mag inchecken.

## Medewerker verwijderen en terugzetten

**Verwijderen** maakt een medewerker inactief: de kaart verdwijnt van alle schermen, maar de gegevens en de geschiedenis blijven bewaard. Vink **Toon inactief** aan en klik op **♻️ Herstellen** om iemand terug te zetten.

## Importeren en exporteren (CSV)

- **Export CSV** downloadt alle medewerkers als CSV-bestand (te openen in Excel).
- **Import CSV** voegt medewerkers in één keer toe. Het bestand moet minimaal de kolommen `voornaam` en `achternaam` hebben; de kolommen `functie`, `afdeling`, `locatie`, `bhv` (Ja/Nee), `foto_url`, `notities` en `status` worden meegenomen als ze er zijn. Kies het bestand onder de lijst en klik op **Importeren**.

> **Tip** — Exporteer eerst de bestaande medewerkers. Zo heb je meteen een voorbeeld met de juiste kolommen.

# Gebruikers

Via **Gebruikers** beheer je alle accounts.

![Gebruikers beheren](../images/gebruikers.png)

Zoek op naam, gebruikersnaam of e-mail, of filter op rol. Met **+ Nieuwe Gebruiker** maak je een account aan; met **Groepen beheren** maak je groepen om gebruikers te ordenen (bijvoorbeeld per locatie).

## Gebruiker bewerken

Klik op het potlood (✏️) achter een gebruiker. Het bewerkvenster heeft vier tabbladen.

![Account-gegevens van een gebruiker](../images/gebruiker-bewerken.png)

- **Account** — weergavenaam, e-mail, rol, groep, een nieuw wachtwoord (leeg laten om niets te wijzigen) en of het account actief is.
- **Features** — wat deze gebruiker op het aanmeldscherm ziet en mag (zie hieronder).
- **Presentatie** — een Google Slides-presentatie die automatisch start als het scherm een tijd niet wordt aangeraakt, met het aantal seconden wachttijd. Handig voor een infoscherm in de hal.
- **Scanner** — of de gebruiker badges mag scannen om medewerkers in en uit te checken.

Klik op **Opslaan** om de wijzigingen te bewaren.

## Features per gebruiker

![Features en rechten van een gebruiker](../images/gebruiker-features.png)

- **Zichtbare velden** — welke gegevens op de kaarten staan: foto, voornaam, achternaam, functie, afdeling, locatie, status, BHV en tijdstip.
- **Extra knoppen** — welke van de drie extra statusknoppen de gebruiker ziet.
- **Sorteer Toggle Knop** — toont de sorteerknop (A) waarmee de gebruiker zelf tussen voornaam en achternaam kiest.
- **Standaard sorteervolgorde** — geldt altijd, ook zonder sorteerknop: *Voornaam A→Z, status IN eerst*, *Voornaam* of *Achternaam*.
- **Zoomknop tonen** en **Standaard zoomniveau** (50–150%).
- **IN/UIT Bord weergave** — de gebruiker ziet het IN/UIT-bord in plaats van de kaarten. Geschikt voor een groot scherm.
- **Zichtbare locaties** en **Zichtbare afdelingen** — welke kaarten deze gebruiker ziet. Geen afdelingen aangevinkt betekent: alle afdelingen.

Is de rol **Admin**, dan staat hier ook een lijst met beheerfuncties (locaties, afdelingen, kiosk tokens, bezoekers, badges, bulk acties, audit log, systeemconfiguratie, sub-status datums, gebruikers, back-up). Alleen de aangevinkte functies zijn voor die admin beschikbaar.

# Locaties

Via **Locaties** beheer je de vestigingen.

![Locaties beheren](../images/locaties.png)

- **Nieuwe Locatie Toevoegen** — naam, korte code en adres.
- **Bewerken** — wijzig een locatie. Een nieuwe naam wordt automatisch bij alle medewerkers van die locatie doorgevoerd.
- **IP-configuratie en Auto Check-in** — vul het externe IP-adres van de locatie in (en eventueel een reserve-IP of IP-bereik) en zet **Auto Check-in** aan. Medewerkers die PeopleDisplay als app op hun telefoon hebben, krijgen dan voorgesteld om automatisch in te checken zodra ze op het netwerk van die locatie zijn. Met **Test IP Match** controleer je welk IP-adres je nu hebt.

## Volgorde van locaties

Via **Locatie Volgorde** bepaal je in welke volgorde de locaties in het menu en de filters staan. Sleep de locaties in de gewenste volgorde en sla op.

![De volgorde van locaties](../images/locatie-volgorde.png)

# Afdelingen

Via **Afdelingen** beheer je de afdelingen of teams. Op het aanmeldscherm kan op afdeling worden gefilterd. Een nieuwe naam wordt automatisch bij alle medewerkers doorgevoerd. Via **Afdelingen Volgorde** zet je ze in de gewenste volgorde.

![Afdelingen beheren](../images/afdelingen.png)

# Bezoekers

## Bezoekers beheren

Via **Bezoekers** zie je wie er vandaag is aangemeld, binnen is of al is vertrokken.

![Bezoekers beheren](../images/bezoekers-beheer.png)

Filter op datum, status en locatie. Met de knop in de kolom **Acties** check je een bezoeker in (✓) of uit (→).

## Het registratieformulier

Met **Registratieformulier** open je het formulier waarmee bezoekers zich zelf aanmelden (`/visitor_register.php`). Zet de link op je website of hang een QR-code met die link bij de ingang. Het formulier staat beschreven in de Gebruikershandleiding.

## E-mails voor bezoekers

Bij een aanmelding en bij het inchecken krijgt de contactpersoon een e-mail. Via **Bezoeker Emails** stel je een **fallback-adres** in (bijvoorbeeld de receptie) dat de melding krijgt als de contactpersoon geen e-mailadres heeft.

![E-mailinstellingen voor bezoekers](../images/bezoeker-emails.png)

> **Let op** — PeopleDisplay verstuurt e-mail via de mailfunctie van je hosting. Komen er geen e-mails aan, controleer dan de spammap en vraag je hostingpartij of uitgaande e-mail is toegestaan.

# Badge Generator

Met de **Badge Generator** maak je naambadges met QR-code of barcode, bijvoorbeeld voor de scanner.

![De badge generator](../images/badges.png)

1. Kies een sjabloon: **Professional**, **Colorful**, **Minimalist** of **Emergency (BHV)**.
2. Kies welke gegevens op de badge komen.
3. Selecteer de medewerkers.
4. Download de badges als **PDF** om te printen, of als **PNG**-afbeeldingen in een ZIP-bestand.

# Bulk acties

Met **Bulk Acties** zet je in één keer statussen terug.

![Bulk acties](../images/bulk-acties.png)

- **Zet Alles op OUT** — alle medewerkers op UIT en alle extra statussen gewist. Dit gebeurt ook elke avond automatisch om 23:00, als de cronjob is ingesteld (zie de Installatiehandleiding).
- **Sub-Status Reset** — wis alleen de extra status van knop 1, 2 of 3, zonder IN/UIT te wijzigen.
- **Bezoekers Reset** — zet alle bezoekers op vertrokken.

Bovenaan zie je de huidige aantallen.

# Systeemconfiguratie

## Extra knoppen en weergave

Via **Systeemconfiguratie** geef je de drie extra knoppen een naam (standaard PAUZE, THUISWERKEN en VAKANTIE). Vink **Gebruikers mogen eigen button namen instellen** aan als gebruikers die namen in hun profiel zelf mogen kiezen. **Auto-fullscreen toestaan** laat kiosk-schermen automatisch op volledig scherm openen.

![Systeemconfiguratie](../images/systeemconfiguratie.png)

## Datum en tijd bij extra knoppen

Via **Sub-Status Datum Settings** bepaal je per extra knop of PeopleDisplay vraagt **tot wanneer** de status geldt. De status wordt op dat moment automatisch teruggezet (via de cronjob). Bovenaan zie je hoeveel statussen een einddatum hebben, nog actief zijn of verlopen zijn.

![Datum en tijd per extra knop](../images/substatus-datums.png)

## Naamweergave

Via **Naam Weergave Configuratie** kies je hoe namen op de kaarten staan: volledige naam, alleen voornaam, alleen achternaam, of initiaal + achternaam.

![Naamweergave](../images/naamweergave.png)

# Kiosk tokens

Een kiosk token is een vaste inloglink voor een scherm zonder toetsenbord, zoals een tablet bij de ingang. Het scherm opent PeopleDisplay daarmee automatisch, ook na een herstart.

![Kiosk tokens beheren](../images/kiosk-tokens.png)

## Een kiosk token maken

1. Maak eerst een **gebruiker** voor het scherm, met de juiste locaties en features.
2. Klik op **Nieuwe Kiosk Token**.
3. Kies die gebruiker, geef een omschrijving (bijvoorbeeld *Tablet ontvangstbalie*), en stel eventueel een **IP-beperking** en een verloopdatum in.
4. Klik op **Kopieer URL** of **Toon QR-Code** en open die link op het scherm.

Met **Deactiveren** of **Verwijderen** werkt de link direct niet meer, bijvoorbeeld als een tablet kwijt is.

> **Tip voor een vaste kioskpc** — Start Chrome met `--kiosk` gevolgd door de token-URL. Chrome opent dan op volledig scherm, zonder adresbalk. Het BHV-overzicht heeft een eigen knop **Venster sluiten**, zodat je ook in kiosk-modus terugkomt.

# Online gebruikers

Via **Online Gebruikers** zie je wie er nu is ingelogd, op welk apparaat, met welke browser, vanaf welk IP-adres en hoe lang al.

![Online gebruikers](../images/online-gebruikers.png)

- **Online** = actief in de laatste minuut, **Idle** = langer dan 1 minuut niets gedaan, **Away** = langer dan 5 minuten.
- Met **Uitloggen** beëindig je een sessie op afstand, bijvoorbeeld bij een kwijtgeraakte tablet. De gebruiker ziet binnen enkele seconden een melding en komt op het inlogscherm. Daarna kan hij gewoon opnieuw inloggen.

# Audit log

Het **Audit Log** houdt bij wie wat heeft gewijzigd: toegevoegde, gewijzigde en verwijderde medewerkers en statuswijzigingen, met tijdstip, oude en nieuwe waarde, wie het deed en vanaf welk IP-adres.

![Het audit log, gefilterd op statuswijzigingen](../images/audit-log.png)

Filter op actie (toegevoegd, gewijzigd, status, verwijderd), periode en naam of medewerkernummer. De regels **MIGRATION** zijn de automatische databasecontroles bij het openen van het dashboard.

# Licentie

Via **Licentiebeheer** zie je welk pakket actief is, voor welk domein, hoeveel je gebruikt en welke functies beschikbaar zijn.

![Licentiebeheer](../images/licentie.png)

- **Starter** is gratis en werkt zonder licentiecode: maximaal 10 medewerkers, 1 locatie en 3 gebruikers. Bezoekersbeheer, kiosk-modus, bulk acties en het audit log zitten in de betaalde pakketten.
- **Nieuwe Licentie Activeren** — vul een licentiecode in (formaat `PDIS-…`). De licentie wordt gekoppeld aan het domein van deze installatie.
- **Licentie Deactiveren** — maakt de koppeling los, bijvoorbeeld voor een verhuizing. Daarna gelden de Starter-limieten tot je opnieuw activeert.
- **Continuity-sleutel** (Unlimited en Corporate) — met deze sleutel blijft je licentie werken na een verhuizing naar een ander domein of andere hosting, ook zonder hulp van peopledisplay.nl. Bewaar hem op een veilige plek. Je wisselt hem in via `activate_license.php` op de nieuwe omgeving.

Actuele pakketten en prijzen staan op [peopledisplay.nl](https://peopledisplay.nl).

# Back-up

Alleen een SuperAdmin kan via **Backup aanmaken** een back-up downloaden als ZIP-bestand.

![Back-up maken](../images/backup.png)

- **Database only** — alle tabellen en gegevens als `.sql`-bestand.
- **Bestanden only** — alle programmabestanden (zonder `tmp/`, `logs/` en `install/`).
- **Volledige backup** (aanbevolen) — database én bestanden, met herstelinstructies. Hiermee verhuis je PeopleDisplay naar een andere server.

Een volledige back-up van een grote installatie kan 1 tot 3 minuten duren. Sluit het venster niet voordat de download start. Hoe je een back-up terugzet, staat in de Installatiehandleiding.

# Updates

PeopleDisplay controleert zelf of er een nieuwe versie is. Is die er, dan verschijnt bovenaan het dashboard een melding met het versienummer en de belangrijkste wijzigingen.

1. Klik op **Wat is nieuw?** voor alle wijzigingen.
2. Klik op **Update downloaden**. Je krijgt een klein ZIP-bestand met alleen de gewijzigde bestanden.
3. Pak het bestand uit en upload de inhoud met FTP naar je PeopleDisplay-map. Overschrijf de bestaande bestanden. Je instellingen, foto's en gegevens blijven staan.
4. Open het admin-dashboard. Eventuele databasewijzigingen worden automatisch doorgevoerd.

Met het kruisje verberg je de melding tot de volgende versie. Open schermen en geïnstalleerde apps laden de nieuwe versie binnen enkele minuten vanzelf.

# Veelgestelde vragen

**Een medewerker staat niet op het scherm van een bepaalde gebruiker.**
Controleer bij die gebruiker de tabbladen *Features* → *Zichtbare locaties* en *Zichtbare afdelingen*, en bij de medewerker de locatie en *Zichtbaar op locaties*.

**Ik kan geen medewerkers, locaties of gebruikers meer toevoegen.**
Je zit aan de limiet van je pakket. Zie **Licentiebeheer** voor je gebruik.

**De extra statussen worden niet automatisch teruggezet.**
Dat gebeurt via cronjobs. Controleer of die zijn ingesteld (zie de Installatiehandleiding).

**Hoe zet ik het wachtwoord van een gebruiker opnieuw?**
Open de gebruiker via ✏️, vul op het tabblad *Account* een nieuw wachtwoord in en klik op **Opslaan**.

**Hoe verhuis ik PeopleDisplay naar een andere hosting?**
Maak een volledige back-up en zet die op de nieuwe hosting terug met `restore.php` (zie de Installatiehandleiding). Heb je een Unlimited- of Corporate-licentie, wissel dan daarna je continuity-sleutel in.
