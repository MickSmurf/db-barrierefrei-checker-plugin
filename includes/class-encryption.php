<?php

/**
 * Ver-/Entschlüsselung sensibler Plugin-Daten (API-Zugangsdaten).
 *
 * @package DB_Barrierefrei_Check
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schützt die in wp_options gespeicherten DB-API-Zugangsdaten vor
 * Klartext-Auslesbarkeit (z.B. bei DB-Dumps, SQL-Injection mit reinem
 * Lesezugriff, oder versehentlich mitgeteilten DB-Backups).
 *
 * Einordnung, wichtig zu wissen: Der Schlüssel selbst leitet sich aus
 * AUTH_KEY (wp-config.php) und wp_salt() ab. Wer vollen Server-Zugriff
 * hat, kommt damit auch an wp-config.php und könnte theoretisch
 * entschlüsseln – das ist eine bewusste, für WordPress-Plugins übliche
 * Abwägung, kein Schutz gegen einen vollständig kompromittierten
 * Server, sondern gegen isolierten Datenbank-Zugriff.
 */
class DB_Barrierefrei_Check_Encryption
{

    /**
     * Verschlüsselungsverfahren. AES-256-CBC ist breit verfügbar
     * (Standard-OpenSSL-Build) und für diesen Anwendungsfall
     * (kurze Strings, kein High-Throughput) völlig ausreichend.
     */
    private const CIPHER_METHOD = 'aes-256-cbc';

    /**
     * Verschlüsselt einen Klartext-String.
     *
     * Pro Aufruf wird ein neuer, zufälliger Initialisierungsvektor (IV)
     * erzeugt und dem Chiffrat vorangestellt (nicht aus wp_salt()
     * abgeleitet) – das ist die kryptographisch korrekte Vorgehensweise
     * bei CBC-Mode und verhindert, dass zwei identische Klartexte
     * (z.B. zwei Installationen mit demselben Test-Key) zum selben
     * Chiffrat führen.
     *
     * @param string $value Klartext, z.B. der DB-API Client Secret.
     *
     * @return string Base64-kodiertes (IV + Chiffrat), oder leerer
     *                String bei leerer Eingabe oder Verschlüsselungsfehler.
     */
    public static function encrypt(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        if (!self::is_available()) {
            self::log_error('OpenSSL-Erweiterung oder AES-256-CBC nicht verfügbar – Verschlüsselung nicht möglich.');
            return '';
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $ciphertext = openssl_encrypt(
            $value,
            self::CIPHER_METHOD,
            self::get_key(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if (false === $ciphertext) {
            self::log_error('openssl_encrypt() ist fehlgeschlagen.');
            return '';
        }

        return base64_encode($iv . $ciphertext); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    /**
     * Entschlüsselt einen zuvor mit encrypt() erzeugten String.
     *
     * Gibt bewusst einen leeren String zurück statt eine Exception zu
     * werfen, falls etwas nicht stimmt (falscher Key nach Server-Umzug,
     * korrupter Wert, etc.) – der Aufrufer (z.B. der API-Client)
     * behandelt einen leeren String bereits als "keine Zugangsdaten
     * vorhanden" und reagiert entsprechend kontrolliert, statt dass das
     * Plugin abstürzt.
     *
     * @param string $value Base64-kodiertes (IV + Chiffrat) aus encrypt().
     *
     * @return string Klartext, oder leerer String bei Fehler.
     */
    public static function decrypt(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        if (!self::is_available()) {
            self::log_error('OpenSSL-Erweiterung oder AES-256-CBC nicht verfügbar – Entschlüsselung nicht möglich.');
            return '';
        }

        $raw = base64_decode($value, true);
        if (false === $raw) {
            self::log_error('Gespeicherter Wert ist kein gültiges Base64 – möglicherweise korrupt oder unverschlüsselt.');
            return '';
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);

        if (strlen($raw) <= $iv_length) {
            self::log_error('Gespeicherter Wert ist zu kurz, um IV + Chiffrat zu enthalten.');
            return '';
        }

        $iv = substr($raw, 0, $iv_length);
        $ciphertext = substr($raw, $iv_length);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER_METHOD,
            self::get_key(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if (false === $plaintext) {
            self::log_error('openssl_decrypt() ist fehlgeschlagen (falscher Schlüssel oder korrupte Daten?).');
            return '';
        }

        return $plaintext;
    }

    /**
     * Prüft, ob die Laufzeitumgebung Verschlüsselung überhaupt
     * unterstützt. Praktisch auf jedem Hosting-Anbieter gegeben, aber
     * exotische Minimal-PHP-Builds ohne OpenSSL-Erweiterung existieren.
     */
    public static function is_available(): bool
    {
        return extension_loaded('openssl')
            && in_array(self::CIPHER_METHOD, openssl_get_cipher_methods(), true);
    }

    /**
     * Leitet einen 256-Bit-Schlüssel aus AUTH_KEY (wp-config.php) und
     * wp_salt() ab. Beide Werte sind pro WordPress-Installation
     * einzigartig und werden nicht in der Datenbank gespeichert.
     */
    private static function get_key(): string
    {
        $auth_key = defined('AUTH_KEY') ? AUTH_KEY : '';
        $material = $auth_key . wp_salt('auth');

        // Raw-Binary-Output (true als dritter Parameter), da
        // openssl_encrypt/decrypt für AES-256 exakt 32 Byte erwartet –
        // ein Hex-String wäre 64 Zeichen und damit falsch dimensioniert.
        return hash('sha256', $material, true);
    }

    /**
     * Loggt einen Fehler, aber nur wenn WP_DEBUG aktiv ist – im
     * Produktivbetrieb sollen solche Details nicht in allgemein
     * zugänglichen Logs landen (könnten Rückschlüsse auf das
     * Verschlüsselungs-Setup erlauben).
     */
    private static function log_error(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('DB Barrierefrei-Check [Encryption]: ' . $message);
        }
    }
}