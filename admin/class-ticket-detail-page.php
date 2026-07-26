<?php
/**
 * Ticket Detail Page.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

use Fanaloka\Maintenance\Ticket\TicketManager;
use Fanaloka\Maintenance\Ticket\ConversationManager;
use Fanaloka\Maintenance\Attachment\AttachmentManager;
use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TicketDetailPage Class.
 */
class TicketDetailPage {

    /**
     * Current ticket ID.
     *
     * @var int
     */
    private int $ticket_id = 0;

    /**
     * Constructor.
     *
     * @param int $ticket_id Optional ticket ID.
     */
    public function __construct( int $ticket_id = 0 ) {
        if ( $ticket_id ) {
            $this->ticket_id = $ticket_id;
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $this->ticket_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        }
    }

    /**
     * Render the ticket detail page.
     *
     * @return void
     */
    public function render(): void {
        if ( ! $this->ticket_id ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Invalid ticket.', 'fanaloka-maintenance' ) . '</p></div>';
            return;
        }

        wp_enqueue_editor();
        wp_enqueue_media();
        wp_enqueue_style( 'editor-style' );

        $ticket_manager = new TicketManager();
        $ticket         = $ticket_manager->get_ticket_meta( $this->ticket_id );

        if ( empty( $ticket ) ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Ticket not found.', 'fanaloka-maintenance' ) . '</p></div>';
            return;
        }

        $conversation   = new ConversationManager();
        $entries        = $conversation->get_entries( $this->ticket_id );
        $attachments    = new AttachmentManager();
        $files          = $attachments->get_ticket_attachments( $this->ticket_id );
        $developers     = $this->get_developer_options();

        $status_colors = [
            'new'             => '#2271b1',
            'open'            => '#dba617',
            'in-progress'     => '#996800',
            'waiting-client'  => '#00a32a',
            'completed'       => '#00a32a',
            'cancelled'       => '#d63638',
        ];
        $priority_colors = [
            'low'      => '#646970',
            'medium'   => '#2271b1',
            'high'     => '#dba617',
            'critical' => '#d63638',
        ];
        $status_color = $status_colors[ $ticket['status'] ?? '' ] ?? '#646970';
        $priority_color = $priority_colors[ $ticket['priority'] ?? '' ] ?? '#646970';
        ?>

        <div class="wrap fm-ticket-wrap">
            <!-- Ticket Header -->
            <div class="fm-ticket-header">
                <div class="fm-ticket-header-left">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-requests' ) ); ?>" class="fm-ticket-back">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </a>
                    <div class="fm-ticket-title-area">
                        <h1 class="fm-ticket-title"><?php echo esc_html( $ticket['subject'] ?? '' ); ?></h1>
                        <span class="fm-ticket-number"><?php echo esc_html( $ticket['full_number'] ?? '' ); ?></span>
                    </div>
                </div>
                <div class="fm-ticket-header-right">
                    <span class="fm-badge" style="background:<?php echo esc_attr( $status_color ); ?>;"><?php echo esc_html( ucfirst( str_replace( '-', ' ', $ticket['status'] ?? '' ) ) ); ?></span>
                    <span class="fm-badge fm-badge-outline" style="color:<?php echo esc_attr( $priority_color ); ?>; border-color:<?php echo esc_attr( $priority_color ); ?>;"><?php echo esc_html( ucfirst( $ticket['priority'] ?? '' ) ); ?></span>
                </div>
            </div>

            <div class="fm-ticket-body">
                <!-- Conversation Column -->
                <div class="fm-ticket-conversation">
                    <?php if ( empty( $entries ) ) : ?>
                        <div class="fm-empty-state">
                            <span class="dashicons dashicons-format-chat"></span>
                            <p><?php esc_html_e( 'No conversation entries yet.', 'fanaloka-maintenance' ); ?></p>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $entries as $entry ) :
                            $initials = strtoupper( substr( $entry['sender'], 0, 1 ) );
                            $entry_color = 'developer' === $entry['entry_type'] ? '#2271b1' : ( 'client' === $entry['entry_type'] ? '#00a32a' : '#646970' );
                        ?>
                            <div class="fm-entry fm-entry-<?php echo esc_attr( $entry['entry_type'] ); ?>" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>">
                                <div class="fm-entry-avatar" style="background:<?php echo esc_attr( $entry_color ); ?>;">
                                    <?php echo esc_html( $initials ); ?>
                                </div>
                                <div class="fm-entry-body">
                                    <div class="fm-entry-meta">
                                        <strong class="fm-entry-sender"><?php echo esc_html( $entry['sender'] ); ?></strong>
                                        <span class="fm-entry-type-badge" style="background:<?php echo esc_attr( $entry_color ); ?>15;color:<?php echo esc_attr( $entry_color ); ?>;"><?php echo esc_html( ucfirst( $entry['entry_type'] ) ); ?></span>
                                        <span class="fm-entry-date"><?php echo esc_html( $entry['created_at'] ); ?></span>
                                    </div>
                                    <div class="fm-entry-content">
                                        <?php
                                        if ( 'developer' === $entry['entry_type'] || 'internal' === $entry['entry_type'] ) {
                                            echo wp_kses_post( $entry['body'] );
                                        } elseif ( 'client' === $entry['entry_type'] && ! empty( $entry['body_html'] ) ) {
                                            echo wp_kses_post( $entry['body_html'] );
                                        } else {
                                            echo wp_kses_post( nl2br( esc_html( $entry['body'] ) ) );
                                        }
                                        ?>
                                    </div>
                                    <?php
                                    $entry_attachments = ! empty( $entry['attachments'] ) ? explode( ',', $entry['attachments'] ) : [];
                                    if ( ! empty( $entry_attachments ) ) : ?>
                                        <div class="fm-entry-attachments">
                                            <?php foreach ( $entry_attachments as $att_id ) :
                                                $att_id = absint( $att_id );
                                                $url    = wp_get_attachment_url( $att_id );
                                                $name   = get_the_title( $att_id );
                                                $is_image = wp_attachment_is_image( $att_id );
                                                if ( $url ) : ?>
                                                    <div class="fm-attachment-item">
                                                        <?php if ( $is_image ) : ?>
                                                            <a href="<?php echo esc_url( $url ); ?>" target="_blank">
                                                                <img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $name ); ?>" />
                                                            </a>
                                                        <?php else : ?>
                                                            <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="fm-attachment-file">
                                                                <span class="dashicons dashicons-media-default"></span>
                                                                <?php echo esc_html( $name ); ?>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif;
                                            endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Reply Form -->
                    <div class="fm-reply-box">
                        <div class="fm-reply-header">
                            <span class="dashicons dashicons-edit"></span>
                            <strong><?php esc_html_e( 'Reply', 'fanaloka-maintenance' ); ?></strong>
                        </div>
                        <form method="post" id="fm-reply-form" enctype="multipart/form-data">
                            <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $this->ticket_id ); ?>" />
                            <input type="hidden" name="fm_action" value="reply" />
                            <?php wp_nonce_field( 'fm_reply_ticket' ); ?>
                            <div id="reply-editor-wrap">
                                <?php
                                wp_editor( '', 'reply_content', [
                                    'textarea_name' => 'reply_content',
                                    'textarea_rows' => 6,
                                    'media_buttons' => true,
                                    'teeny'         => false,
                                    'quicktags'     => true,
                                    'tinymce'       => [
                                        'toolbar1' => 'bold,italic,underline,strikethrough,bullist,numlist,link,unlink,formatselect',
                                        'toolbar2' => '',
                                    ],
                                ] );
                                ?>
                            </div>
                            <div class="fm-reply-footer">
                                <div class="fm-reply-attach">
                                    <label class="fm-attach-btn">
                                        <span class="dashicons dashicons-paperclip"></span>
                                        <?php esc_html_e( 'Attach files', 'fanaloka-maintenance' ); ?>
                                        <input type="file" name="reply_attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip" style="display:none;" />
                                    </label>
                                    <span class="description"><?php esc_html_e( 'JPG, PNG, PDF, DOC, DOCX, ZIP', 'fanaloka-maintenance' ); ?></span>
                                </div>
                                <?php submit_button( __( 'Send Reply', 'fanaloka-maintenance' ), 'primary', 'submit', false, [ 'class' => 'fm-reply-submit' ] ); ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="fm-ticket-sidebar">
                    <div class="fm-sidebar-section">
                        <h3 class="fm-sidebar-title"><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e( 'Details', 'fanaloka-maintenance' ); ?></h3>
                        <div class="fm-sidebar-content">
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></label>
                                <select class="fm-ajax-field fm-field-select" data-ticket-id="<?php echo esc_attr( $this->ticket_id ); ?>" data-field="status">
                                    <?php foreach ( TicketManager::STATUSES as $key => $label ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $ticket['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Priority', 'fanaloka-maintenance' ); ?></label>
                                <select class="fm-ajax-field fm-field-select" data-ticket-id="<?php echo esc_attr( $this->ticket_id ); ?>" data-field="priority">
                                    <?php foreach ( TicketManager::PRIORITIES as $key => $label ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $ticket['priority'], $key ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Assignee', 'fanaloka-maintenance' ); ?></label>
                                <select class="fm-ajax-field fm-field-select" data-ticket-id="<?php echo esc_attr( $this->ticket_id ); ?>" data-field="developer_id">
                                    <option value="0"><?php esc_html_e( 'Unassigned', 'fanaloka-maintenance' ); ?></option>
                                    <?php foreach ( $developers as $id => $name ) : ?>
                                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $ticket['assigned_dev'], $id ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="fm-sidebar-section">
                        <h3 class="fm-sidebar-title"><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Requester', 'fanaloka-maintenance' ); ?></h3>
                        <div class="fm-sidebar-content">
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Name', 'fanaloka-maintenance' ); ?></label>
                                <span><?php echo esc_html( $ticket['client_name'] ?? '' ); ?></span>
                            </div>
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Email', 'fanaloka-maintenance' ); ?></label>
                                <span><a href="mailto:<?php echo esc_attr( $ticket['client_email'] ?? '' ); ?>"><?php echo esc_html( $ticket['client_email'] ?? '' ); ?></a></span>
                            </div>
                        </div>
                    </div>

                    <div class="fm-sidebar-section">
                        <h3 class="fm-sidebar-title"><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Dates', 'fanaloka-maintenance' ); ?></h3>
                        <div class="fm-sidebar-content">
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Created', 'fanaloka-maintenance' ); ?></label>
                                <span><?php echo esc_html( $ticket['date_created'] ?? '' ); ?></span>
                            </div>
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Updated', 'fanaloka-maintenance' ); ?></label>
                                <span><?php echo esc_html( $ticket['last_updated'] ?? '' ); ?></span>
                            </div>
                            <?php if ( ! empty( $ticket['completion_date'] ) ) : ?>
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Completed', 'fanaloka-maintenance' ); ?></label>
                                <span><?php echo esc_html( $ticket['completion_date'] ); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( ! empty( $files ) ) : ?>
                    <div class="fm-sidebar-section">
                        <h3 class="fm-sidebar-title"><span class="dashicons dashicons-paperclip"></span> <?php esc_html_e( 'Attachments', 'fanaloka-maintenance' ); ?></h3>
                        <div class="fm-sidebar-content">
                            <?php foreach ( $files as $file ) : ?>
                                <a href="<?php echo esc_url( $file['url'] ); ?>" target="_blank" class="fm-file-link">
                                    <span class="dashicons dashicons-media-default"></span>
                                    <span class="fm-file-name"><?php echo esc_html( $file['name'] ); ?></span>
                                    <span class="fm-file-size">(<?php echo esc_html( $file['size'] ); ?>)</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
        /* Shared Design System */
        .fm-page-wrap { max-width: 1400px; margin: 0 auto; padding: 0 0 40px; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-page-columns { display: flex; gap: 20px; align-items: flex-start; }
        .fm-page-main { flex: 1; min-width: 0; }
        .fm-page-sidebar { width: 300px; flex-shrink: 0; }

        /* Ticket Header */
        .fm-ticket-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-ticket-header-left { display: flex; align-items: center; gap: 15px; }
        .fm-ticket-back { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 6px; background: #f0f0f1; color: #1d2327; text-decoration: none; transition: background 0.15s; }
        .fm-ticket-back:hover { background: #ddd; color: #1d2327; }
        .fm-ticket-title { margin: 0; font-size: 20px; line-height: 1.3; }
        .fm-ticket-number { font-size: 13px; color: #646970; }
        .fm-ticket-header-right { display: flex; gap: 8px; align-items: center; }

        /* Badges */
        .fm-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #fff; line-height: 1.4; }
        .fm-badge-outline { background: transparent; border: 1.5px solid; }
        .fm-badge-success { background: #00a32a; }
        .fm-badge-danger { background: #d63638; }

        /* Conversation & Sidebar */
        .fm-ticket-body { display: flex; gap: 20px; align-items: flex-start; }
        .fm-ticket-conversation { flex: 1; min-width: 0; }
        .fm-ticket-sidebar { width: 300px; flex-shrink: 0; }

        /* Entry */
        .fm-entry { display: flex; gap: 12px; padding: 16px 0; border-bottom: 1px solid #f0f0f1; }
        .fm-entry:last-child { border-bottom: none; }
        .fm-entry-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; font-weight: 700; flex-shrink: 0; }
        .fm-entry-body { flex: 1; min-width: 0; }
        .fm-entry-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
        .fm-entry-sender { font-size: 14px; color: #1d2327; }
        .fm-entry-type-badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .fm-entry-date { font-size: 12px; color: #8c8f94; }
        .fm-entry-content { font-size: 14px; line-height: 1.6; color: #1d2327; }
        .fm-entry-content p { margin: 0 0 8px; }
        .fm-entry-content p:last-child { margin-bottom: 0; }
        .fm-entry-attachments { margin-top: 10px; }
        .fm-entry-attachments .fm-attachment-item { display: inline-block; margin-right: 8px; margin-bottom: 8px; }
        .fm-entry-attachments img { max-width: 280px; max-height: 180px; border-radius: 6px; border: 1px solid #e2e4e7; }
        .fm-attachment-file { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #f0f0f1; border-radius: 6px; font-size: 13px; color: #2271b1; text-decoration: none; }
        .fm-attachment-file:hover { background: #e0e0e1; color: #135e96; }

        /* Empty State */
        .fm-empty-state { text-align: center; padding: 60px 20px; color: #8c8f94; }
        .fm-empty-state .dashicons { font-size: 48px; width: 48px; height: 48px; margin-bottom: 15px; color: #c3c4c7; }

        /* Reply Box */
        .fm-reply-box { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden; }
        .fm-reply-header { display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: #f9f9f9; border-bottom: 1px solid #e2e4e7; font-size: 14px; color: #1d2327; }
        .fm-reply-box .inside { padding: 0; }
        .fm-reply-box #reply-editor-wrap { padding: 0; }
        .fm-reply-footer { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-top: 1px solid #e2e4e7; background: #f9f9f9; }
        .fm-reply-attach { display: flex; align-items: center; gap: 10px; }
        .fm-attach-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #fff; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-size: 13px; color: #1d2327; transition: background 0.15s; }
        .fm-attach-btn:hover { background: #f0f0f1; }
        .fm-reply-submit { margin: 0 !important; }

        /* Sidebar Sections */
        .fm-sidebar-section { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-sidebar-title { display: flex; align-items: center; gap: 6px; margin: 0; padding: 10px 14px; font-size: 13px; font-weight: 600; color: #1d2327; background: #f9f9f9; border-bottom: 1px solid #e2e4e7; }
        .fm-sidebar-title .dashicons { color: #2271b1; font-size: 16px; }
        .fm-sidebar-content { padding: 12px 14px; }

        /* Field Rows */
        .fm-field-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #f0f0f1; }
        .fm-field-row:last-child { border-bottom: none; }
        .fm-field-row label { font-size: 12px; color: #646970; font-weight: 500; }
        .fm-field-row span { font-size: 13px; color: #1d2327; text-align: right; }
        .fm-field-row span a { color: #2271b1; text-decoration: none; }
        .fm-field-row span a:hover { text-decoration: underline; }
        .fm-field-select { width: auto; min-width: 130px; font-size: 13px; }
        .fm-file-link { display: flex; align-items: center; gap: 6px; padding: 6px 0; font-size: 13px; color: #2271b1; text-decoration: none; border-bottom: 1px solid #f0f0f1; }
        .fm-file-link:last-child { border-bottom: none; }
        .fm-file-link:hover { color: #135e96; }
        .fm-file-size { color: #8c8f94; font-size: 12px; }
        </style>
        <?php
    }

    /**
     * Handle form actions.
     *
     * @return void
     */
    public function handle_actions(): void {
        // Only handle on our page.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_GET['page'] ) || 'fm-requests' !== $_GET['page'] ) {
            return;
        }

        if ( ! isset( $_POST['fm_action'] ) ) {
            return;
        }

        if ( ! $this->ticket_id ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $action = sanitize_text_field( wp_unslash( $_POST['fm_action'] ) );

        // Verify nonce based on action.
        $nonce_valid = false;

        if ( 'reply' === $action ) {
            $nonce_valid = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fm_reply_ticket' );
        } else {
            $nonce_valid = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fm_update_ticket' );
        }

        if ( ! $nonce_valid ) {
            return;
        }

        $ticket_manager = new TicketManager();

        switch ( $action ) {
            case 'update_status':
                $status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
                $ticket_manager->update_status( $this->ticket_id, $status );
                break;

            case 'update_priority':
                $priority = sanitize_text_field( wp_unslash( $_POST['priority'] ?? '' ) );
                $ticket_manager->update_priority( $this->ticket_id, $priority );
                break;

            case 'assign_developer':
                $dev_id = absint( $_POST['developer_id'] ?? 0 );
                $ticket_manager->assign_developer( $this->ticket_id, $dev_id );
                break;

            case 'reply':
                $content = wp_kses_post( wp_unslash( $_POST['reply_content'] ?? '' ) );
                if ( ! empty( $content ) ) {
                    $conversation = new ConversationManager();
                    $entry_id = $conversation->add_reply_from_developer( $this->ticket_id, $content );

                    // Handle file uploads.
                    if ( ! empty( $_FILES['reply_attachments']['name'][0] ) && $entry_id ) {
                        $this->handle_reply_attachments( $this->ticket_id, $entry_id );
                    }

                    // Send email to client (with attachments).
                    $this->send_reply_email( $content, $this->ticket_id );
                }
                break;
        }

        // Redirect to avoid resubmission via JavaScript (avoids headers issue).
        $url = esc_url_raw( admin_url( 'admin.php?page=fm-requests&action=view&id=' . $this->ticket_id ) );
        echo '<script>window.location.replace("' . esc_js( $url ) . '");</script>';
        echo '<meta http-equiv="refresh" content="0;url=' . esc_attr( $url ) . '">';
        exit;
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
            \Fanaloka\Maintenance\Logger\Logger::log( 'handle_reply_attachments: no files uploaded' );
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
                \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Attachment skip: name=%s error=%d', $files['name'][ $i ] ?? '', $files['error'][ $i ] ?? -1 ) );
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
                \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Attachment moved to: %s', $file_path ) );
                $attachment_id = $this->save_to_media( $file_path, $filename, $ticket_id );
                \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Attachment media ID: %s for %s', $attachment_id ?: 'false', $filename ) );
                if ( $attachment_id ) {
                    $saved_ids[] = $attachment_id;
                }
            } else {
                \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Attachment move FAILED: %s to %s', $files['tmp_name'][ $i ], $file_path ) );
            }
        }

        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'handle_reply_attachments: saved_ids=%s for entry_id=%d', implode( ',', $saved_ids ), $entry_id ) );

        if ( ! empty( $saved_ids ) ) {
            $existing = $wpdb->get_var(
                $wpdb->prepare( "SELECT attachments FROM {$table} WHERE id = %d", $entry_id )
            );
            $all = ! empty( $existing ) ? $existing . ',' . implode( ',', $saved_ids ) : implode( ',', $saved_ids );
            $wpdb->update( $table, [ 'attachments' => $all ], [ 'id' => $entry_id ], [ '%s' ], [ '%d' ] );
            \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'DB updated: entry_id=%d attachments=%s', $entry_id, $all ) );
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
     * Send reply email to client.
     *
     * @param string $content   Reply content.
     * @param int    $ticket_id Ticket ID.
     * @return void
     */
    private function send_reply_email( string $content, int $ticket_id = 0 ): void {
        $ticket_id = $ticket_id ?: $this->ticket_id;
        $ticket    = ( new TicketManager() )->get_ticket_meta( $ticket_id );
        $to        = $ticket['client_email'] ?? '';
        $subject   = sprintf( 'Re: %s', $ticket['subject'] ?? '' );

        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'send_reply_email START: to=%s ticket=%d content_len=%d', $to, $ticket_id, strlen( $content ) ) );

        $conversation = new ConversationManager();
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

        // Get attachment file paths from the latest developer entry.
        $attachment_files = [];
        foreach ( array_reverse( $all_entries ) as $entry ) {
            if ( 'developer' === $entry['entry_type'] && ! empty( $entry['attachments'] ) ) {
                $att_ids = array_filter( array_map( 'absint', explode( ',', $entry['attachments'] ) ) );
                \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Reply attachments found: entry_id=%d att_ids=%s', $entry['id'], implode( ',', $att_ids ) ) );
                foreach ( $att_ids as $att_id ) {
                    $file = get_attached_file( $att_id );
                    if ( $file && file_exists( $file ) ) {
                        $attachment_files[] = $file;
                        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Reply attachment file: %s', $file ) );
                    } else {
                        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Reply attachment MISSING: att_id=%d file=%s', $att_id, $file ?: 'null' ) );
                    }
                }
                break;
            }
        }

        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Sending reply: %d attachments', count( $attachment_files ) ) );

        // Build HTML body.
        $body_html = wp_kses_post( $content );

        $body_plain = wp_strip_all_tags( $content );

        // Store data for phpmailer callback.
        $admin = Admin::instance();
        $admin->set_reply_headers( $message_id, $last_client_msg_id ?? '', $references, $attachment_files );
        $admin->set_reply_body( $body_html, $body_plain );

        add_action( 'phpmailer_init', [ $admin, 'set_reply_email_headers' ], 999 );

        // Pass HTML body directly with HTML content type.
        wp_mail( $to, $subject, $body_html, "Content-Type: text/html; charset=UTF-8\nMIME-Version: 1.0" );

        remove_action( 'phpmailer_init', [ $admin, 'set_reply_email_headers' ], 999 );

        // Reset state.
        $admin->set_reply_body( '', '' );

        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Reply email sent to %s for ticket #%d', $to, $ticket_id ) );
    }

    /**
     * Get developer options.
     *
     * @return array<int, string> User ID => Display name.
     */
    private function get_developer_options(): array {
        $users = get_users( [
            'role__in' => [ 'administrator', 'editor', 'author', 'contributor' ],
            'fields'   => [ 'ID', 'display_name' ],
        ] );

        $options = [];

        foreach ( $users as $user ) {
            $options[ $user->ID ] = $user->display_name;
        }

        return $options;
    }
}
