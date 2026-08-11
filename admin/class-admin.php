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
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_badge' ], 999 );
        add_action( 'admin_head', [ $this, 'admin_bar_css' ] );
        add_action( 'wp_ajax_fm_update_ticket', [ $this, 'ajax_update_ticket' ] );
        add_action( 'wp_ajax_fm_reply_ticket', [ $this, 'ajax_reply_ticket' ] );
        add_action( 'wp_ajax_fm_add_internal_note', [ $this, 'ajax_add_internal_note' ] );
        add_action( 'wp_ajax_fm_list_requests', [ $this, 'ajax_list_requests' ] );
        add_action( 'wp_ajax_fm_bulk_delete_requests', [ $this, 'ajax_bulk_delete_requests' ] );
        add_action( 'wp_ajax_fm_bulk_update_requests', [ $this, 'ajax_bulk_update_requests' ] );
        add_action( 'wp_ajax_fm_get_entries', [ $this, 'ajax_get_entries' ] );
        add_action( 'wp_ajax_fm_list_clients', [ $this, 'ajax_list_clients' ] );
        add_action( 'wp_ajax_fm_get_client_tickets', [ $this, 'ajax_get_client_tickets' ] );
        add_action( 'wp_ajax_fm_list_developers', [ $this, 'ajax_list_developers' ] );
        add_action( 'wp_ajax_fm_get_developer_tickets', [ $this, 'ajax_get_developer_tickets' ] );
        add_action( 'wp_ajax_fm_test_connection', [ new SettingsPage(), 'ajax_test_connection' ] );
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

        if ( ! current_user_can( 'manage_options' ) ) {
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
    public function admin_bar_css(): void {
        if ( ! is_admin_bar_showing() ) {
            return;
        }
        ?>
        <style>
        .fm-admin-bar-count { display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 18px !important; height: 18px !important; padding: 0 5px !important; margin-left: 4px !important; margin-right: 2px !important; border-radius: 9px !important; background: #d63638 !important; color: #fff !important; font-size: 11px !important; font-weight: 600 !important; line-height: 1 !important; vertical-align: middle !important; }
        #wp-admin-bar-fm-maintenance .ab-icon { margin-right: 2px; }
        </style>
        <?php
    }

    /**
     * Add admin bar badge for open tickets.
     *
     * @param \WP_Admin_Bar $admin_bar Admin bar object.
     * @return void
     */
    public function add_admin_bar_badge( \WP_Admin_Bar $admin_bar ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;

        // Count tickets with _fm_status = new or open from postmeta.
        $new_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta}
                 WHERE meta_key = '_fm_status' AND meta_value = %s",
                'new'
            )
        );
        $open_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta}
                 WHERE meta_key = '_fm_status' AND meta_value = %s",
                'open'
            )
        );
        $pending = $new_count + $open_count;

        if ( $pending === 0 ) {
            return;
        }

        $admin_bar->add_node( [
            'id'    => 'fm-maintenance',
            'title' => '<span class="ab-icon dashicons dashicons-admin-tools"></span>' .
                        '<span class="fm-admin-bar-count">' . esc_html( $pending ) . '</span>' .
                        '<span class="ab-label">' . esc_html__( 'Tickets', 'fanaloka-maintenance' ) . '</span>',
            'href'  => admin_url( 'admin.php?page=fm-requests' ),
            'meta'  => [
                'title' => esc_attr( sprintf(
                    /* translators: %d: number of open tickets */
                    __( '%d open ticket(s)', 'fanaloka-maintenance' ),
                    $pending
                ) ),
            ],
        ] );

        // Sub-items for quick access.
        $admin_bar->add_node( [
            'id'     => 'fm-new',
            'parent' => 'fm-maintenance',
            'title'  => sprintf(
                /* translators: %d: number of new tickets */
                esc_html__( 'New: %d', 'fanaloka-maintenance' ),
                $new_count
            ),
            'href'   => admin_url( 'admin.php?page=fm-requests&status=new' ),
        ] );

        $admin_bar->add_node( [
            'id'     => 'fm-open',
            'parent' => 'fm-maintenance',
            'title'  => sprintf(
                /* translators: %d: number of open tickets */
                esc_html__( 'Open: %d', 'fanaloka-maintenance' ),
                $open_count
            ),
            'href'   => admin_url( 'admin.php?page=fm-requests&status=open' ),
        ] );

        $admin_bar->add_node( [
            'id'     => 'fm-all',
            'parent' => 'fm-maintenance',
            'title'  => esc_html__( 'View All Requests', 'fanaloka-maintenance' ),
            'href'   => admin_url( 'admin.php?page=fm-requests' ),
        ] );
    }

    /**
     * Register admin menus.
     */
    public function register_menus(): void {
        add_menu_page(
            __( 'Maintenance', 'fanaloka-maintenance' ),
            __( 'Maintenance', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-dashboard',
            [ new DashboardPage(), 'render' ],
            'dashicons-admin-tools',
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

        add_submenu_page(
            'fm-dashboard',
            __( 'Guide', 'fanaloka-maintenance' ),
            __( 'Guide', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-guide',
            [ new GuidePage(), 'render' ]
        );

        add_submenu_page(
            'fm-dashboard',
            __( 'Activity Log', 'fanaloka-maintenance' ),
            __( 'Activity Log', 'fanaloka-maintenance' ),
            'manage_options',
            'fm-activity-log',
            [ new \Fanaloka\Maintenance\Admin\ActivityLogPage(), 'render' ]
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

        wp_enqueue_style( 'dashicons' );

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

        require_once FM_PLUGIN_DIR . 'includes/class-activity-log.php';

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

        // Update last activity on any field change.
        update_post_meta( $ticket_id, '_fm_last_updated', time() );

        // Log the change.
        $log_action = 'ticket_' . str_replace( 'developer_id', 'assigned', $field ) . '_changed';
        if ( 'developer_id' === $field ) {
            $log_action = 'ticket_assigned';
            $dev_name = get_the_title( absint( $value ) ) ?: 'Unassigned';
            \Fanaloka\Maintenance\Log\ActivityLog::log( $log_action, 'ticket', $ticket_id, sprintf( 'Ticket #%d assigned to %s', $ticket_id, $dev_name ) );
        } else {
            \Fanaloka\Maintenance\Log\ActivityLog::log( $log_action, 'ticket', $ticket_id, sprintf( 'Ticket #%d %s changed to %s', $ticket_id, $field, $value ) );
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

        require_once FM_PLUGIN_DIR . 'includes/class-activity-log.php';

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        $content   = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
        $cc        = sanitize_text_field( wp_unslash( $_POST['reply_cc'] ?? '' ) );
        $bcc       = sanitize_text_field( wp_unslash( $_POST['reply_bcc'] ?? '' ) );

        // Convert raw newlines to <br> when content has HTML tags but bare \r\n.
        if ( $content && preg_match( '/<[^>]+>/', $content ) && preg_match( '/\r?\n/', $content ) && strpos( $content, '<p>' ) === false ) {
            $content = str_replace( [ "\r\n", "\n" ], '<br>', $content );
        }

        if ( ! $ticket_id || empty( $content ) ) {
            wp_send_json_error( [ 'message' => 'Empty content' ] );
        }

        $user = wp_get_current_user();

        $conversation = new \Fanaloka\Maintenance\Ticket\ConversationManager();
        $entry_id = $conversation->add_reply_from_developer( $ticket_id, $content, $cc, $bcc );

        // Update last activity.
        update_post_meta( $ticket_id, '_fm_last_updated', time() );

        // Handle file uploads.
        if ( ! empty( $_FILES['reply_attachments']['name'][0] ) && $entry_id ) {
            $this->handle_reply_attachments( $ticket_id, $entry_id );
        }

        // Send email to client (with CC/BCC).
        $this->send_reply_email( $ticket_id, $content, $cc, $bcc );

        // Build entry HTML for timeline.
        $new_entry = $conversation->get_entry( $entry_id );
        $entry_html = $this->render_entry_html( [
            'id'          => $entry_id,
            'ticket_id'   => $ticket_id,
            'sender'      => $user->display_name,
            'entry_type'  => 'developer',
            'body'        => $content,
            'attachments' => $new_entry ? $new_entry['attachments'] ?? '' : '',
            'created_at'  => wp_date( 'Y-m-d H:i:s' ),
            'meta'        => $new_entry ? $new_entry['meta'] ?? '' : '',
        ] );

        // Get updated badges.
        $ticket_manager = new \Fanaloka\Maintenance\Ticket\TicketManager();
        $ticket = $ticket_manager->get_ticket_meta( $ticket_id );
        $badges = $this->render_ticket_badges( $ticket );

        \Fanaloka\Maintenance\Log\ActivityLog::log( 'reply_sent', 'ticket', $ticket_id, sprintf( 'Reply to ticket #%d', $ticket_id ) );

        wp_send_json_success( [
            'message' => 'Reply sent!',
            'entry'   => $entry_html,
            'badges'  => $badges,
        ] );
    }

    /**
     * AJAX handler: Add internal note.
     */
    public function ajax_add_internal_note(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        require_once FM_PLUGIN_DIR . 'includes/class-activity-log.php';

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        $content   = wp_kses_post( wp_unslash( $_POST['note_content'] ?? '' ) );

        if ( ! $ticket_id || empty( trim( $content ) ) ) {
            wp_send_json_error( [ 'message' => 'Empty content' ] );
        }

        $user = wp_get_current_user();
        $conversation = new \Fanaloka\Maintenance\Ticket\ConversationManager();
        $entry_id = $conversation->add_internal_note( $ticket_id, $content );

        update_post_meta( $ticket_id, '_fm_last_updated', time() );

        $entry_html = $this->render_entry_html( [
            'id'         => $entry_id,
            'ticket_id'  => $ticket_id,
            'sender'     => $user->display_name,
            'entry_type' => 'internal',
            'body'       => $content,
            'created_at' => wp_date( 'Y-m-d H:i:s' ),
        ] );

        $ticket_manager = new \Fanaloka\Maintenance\Ticket\TicketManager();
        $ticket = $ticket_manager->get_ticket_meta( $ticket_id );
        $badges = $this->render_ticket_badges( $ticket );

        \Fanaloka\Maintenance\Log\ActivityLog::log( 'internal_note_added', 'ticket', $ticket_id, sprintf( 'Internal note on ticket #%d', $ticket_id ) );

        wp_send_json_success( [
            'message' => 'Internal note added!',
            'entry'   => $entry_html,
            'badges'  => $badges,
        ] );
    }

    /**
     * Remove inline images with non-resolvable CID references from DOMDocument.
     *
     * Gmail uses bare refs like "ii_xxx" instead of "cid:xxx@yyy". These images
     * can't be displayed inline. The actual image is saved as attachment.
     *
     * @param \DOMDocument $doc DOM document to clean.
     * @return void
     */
    private function strip_bare_cid_images( \DOMDocument $doc ): void {
        $img_tags = $doc->getElementsByTagName( 'img' );
        $to_remove = [];

        for ( $i = 0; $i < $img_tags->length; $i++ ) {
            $img = $img_tags->item( $i );
            $src = trim( $img->getAttribute( 'src' ) );
            // Keep http/https/data: images. Remove bare CID refs (ii_xxx, inline refs).
            if ( ! empty( $src ) && ! preg_match( '/^(https?|cid|data):/i', $src ) ) {
                $to_remove[] = $img;
            }
        }

        foreach ( $to_remove as $img ) {
            $parent = $img->parentNode;
            if ( $parent ) {
                // Remove the parent <div> if it only contains this image + optional <br>.
                if ( 'div' === $parent->nodeName && $this->is_emptyish_container( $parent ) ) {
                    $parent->parentNode->removeChild( $parent );
                } else {
                    $parent->removeChild( $img );
                }
            }
        }
    }

    /**
     * Check if a node is an empty-ish container (only <br> or whitespace).
     *
     * @param \DOMNode $node Node to check.
     * @return bool
     */
    private function is_emptyish_container( \DOMNode $node ): bool {
        $content = trim( $node->textContent );
        return '' === $content || ctype_space( $content );
    }

    /**
     * Render a single conversation entry HTML.
     *
     * @param array<string, mixed> $entry Entry data.
     * @return string
     */
    private function render_entry_html( array $entry ): string {
        $initials    = strtoupper( substr( $entry['sender'], 0, 1 ) );
        $type        = $entry['entry_type'] ?? 'client';
        $entry_color = 'developer' === $type ? '#2271b1' : ( 'client' === $type ? '#00a32a' : ( 'internal' === $type ? '#856404' : '#646970' ) );

        $entry_id = $entry['id'] ?? 0;
        $created  = $entry['created_at'] ?? wp_date( 'Y-m-d H:i:s' );

        // Action text.
        if ( 'internal' === $type ) {
            $action_text = 'added an internal note';
        } elseif ( 'client' === $type ) {
            $action_text = 'sent a message';
        } else {
            $action_text = 'replied';
        }

        // Get ticket client email for "To:" display (not for internal notes).
        $ticket_id = $entry['ticket_id'] ?? 0;
        $ticket_client_email = ( 'internal' !== $type && $ticket_id ) ? ( get_post_meta( $ticket_id, '_fm_client_email', true ) ?? '' ) : '';

        $html  = '<div class="fm-entry fm-entry-' . esc_attr( $type ) . '" data-entry-id="' . esc_attr( $entry_id ) . '">';
        $html .= '<div class="fm-entry-avatar" style="background:' . esc_attr( $entry_color ) . ';">' . esc_html( $initials ) . '</div>';
        $html .= '<div class="fm-entry-body">';

        // Sender + action + time (Freshdesk style).
        $html .= '<div class="fm-entry-meta">';
        $html .= '<strong class="fm-entry-sender" style="color:' . esc_attr( $entry_color ) . ';">' . esc_html( $entry['sender'] ) . '</strong>';
        $html .= '<span class="fm-entry-action">' . esc_html( $action_text ) . '</span>';
        $html .= '<span class="fm-entry-date">- ' . esc_html( $this->get_relative_time( $created ) ) . ' (' . esc_html( wp_date( 'D, d M Y \a\t g:i A', strtotime( $created ) ) ) . ')</span>';
        if ( 'internal' === $type ) {
            $html .= '<span class="fm-entry-badge-internal">Internal</span>';
        }
        $html .= '</div>';

        // To / CC / BCC line (only for non-internal entries).
        if ( 'internal' !== $type ) {
            $entry_meta = ! empty( $entry['meta'] ) ? json_decode( $entry['meta'], true ) : [];
            $has_to   = ! empty( $ticket_client_email );
            $has_cc   = ! empty( $entry_meta['cc'] );
            $has_bcc  = ! empty( $entry_meta['bcc'] );

            if ( $has_to || $has_cc || $has_bcc ) {
                $html .= '<div class="fm-entry-recipients">';
                if ( $has_to ) {
                    $html .= '<span class="fm-entry-to"><strong>To:</strong> ' . esc_html( $ticket_client_email ) . '</span>';
                }
                if ( $has_cc ) {
                    $html .= '<span class="fm-entry-cc"><strong>Cc:</strong> ' . esc_html( $entry_meta['cc'] ) . '</span>';
                }
                if ( $has_bcc ) {
                    $html .= '<span class="fm-entry-bcc"><strong>Bcc:</strong> ' . esc_html( $entry_meta['bcc'] ) . '</span>';
                }
                $html .= '</div>';
            }
        }

        // Entry content — use body_html for client entries if available.
        $entry_body = $entry['body'] ?? '';
        $entry_body_html = $entry['body_html'] ?? '';
        if ( 'client' === $type && ! empty( $entry_body_html ) ) {
            // Strip quoted reply/forward text to avoid showing old inline images.
            $parser = new \Fanaloka\Maintenance\Email\EmailParser();
            $entry_body_html = $parser->strip_quoted_html( $entry_body_html );

            // Allow data: URIs in img src by temporarily replacing them.
            $data_uris = [];
            $entry_body_html = preg_replace_callback( '/src="(data:[^"]+)"/', function ( $m ) use ( &$data_uris ) {
                $key = '___DATA_URI_' . count( $data_uris ) . '___';
                $data_uris[] = $m[1];
                return 'src="' . $key . '"';
            }, $entry_body_html );

            // Fix malformed HTML with DOMDocument.
            $doc = new \DOMDocument( LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = false;
            @$doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $entry_body_html . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );

            // Remove inline images with non-resolvable CID refs (Gmail bare refs like "ii_xxx").
            // The actual image is saved as attachment and shown in attachment section.
            $this->strip_bare_cid_images( $doc );

            $fixed_html = $doc->saveHTML( $doc->getElementsByTagName( 'body' )->item( 0 ) );
            $fixed_html = preg_replace( '/^<body>(.*)<\/body>$/s', '$1', $fixed_html );
            $entry_body_html = wp_kses_post( $fixed_html );

            // Restore data: URIs only in img src (safe context, images only).
            $entry_body_html = preg_replace_callback( '/src="(___DATA_URI_\d+___)"/', function ( $m ) use ( $data_uris ) {
                $idx = (int) preg_replace( '/^___DATA_URI_(\d+)___$/', '$1', $m[1] );
                if ( isset( $data_uris[ $idx ] ) ) {
                    $uri = $data_uris[ $idx ];
                    if ( preg_match( '/^data:image\/(jpeg|png|gif|webp|svg\+xml);base64,/', $uri ) ) {
                        return 'src="' . esc_attr( $uri ) . '"';
                    }
                }
                return 'src=""';
            }, $entry_body_html );

            // Open all links in new tab.
            $entry_body_html = preg_replace( '/<a\b(?! [^>]*target=)([^>]*)>/i', '<a$1 target="_blank" rel="noopener">', $entry_body_html );

            // Wrap images in clickable links (open full image in new tab).
            $entry_body_html = preg_replace( '/<img\b([^>]*src="([^"]+)")>/i', '<a href="$2" target="_blank" rel="noopener"><img$1></a>', $entry_body_html );

            $html .= '<div class="fm-entry-content">' . $entry_body_html . '</div>';
        } else {
            $html .= '<div class="fm-entry-content">' . wp_kses_post( $entry_body ) . '</div>';
        }

        // Handle attachments.
        $att_ids = ! empty( $entry['attachments'] ) ? explode( ',', $entry['attachments'] ) : [];
        $att_ids = array_filter( array_map( 'absint', $att_ids ) );
        if ( ! empty( $att_ids ) ) {
            $att_count = count( $att_ids );
            $label = 1 === $att_count ? 'Attachment' : 'Attachments';
            $html .= '<div class="fm-entry-attachments">';
            $html .= '<div class="fm-attachments-label">' . esc_html( $label ) . '</div>';
            $html .= '<div class="fm-attachments-grid">';
            foreach ( $att_ids as $att_id ) {
                $url  = wp_get_attachment_url( $att_id );
                $name = get_the_title( $att_id );
                $mime = get_post_mime_type( $att_id );
                if ( ! $url ) {
                    continue;
                }
                $is_image = str_starts_with( $mime, 'image/' );
                $ext = strtoupper( pathinfo( $name, PATHINFO_EXTENSION ) );
                $size = size_format( (int) get_post_field( 'post_mime_type', $att_id ) ? 0 : 0 );
                if ( $is_image ) {
                    $html .= '<div class="fm-attachment-item fm-attachment-thumb"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">';
                    $html .= '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $name ) . '" />';
                    $html .= '</a></div>';
                } else {
                    $html .= '<a class="fm-attachment-file" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">';
                    $html .= '<span class="fm-attachment-icon">' . esc_html( $ext ) . '</span>';
                    $html .= '<span class="fm-attachment-name">' . esc_html( $name ) . '</span>';
                    $html .= '</a>';
                }
            }
            $html .= '</div></div>';
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
    private function send_reply_email( int $ticket_id, string $content, string $cc = '', string $bcc = '' ): void {
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

        // Append email signature.
        $signature = get_option( 'fm_email_signature', '' );
        if ( ! empty( $signature ) ) {
            $body_html  .= '<br><br>' . $signature;
            $body_plain .= "\n\n" . wp_strip_all_tags( $signature );
        }

        $this->set_reply_body( $body_html, $body_plain );

        // Build headers with CC/BCC.
        $headers = "Content-Type: text/html; charset=UTF-8\nMIME-Version: 1.0";
        if ( ! empty( $cc ) ) {
            $headers .= "\nCC: " . $cc;
        }
        if ( ! empty( $bcc ) ) {
            $headers .= "\nBCC: " . $bcc;
        }

        add_action( 'phpmailer_init', [ $this, 'set_reply_email_headers' ], 999 );

        wp_mail( $to, $subject, $body_html, $headers );

        remove_action( 'phpmailer_init', [ $this, 'set_reply_email_headers' ], 999 );

        $this->set_reply_body( '', '' );

        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Reply email sent to %s%s%s for ticket #%d', $to, ! empty( $cc ) ? ' CC: ' . $cc : '', ! empty( $bcc ) ? ' BCC: ' . $bcc : '', $ticket_id ) );
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

        // Allowed MIME types and max file size (10MB).
        $allowed_mimes = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'zip', 'rar', '7z',
        ];
        $max_size = 10 * 1024 * 1024; // 10MB.

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

            // File size check.
            if ( $files['size'][ $i ] > $max_size ) {
                continue;
            }

            // MIME type check — verify extension + real file type.
            $ext = strtolower( pathinfo( $files['name'][ $i ], PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $allowed_mimes, true ) ) {
                continue;
            }
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $real_mime = finfo_file( $finfo, $files['tmp_name'][ $i ] );
            finfo_close( $finfo );
            $ext_to_mime = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'txt' => 'text/plain', 'csv' => 'text/csv',
                'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed', '7z' => 'application/x-7z-compressed',
            ];
            $expected = $ext_to_mime[ $ext ] ?? '';
            if ( $expected && $real_mime !== $expected ) {
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
        $client   = isset( $_POST['client'] ) ? sanitize_email( wp_unslash( $_POST['client'] ) ) : '';
        $status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
        $priority = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : '';
        $search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $allowed_orderby = [ 'ticket_number', 'status', 'priority', 'date', '_fm_last_updated' ];
        $orderby  = isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : '_fm_last_updated';
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = '_fm_last_updated';
        }
        $order    = isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : 'DESC';
        if ( ! in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }
        $per_page = isset( $_POST['per_page'] ) ? max( 1, absint( $_POST['per_page'] ) ) : 20;

        $ticket_manager = new \Fanaloka\Maintenance\Ticket\TicketManager();
        $result         = $ticket_manager->get_tickets( [
            'per_page' => $per_page,
            'paged'    => $paged,
            'client'   => $client,
            'status'   => $status,
            'priority' => $priority,
            'search'   => $search,
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
                $html .= '<td class="column-ticket_number column-primary"><a href="' . esc_url( $view_url . $ticket['id'] ) . '"><strong>' . esc_html( $ticket['subject'] ) . ' - ' . esc_html( $ticket['id'] ) . '</strong></a></td>';
                $html .= '<td class="column-client"><strong>' . esc_html( $ticket['client_name'] ) . '</strong><br><span style="color:#8c8f94;font-size:12px;">' . esc_html( $ticket['client_email'] ) . '</span></td>';
                $html .= '<td class="column-status"><span class="fm-badge ' . esc_attr( $sc ) . '">' . esc_html( $ticket['status_label'] ?? $ticket['status'] ) . '</span></td>';
                $html .= '<td class="column-priority"><span class="fm-badge ' . esc_attr( $pc ) . '">' . esc_html( $ticket['priority_label'] ?? $ticket['priority'] ) . '</span></td>';
                $html .= '<td class="column-assigned_dev">' . esc_html( $ticket['assigned_dev_name'] ?? '-' ) . '</td>';
                $date_val = $ticket['date_created'] ?? '';
                $date_display = '';
                if ( $date_val ) {
                    $ts = strtotime( $date_val );
                    $now = time();
                    $diff = $now - $ts;
                    if ( $diff < 60 ) {
                        $date_display = sprintf( '%ds ago', $diff );
                    } elseif ( $diff < 3600 ) {
                        $date_display = sprintf( '%dm ago', floor( $diff / 60 ) );
                    } elseif ( $diff < 86400 ) {
                        $date_display = sprintf( '%dh ago', floor( $diff / 3600 ) );
                    } elseif ( $diff < 604800 ) {
                        $date_display = sprintf( '%dd ago', floor( $diff / 86400 ) );
                    } else {
                        $date_display = date( 'd M Y', $ts );
                    }
                }
                $html .= '<td class="column-date_created" title="' . esc_attr( $date_val ) . '">' . esc_html( $date_display ) . '</td>';
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
            $pagination .= '<a class="button" data-page="' . esc_attr( max( 1, $paged - 1 ) ) . '"' . ( $paged <= 1 ? ' disabled' : '' ) . '>&laquo;</a> ';
            $pagination .= '<span class="paging-input">' . esc_html( $paged ) . ' of ' . esc_html( $pages ) . '</span> ';
            $pagination .= '<a class="button" data-page="' . esc_attr( min( $pages, $paged + 1 ) ) . '"' . ( $paged >= $pages ? ' disabled' : '' ) . '>&raquo;</a>';
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

        require_once FM_PLUGIN_DIR . 'includes/class-activity-log.php';

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

        \Fanaloka\Maintenance\Log\ActivityLog::log( 'bulk_deleted', 'ticket', 0, sprintf( 'Bulk deleted %d tickets: IDs %s', $deleted, implode( ', ', $ids ) ) );

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %d: number of deleted tickets */
                __( '%d ticket(s) deleted.', 'fanaloka-maintenance' ),
                $deleted
            ),
            'deleted' => $deleted,
        ] );
    }

    /**
     * AJAX: Bulk update tickets (status, priority, developer).
     *
     * @return void
     */
    public function ajax_bulk_update_requests(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        require_once FM_PLUGIN_DIR . 'includes/class-activity-log.php';

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
        $ids    = array_filter( $ids );
        $field  = sanitize_text_field( wp_unslash( $_POST['bulk_field'] ?? '' ) );
        $value  = sanitize_text_field( wp_unslash( $_POST['bulk_value'] ?? '' ) );

        if ( empty( $ids ) ) {
            wp_send_json_error( [ 'message' => 'No tickets selected.' ] );
        }

        if ( ! in_array( $field, [ 'status', 'priority', 'developer_id' ], true ) || empty( $value ) ) {
            wp_send_json_error( [ 'message' => 'Invalid bulk action.' ] );
        }

        $ticket_manager = new \Fanaloka\Maintenance\Ticket\TicketManager();
        $updated = 0;

        foreach ( $ids as $id ) {
            switch ( $field ) {
                case 'status':
                    $ticket_manager->update_status( $id, $value );
                    break;
                case 'priority':
                    $ticket_manager->update_priority( $id, $value );
                    break;
                case 'developer_id':
                    $ticket_manager->assign_developer( $id, absint( $value ) );
                    break;
            }
            update_post_meta( $id, '_fm_last_updated', time() );
            ++$updated;
        }

        \Fanaloka\Maintenance\Log\ActivityLog::log( 'bulk_updated', 'ticket', 0, sprintf( 'Bulk updated %d tickets: %s = %s', $updated, $field, $value ) );

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %d: number of updated tickets */
                __( '%d ticket(s) updated.', 'fanaloka-maintenance' ),
                $updated
            ),
            'updated' => $updated,
        ] );
    }

    /**
     * AJAX: List all unique clients from tickets.
     *
     * @return void
     */
    public function ajax_list_clients(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        $clients = $this->get_unique_clients( $search );

        // Stats.
        $total_clients  = count( $clients );
        $total_tickets  = 0;
        foreach ( $clients as &$c ) {
            $total_tickets += $c['total'];
        }
        unset( $c );

        // Build HTML.
        $html = '';
        if ( empty( $clients ) ) {
            $html = '<div style="text-align:center;padding:40px;color:#8c8f94;">No clients found.</div>';
        } else {
            $colors = [ '#2271b1', '#00a32a', '#dba617', '#d63638', '#996800', '#8c8f94' ];
            foreach ( $clients as $client ) {
                $initials = strtoupper( substr( $client['name'], 0, 1 ) );
                $color    = $colors[ crc32( $client['email'] ) % count( $colors ) ];

                $html .= '<div class="fm-client-row" data-email="' . esc_attr( $client['email'] ) . '" data-name="' . esc_attr( $client['name'] ) . '">';
                $html .= '<div class="fm-client-info">';
                $html .= '<div class="fm-client-avatar" style="background:' . esc_attr( $color ) . ';">' . esc_html( $initials ) . '</div>';
                $html .= '<div>';
                $html .= '<div class="fm-client-name">' . esc_html( $client['name'] ?: $client['email'] ) . '</div>';
                $html .= '<div class="fm-client-email">' . esc_html( $client['email'] ) . '</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="fm-client-stats">';
                $html .= '<div class="fm-client-stat"><span class="fm-client-stat-num">' . esc_html( $client['total'] ) . '</span><span class="fm-client-stat-label">Tickets</span></div>';
                $html .= '<div class="fm-client-stat"><span class="fm-client-stat-num">' . esc_html( $client['open'] ) . '</span><span class="fm-client-stat-label">Open</span></div>';
                $html .= '<div class="fm-client-stat"><span class="fm-client-stat-num">' . esc_html( $client['completed'] ) . '</span><span class="fm-client-stat-label">Done</span></div>';
                $html .= '<span class="fm-client-arrow dashicons dashicons-arrow-right-alt2"></span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }

        wp_send_json_success( [
            'html'          => $html,
            'total_clients' => $total_clients,
            'total_tickets' => $total_tickets,
        ] );
    }

    /**
     * Get unique clients from tickets.
     *
     * @param string $search Search query.
     * @return array<int, array{email: string, name: string, total: int, open: int, completed: int}>
     */
    private function get_unique_clients( string $search = '' ): array {
        global $wpdb;

        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        if ( $search ) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => '_fm_client_name',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => '_fm_client_email',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
            ];
        }

        $query   = new \WP_Query( $args );
        $clients = [];

        foreach ( $query->posts as $post_id ) {
            $email = get_post_meta( $post_id, '_fm_client_email', true );
            $name  = get_post_meta( $post_id, '_fm_client_name', true );

            if ( empty( $email ) ) {
                continue;
            }

            if ( ! isset( $clients[ $email ] ) ) {
                $clients[ $email ] = [
                    'email'     => $email,
                    'name'      => $name,
                    'total'     => 0,
                    'open'      => 0,
                    'completed' => 0,
                ];
            }

            $clients[ $email ]['total']++;

            $status = get_post_meta( $post_id, '_fm_status', true );
            if ( in_array( $status, [ 'new', 'open', 'in-progress', 'waiting-client' ], true ) ) {
                $clients[ $email ]['open']++;
            } elseif ( 'completed' === $status ) {
                $clients[ $email ]['completed']++;
            }
        }

        // Sort by total desc.
        uasort( $clients, function ( $a, $b ) {
            return $b['total'] - $a['total'];
        } );

        return array_values( $clients );
    }

    /**
     * AJAX: Get tickets for a specific client.
     *
     * @return void
     */
    public function ajax_get_client_tickets(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

        if ( empty( $email ) ) {
            wp_send_json_error( [ 'message' => 'Invalid email' ] );
        }

        $query = new \WP_Query( [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_fm_client_email',
                    'value' => $email,
                ],
            ],
            'orderby'  => 'date',
            'order'    => 'DESC',
        ] );

        $name = '';
        $total     = 0;
        $open      = 0;
        $completed = 0;

        $status_colors = [
            'new'            => 'fm-badge-primary',
            'open'           => 'fm-badge-warning',
            'in-progress'    => 'fm-badge-success',
            'waiting-client' => 'fm-badge-warning',
            'completed'      => 'fm-badge-success',
            'cancelled'      => 'fm-badge-danger',
        ];

        $tickets_html = '';
        foreach ( $query->posts as $post ) {
            $post_id = is_object( $post ) ? $post->ID : $post;
            if ( ! $name ) {
                $name = get_post_meta( $post_id, '_fm_client_name', true );
            }
            $total++;
            $status      = get_post_meta( $post_id, '_fm_status', true );
            $priority    = get_post_meta( $post_id, '_fm_priority', true );
            $full_number = get_post_meta( $post_id, '_fm_full_number', true );
            $subject     = get_the_title( $post_id );

            if ( in_array( $status, [ 'new', 'open', 'in-progress', 'waiting-client' ], true ) ) {
                $open++;
            } elseif ( 'completed' === $status ) {
                $completed++;
            }

            $sc = $status_colors[ $status ] ?? 'fm-badge-default';
            $view_url = admin_url( 'admin.php?page=fm-requests&action=view&id=' . $post_id );

            $tickets_html .= '<a href="' . esc_url( $view_url ) . '" class="fm-modal-ticket-row">';
            $tickets_html .= '<span class="fm-modal-ticket-num">' . esc_html( $full_number ) . '</span>';
            $tickets_html .= '<span class="fm-modal-ticket-subject">' . esc_html( $subject ) . '</span>';
            $tickets_html .= '<span class="fm-badge ' . esc_attr( $sc ) . '">' . esc_html( ucfirst( str_replace( '-', ' ', $status ) ) ) . '</span>';
            $tickets_html .= '</a>';
        }

        if ( empty( $tickets_html ) ) {
            $tickets_html = '<div style="text-align:center;padding:20px;color:#8c8f94;">No tickets found.</div>';
        }

        $info_html  = '<div class="fm-modal-info-item fm-modal-info-email"><strong>' . esc_html( $email ) . '</strong>Email</div>';
        $info_html .= '<div class="fm-modal-info-stats">';
        $info_html .= '<div class="fm-modal-info-item"><strong>' . esc_html( $total ) . '</strong>Total Tickets</div>';
        $info_html .= '<div class="fm-modal-info-item"><strong>' . esc_html( $open ) . '</strong>Open</div>';
        $info_html .= '<div class="fm-modal-info-item"><strong>' . esc_html( $completed ) . '</strong>Completed</div>';
        $info_html .= '</div>';

        wp_send_json_success( [
            'info_html'    => $info_html,
            'tickets_html' => $tickets_html,
        ] );
    }

    /**
     * AJAX: List all developers from WordPress users with assigned tickets.
     *
     * @return void
     */
    public function ajax_list_developers(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        $developers = $this->get_developers( $search );

        $total_devs    = count( $developers );
        $total_tickets = 0;
        foreach ( $developers as &$d ) {
            $total_tickets += $d['total'];
        }
        unset( $d );

        $html = '';
        if ( empty( $developers ) ) {
            $html = '<div style="text-align:center;padding:40px;color:#8c8f94;">No developers found.</div>';
        } else {
            $colors = [ '#2271b1', '#00a32a', '#dba617', '#d63638', '#996800', '#8c8f94' ];
            foreach ( $developers as $dev ) {
                $initials = strtoupper( substr( $dev['display_name'], 0, 1 ) );
                $color    = $colors[ crc32( $dev['email'] ) % count( $colors ) ];
                $role_badge = esc_html( ucfirst( str_replace( '_', ' ', $dev['role'] ) ) );

                $html .= '<div class="fm-dev-row" data-user-id="' . esc_attr( $dev['id'] ) . '" data-name="' . esc_attr( $dev['display_name'] ) . '">';
                $html .= '<div class="fm-dev-info">';
                $html .= '<div class="fm-dev-avatar" style="background:' . esc_attr( $color ) . ';">' . esc_html( $initials ) . '</div>';
                $html .= '<div>';
                $html .= '<div class="fm-dev-name">' . esc_html( $dev['display_name'] ) . '</div>';
                $html .= '<div class="fm-dev-email">' . esc_html( $dev['email'] ) . '</div>';
                $html .= '<span class="fm-dev-role">' . $role_badge . '</span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="fm-dev-stats">';
                $html .= '<div class="fm-dev-stat"><span class="fm-dev-stat-num">' . esc_html( $dev['total'] ) . '</span><span class="fm-dev-stat-label">Assigned</span></div>';
                $html .= '<div class="fm-dev-stat"><span class="fm-dev-stat-num">' . esc_html( $dev['open'] ) . '</span><span class="fm-dev-stat-label">Open</span></div>';
                $html .= '<div class="fm-dev-stat"><span class="fm-dev-stat-num">' . esc_html( $dev['completed'] ) . '</span><span class="fm-dev-stat-label">Done</span></div>';
                $html .= '<span class="fm-dev-arrow dashicons dashicons-arrow-right-alt2"></span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }

        wp_send_json_success( [
            'html'           => $html,
            'total_devs'     => $total_devs,
            'total_tickets'  => $total_tickets,
        ] );
    }

    /**
     * Get developers (WP users with certain roles) and their assigned ticket counts.
     *
     * @param string $search Search query.
     * @return array<int, array{id: int, display_name: string, email: string, role: string, total: int, open: int, completed: int}>
     */
    private function get_developers( string $search = '' ): array {
        $developer_roles = [ 'administrator', 'editor', 'author', 'contributor' ];

        $args = [
            'role__in'       => $developer_roles,
            'fields'         => [ 'ID', 'user_email', 'display_name', 'user_login' ],
            'orderby'        => 'display_name',
            'order'          => 'ASC',
            'number'         => 200,
            'hide_empty'     => true,
        ];

        if ( $search ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'display_name', 'user_email', 'user_login' ];
        }

        $wp_users = get_users( $args );

        // Fetch all tickets once.
        $all_query = new \WP_Query( [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        // Group ticket IDs by _fm_assigned_dev.
        $tickets_by_dev = [];

        foreach ( $all_query->posts as $post_id ) {
            $dev_id = absint( get_post_meta( $post_id, '_fm_assigned_dev', true ) );
            if ( $dev_id > 0 ) {
                if ( ! isset( $tickets_by_dev[ $dev_id ] ) ) {
                    $tickets_by_dev[ $dev_id ] = [];
                }
                $tickets_by_dev[ $dev_id ][] = $post_id;
            }
        }

        $developers = [];

        foreach ( $wp_users as $user ) {
            $dev_id = $user->ID;
            $user_tickets = $tickets_by_dev[ $dev_id ] ?? [];
            $total     = count( $user_tickets );
            $open      = 0;
            $completed = 0;

            foreach ( $user_tickets as $tid ) {
                $status = get_post_meta( $tid, '_fm_status', true );
                if ( in_array( $status, [ 'new', 'open', 'in-progress', 'waiting-client' ], true ) ) {
                    $open++;
                } elseif ( 'completed' === $status ) {
                    $completed++;
                }
            }

            $roles = get_userdata( $dev_id );
            $role  = '';
            if ( $roles && ! empty( $roles->roles ) ) {
                $role = array_values( $roles->roles )[0];
            }

            $developers[] = [
                'id'           => $dev_id,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'role'         => $role ?: 'developer',
                'total'        => $total,
                'open'         => $open,
                'completed'    => $completed,
            ];
        }

        // Sort by total desc.
        usort( $developers, function ( $a, $b ) {
            return $b['total'] - $a['total'];
        } );

        return $developers;
    }

    /**
     * AJAX: Get tickets for a specific developer.
     *
     * @return void
     */
    public function ajax_get_developer_tickets(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $user_id = absint( $_POST['user_id'] ?? 0 );

        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'Invalid developer' ] );
        }

        $user_data = get_userdata( $user_id );
        $display_name = $user_data ? $user_data->display_name : 'Unknown';
        $email = $user_data ? $user_data->user_email : '';

        $query = new \WP_Query( [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_fm_assigned_dev',
                    'value' => $user_id,
                ],
            ],
            'orderby' => 'date',
            'order'   => 'DESC',
        ] );

        $total     = 0;
        $open      = 0;
        $completed = 0;

        $status_colors = [
            'new'            => 'fm-badge-primary',
            'open'           => 'fm-badge-warning',
            'in-progress'    => 'fm-badge-success',
            'waiting-client' => 'fm-badge-warning',
            'completed'      => 'fm-badge-success',
            'cancelled'      => 'fm-badge-danger',
        ];

        $tickets_html = '';
        foreach ( $query->posts as $post ) {
            $post_id = is_object( $post ) ? $post->ID : $post;
            $total++;
            $status      = get_post_meta( $post_id, '_fm_status', true );
            $full_number = get_post_meta( $post_id, '_fm_full_number', true );
            $subject     = get_the_title( $post_id );

            if ( in_array( $status, [ 'new', 'open', 'in-progress', 'waiting-client' ], true ) ) {
                $open++;
            } elseif ( 'completed' === $status ) {
                $completed++;
            }

            $sc       = $status_colors[ $status ] ?? 'fm-badge-default';
            $view_url = admin_url( 'admin.php?page=fm-requests&action=view&id=' . $post_id );

            $tickets_html .= '<a href="' . esc_url( $view_url ) . '" class="fm-modal-ticket-row">';
            $tickets_html .= '<span class="fm-modal-ticket-num">' . esc_html( $full_number ) . '</span>';
            $tickets_html .= '<span class="fm-modal-ticket-subject">' . esc_html( $subject ) . '</span>';
            $tickets_html .= '<span class="fm-badge ' . esc_attr( $sc ) . '">' . esc_html( ucfirst( str_replace( '-', ' ', $status ) ) ) . '</span>';
            $tickets_html .= '</a>';
        }

        if ( empty( $tickets_html ) ) {
            $tickets_html = '<div style="text-align:center;padding:20px;color:#8c8f94;">No tickets assigned yet.</div>';
        }

        $role = '';
        if ( $user_data && ! empty( $user_data->roles ) ) {
            $role = array_values( $user_data->roles )[0];
        }

        $info_html  = '<div class="fm-modal-info-main">';
        $info_html .= '<div class="fm-modal-info-item"><strong>' . esc_html( $display_name ) . '</strong>Name</div>';
        $info_html .= '<div class="fm-modal-info-item"><strong>' . esc_html( $email ) . '</strong>Email</div>';
        $info_html .= '<div class="fm-modal-info-item"><strong>' . esc_html( ucfirst( str_replace( '_', ' ', $role ) ) ) . '</strong>Role</div>';
        $info_html .= '</div>';
        $info_html .= '<div class="fm-modal-info-stats">';
        $info_html .= '<div class="fm-modal-info-item"><strong>' . esc_html( $total ) . '</strong>Total Assigned</div>';
        $info_html .= '<div class="fm-modal-info-item"><strong>' . esc_html( $open ) . '</strong>Open</div>';
        $info_html .= '<div class="fm-modal-info-item"><strong>' . esc_html( $completed ) . '</strong>Completed</div>';
        $info_html .= '</div>';

        wp_send_json_success( [
            'info_html'    => $info_html,
            'tickets_html' => $tickets_html,
        ] );
    }

    /**
     * Get relative time string (e.g. "5 minutes ago", "a day ago").
     *
     * @param string $datetime MySQL datetime string.
     * @return string Human-readable relative time.
     */
    private function get_relative_time( string $datetime ): string {
        $now  = new \DateTime();
        $past = new \DateTime( $datetime );
        $diff = $now->diff( $past );

        if ( $diff->y > 0 ) {
            return $diff->y === 1 ? 'a year ago' : sprintf( '%d years ago', $diff->y );
        }
        if ( $diff->m > 0 && $diff->d === 0 ) {
            return $diff->m === 1 ? 'a month ago' : sprintf( '%d months ago', $diff->m );
        }
        if ( $diff->d > 0 ) {
            if ( $diff->d === 1 ) {
                return 'yesterday';
            }
            if ( $diff->d < 7 ) {
                return sprintf( '%d days ago', $diff->d );
            }
            $weeks = (int) floor( $diff->d / 7 );
            return $weeks === 1 ? 'a week ago' : sprintf( '%d weeks ago', $weeks );
        }
        if ( $diff->h > 0 ) {
            return $diff->h === 1 ? 'an hour ago' : sprintf( '%d hours ago', $diff->h );
        }
        if ( $diff->i > 0 ) {
            return $diff->i === 1 ? 'a minute ago' : sprintf( '%d minutes ago', $diff->i );
        }

        return 'just now';
    }
}
