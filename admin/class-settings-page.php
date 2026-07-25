<?php
/**
 * Settings Page - Full Implementation.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

use Fanaloka\Maintenance\IMAP\IMAPReader;
use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SettingsPage Class.
 */
class SettingsPage {

    /**
     * Option group name.
     *
     * @var string
     */
    private const OPTION_GROUP = 'fm_settings';

    /**
     * Option prefix.
     *
     * @var string
     */
    private const OPTION_PREFIX = 'fm_';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_fm_test_connection', [ $this, 'ajax_test_connection' ] );
    }

    /**
     * Register all settings.
     *
     * @return void
     */
    public function register_settings(): void {
        $this->register_imap_settings();
        $this->register_sync_settings();
        $this->register_ticket_settings();
        $this->register_notification_settings();
        $this->register_sections_and_fields();
    }

    /**
     * Register IMAP options.
     *
     * @return void
     */
    private function register_imap_settings(): void {
        $fields = [
            'imap_host'     => [ 'type' => 'string', 'default' => '' ],
            'imap_port'     => [ 'type' => 'string', 'default' => '993' ],
            'imap_ssl'      => [ 'type' => 'string', 'default' => 'ssl' ],
            'imap_username' => [ 'type' => 'string', 'default' => '' ],
            'imap_password' => [ 'type' => 'string', 'default' => '' ],
            'imap_folder'   => [ 'type' => 'string', 'default' => 'INBOX' ],
        ];

        foreach ( $fields as $name => $config ) {
            register_setting(
                self::OPTION_GROUP,
                self::OPTION_PREFIX . $name,
                [
                    'type'              => $config['type'],
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => $config['default'],
                ]
            );
        }
    }

    /**
     * Register sync options.
     *
     * @return void
     */
    private function register_sync_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_PREFIX . 'sync_interval',
            [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 5,
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_PREFIX . 'auto_sync',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'yes',
            ]
        );
    }

    /**
     * Register ticket options.
     *
     * @return void
     */
    private function register_ticket_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_PREFIX . 'ticket_prefix',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'REQ',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_PREFIX . 'default_status',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'new',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_PREFIX . 'default_priority',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'medium',
            ]
        );
    }

    /**
     * Register notification options.
     *
     * @return void
     */
    private function register_notification_settings(): void {
        $notifs = [
            'notif_new_ticket'        => 'yes',
            'notif_status_change'     => 'yes',
            'notif_assignment'        => 'yes',
            'notif_ticket_completed'  => 'yes',
        ];

        foreach ( $notifs as $name => $default ) {
            register_setting(
                self::OPTION_GROUP,
                self::OPTION_PREFIX . $name,
                [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => $default,
                ]
            );
        }

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_PREFIX . 'admin_email',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default'           => get_option( 'admin_email' ),
            ]
        );
    }

    /**
     * Register sections and fields.
     *
     * @return void
     */
    private function register_sections_and_fields(): void {
        // IMAP Section.
        add_settings_section(
            'fm_imap_section',
            __( 'IMAP Connection', 'fanaloka-maintenance' ),
            [ $this, 'render_imap_section' ],
            'fm-settings'
        );

        $imap_fields = [
            'fm_imap_host' => [
                'label' => __( 'IMAP server hostname (e.g., imap.gmail.com)', 'fanaloka-maintenance' ),
                'type'  => 'text',
            ],
            'fm_imap_port' => [
                'label' => __( 'IMAP port (default: 993)', 'fanaloka-maintenance' ),
                'type'  => 'number',
            ],
            'fm_imap_ssl' => [
                'label'   => __( 'Encryption type', 'fanaloka-maintenance' ),
                'type'    => 'select',
                'options' => [
                    'ssl'    => __( 'SSL', 'fanaloka-maintenance' ),
                    'tls'    => __( 'TLS', 'fanaloka-maintenance' ),
                    'notls'  => __( 'No TLS', 'fanaloka-maintenance' ),
                ],
            ],
            'fm_imap_username' => [
                'label' => __( 'Email address or username', 'fanaloka-maintenance' ),
                'type'  => 'text',
            ],
            'fm_imap_password' => [
                'label' => __( 'Email password or app password', 'fanaloka-maintenance' ),
                'type'  => 'password',
            ],
            'fm_imap_folder' => [
                'label' => __( 'Mailbox folder (default: INBOX)', 'fanaloka-maintenance' ),
                'type'  => 'text',
            ],
        ];

        foreach ( $imap_fields as $id => $args ) {
            $render = 'select' === $args['type']
                ? [ $this, 'render_select_field' ]
                : [ $this, 'render_text_field' ];

            add_settings_field( $id, $this->field_label( $id ), $render, 'fm-settings', 'fm_imap_section', array_merge( [ 'id' => $id ], $args ) );
        }

        add_settings_field(
            'fm_test_connection',
            __( 'Test Connection', 'fanaloka-maintenance' ),
            [ $this, 'render_test_connection_button' ],
            'fm-settings',
            'fm_imap_section'
        );

        // Sync Section.
        add_settings_section(
            'fm_sync_section',
            __( 'Sync Settings', 'fanaloka-maintenance' ),
            [ $this, 'render_sync_section' ],
            'fm-settings'
        );

        add_settings_field(
            'fm_auto_sync',
            __( 'Auto Sync', 'fanaloka-maintenance' ),
            [ $this, 'render_select_field' ],
            'fm-settings',
            'fm_sync_section',
            [
                'id'      => 'fm_auto_sync',
                'label'   => __( 'Enable automatic email sync', 'fanaloka-maintenance' ),
                'options' => [
                    'yes' => __( 'Enabled', 'fanaloka-maintenance' ),
                    'no'  => __( 'Disabled', 'fanaloka-maintenance' ),
                ],
            ]
        );

        add_settings_field(
            'fm_sync_interval',
            __( 'Sync Interval (minutes)', 'fanaloka-maintenance' ),
            [ $this, 'render_text_field' ],
            'fm-settings',
            'fm_sync_section',
            [
                'id'    => 'fm_sync_interval',
                'label' => __( 'How often to check for new emails (default: 5)', 'fanaloka-maintenance' ),
                'type'  => 'number',
            ]
        );

        // Ticket Section.
        add_settings_section(
            'fm_ticket_section',
            __( 'Ticket Settings', 'fanaloka-maintenance' ),
            [ $this, 'render_ticket_section' ],
            'fm-settings'
        );

        add_settings_field(
            'fm_ticket_prefix',
            __( 'Ticket Prefix', 'fanaloka-maintenance' ),
            [ $this, 'render_text_field' ],
            'fm-settings',
            'fm_ticket_section',
            [
                'id'    => 'fm_ticket_prefix',
                'label' => __( 'Prefix for ticket numbers (e.g., REQ)', 'fanaloka-maintenance' ),
                'type'  => 'text',
            ]
        );

        add_settings_field(
            'fm_default_status',
            __( 'Default Status', 'fanaloka-maintenance' ),
            [ $this, 'render_select_field' ],
            'fm-settings',
            'fm_ticket_section',
            [
                'id'      => 'fm_default_status',
                'label'   => __( 'Status for new tickets', 'fanaloka-maintenance' ),
                'options' => [
                    'new'         => __( 'New', 'fanaloka-maintenance' ),
                    'open'        => __( 'Open', 'fanaloka-maintenance' ),
                    'in-progress' => __( 'In Progress', 'fanaloka-maintenance' ),
                ],
            ]
        );

        add_settings_field(
            'fm_default_priority',
            __( 'Default Priority', 'fanaloka-maintenance' ),
            [ $this, 'render_select_field' ],
            'fm-settings',
            'fm_ticket_section',
            [
                'id'      => 'fm_default_priority',
                'label'   => __( 'Priority for new tickets', 'fanaloka-maintenance' ),
                'options' => [
                    'low'      => __( 'Low', 'fanaloka-maintenance' ),
                    'medium'   => __( 'Medium', 'fanaloka-maintenance' ),
                    'high'     => __( 'High', 'fanaloka-maintenance' ),
                    'critical' => __( 'Critical', 'fanaloka-maintenance' ),
                ],
            ]
        );

        // Notification Section.
        add_settings_section(
            'fm_notification_section',
            __( 'Notification Settings', 'fanaloka-maintenance' ),
            [ $this, 'render_notification_section' ],
            'fm-settings'
        );

        add_settings_field(
            'fm_admin_email',
            __( 'Admin Email', 'fanaloka-maintenance' ),
            [ $this, 'render_text_field' ],
            'fm-settings',
            'fm_notification_section',
            [
                'id'    => 'fm_admin_email',
                'label' => __( 'Email address for admin notifications', 'fanaloka-maintenance' ),
                'type'  => 'email',
            ]
        );

        $notif_fields = [
            'fm_notif_new_ticket'       => __( 'Notify when new ticket is created', 'fanaloka-maintenance' ),
            'fm_notif_status_change'    => __( 'Notify when ticket status changes', 'fanaloka-maintenance' ),
            'fm_notif_assignment'       => __( 'Notify when developer is assigned', 'fanaloka-maintenance' ),
            'fm_notif_ticket_completed' => __( 'Notify when ticket is completed', 'fanaloka-maintenance' ),
        ];

        $yes_no = [
            'yes' => __( 'Enabled', 'fanaloka-maintenance' ),
            'no'  => __( 'Disabled', 'fanaloka-maintenance' ),
        ];

        foreach ( $notif_fields as $id => $label ) {
            add_settings_field(
                $id,
                $this->field_label( $id ),
                [ $this, 'render_select_field' ],
                'fm-settings',
                'fm_notification_section',
                [
                    'id'      => $id,
                    'label'   => $label,
                    'options' => $yes_no,
                ]
            );
        }
    }

    /**
     * Get human-readable label from field ID.
     *
     * @param string $id Field ID.
     * @return string
     */
    private function field_label( string $id ): string {
        $labels = [
            'fm_imap_host'             => __( 'Host', 'fanaloka-maintenance' ),
            'fm_imap_port'             => __( 'Port', 'fanaloka-maintenance' ),
            'fm_imap_ssl'              => __( 'SSL', 'fanaloka-maintenance' ),
            'fm_imap_username'         => __( 'Username', 'fanaloka-maintenance' ),
            'fm_imap_password'         => __( 'Password', 'fanaloka-maintenance' ),
            'fm_imap_folder'           => __( 'Folder', 'fanaloka-maintenance' ),
            'fm_notif_new_ticket'      => __( 'New Ticket', 'fanaloka-maintenance' ),
            'fm_notif_status_change'   => __( 'Status Change', 'fanaloka-maintenance' ),
            'fm_notif_assignment'      => __( 'Developer Assignment', 'fanaloka-maintenance' ),
            'fm_notif_ticket_completed' => __( 'Ticket Completed', 'fanaloka-maintenance' ),
        ];

        return $labels[ $id ] ?? $id;
    }

    /**
     * Render IMAP section description.
     *
     * @return void
     */
    public function render_imap_section(): void {
        echo '<p>' . esc_html__( 'Configure your IMAP email connection settings.', 'fanaloka-maintenance' ) . '</p>';
    }

    /**
     * Render sync section description.
     *
     * @return void
     */
    public function render_sync_section(): void {
        echo '<p>' . esc_html__( 'Configure how often the plugin checks for new emails.', 'fanaloka-maintenance' ) . '</p>';
    }

    /**
     * Render ticket section description.
     *
     * @return void
     */
    public function render_ticket_section(): void {
        echo '<p>' . esc_html__( 'Default settings for new maintenance tickets.', 'fanaloka-maintenance' ) . '</p>';
    }

    /**
     * Render notification section description.
     *
     * @return void
     */
    public function render_notification_section(): void {
        echo '<p>' . esc_html__( 'Configure email notification preferences.', 'fanaloka-maintenance' ) . '</p>';
    }

    /**
     * Render text field.
     *
     * @param array<string, string> $args Field args.
     * @return void
     */
    public function render_text_field( array $args ): void {
        $id    = $args['id'] ?? '';
        $label = $args['label'] ?? '';
        $type  = $args['type'] ?? 'text';
        $value = get_option( $id, '' );

        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr( $type ),
            esc_attr( $id ),
            esc_attr( $id ),
            esc_attr( $value )
        );

        if ( $label ) {
            printf( '<p class="description">%s</p>', esc_html( $label ) );
        }
    }

    /**
     * Render select field.
     *
     * @param array<string, mixed> $args Field args.
     * @return void
     */
    public function render_select_field( array $args ): void {
        $id      = $args['id'] ?? '';
        $label   = $args['label'] ?? '';
        $options = $args['options'] ?? [];
        $value   = get_option( $id, '' );

        printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $id ) );

        foreach ( $options as $opt_val => $opt_label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $opt_val ),
                selected( $value, $opt_val, false ),
                esc_html( $opt_label )
            );
        }

        echo '</select>';

        if ( $label ) {
            printf( '<p class="description">%s</p>', esc_html( $label ) );
        }
    }

    /**
     * Render test connection button.
     *
     * @return void
     */
    public function render_test_connection_button(): void {
        printf(
            '<button type="button" class="button fm-btn-test-connection" id="fm-test-connection">%s</button> ',
            esc_html__( 'Test Connection', 'fanaloka-maintenance' )
        );
        echo '<span id="fm-test-result"></span>';
    }

    /**
     * AJAX test IMAP connection.
     *
     * @return void
     */
    public function ajax_test_connection(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [
                'message' => __( 'Permission denied.', 'fanaloka-maintenance' ),
            ] );
        }

        $reader = new IMAPReader();
        $result = $reader->test_connection();

        if ( $result['success'] ) {
            Logger::log( 'IMAP connection test successful' );
            wp_send_json_success( [
                'message' => __( 'Connection successful!', 'fanaloka-maintenance' ),
            ] );
        } else {
            Logger::log( 'IMAP connection test failed: ' . $result['message'], Logger::LEVEL_ERROR );
            wp_send_json_error( [
                'message' => $result['message'],
            ] );
        }
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $saved = isset( $_GET['settings-updated'] );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings saved.', 'fanaloka-maintenance' ); ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=fm-settings&tab=general"
                   class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'IMAP Connection', 'fanaloka-maintenance' ); ?>
                </a>
                <a href="?page=fm-settings&tab=sync"
                   class="nav-tab <?php echo 'sync' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Sync', 'fanaloka-maintenance' ); ?>
                </a>
                <a href="?page=fm-settings&tab=ticket"
                   class="nav-tab <?php echo 'ticket' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Ticket', 'fanaloka-maintenance' ); ?>
                </a>
                <a href="?page=fm-settings&tab=notification"
                   class="nav-tab <?php echo 'notification' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Notifications', 'fanaloka-maintenance' ); ?>
                </a>
            </nav>

            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );

                if ( 'general' === $tab ) {
                    do_settings_sections( 'fm-settings' );
                } elseif ( 'sync' === $tab ) {
                    $this->render_tab_sections( [ 'fm_sync_section' ] );
                } elseif ( 'ticket' === $tab ) {
                    $this->render_tab_sections( [ 'fm_ticket_section' ] );
                } elseif ( 'notification' === $tab ) {
                    $this->render_tab_sections( [ 'fm_notification_section' ] );
                }

                submit_button( __( 'Save Settings', 'fanaloka-maintenance' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render specific sections only.
     *
     * @param array<int, string> $section_ids Section IDs to render.
     * @return void
     */
    private function render_tab_sections( array $section_ids ): void {
        global $wp_settings_sections, $wp_settings_fields;

        if ( ! isset( $wp_settings_sections['fm-settings'] ) ) {
            return;
        }

        foreach ( $section_ids as $section_id ) {
            if ( ! isset( $wp_settings_sections['fm-settings'][ $section_id ] ) ) {
                continue;
            }

            $section = $wp_settings_sections['fm-settings'][ $section_id ];
            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';

            if ( $section['callback'] ) {
                call_user_func( $section['callback'], $section );
            }

            if ( ! isset( $wp_settings_fields['fm-settings'][ $section_id ] ) ) {
                continue;
            }

            echo '<table class="form-table">';

            foreach ( $wp_settings_fields['fm-settings'][ $section_id ] as $field ) {
                echo '<tr>';
                echo '<th scope="row">' . esc_html( $field['title'] ) . '</th>';
                echo '<td>';
                call_user_func( $field['callback'], $field['args'] );
                echo '</td>';
                echo '</tr>';
            }

            echo '</table>';
        }
    }
}
