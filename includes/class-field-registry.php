<?php

/**
 * Zentrale Definition aller anzeigbaren Stations-Felder.
 *
 * @package DB_Barrierefrei_Check
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Single Source of Truth für alle Station-Felder, die dieses Plugin
 * anzeigen kann: Kategorie-Zuordnung (Kern/Kontext/Luxus), Datentyp und
 * deutsches Label. Sowohl die Admin-Checkbox-Auswahl als auch die
 * Filterung der Frontend-Antwort lesen ausschließlich von hier – Felder
 * werden nur an dieser einen Stelle gepflegt.
 *
 * Kategorie-Zuordnung und Begründung entsprechen der gemeinsam
 * erarbeiteten Einteilung (siehe Planungs-Verlauf):
 * - kern:    Barrierefreiheit direkt betroffen
 * - kontext: mobilitätsrelevant, aber indirekt / Backup-Funktion
 * - luxus:   allgemeine Ausstattung ohne Mobilitäts-/Gesundheitsbezug
 */
class DB_Barrierefrei_Check_Field_Registry
{

    /**
     * Basis-Felder, die IMMER Teil der Antwort sind, unabhängig von
     * der Admin-Auswahl – ohne Name und Bundesland wäre ein
     * Suchergebnis nicht sinnvoll identifizierbar/darstellbar.
     *
     * @return string[]
     */
    public static function get_always_included_fields(): array
    {
        return array('name', 'federalState', 'number');
    }

    /**
     * Die vollständige Feld-Definition: Schlüssel exakt wie im
     * Station-Schema der StaDa-API, plus Kategorie, Typ und Label.
     *
     * @return array<string, array{label: string, kategorie: string, typ: string}>
     */
    public static function get_all_fields(): array
    {
        return array(
            // ---- Kategorie 1: Kern (Barrierefreiheit direkt) ----
            'hasSteplessAccess' => array(
                'label' => __('Stufenloser Zugang', 'db-barrierefrei-check'),
                'kategorie' => 'kern',
                'typ' => 'enum', // yes | no | partial
            ),
            'hasPublicFacilities' => array(
                'label' => __('Öffentliche Einrichtungen (z.B. WC)', 'db-barrierefrei-check'),
                'kategorie' => 'kern',
                'typ' => 'boolean',
            ),
            'hasMobilityService' => array(
                'label' => __('Mobilitätsservice', 'db-barrierefrei-check'),
                'kategorie' => 'kern',
                'typ' => 'string', // Freitext, kein echtes Boolean; Wert kann "no" sein
            ),
            'mobilityServiceStaff' => array(
                'label' => __('Details zum Mobilitätsservice', 'db-barrierefrei-check'),
                'kategorie' => 'kern',
                'typ' => 'object', // meetingPoint, staffOnSite, serviceOnBehalf, availability
            ),
            'localServiceStaff' => array(
                'label' => __('Personal vor Ort (Öffnungszeiten)', 'db-barrierefrei-check'),
                'kategorie' => 'kern',
                'typ' => 'object', // Schedule
            ),
            'hasTaxiRank' => array(
                'label' => __('Taxistand', 'db-barrierefrei-check'),
                'kategorie' => 'kern',
                'typ' => 'boolean',
            ),

            // ---- Kategorie 2: Kontextrelevant ----
            'hasLocalPublicTransport' => array(
                'label' => __('ÖPNV-Anschluss vorhanden', 'db-barrierefrei-check'),
                'kategorie' => 'kontext',
                'typ' => 'boolean',
            ),
            'category' => array(
                'label' => __('Bahnhofskategorie', 'db-barrierefrei-check'),
                'kategorie' => 'kontext',
                'typ' => 'integer', // 1 (groß) bis 7 (klein)
            ),
            'DBinformation' => array(
                'label' => __('Öffnungszeiten Reisezentrum/Information', 'db-barrierefrei-check'),
                'kategorie' => 'kontext',
                'typ' => 'object', // Schedule
            ),
            'hasParking' => array(
                'label' => __('Parkplätze', 'db-barrierefrei-check'),
                'kategorie' => 'kontext',
                'typ' => 'boolean',
            ),
            'hasRailwayMission' => array(
                'label' => __('Bahnhofsmission', 'db-barrierefrei-check'),
                'kategorie' => 'kontext',
                'typ' => 'boolean',
            ),
            'hasTravelCenter' => array(
                'label' => __('Reisezentrum', 'db-barrierefrei-check'),
                'kategorie' => 'kontext',
                'typ' => 'boolean',
            ),
            'hasTravelNecessities' => array(
                'label' => __('Kiosk / Reisebedarf', 'db-barrierefrei-check'),
                'kategorie' => 'kontext',
                'typ' => 'boolean',
            ),

            // ---- Kategorie 3: Luxus / allgemeine Ausstattung ----
            'hasWiFi' => array(
                'label' => __('WLAN', 'db-barrierefrei-check'),
                'kategorie' => 'luxus',
                'typ' => 'boolean',
            ),
            'hasDBLounge' => array(
                'label' => __('DB Lounge', 'db-barrierefrei-check'),
                'kategorie' => 'luxus',
                'typ' => 'boolean',
            ),
            'hasBicycleParking' => array(
                'label' => __('Fahrradstellplätze', 'db-barrierefrei-check'),
                'kategorie' => 'luxus',
                'typ' => 'boolean',
            ),
            'hasCarRental' => array(
                'label' => __('Mietwagen', 'db-barrierefrei-check'),
                'kategorie' => 'luxus',
                'typ' => 'boolean',
            ),
            'hasLostAndFound' => array(
                'label' => __('Fundbüro', 'db-barrierefrei-check'),
                'kategorie' => 'luxus',
                'typ' => 'boolean',
            ),
            'hasLockerSystem' => array(
                'label' => __('Schließfächer', 'db-barrierefrei-check'),
                'kategorie' => 'luxus',
                'typ' => 'boolean',
            ),
        );
    }

    /**
     * Liefert die verfügbaren Kategorien mit sprechendem Label, in der
     * Reihenfolge, in der sie im Admin-Formular gruppiert angezeigt
     * werden sollen.
     *
     * @return array<string, string>
     */
    public static function get_categories(): array
    {
        return array(
            'kern' => __('Kern – Barrierefreiheit direkt', 'db-barrierefrei-check'),
            'kontext' => __('Kontextrelevant für mobilitätseingeschränkte Reisende', 'db-barrierefrei-check'),
            'luxus' => __('Allgemeine Ausstattung', 'db-barrierefrei-check'),
        );
    }

    /**
     * Alle Felder einer bestimmten Kategorie.
     *
     * @param string $category 'kern' | 'kontext' | 'luxus'.
     *
     * @return array<string, array{label: string, kategorie: string, typ: string}>
     */
    public static function get_fields_by_category(string $category): array
    {
        return array_filter(
            self::get_all_fields(),
            static fn(array $field): bool => $field['kategorie'] === $category
        );
    }

    /**
     * Prüft, ob ein Feld-Schlüssel in der Registry existiert – wichtig
     * beim Speichern der Admin-Auswahl, um zu verhindern, dass
     * beliebige, nicht vorgesehene Werte in die Settings gelangen.
     */
    public static function is_valid_field(string $key): bool
    {
        return array_key_exists($key, self::get_all_fields());
    }

    /**
     * Liest die im Backend aktivierten Felder aus den Plugin-Settings
     * und bereinigt sie gegen die Registry (falls sich z.B. durch ein
     * Update Feld-Namen geändert haben, fliegen ungültige Einträge
     * automatisch raus, statt stillschweigend mitgeschleppt zu werden).
     *
     * @return string[]
     */
    public static function get_active_fields(): array
    {
        $settings = get_option('db_barrierefrei_check_settings', array());
        $active = $settings['felder_aktiv'] ?? array();

        if (!is_array($active)) {
            return array();
        }

        return array_values(array_filter($active, array(self::class, 'is_valid_field')));
    }

    /**
     * Reduziert ein einzelnes Station-Objekt (aus der StaDa-API-Antwort)
     * auf die Basis-Felder plus die aktuell aktivierten Felder. Wird vom
     * AJAX-Handler auf jedes Element von `result` angewendet, bevor die
     * Antwort ans Frontend geht.
     *
     * @param array<string, mixed> $station Rohes Station-Objekt.
     * @param string[]|null $active_fields Optional vorgegebene
     *                                             Feldliste, sonst wird
     *                                             get_active_fields()
     *                                             verwendet.
     *
     * @return array<string, mixed>
     */
    public static function filter_station_data(array $station, ?array $active_fields = null): array
    {
        if (null === $active_fields) {
            $active_fields = self::get_active_fields();
        }

        $allowed_keys = array_merge(self::get_always_included_fields(), $active_fields);

        return array_intersect_key($station, array_flip($allowed_keys));
    }
}