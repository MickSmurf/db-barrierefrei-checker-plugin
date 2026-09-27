<?php
/**
 * Client für die Deutsche Bahn StaDa (Station Data) API v2.
 *
 * @package DB_Barrierefrei_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Kapselt sämtliche Kommunikation mit der StaDa-API: Auth-Header,
 * Query-Parameter-Whitelisting, Caching per Transient und
 * Fehlerbehandlung. Kein anderer Teil des Plugins spricht direkt mit
 * der API – so bleibt das Auth-Handling an genau einer Stelle.
 */
class DB_Barrierefrei_Check_Api_Client {

    /**
     * Basis-URL der StaDa API v2, exakt aus der OpenAPI-Spec.
     */
    private const API_BASE_URL = 'https://apis.deutschebahn.com/db-api-marketplace/apis/station-data/v2';

    /**
     * Präfix für Transient-Cache-Keys. Muss mit dem Präfix in
     * uninstall.php und class-deactivator.php übereinstimmen, da dort
     * per Präfix-Suche aufgeräumt wird.
     */
    private const CACHE_PREFIX = 'db_check_';

    /**
     * Standard-Cache-Dauer. Stationsdaten sind Stammdaten, die sich
     * selten ändern (kein Echtzeitstatus wie z.B. Aufzugsstörungen),
     * daher ist eine mehrstündige Cache-Zeit unproblematisch und
     * schont gleichzeitig das API-Rate-Limit des Kunden-Accounts.
     */
    private const DEFAULT_CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * Timeout für den HTTP-Request in Sekunden.
     */
    private const REQUEST_TIMEOUT = 10;

    /**
     * Sucht Stationen über den /stations-Endpunkt.
     *
     * @param array<string, mixed> $args Suchparameter, siehe
     *                                   build_query_args() für die
     *                                   erlaubten Schlüssel.
     *
     * @return array|WP_Error Dekodierte API-Antwort
     *                        ({limit, offset, total, result}) oder
     *                        WP_Error bei fehlenden Zugangsdaten,
     *                        Netzwerkfehler oder API-Fehlerantwort.
     */
    public function search_stations( array $args = array() ) {
        $credentials = $this->get_credentials();

        if ( '' === $credentials['client_id'] || '' === $credentials['client_secret'] ) {
            return new WP_Error(
                'db_check_missing_credentials',
                __( 'Es sind noch keine DB-API-Zugangsdaten hinterlegt. Bitte im Plugin-Backend unter Einstellungen eintragen.', 'db-barrierefrei-check' )
            );
        }
        if ( isset( $args['searchstring'] ) && '' !== $args['searchstring'] ) {
            $args['searchstring'] = '*' . trim( $args['searchstring'] ) . '*';
        }
        $query_args = $this->build_query_args( $args );
        $cache_key  = self::CACHE_PREFIX . md5( wp_json_encode( $query_args ) );

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $url = add_query_arg( $query_args, self::API_BASE_URL . '/stations' );

        // Für diesen einen Request auf den PHP-Streams-Transport
        // ausweichen (siehe force_streams_transport_for_this_request()
        // unten) und danach den Filter wieder entfernen, damit der
        // Rest von WordPress' HTTP-Verhalten unangetastet bleibt.
        add_filter( 'http_api_transports', array( $this, 'force_streams_transport_for_this_request' ), 10, 3 );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => array(
                    'DB-Client-ID' => $credentials['client_id'],
                    'DB-Api-Key'   => $credentials['client_secret'],
                    'Accept'       => 'application/json',
                ),
            )
        );

        remove_filter( 'http_api_transports', array( $this, 'force_streams_transport_for_this_request' ), 10 );

        if ( is_wp_error( $response ) ) {
            // Netzwerkfehler (Timeout, DNS, etc.) – nicht cachen, beim
            // nächsten Versuch soll es erneut probiert werden.
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $body        = json_decode( $body_raw, true );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return new WP_Error(
                'db_check_api_error',
                $this->extract_error_message( $body, $status_code ),
                array( 'status' => $status_code )
            );
        }

        if ( ! is_array( $body ) ) {
            return new WP_Error(
                'db_check_invalid_response',
                __( 'Die DB-API hat eine unerwartete Antwort geliefert.', 'db-barrierefrei-check' )
            );
        }

        /**
         * Erlaubt es, die Cache-Dauer für StaDa-API-Antworten zu filtern.
         *
         * @param int   $ttl        Cache-Dauer in Sekunden.
         * @param array $query_args Die tatsächlich gestellte Suchanfrage.
         */
        $ttl = apply_filters( 'db_barrierefrei_check_cache_ttl', self::DEFAULT_CACHE_TTL, $query_args );

        set_transient( $cache_key, $body, $ttl );

        return $body;
    }

    /**
     * Filtert die von WordPress verfügbaren HTTP-Transport-Methoden,
     * aber NUR für Requests an die StaDa-API – erkennbar an der URL.
     *
     * Hintergrund: Auf macOS nutzt PHPs cURL-Erweiterung standardmäßig
     * Apples SecureTransport-TLS-Backend. Das kollidiert bei bestimmten
     * lokalen PHP-FPM-Setups (u.a. Local by WP Engine, Laravel Herd,
     * Valet) mit einem internen fork()-Aufruf und lässt den PHP-Worker-
     * Prozess mit SIGABRT abstürzen ("Crashing instead" im
     * PHP-FPM-Error-Log) – im Browser sichtbar als 502 Bad Gateway.
     * Das ist reines macOS/PHP-FPM-Verhalten, kein Bug im Plugin oder
     * in der DB-API selbst, und betrifft nur bestimmte lokale
     * Entwicklungsumgebungen, nicht produktive Linux-Server.
     *
     * Der PHP-Streams-Transport umgeht SecureTransport komplett und
     * ist von diesem Problem nicht betroffen. Der Filter wird direkt
     * vor und nach dem einzelnen wp_remote_get()-Aufruf an- und
     * abgehängt (siehe search_stations()), damit ausschließlich dieser
     * eine Request betroffen ist – der Rest von WordPress (Plugin-
     * Updates, andere HTTP-Requests) nutzt weiterhin ganz normal cURL.
     *
     * @param string[] $transports Von WordPress vorgeschlagene Transport-Klassen.
     * @param array    $args       Die an wp_remote_get() übergebenen Argumente.
     * @param string   $url        Die angefragte URL.
     *
     * @return string[]
     */
    public function force_streams_transport_for_this_request( array $transports, array $args, string $url ): array {
        if ( 0 !== strpos( $url, self::API_BASE_URL ) ) {
            return $transports;
        }

        return array_values( array_diff( $transports, array( 'curl' ) ) );
    }

    /**
     * Liest und entschlüsselt die gespeicherten API-Zugangsdaten.
     *
     * Gibt bewusst leere Strings statt WP_Error zurück, wenn die
     * Encryption-Klasse noch nicht vorhanden ist oder keine Zugangsdaten
     * hinterlegt sind – die Entscheidung, was das für den Aufrufer
     * bedeutet, liegt bei search_stations().
     *
     * @return array{client_id: string, client_secret: string}
     */
    private function get_credentials(): array {
        $settings = get_option( 'db_barrierefrei_check_settings', array() );

        $client_id     = $settings['client_id'] ?? '';
        $client_secret = $settings['client_secret'] ?? '';

        if ( '' === $client_id || '' === $client_secret ) {
            return array(
                'client_id'     => '',
                'client_secret' => '',
            );
        }

        // Defensive Prüfung: Falls class-encryption.php noch nicht
        // existiert (während der stufenweisen Entwicklung dieses
        // Plugins), Werte unverändert durchreichen, statt fatal
        // abzubrechen. Sobald die Encryption-Klasse steht, greift der
        // Zweig automatisch.
        if ( class_exists( 'DB_Barrierefrei_Check_Encryption' ) ) {
            $client_id     = DB_Barrierefrei_Check_Encryption::decrypt( $client_id );
            $client_secret = DB_Barrierefrei_Check_Encryption::decrypt( $client_secret );
        }

        return array(
            'client_id'     => (string) $client_id,
            'client_secret' => (string) $client_secret,
        );
    }

    /**
     * Filtert die übergebenen Argumente auf die tatsächlich von der
     * StaDa-API unterstützten Query-Parameter und setzt einen sinnvollen
     * Default-Limit, falls keiner übergeben wurde.
     *
     * @param array<string, mixed> $args Rohe, ungeprüfte Eingabe.
     *
     * @return array<string, mixed>
     */
    private function build_query_args( array $args ): array {
        $allowed_keys = array(
            'offset',
            'limit',
            'searchstring',
            'category',
            //'federalstate',
            'eva',
            'ril',
            'logicaloperator',
        );

        $query = array();

        foreach ( $allowed_keys as $key ) {
            if ( isset( $args[ $key ] ) && '' !== $args[ $key ] ) {
                $query[ $key ] = $args[ $key ];
            }
        }

        if ( ! isset( $query['limit'] ) ) {
            // API erlaubt bis zu 10000, aber für eine normale
            // Bahnhofssuche im Frontend ist ein deutlich kleinerer
            // Default sinnvoller (Performance, Übersichtlichkeit).
            $query['limit'] = 50;
        }

        return $query;
    }

    /**
     * Extrahiert eine möglichst sprechende Fehlermeldung aus einer
     * Fehler-Antwort der API (Schema: {errNo, errMsg}), mit Fallback
     * auf den reinen HTTP-Status, falls der Body nicht wie erwartet
     * aussieht.
     *
     * @param mixed $body        Dekodierter Response-Body.
     * @param int   $status_code HTTP-Statuscode.
     */
    private function extract_error_message( $body, int $status_code ): string {
        if ( is_array( $body ) && ! empty( $body['errMsg'] ) ) {
            return sanitize_text_field( (string) $body['errMsg'] );
        }

        return sprintf(
        /* translators: %d: HTTP-Statuscode */
            __( 'Die DB-API antwortete mit Status %d.', 'db-barrierefrei-check' ),
            $status_code
        );
    }
}

