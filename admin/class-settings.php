<?php
/**
 * Settings-Registrierung und Sanitizing für DB Barrierefrei-Check.
 *
 * @package DB_Barrierefrei_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registriert die Settings API für den zentralen Options-Eintrag und
 * kümmert sich um Sanitizing + Verschlüsselung beim Speichern.
 *
 * Wichtiges Verhalten (Passwort-Feld-Muster: Client-ID/-Secret werden im Formular nie im Klartext
 * zurückgegeben. Lässt der Nutzer ein Feld beim Speichern leer, bleibt
 * der zuvor gespeicherte (verschlüsselte) Wert unverändert bestehen,
 * statt überschrieben/gelöscht zu werden.
 */
class DB_Barrierefrei_Check_Settings {

    /**
     * Name des Options-Eintrags. Muss exakt mit uninstall.php,
     * class-activator.php und class-field-registry.php übereinstimmen.
     */
    public const OPTION_NAME = 'db_barrierefrei_check_settings';

    /**
     * Name der Settings-Gruppe für settings_fields() im Formular.
     */
    public const OPTION_GROUP = 'db_barrierefrei_check_settings_group';

    public static function init(): void {
        add_action( 'admin_init', array( self::class, 'register' ) );
    }

    public static function register(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( self::class, 'sanitize' ),
                'default'           => array(
                    'client_id'     => '',
                    'client_secret' => '',
                    'felder_aktiv'  => array(),
                ),
            )
        );
    }

    /**
     * Sanitize-/Verschlüsselungs-Callback für den gesamten
     * Settings-Array. Wird von WordPress automatisch beim Absenden des
     * Formulars über options.php aufgerufen.
     *
     * @param mixed $input Rohe Eingabe aus $_POST (von WordPress bereits
     *                     als Array für dieses Feld extrahiert).
     *
     * @return array{client_id: string, client_secret: string, felder_aktiv: string[]}
     */
    public static function sanitize( $input ): array {
        if ( ! is_array( $input ) ) {
            $input = array();
        }

        $existing = get_option( self::OPTION_NAME, array() );
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }

        $output = array(
            'client_id'     => self::sanitize_credential( $input['client_id'] ?? '', $existing['client_id'] ?? '' ),
            'client_secret' => self::sanitize_credential( $input['client_secret'] ?? '', $existing['client_secret'] ?? '' ),
            'felder_aktiv'  => self::sanitize_active_fields( $input['felder_aktiv'] ?? array() ),
        );

        add_settings_error(
            self::OPTION_NAME,
            'db_barrierefrei_check_settings_saved',
            __( 'Einstellungen gespeichert.', 'db-barrierefrei-check' ),
            'success'
        );

        return $output;
    }

    /**
     * Verarbeitet ein einzelnes Zugangsdaten-Feld (Client-ID oder
     * -Secret): leere Eingabe → alten (bereits verschlüsselten) Wert
     * behalten; neue Eingabe → bereinigen und verschlüsseln.
     *
     * @param mixed  $raw_input      Rohwert aus dem Formular.
     * @param string $existing_value Bereits gespeicherter, verschlüsselter Wert.
     */
    private static function sanitize_credential( $raw_input, string $existing_value ): string {
        $value = is_string( $raw_input ) ? trim( sanitize_text_field( $raw_input ) ) : '';

        if ( '' === $value ) {
            // Feld leer gelassen: alten Wert unverändert beibehalten.
            return $existing_value;
        }

        if ( class_exists( 'DB_Barrierefrei_Check_Encryption' ) ) {
            return DB_Barrierefrei_Check_Encryption::encrypt( $value );
        }

        // Encryption-Klasse fehlt (sollte im fertigen Plugin nicht
        // vorkommen) – Wert lieber unverschlüsselt speichern als den
        // Speichervorgang komplett abzubrechen, aber im Debug-Log
        // sichtbar machen.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'DB Barrierefrei-Check [Settings]: Encryption-Klasse fehlt, Zugangsdaten wurden unverschlüsselt gespeichert.' );
        }

        return $value;
    }

    /**
     * Validiert die im Formular ausgewählten Felder (Checkboxen) gegen
     * die Field Registry, damit nur tatsächlich bekannte Feld-Schlüssel
     * gespeichert werden können.
     *
     * @param mixed $raw_input Rohwert aus dem Formular (Array von Feld-Keys).
     *
     * @return string[]
     */
    private static function sanitize_active_fields( $raw_input ): array {
        if ( ! is_array( $raw_input ) ) {
            return array();
        }

        $sanitized = array_map( 'sanitize_text_field', $raw_input );

        if ( class_exists( 'DB_Barrierefrei_Check_Field_Registry' ) ) {
            $sanitized = array_filter( $sanitized, array( 'DB_Barrierefrei_Check_Field_Registry', 'is_valid_field' ) );
        }

        return array_values( array_unique( $sanitized ) );
    }
}

DB_Barrierefrei_Check_Settings::init();
