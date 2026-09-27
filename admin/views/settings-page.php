<?php
/**
 * View: Einstellungsseite (API-Zugangsdaten + Feld-Auswahl).
 *
 * Wird eingebunden über DB_Barrierefrei_Check_Admin_Menu::render_settings_page().
 * Erwartet keine externen Variablen, liest die aktuellen Einstellungen
 * selbst über get_option().
 *
 * @package DB_Barrierefrei_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings           = get_option( DB_Barrierefrei_Check_Settings::OPTION_NAME, array() );
$has_client_id      = ! empty( $settings['client_id'] );
$has_client_secret   = ! empty( $settings['client_secret'] );
?>
<div class="wrap db-barrierefrei-check-settings">
    <h1><?php esc_html_e( 'DB Barrierefrei-Check – Einstellungen', 'db-barrierefrei-check' ); ?></h1>

    <?php settings_errors( DB_Barrierefrei_Check_Settings::OPTION_NAME ); ?>

    <form method="post" action="options.php" novalidate="novalidate">
        <?php settings_fields( DB_Barrierefrei_Check_Settings::OPTION_GROUP ); ?>

        <fieldset>
            <legend><?php esc_html_e( 'API-Zugangsdaten', 'db-barrierefrei-check' ); ?></legend>

            <p class="description">
                <?php esc_html_e( 'Zugangsdaten erhältst du nach kostenloser Registrierung im DB API Marketplace.', 'db-barrierefrei-check' ); ?>
                <a href="https://developers.deutschebahn.com/" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Zur Registrierung (öffnet in neuem Tab)', 'db-barrierefrei-check' ); ?>
                </a>
            </p>

            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="db_check_client_id"><?php esc_html_e( 'Client ID', 'db-barrierefrei-check' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="db_check_client_id"
                            name="<?php echo esc_attr( DB_Barrierefrei_Check_Settings::OPTION_NAME ); ?>[client_id]"
                            value=""
                            autocomplete="off"
                            class="regular-text"
                            aria-describedby="db_check_client_id_description"
                            placeholder="<?php echo $has_client_id ? '••••••••••••' : esc_attr__( 'Client ID eingeben', 'db-barrierefrei-check' ); ?>"
                        >
                        <p class="description" id="db_check_client_id_description">
                            <?php
                            echo $has_client_id
                                ? esc_html__( 'Eine Client ID ist bereits hinterlegt. Nur ausfüllen, um sie zu ändern.', 'db-barrierefrei-check' )
                                : esc_html__( 'Wird verschlüsselt gespeichert.', 'db-barrierefrei-check' );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="db_check_client_secret"><?php esc_html_e( 'Client Secret', 'db-barrierefrei-check' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="db_check_client_secret"
                            name="<?php echo esc_attr( DB_Barrierefrei_Check_Settings::OPTION_NAME ); ?>[client_secret]"
                            value=""
                            autocomplete="off"
                            class="regular-text"
                            aria-describedby="db_check_client_secret_description"
                            placeholder="<?php echo $has_client_secret ? '••••••••••••' : esc_attr__( 'Client Secret eingeben', 'db-barrierefrei-check' ); ?>"
                        >
                        <p class="description" id="db_check_client_secret_description">
                            <?php
                            echo $has_client_secret
                                ? esc_html__( 'Ein Client Secret ist bereits hinterlegt. Nur ausfüllen, um es zu ändern.', 'db-barrierefrei-check' )
                                : esc_html__( 'Wird verschlüsselt gespeichert.', 'db-barrierefrei-check' );
                            ?>
                        </p>
                    </td>
                </tr>
                </tbody>
            </table>
        </fieldset>

        <?php
        $felder_template = DB_BARRIEREFREI_CHECK_PATH . 'admin/views/felder-auswahl.php';
        if ( file_exists( $felder_template ) ) {
            // $settings steht dem Template über den gemeinsamen
            // Include-Scope zur Verfügung.
            include $felder_template;
        }
        ?>

        <?php submit_button( __( 'Einstellungen speichern', 'db-barrierefrei-check' ) ); ?>
    </form>
</div>

