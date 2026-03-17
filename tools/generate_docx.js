// generate_docx.js — PeopleDisplay Gebruikershandleiding v2.0
// Run: node generate_docx.js
"use strict";

const {
  Document, Packer, Paragraph, TextRun, HeadingLevel,
  AlignmentType, TableOfContents, PageNumber, Footer,
  Header, ImageRun, BorderStyle, Table, TableRow, TableCell,
  WidthType, ShadingType, convertInchesToTwip, LevelFormat,
  NumberingConfig, UnderlineType, PageBreak
} = require("docx");
const fs = require("fs");
const path = require("path");

// ── Colour constants ────────────────────────────────────────────────────────
const BLUE   = "2E75B6";
const BLUE_L = "D6E4F0"; // light blue for info boxes
const GREY_L = "F2F2F2";
const BLACK  = "000000";

// ── Helper: heading paragraph ───────────────────────────────────────────────
function h1(text) {
  return new Paragraph({
    text,
    heading: HeadingLevel.HEADING_1,
    spacing: { before: 400, after: 120 },
    run: { color: BLUE, bold: true, size: 28 },
    style: "Heading1",
  });
}
function h2(text) {
  return new Paragraph({
    text,
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 300, after: 80 },
    run: { color: BLUE, bold: true, size: 24 },
    style: "Heading2",
  });
}
function h3(text) {
  return new Paragraph({
    text,
    heading: HeadingLevel.HEADING_3,
    spacing: { before: 200, after: 60 },
    run: { color: BLUE, bold: true, size: 22 },
    style: "Heading3",
  });
}

// ── Helper: normal paragraph ────────────────────────────────────────────────
function p(text, opts = {}) {
  return new Paragraph({
    children: [new TextRun({ text, size: 22, color: BLACK, ...opts })],
    spacing: { before: 60, after: 60 },
  });
}

// ── Helper: bold inline paragraph ───────────────────────────────────────────
function pb(text) {
  return new Paragraph({
    children: [new TextRun({ text, size: 22, bold: true, color: BLACK })],
    spacing: { before: 60, after: 60 },
  });
}

// ── Helper: bullet item ─────────────────────────────────────────────────────
function bullet(text, level = 0) {
  return new Paragraph({
    children: [new TextRun({ text, size: 22, color: BLACK })],
    bullet: { level },
    spacing: { before: 40, after: 40 },
  });
}

// ── Helper: numbered list item ───────────────────────────────────────────────
function numbered(text, level = 0) {
  return new Paragraph({
    children: [new TextRun({ text, size: 22, color: BLACK })],
    numbering: { reference: "main-numbering", level },
    spacing: { before: 40, after: 40 },
  });
}

// ── Helper: info box (shaded paragraph) ────────────────────────────────────
function infoBox(lines) {
  return lines.map((line, i) =>
    new Paragraph({
      children: [
        new TextRun({ text: line, size: 21, color: "1A5276", italics: i === 0 && line.startsWith("ℹ") }),
      ],
      spacing: { before: i === 0 ? 80 : 20, after: i === lines.length - 1 ? 80 : 20 },
      indent: { left: convertInchesToTwip(0.25), right: convertInchesToTwip(0.25) },
      shading: { type: ShadingType.CLEAR, color: "auto", fill: BLUE_L },
    })
  );
}

// ── Helper: screenshot placeholder ──────────────────────────────────────────
function screenshot(description) {
  return new Paragraph({
    children: [
      new TextRun({ text: `[SCREENSHOT: ${description}]`, size: 20, color: "999999", italics: true }),
    ],
    alignment: AlignmentType.CENTER,
    spacing: { before: 120, after: 120 },
    shading: { type: ShadingType.CLEAR, color: "auto", fill: GREY_L },
    border: {
      top:    { style: BorderStyle.SINGLE, size: 1, color: "CCCCCC" },
      bottom: { style: BorderStyle.SINGLE, size: 1, color: "CCCCCC" },
      left:   { style: BorderStyle.SINGLE, size: 1, color: "CCCCCC" },
      right:  { style: BorderStyle.SINGLE, size: 1, color: "CCCCCC" },
    },
  });
}

// ── Helper: page break ───────────────────────────────────────────────────────
function pageBreak() {
  return new Paragraph({ children: [new PageBreak()] });
}

// ── Helper: empty line ────────────────────────────────────────────────────────
function empty() {
  return new Paragraph({ text: "", spacing: { before: 40, after: 40 } });
}

// ═══════════════════════════════════════════════════════════════════════════
// DOCUMENT CONTENT
// ═══════════════════════════════════════════════════════════════════════════

function buildContent() {
  const sections = [];

  // ── COVER PAGE ──────────────────────────────────────────────────────────
  sections.push(
    new Paragraph({
      children: [new TextRun({ text: "", size: 22 })],
      spacing: { before: 2000 },
    }),
    new Paragraph({
      children: [new TextRun({ text: "PeopleDisplay", size: 64, bold: true, color: BLUE })],
      alignment: AlignmentType.CENTER,
    }),
    new Paragraph({
      children: [new TextRun({ text: "Gebruikershandleiding", size: 40, color: "555555" })],
      alignment: AlignmentType.CENTER,
      spacing: { before: 120 },
    }),
    new Paragraph({
      children: [new TextRun({ text: "Versie 2.0", size: 28, color: "777777" })],
      alignment: AlignmentType.CENTER,
      spacing: { before: 80 },
    }),
    new Paragraph({
      children: [new TextRun({ text: "Medewerkers aanwezigheidsregistratie", size: 24, color: "888888", italics: true })],
      alignment: AlignmentType.CENTER,
      spacing: { before: 60 },
    }),
    empty(),
    screenshot("PeopleDisplay logo / splash afbeelding"),
    pageBreak()
  );

  // ── TABLE OF CONTENTS ───────────────────────────────────────────────────
  sections.push(
    new Paragraph({
      children: [new TextRun({ text: "Inhoudsopgave", size: 32, bold: true, color: BLUE })],
      spacing: { before: 200, after: 200 },
    }),
    new TableOfContents("Inhoudsopgave", {
      hyperlink: true,
      headingStyleRange: "1-3",
    }),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 1: Introductie
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("1. Introductie"),
    p("PeopleDisplay is een webgebaseerd systeem voor het registreren van de aanwezigheid van medewerkers. Het systeem biedt een overzichtelijk scherm waarop zichtbaar is wie aanwezig, afwezig of in vergadering is."),
    empty(),
    h2("1.1 Wat is PeopleDisplay?"),
    p("PeopleDisplay is ontwikkeld voor organisaties die in één oogopslag willen zien welke medewerkers aanwezig zijn. Het systeem werkt via een webbrowser en is geschikt voor gebruik op een grote schermen in de ontvangsthal, aan de balie, of als zelfbedieningsterminal."),
    empty(),
    h2("1.2 Functionaliteiten"),
    bullet("Aanwezigheidsregistratie (IN / UIT / VERGADERING / OVERLEG)"),
    bullet("Bezoekersregistratie met e-mailnotificaties"),
    bullet("BHV-overzicht voor noodsituaties"),
    bullet("Kiosk-modus voor onbemande tablets"),
    bullet("Auditlog van alle statuswijzigingen"),
    bullet("CSV-import en -export van medewerkers"),
    bullet("Badgegeneratie (PDF)"),
    bullet("WiFi auto-inchecken op basis van IP-bereik"),
    bullet("PWA-ondersteuning (installeerbaar als app)"),
    bullet("Presentatiemodus met Google Slides"),
    bullet("Rolgebaseerde toegang (superadmin / admin / gebruiker)"),
    bullet("Licentiebeheer met meerdere tiers"),
    empty(),
    h2("1.3 Systeemvereisten"),
    bullet("Webserver met PHP 8.0 of hoger"),
    bullet("MySQL 5.7 of hoger / MariaDB 10.3 of hoger"),
    bullet("Moderne webbrowser (Chrome, Firefox, Edge, Safari)"),
    bullet("Internetverbinding voor licentievalidatie en updatecontrole"),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 2: Installatie
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("2. Installatie"),
    p("PeopleDisplay wordt geleverd als een ZIP-bestand dat via een webinstaller in 8 stappen wordt geconfigureerd."),
    empty(),
    h2("2.1 Benodigdheden"),
    bullet("FTP-toegang of bestandsbeheer via hosting-configuratiescherm"),
    bullet("Een MySQL/MariaDB-database met gebruikersrechten"),
    bullet("De databasenaam, gebruikersnaam en wachtwoord bij de hand"),
    empty(),
    h2("2.2 Stap-voor-stap installatie"),
    numbered("Upload de bestanden via FTP naar de gewenste map op uw server (bijv. /public_html/peopledisplay/)"),
    numbered("Open de browser en ga naar https://uwdomein.nl/peopledisplay/install.php"),
    numbered("Stap 1 — Systeemcontrole: het installatieprogramma controleert of alle vereisten aanwezig zijn"),
    numbered("Stap 2 — EULA: lees en accepteer de licentieovereenkomst"),
    numbered("Stap 3 — Licentiesleutel: voer uw licentiesleutel in (formaat: PDIS-XXXX-XXXX-XXXX)"),
    numbered("Stap 4 — Database: vul de databasegegevens in"),
    numbered("Stap 5 — Schema: het installatieprogramma maakt alle tabellen aan"),
    numbered("Stap 6 — Beheerdersaccount: stel uw eerste beheerdersaccount in"),
    numbered("Stap 7 — Configuratie: instellingen worden weggeschreven"),
    numbered("Stap 8 — Gereed: de installatie is voltooid"),
    empty(),
    ...infoBox([
      "ℹ️  Tip",
      "Na een succesvolle installatie is het installatiebestand (install.php) geblokkeerd.",
      "Verwijder dit bestand als extra veiligheidsmaatregel via FTP.",
    ]),
    empty(),
    screenshot("Installatieprogramma stap 1 - Systeemcontrole"),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 3: Eerste configuratie
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("3. Eerste configuratie"),
    p("Na de installatie logt u in met het beheerdersaccount dat u tijdens de installatie heeft aangemaakt. Vervolgens configureert u de basisinstellingen."),
    empty(),
    h2("3.1 Inloggen als beheerder"),
    numbered("Ga naar https://uwdomein.nl/peopledisplay/login.php"),
    numbered("Voer uw gebruikersnaam en wachtwoord in"),
    numbered("Klik op 'Inloggen'"),
    numbered("U wordt doorgestuurd naar het beheerderspaneel"),
    empty(),
    screenshot("Inlogscherm PeopleDisplay"),
    empty(),
    h2("3.2 Organisatienaam instellen"),
    p("Ga naar Beheer → Instellingen en voer de naam van uw organisatie in. Deze naam verschijnt bovenaan het aanwezigheidsscherm."),
    empty(),
    h2("3.3 Locaties aanmaken"),
    p("Ga naar Beheer → Locaties en voeg één of meerdere locaties toe. Elke locatie kan een eigen IP-bereik hebben voor automatisch inchecken."),
    empty(),
    h2("3.4 Afdelingen aanmaken"),
    p("Ga naar Beheer → Afdelingen en maak de afdelingen aan die in uw organisatie voorkomen. Medewerkers kunnen aan één afdeling worden gekoppeld."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 4: Medewerkersbeheer
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("4. Medewerkersbeheer"),
    p("In het medewerkersbeheer kunt u medewerkers toevoegen, bewerken en verwijderen. U kunt ook medewerkers importeren via CSV."),
    empty(),
    h2("4.1 Medewerker toevoegen"),
    numbered("Ga naar Beheer → Medewerkers"),
    numbered("Klik op 'Medewerker toevoegen'"),
    numbered("Vul de vereiste velden in: voornaam, achternaam, afdeling, locatie"),
    numbered("Optioneel: e-mailadres, telefoonnummer, foto, notities"),
    numbered("Klik op 'Opslaan'"),
    empty(),
    screenshot("Formulier medewerker toevoegen"),
    empty(),
    h2("4.2 Medewerker bewerken"),
    p("Klik in de medewerkerslijst op het potlood-icoon naast de betreffende medewerker. Pas de gegevens aan en klik op 'Opslaan'."),
    empty(),
    h2("4.3 Medewerker verwijderen"),
    p("Klik op het prullenbak-icoon naast de medewerker. Bevestig de verwijdering in het bevestigingsvenster. Verwijderde medewerkers worden uit alle overzichten verwijderd."),
    empty(),
    h2("4.4 CSV-import"),
    p("Via Beheer → Medewerkers → CSV importeren kunt u een lijst medewerkers in bulk importeren. Gebruik het meegeleverde CSV-sjabloon als basis."),
    bullet("Kolommen: voornaam, achternaam, afdeling, locatie, email, telefoon"),
    bullet("Maximum: afhankelijk van uw licentietier"),
    bullet("Bestaande medewerkers worden niet overschreven"),
    empty(),
    h2("4.5 CSV-export"),
    p("Via Beheer → Medewerkers → CSV exporteren downloadt u een overzicht van alle medewerkers inclusief hun huidige status."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 5: Aanwezigheidsscherm
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("5. Aanwezigheidsscherm"),
    p("Het aanwezigheidsscherm (index.php) is het centrale overzicht dat medewerkers en bezoekers zien. Hier kunnen medewerkers hun status bijwerken."),
    empty(),
    h2("5.1 Statusopties"),
    bullet("IN — medewerker is aanwezig op kantoor"),
    bullet("UIT — medewerker is afwezig"),
    bullet("VERGADERING — medewerker is in een vergadering"),
    bullet("OVERLEG — medewerker is in intern overleg"),
    empty(),
    screenshot("Aanwezigheidsscherm met medewerkerskaarten"),
    empty(),
    h2("5.2 Status wijzigen"),
    p("Klik op de kaart van de medewerker om de beschikbare statusopties te tonen. Klik op de gewenste status om deze op te slaan. De kaart wordt direct bijgewerkt."),
    empty(),
    h2("5.3 Zoeken en filteren"),
    p("Gebruik de zoekbalk bovenaan het scherm om te zoeken op naam. Gebruik de filteropties om te filteren op afdeling of locatie."),
    empty(),
    h2("5.4 Automatisch vernieuwen"),
    p("Het aanwezigheidsscherm vernieuwt automatisch elke 30 seconden. Zo blijft het overzicht actueel zonder handmatige actie."),
    empty(),
    h2("5.5 Sorteerknop"),
    p("De sorteerknop staat rechtsboven op het aanwezigheidsscherm (indien ingeschakeld door de beheerder). Hiermee kunt u de volgorde van medewerkers aanpassen."),
    empty(),
    pb("Beschikbare sorteeropties:"),
    bullet("Alfabetisch (A → Z) — medewerkers gesorteerd op achternaam"),
    bullet("Status — aanwezige medewerkers (IN) bovenaan, afwezigen onderaan"),
    bullet("Standaard — volgorde zoals ingesteld door de beheerder"),
    empty(),
    p("De geselecteerde sorteervolgorde wordt opgeslagen in de lokale browseropslag (localStorage) en blijft bewaard na het sluiten van de browser."),
    empty(),
    ...infoBox([
      "ℹ️  Beheerder instelling",
      "De sorteerknop moet door de beheerder worden ingeschakeld per gebruiker of per rol.",
      "Ga naar Beheer → Functies om de sorteerknop-functie in te schakelen.",
    ]),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 6: Bezoekersregistratie
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("6. Bezoekersregistratie"),
    p("Met de bezoekersregistratie kunnen bezoekers worden aangemeld bij de balie. Bezoekers ontvangen automatisch een e-mail met een uitchecklink."),
    empty(),
    h2("6.1 Bezoeker aanmelden"),
    numbered("Ga naar de bezoekerspagina (visitor_register.php) of gebruik de knop op het hoofdscherm"),
    numbered("Vul de naam van de bezoeker in"),
    numbered("Selecteer de gastheer (medewerker bij wie de bezoeker komt)"),
    numbered("Voer optioneel een e-mailadres en telefoonnummer in"),
    numbered("Klik op 'Aanmelden'"),
    numbered("De bezoeker ontvangt een e-mail met uitchecklink"),
    empty(),
    screenshot("Bezoekersregistratieformulier"),
    empty(),
    h2("6.2 E-mailnotificaties"),
    p("Bij aanmelding van een bezoeker ontvangt de gastheer automatisch een e-mailnotificatie. De bezoeker ontvangt een e-mail met een persoonlijke uitchecklink."),
    empty(),
    h2("6.3 Bezoeker uitchecken"),
    p("De bezoeker klikt op de uitchecklink in de e-mail om zichzelf uit te melden. De beheerder kan bezoekers ook handmatig uitchecken via Beheer → Bezoekers."),
    empty(),
    h2("6.4 Bezoekersbeheer"),
    p("Via Beheer → Bezoekers ziet u een overzicht van alle huidige bezoekers en de bezoekersgeschiedenis. U kunt bezoekers handmatig uitchecken of verwijderen."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 7: BHV-overzicht
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("7. BHV-overzicht"),
    p("Het BHV-overzicht (Bedrijfshulpverlening) geeft een geprint overzicht van alle aanwezige medewerkers en bezoekers. Dit overzicht is bedoeld voor gebruik bij ontruimingen en noodsituaties."),
    empty(),
    h2("7.1 BHV-overzicht openen"),
    p("Klik op de rode 'BHV Overzicht' knop onderaan het aanwezigheidsscherm. Het overzicht opent in een apart venster, klaar om af te drukken."),
    empty(),
    ...infoBox([
      "ℹ️  Kiosk-modus",
      "In kiosk-modus opent het BHV-overzicht in een popup-venster.",
      "Gebruik de rode sluitknop bovenaan het popup-venster om het te sluiten.",
    ]),
    empty(),
    screenshot("BHV-overzicht printpagina"),
    empty(),
    h2("7.2 BHV-overzicht afdrukken"),
    p("Druk op Ctrl+P (of Cmd+P op Mac) om het afdrukvenster te openen. Het BHV-overzicht is geoptimaliseerd voor afdrukken op A4-formaat."),
    empty(),
    h2("7.3 BHV-venster sluiten"),
    p("Klik op de rode balk bovenaan het BHV-venster en klik op 'Venster sluiten'. Dit werkt ook in kiosk-modus waar de normale browserknop niet beschikbaar is."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 8: Beheerderspaneel
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("8. Beheerderspaneel"),
    p("Het beheerderspaneel (admin/dashboard.php) is het centrale beheercentrum van PeopleDisplay. Hier beheert u medewerkers, instellingen, gebruikers en licenties."),
    empty(),
    h2("8.1 Dashboard"),
    p("Op het dashboard ziet u een overzicht van:"),
    bullet("Aantal aanwezige medewerkers"),
    bullet("Aantal bezoekers"),
    bullet("Recente auditlogentries"),
    bullet("Systeemstatus en updatenotificaties"),
    empty(),
    screenshot("Beheerderspaneel dashboard"),
    empty(),
    h2("8.2 Navigatiemenu"),
    p("Het navigatiemenu aan de linkerzijde geeft toegang tot alle beheerfuncties:"),
    bullet("Dashboard — overzicht"),
    bullet("Medewerkers — medewerkerbeheer"),
    bullet("Gebruikers — gebruikersaccounts"),
    bullet("Locaties — locatiebeheer"),
    bullet("Afdelingen — afdelingenbeheer"),
    bullet("Bezoekers — bezoekersoverzicht"),
    bullet("Auditlog — wijzigingshistorie"),
    bullet("Functies — functierechten per gebruiker"),
    bullet("Instellingen — systeeminstellingen"),
    bullet("Licentie — licentiebeheer"),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 9: Gebruikersbeheer
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("9. Gebruikersbeheer"),
    p("Gebruikers zijn de accounts waarmee beheerders en medewerkers kunnen inloggen in PeopleDisplay. Er zijn drie rollen: superadmin, admin en gebruiker."),
    empty(),
    h2("9.1 Rollen en rechten"),
    bullet("Superadmin — volledige toegang, inclusief gebruikersbeheer en licentie"),
    bullet("Admin — beheerderstoegang zonder gebruikersbeheer"),
    bullet("Gebruiker — alleen het aanwezigheidsscherm bekijken en eigen status wijzigen"),
    empty(),
    h2("9.2 Gebruiker aanmaken"),
    numbered("Ga naar Beheer → Gebruikers → Gebruiker toevoegen"),
    numbered("Vul gebruikersnaam en wachtwoord in"),
    numbered("Selecteer de rol"),
    numbered("Koppel optioneel een medewerker aan het account"),
    numbered("Klik op 'Opslaan'"),
    empty(),
    h2("9.3 Wachtwoord wijzigen"),
    p("Een beheerder kan het wachtwoord van elke gebruiker wijzigen via Beheer → Gebruikers. Gebruikers kunnen hun eigen wachtwoord wijzigen via hun profiel."),
    empty(),
    h2("9.4 Gebruiker deactiveren"),
    p("Deactiveer een gebruikersaccount om de toegang in te trekken zonder het account te verwijderen. Het account blijft zichtbaar in de lijst maar kan niet inloggen."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 10: Instellingen
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("10. Instellingen"),
    p("Via Beheer → Instellingen configureert u de algemene instellingen van PeopleDisplay."),
    empty(),
    h2("10.1 Algemene instellingen"),
    bullet("Organisatienaam — verschijnt bovenaan het aanwezigheidsscherm"),
    bullet("Logo — upload een bedrijfslogo (PNG/JPG, aanbevolen formaat 200×60 px)"),
    bullet("Tijdzone — stel de tijdzone in voor correcte tijdweergave"),
    bullet("Taal — Nederlands of Engels"),
    empty(),
    h2("10.2 Weergave-instellingen"),
    bullet("Naamweergave — voornaam, achternaam, of voornaam + eerste letter achternaam"),
    bullet("Kaartgrootte — klein, normaal of groot"),
    bullet("Donkere modus — automatisch of handmatig"),
    empty(),
    h2("10.3 Statusknopinstellingen"),
    p("Configureer welke statusopties beschikbaar zijn en stel in na hoeveel uur de status automatisch terugvalt naar UIT."),
    empty(),
    h2("10.4 E-mailinstellingen"),
    p("Configureer de SMTP-server voor het verzenden van e-mailnotificaties (bezoekersregistratie, systeemmeldingen)."),
    bullet("SMTP-server en poort"),
    bullet("Gebruikersnaam en wachtwoord"),
    bullet("Afzendernaam en -adres"),
    bullet("Testmail versturen"),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 11: Locatiebeheer
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("11. Locatiebeheer"),
    p("PeopleDisplay ondersteunt meerdere locaties. Elke locatie kan eigen medewerkers, instellingen en een IP-bereik hebben."),
    empty(),
    h2("11.1 Locatie aanmaken"),
    numbered("Ga naar Beheer → Locaties"),
    numbered("Klik op 'Locatie toevoegen'"),
    numbered("Vul de locatienaam in"),
    numbered("Voer optioneel een IP-bereik in voor automatisch inchecken"),
    numbered("Klik op 'Opslaan'"),
    empty(),
    h2("11.2 IP-bereik voor automatisch inchecken"),
    p("Als een medewerker verbinding maakt vanuit een IP-adres dat valt binnen het geconfigureerde IP-bereik van een locatie, wordt de status automatisch op IN gezet."),
    empty(),
    ...infoBox([
      "ℹ️  IP-bereik formaat",
      "Voer het IP-bereik in als CIDR-notatie, bijv. 192.168.1.0/24",
      "Of als begin- en eindadres: 192.168.1.1 - 192.168.1.254",
    ]),
    empty(),
    h2("11.3 Locatie hernoemen"),
    p("Als u een locatienaam wijzigt, wordt deze automatisch bijgewerkt voor alle medewerkers die aan die locatie zijn gekoppeld."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 12: Afdelingenbeheer
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("12. Afdelingenbeheer"),
    p("Afdelingen worden gebruikt om medewerkers te groeperen. Op het aanwezigheidsscherm kunt u filteren op afdeling."),
    empty(),
    h2("12.1 Afdeling aanmaken"),
    numbered("Ga naar Beheer → Afdelingen"),
    numbered("Klik op 'Afdeling toevoegen'"),
    numbered("Voer de afdelingsnaam in"),
    numbered("Klik op 'Opslaan'"),
    empty(),
    h2("12.2 Afdeling hernoemen"),
    p("Als u een afdelingsnaam wijzigt, wordt deze automatisch bijgewerkt voor alle medewerkers die aan die afdeling zijn gekoppeld."),
    empty(),
    h2("12.3 Afdeling verwijderen"),
    p("Een afdeling kan alleen worden verwijderd als er geen medewerkers meer aan zijn gekoppeld. Koppel eerst alle medewerkers los of wijs ze toe aan een andere afdeling."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 13: Auditlog
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("13. Auditlog"),
    p("Het auditlog registreert alle statuswijzigingen van medewerkers, inclusief wie de wijziging heeft uitgevoerd, wanneer en vanuit welk IP-adres."),
    empty(),
    h2("13.1 Auditlog bekijken"),
    p("Ga naar Beheer → Auditlog. U ziet een chronologisch overzicht van alle wijzigingen. Gebruik de filters om te zoeken op medewerker, datum of wijzigingstype."),
    empty(),
    screenshot("Auditlog overzicht"),
    empty(),
    h2("13.2 Informatie in het auditlog"),
    bullet("Datum en tijdstip van de wijziging"),
    bullet("Naam van de medewerker"),
    bullet("Veld dat is gewijzigd (status, locatie, afdeling, etc.)"),
    bullet("Oude en nieuwe waarde"),
    bullet("Wie de wijziging heeft doorgevoerd"),
    bullet("IP-adres en gebruikersagent"),
    empty(),
    h2("13.3 Auditlog exporteren"),
    p("Via de exportknop kunt u het auditlog exporteren als CSV-bestand voor archivering of analyse."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 14: Badgegeneratie
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("14. Badgegeneratie"),
    p("PeopleDisplay kan ID-kaarten en badges genereren als PDF-bestand. Gebruik deze functie voor het aanmaken van toegangsbadges of naamkaartjes."),
    empty(),
    h2("14.1 Badge aanmaken"),
    numbered("Ga naar Beheer → Medewerkers"),
    numbered("Selecteer één of meerdere medewerkers"),
    numbered("Klik op 'Badges genereren'"),
    numbered("Kies het badgeformaat"),
    numbered("Klik op 'Genereren' — het PDF-bestand wordt gedownload"),
    empty(),
    screenshot("Badgegeneratie interface"),
    empty(),
    h2("14.2 Badgeformaten"),
    bullet("Standaard badge — naam, afdeling, foto, QR-code"),
    bullet("Bezoekerspas — naam bezoeker, datum, gastheer"),
    bullet("Compact — alleen naam en afdeling"),
    empty(),
    h2("14.3 Fotobeheer"),
    p("Via het medewerkerformulier kunt u een profielfoto uploaden. Deze foto wordt gebruikt op de badge en op het aanwezigheidsscherm."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 15: PWA en presentatiemodus
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("15. PWA en presentatiemodus"),
    p("PeopleDisplay ondersteunt Progressive Web App (PWA) technologie, waardoor het als een app kan worden geïnstalleerd op mobiele apparaten en computers."),
    empty(),
    h2("15.1 PWA installeren"),
    p("Op ondersteunde apparaten verschijnt in de browser een installatieprompt. Klik op 'Installeren' om PeopleDisplay toe te voegen aan het startscherm of de taakbalk."),
    bullet("Android: Chrome toont een installatiebanner"),
    bullet("iOS: gebruik 'Toevoegen aan beginscherm' via het deelmenu"),
    bullet("Windows/Mac: klik op het installatie-icoon in de adresbalk"),
    empty(),
    h2("15.2 Offline werking"),
    p("De PWA-versie heeft beperkte offlinefunctionaliteit. Het laatste bekende overzicht blijft zichtbaar bij een verbindingsuitval."),
    empty(),
    h2("15.3 Presentatiemodus"),
    p("De presentatiemodus start automatisch een Google Slides presentatie op het aanwezigheidsscherm. Configureer de presentatie-URL via Beheer → Instellingen → Presentatiemodus."),
    bullet("Stel een Google Slides URL in"),
    bullet("Configureer de vertraging voordat de presentatie start"),
    bullet("De presentatie stopt automatisch bij klikactiviteit"),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 16: Kiosk-tokens (NIEUW)
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("16. Kiosk-tokens"),
    p("Kiosk-tokens zijn unieke URL-gebaseerde toegangssleutels voor onbemande schermen, zoals tabletten in een ontvangsthal of aula. Met een kiosk-token kan een tablet het PeopleDisplay-aanwezigheidsscherm tonen zonder dat er een gebruikersaccount voor nodig is."),
    empty(),
    h2("16.1 Wat zijn kiosk-tokens?"),
    p("Een kiosk-token is een lange, unieke code die wordt toegevoegd aan de URL van het aanwezigheidsscherm. De tablet slaat deze URL op en opent het scherm automatisch, ook na een herstart, zonder inlogprompt."),
    empty(),
    ...infoBox([
      "ℹ️  Beveiliging",
      "Een kiosk-token geeft alleen leestoegang tot het aanwezigheidsscherm.",
      "Statuswijzigingen via een kiosk-token zijn beperkt afhankelijk van uw instellingen.",
      "Bewaar de token-URL veilig — iedereen met de URL heeft toegang tot het scherm.",
    ]),
    empty(),
    h2("16.2 Kiosk-token aanmaken"),
    numbered("Ga naar Beheer → Kiosk-tokens"),
    numbered("Klik op 'Nieuw token aanmaken'"),
    numbered("Geef het token een beschrijvende naam (bijv. 'Tablet ontvangstbalie')"),
    numbered("Selecteer optioneel een locatie en/of afdeling"),
    numbered("Klik op 'Token genereren'"),
    numbered("Kopieer de volledige token-URL"),
    empty(),
    screenshot("Kiosk-token aanmaken — overzicht met gegenereerde token-URL"),
    empty(),
    h2("16.3 Token instellen op een tablet"),
    p("Open de token-URL op de tablet. PeopleDisplay herkent de token en toont het aanwezigheidsscherm zonder inlogscherm."),
    empty(),
    pb("Aanbevolen instelling voor een onbemande kiosk-tablet (Windows):"),
    numbered("Open Chrome en ga naar de token-URL"),
    numbered("Maak een snelkoppeling op het bureaublad"),
    numbered("Pas de snelkoppeling aan en voeg de volgende vlaggen toe achter het Chrome-pad:"),
    new Paragraph({
      children: [
        new TextRun({
          text: 'chrome.exe --kiosk "https://uwdomein.nl/peopledisplay/?kiosk_token=UWTOKEN"',
          font: "Courier New",
          size: 18,
          color: "333333",
        }),
      ],
      indent: { left: convertInchesToTwip(0.5) },
      shading: { type: ShadingType.CLEAR, color: "auto", fill: GREY_L },
      spacing: { before: 80, after: 80 },
    }),
    p("Met de --kiosk vlag start Chrome in volledig scherm zonder adresbalk, tabs of het sluitkruisje. De gebruiker ziet alleen PeopleDisplay."),
    empty(),
    h2("16.4 BHV-overzicht in kiosk-modus"),
    p("In kiosk-modus is de BHV-knop onderaan het scherm beschikbaar. Bij klikken opent het BHV-overzicht in een pop-upvenster bovenop het kiosk-scherm."),
    p("Bovenaan het BHV-venster bevindt zich een rode balk met een 'Venster sluiten'-knop. Hiermee sluit de medewerker het BHV-venster en keert terug naar het aanwezigheidsscherm — ook als de normale browserknop niet beschikbaar is in kiosk-modus."),
    empty(),
    screenshot("BHV-venster met rode sluitbalk in kiosk-modus"),
    empty(),
    h2("16.5 Token intrekken"),
    p("Als een tablet verloren raakt of een token gecompromitteerd is, kunt u het token intrekken via Beheer → Kiosk-tokens → Token intrekken. Na intrekking werkt de token-URL niet meer."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 17: Functiebeheer
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("17. Functiebeheer"),
    p("Via Beheer → Functies kunt u per gebruiker instellen welke functies beschikbaar zijn. Dit is onafhankelijk van de rol van de gebruiker."),
    empty(),
    h2("17.1 Beschikbare functies"),
    bullet("Sorteerknop — medewerkers kunnen de volgorde van het aanwezigheidsscherm aanpassen"),
    bullet("CSV-export — toegang tot de exportfunctie"),
    bullet("Badgegeneratie — toegang tot de PDF-badgegenerator"),
    bullet("Bezoekersregistratie — toegang tot het bezoekersformulier"),
    bullet("Auditlog — toegang tot het auditlogscherm"),
    empty(),
    h2("17.2 Functie in- of uitschakelen"),
    p("Klik in de functienlijst op de schakelaar naast een functie om deze in of uit te schakelen voor een specifieke gebruiker. Wijzigingen worden direct opgeslagen."),
    empty(),
    screenshot("Functiebeheer — schakelaaroverzicht per gebruiker"),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 18: Updates
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("18. Updates"),
    p("PeopleDisplay controleert automatisch of er een nieuwe versie beschikbaar is. Als een update beschikbaar is, verschijnt een banner op het beheerderspaneel."),
    empty(),
    h2("18.1 Updatenotificatie"),
    p("Op het dashboard verschijnt een blauwe banner als er een nieuwe versie beschikbaar is. De banner toont het versienummer, de belangrijkste wijzigingen en een link naar de volledige changelog."),
    empty(),
    ...infoBox([
      "ℹ️  Kritieke updates",
      "Als een update als 'kritiek' is gemarkeerd, wordt de banner rood weergegeven.",
      "Kritieke updates bevatten beveiligingsoplossingen en worden aanbevolen zo snel mogelijk te installeren.",
    ]),
    empty(),
    h2("18.2 Update installeren"),
    numbered("Download het updatepakket via de link in de updatebanner"),
    numbered("Maak een back-up van uw database en bestanden"),
    numbered("Upload de bestanden via FTP (overschrijf bestaande bestanden)"),
    numbered("Voer eventueel meegeleverde migratiescripts uit in phpMyAdmin"),
    numbered("Leeg de browsercache"),
    empty(),
    h2("18.3 Update negeren"),
    p("Klik op 'Later herinneren' in de updatebanner om de melding tijdelijk te verbergen. De melding verschijnt opnieuw bij de volgende versie."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 19: Veelgestelde vragen
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("19. Veelgestelde vragen"),
    empty(),
    pb("Waarom zie ik een witte pagina na de installatie?"),
    p("Controleer of de databasegegevens in admin/db_config.php correct zijn. Controleer ook of PHP foutrapportage aan staat en bekijk de PHP-foutlog van uw server."),
    empty(),
    pb("De status wordt niet opgeslagen. Wat gaat er mis?"),
    p("Controleer of de sessieconfiguratie correct is. Op sommige hostingomgevingen moet de sessiepad handmatig worden ingesteld. Raadpleeg de installatiehandleiding voor uw hostingprovider."),
    empty(),
    pb("Ik ontvang geen e-mailmeldingen bij bezoekersregistraties."),
    p("Controleer de SMTP-instellingen via Beheer → Instellingen → E-mail. Verstuur een testmail om de verbinding te verifiëren. Controleer ook de spammap."),
    empty(),
    pb("Het aanwezigheidsscherm vernieuwt niet automatisch."),
    p("Controleer of JavaScript is ingeschakeld in de browser. Controleer ook of er geen browserextensies zijn die JavaScript blokkeren."),
    empty(),
    pb("Hoe reset ik het wachtwoord van de superadmin?"),
    p("Via phpMyAdmin kunt u in de users-tabel het wachtwoord handmatig wijzigen. Gebruik password_hash() in PHP om een nieuw wachtwoord te hashen."),
    empty(),
    pb("Kan ik PeopleDisplay gebruiken op meerdere domeinen?"),
    p("Elke installatie is gebonden aan één domein via het licentiesysteem. Voor gebruik op meerdere domeinen heeft u meerdere licenties nodig."),
    empty(),
    pb("Hoe verwijder ik een installatie volledig?"),
    p("Verwijder alle bestanden via FTP en verwijder de database via phpMyAdmin. Het licentiesysteem wordt automatisch gedeactiveerd na het verwijderen."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // HOOFDSTUK 20: Licentiebeheer (NIEUW)
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("20. Licentiebeheer"),
    p("PeopleDisplay werkt met een licentiesysteem. Elke installatie vereist een geldige licentiesleutel. De licentie is gebonden aan uw domeinnaam."),
    empty(),
    h2("20.1 Licentietiers"),
    p("PeopleDisplay is beschikbaar in zes licentietiers:"),
    empty(),
    // License tier table
    new Table({
      width: { size: 100, type: WidthType.PERCENTAGE },
      rows: [
        new TableRow({
          tableHeader: true,
          children: [
            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: "Tier", bold: true, color: "FFFFFF", size: 20 })] })], shading: { fill: BLUE } }),
            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: "Naam", bold: true, color: "FFFFFF", size: 20 })] })], shading: { fill: BLUE } }),
            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: "Medewerkers", bold: true, color: "FFFFFF", size: 20 })] })], shading: { fill: BLUE } }),
            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: "Gebruikers", bold: true, color: "FFFFFF", size: 20 })] })], shading: { fill: BLUE } }),
            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: "Locaties", bold: true, color: "FFFFFF", size: 20 })] })], shading: { fill: BLUE } }),
          ],
        }),
        ...[
          ["S", "Starter",      "25",         "3",  "1"],
          ["P", "Professional", "75",         "5",  "3"],
          ["B", "Business",     "150",        "10", "5"],
          ["E", "Enterprise",   "300",        "25", "10"],
          ["C", "Corporate",    "500",        "50", "20"],
          ["U", "Unlimited",    "Onbeperkt",  "Onbeperkt", "Onbeperkt"],
        ].map((row, i) =>
          new TableRow({
            children: row.map(cell =>
              new TableCell({
                children: [new Paragraph({ children: [new TextRun({ text: cell, size: 20 })] })],
                shading: { fill: i % 2 === 0 ? GREY_L : "FFFFFF" },
              })
            ),
          })
        ),
      ],
    }),
    empty(),
    h2("20.2 Licentie activeren"),
    p("Tijdens de installatie (stap 3) voert u uw licentiesleutel in. Na de installatie kunt u de licentie ook activeren via de licentiepagina."),
    empty(),
    numbered("Ga naar de startpagina van PeopleDisplay of navigate naar activate_license.php"),
    numbered("Voer uw licentiesleutel in (formaat: PDIS-XXXX-XXXX-XXXX)"),
    numbered("Klik op 'Activeren'"),
    numbered("De licentie wordt gevalideerd via de PeopleDisplay-server"),
    numbered("Bij succesvolle activering wordt u doorgestuurd naar het dashboard"),
    empty(),
    ...infoBox([
      "ℹ️  Domeinbinding",
      "Een licentie is gebonden aan één domeinnaam.",
      "Als u PeopleDisplay verplaatst naar een ander domein, dient u uw licentie opnieuw te activeren.",
      "Neem contact op met support@peopledisplay.nl voor domeinwijzigingen.",
    ]),
    empty(),
    screenshot("Licentiesleutel activeren — invoerveld met formaat PDIS-XXXX-XXXX-XXXX"),
    empty(),
    h2("20.3 Licentiestatus bekijken"),
    p("Via Beheer → Licentie ziet u de actuele licentiestatus:"),
    bullet("Licentiesleutel (gedeeltelijk gemaskeerd)"),
    bullet("Huidige tier en bijbehorende limieten"),
    bullet("Activatiedatum en verloopdatum"),
    bullet("Huidig gebruik (medewerkers, gebruikers, locaties)"),
    bullet("Beschikbare features per tier"),
    empty(),
    screenshot("Licentiebeheer pagina met statusoverzicht en gebruiksbalken"),
    empty(),
    h2("20.4 Licentie upgraden"),
    p("Om te upgraden naar een hogere tier neemt u contact op via de PeopleDisplay-website. Na aankoop ontvangt u een nieuwe licentiesleutel die u kunt activeren via de licentiepagina."),
    empty(),
    h2("20.5 Licentie deactiveren"),
    p("Via Beheer → Licentie → Licentie deactiveren kunt u de licentie deactiveren. Dit is nodig voordat u PeopleDisplay naar een ander domein verplaatst."),
    empty(),
    ...infoBox([
      "⚠️  Waarschuwing",
      "Na deactivering is PeopleDisplay niet meer toegankelijk totdat een nieuwe licentie is geactiveerd.",
      "Zorg dat u de nieuwe licentiesleutel bij de hand heeft voordat u deactiveert.",
    ]),
    empty(),
    h2("20.6 Verlopen licentie"),
    p("Als uw licentie is verlopen, worden gebruikers doorgestuurd naar de licentiepagina. Beheerdersaccounts behouden toegang om de licentie te vernieuwen."),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // BIJLAGE A: Technische specificaties
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("Bijlage A: Technische specificaties"),
    empty(),
    h2("Serverspecificaties"),
    bullet("PHP 8.0 of hoger (8.2 aanbevolen)"),
    bullet("MySQL 5.7+ of MariaDB 10.3+"),
    bullet("Min. 256 MB RAM voor PHP"),
    bullet("Min. 50 MB schijfruimte (excl. foto-uploads)"),
    bullet("HTTPS aanbevolen voor productiegebruik"),
    empty(),
    h2("Ondersteunde browsers"),
    bullet("Google Chrome 90+"),
    bullet("Mozilla Firefox 88+"),
    bullet("Microsoft Edge 90+"),
    bullet("Apple Safari 14+"),
    empty(),
    h2("Databasetabellen"),
    p("PeopleDisplay gebruikt 30 databasetabellen, inclusief:"),
    bullet("employees — medewerkersinformatie en status"),
    bullet("users — gebruikersaccounts en rollen"),
    bullet("locations — locatiedefinities met IP-bereiken"),
    bullet("afdelingen — afdelingsindeling"),
    bullet("visitors — bezoekersregistraties"),
    bullet("employee_audit — auditlog van statuswijzigingen"),
    bullet("config — systeeminstellingen (sleutel-waardeparen)"),
    bullet("license_tiers — licentietiersdefinities"),
    bullet("feature_keys — beschikbare systeemfuncties"),
    empty(),
    h2("API-endpoints"),
    bullet("api/get_employees.php — medewerkersoverzicht (JSON)"),
    bullet("api/update_status.php — statusupdate"),
    bullet("api/get_visitors.php — bezoekersoverzicht"),
    bullet("admin/api/changelog.php — versiehistorie (publiek)"),
    bullet("admin/api/users_create.php — gebruiker aanmaken"),
    empty(),
    pageBreak()
  );

  // ═══════════════════════════════════════════════════════════════
  // BIJLAGE B: Licentieovereenkomst
  // ═══════════════════════════════════════════════════════════════
  sections.push(
    h1("Bijlage B: Licentieovereenkomst (samenvatting)"),
    p("Dit is een samenvatting van de Eindgebruikslicentieovereenkomst (EULA). De volledige EULA wordt getoond tijdens de installatie en is beschikbaar via peopledisplay.nl."),
    empty(),
    bullet("PeopleDisplay is gelicenseerd, niet verkocht"),
    bullet("De licentie is gebonden aan één domein"),
    bullet("Doorverkoop of herlicentering is niet toegestaan"),
    bullet("Aanpassingen aan de broncode zijn niet toegestaan zonder schriftelijke toestemming"),
    bullet("Aansprakelijkheid is beperkt tot de aankoopprijs van de licentie"),
    bullet("De licentie blijft van kracht totdat deze wordt beëindigd"),
    empty(),
    p("Voor de volledige EULA ga naar: https://peopledisplay.nl/licentie"),
    empty(),
    empty(),
    new Paragraph({
      children: [new TextRun({ text: "© 2025–2026 PeopleDisplay. Alle rechten voorbehouden.", size: 18, color: "888888", italics: true })],
      alignment: AlignmentType.CENTER,
      spacing: { before: 400 },
    })
  );

  return sections;
}

// ═══════════════════════════════════════════════════════════════════════════
// BUILD DOCUMENT
// ═══════════════════════════════════════════════════════════════════════════

async function main() {
  const doc = new Document({
    numbering: {
      config: [
        {
          reference: "main-numbering",
          levels: [
            {
              level: 0,
              format: LevelFormat.DECIMAL,
              text: "%1.",
              alignment: AlignmentType.LEFT,
              style: { paragraph: { indent: { left: 360, hanging: 260 } } },
            },
          ],
        },
      ],
    },
    styles: {
      default: {
        document: {
          run: { font: "Calibri", size: 22, color: BLACK },
          paragraph: { spacing: { line: 276 } },
        },
      },
      paragraphStyles: [
        {
          id: "Heading1",
          name: "Heading 1",
          basedOn: "Normal",
          next: "Normal",
          quickFormat: true,
          run: { font: "Calibri", size: 32, bold: true, color: BLUE },
          paragraph: { spacing: { before: 400, after: 160 } },
        },
        {
          id: "Heading2",
          name: "Heading 2",
          basedOn: "Normal",
          next: "Normal",
          quickFormat: true,
          run: { font: "Calibri", size: 26, bold: true, color: BLUE },
          paragraph: { spacing: { before: 300, after: 100 } },
        },
        {
          id: "Heading3",
          name: "Heading 3",
          basedOn: "Normal",
          next: "Normal",
          quickFormat: true,
          run: { font: "Calibri", size: 22, bold: true, color: BLUE },
          paragraph: { spacing: { before: 200, after: 80 } },
        },
      ],
    },
    sections: [
      {
        properties: {
          page: {
            size: {
              width:  convertInchesToTwip(8.27),   // A4 width
              height: convertInchesToTwip(11.69),  // A4 height
            },
            margin: {
              top:    convertInchesToTwip(1.0),
              bottom: convertInchesToTwip(1.0),
              left:   convertInchesToTwip(1.18),
              right:  convertInchesToTwip(1.18),
            },
          },
        },
        footers: {
          default: new Footer({
            children: [
              new Paragraph({
                children: [
                  new TextRun({ text: "PeopleDisplay Gebruikershandleiding v2.0  |  ", size: 18, color: "888888" }),
                  new TextRun({ children: [PageNumber.CURRENT], size: 18, color: "888888" }),
                  new TextRun({ text: " / ", size: 18, color: "888888" }),
                  new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 18, color: "888888" }),
                ],
                alignment: AlignmentType.CENTER,
                border: { top: { style: BorderStyle.SINGLE, size: 1, color: "CCCCCC" } },
              }),
            ],
          }),
        },
        children: buildContent(),
      },
    ],
  });

  const outPath = path.join(__dirname, "PeopleDisplay_Gebruikershandleiding_v2_0_bijgewerkt.docx");
  const buffer = await Packer.toBuffer(doc);
  fs.writeFileSync(outPath, buffer);
  console.log("✓ Generated:", outPath, `(${Math.round(buffer.length / 1024)} KB)`);
}

main().catch(err => { console.error(err); process.exit(1); });
