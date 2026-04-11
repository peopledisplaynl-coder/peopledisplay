<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ============================================================
 * PeopleDisplay — Database Configuration Template
 * ============================================================
 *
 * INSTRUCTIONS:
 * 1. Copy this file to: admin/db_config.php
 * 2. Fill in your database credentials below
 * 3. Save the file
 *
 * The installer (install.php) does this automatically.
 * If you install manually, copy and edit this file yourself.
 *
 * ============================================================
 * WHERE TO GET THESE VALUES:
 *
 * XAMPP / localhost:
 *   DB_HOST = 'localhost'
 *   DB_NAME = name you chose when creating the database
 *   DB_USER = 'root'
 *   DB_PASS = '' (empty by default in XAMPP)
 *
 * Strato Webhosting:
 *   DB_HOST = provided in Strato control panel (e.g. db12345.hosting.strato.de)
 *   DB_NAME = provided in Strato control panel
 *   DB_USER = provided in Strato control panel
 *   DB_PASS = password you set in Strato control panel
 *
 * cPanel Hosting:
 *   DB_HOST = 'localhost' (almost always)
 *   DB_NAME = yourusername_dbname (cPanel prefixes the database name)
 *   DB_USER = yourusername_dbuser (cPanel prefixes the username)
 *   DB_PASS = password you set when creating the DB user
 *
 * Plesk Hosting:
 *   DB_HOST = 'localhost' (almost always)
 *   DB_NAME = name you chose
 *   DB_USER = name you chose
 *   DB_PASS = password you set
 * ============================================================
 */

// Database hostname
// Local: 'localhost' | Strato: 'db12345.hosting.strato.de' | cPanel: 'localhost'
$DB_HOST = 'localhost';

// Database name
$DB_NAME = 'your_database_name';

// Database username
$DB_USER = 'your_database_user';

// Database password
$DB_PASS = 'your_database_password';

/*
 * ============================================================
 * AFTER EDITING:
 * - Rename/copy this file to: admin/db_config.php
 * - Make sure admin/db_config.php is NOT accessible from the web
 *   (the admin/.htaccess already blocks direct access to this file)
 * - Run install.php to set up the database tables
 * ============================================================
 *
 * ============================================================
 * FUTURE: License Configuration (not yet active)
 * ============================================================
 * When the licensing system is implemented, these fields will
 * be added to the config table and read from here.
 *
 * // define('LICENSE_KEY', '');
 * // define('LICENSE_TYPE', 'free'); // free|starter|professional|business|enterprise|unlimited
 * // define('LICENSE_EXPIRES', '');  // YYYY-MM-DD or empty for never
 * ============================================================
 */
