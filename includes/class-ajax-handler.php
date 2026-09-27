<?php
/**
 * AJAX-Endpunkt für die Frontend-Bahnhofssuche.
 *
 * @package DB_Barrierefrei_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Nimmt die Suchanfrage aus dem Frontend-Formular entgegen, validiert
 * sie und reicht sie an den API-Client weiter.
 */
class DB_Barrierefrei_Check_Ajax_Handler {

    /**
     * Name der Nonce-Action, muss mit dem Wert übereinstimmen, der im
     * Shortcode-Template per wp_create_nonce() erzeugt wird.
     */
    private const NONCE_ACTION = 'db_check_nonce';

    /**
     * Feste Anzahl Treffer pro Ladevorgang ("Weitere Ergebnisse laden").
     * Bewusst als Code-Konstante statt Admin-Einstellung, um die
     * Einstellungsseite nicht mit einem selten benötigten Wert zu
     * überladen – bei Bedarf hier anpassen.
     */
    private const PAGE_SIZE = 10;

    /**
     * Registriert die AJAX-Hooks. Wird am Ende dieser Datei direkt
     * aufgerufen (siehe unten), da die Datei nur während
     * 'plugins_loaded' eingebunden wird – rechtzeitig genug, um die
     * späteren 'wp_ajax_*'-Hooks noch zu erreichen.
     */
    public static function init(): void {
        // _nopriv_ zusätzlich, damit auch nicht eingeloggte
        // Website-Besucher die Suche nutzen können – das ist hier der
        // Regelfall, nicht die Ausnahme.
        add_action( 'wp_ajax_db_check_suche', array( self::class, 'handle_search' ) );
        add_action( 'wp_ajax_nopriv_db_check_suche', array( self::class, 'handle_search' ) );
    }

    /**
     * Verarbeitet die eingehende Suchanfrage und sendet eine
     * JSON-Antwort zurück (wp_send_json_success / wp_send_json_error
     * beenden die Ausführung selbstständig).
     */
    public static function handle_search(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $searchstring = isset( $_POST['searchstring'] ) ? sanitize_text_field( wp_unslash( $_POST['searchstring'] ) ) : '';
        $offset       = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;

        if ( '' === $searchstring ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Bitte einen Bahnhofsnamen eingeben.', 'db-barrierefrei-check' ),
                ),
                400
            );
            return;
        }

        $query_args = array(
            'searchstring' => $searchstring,
            'limit'        => self::PAGE_SIZE,
            'offset'       => $offset,
        );

        if ( ! class_exists( 'DB_Barrierefrei_Check_Api_Client' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Interner Fehler: API-Client nicht verfügbar.', 'db-barrierefrei-check' ),
                ),
                500
            );
            return;
        }

        $client = new DB_Barrierefrei_Check_Api_Client();
        $result = $client->search_stations( $query_args );

        if ( is_wp_error( $result ) ) {
            $status = 500;
            $data   = $result->get_error_data();
            if ( is_array( $data ) && isset( $data['status'] ) ) {
                $status = (int) $data['status'];
            } elseif ( 'db_check_missing_credentials' === $result->get_error_code() ) {
                $status = 503;
            }

            wp_send_json_error(
                array(
                    'message' => $result->get_error_message(),
                ),
                $status
            );
            return;
        }

        /*
         * Jede Station im Ergebnis auf die Basis-Felder plus die im
         * Backend aktivierten Felder reduzieren, statt die komplette
         * Rohantwort der API durchzureichen.
         */
        if ( class_exists( 'DB_Barrierefrei_Check_Field_Registry' ) && isset( $result['result'] ) && is_array( $result['result'] ) ) {
            $active_fields    = DB_Barrierefrei_Check_Field_Registry::get_active_fields();
            $result['result'] = array_map(
                static fn( array $station ): array => DB_Barrierefrei_Check_Field_Registry::filter_station_data( $station, $active_fields ),
                $result['result']
            );
        }

        wp_send_json_success( $result );
    }
}

DB_Barrierefrei_Check_Ajax_Handler::init();

