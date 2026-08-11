<?php
/**
 * Plugin Name:       Fanaloka Maintenance Manager
 * Plugin URI:        https://fanaloka.com
 * Description:       Mengubah email menjadi maintenance ticket untuk klien website.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Fanaloka
 * Author URI:        https://fanaloka.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fanaloka-maintenance
 * Domain Path:       /languages
 */

namespace Fanaloka\Maintenance;

use Fanaloka\Maintenance\Email\EmailParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FM_VERSION', '1.0.3' );
define( 'FM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class.
 */
final class Plugin {

    /**
     * Single instance of the class.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Get single instance.
     *
     * @return Plugin
     */
    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files.
     *
     * @return void
     */
    private function load_dependencies(): void {
        require_once FM_PLUGIN_DIR . 'includes/class-autoloader.php';
        Autoloader::init();
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

        add_action( 'init', [ $this, 'init' ] );
        add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );

        // Register custom cron intervals.
        add_filter( 'cron_schedules', [ $this, 'add_cron_interval' ] );
    }

    /**
     * Add custom cron interval.
     *
     * @param array<int, array{display: string, interval: int}> $schedules Existing intervals.
     * @return array<int, array{display: string, interval: int}>
     */
    public function add_cron_interval( array $schedules ): array {
        $minutes = max( 1, absint( get_option( 'fm_sync_interval', 5 ) ) );

        $schedules[ 'fm_' . $minutes . '_min' ] = [
            'display'  => sprintf(
                /* translators: %d: number of minutes */
                __( 'Every %d Minutes (Fanaloka)', 'fanaloka-maintenance' ),
                $minutes
            ),
            'interval' => $minutes * 60,
        ];

        return $schedules;
    }

    /**
     * Run on plugin activation.
     *
     * @return void
     */
    public function activate(): void {
        // Set default options.
        $defaults = [
            'imap_host'     => '',
            'imap_port'     => '993',
            'imap_ssl'      => 'ssl',
            'imap_username' => '',
            'imap_password' => '',
            'sync_interval' => 5,
            'ticket_prefix' => 'REQ',
        ];

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( 'fm_' . $key ) ) {
                update_option( 'fm_' . $key, $value );
            }
        }

        // Set default ignore settings (only if not already set).
        if ( false === get_option( 'fm_ignore_local_domain' ) ) {
            update_option( 'fm_ignore_local_domain', 'fanaloka.co' );
        }

        if ( false === get_option( 'fm_ignore_domains' ) ) {
            $default_domains = implode( "\n", EmailParser::DEFAULT_IGNORED_DOMAINS );
            update_option( 'fm_ignore_domains', $default_domains );
        }

        if ( false === get_option( 'fm_ignore_sender_prefixes' ) ) {
            $default_prefixes = implode( "\n", EmailParser::DEFAULT_IGNORED_PREFIXES );
            update_option( 'fm_ignore_sender_prefixes', $default_prefixes );
        }

        // Create database tables.
        Database::maybe_create_tables();

        // Migrate old conversations if needed.
        Database::migrate_old_conversations();

        // Register CPT and flush rewrite rules.
        $this->register_post_types();
        flush_rewrite_rules();
    }

    /**
     * Run on plugin deactivation.
     *
     * @return void
     */
    public function deactivate(): void {
        // Clear scheduled cron event.
        $timestamp = wp_next_scheduled( 'fm_sync_emails' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'fm_sync_emails' );
        }

        flush_rewrite_rules();
    }

    /**
     * Run on init hook.
     *
     * @return void
     */
    public function init(): void {
        $this->register_post_types();
        $this->register_taxonomies();
    }

    /**
     * Run on plugins_loaded hook.
     *
     * @return void
     */
    public function plugins_loaded(): void {
        // Load text domain for translations.
        load_plugin_textdomain(
            'fanaloka-maintenance',
            false,
            dirname( FM_PLUGIN_BASENAME ) . '/languages'
        );

        // Create/update database tables on every load.
        Database::maybe_create_tables();

        // Initialize components.
        if ( is_admin() ) {
            Admin\Admin::instance();
        }

        Cron\CronManager::instance();
        PublicArea\Frontend::instance();
    }

    /**
     * Register Custom Post Types.
     *
     * @return void
     */
    private function register_post_types(): void {
        register_post_type( 'maintenance_request', [
            'labels'       => [
                'name'               => __( 'Maintenance Requests', 'fanaloka-maintenance' ),
                'singular_name'      => __( 'Maintenance Request', 'fanaloka-maintenance' ),
                'add_new_item'       => __( 'Add New Request', 'fanaloka-maintenance' ),
                'edit_item'          => __( 'Edit Request', 'fanaloka-maintenance' ),
                'view_item'          => __( 'View Request', 'fanaloka-maintenance' ),
                'search_items'       => __( 'Search Requests', 'fanaloka-maintenance' ),
                'not_found'          => __( 'No requests found', 'fanaloka-maintenance' ),
                'not_found_in_trash' => __( 'No requests found in Trash', 'fanaloka-maintenance' ),
            ],
            'public'       => false,
            'show_ui'      => false,
            'show_in_menu' => false,
            'supports'     => [ 'title', 'editor' ],
            'has_archive'  => false,
            'rewrite'      => false,
        ] );
    }

    /**
     * Register Custom Taxonomies.
     *
     * @return void
     */
    private function register_taxonomies(): void {
        // No custom taxonomies needed for now.
    }
}

// Initialize the plugin.
Plugin::instance();
