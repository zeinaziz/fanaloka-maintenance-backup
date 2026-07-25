<?php
/**
 * Admin Menu Handler.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Class.
 */
class Admin {

    /**
     * Single instance.
     *
     * @var Admin|null
     */
    private static ?Admin $instance = null;

    /**
     * Message-ID for reply email.
     *
     * @var string
     */
    private string $reply_message_id = '';

    /**
     * In-Reply-To for reply email.
     *
     * @var string
     */
    private string $reply_in_reply_to = '';

    /**
     * References for reply email.
     *
     * @var string
     */
    private string $reply_references = '';

    /**
     * Attachments for reply email.
     *
     * @var array<int, string>
     */
    private array $reply_attachments = [];

    /**
     * Get single instance.
     *
     * @return Admin
     */
    public static function instance(): Admin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'init', [ $this, 'handle_ticket_actions' ], 1 );
        add_action( 'wp_ajax_fm_update_ticket', [ $this, 'ajax_update_ticket' ] );
        add_action( 'wp_ajax_fm_reply_ticket', [ $this, 'ajax_reply_ticket' ] );
    }

    /**
     * Handle ticket form actions before any output (reply, status change, etc).
     *
     * @return void
     */
    public function handle_ticket_actions(): void {
        if ( ! is_admin() ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_GET['page'] ) || 'fm-requests' !== $_GET['page'] ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST['fm_action'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $ticket_id = isset( $_POST['ticket_id'] )
            ? absint( $_POST['ticket_id'] )
            : ( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );

        if ( ! $ticket_id ) {
            return;
        }

        $detail = new TicketDetailPage( $ticket_id );
        $detail->handle_actions();
    }

    /**
     * Register admin menus.
     *
     * @return void
     */
    public function register_menus(): void {
        add_menu_page(
            __( 'Maintenance', 'fanaloka-maintenance' ),
            __( 'Maintenance', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-dashboard',
            [ new DashboardPage(), 'render' ],
            'dashicons-tools',
            3
        );

        add_submenu_page(
            'fm-dashboard',
            __( 'Dashboard', 'fanaloka-maintenance' ),
            __( 'Dashboard', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-dashboard',
            [ new DashboardPage(), 'render' ]
        );

        add_submenu_page(
            'fm-dashboard',
            __( 'Requests', 'fanaloka-maintenance' ),
            __( 'Requests', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-requests',
            [ new RequestsPage(), 'render' ]
        );

        add_submenu_page(
            'fm-dashboard',
            __( 'Clients', 'fanaloka-maintenance' ),
            __( 'Clients', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-clients',
            [ new ClientsPage(), 'render' ]
        );

        add_submenu_page(
            'fm-dashboard',
            __( 'Developers', 'fanaloka-maintenance' ),
            __( 'Developers', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-developers',
            [ new DevelopersPage(), 'render' ]
        );

        add_submenu_page(
            'fm-dashboard',
            __( 'Reports', 'fanaloka-maintenance' ),
            __( 'Reports', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-reports',
            [ new ReportsPage(), 'render' ]
        );

        add_submenu_page(
            'fm-dashboard',
            __( 'Settings', 'fanaloka-maintenance' ),
            __( 'Settings', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-settings',
            [ new SettingsPage(), 'render' ]
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'fm-' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'fm-admin',
            FM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            FM_VERSION
        );

        wp_enqueue_script(
            'fm-admin',
            FM_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            FM_VERSION,
            true
        );

        wp_localize_script( 'fm-admin', 'fmAdmin', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'fm_admin_nonce' ),
            'saved'         => __( 'Saved!', 'fanaloka-maintenance' ),
            'savedError'    => __( 'Error saving', 'fanaloka-maintenance' ),
            'testing'       => __( 'Testing...', 'fanaloka-maintenance' ),
            'success'       => __( 'Connected!', 'fanaloka-maintenance' ),
            'failed'        => __( 'Failed', 'fanaloka-maintenance' ),
            'testConnection' => __( 'Test Connection', 'fanaloka-maintenance' ),
            'syncing'       => __( 'Syncing...', 'fanaloka-maintenance' ),
            'syncComplete'  => __( 'Sync Complete!', 'fanaloka-maintenance' ),
            'syncNow'       => __( 'Sync Now', 'fanaloka-maintenance' ),
        ] );
    }

    /**
     * AJAX: Update ticket field (status/priority/developer).
     *
     * @return void
     */
    public function ajax_update_ticket(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        $field     = sanitize_text_field( wp_unslash( $_POST['field'] ?? '' ) );
        $value     = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

        if ( ! $ticket_id || ! in_array( $field, [ 'status', 'priority', 'developer_id' ], true ) ) {
            wp_send_json_error( [ 'message' => 'Invalid params' ] );
        }

        $ticket_manager = new \Fanaloka\Maintenance\Ticket\TicketManager();

        switch ( $field ) {
            case 'status':
                $ticket_manager->update_status( $ticket_id, $value );
                break;
            case 'priority':
                $ticket_manager->update_priority( $ticket_id, $value );
                break;
            case 'developer_id':
                $ticket_manager->assign_developer( $ticket_id, absint( $value ) );
                break;
        }

        wp_send_json_success( [ 'message' => 'Saved!' ] );
    }

    /**
     * AJAX: Reply to ticket.
     *
     * @return void
     */
    public function ajax_reply_ticket(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        $content   = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );

        if ( ! $ticket_id || empty( $content ) ) {
            wp_send_json_error();
        }

        $user = wp_get_current_user();

        $conversation = new \Fanaloka\Maintenance\Ticket\ConversationManager();
        $conversation->add_reply_from_developer( $ticket_id, $content );

        // Send email to client.
        $this->send_reply_email( $ticket_id, $content );

        wp_send_json_success( [
            'message' => 'Reply sent!',
            'author'  => $user->display_name,
            'content' => nl2br( esc_html( $content ) ),
            'date'    => wp_date( 'd M Y, H:i' ),
        ] );
    }

    /**
     * Send reply email to client.
     *
     * @param int    $ticket_id Ticket ID.
     * @param string $content   Reply content.
     * @return void
     */
    private function send_reply_email( int $ticket_id, string $content ): void {
        $ticket  = ( new \Fanaloka\Maintenance\Ticket\TicketManager() )->get_ticket_meta( $ticket_id );
        $to      = $ticket['client_email'] ?? '';
        $subject = 'Re: ' . $ticket['subject'];

        // Get conversation data for threading.
        $conversation = new \Fanaloka\Maintenance\Ticket\ConversationManager();
        $last_client_msg_id = $conversation->get_last_client_message_id( $ticket_id );

        // Generate Message-ID for this reply.
        $message_id = ( new \Fanaloka\Maintenance\Email\EmailParser() )->generate_message_id( $ticket_id );

        // Build references chain.
        $all_entries = $conversation->get_entries( $ticket_id );
        $refs = [];
        foreach ( $all_entries as $entry ) {
            if ( ! empty( $entry['message_id'] ) ) {
                $refs[] = $entry['message_id'];
            }
        }
        $references = implode( ' ', $refs );

        // Store data for phpmailer_init callback.
        $this->reply_message_id = $message_id;
        $this->reply_in_reply_to = $last_client_msg_id ?? '';
        $this->reply_references  = $references;
        $this->reply_attachments = [];

        // Get attachment file paths from latest developer entry.
        foreach ( array_reverse( $all_entries ) as $entry ) {
            if ( 'developer' === $entry['entry_type'] && ! empty( $entry['attachments'] ) ) {
                $att_ids = explode( ',', $entry['attachments'] );
                foreach ( $att_ids as $att_id ) {
                    $file = wp_get_attachment_upload_dir( absint( $att_id ) );
                    $url  = wp_get_attachment_url( absint( $att_id ) );
                    if ( $url ) {
                        $this->reply_attachments[] = get_attached_file( absint( $att_id ) );
                    }
                }
                break;
            }
        }

        add_action( 'phpmailer_init', [ $this, 'set_reply_email_headers' ] );

        $body = sprintf(
            '<p>Halo %s,</p><p>Berikut adalah balasan dari tim kami:</p><p>%s</p><p>Salam,<br>Tim Support</p>',
            esc_html( $ticket['client_name'] ),
            nl2br( esc_html( $content ) )
        );

        wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );

        remove_action( 'phpmailer_init', [ $this, 'set_reply_email_headers' ] );
    }

    /**
     * Set reply headers for use by TicketDetailPage.
     *
     * @param string   $message_id   Message-ID.
     * @param string   $in_reply_to  In-Reply-To.
     * @param string   $references   References.
     * @param string[] $attachments  Attachment file paths.
     * @return void
     */
    public function set_reply_headers( string $message_id, string $in_reply_to, string $references, array $attachments = [] ): void {
        $this->reply_message_id  = $message_id;
        $this->reply_in_reply_to = $in_reply_to;
        $this->reply_references  = $references;
        $this->reply_attachments = $attachments;
    }

    /**
     * Set Message-ID and In-Reply-To via PHPMailer.
     *
     * @param \PHPMailer $phpmailer PHPMailer instance.
     * @return void
     */
    public function set_reply_email_headers( $phpmailer ): void {
        $phpmailer->IsHTML( true );
        $phpmailer->CharSet = 'UTF-8';

        if ( ! empty( $this->reply_message_id ) ) {
            $phpmailer->MessageID = $this->reply_message_id;
        }
        if ( ! empty( $this->reply_in_reply_to ) ) {
            $phpmailer->addCustomHeader( 'In-Reply-To', $this->reply_in_reply_to );
        }
        if ( ! empty( $this->reply_references ) ) {
            $phpmailer->addCustomHeader( 'References', $this->reply_references );
        }
        if ( ! empty( $this->reply_attachments ) ) {
            foreach ( $this->reply_attachments as $file_path ) {
                if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
                    $phpmailer->addAttachment( $file_path );
                }
            }
        }
    }
}
