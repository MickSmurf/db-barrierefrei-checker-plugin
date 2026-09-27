<?php
/**
 * View: Feld-Auswahl, gruppiert nach Kategorie (Kern/Kontext/Luxus).
 *
 * Wird von admin/views/settings-page.php eingebunden. Erwartet
 * optional eine Variable $settings (aktuelle Plugin-Einstellungen);
 * fällt andernfalls auf DB_Barrierefrei_Check_Field_Registry::get_active_fields()
 * zurück.
 *
 * @package DB_Barrierefrei_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DB_Barrierefrei_Check_Field_Registry' ) ) {
    return;
}

$active_fields = ( isset( $settings ) && is_array( $settings ) && isset( $settings['felder_aktiv'] ) && is_array( $settings['felder_aktiv'] ) )
    ? $settings['felder_aktiv']
    : DB_Barrierefrei_Check_Field_Registry::get_active_fields();

$categories = DB_Barrierefrei_Check_Field_Registry::get_categories();
$option_name = DB_Barrierefrei_Check_Settings::OPTION_NAME;
?>
<fieldset class="db-check-felder-auswahl">
    <legend><?php esc_html_e( 'Angezeigte Informationen', 'db-barrierefrei-check' ); ?></legend>
    <p class="description">
        <?php esc_html_e( 'Wähle aus, welche Informationen im Frontend-Suchergebnis angezeigt werden sollen. "Kern" ist die empfohlene Grundausstattung für ein Barrierefreiheits-Plugin.', 'db-barrierefrei-check' ); ?>
    </p>

    <?php
    foreach ( $categories as $category_key => $category_label ) :
        $fields_in_category = DB_Barrierefrei_Check_Field_Registry::get_fields_by_category( $category_key );

        if ( empty( $fields_in_category ) ) {
            continue;
        }
        ?>
        <fieldset class="db-check-felder-kategorie db-check-felder-kategorie--<?php echo esc_attr( $category_key ); ?>">
            <legend><?php echo esc_html( $category_label ); ?></legend>

            <ul class="db-check-felder-liste">
                <?php
                foreach ( $fields_in_category as $field_key => $field_meta ) :
                    $checkbox_id = 'db_check_feld_' . sanitize_html_class( $field_key );
                    $is_checked  = in_array( $field_key, $active_fields, true );
                    ?>
                    <li>
                        <label for="<?php echo esc_attr( $checkbox_id ); ?>">
                            <input
                                type="checkbox"
                                id="<?php echo esc_attr( $checkbox_id ); ?>"
                                name="<?php echo esc_attr( $option_name ); ?>[felder_aktiv][]"
                                value="<?php echo esc_attr( $field_key ); ?>"
                                <?php checked( $is_checked ); ?>
                            >
                            <?php echo esc_html( $field_meta['label'] ); ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </fieldset>
    <?php endforeach; ?>
</fieldset>

