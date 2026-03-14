<?php
/**
 * BESTANDSNAAM: privacy.php
 * LOCATIE: /privacy.php (ROOT)
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacyverklaring - PeopleDisplay</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; line-height: 1.8; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #1976d2; margin-bottom: 10px; font-size: 32px; }
        h2 { color: #1976d2; margin-top: 30px; margin-bottom: 15px; font-size: 24px; border-bottom: 2px solid #e3f2fd; padding-bottom: 8px; }
        h3 { color: #424242; margin-top: 20px; margin-bottom: 10px; font-size: 18px; }
        p { margin-bottom: 15px; }
        .summary { background: #e3f2fd; padding: 20px; border-left: 4px solid #1976d2; margin: 20px 0; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #1976d2; color: white; }
        ul { margin-left: 30px; margin-bottom: 15px; }
        li { margin-bottom: 8px; }
        .contact-box { background: #f5f5f5; padding: 20px; border-radius: 6px; margin: 20px 0; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #1976d2; text-decoration: none; font-weight: bold; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="javascript:history.back()" class="back-link">← Terug</a>
        
        <h1>🔒 Privacyverklaring Bezoekersregistratie</h1>
        <p><em>Laatst bijgewerkt: <?= date('d-m-Y') ?></em></p>
        
        <div class="summary">
            <strong>Kort samengevat:</strong> Wij verzamelen alleen de noodzakelijke gegevens voor bezoekersregistratie. Uw gegevens worden na 7 dagen automatisch verwijderd en worden nooit met derden gedeeld.
        </div>
        
        <h2>1. Wie zijn wij?</h2>
        <p>PeopleDisplay maakt gebruik van een digitaal bezoekersregistratiesysteem (PeopleDisplay) voor het registreren van bezoekers op onze locatie(s). Wij zijn verantwoordelijk voor de verwerking van uw persoonsgegevens.</p>
        
        <h2>2. Welke gegevens verzamelen wij?</h2>
        <p>Bij uw bezoek verzamelen wij de volgende gegevens:</p>
        
        <table>
            <tr>
                <th>Gegeven</th>
                <th>Doel</th>
                <th>Bewaartermijn</th>
            </tr>
            <tr>
                <td>Voor- en achternaam</td>
                <td>Identificatie en aanmelding</td>
                <td>7 dagen</td>
            </tr>
            <tr>
                <td>E-mailadres</td>
                <td>Check-in/check-out link versturen</td>
                <td>7 dagen</td>
            </tr>
            <tr>
                <td>Telefoonnummer (optioneel)</td>
                <td>Contact bij noodsituaties</td>
                <td>7 dagen</td>
            </tr>
            <tr>
                <td>Bedrijfsnaam (optioneel)</td>
                <td>Administratieve doeleinden</td>
                <td>7 dagen</td>
            </tr>
            <tr>
                <td>Datum en tijd bezoek</td>
                <td>Registratie aanwezigheid</td>
                <td>7 dagen</td>
            </tr>
            <tr>
                <td>Contactpersoon/medewerker</td>
                <td>Wie u bezoekt</td>
                <td>7 dagen</td>
            </tr>
            <tr>
                <td>Check-in en check-out tijden</td>
                <td>Aanwezigheidsregistratie</td>
                <td>7 dagen</td>
            </tr>
        </table>
        
        <h2>3. Waarom verwerken wij deze gegevens?</h2>
        <p>Wij verwerken uw persoonsgegevens voor de volgende doeleinden:</p>
        <ul>
            <li><strong>Beveiliging en toegangsbeheer:</strong> Het registreren van bezoekers voor veiligheidsredenen</li>
            <li><strong>Communicatie:</strong> Het versturen van check-in en check-out links via e-mail</li>
            <li><strong>Melding aan medewerkers:</strong> Uw contactpersoon informeren over uw aankomst en vertrek</li>
            <li><strong>Noodsituaties:</strong> In geval van calamiteiten (brand, evacuatie) weten wie er aanwezig is</li>
        </ul>
        
        <h2>4. Rechtsgrond voor verwerking</h2>
        <p>Wij verwerken uw persoonsgegevens op basis van:</p>
        <ul>
            <li><strong>Toestemming:</strong> U geeft expliciet toestemming bij het inchecken</li>
            <li><strong>Gerechtvaardigd belang:</strong> Beveiliging van ons pand en medewerkers</li>
            <li><strong>Wettelijke verplichting:</strong> Voldoen aan veiligheidsvoorschriften</li>
        </ul>
        
        <h2>5. Hoe lang bewaren wij uw gegevens?</h2>
        <h3>Automatische verwijdering na 7 dagen</h3>
        <p>Alle bezoekersgegevens worden <strong>automatisch en permanent</strong> uit ons systeem verwijderd 7 dagen na uw bezoek. Dit gebeurt volledig geautomatiseerd.</p>
        
        <h2>6. Delen wij uw gegevens?</h2>
        <p><strong>Nee.</strong> Wij delen uw persoonsgegevens nooit met derden, behalve:</p>
        <ul>
            <li>De medewerker die u bezoekt ontvangt een melding van uw aankomst</li>
            <li>In geval van noodsituaties (hulpdiensten)</li>
        </ul>
        <p>Wij verkopen of verhuren uw gegevens <strong>nooit</strong> aan externe partijen.</p>
        
        <h2>7. Beveiliging van uw gegevens</h2>
        <p>Wij nemen de bescherming van uw gegevens serieus en hebben passende technische en organisatorische maatregelen getroffen:</p>
        <ul>
            <li>Beveiligde SSL/HTTPS verbinding</li>
            <li>Versleutelde database opslag</li>
            <li>Toegangsbeperking tot geautoriseerd personeel</li>
            <li>Unieke, niet-raadbare check-in tokens</li>
            <li>Automatische verwijdering na 7 dagen</li>
        </ul>
        
        <h2>8. Uw rechten</h2>
        <p>U heeft de volgende rechten met betrekking tot uw persoonsgegevens:</p>
        <ul>
            <li><strong>Recht op inzage:</strong> U kunt opvragen welke gegevens wij van u hebben</li>
            <li><strong>Recht op correctie:</strong> U kunt onjuiste gegevens laten corrigeren</li>
            <li><strong>Recht op verwijdering:</strong> U kunt verzoeken uw gegevens eerder te verwijderen</li>
            <li><strong>Recht op beperking:</strong> U kunt verzoeken de verwerking te beperken</li>
            <li><strong>Recht op bezwaar:</strong> U kunt bezwaar maken tegen de verwerking</li>
            <li><strong>Recht op intrekking toestemming:</strong> U kunt uw toestemming op elk moment intrekken</li>
        </ul>
        
        <h3>Hoe kunt u uw rechten uitoefenen?</h3>
        <p>Neem contact op met onze beheerder via de contactgegevens hieronder. Wij reageren binnen 30 dagen op uw verzoek.</p>
        
        <h2>9. Cookies</h2>
        <p>Ons bezoekersregistratiesysteem maakt <strong>geen gebruik van tracking cookies</strong>. Wij gebruiken alleen technische sessiecookies die noodzakelijk zijn voor de werking van het systeem.</p>
        
        <h2>10. Wijzigingen in deze privacyverklaring</h2>
        <p>Wij kunnen deze privacyverklaring van tijd tot tijd aanpassen. De laatste versie is altijd beschikbaar op deze pagina. Belangrijke wijzigingen communiceren wij actief.</p>
        
        <h2>11. Contact</h2>
        <div class="contact-box">
            <p><strong>PeopleDisplay</strong></p>
            <p>E-mail: privacy@uwbedrijf.nl</p>
            <p>Telefoon: +31 (0)20 123 4567</p>
        </div>
        
        <h2>12. Klacht indienen</h2>
        <p>Als u niet tevreden bent over de manier waarop wij met uw persoonsgegevens omgaan, heeft u het recht een klacht in te dienen bij de Autoriteit Persoonsgegevens:</p>
        <div class="contact-box">
            <p><strong>Autoriteit Persoonsgegevens</strong></p>
            <p>Postbus 93374<br>2509 AJ Den Haag</p>
            <p>Telefoon: 088 - 1805 250</p>
            <p>Website: <a href="https://autoriteitpersoonsgegevens.nl" target="_blank">autoriteitpersoonsgegevens.nl</a></p>
        </div>
    </div>
</body>
</html>
