<?php

/**
 *Plugin Name: DB-Barrierefrei-Check
 *Plugin URI:  https://mikeberg.de/wpplugins
 *Description: Prüft Bahnhöfe der Deutschen Bahn auf Barrierefreiheit anhand der StaDA (Station Data) API. Bietet ein Suchformular per Shortcode sowie ein Admin-Backend zur Konfiguration der API-Zugangsdaten und der angezeigten Informationsfelder.
 *Version: 0.1.0
 *Requires at least: 6.5
 * Tested up to: 7.1
 * Requires PHP: 8.0
 *Author: Mike Berg
 *Author URI: https://mikeberg.de
 * Licence: GPLv2 or later
 * Licence URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: db-barrierefrei-check
 * Domain Path: /languages
 *
 * @package DB-Barrierefrei-Check
 */

// Direkter Zugriff auf die Datei ist nicht erlaubt
if (!defined('ABSPATH')) {
    exit;
}

/*
  * -----------------------------------------------------------------------
  * Konstanten
  * -----------------------------------------------------------------------
  */
define('DB_BARRIEREFREI_CHECK_VERSION', '0.1.0');
define('DB_BARRIEREFREI_CHECK_FILE', __FILE__);
define('DB_BARRIEREFREI_CHECK_PATH', plugin_dir_path(__FILE__));
define('DB_BARRIEREFREI_CHECK_URL', plugin_dir_url(__FILE__));
define('DB_BARRIEREFREI_CHECK_BASENAME', plugin_basename(__FILE__));

/*
 * -----------------------------------------------------------------------
 * Versions-Guards
 * -----------------------------------------------------------------------
 * "Requires PHP" / "Requires at least" im Header oben werden von
 * WordPress selber ausgewertet (seit WP 5.2), verhindern dort aber nur
 * die AKtivierung über das normale Plugin-Formular. Als zusätzliche
 * Absicherung (z.B. bei WP-CLI-Aktivierung oder direktem Zugriff) prüfen
 * wir hier defensiv nochmal selbst und brechen kontrolliert ab, statt
 * einen Fatal Error zu erzeugen.
 */

/**
 * Prüft, ob die Laufzeitumgebung die Mindestanforderungen erfüllt.
 *
 * @return bool
 */
function db_barrierefrei_check_environment_ok()
{
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        return false;
    }

    global $wp_version;
    if(isset($wp_version) && version_compare($wp_version, '6.5', '<')){
        return false;
    }
    return true;
}

/**
 * Zeigt einen Admin-Hinweis, falls die Mindestanforderungen nicht erfüllt sind.
 */
function db_barrierefrei_check_environment_notice()
{ ?>
    <div class="notice notice-error">
        <p>
        <?php printf(esc_html__('DB Barrierefrei-Check benötigt mindestens PHP %1$s. Aktuell installiert ist PHP %2$s. Das Plugin wurde automatisch deaktiviert.','db-barrierefreu-check'),'8.0',esc_html(PHP_VERSION)); ?>
        </p>
    </div>
<?php
    if (isset ($_GET['activate'])) {
    unset($_GET['activate']);

    }
}

// Wenn die Umgebung nicht passt: Hinweis zeigen, Plugin deaktivieren, nicht weiterladen.
    if ( !db_barrierefrei_check_environment_ok()){
       add_action('admin_notices', 'db_barrierefrei_check_environment_notice');

       add_action('admin_init' ,
           function() {
               deactivate_plugins(plugin_basename(__FILE__));
           }
           );

       return;
    }


/*
 * -----------------------------------------------------------------------
 * Autoloading der Plugin-Klassen
 * -----------------------------------------------------------------------
 * Bewusst ein einfacher, manueller Require-Ansatz statt Composer Autoloader,
 * damit das Plugin ohne zusätzlichen Build-Schritt läuft.
 * Die referenzierten Klassen werden in der richtigen Reihenfolge geladen.
 */
    function db_barrierfrei_check_load_includes() {
        $includes = array(
                'includes/class-field-registry.php',
                'includes/class-encryption.php',
                'includes/class-db-api-client.php',
                'includes/class-shortcode.php',
                'includes/class-ajax-handler.php',
        );

        foreach ($includes as $relative_path) {
            $file= DB_BARRIEREFREI_CHECK_PATH . $relative_path;
            if (file_exists($file)) {
                require_once $file;
            }
        }
        // Admin-Klassen nur im Backend laden, spart etwas ladezeit im Frontend.
        if (is_admin()) {
            $admin_includes = array(
              'admin/class-admin-menu.php',
              'admin/class-settings.php',
            );

            foreach ( $admin_includes as $relative_path) {
                $file= DB_BARRIEREFREI_CHECK_PATH . $relative_path;
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }
    }
    add_action('plugins_loaded', 'db_barrierfrei_check_load_includes');

    /*
     * -----------------------------------------------------------------------
     * Übersetzungen laden
     * -----------------------------------------------------------------------
     * Hinweis: Seit WP 4.6 lädt WordPress Übersetzungen für Plugins aus dem
     * Wordpress.org-Verzeichnis automatisch. load_plugin_textdomain() bleibt
     * trotzdem sinnvoll für selbst vertriebene /nicht auf wordpress.org
     * gehostete Plugins wie dieses.
     */

        function db_barrierefrei_check_load_textdomain() {
            load_plugin_textdomain( 'db-barrierefrei-check', false, dirname(DB_BARRIEREFREI_CHECK_BASENAME ) . '/languages/' );
        }
        add_action('init', 'db_barrierefrei_check_load_textdomain');

        /*
         * -----------------------------------------------------------------------
         * Activation / Deactivation
         * -----------------------------------------------------------------------
         * Die eigentliche Logik lebt in includes/class-activator.php. Hier nur
         * die Registrierung der Hooks, wie von WordPress verlangt,
         */
            function db_barrierefrei_check_activate(bool $network_wide= false): void {
                $activator_file= DB_BARRIEREFREI_CHECK_PATH . 'includes/class-activator.php';
                if ( file_exists( $activator_file ) ) {
                    require_once $activator_file;
                    if ( class_exists( 'DB_Barrierefrei_Check_Activator' ) ) {
                        DB_Barrierefrei_Check_Activator::activate();
                    }
                }
            }
            register_activation_hook( __FILE__, 'db_barrierefrei_check_activate' );

            function db_barrierefrei_check_deactivate(bool $network_wide= false): void {
                $deactivator_file= DB_BARRIEREFREI_CHECK_PATH . 'includes/class-deactivator.php';
                if ( file_exists( $deactivator_file ) ) {
                    require_once $deactivator_file;
                    if ( class_exists( 'DB_Barrierefrei_Check_Deactivator' ) ) {
                        DB_Barrierefrei_Check_Deactivator::deactivate();
                    }
                }
            }
            register_deactivation_hook( __FILE__, 'db_barrierefrei_check_deactivate' );