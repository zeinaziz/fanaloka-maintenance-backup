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
    }

    /**
     * Sanitize value as text.
     *
     * @param mixed $value Input value.
     * @return string
     */
    public static function sanitize_text( $value ): string {
        return is_string( $value ) ? sanitize_text_field( $value ) : '';
    }

    /**
     * Sanitize textarea value (preserve newlines).
     *
     * @param mixed $value Input value.
     * @return string
     */
    public static function sanitize_textarea( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }
        $value = wp_unslash( $value );
        $lines = explode( "\n", $value );
        $lines = array_map( 'sanitize_text_field', $lines );
        return implode( "\n", $lines );
    }

    /**
     * Sanitize value as email.
     *
     * @param mixed $value Input value.
     * @return string
     */
    public static function sanitize_email_val( $value ): string {
        $email = is_string( $value ) ? sanitize_email( $value ) : '';
        return is_email( $email ) ? $email : '';
    }

    /**
     * Sanitize value as integer.
     *
     * @param mixed $value Input value.
     * @return int
     */
    public static function sanitize_int( $value ): int {
        return absint( $value );
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
            'fm_imap_host'     => [ 'type' => 'string', 'default' => '' ],
            'fm_imap_port'     => [ 'type' => 'string', 'default' => '993' ],
            'fm_imap_ssl'      => [ 'type' => 'string', 'default' => 'ssl' ],
            'fm_imap_username' => [ 'type' => 'string', 'default' => '' ],
            'fm_imap_password' => [ 'type' => 'string', 'default' => '' ],
            'fm_imap_folder'   => [ 'type' => 'string', 'default' => 'INBOX' ],
        ];

        foreach ( $fields as $name => $config ) {
            register_setting(
                self::OPTION_GROUP,
                $name,
                [
                    'type'              => $config['type'],
                    'sanitize_callback' => [ self::class, 'sanitize_text' ],
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
            'fm_sync_interval',
            [
                'type'              => 'integer',
                'sanitize_callback' => [ self::class, 'sanitize_int' ],
                'default'           => 5,
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            'fm_auto_sync',
            [
                'type'              => 'string',
                'sanitize_callback' => [ self::class, 'sanitize_text' ],
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
            'fm_ticket_prefix',
            [
                'type'              => 'string',
                'sanitize_callback' => [ self::class, 'sanitize_text' ],
                'default'           => 'REQ',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            'fm_default_status',
            [
                'type'              => 'string',
                'sanitize_callback' => [ self::class, 'sanitize_text' ],
                'default'           => 'new',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            'fm_default_priority',
            [
                'type'              => 'string',
                'sanitize_callback' => [ self::class, 'sanitize_text' ],
                'default'           => 'medium',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            'fm_ignore_sender_patterns',
            [
                'type'              => 'string',
                'sanitize_callback' => [ self::class, 'sanitize_textarea' ],
                'default'           => '',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            'fm_ignore_domains',
            [
                'type'              => 'string',
                'sanitize_callback' => [ self::class, 'sanitize_textarea' ],
                'default'           => '',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            'fm_ignore_sender_prefixes',
            [
                'type'              => 'string',
                'sanitize_callback' => [ self::class, 'sanitize_textarea' ],
                'default'           => '',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            'fm_ignore_local_domain',
            [
                'type'              => 'string',
                'sanitize_callback' => [ self::class, 'sanitize_text' ],
                'default'           => 'fanaloka.co',
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
                'fm_' . $name,
                [
                    'type'              => 'string',
                    'sanitize_callback' => [ self::class, 'sanitize_text' ],
                    'default'           => $default,
                ]
            );
        }

        register_setting(
            self::OPTION_GROUP,
            'fm_admin_email',
            [
                'type'              => 'string',
                'sanitize_callback' => [ self::class, 'sanitize_email_val' ],
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

        add_settings_field(
            'fm_ignore_sender_patterns',
            __( 'Ignore Sender Patterns', 'fanaloka-maintenance' ),
            [ $this, 'render_textarea_field' ],
            'fm-settings',
            'fm_ticket_section',
            [
                'id'    => 'fm_ignore_sender_patterns',
                'label' => __( 'Email addresses or patterns to ignore (one per line). Supports: exact email, @domain.com, or *wildcard*', 'fanaloka-maintenance' ),
            ]
        );

        add_settings_field(
            'fm_ignore_domains',
            __( 'Ignore Domains', 'fanaloka-maintenance' ),
            [ $this, 'render_textarea_field' ],
            'fm-settings',
            'fm_ticket_section',
            [
                'id'    => 'fm_ignore_domains',
                'label' => __( 'Additional domains to ignore (one per line, without @). Example: github.com', 'fanaloka-maintenance' ),
            ]
        );

        add_settings_field(
            'fm_ignore_sender_prefixes',
            __( 'Ignore Sender Prefixes', 'fanaloka-maintenance' ),
            [ $this, 'render_textarea_field' ],
            'fm-settings',
            'fm_ticket_section',
            [
                'id'    => 'fm_ignore_sender_prefixes',
                'label' => __( 'Sender local part prefixes to ignore (one per line). Example: noreply, notifications', 'fanaloka-maintenance' ),
            ]
        );

        add_settings_field(
            'fm_ignore_local_domain',
            __( 'Local Domain', 'fanaloka-maintenance' ),
            [ $this, 'render_text_field' ],
            'fm-settings',
            'fm_ticket_section',
            [
                'id'    => 'fm_ignore_local_domain',
                'label' => __( 'Your company domain to ignore internal emails (e.g., fanaloka.co)', 'fanaloka-maintenance' ),
                'type'  => 'text',
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

        $autocomplete = 'password' === $type ? ' autocomplete="current-password"' : '';

        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="regular-text"%s />',
            esc_attr( $type ),
            esc_attr( $id ),
            esc_attr( $id ),
            esc_attr( $value ),
            $autocomplete
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
     * Render textarea field.
     *
     * @param array<string, string> $args Field args.
     * @return void
     */
    public function render_textarea_field( array $args ): void {
        $id    = $args['id'] ?? '';
        $label = $args['label'] ?? '';
        $value = get_option( $id, '' );

        printf(
            '<textarea id="%s" name="%s" rows="5" class="large-text">%s</textarea>',
            esc_attr( $id ),
            esc_attr( $id ),
            esc_textarea( $value )
        );

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
        echo '<span id="fm-test-result" style="margin-left:10px;font-size:13px;font-weight:600;"></span>';
        ?>
        <style>
        .fm-test-success { color: #00a32a; }
        .fm-test-error { color: #d63638; }
        </style>
        <?php
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

        try {
            $reader = new IMAPReader();
            $result = $reader->test_connection();
        } catch ( \Exception $e ) {
            Logger::log( 'Test connection exception: ' . $e->getMessage(), Logger::LEVEL_ERROR );
            wp_send_json_error( [
                'message' => $e->getMessage(),
            ] );
            return;
        }

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
        <div class="fm-page-wrap">
            <div class="fm-page-header">
                <h1 class="fm-page-title">
                    <span class="fm-icon"><?php echo \Fanaloka\Maintenance\Icons::get( 'settings', '#646970' ); ?></span>
                    <?php echo esc_html( get_admin_page_title() ); ?>
                </h1>
            </div>

            <?php if ( $saved ) : ?>
                <div class="fm-notice fm-notice-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Settings saved.', 'fanaloka-maintenance' ); ?>
                </div>
            <?php endif; ?>

            <div class="fm-card">
                <nav class="nav-tab-wrapper fm-nav-tabs">
                    <a href="?page=fm-settings&tab=general"
                       class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>">
                        <span class="fm-icon-sm"><?php echo \Fanaloka\Maintenance\Icons::get( 'clients', '#646970' ); ?></span> <?php esc_html_e( 'IMAP', 'fanaloka-maintenance' ); ?>
                    </a>
                    <a href="?page=fm-settings&tab=sync"
                       class="nav-tab <?php echo 'sync' === $tab ? 'nav-tab-active' : ''; ?>">
                        <span class="fm-icon-sm"><?php echo \Fanaloka\Maintenance\Icons::get( 'refresh', '#646970' ); ?></span> <?php esc_html_e( 'Sync', 'fanaloka-maintenance' ); ?>
                    </a>
                    <a href="?page=fm-settings&tab=ticket"
                       class="nav-tab <?php echo 'ticket' === $tab ? 'nav-tab-active' : ''; ?>">
                        <span class="fm-icon-sm"><?php echo \Fanaloka\Maintenance\Icons::get( 'requests', '#646970' ); ?></span> <?php esc_html_e( 'Ticket', 'fanaloka-maintenance' ); ?>
                    </a>
                    <a href="?page=fm-settings&tab=notification"
                       class="nav-tab <?php echo 'notification' === $tab ? 'nav-tab-active' : ''; ?>">
                        <span class="fm-icon-sm"><?php echo \Fanaloka\Maintenance\Icons::get( 'bell', '#646970' ); ?></span> <?php esc_html_e( 'Notifications', 'fanaloka-maintenance' ); ?>
                    </a>
                </nav>

                <div class="fm-settings-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( self::OPTION_GROUP );

                        // Hidden fields to preserve values from other tabs.
                        $this->render_hidden_fields( $tab );

                        if ( 'general' === $tab ) {
                            $this->render_tab_sections( [ 'fm_imap_section' ] );
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
            </div>
        </div>

        <style>
        .fm-page-wrap { max-width: 1200px; margin: 0 auto; padding: 0 0 40px; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-nav-tabs { padding: 12px 20px 0 !important; background: #f9f9f9; border-bottom: 1px solid #e2e4e7 !important; }
        .fm-nav-tabs .nav-tab { border: 1px solid transparent !important; border-bottom: none !important; margin-bottom: -1px; padding: 10px 18px; font-size: 14px; color: #646970; }
        .fm-nav-tabs .nav-tab .dashicons { margin-right: 4px; font-size: 15px; top: 2px; }
        .fm-nav-tabs .nav-tab:hover { background: #fff; color: #1d2327; }
        .fm-nav-tabs .nav-tab.nav-tab-active { background: #fff; border-color: #e2e4e7 !important; color: #1d2327; font-weight: 600; }
        .fm-settings-content { padding: 20px; }
        .fm-settings-content .form-table th { width: 200px; }
        .fm-notice { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .fm-notice-success { background: #e6f9e6; color: #00a32a; border: 1px solid #b8e6b8; }
        .fm-notice-success .dashicons { font-size: 18px; }
        </style>
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

    /**
     * Render hidden fields to preserve values from other tabs.
     *
     * @param string $current_tab Current active tab.
     * @return void
     */
    private function render_hidden_fields( string $current_tab ): void {
        $all_options = [
            'fm_imap_host', 'fm_imap_port', 'fm_imap_ssl', 'fm_imap_username',
            'fm_imap_password', 'fm_imap_folder', 'fm_sync_interval', 'fm_auto_sync',
            'fm_ticket_prefix', 'fm_default_status', 'fm_default_priority',
            'fm_ignore_sender_patterns', 'fm_ignore_domains', 'fm_ignore_sender_prefixes', 'fm_ignore_local_domain',
            'fm_notif_new_ticket', 'fm_notif_status_change', 'fm_notif_assignment',
            'fm_notif_ticket_completed', 'fm_admin_email',
        ];

        // Determine which fields are visible on current tab.
        $visible = [];
        if ( 'general' === $current_tab ) {
            $visible = [ 'fm_imap_host', 'fm_imap_port', 'fm_imap_ssl', 'fm_imap_username', 'fm_imap_password', 'fm_imap_folder' ];
        } elseif ( 'sync' === $current_tab ) {
            $visible = [ 'fm_sync_interval', 'fm_auto_sync' ];
        } elseif ( 'ticket' === $current_tab ) {
            $visible = [ 'fm_ticket_prefix', 'fm_default_status', 'fm_default_priority', 'fm_ignore_sender_patterns', 'fm_ignore_domains', 'fm_ignore_sender_prefixes', 'fm_ignore_local_domain' ];
        } elseif ( 'notification' === $current_tab ) {
            $visible = [ 'fm_notif_new_ticket', 'fm_notif_status_change', 'fm_notif_assignment', 'fm_notif_ticket_completed', 'fm_admin_email' ];
        }

        foreach ( $all_options as $option ) {
            if ( ! in_array( $option, $visible, true ) ) {
                $value = get_option( $option, '' );
                printf(
                    '<input type="hidden" name="%s" value="%s" />',
                    esc_attr( $option ),
                    esc_attr( $value )
                );
            }
        }
    }
}
