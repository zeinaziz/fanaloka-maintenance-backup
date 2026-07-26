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
     * Reply HTML body.
     *
     * @var string
     */
    private string $reply_body_html = '';

    /**
     * Reply plain text body.
     *
     * @var string
     */
    private string $reply_body_plain = '';

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
        add_action( 'wp_ajax_fm_list_requests', [ $this, 'ajax_list_requests' ] );
        add_action( 'wp_ajax_fm_bulk_delete_requests', [ $this, 'ajax_bulk_delete_requests' ] );
        add_action( 'wp_ajax_fm_get_entries', [ $this, 'ajax_get_entries' ] );
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

        // Re-fetch ticket to get updated data.
        $ticket = $ticket_manager->get_ticket_meta( $ticket_id );
        $badges = $this->render_ticket_badges( $ticket );

        wp_send_json_success( [
            'message' => 'Saved!',
            'badges'  => $badges,
        ] );
    }

    /**
     * Render ticket header badges HTML.
     *
     * @param array<string, mixed> $ticket Ticket data.
     * @return string
     */
    private function render_ticket_badges( array $ticket ): string {
        $status_colors = [
            'new'            => '#2271b1',
            'open'           => '#dba617',
            'in-progress'    => '#996800',
            'waiting-client' => '#00a32a',
            'completed'      => '#00a32a',
            'cancelled'      => '#d63638',
        ];
        $priority_colors = [
            'low'      => '#646970',
            'medium'   => '#2271b1',
            'high'     => '#dba617',
            'critical' => '#d63638',
        ];
        $sc = $status_colors[ $ticket['status'] ?? '' ] ?? '#646970';
        $pc = $priority_colors[ $ticket['priority'] ?? '' ] ?? '#646970';

        $html  = '<span class="fm-badge" style="background:' . esc_attr( $sc ) . ';">' . esc_html( ucfirst( str_replace( '-', ' ', $ticket['status'] ?? '' ) ) ) . '</span>';
        $html .= '<span class="fm-badge fm-badge-outline" style="color:' . esc_attr( $pc ) . ';border-color:' . esc_attr( $pc ) . ';">' . esc_html( ucfirst( $ticket['priority'] ?? '' ) ) . '</span>';

        return $html;
    }

    /**
     * AJAX: Reply to ticket.
     *
     * @return void
     */
    public function ajax_reply_ticket(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        $content   = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );

        if ( ! $ticket_id || empty( $content ) ) {
            wp_send_json_error( [ 'message' => 'Empty content' ] );
        }

        $user = wp_get_current_user();

        $conversation = new \Fanaloka\Maintenance\Ticket\ConversationManager();
        $entry_id = $conversation->add_reply_from_developer( $ticket_id, $content );

        // Handle file uploads.
        if ( ! empty( $_FILES['reply_attachments']['name'][0] ) && $entry_id ) {
            $this->handle_reply_attachments( $ticket_id, $entry_id );
        }

        // Send email to client.
        $this->send_reply_email( $ticket_id, $content );

        // Build entry HTML for timeline.
        $entry_html = $this->render_entry_html( [
            'sender'     => $user->display_name,
            'entry_type' => 'developer',
            'body'       => $content,
            'created_at' => wp_date( 'Y-m-d H:i:s' ),
        ] );

        // Get updated badges.
        $ticket_manager = new \Fanaloka\Maintenance\Ticket\TicketManager();
        $ticket = $ticket_manager->get_ticket_meta( $ticket_id );
        $badges = $this->render_ticket_badges( $ticket );

        wp_send_json_success( [
            'message' => 'Reply sent!',
            'entry'   => $entry_html,
            'badges'  => $badges,
        ] );
    }

    /**
     * Render a single conversation entry HTML.
     *
     * @param array<string, mixed> $entry Entry data.
     * @return string
     */
    private function render_entry_html( array $entry ): string {
        $initials    = strtoupper( substr( $entry['sender'], 0, 1 ) );
        $entry_color = 'developer' === $entry['entry_type'] ? '#2271b1' : ( 'client' === $entry['entry_type'] ? '#00a32a' : '#646970' );

        $html  = '<div class="fm-entry fm-entry-' . esc_attr( $entry['entry_type'] ) . '">';
        $html .= '<div class="fm-entry-avatar" style="background:' . esc_attr( $entry_color ) . ';">' . esc_html( $initials ) . '</div>';
        $html .= '<div class="fm-entry-body">';
        $html .= '<div class="fm-entry-meta">';
        $html .= '<strong class="fm-entry-sender">' . esc_html( $entry['sender'] ) . '</strong>';
        $html .= '<span class="fm-entry-type-badge" style="background:' . esc_attr( $entry_color ) . '15;color:' . esc_attr( $entry_color ) . ';">' . esc_html( ucfirst( $entry['entry_type'] ) ) . '</span>';
        $html .= '<span class="fm-entry-date">' . esc_html( $entry['created_at'] ) . '</span>';
        $html .= '</div>';
        $html .= '<div class="fm-entry-content">' . wp_kses_post( $entry['body'] ) . '</div>';

        // Handle attachments.
        $att_ids = ! empty( $entry['attachments'] ) ? explode( ',', $entry['attachments'] ) : [];
        $att_ids = array_filter( array_map( 'absint', $att_ids ) );
        if ( ! empty( $att_ids ) ) {
            $html .= '<div class="fm-entry-attachments">';
            foreach ( $att_ids as $att_id ) {
                $url  = wp_get_attachment_url( $att_id );
                $name = get_the_title( $att_id );
                if ( $url ) {
                    if ( wp_attachment_is_image( $att_id ) ) {
                        $html .= '<div class="fm-attachment-item"><a href="' . esc_url( $url ) . '" target="_blank"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $name ) . '" /></a></div>';
                    } else {
                        $html .= '<a href="' . esc_url( $url ) . '" target="_blank" class="fm-attachment-file"><span class="dashicons dashicons-media-default"></span> ' . esc_html( $name ) . '</a>';
                    }
                }
            }
            $html .= '</div>';
        }

        $html .= '</div></div>';

        return $html;
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

        $conversation = new \Fanaloka\Maintenance\Ticket\ConversationManager();
        $last_client_msg_id = $conversation->get_last_client_message_id( $ticket_id );

        $message_id = ( new \Fanaloka\Maintenance\Email\EmailParser() )->generate_message_id( $ticket_id );

        $all_entries = $conversation->get_entries( $ticket_id );
        $refs = [];
        foreach ( $all_entries as $entry ) {
            if ( ! empty( $entry['message_id'] ) ) {
                $refs[] = $entry['message_id'];
            }
        }
        $references = implode( ' ', $refs );

        $this->reply_message_id  = $message_id;
        $this->reply_in_reply_to = $last_client_msg_id ?? '';
        $this->reply_references  = $references;
        $this->reply_attachments = [];

        // Get latest developer attachments.
        foreach ( array_reverse( $all_entries ) as $entry ) {
            if ( 'developer' === $entry['entry_type'] && ! empty( $entry['attachments'] ) ) {
                $att_ids = explode( ',', $entry['attachments'] );
                foreach ( $att_ids as $att_id ) {
                    $file = get_attached_file( absint( $att_id ) );
                    if ( $file && file_exists( $file ) ) {
                        $this->reply_attachments[] = $file;
                    }
                }
                break;
            }
        }

        $body_html  = wp_kses_post( $content );
        $body_plain = wp_strip_all_tags( $content );

        $this->set_reply_body( $body_html, $body_plain );

        add_action( 'phpmailer_init', [ $this, 'set_reply_email_headers' ], 999 );

        wp_mail( $to, $subject, $body_html, "Content-Type: text/html; charset=UTF-8\nMIME-Version: 1.0" );

        remove_action( 'phpmailer_init', [ $this, 'set_reply_email_headers' ], 999 );

        $this->set_reply_body( '', '' );

        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Reply email sent to %s for ticket #%d', $to, $ticket_id ) );
    }

    /**
     * Handle file uploads for reply.
     *
     * @param int $ticket_id Ticket ID.
     * @param int $entry_id  Conversation entry ID.
     * @return void
     */
    private function handle_reply_attachments( int $ticket_id, int $entry_id ): void {
        global $wpdb;

        $table = \Fanaloka\Maintenance\Database::table_name();
        $files = $_FILES['reply_attachments'] ?? [];

        if ( empty( $files['name'][0] ) ) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $ticket_dir = $upload_dir['path'] . '/fm_tickets/' . $ticket_id;

        if ( ! file_exists( $ticket_dir ) ) {
            wp_mkdir_p( $ticket_dir );
        }

        $saved_ids = [];

        for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
            if ( empty( $files['name'][ $i ] ) || UPLOAD_ERR_OK !== $files['error'][ $i ] ) {
                continue;
            }

            $filename = sanitize_file_name( $files['name'][ $i ] );
            $file_path = $ticket_dir . '/' . $filename;

            $counter = 1;
            while ( file_exists( $file_path ) ) {
                $pathinfo  = pathinfo( $filename );
                $file_path = $ticket_dir . '/' . $pathinfo['filename'] . '-' . $counter . '.' . $pathinfo['extension'];
                $counter++;
            }

            if ( move_uploaded_file( $files['tmp_name'][ $i ], $file_path ) ) {
                $attachment_id = $this->save_to_media( $file_path, $filename, $ticket_id );
                if ( $attachment_id ) {
                    $saved_ids[] = $attachment_id;
                }
            }
        }

        if ( ! empty( $saved_ids ) ) {
            $existing = $wpdb->get_var(
                $wpdb->prepare( "SELECT attachments FROM {$table} WHERE id = %d", $entry_id )
            );
            $all = ! empty( $existing ) ? $existing . ',' . implode( ',', $saved_ids ) : implode( ',', $saved_ids );
            $wpdb->update( $table, [ 'attachments' => $all ], [ 'id' => $entry_id ], [ '%s' ], [ '%d' ] );
        }
    }

    /**
     * Save file to media library.
     *
     * @param string $file_path Local file path.
     * @param string $filename  Original filename.
     * @param int    $ticket_id Ticket ID.
     * @return int|false Attachment ID.
     */
    private function save_to_media( string $file_path, string $filename, int $ticket_id ) {
        $filetype = wp_check_filetype( $filename );
        $upload   = wp_insert_attachment( [
            'post_title'     => $filename,
            'post_mime_type' => $filetype['type'] ?? 'application/octet-stream',
            'post_status'    => 'inherit',
            'post_content'   => '',
        ], $file_path );

        if ( is_wp_error( $upload ) ) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $upload, $file_path );
        wp_update_attachment_metadata( $upload, $attach_data );

        return $upload;
    }

    /**
     * AJAX: Get latest conversation entries (for auto-refresh).
     *
     * @return void
     */
    public function ajax_get_entries(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        $after_id  = absint( $_POST['after_id'] ?? 0 );

        if ( ! $ticket_id ) {
            wp_send_json_error( [ 'message' => 'Invalid ticket' ] );
        }

        $conversation = new \Fanaloka\Maintenance\Ticket\ConversationManager();
        $entries      = $conversation->get_entries( $ticket_id );

        $new_entries = [];
        foreach ( $entries as $entry ) {
            if ( $entry['id'] > $after_id ) {
                $new_entries[] = $this->render_entry_html( $entry );
            }
        }

        // Get updated badges.
        $ticket_manager = new \Fanaloka\Maintenance\Ticket\TicketManager();
        $ticket = $ticket_manager->get_ticket_meta( $ticket_id );
        $badges = $this->render_ticket_badges( $ticket );

        $last_id = ! empty( $entries ) ? end( $entries )['id'] : 0;

        wp_send_json_success( [
            'entries' => $new_entries,
            'badges'  => $badges,
            'last_id' => $last_id,
        ] );
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
     * Set reply email bodies.
     *
     * @param string $html  HTML body.
     * @param string $plain Plain text body.
     * @return void
     */
    public function set_reply_body( string $html, string $plain ): void {
        $this->reply_body_html  = $html;
        $this->reply_body_plain = $plain;
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

        // Force HTML body.
        if ( ! empty( $this->reply_body_html ) ) {
            $phpmailer->Body = $this->reply_body_html;
        }
        if ( ! empty( $this->reply_body_plain ) ) {
            $phpmailer->AltBody = $this->reply_body_plain;
        }

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
            \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'PHPMailer: adding %d attachments', count( $this->reply_attachments ) ) );
            foreach ( $this->reply_attachments as $file_path ) {
                if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
                    $phpmailer->addAttachment( $file_path );
                    \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'PHPMailer: attached %s', $file_path ) );
                } else {
                    \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'PHPMailer: SKIPPED %s (not found)', $file_path ) );
                }
            }
        } else {
            \Fanaloka\Maintenance\Logger\Logger::log( 'PHPMailer: no attachments to add' );
        }
    }

    /**
     * AJAX: List requests with filter, sort, pagination.
     *
     * @return void
     */
    public function ajax_list_requests(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $paged    = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
        $status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
        $priority = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : '';
        $orderby  = isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : 'date';
        $order    = isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : 'DESC';
        $per_page = isset( $_POST['per_page'] ) ? max( 1, absint( $_POST['per_page'] ) ) : 20;

        $ticket_manager = new \Fanaloka\Maintenance\Ticket\TicketManager();
        $result         = $ticket_manager->get_tickets( [
            'per_page' => $per_page,
            'paged'    => $paged,
            'status'   => $status,
            'priority' => $priority,
            'orderby'  => $orderby,
            'order'    => $order,
        ] );

        $html = '';
        if ( empty( $result['tickets'] ) ) {
            $html = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#8c8f94;">No tickets found.</td></tr>';
        } else {
            $view_url = admin_url( 'admin.php?page=fm-requests&action=view&id=' );
            foreach ( $result['tickets'] as $ticket ) {
                $status_colors = [
                    'new'            => 'fm-badge-primary',
                    'open'           => 'fm-badge-warning',
                    'in-progress'    => 'fm-badge-success',
                    'waiting-client' => 'fm-badge-warning',
                    'completed'      => 'fm-badge-success',
                    'cancelled'      => 'fm-badge-danger',
                ];
                $priority_colors = [
                    'low'      => 'fm-badge-default',
                    'medium'   => 'fm-badge-warning',
                    'high'     => 'fm-badge-danger',
                    'critical' => 'fm-badge-danger',
                ];
                $sc = $status_colors[ $ticket['status'] ] ?? 'fm-badge-default';
                $pc = $priority_colors[ $ticket['priority'] ] ?? 'fm-badge-default';

                $html .= '<tr>';
                $html .= '<th scope="row" class="check-column"><input type="checkbox" name="ticket[]" value="' . esc_attr( $ticket['id'] ) . '" /></th>';
                $html .= '<td class="column-ticket_number column-primary"><a href="' . esc_url( $view_url . $ticket['id'] ) . '"><strong>' . esc_html( $ticket['full_number'] ) . '</strong></a></td>';
                $html .= '<td class="column-client"><strong>' . esc_html( $ticket['client_name'] ) . '</strong><br><span style="color:#8c8f94;font-size:12px;">' . esc_html( $ticket['client_email'] ) . '</span></td>';
                $html .= '<td class="column-subject"><a href="' . esc_url( $view_url . $ticket['id'] ) . '">' . esc_html( $ticket['subject'] ) . '</a></td>';
                $html .= '<td class="column-status"><span class="fm-badge ' . esc_attr( $sc ) . '">' . esc_html( $ticket['status_label'] ?? $ticket['status'] ) . '</span></td>';
                $html .= '<td class="column-priority"><span class="fm-badge ' . esc_attr( $pc ) . '">' . esc_html( $ticket['priority_label'] ?? $ticket['priority'] ) . '</span></td>';
                $html .= '<td class="column-assigned_dev">' . esc_html( $ticket['assigned_dev_name'] ?? '-' ) . '</td>';
                $html .= '<td class="column-date_created">' . esc_html( $ticket['date_created'] ?? '' ) . '</td>';
                $html .= '</tr>';
            }
        }

        $total  = $result['total'];
        $pages  = $result['pages'];
        $from   = ( $paged - 1 ) * $per_page + 1;
        $to     = min( $paged * $per_page, $total );
        $displaying = $total > 0
            ? sprintf(
                /* translators: 1: from number, 2: to number, 3: total */
                __( '%1$d – %2$d of %3$d items', 'fanaloka-maintenance' ),
                $from, $to, $total
            )
            : __( 'No items', 'fanaloka-maintenance' );

        $pagination = '';
        if ( $pages > 1 ) {
            $pagination .= '<a class="button" data-page="' . max( 1, $paged - 1 ) . '"' . ( $paged <= 1 ? ' disabled' : '' ) . '>&laquo;</a> ';
            $pagination .= '<span class="paging-input">' . $paged . ' of ' . $pages . '</span> ';
            $pagination .= '<a class="button" data-page="' . min( $pages, $paged + 1 ) . '"' . ( $paged >= $pages ? ' disabled' : '' ) . '>&raquo;</a>';
        }

        wp_send_json_success( [
            'html'       => $html,
            'displaying' => $displaying,
            'pagination' => $pagination,
            'total'      => $total,
            'pages'      => $pages,
        ] );
    }

    /**
     * AJAX: Bulk delete requests.
     *
     * @return void
     */
    public function ajax_bulk_delete_requests(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
        $ids = array_filter( $ids );

        if ( empty( $ids ) ) {
            wp_send_json_error( [ 'message' => 'No tickets selected.' ] );
        }

        $ticket_manager = new \Fanaloka\Maintenance\Ticket\TicketManager();
        $deleted = 0;

        foreach ( $ids as $id ) {
            if ( $ticket_manager->delete_ticket( $id ) ) {
                ++$deleted;
            }
        }

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %d: number of deleted tickets */
                __( '%d ticket(s) deleted.', 'fanaloka-maintenance' ),
                $deleted
            ),
            'deleted' => $deleted,
        ] );
    }
}
