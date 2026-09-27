<?php
/**
 * View: Frontend-Suchformular für den Shortcode [db_barrierefrei_check].
 *
 * Wird eingebunden über DB_Barrierefrei_Check_Shortcode::render().
 * Verfügbare Variablen aus dem Include-Scope: $atts, $nonce_action.
 *
 * Reines Markup-Gerüst – die eigentliche Suche, das Rendern der
 * Ergebniskarten und alle Interaktion übernimmt public/js/frontend.js,
 * basierend auf den über wp_localize_script() bereitgestellten Daten
 * (window.dbCheckAjax: url, nonce, fields, i18n).
 *
 * @package DB_Barrierefrei_Check
 *
 * @var array<string, mixed> $atts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Eindeutige ID-Basis, falls der Shortcode mehrfach auf derselben
// Seite verwendet wird – verhindert doppelte id-Attribute, die sonst
// Labels/aria-Referenzen durcheinanderbringen würden.
$unique_id = wp_unique_id( 'db-check-' );
?>
<section class="db-barrierefrei-check-widget" aria-labelledby="<?php echo esc_attr( $unique_id ); ?>-heading">

    <h2 id="<?php echo esc_attr( $unique_id ); ?>-heading">
        <?php echo esc_html( $atts['ueberschrift'] ); ?>
    </h2>

    <form class="db-check-form" data-db-check-form novalidate>
        <fieldset>
            <legend class="db-check-visually-hidden">
                <?php esc_html_e( 'Suchkriterien', 'db-barrierefrei-check' ); ?>
            </legend>

            <div class="db-check-field">
                <label for="<?php echo esc_attr( $unique_id ); ?>-searchstring">
                    <?php esc_html_e( 'Bahnhofsname', 'db-barrierefrei-check' ); ?>
                </label>
                <input
                        type="text"
                        id="<?php echo esc_attr( $unique_id ); ?>-searchstring"
                        name="searchstring"
                        autocomplete="off"
                        aria-describedby="<?php echo esc_attr( $unique_id ); ?>-error"
                >
            </div>

            <button type="submit" class="db-check-submit">
                <?php esc_html_e( 'Suchen', 'db-barrierefrei-check' ); ?>
            </button>
        </fieldset>

        <p
                class="db-check-error"
                id="<?php echo esc_attr( $unique_id ); ?>-error"
                role="alert"
                hidden
        ></p>
    </form>

    <div class="db-check-status" aria-live="polite" aria-atomic="true">
        <p class="db-check-status-text"></p>
    </div>

    <ul class="db-check-results" aria-busy="false"></ul>

    <button
            type="button"
            class="db-check-load-more"
            data-db-check-load-more
            hidden
    >
        <?php esc_html_e( 'Weitere Ergebnisse laden', 'db-barrierefrei-check' ); ?>
    </button>

</section>