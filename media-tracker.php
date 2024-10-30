<?php
/**
 * Plugin Name: Media Tracker
 * Description: Media Tracker is a WordPress plugin to find and remove unused media files, manage duplicates, and optimize your media library for better performance.
 * Author: TheBitCraft
 * Author URI: https://thebitcraft.com/
 * Version: 1.0.7
 * Requires PHP: 7.4
 * Requires at least: 5.9
 * Tested up to: 6.6.1
 * Text Domain: media-tracker
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
final class Media_Tracker {

    /**
     * Plugin version
     *
     * @var string
     */
    const version = '1.0.7';

    /**
     * Class constructor
     */
    private function __construct() {
        $this->define_constants();
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_script' ) );
        Media_Tracker\Installer::deactivate();
    }

    /**
     * Initialize a singleton instance
     * @return \Media_Tracker
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants() {
        define( 'MEDIA_TRACKER_VERSION', self::version );
        define( 'MEDIA_TRACKER_FILE', __FILE__ );
        define( 'MEDIA_TRACKER_PATH', __DIR__ );
        define( 'MEDIA_TRACKER_URL', plugins_url( '', MEDIA_TRACKER_FILE ) );
        define( 'MEDIA_TRACKER_ASSETS', MEDIA_TRACKER_URL . '/assets' );
        define( 'MEDIA_TRACKER_BASENAME', plugin_basename(__FILE__) );
    }

    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate() {
        $installer = new Media_Tracker\Installer();
        $installer->run();
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {
        new Media_Tracker\Media_Tracker_i18n();

        if ( is_admin() ) {
            new Media_Tracker\Admin();
        }
    }

    /**
     * Register necessary CSS and JS
     * @ Admin
     */
    public function admin_script() {
        wp_enqueue_style( 'mt-admin-style', MEDIA_TRACKER_URL . '/assets/dist/css/mt-admin.css', false, MEDIA_TRACKER_VERSION );
        wp_enqueue_script( 'mt-admin-script', MEDIA_TRACKER_URL . '/assets/dist/js/mt-admin.js', array( 'jquery' ), MEDIA_TRACKER_VERSION, true );
        wp_localize_script( 'mt-admin-script', 'mediaTacker', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mediaTacker_nonce' ),
            'security' => wp_create_nonce('media_tracker_nonce'),
        ));
    }
}

/**
 * Initialize the main plugin
 */
function media_tracker_list() {
    return Media_Tracker::init();
}

// Kick-off the plugin
media_tracker_list();
