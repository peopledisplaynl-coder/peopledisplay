<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Oude en onveilige bestanden opruimen.
 *
 * Een update-ZIP kan geen bestanden verwijderen. Daarom ruimt PeopleDisplay deze
 * bestanden zelf op, één keer per versie van deze lijst, bij het eerste verzoek
 * na een update (aangeroepen vanuit includes/migrations.php).
 *
 * Alleen exact de paden hieronder, relatief aan de hoofdmap, worden verwijderd.
 * Voeg hier nooit een map of een patroon toe.
 */

const PD_OBSOLETE_FILES_VERSION = '2.1.16';

/** @return string[] */
function pd_obsolete_files_list(): array
{
    return [
        // Onbeveiligd wachtwoord-resetscript (iedereen kon elk wachtwoord resetten)
        'reset_password.php',

        // Onbeveiligde of verouderde API's (zonder inlogcontrole of niet meer in gebruik)
        'admin/api/features_update.php',
        'admin/api/features.php',
        'admin/api/users_list.php',
        'admin/api/proxy.php',
        'admin/api/get-config.php',
        'admin/api/get_button_config.php',
        'admin/user_permissions.php',
        'admin/js/user_permissions_save.js',
        'api/send_visitor_notification.php',
        'mobile-status-view.js',

        // Losse hulp- en debugscripts die op sommige installaties zijn achtergebleven
        'api/features_update.php',
        'api/users_list.php',
        'api/proxy.php',
        'api/get-config.php',
        'api/get_button_config.php',
        'js/user_permissions_save.js',
        'admin/api/sync_locations_smart.php',

        // Losse oude kopieën in de hoofdmap en een oud voorbeeldbestand met instellingen
        // (includes/config.json wordt nergens gelezen en is via de browser op te vragen)
        'includes/config.json',
        'version.php',
        'migrations.php',

        // Losse kopieën van admin/api/ in de map api/ (zaten per ongeluk in oudere installatie-ZIP's;
        // vanuit api/ kloppen de paden niet, dus ze deden niets behalve in de weg staan)
        'api/afdelingen_api.php',
        'api/bulk_actions_api.php',
        'api/change_password.php',
        'api/check_button_names_api.php',
        'api/delete_profile_photo.php',
        'api/dismiss_update.php',
        'api/employees_api.php',
        'api/employee_add.php',
        'api/employee_delete.php',
        'api/employee_update.php',
        'api/export_employees_csv.php',
        'api/features.php',
        'api/force_logout_user.php',
        'api/get-user-features.php',
        'api/get_locations_ordered.php',
        'api/get_online_users.php',
        'api/import_employees_csv.php',
        'api/kiosk_token_actions.php',
        'api/save_locations_order.php',
        'api/save_user_button_names.php',
        'api/sync_locations_smart.php',
        'api/update_profile.php',
        'api/update_status.php',
        'api/upload_profile_photo.php',
        'api/users_create.php',
        'api/users_delete.php',
        'api/users_password.php',
        'api/users_update.php',
        'api/user_delete.php',
        'api/user_features.php',
        'api/changelog.php',
        'license_debug.php',
        'version_debug.php',
        'db_check.php',
        'clear_cache.php',
        'clear_opcache.php',
        'admin/check_telefoon_column.php',
    ];
}

/**
 * Verwijder de bestanden uit de lijst, hooguit één keer per lijstversie.
 *
 * @return string[] Relatieve paden die zijn verwijderd (leeg als er niets te doen was)
 */
function pd_remove_obsolete_files(string $root): array
{
    $rootReal = realpath($root);
    if ($rootReal === false) {
        return [];
    }

    $marker = sys_get_temp_dir() . '/pd_obsolete_' . md5($rootReal) . '_' . PD_OBSOLETE_FILES_VERSION;
    if (is_file($marker)) {
        return [];
    }

    $removed = [];
    foreach (pd_obsolete_files_list() as $rel) {
        $path = $rootReal . '/' . $rel;
        if (!is_file($path) && !is_link($path)) {
            continue;
        }
        // Alleen echt binnen de hoofdmap werken
        $real = realpath(dirname($path));
        if ($real === false || strpos($real . '/', $rootReal . '/') !== 0) {
            continue;
        }
        if (@unlink($path)) {
            $removed[] = $rel;
        } else {
            error_log('PeopleDisplay: kon verouderd bestand niet verwijderen: ' . $rel);
        }
    }

    if ($removed) {
        error_log('PeopleDisplay: verouderde bestanden verwijderd: ' . implode(', ', $removed));
    }

    // Alleen als alles weg is, hoeft het niet opnieuw (anders volgende keer opnieuw proberen)
    $left = array_filter(pd_obsolete_files_list(), function ($rel) use ($rootReal) {
        return is_file($rootReal . '/' . $rel);
    });
    if (!$left) {
        @file_put_contents($marker, date('c'));
    }

    return $removed;
}
