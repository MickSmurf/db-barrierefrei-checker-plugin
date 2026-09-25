<?php
/**
 * Uninstall script for db-barrierefrei-check plugin
 *
 * Diese Datei enthält die Funktionen, die beim Deinstallieren des Plugins ausgeführt werden.
 *
 * @package db-barrierefrei-check
 */

//sicherstellen, dass diese Datei wirklich von WordPress ausgerufen wird
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Löscht alle Daten des Plugins beim Deinstallieren
 */
Function db_barrierefrei_check_uninstall_cleanup(): void {
    delete_option('db_barrierefrei_check_settings');

    delete_option('db_barrierefrei_settings');

    global $wpdb;

    $prefix ='db_check_';

    $transient_options = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('_transient_' . $prefix). '%'));

    foreach ($transient_options as $option_name) {
        $transient_name = str_replace('_transient_', '', $option_name);
        delete_option($option_name);
    }

    wp_cache_flush();
}

// Multisite: Falls das Plugin netzwerkweit installiert ist, werden die Daten für alle Sites gelöscht
if (is_multisite()) {
    $sites_ids = get_sites(array('fields' => 'ids'));
    foreach ($sites_ids as $site_id) {
        switch_to_blog($site_id);
        db_barrierefrei_check_uninstall_cleanup();
        restore_current_blog();
    }
} else {
    db_barrierefrei_check_uninstall_cleanup();
}

