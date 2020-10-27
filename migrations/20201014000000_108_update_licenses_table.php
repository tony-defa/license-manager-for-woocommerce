<?php

defined('ABSPATH') || exit;

/**
 * @var string $migrationMode
 */

use LicenseManagerForWooCommerce\Setup;
use LicenseManagerForWooCommerce\Migration;

$tableLicenses     = $wpdb->prefix . Setup::LICENSES_TABLE_NAME;

if ($wpdb->get_var("SHOW TABLES LIKE '{$tableLicenses}'") != $tableLicenses) {
    return;
}

/**
 * Upgrade
 */
if ($migrationMode === Migration::MODE_UP) {
    $sql = "
        ALTER TABLE {$tableLicenses}
            ADD COLUMN `times_activated_overall` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `times_activated_max`;
    ";

    $wpdb->query($sql);
}

/**
 * Downgrade
 */
if ($migrationMode === Migration::MODE_DOWN) {
    $sql = "
        ALTER TABLE {$tableLicenses}
            DROP COLUMN `times_activated_overall`;
    ";

    $wpdb->query($sql);
}