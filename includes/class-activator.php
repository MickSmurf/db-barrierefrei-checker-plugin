<?php
/**
 * Aktivator-Klasse für das db-barrierefrei-check-Plugin
 *
 * @package db-barrierefrei-check
 */
if ( !defined('ABSPATH') ) {
    exit;
}
class DB_Barrierefrei_Check_Activator {
    private const OPTION_NAME = 'db_barrierefrei_check_settings';


    /**
     * Standardmäßig aktive Felder bei einer Neuinstallation
     *
     * Entspricht Kategorie 1 ("Kernfelder") aus der Feld-Einteilung -
     *bewusst konservativ, damit ein frisch installiertes Plugin sofort
     *sinnvolle barrierefreiheits-relevante Infos anzeigt, ohne dass
     *der Kunde zwingend erst ins Backend muss, um irgendwas zu sehen.
     *Kategorie 2/3 muss der Kunde bewusst selbst dazuschalten.
     *
     *@return string[]
     */
    private static function get_default_active_fields(): array {
        return array(
            'hasSteplessAccess',
            'hasPublicFacilities',
            'hasMobilityService',
            'mobilityServiceStaff',
            'localServiceStaff',
            'hasTaxiRank',
        );
    }

    /**
     * Haupt-Einstiegspunkt für das Plugin
     *
     * @param bool $network_wide True, wenn im Multisite-Netzwerk installiert
     */
    public static function activate(bool $network_wide= false): void {
        if ( is_multisite() && $network_wide ) {
            $site_ids = get_sites(array('fields' => 'ids'));

            foreach ( $site_ids as $site_id ) {
                switch_to_blog((int) $site_id);
                self::activate_single_site();
                restore_current_blog();
            }
            return;
        }
        self::activate_single_site();
    }

    /**
     * Aktivierungs-Logik für genau eine Site
     */
    private static function activate_single_site():void {
        self::maybe_set_default_settings();
        self::maybe_update_version_option();
        self::queue_welcome_notice();
    }

    /**
     * Legt die Standard-Einstellungen fest, wenn noch nicht gesetzt
     */
    private static function maybe_set_default_settings(): void {
        $existing = get_option(self::OPTION_NAME, false);
        if (false !== $existing) {
            $defaults = self::get_default_settings();
            $merged = wp_parse_args($existing, $defaults);

            if( $merged !== $existing) {
                update_option(self::OPTION_NAME, $merged, false);
            }

            return;
        }
        add_option(self::OPTION_NAME, self::get_default_settings(), '', false);
    }

    /**
     * Struktur und Default-Werte des zentralen Settings-Arrays.
     *
     * @return array{client_id: string, client_secret: string, felder_aktiv: string[]}
     */
    private static function get_default_settings(): array {
        return array(
            'client_id' => '',
            'client_secret' => '',
            'felder_aktiv' => self::get_default_active_fields(),
        );
    }

    /**
     * Speichert die Plugin Version, mit der zuletzt aktiviert wurde
     *
     */
    private static function maybe_update_version_option(): void {
        update_option('db_barrierefrei_check_version', DB_BARRIEREFREI_CHECK_VERSION, false);
    }

    /**
     * Merkt sich für einen einmaligen Admin Hinweis, dass frisch
     * aktiviert wurde - z.B. um im Backend "Bitte jetzt API-Zugangsdaten eintragen" zu empfehlen
     */
    private static function queue_welcome_notice(): void {
        set_transient('db_barrierefrei_check_activation_notice', true, MINUTE_IN_SECONDS * 5);
    }
}