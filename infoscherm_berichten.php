<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscherm_berichten.php
 * Upload naar: /infoscherm_berichten.php
 *
 * Since 2.10.0 everybody with an infoscreen right uses admin/infoscreen_manage.php (with
 * only their own rights and locations). This address stays for bookmarks.
 */
$tab = ($_GET['tab'] ?? '') === 'huisstijl' ? 'huisstijl' : 'berichten';
header('Location: admin/infoscreen_manage.php?tab=' . $tab, true, 302);
exit;
