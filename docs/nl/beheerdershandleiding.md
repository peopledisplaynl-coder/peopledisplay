---
title: "PeopleDisplay — Beheerdershandleiding"
subtitle: "Versie 2.3.0"
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
- **Systeem Configuratie** — online gebruikers, systeemconfiguratie, kiosk tokens, infoscherm, audit log, sub-status datums, licentiebeheer en back-up.

Rechtsboven zie je je rol en de melding **Migraties toegepast** of **Database schema gecontroleerd**: bij elk bezoek aan het dashboard controleert PeopleDisplay of de database bij de geïnstalleerde versie past, en past die zo nodig zelf aan. Onderaan staat de versie.

Is er een nieuwe versie beschikbaar, dan verschijnt bovenaan een updatemelding (zie [Updates](#updates)).

# Medewerkers

Via **Medewerkers** beheer je alle kaarten op het aanmeldscherm.

![Medewerkers beheren](../images/medewerkers.png)

Boven de lijst zoek en filter je op naam, locatie, afdeling, BHV en foto. **Toon inactief** laat ook medewerkers zien die zijn verwijderd. Rechts zie je hoeveel medewerkers er getoond worden. Klik op een kolomkop om te sorteren.

## Medewerker toevoegen

Klik op **Nieuwe Medewerker Toevoegen**. Het formulier staat in overzichtelijke blokken: Basisgegevens, Organisatie en Foto naast elkaar, met Zichtbaarheid op locaties daaronder.

![Een nieuwe medewerker toevoegen](../images/medewerker-nieuw.png)

1. Vul bij **Basisgegevens** de **voornaam** en **achternaam** in (verplicht), en eventueel e-mailadres, telefoon, **verjaardag** en functie. De verjaardag (alleen dag en maand, bijvoorbeeld *14-03*; het jaar wordt niet bewaard) vul je alleen in als de medewerker op het infoscherm bij *Vandaag jarig* wil staan. Het e-mailadres wordt gebruikt voor bezoekersmeldingen. Vink **BHV'er** aan voor medewerkers met een BHV-taak.
2. Kies bij **Organisatie** de **afdeling** en **locatie** (verplicht).
3. Voeg bij **Foto** een foto toe met **Foto kiezen** — PeopleDisplay comprimeert de foto automatisch — of plak een foto-URL.
4. Bepaal bij **Zichtbaarheid op locaties** waar de kaart verschijnt. Met **Zichtbaar op ALLE locaties** staat de kaart overal, ongeacht de huidige locatie; vink daarbij ook **Mag handmatig locatie kiezen bij check-in** aan als deze medewerker zelf met de Pion (📍) op een andere locatie moet kunnen inchecken.
5. Klik op **Opslaan**.

## Medewerker bewerken

Klik op **Bewerken** in de rij van de medewerker. Het formulier opent direct onder die rij, in dezelfde blokken als bij het toevoegen.

![Een medewerker bewerken](../images/medewerker-bewerken.png)

Hier pas je ook de foto aan — upload een nieuwe (automatisch gecomprimeerd) of plak een foto-URL — en stel je in of de medewerker met de **Pion (📍)** op een andere locatie mag inchecken: vink bij **Zichtbaarheid op locaties** eerst **Zichtbaar op ALLE locaties** aan, en daarna **Mag handmatig locatie kiezen bij check-in**.

## Medewerker verwijderen en terugzetten

**Verwijderen** maakt een medewerker inactief: de kaart verdwijnt van alle schermen, maar de gegevens en de geschiedenis blijven bewaard. Vink **Toon inactief** aan en klik op **♻️ Herstellen** om iemand terug te zetten.

## Importeren en exporteren (CSV)

- **Export CSV** downloadt alle medewerkers als CSV-bestand (te openen in Excel).
- **Import CSV** voegt medewerkers in één keer toe. Het bestand moet minimaal de kolommen `voornaam` en `achternaam` hebben; de kolommen `functie`, `afdeling`, `locatie`, `bhv` (Ja/Nee), `foto_url`, `notities`, `status` en `verjaardag` (dag-maand) worden meegenomen als ze er zijn. Kies het bestand onder de lijst en klik op **Importeren**.

> **Tip** — Exporteer eerst de bestaande medewerkers. Zo heb je meteen een voorbeeld met de juiste kolommen.

# Gebruikers

Via **Gebruikers** beheer je alle accounts.

![Gebruikers beheren](../images/gebruikers.png)

Zoek op naam, gebruikersnaam of e-mail, of filter op rol. Met **+ Nieuwe Gebruiker** maak je een account aan; met **Groepen beheren** maak je groepen om gebruikers te ordenen (bijvoorbeeld per locatie).

## Gebruiker bewerken

Klik op het potlood (✏️) achter een gebruiker. Het bewerkvenster heeft vier tabbladen.

![Account-gegevens van een gebruiker](../images/gebruiker-bewerken.png)

- **Account** — weergavenaam, e-mail, rol, groep, **gekoppelde medewerker** (optioneel: koppelt dit account aan één medewerkerskaart, zodat de gebruiker bij het inloggen meteen die eigen kaart ziet in plaats van de locatieweergave of het admin-dashboard — werkt voor elke rol), een nieuw wachtwoord (leeg laten om niets te wijzigen; minimaal 10 tekens), het vinkje **Gebruiker moet bij de eerstvolgende inlog een nieuw wachtwoord kiezen** en of het account actief is. Hier staat ook het vinkje **Tweestapsverificatie (2FA) verplicht voor deze gebruiker**, en (als de gebruiker 2FA heeft) de knop **🔄 2FA resetten (telefoon kwijt)**; zie *Tweestapsverificatie (2FA)* hieronder.
- **Features** — wat deze gebruiker op het aanmeldscherm ziet en mag (zie hieronder).
- **Presentatie** — wat het scherm toont als het een tijd niet wordt aangeraakt: een Google Slides-presentatie, het [Infoscherm](#infoscherm) of beide, met het aantal seconden wachttijd. Handig voor een scherm in de hal.
- **Scanner** — of de gebruiker badges mag scannen om medewerkers in en uit te checken.

Klik op **Opslaan** om de wijzigingen te bewaren.

## Wachtwoorden en inloggen

- **Verplicht nieuw wachtwoord.** Zet je bij een gebruiker het vinkje *Gebruiker moet bij de eerstvolgende inlog een nieuw wachtwoord kiezen* aan, dan kan die persoon pas verder nadat hij of zij zelf een nieuw wachtwoord heeft gekozen. Het vinkje staat standaard aan als je een wachtwoord instelt of een gebruiker aanmaakt, zodat alleen de gebruiker zelf het wachtwoord kent. In het overzicht zie je welke gebruikers nog moeten wijzigen (🔑 *nieuw ww*) en wanneer iemand voor het laatst is ingelogd.
- **Iedereen tegelijk.** Met de knop **Wachtwoord wijzigen verplichten** bovenin kies je rollen en zet je de verplichting voor alle actieve accounts met die rol aan (of op). Kiosk- en tabletaccounts, dus accounts met een kiosk-token, worden overgeslagen. Wie al is ingelogd, wordt binnen 5 minuten naar de wijzigpagina gestuurd.
- **Wachtwoordbeleid.** Een nieuw wachtwoord bestaat uit minimaal 10 tekens. Een zin van drie of vier woorden is sterk en makkelijk te onthouden.
- **Te veel foute pogingen.** Na 5 foute pogingen met dezelfde gebruikersnaam vanaf hetzelfde adres kan er 15 minuten niet worden ingelogd; bij herhaling duurt het langer (maximaal 24 uur). Ook een goed wachtwoord werkt dan niet tot de wachttijd voorbij is. Andere gebruikers op hetzelfde adres kunnen gewoon inloggen. Alle pogingen worden 90 dagen bewaard (tabel `login_attempts`). Foute codes van tweestapsverificatie tellen daar ook in mee.

## Tweestapsverificatie (2FA)

Met tweestapsverificatie vult een gebruiker bij het inloggen, naast het wachtwoord, een code van 6 cijfers in uit een authenticator-app op de telefoon (Google Authenticator, Microsoft Authenticator, Authy, 1Password en dergelijke). Een gestolen of afgekeken wachtwoord is dan niet genoeg om in te loggen. Gebruikers stellen het zelf in via **Mijn Profiel** → **Tweestapsverificatie** → **Instellen** (zie de Gebruikershandleiding). Dat kost ongeveer twee minuten en er is geen extra abonnement of dienst voor nodig: de QR-code wordt op je eigen server gemaakt. Gebruikers die PeopleDisplay alleen op hun telefoon hebben, kunnen het ook op die telefoon instellen: daar staat in plaats van de QR-code een knop **Open in je authenticator-app** en een knop **Sleutel kopiëren** (zie de Gebruikershandleiding). Noem dit in je aankondiging, zodat niemand denkt dat een computer nodig is.

- **Aanbevolen of verplicht.** Iedere gebruiker kan 2FA zelf aanzetten. Wil je het afdwingen, dan zet je het verplicht, per gebruiker of voor hele rollen (zie hieronder). Wij raden aan 2FA minimaal verplicht te stellen voor alle beheerdersrollen.
- **Per gebruiker verplichten.** Open de gebruiker via ✏️ en vink op het tabblad *Account* **Tweestapsverificatie (2FA) verplicht voor deze gebruiker** aan.
- **Rollen tegelijk verplichten.** Met de knop **🔐 2FA verplichten** bovenin kies je rollen en klik je op **✓ Verplichten** (of **Verplichting opheffen**). Let op: alle rollen staan standaard aangevinkt. Wil je alleen beheerders verplichten, haal dan het vinkje bij **User** weg. Jijzelf valt er ook onder als jouw rol is aangevinkt. Alleen actieve accounts worden meegenomen.
- **7 dagen uitstel.** Wie 2FA verplicht krijgt maar het nog niet heeft, mag het 7 dagen uitstellen. Bij het inloggen krijgt die persoon dan het instelscherm te zien, met de knop **Later instellen** en het aantal resterende dagen (hooguit eens per 24 uur per sessie). Na die 7 dagen komt de gebruiker pas verder als 2FA is ingesteld. Het uitstel begint op het moment dat je het verplicht maakt en wordt niet verlengd als je het later nog eens aanzet. Ook wie *Blijf ingelogd* heeft gekozen, moet na die 7 dagen 2FA instellen.
- **Kiosk- en tabletaccounts doen nooit mee.** Een account met een kiosk-token wordt nooit om 2FA gevraagd, ook niet als het vinkje aan staat; dat scherm heeft immers geen telefoon. In de lijst staat bij zo'n account **📺 kiosk: geen 2FA**.
- **Status in de lijst.** Een badge **🔐 2FA aan** laat zien wie het heeft ingesteld; **🔐 2FA verplicht** betekent verplicht maar nog niet ingesteld (met de einddatum van het uitstel als je er met de muis op staat).
- **Telefoon kwijt of nieuwe telefoon.** Een gebruiker die zijn telefoon kwijt is, logt in met een van de 10 **herstelcodes** die hij bij het instellen heeft gekregen. Elke herstelcode werkt één keer. Zijn de herstelcodes ook weg, dan reset jij 2FA: open de gebruiker via ✏️ en klik op **🔄 2FA resetten (telefoon kwijt)**. De gebruiker kan daarna weer met alleen het wachtwoord inloggen en stelt 2FA opnieuw in (als het verplicht is, met weer 7 dagen uitstel). Een gewone beheerder kan dat niet voor een SuperAdmin doen; dat kan alleen een SuperAdmin.
- **Je zelf buitensluiten.** Kom je zelf niet meer binnen en kan geen andere beheerder je 2FA resetten, maak dan via FTP in de hoofdmap van de site een bestand `twofa_uit.txt` (leeg is goed). Zolang dat bestand er staat is voor niemand een code nodig en toont het inlogscherm een oranje waarschuwing. Log in met je wachtwoord, reset waar nodig 2FA en **verwijder het bestand weer**.
- **Dit apparaat vertrouwen.** Bij het invullen van de code kan een gebruiker *Dit apparaat 30 dagen vertrouwen* aanvinken; op dat apparaat is dan 30 dagen geen code nodig. *Blijf ingelogd* is iets anders en omzeilt 2FA niet: wie 2FA heeft, moet na uitloggen (of als de sessie verloopt) opnieuw een code invullen, tenzij het apparaat vertrouwd is. Een gebruiker kan alle vertrouwde apparaten zelf afmelden; ook een wachtwoordwijziging meldt ze allemaal af.
- **Foute codes.** Een foute code telt mee voor de automatische blokkade (5 pogingen, daarna 15 minuten en langer). Daarnaast geldt een grens van 10 foute codes per 15 minuten per gebruiker, vanaf welk adres dan ook.
- **De klok van de telefoon moet kloppen.** Een code werkt alleen als de tijd op de telefoon klopt (automatische tijd aan). Loopt de klok af, dan krijgt de gebruiker bij het instellen een melding.

> **Belangrijk voor de beheerder (back-up en verhuizen)** — De geheime sleutels van de authenticator-apps staan versleuteld in de database. De sleutel waarmee dat gebeurt staat in het bestand `data/twofa_key.php` op de server. Dit bestand wordt automatisch aangemaakt, zit nooit in updates en is niet bereikbaar via de browser. Een **Volledige backup** neemt de map `data/` vanzelf mee (bewaar dat ZIP-bestand dus veilig: de sleutel zit erin) en `restore.php` zet hem bij het verhuizen weer terug. Back-up of verhuis je handmatig via FTP, kopieer dan de map `data/` zelf mee, samen met de database. Raakt het bestand kwijt, dan werkt 2FA voor niemand meer tot je 2FA per gebruiker reset (of tijdelijk `twofa_uit.txt` zet).

## Features per gebruiker

![Features en rechten van een gebruiker](../images/gebruiker-features.png)

- **Zichtbare velden** — welke gegevens op de kaarten staan: foto, voornaam, achternaam, functie, afdeling, locatie, status, BHV en tijdstip.
- **Extra knoppen** — welke van de drie extra statusknoppen de gebruiker ziet.
- **Sorteer Toggle Knop** — toont de sorteerknop (A) waarmee de gebruiker zelf tussen voornaam en achternaam kiest.
- **Standaard sorteervolgorde** — geldt altijd, ook zonder sorteerknop: *Voornaam A→Z, status IN eerst*, *Voornaam* of *Achternaam*.
- **Zoomknop tonen** en **Standaard zoomniveau** (50–150%).
- **IN/UIT Bord weergave** — de gebruiker ziet het IN/UIT-bord in plaats van de kaarten. Geschikt voor een groot scherm.
- **Zichtbare locaties** en **Zichtbare afdelingen** — welke kaarten deze gebruiker ziet. Geen afdelingen aangevinkt betekent: alle afdelingen.

## Rechten van een admin

Heeft de gebruiker de rol **Admin**, dan staat er een extra tabblad **🔐 Rechten**. Daar vink je aan welke beheerfuncties deze admin mag gebruiken: locaties, afdelingen, kiosk tokens, bezoekers, badges, bulk acties, audit log, systeemconfiguratie, sub-status datums, gebruikers en back-up. De rechten voor het infoscherm staan op het tabblad **📺 Infoscherm** (zie [Rechten voor het infoscherm](#rechten-voor-het-infoscherm)). Met **Alles aan** en **Alles uit** zet je ze in één keer. Een SuperAdmin mag altijd alles. Het tabblad verschijnt pas nadat je de rol Admin hebt opgeslagen.

![Het tabblad Rechten van een admin](../images/gebruiker-rechten.png)

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

# Infoscherm

Het infoscherm toont **eigen berichten, nieuws, het weer en de agenda** op een scherm, met datum en tijd. Collega's kunnen ook vanuit **Teams** een bericht plaatsen. Je gebruikt het in plaats van de Google Slides-presentatie, of samen ermee. Het is beschikbaar in alle pakketten, ook in Starter. Je beheert het via **Infoscherm** op het dashboard (groep *Systeem Configuratie*); wie dat mag en voor welke locaties, lees je bij [Rechten voor het infoscherm](#rechten-voor-het-infoscherm).

![Het infoscherm met een eigen bericht, het weer in de zijbalk en de nieuwsbalk](../images/infoscherm-scherm.png)

Het scherm bestaat uit:

- **Dia's in het midden** — je eigen berichten, nieuws met een afbeelding als grote dia, en één keer per ronde een weerdia met de verwachting voor vijf dagen. Heb je meer nieuwsbronnen, dan komen ze om de beurt aan bod, en elke ronde toont de volgende berichten.
- **De zijbalk** — het weer van nu en van de komende dagen, en de agenda als die gekoppeld is. Zonder weerplaats staat hier een grote klok.
- **De nieuwsbalk onderaan** — alle nieuwskoppen lopen voorbij, met de naam van de bron. Belangrijke berichten staan er ook in.

## Berichten

![Berichten op het infoscherm beheren](../images/infoscherm-berichten.png)

Klik op **Nieuw bericht** en vul in:

- **Titel** en (korte) **tekst**. Een bericht met alleen een afbeelding en geen tekst toont de afbeelding groot, bijvoorbeeld een poster. Onder de tekst zie je hoeveel tekens je hebt gebruikt en wat het advies is (met afbeelding ongeveer 250, zonder ongeveer 450). Past een bericht niet op het scherm, dan maakt het scherm de tekst vanzelf kleiner, zodat titel en *Belangrijk* altijd in beeld blijven.
- **Afbeelding** (JPG, PNG, GIF of WEBP, maximaal 5 MB), met de **plaats** (links, rechts, boven of over de hele dia met de tekst eroverheen), of hij de ruimte moet **vullen** of **helemaal** zichtbaar moet zijn, en de **grootte** (30 tot 70% van de dia). Een liggende foto die aan de zijkanten wordt afgekapt, zet je *Boven* of op *Helemaal tonen*. Rechts in het formulier zie je meteen een kleine schets.
- **Achtergrondkleur** — een vaste kleur, of **Huisstijl** of **Accent**: dan toont elk scherm het bericht in de kleuren van zijn eigen [huisstijl](#huisstijl). Nieuwe berichten krijgen standaard *Huisstijl*.
- **Tonen vanaf** en **Tonen tot en met** (leeg = direct en blijvend) en de **Tijd in beeld**.
- **Locaties** — vink **🌐 Alle locaties** aan voor een bericht dat overal moet staan, of vink één of meer locaties aan. Een scherm toont de berichten van de locaties die bij zijn gebruikersaccount zijn ingesteld.
- **Belangrijk** — het bericht krijgt een rode markering, komt vaker in beeld en staat ook in de nieuwsbalk.

![Een nieuw bericht voor twee locaties](../images/infoscherm-nieuw-bericht.png)

![Plaats, vulling en grootte van de afbeelding, met een schets](../images/infoscherm-afbeelding.png)

In de lijst zie je per bericht voor welke locaties het is en of het **Op het scherm** staat, **Gepland** of **Verlopen** is, of **Uit** staat. Met **Uitzetten** haal je een bericht weg zonder het te verwijderen.

Achter elk bericht en elke bron staat **📺** met het aantal infoschermen dat het toont; wijs ernaar om de namen te zien. Bij het kiezen van locaties staat dat aantal per locatie. Ook nieuwsbronnen, weerplaatsen, agenda's en koppelingen kun je aan één, meer of alle locaties koppelen. Wijzig je de naam van een locatie, dan gaan berichten, bronnen en koppelingen automatisch mee.

## Rechten voor het infoscherm

Wie wat mag op het infoscherm, stel je per gebruiker in op één plek: bij **Gebruikers** › ✏️ › tabblad **📺 Infoscherm**. Dat kan bij elke rol, ook bij een gewone *User*. Zo kan bijvoorbeeld de directeur van één school de berichten, het nieuws en de huisstijl van die school beheren, zonder bij de andere scholen te kunnen en zonder andere beheerrechten.

![Rechten voor het infoscherm per gebruiker](../images/gebruiker-infoscherm.png)

1. **Voor welke locaties?** Kies *Alleen de aangevinkte locaties* en vink ze aan, of *🌐 Alle locaties*. Deze locaties staan los van de *Zichtbare locaties* op het tabblad Features.
2. **Wat mag deze gebruiker?** Vink de onderdelen aan, of gebruik een snelkeuze en pas die zo nodig aan:

| Onderdeel | Wat het inhoudt |
|---|---|
| 📝 Berichten | berichten plaatsen, aanpassen, uitzetten en verwijderen |
| 📰 Nieuws | nieuwsbronnen en websites koppelen |
| 🌤️ Weer | een weerplaats kiezen |
| 📅 Agenda | agenda's koppelen |
| 🖌️ Huisstijl | logo, titel, kleuren en lettertype |
| 🖥️ Schermen | per scherm de weergave, huisstijl, jarigen en BHV kiezen, met voorbeeld; de locaties van een scherm blijven bij de beheerder |
| 🎨 Weergave | instellingen voor alle schermen (alleen bij alle locaties) |
| 🔗 Koppelingen | Teams en de Berichten-API (alleen bij alle locaties) |

| Snelkeuze | Rechten |
|---|---|
| **Redacteur** | alleen berichten, voor de eigen locaties |
| **Hoofdredacteur** | alles voor de eigen locaties (berichten, nieuws, weer, agenda, huisstijl, schermen) |
| **Beheerder infoscherm** | alles, voor alle locaties |

Onderaan staat in één zin wat de gebruiker mag en voor welke locaties. Klik op **Opslaan**.

De gebruiker krijgt op het welkomstscherm de knop **📝 Infoscherm** (een admin ook de tegel op het dashboard) en ziet alleen de tabbladen waarvoor hij of zij rechten heeft. Bovenaan staat voor welke locaties. Alles wat ook voor andere locaties of voor alle locaties geldt, staat er met **🔒 Alleen lezen** bij. Een SuperAdmin mag altijd alles.

![Het infoscherm voor een hoofdredacteur van twee locaties](../images/infoscherm-redacteur.png)

Van vóór versie 2.10.0: een admin met het oude recht *Infoscherm beheren* heeft nu *Beheerder infoscherm, alle locaties*, en een redacteur houdt zijn rechten en locaties. Dat zie je op het tabblad; het wordt vastgelegd zodra je de gebruiker opslaat.

## Nieuws

Op het tabblad **Nieuws** voeg je RSS- of Atom-feeds toe: een naam, het webadres van de feed, de locaties, het aantal berichten en of afbeeldingen getoond worden. Na het opslaan wordt de feed meteen opgehaald, zodat je direct ziet of hij werkt. Handig zijn feeds van je eigen organisatie: een WordPress-website heeft er standaard een op `https://jouwwebsite.nl/feed/`, ook als die nergens op de site staat.

### Een website zonder RSS-feed

Heeft een website geen feed, klik dan op **➕ Website zonder feed**. PeopleDisplay haalt dan de nieuwspagina zelf op en neemt van elk bericht de titel, een korte tekst, de afbeelding, de datum en de link over.

1. Vul een naam in en het webadres van de **overzichtspagina** met alle berichten, bijvoorbeeld de pagina *Nieuws* of *Actueel*. Gebruik niet het adres van één los bericht.
2. Klik op **🔍 Zoek berichten**. Je ziet wat PeopleDisplay op de pagina vindt:
   - **📡 Feed gevonden (aanbevolen)** — de site heeft toch een feed. Die is het betrouwbaarst.
   - **📋 Lijst 1, Lijst 2 …** — lijsten met berichten op de pagina, met de eerste titels erbij. Kies de lijst met het nieuws.
3. Kies de locaties en klik op **Opslaan en ophalen**.

![Een website zonder feed toevoegen: PeopleDisplay vindt een lijst met berichten](../images/infoscherm-website.png)

Vindt PeopleDisplay niet de goede berichten? Open dan **Geavanceerd: zelf aanwijzen met CSS-selectors** en vul in wat één bericht is (bijvoorbeeld `.nieuws-item` of `ul.news > li`). Titel, link, afbeelding en datum zoekt PeopleDisplay daarbinnen zelf; je kunt ze ook aanwijzen. Klik op **Testen met deze selectors** om het resultaat te zien. Wie de selectors van een pagina niet kent, kan ze vinden met *Inspecteren* in de browser (rechtsklik op een bericht), of de maker van de website vragen.

Dit werkt niet voor pagina's achter een inlog (zoals Social Schools of Parro), voor Facebook, Instagram, X en LinkedIn, en voor sites die hun berichten pas met JavaScript in de browser opbouwen. Bij dat laatste meldt **Zoek berichten** dat de pagina met JavaScript is opgebouwd. Verandert een website later van opbouw, dan kiest PeopleDisplay zelf de best passende lijst; lukt ook dat niet, dan zie je bij de bron een foutmelding en kies je opnieuw.

> **Let op de voorwaarden van een nieuwsbron** — Veel nieuwssites staan hun feed alleen toe voor persoonlijk, niet-commercieel gebruik, soms alleen de koppen en altijd met bronvermelding. Het infoscherm toont bij elk bericht de bron. Controleer de voorwaarden voordat je een bron toevoegt en zet zo nodig **Afbeeldingen tonen** uit. Afbeeldingen worden nooit naar je eigen server gekopieerd.

## Weer

Op het tabblad **Weer** voeg je een plaats toe. Typ de naam en klik op **Zoek plaats**; de coördinaten worden dan ingevuld. Kies daarna de weerbron:

- **Buienradar** — actuele metingen van het dichtstbijzijnde KNMI-meetstation, de verwachting voor vijf dagen en het weerbericht. Alleen voor Nederland.
- **Open-Meteo** — werkt voor elke plaats, zonder weerbericht.

Beide zijn gratis voor niet-commercieel gebruik, met bronvermelding (die staat altijd op het scherm). Je kunt één plaats voor alle schermen instellen, of per locatie (of groep locaties) een eigen plaats.

## Agenda

Op het tabblad **Agenda** koppel je een agenda via een iCal-link (.ics). De afspraken van vandaag en de komende dagen staan dan in de zijbalk, en één keer per ronde verschijnt een agendadia. Terugkerende afspraken, verplaatste en geannuleerde afspraken worden goed getoond; **privé-afspraken niet**.

- **Outlook / Microsoft 365** — Outlook op het web → **Instellingen** → **Agenda** → **Gedeelde agenda's** → *Een agenda publiceren*. Kies de agenda en *Kan titels en locaties bekijken*, klik op **Publiceren** en kopieer de **ICS**-link.
- **Google Agenda** — **Instellingen** → kies de agenda → *Geheim adres in iCal-indeling* → kopieer.

Vul een naam en de link in en klik op **Opslaan en ophalen**: je ziet meteen hoeveel afspraken er gevonden zijn. Iedereen met de link kan de agenda lezen; deel hem dus niet verder. Hoeveel dagen vooruit het scherm toont (standaard 7), stel je in bij **Weergave**. Ook een agenda kun je aan één of meer locaties koppelen.

## Koppelingen: Teams en andere systemen

![Koppelingen voor Teams en de Berichten-API](../images/infoscherm-koppelingen.png)

Met een koppeling zetten collega's of andere systemen zelf een bericht op het infoscherm. Zo'n bericht krijgt de standaardlocaties, -kleur en -looptijd van de koppeling (standaard 7 dagen), en staat gewoon bij **Berichten**, met *Teams* of *API* en de naam van de afzender erbij. Je kunt het daar aanpassen, uitzetten of verwijderen. Per koppeling kunnen maximaal 120 berichten per uur worden geplaatst.

**Teams (gratis, zonder extra licentie)**

1. Klik op **➕ Teams-koppeling**, laat de naam *Infoscherm* staan (of kies een andere korte naam) en klik op **Aanmaken**.
2. Open in Teams het team → **⋯** → **Team beheren** → **Apps** → **Een uitgaande webhook maken**. Vul dezelfde naam in en de **callback-URL** die PeopleDisplay toont, en klik op **Maken**. Staat de knop er niet, dan moet de beheerder van Microsoft 365 "aangepaste apps" toestaan.
3. Teams toont een **beveiligingstoken**. Plak dat in PeopleDisplay en klik op **Token opslaan**.

Daarna typt iemand in een kanaal van dat team bijvoorbeeld:

```
@Infoscherm Schoolreis groep 6
Vertrek 8:15 bij de bus.
tot vrijdag
belangrijk
```

De eerste regel wordt de titel, de rest de tekst. `tot …` (bijvoorbeeld *tot vrijdag*, *tot 12-12*, *tot morgen 16:00*) is de einddatum, `belangrijk` maakt het een belangrijk bericht, en `kleur rood` of `locatie Hoofdgebouw` (of `locaties Hoofdgebouw, BSO De Vlinder`) mag ook. Teams antwoordt meteen met het nummer van het bericht. Met `@Infoscherm lijst` zie je wat er nu staat, met `@Infoscherm stop 12` haal je bericht 12 weg en `@Infoscherm help` toont de uitleg. Het werkt in openbare kanalen van dat team, niet in chats of privékanalen; afbeeldingen uit Teams worden niet meegenomen.

**Berichten-API (voor eigen systemen en scripts)**

Klik op **➕ API-koppeling**. Je krijgt één keer een sleutel te zien: kopieer en bewaar hem. Een systeem stuurt dan een POST naar `/api/infoscreen_push.php` met de kop `Authorization: Bearer <sleutel>` en JSON met minimaal een `title`, en eventueel `"locations": ["Hoofdgebouw"]`. Op het tabblad staat een volledig voorbeeld, ook voor PowerShell. Met hetzelfde `external_id` werk je een bericht bij, met `"action": "delete"` haal je het weg. Power Automate kan dit ook, maar de HTTP-actie daarvan vraagt een Premium-licentie.

## Weergave

Op het tabblad **Weergave** stel je de kleurstelling in (donker of licht; geldt als er geen eigen kleuren zijn bij [Huisstijl](#huisstijl)), de tijd per dia, de **volgorde van de dia's**, het aantal nieuwsdia's per ronde, de snelheid van de nieuwsbalk, hoeveel dagen vooruit de agenda gaat en welke onderdelen zichtbaar zijn (zijbalk, nieuwsbalk, nieuwsdia's, weerdia, agenda in de zijbalk, agendadia). Met **Voorbeeld** bekijk je het resultaat in een nieuw venster. Open schermen nemen wijzigingen binnen 5 minuten over.

Bij **Volgorde van de dia's** kies je *Op volgorde*: het scherm gaat verder waar het de vorige keer was, ook als een kiosk het infoscherm telkens opnieuw opent (na elke aanmelding). Zo komen alle dia's aan bod en niet steeds alleen de eerste. Met *Willekeurig* is elke ronde in een andere volgorde.

Onder **BHV en verjaardagen** staan twee extra onderdelen. Beide staan standaard uit. Wat je hier kiest, is de standaard voor alle schermen; per scherm zet je ze apart aan of uit bij [Schermen](#schermen).

- **BHV aanwezig** — een blok in de zijbalk met het aantal BHV'ers dat nu is ingecheckt op de locatie(s) van het scherm, met (als je dat aanvinkt) hun voornaam en de eerste letter van de achternaam. Het telt medewerkers met BHV = Ja en status IN. Wie een extra status heeft (bijvoorbeeld Pauze, Thuiswerken of Vakantie), telt niet mee: het scherm toont liever te weinig dan te veel. Is er niemand, dan kleurt het blok rood met *Geen BHV'er ingecheckt*. Een scherm met dit blok ververst elke 2 minuten. Zonder zijbalk komt de melding in de nieuwsbalk. Het blok is een hulpmiddel en vervangt het BHV-overzicht niet.
- **Vandaag jarig** — één keer per ronde een feestelijke dia met de namen van de jarigen, en een melding in de nieuwsbalk (ook in *Slides + infoscherm*). Alleen medewerkers bij wie een verjaardag is ingevuld, doen mee. Vanwege de privacy vul je die alleen in met toestemming van de medewerker; leeg maken haalt iemand er weer af. Op het scherm staan alleen de jarigen van de locatie(s) van dat scherm (de vaste locatie van de medewerker).

![Vandaag jarig op het infoscherm](../images/infoscherm-jarig.png)

## Huisstijl

Op het tabblad **🖌️ Huisstijl** zet je het infoscherm in de huisstijl van de organisatie, en eventueel per locatie, bijvoorbeeld per school binnen een stichting.

![De huisstijl van een locatie, met een voorbeeld en de leesbaarheidscontrole](../images/infoscherm-huisstijl.png)

1. Kies bovenaan **🌐 Hele organisatie** of een locatie. Een ✓ betekent: deze heeft een eigen huisstijl.
2. **Titel bovenaan** — bijvoorbeeld *Welkom op OBS De Linde*.
3. **Logo** — bij voorkeur een PNG met een doorzichtige achtergrond, maximaal 2 MB (een SVG eerst opslaan als PNG). Kies bij **Bovenbalk** *Logo en titel*, *Alleen logo* of *Alleen titel*. Met **Logo op een wit vlak** blijft een donker logo zichtbaar op een donkere achtergrond.
4. **Lettertype** — een van de standaardlettertypes (Segoe UI, Arial, Calibri, Verdana, Tahoma, Trebuchet MS of Georgia). Er wordt niets van internet geladen; op een Chromebook of Android-scherm verschijnt een gelijkend lettertype.
5. **Eigen kleuren gebruiken** — de **hoofdkleur** (berichten in de huisstijl, de nieuwsbalk en de kopjes), de **accentkleur** (een tweede berichtkleur, bijvoorbeeld voor belangrijke berichten) en twee **achtergrondkleuren** (een kleurverloop; twee keer dezelfde kleur is effen). Bij een lichte achtergrond wordt de tekst vanzelf donker, bij een donkere achtergrond wit.
6. Onder de kleuren staat de **leesbaarheidscontrole**: ✓ goed leesbaar, ⚠ matig of ✗ slecht leesbaar. Rechts zie je meteen een **voorbeeld**. Klik op **Opslaan**, en met **Bekijk op het scherm** zie je het echte scherm van die locatie.

Lege velden van een locatie volgen de organisatie: zonder eigen titel of logo staan die van de organisatie erop, en zonder *Eigen kleuren* de kleuren van de organisatie. Met **↺ Volg de organisatie** verwijder je de eigen huisstijl van een locatie weer.

Welke huisstijl een scherm gebruikt: een scherm met **één locatie** krijgt de huisstijl van die locatie, een scherm met **meer locaties** die van de organisatie. Bij [Schermen](#schermen) kies je per scherm een andere.

![Het infoscherm in de huisstijl van een locatie, met een bericht in de huisstijlkleur](../images/infoscherm-huisstijl-scherm.png)

## Schermen

Elk scherm (tv, kiosk of tablet) is een gebruikersaccount. Op het tabblad **Schermen** zie je per account de **locaties** en kies je wat het scherm toont: **Google Slides**, **Infoscherm** of **Slides + infoscherm** (de presentatie groot in beeld, met het weer, de mededelingen en de nieuwsbalk eromheen). Dezelfde keuze staat ook bij de gebruiker op het tabblad *Presentatie*.

**Welke inhoud een scherm toont, bepalen de locaties van het account** (Gebruikers › Features › *Zichtbare locaties*, in te stellen door de beheerder). Staat er geen locatie aangevinkt, dan staat er **🌐 Alle locaties** en toont het scherm alles. Iemand met het recht *Schermen* ziet alleen de schermen van zijn eigen locaties en kan hun locaties niet wijzigen.

| | Scherm met 1 locatie | Meer locaties (bijv. een MFA of een kiosk voor meerdere locaties) | 🌐 Alle locaties |
|---|---|---|---|
| Berichten, nieuws, agenda | van die locatie + wat voor alle locaties is | van al zijn locaties samen + wat voor alle locaties is | alles |
| BHV aanwezig, Vandaag jarig | die locatie | al zijn locaties samen | iedereen |
| Weer | weerplaats van die locatie, anders de algemene | de eerste van zijn locaties met een eigen weerplaats | de algemene |
| Huisstijl | van de locatie, anders de organisatie | de organisatie, of zelf een kiezen | de organisatie, of zelf kiezen |

Een **kiosk** met medewerkerskaarten voor meerdere locaties toont op het infoscherm altijd de inhoud van al zijn locaties, ook als op de kiosk één locatie is gekozen.

In de kolom **Infoscherm** kies je per scherm de **Huisstijl** (*Automatisch*, *Organisatie* of een locatie met een eigen huisstijl). Bij **Vandaag jarig** en **BHV aanwezig** kies je per scherm **Standaard** (volgt de instelling bij Weergave), **Aan** of **Uit**. Zo toont bijvoorbeeld het scherm in de school de jarigen wel en het scherm van de opvang niet. Een scherm toont altijd alleen de jarigen en BHV'ers van zijn eigen locaties. Een account zonder aangevinkte locaties ziet alle locaties; vink bij een scherm in een gebouw dus altijd de locatie(s) aan.

Met **👁️ Voorbeeld** achter een account zie je in een nieuw venster precies wat dat scherm toont: de berichten, het nieuws, het weer en de BHV'ers van de locaties van dat account. Bij een scherm met alleen Google Slides opent **👁️ Slides** de presentatie. Het voorbeeld toont de opgeslagen keuze; sla dus eerst op na een wijziging.

![Het tabblad Schermen met een voorbeeldknop per scherm](../images/infoscherm-schermen.png)

- **Op een aanraakscherm met medewerkerskaarten** verschijnt het infoscherm na de ingestelde tijd zonder aanraking (vinkje *Automatisch tonen*). Aanraken sluit het weer.
- **Een scherm dat alleen het infoscherm toont** (een tv in de hal): log op dat scherm in met het schermaccount, vink *Blijf ingelogd* aan en open `infoscherm.php`. Met een kiosk token opent het scherm zichzelf, ook na een herstart: voeg dan `&page=infoscherm` toe aan de token-URL. Het tabblad toont de volledige adressen.

Het infoscherm haalt elke 5 minuten nieuwe gegevens op, blijft bij een korte internetstoring het laatste tonen en laadt zichzelf opnieuw na een update.

> **Bijwerken van nieuws en weer** — Nieuws en weer worden elke 15 minuten bijgewerkt, zodra een scherm gegevens opvraagt. Met de cronjob `cron/cron_infoscreen.php` (zie [Automatische taken](#automatische-taken)) hoeft een scherm daar nooit op te wachten.

# Automatische taken

Een aantal taken draait op vaste tijden via cronjobs: iedereen om 23:00 op UIT, bezoekers uitchecken, oude bezoekersgegevens wissen, verlopen statussen terugzetten en het infoscherm bijwerken. Via **Automatische taken** op het dashboard (groep *Systeem Configuratie*, alleen met het recht *Systeemconfiguratie*) zie je per taak wanneer hij moet draaien, de opdracht voor het hostingpaneel en de weblink voor [cron-job.org](https://cron-job.org).

![Automatische taken met de sleutel en de links](../images/automatische-taken.png)

- **Sleutel van deze installatie** — een weblink werkt alleen met deze geheime sleutel. Elke installatie heeft een eigen sleutel; hij staat niet in de code. Met **Tonen** zie je hem, met **Kopiëren** kopieer je hem. Met **Nieuwe sleutel maken** maak je een nieuwe (de oude links werken dan niet meer; pas ze aan in cron-job.org).
- **Overstappen vanaf versie 2.5 of ouder** — tot en met 2.5 gebruikte elke installatie dezelfde, openbare sleutel. Die werkt na de update nog 30 dagen, zodat je cronjobs blijven draaien. Zolang een cronjob de oude sleutel gebruikt, toont het dashboard een melding. Vervang de links in cron-job.org door die van deze pagina en klik daarna op **Oude sleutel nu uitzetten**.

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
Dat gebeurt via cronjobs. Controleer bij **Automatische taken** of die zijn ingesteld en de links de juiste sleutel hebben.

**Hoe zet ik het wachtwoord van een gebruiker opnieuw?**
Open de gebruiker via ✏️, vul op het tabblad *Account* een nieuw wachtwoord in en klik op **Opslaan**.

**Een gebruiker is zijn telefoon kwijt en heeft geen herstelcodes meer.**
Open de gebruiker via ✏️ en klik op **🔄 2FA resetten (telefoon kwijt)**. Daarna kan die persoon weer met alleen het wachtwoord inloggen en stelt 2FA opnieuw in. Zie *Tweestapsverificatie (2FA)*.

**Ik kom zelf niet meer binnen door 2FA.**
Maak via FTP in de hoofdmap van de site een leeg bestand `twofa_uit.txt`. Log in met je wachtwoord, reset 2FA en verwijder het bestand weer.

**De code uit de app werkt niet.**
Meestal loopt de klok van de telefoon af: zet de automatische tijd aan. In Google Authenticator kan dat via *Instellingen → Tijdcorrectie voor codes → Nu synchroniseren*. Staan er twee accounts met bijna dezelfde naam in de app (van een eerdere poging), gebruik dan het account dat bij de huidige QR-code hoort en verwijder het oude.

**Hoe verhuis ik PeopleDisplay naar een andere hosting?**
Maak een volledige back-up en zet die op de nieuwe hosting terug met `restore.php` (zie de Installatiehandleiding). Heb je een Unlimited- of Corporate-licentie, wissel dan daarna je continuity-sleutel in.
