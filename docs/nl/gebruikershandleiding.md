---
title: "PeopleDisplay — Gebruikershandleiding"
subtitle: "Versie 2.1.10"
lang: nl-NL
---

# Introductie

PeopleDisplay is een digitaal aanmeldscherm voor medewerkers. In één oogopslag zie je wie er aanwezig is, op welke locatie, en wie er BHV'er is. Medewerkers melden zich aan en af met één tik op hun eigen kaart. PeopleDisplay werkt in de browser: op een groot scherm in de hal, op een tablet bij de ingang, op een pc of op een telefoon. Je kunt het ook als app installeren.

Deze handleiding is voor iedereen die PeopleDisplay dagelijks gebruikt. Voor het inrichten en beheren is er de **Beheerdershandleiding**; voor het installeren op je eigen hosting de **Installatiehandleiding**.

> **Over de voorbeelden** — De screenshots in deze handleiding zijn gemaakt met een demo-omgeving met verzonnen namen en locaties. Welke onderdelen jij ziet, hangt af van wat de beheerder voor jouw account heeft aangezet.

# Inloggen

Open het adres van jouw PeopleDisplay-omgeving in de browser, bijvoorbeeld `https://mijn.peopledisplay.nl`. Je komt dan op het inlogscherm.

![Het inlogscherm](../images/login.png)

1. Vul je **gebruikersnaam** en **wachtwoord** in.
2. Vink **Blijf ingelogd** aan als je op dit apparaat ingelogd wilt blijven (30 dagen). Doe dit alleen op een apparaat dat niet door anderen wordt gebruikt.
3. Klik op **Inloggen**.

Na het inloggen kom je op het welkomstscherm. Beheerders komen direct in het admin-dashboard.

> **Wachtwoord vergeten?** Vraag je beheerder om een nieuw wachtwoord in te stellen.

# Het welkomstscherm

Op het welkomstscherm kies je waar je naartoe wilt.

![Het welkomstscherm](../images/frontpage.png)

| Tegel | Wat je daar doet |
|---|---|
| **Aanmeldscherm** | Medewerkers in- en uitchecken (het hoofdscherm) |
| **Overzicht** | Een lijst van iedereen die aanwezig is, per locatie |
| **Mijn Profiel** | Je naam, e-mailadres, foto en wachtwoord aanpassen |
| **Beheer** | Alleen zichtbaar voor beheerders: het admin-dashboard |

Onderaan staat de knop **Uitloggen**.

# Het aanmeldscherm

Het aanmeldscherm is het hart van PeopleDisplay. Elke medewerker heeft een eigen kaart. Een **groene** kaart betekent aanwezig (IN), een **rode** kaart afwezig (UIT). Kaarten met een extra status (zoals pauze of vakantie) hebben de kleur van die status.

![Het aanmeldscherm](../images/aanmeldscherm.png)

Het scherm ververst zichzelf elke 30 seconden, dus wijzigingen van collega's verschijnen vanzelf. Onderaan zie je hoe laat het scherm voor het laatst is ververst.

## De bovenbalk

![De bovenbalk met tellers en knoppen](../images/header.png)

- **Tellers** — hoeveel medewerkers er IN (✓) en UIT (✗) zijn, hoeveel BHV'ers er aanwezig zijn (🚨) en hoeveel medewerkers een extra status hebben (bijvoorbeeld ☕ pauze, 🏖️ vakantie, 🏠 thuiswerken).
- **Sorteerknop (A)** — kies of de kaarten op voornaam of achternaam worden gesorteerd. Je keuze wordt op dit apparaat onthouden. De knop is alleen zichtbaar als de beheerder hem voor je heeft aangezet.
- **− en +** — maak de kaarten kleiner of groter (zoomen). Handig op een groot scherm of juist op een kleine tablet. Ook dit wordt per apparaat onthouden.
- **Overzicht** — opent de overzichtspagina.
- **Fullscreen** — toont het aanmeldscherm over het hele scherm. Druk op **Esc** om terug te gaan.
- **Je initiaal** (linksboven) — opent een menu met **Menu** (welkomstscherm), **Mijn Profiel** en **Uitloggen**.

## Locaties kiezen

Werk je met meerdere locaties? Klik op **TOON MENU**. Er verschijnt een rij knoppen met alle locaties die je mag zien.

![Het locatiemenu](../images/menu-locaties.png)

- Klik op een locatie om alleen die locatie te tonen. Klik op meer locaties om ze te combineren.
- **ALL** selecteert alle locaties, **CLEAR** wist je keuze.
- Klik op **VERBERG MENU** om de rij weer in te klappen.

## Zoeken en filteren

![De zoek- en filterbalk](../images/filters.png)

- **Zoek op naam** — typ een deel van een naam; alleen de kaarten die passen blijven staan.
- **Status** — toon bijvoorbeeld alleen wie IN of UIT is.
- **BHV** — toon alleen BHV'ers, of juist iedereen behalve de BHV'ers.
- **Locatie** en **Afdeling** — beperk het scherm tot één locatie of afdeling.

Zodra er een filter actief is, verschijnt de knop **Reset Filters** met het aantal actieve filters. Op een gedeeld scherm worden zoekopdracht en filters na **1 minuut zonder aanraking** automatisch gewist, zodat de volgende collega weer het hele scherm ziet.

# Medewerkerkaarten

![Een medewerkerkaart](../images/kaart.png)

Een kaart kan de volgende onderdelen hebben (de beheerder bepaalt welke zichtbaar zijn):

- **Foto** en **naam**
- **BHV**-label als de medewerker BHV'er is
- **Functie**, **afdeling** en **locatie**
- De knoppen **IN** en **UIT**
- Tot drie **extra knoppen**, bijvoorbeeld PAUZE, THUISWERKEN en VAKANTIE
- Een **📍-knop** (Pion) om op een andere locatie in te checken

## In- en uitchecken

Tik op **IN** als je binnenkomt en op **UIT** als je weggaat. De kaart verandert direct van kleur en de tellers bovenaan worden bijgewerkt.

## Extra statussen

Met de extra knoppen geef je aan dat je er wel bent maar even niet beschikbaar, of juist niet op locatie maar wel aan het werk. Welke knoppen er zijn en hoe ze heten, stelt de beheerder in.

Bij sommige knoppen vraagt PeopleDisplay **tot wanneer** de status geldt:

![Tot wanneer geldt de status?](../images/tot-wanneer.png)

- Kies **Vandaag 17:00**, **Morgen 23:59**, **Over 1 week**, of **Aangepast…** voor een eigen datum en tijd.
- Klik op **Bevestigen**.

De kaart toont daarna de status en de einddatum. Op het afgesproken moment zet PeopleDisplay de status automatisch terug.

![Een kaart met een extra status en einddatum](../images/kaart-substatus.png)

Tik op **IN** of **UIT** om een extra status eerder te beëindigen.

## Inchecken op een andere locatie (Pion)

Werk je vandaag op een andere locatie dan normaal? Als de beheerder dit voor jou heeft aangezet, staat er een **📍**-knop onder je foto.

![Inchecken op een andere locatie](../images/pion.png)

1. Tik op **📍**.
2. Kies de locatie waar je vandaag bent.
3. Tik op **Inchecken op deze locatie**.

Je kaart verschijnt nu bij die locatie. Bij het uitchecken ga je automatisch terug naar je vaste locatie.

# Bezoekers

Als bezoekersregistratie aan staat, zie je boven de kaarten twee blokken:

![Verwachte bezoekers en bezoekers binnen](../images/bezoekers-blok.png)

- **Verwachte Bezoekers** — wie zich vandaag heeft aangemeld maar nog niet binnen is. Tik op **Check In** als de bezoeker arriveert.
- **Bezoekers Binnen** — wie nu in het gebouw is. Tik op **Check Out** als de bezoeker vertrekt.

## Een bezoeker aanmelden

Bezoekers melden zich zelf aan via het registratieformulier (de beheerder kan de link of een QR-code bij de ingang hangen).

![Het registratieformulier voor bezoekers](../images/bezoeker-registratie.png)

1. Vul voornaam, achternaam en e-mailadres in, en eventueel telefoonnummer en bedrijf.
2. Kies de **locatie** en daarna de **contactpersoon**.
3. Kies de bezoekdatum en verwachte tijd, of vink **bezoek over meerdere dagen** aan.
4. Klik op **Registreren**.

De contactpersoon krijgt een e-mail dat de bezoeker is aangemeld.

# BHV-overzicht

Klik onderaan het aanmeldscherm op **🚨 BHV Overzicht**. Je ziet dan alle medewerkers die nu **IN** zijn, met de BHV'ers gemarkeerd. Dit overzicht is bedoeld voor ontruimingen en noodsituaties.

![Het BHV-overzicht](../images/bhv-overzicht.png)

- **Print** of **PDF** — maak een papieren of digitale lijst.
- **CSV** — download de lijst voor Excel.
- **Versturen per e-mail** — stuur het overzicht naar een gekozen of zelf ingevuld e-mailadres.
- Met de filters bovenaan beperk je de lijst tot een afdeling, locatie of alleen BHV'ers.
- Met **Venster sluiten** (rode balk) ga je terug — dat werkt ook in kiosk-modus, waar geen browserknoppen zichtbaar zijn.

# Overzicht

De overzichtspagina toont alle aanwezige medewerkers als lijst, gegroepeerd per locatie.

![De overzichtspagina](../images/overzicht.png)

Bovenaan staan het totaal, het aantal IN en het aantal BHV'ers. Je kunt zoeken en filteren zoals op het aanmeldscherm. Met **Auto-refresh** (onderaan) ververst de pagina zich elke 30 seconden; **Ververs** doet dat direct. **Terug** brengt je naar het aanmeldscherm.

# IN/UIT-bord

Het IN/UIT-bord is een eenvoudige weergave met twee kolommen: links wie er **UIT** is, rechts wie er **IN** is. Er staan alleen namen en een BHV-label op — ideaal voor een groot scherm in de hal.

![Het IN/UIT-bord](../images/inuitbord.png)

- Klik op een naam om die medewerker in of uit te checken.
- Kies bovenaan een locatie, of **Alle**.
- Met de knoppen rechtsboven sorteer je op voor- of achternaam, open je de filters, toon je alleen BHV'ers, ga je naar het overzicht, log je uit of zet je het bord op volledig scherm.
- Het bord ververst zichzelf elke 30 seconden (**Live**).

Heeft de beheerder het IN/UIT-bord voor jouw account aangezet, dan opent het aanmeldscherm voor jou altijd als IN/UIT-bord.

# Mijn profiel

Via **Mijn Profiel** (welkomstscherm of het menu onder je initiaal) pas je je eigen gegevens aan.

![De profielpagina](../images/profiel.png)

- **Profielgegevens** — weergavenaam en e-mailadres. Je rol en gebruikersnaam zie je ter informatie.
- **Profielfoto** — upload een JPG-, PNG- of GIF-bestand (maximaal 5 MB) of plak de link naar een foto.
- **Wachtwoord wijzigen** — vul je huidige wachtwoord in en twee keer het nieuwe (minimaal 8 tekens).

Als de beheerder dat toestaat, kun je hier ook je eigen namen voor de extra knoppen instellen.

# PeopleDisplay op telefoon of tablet

PeopleDisplay past zich aan het scherm aan. Op een telefoon staan de tellers, knoppen en filters onder elkaar.

![PeopleDisplay op een telefoon](../images/mobiel.png)

## Installeren als app

Je kunt PeopleDisplay installeren als app, zodat het een eigen pictogram krijgt en zonder adresbalk opent.

- **Android (Chrome)** — tik op de drie puntjes en kies **App installeren** of **Toevoegen aan startscherm**.
- **iPhone/iPad (Safari)** — tik op het deel-pictogram (vierkant met pijl) en kies **Zet op beginscherm**.
- **Windows/Mac (Chrome of Edge)** — klik op het installatiepictogram rechts in de adresbalk.

Na een update van PeopleDisplay ververst de app zichzelf binnen enkele minuten.

## Automatisch inchecken via wifi

Als de beheerder dit voor een locatie heeft ingesteld, stelt de geïnstalleerde app voor om je automatisch in te checken zodra je telefoon verbonden is met het netwerk van die locatie. Je kunt altijd ook gewoon zelf op IN of UIT tikken.

# Veelgestelde vragen

**Ik zie mijn naam niet op het scherm.**
Controleer of er een locatie- of afdelingsfilter aan staat (knop **Reset Filters**). Staat je kaart er nog steeds niet, vraag dan de beheerder of je als medewerker bent toegevoegd en op welke locatie.

**Ik zie de extra knoppen, de sorteerknop of het IN/UIT-bord niet.**
Deze functies zet de beheerder per gebruiker aan of uit.

**Het scherm laat oude informatie zien.**
Het scherm ververst elke 30 seconden. Vernieuw de pagina (F5, of op een tablet naar beneden vegen) als je direct de nieuwste stand wilt zien.

**Ik ben automatisch uitgelogd.**
Een beheerder kan sessies op afstand beëindigen, bijvoorbeeld als een apparaat kwijt is. Log gewoon opnieuw in.

**Mijn extra status staat er nog, terwijl ik terug ben.**
Tik op **IN**; daarmee vervalt de extra status direct.
