<?php
/**
 * Shortcode [db_barrierefrei_check] für das Frontend-Suchformular.
 *
 * @package DB_Barrierefrei_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registriert den Shortcode und kümmert sich um das bedingte Laden der
 * Frontend-Assets (CSS/JS) – nur auf Seiten, auf denen der Shortcode
 * tatsächlich vorkommt, nicht auf jeder Seite der Website.
 */
class DB_Barrierefrei_Check_Shortcode {

    /**
     * Shortcode-Tag, wie es Redakteure im Editor verwenden:
     * [db_barrierefrei_check].
     */
    private const SHORTCODE_TAG = 'db_barrierefrei_check';

    /**
     * Handle, unter dem Skript/Stylesheet registriert werden – wird
     * auch vom Nonce-Namen im AJAX-Handler referenziert (dort als
     * String-Konstante NONCE_ACTION = 'db_check_nonce').
     */
    private const NONCE_ACTION = 'db_check_nonce';

    public static function init(): void {
        add_shortcode( self::SHORTCODE_TAG, array( self::class, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_assets' ) );
    }

    /**
     * Lädt die Frontend-Assets nur, wenn der Shortcode im Content des
     * aktuell angefragten Beitrags/der Seite vorkommt. Vermeidet
     * unnötiges CSS/JS auf Seiten, die das Suchformular gar nicht zeigen.
     *
     * Hinweis: has_shortcode() prüft nur den regulären Post-Content.
     * Falls der Shortcode z.B. über ein Widget, einen Block-Template-Part
     * oder programmatisch per do_shortcode() eingebunden wird, greift
     * dieser Check nicht – für diesen Fall gibt es den defensiven
     * Fallback direkt in render().
     */
    public static function maybe_enqueue_assets(): void {
        global $post;

        if ( ! ( $post instanceof WP_Post ) || ! has_shortcode( $post->post_content, self::SHORTCODE_TAG ) ) {
            return;
        }

        self::register_and_enqueue();
    }

    /**
     * Registriert und lädt Stylesheet + Skript, inklusive der Werte
     * (AJAX-URL, Nonce), die das Frontend-JS für die Suche braucht.
     */
    private static function register_and_enqueue(): void {
        $style_handle  = 'db-barrierefrei-check-frontend';
        $script_handle = 'db-barrierefrei-check-frontend';

        if ( ! wp_style_is( $style_handle, 'registered' ) ) {
            wp_register_style(
                $style_handle,
                DB_BARRIEREFREI_CHECK_URL . 'public/css/frontend.css',
                array(),
                DB_BARRIEREFREI_CHECK_VERSION
            );
        }
        wp_enqueue_style( $style_handle );

        if ( ! wp_script_is( $script_handle, 'registered' ) ) {
            wp_register_script(
                $script_handle,
                DB_BARRIEREFREI_CHECK_URL . 'public/js/frontend.js',
                array(),
                DB_BARRIEREFREI_CHECK_VERSION,
                true // im Footer laden.
            );

            wp_localize_script(
                $script_handle,
                'dbCheckAjax',
                array(
                    'url'    => admin_url( 'admin-ajax.php' ),
                    'nonce'  => wp_create_nonce( self::NONCE_ACTION ),
                    'fields' => self::get_active_field_meta(),
                    'i18n'   => array(
                        'loading'               => __( 'Suche läuft…', 'db-barrierefrei-check' ),
                        'resultCountSingular'   => __( '1 Bahnhof gefunden', 'db-barrierefrei-check' ),
                        /* translators: %d: Anzahl gefundener Bahnhöfe. */
                        'resultCountPlural'     => __( '%d Bahnhöfe gefunden', 'db-barrierefrei-check' ),
                        'noResults'             => __( 'Keine Bahnhöfe gefunden.', 'db-barrierefrei-check' ),
                        'errorGeneric'          => __( 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.', 'db-barrierefrei-check' ),
                        'requiredFieldsMissing' => __( 'Bitte einen Bahnhofsnamen eingeben.', 'db-barrierefrei-check' ),
                        'loadMore'              => __( 'Weitere Ergebnisse laden', 'db-barrierefrei-check' ),
                        /* translators: %1$d: bisher geladene Treffer, %2$d: Treffer insgesamt. */
                        'loadedStatus'          => __( '%1$d von %2$d Bahnhöfen geladen', 'db-barrierefrei-check' ),
                        'valueYes'              => __( 'Ja', 'db-barrierefrei-check' ),
                        'valuePartial'          => __( 'Teilweise', 'db-barrierefrei-check' ),
                        'valueNoUnknown'        => __( 'Nein / Unbekannt', 'db-barrierefrei-check' ),
                        'valuePresent'          => __( 'Vorhanden', 'db-barrierefrei-check' ),
                        'valueNone'             => __( 'Keine', 'db-barrierefrei-check' ),
                        'moreInfo'              => __( 'Weitere Informationen', 'db-barrierefrei-check' ),
                    ),
                )
            );
        }
        wp_enqueue_script( $script_handle );
    }

    /**
     * Liefert Label + Datentyp für jedes aktuell aktivierte Feld, damit
     * das Frontend-JS Badges passend zum Feldtyp rendern kann, ohne
     * Feldnamen/Labels selbst hart zu codieren – einzige Quelle bleibt
     * die Field Registry.
     *
     * @return array<string, array{label: string, typ: string}>
     */
    private static function get_active_field_meta(): array {
        if ( ! class_exists( 'DB_Barrierefrei_Check_Field_Registry' ) ) {
            return array();
        }

        $active_keys = DB_Barrierefrei_Check_Field_Registry::get_active_fields();
        $all_fields  = DB_Barrierefrei_Check_Field_Registry::get_all_fields();

        $meta = array();
        foreach ( $active_keys as $key ) {
            if ( isset( $all_fields[ $key ] ) ) {
                $meta[ $key ] = array(
                    'label'     => $all_fields[ $key ]['label'],
                    'typ'       => $all_fields[ $key ]['typ'],
                    'kategorie' => $all_fields[ $key ]['kategorie'],
                );
            }
        }

        return $meta;
    }

    /**
     * Rendert das Suchformular. Die eigentliche Markup-Struktur lebt in
     * public/views/shortcode-template.php (folgt als nächste Datei) –
     * hier wird nur vorbereitet: Assets sicherstellen, Attribute
     * normalisieren, Template einbinden und Ausgabe abfangen.
     *
     * @param array<string, mixed>|string $atts Shortcode-Attribute.
     */
    public static function render( $atts ): string {
        // Fallback, falls maybe_enqueue_assets() den Shortcode nicht
        // erkannt hat (z.B. Einbindung außerhalb des normalen
        // Post-Contents) – so werden Assets in jedem Fall geladen,
        // bevor das Formular tatsächlich ausgegeben wird.
        if ( ! wp_style_is( 'db-barrierefrei-check-frontend', 'enqueued' ) ) {
            self::register_and_enqueue();
        }

        $atts = shortcode_atts(
            array(
                'ueberschrift' => __( 'Bahnhof auf Barrierefreiheit prüfen', 'db-barrierefrei-check' ),
            ),
            $atts,
            self::SHORTCODE_TAG
        );

        $template = DB_BARRIEREFREI_CHECK_PATH . 'public/views/shortcode-template.php';

        if ( ! file_exists( $template ) ) {
            return '<p>' . esc_html__( 'Die Vorlage für die Bahnhofssuche wurde nicht gefunden.', 'db-barrierefrei-check' ) . '</p>';
        }

        // $atts und self::NONCE_ACTION stehen dem Template über den
        // gemeinsamen Funktions-Scope zur Verfügung (siehe include unten).
        $nonce_action = self::NONCE_ACTION;

        ob_start();
        include $template;
        return (string) ob_get_clean();
    }
}

DB_Barrierefrei_Check_Shortcode::init();