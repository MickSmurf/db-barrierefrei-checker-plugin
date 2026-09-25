<?php
/**
 * Deaktivierung des Plugins.
 *
 * @package db-barrierefrei-check
 */
if(! defined('ABSPATH')) {
    exit;
}

/**
 * Klasse für die Deaktivierung des Plugins.
 */
class DB_Barrierefrei_Check_Deactivator {
    /**
     * Hauptfunktionsaufruf für die Deaktivierung des Plugins.
     *
     * @param bool $network_wide True, wenn im Multisite-Netzwerk deaktiviert werden soll.
     */
    public static function deactivate(bool $network_wide= false): void {
        if (is_multisite() && $network_wide) {
            $site_ids = get_sites(array('fields' => 'ids')  );

            foreach ($site_ids as $site_id) {
                switch_to_blog((int)$site_id);
                self::deactivate_single_site();
                restore_current_blog();
            }
            return;
        }
        self::deactivate_single_site();
    }

    /**
     * Deaktivierungs-Logik für eine Site
     */
    private static function deactivate_single_site(): void {
        self::clear_cached_api_response();
        self::clear_scheduled_events();
        self::clear_activation_notice();
    }

    /**
     * Entfernt gecachte API-Antworten und temporäre Dateien.
     */
    private static function clear_cached_api_response(): void {
        global $wpdb;

        $prefix= 'db_check_';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $transient_options = $wpdb->get_col(
            $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('_transient_'.$prefix) . '%')
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        foreach ( $transient_options as $option_name ) {
            $transient_name = str_replace( '_transient_', '', $option_name );
            delete_transient( $transient_name );
        }
    }

    /**
     * Entfernt geplante Cron-Events, falls das Plugin in Zukunft welche
     * registriert (z.B. ein regelmäßiger Health-Check der API-Zugangsdaten).
     * Aktuell registriert das Plugin noch keine eigenen Cron-Events – diese
     * Methode ist bewusst als Platzhalter vorbereitet, damit bei Bedarf
     * an genau dieser Stelle ergänzt wird, statt es zu vergessen.
     */
    private static function clear_scheduled_events(): void {
        $timestamp = wp_next_scheduled( 'db_barrierefrei_check_cron_event' );

        if( false !== $timestamp) {
            wp_unschedule_event( $timestamp, 'db_barrierefrei_check_cron_event' );
        }
    }

    /**
     * Entfernt den "Willkommens-Hinweis"-Transient aus dem Activator,
     * falls das Plugin direkt nach der Aktivierung wieder deaktiviert
     * wurde, bevor der Hinweis im Admin-Bereich angezeigt/abgeholt wurde.
     */
    private static function clear_activation_notice(): void {
        delete_transient( 'db_barrierefrei_check_activation_notice' );
    }

}
