<?php
/**
 * Admin-Menü für DB Barrierefrei-Check.
 *
 * @package DB_Barrierefrei_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registriert den Backend-Menüpunkt und alles, was direkt damit
 * zusammenhängt (Assets, Aktivierungs-Hinweis). Die eigentliche
 * Settings-Registrierung (register_setting, Sanitizing) lebt bewusst
 * getrennt in class-settings.php – Trennung von "wo taucht die Seite
 * auf" und "was passiert mit den Daten".
 */
class DB_Barrierefrei_Check_Admin_Menu {

    /**
     * Slug der Einstellungsseite. Öffentlich, da settings-page.php und
     * die Admin-Notice-URL ihn ebenfalls brauchen.
     */
    public const MENU_SLUG = 'db-barrierefrei-check';

    public static function init(): void {
        add_action( 'admin_menu', array( self::class, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( self::class, 'maybe_enqueue_assets' ) );
        add_action( 'admin_notices', array( self::class, 'maybe_show_activation_notice' ) );
    }

    /**
     * Registriert einen eigenen Top-Level-Menüpunkt (statt eines
     * Untermenüs bei "Einstellungen") – bei einem Plugin mit API-
     * Zugangsdaten und potenziell mehreren Unterseiten in Zukunft
     * (z.B. später ein Cache-Status) ist das übersichtlicher.
     */
    public static function register_menu(): void {
        add_menu_page(
            __( 'DB Barrierefrei-Check', 'db-barrierefrei-check' ),
            __( 'Barrierefrei-Check', 'db-barrierefrei-check' ),
            'manage_options',
            self::MENU_SLUG,
            array( self::class, 'render_settings_page' ),
            'dashicons-universal-access-alt',
            80
        );
    }

    /**
     * Gibt die Einstellungsseite aus. Reine Zugriffs- und Existenz-
     * Prüfung hier, die eigentliche Formular-Darstellung lebt in
     * admin/views/settings-page.php.
     */
    public static function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Du hast keine Berechtigung, diese Seite aufzurufen.', 'db-barrierefrei-check' ) );
        }

        $template = DB_BARRIEREFREI_CHECK_PATH . 'admin/views/settings-page.php';

        if ( ! file_exists( $template ) ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Die Einstellungen-Vorlage wurde nicht gefunden.', 'db-barrierefrei-check' ) . '</p></div>';
            return;
        }

        include $template;
    }

    /**
     * Lädt das Admin-Stylesheet ausschließlich auf der eigenen
     * Einstellungsseite, nicht im gesamten WordPress-Backend.
     *
     * @param string $hook_suffix Von WordPress übergebener Hook-Suffix
     *                            der aktuellen Admin-Seite.
     */
    public static function maybe_enqueue_assets( string $hook_suffix ): void {
        // Bei einem Top-Level-Menüpunkt lautet der Hook-Suffix
        // "toplevel_page_{slug}" – von WordPress automatisch vergeben.
        if ( 'toplevel_page_' . self::MENU_SLUG !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'db-barrierefrei-check-admin',
            DB_BARRIEREFREI_CHECK_URL . 'admin/css/admin.css',
            array(),
            DB_BARRIEREFREI_CHECK_VERSION
        );
    }

    /**
     * Zeigt einmalig einen Hinweis nach der Aktivierung, der zur
     * Einstellungsseite verlinkt (Transient wird vom Activator gesetzt,
     * siehe class-activator.php).
     */
    public static function maybe_show_activation_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! get_transient( 'db_barrierefrei_check_activation_notice' ) ) {
            return;
        }

        delete_transient( 'db_barrierefrei_check_activation_notice' );

        $settings_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );

        printf(
            '<div class="notice notice-info is-dismissible"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
            esc_html__( 'DB Barrierefrei-Check wurde aktiviert.', 'db-barrierefrei-check' ),
            esc_url( $settings_url ),
            esc_html__( 'Jetzt API-Zugangsdaten eintragen', 'db-barrierefrei-check' )
        );
    }
}

DB_Barrierefrei_Check_Admin_Menu::init();
