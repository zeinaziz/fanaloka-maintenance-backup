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

        // Ensure fmAdmin is available for inline scripts.
        ?>
        <script>
        if ( typeof fmAdmin === 'undefined' ) {
            var fmAdmin = {
                ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
                nonce: '<?php echo esc_js( wp_create_nonce( 'fm_admin_nonce' ) ); ?>'
            };
        }
        </script>
        <?php

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

        // Auto-fill reply CC from the latest client entry's CC recipients.
        $default_reply_cc = '';
        $own_email        = strtolower( (string) get_option( 'fm_imap_username', '' ) );
        foreach ( array_reverse( $entries ) as $entry ) {
            if ( 'client' !== ( $entry['entry_type'] ?? '' ) ) {
                continue;
            }
            $entry_meta_data = ! empty( $entry['meta'] ) ? json_decode( $entry['meta'], true ) : [];
            $cc_raw          = $entry_meta_data['cc'] ?? '';
            if ( ! empty( $cc_raw ) ) {
                $filtered = [];
                foreach ( array_map( 'trim', explode( ',', $cc_raw ) ) as $cc_addr ) {
                    if ( empty( $own_email ) || strtolower( $cc_addr ) !== $own_email ) {
                        $filtered[] = $cc_addr;
                    }
                }
                $default_reply_cc = implode( ', ', $filtered );
                break;
            }
        }

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
                    <?php else :
                        $entry_count = count( $entries );
                        $has_collapse = $entry_count > 2;
                        $entry_index = 0;
                        foreach ( $entries as $entry ) :
                            $initials = strtoupper( substr( $entry['sender'], 0, 1 ) );
                            $entry_color = 'developer' === $entry['entry_type'] ? '#2271b1' : ( 'client' === $entry['entry_type' ] ? '#00a32a' : ( 'internal' === $entry['entry_type'] ? '#856404' : '#646970' ) );
                            $is_first = ( 0 === $entry_index );
                            $is_last = ( $entry_index === $entry_count - 1 );
                            $is_middle = ! $is_first && ! $is_last;
                            $type = $entry['entry_type'] ?? 'client';
                            if ( 'internal' === $type ) {
                                $action_text = 'added an internal note';
                            } elseif ( 'client' === $type ) {
                                $action_text = 'sent a message';
                            } else {
                                $action_text = 'replied';
                            }
                        ?>
                            <div class="fm-entry fm-entry-<?php echo esc_attr( $entry['entry_type'] ); ?><?php echo ( $has_collapse && $is_middle ) ? ' fm-entry-collapsed' : ''; ?>" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>"<?php echo ( $has_collapse && $is_middle ) ? ' data-collapse-target="1"' : ''; ?>>
                                <div class="fm-entry-avatar" style="background:<?php echo esc_attr( $entry_color ); ?>;">
                                    <?php echo esc_html( $initials ); ?>
                                </div>
                                <div class="fm-entry-body">
                                    <div class="fm-entry-meta">
                                        <strong class="fm-entry-sender" style="color:<?php echo esc_attr( $entry_color ); ?>;"><?php echo esc_html( $entry['sender'] ); ?></strong>
                                        <span class="fm-entry-action"><?php echo esc_html( $action_text ); ?></span>
                                        <span class="fm-entry-date">- <?php echo esc_html( $this->get_relative_time( $entry['created_at'] ) ); ?> (<?php echo esc_html( wp_date( 'D, d M Y \a\t g:i A', strtotime( $entry['created_at'] ) ) ); ?>)</span>
                                        <?php if ( 'internal' === $type ) : ?>
                                            <span class="fm-entry-badge-internal">Internal</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ( 'internal' !== $type ) :
                                    $entry_meta_data = ! empty( $entry['meta'] ) ? json_decode( $entry['meta'], true ) : [];
                                    $ticket_client_email = $ticket['client_email'] ?? '';
                                    $has_to  = ! empty( $ticket_client_email );
                                    $has_cc  = ! empty( $entry_meta_data['cc'] );
                                    $has_bcc = ! empty( $entry_meta_data['bcc'] );
                                    if ( $has_to || $has_cc || $has_bcc ) :
                                    ?>
                                    <div class="fm-entry-recipients">
                                        <?php if ( $has_to ) : ?>
                                            <span class="fm-entry-to"><strong>To:</strong> <?php echo esc_html( $ticket_client_email ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( $has_cc ) : ?>
                                            <span class="fm-entry-cc"><strong>Cc:</strong> <?php echo esc_html( $entry_meta_data['cc'] ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( $has_bcc ) : ?>
                                            <span class="fm-entry-bcc"><strong>Bcc:</strong> <?php echo esc_html( $entry_meta_data['bcc'] ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; endif; ?>
                                    <div class="fm-entry-content">
                                        <?php
                                        if ( 'developer' === $entry['entry_type'] || 'internal' === $entry['entry_type'] ) {
                                            echo wp_kses_post( $entry['body'] );
                                        } elseif ( 'client' === $entry['entry_type'] && ! empty( $entry['body_html'] ) ) {
                                            echo $this->kses_html_body( $entry['body_html'], (int) $entry['id'] );
                                        } else {
                                            echo wp_kses_post( nl2br( make_clickable( esc_html( $entry['body'] ) ) ) );
                                        }
                                        ?>
                                    </div>
                                    <?php
                                    $entry_attachments = ! empty( $entry['attachments'] ) ? explode( ',', $entry['attachments'] ) : [];
                                    if ( ! empty( $entry_attachments ) ) :
                                        $att_count = count( $entry_attachments );
                                        $label = 1 === $att_count ? 'Attachment' : 'Attachments';
                                    ?>
                                        <div class="fm-entry-attachments">
                                            <div class="fm-attachments-label"><?php echo esc_html( $label ); ?></div>
                                            <div class="fm-attachments-grid">
                                            <?php foreach ( $entry_attachments as $att_id ) :
                                                $att_id = absint( $att_id );
                                                $url    = wp_get_attachment_url( $att_id );
                                                $name   = get_the_title( $att_id );
                                                $mime   = get_post_mime_type( $att_id );
                                                $is_image = str_starts_with( $mime, 'image/' );
                                                if ( $url ) : ?>
                                                    <?php if ( $is_image ) : ?>
                                                        <div class="fm-attachment-item fm-attachment-thumb">
                                                            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
                                                                <img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $name ); ?>" />
                                                            </a>
                                                        </div>
                                                    <?php else :
                                                        $ext = strtoupper( pathinfo( $name, PATHINFO_EXTENSION ) ); ?>
                                                        <a class="fm-attachment-file" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
                                                            <span class="fm-attachment-icon"><?php echo esc_html( $ext ); ?></span>
                                                            <span class="fm-attachment-name"><?php echo esc_html( $name ); ?></span>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif;
                                            endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ( $has_collapse && $is_first ) : ?>
                                <div class="fm-entry-collapse-toggle" id="fm-collapse-toggle" data-collapse-label="<?php echo esc_attr( sprintf( __( 'Show %d earlier messages', 'fanaloka-maintenance' ), $entry_count - 2 ) ); ?>" data-expand-label="<?php echo esc_attr( sprintf( __( 'Hide %d earlier messages', 'fanaloka-maintenance' ), $entry_count - 2 ) ); ?>">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    <span class="fm-collapse-text"><?php echo esc_html( sprintf( __( 'Show %d earlier messages', 'fanaloka-maintenance' ), $entry_count - 2 ) ); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php
                            $entry_index++;
                        endforeach;
                    endif; ?>

                    <!-- Reply Form -->
                    <div class="fm-reply-box">
                        <div class="fm-reply-header">
                            <span class="dashicons dashicons-edit"></span>
                            <strong><?php esc_html_e( 'Reply', 'fanaloka-maintenance' ); ?></strong>
                            <span class="fm-reply-to"><?php esc_html_e( 'to', 'fanaloka-maintenance' ); ?> <?php echo esc_html( $ticket['client_email'] ?? '' ); ?></span>
                        </div>
                        <form method="post" id="fm-reply-form" enctype="multipart/form-data">
                            <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $this->ticket_id ); ?>" />
                            <input type="hidden" name="fm_action" value="reply" />
                            <?php wp_nonce_field( 'fm_reply_ticket' ); ?>
                            <div class="fm-recipients">
                                <div class="fm-recipient-row">
                                    <label for="fm-reply-cc">CC</label>
                                    <input type="text" id="fm-reply-cc" name="reply_cc" value="<?php echo esc_attr( $default_reply_cc ); ?>" placeholder="<?php esc_attr_e( 'email@example.com, email2@example.com', 'fanaloka-maintenance' ); ?>" />
                                    <a href="#" id="fm-toggle-bcc" class="fm-bcc-toggle<?php echo empty( $default_reply_cc ) ? '' : ' fm-active'; ?>">BCC</a>
                                </div>
                                <div class="fm-recipient-row fm-bcc-row" id="fm-bcc-row"<?php echo empty( $default_reply_cc ) ? ' style="display:none;"' : ''; ?>>
                                    <label for="fm-reply-bcc">BCC</label>
                                    <input type="text" id="fm-reply-bcc" name="reply_bcc" placeholder="<?php esc_attr_e( 'email@example.com, email2@example.com', 'fanaloka-maintenance' ); ?>" />
                                </div>
                            </div>
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
                                        <input type="file" name="reply_attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z" style="display:none;" />
                                    </label>
                                    <span class="description"><?php esc_html_e( 'Max 10MB per file', 'fanaloka-maintenance' ); ?></span>
                                </div>
                                <div class="fm-file-list" id="fm-file-list"></div>
                                <div class="fm-upload-status" id="fm-upload-status" style="display:none;">
                                    <span class="spinner is-active"></span>
                                    <span class="fm-upload-status-text"></span>
                                </div>
                                <?php submit_button( __( 'Send Reply', 'fanaloka-maintenance' ), 'primary', 'submit', false, [ 'class' => 'fm-reply-submit' ] ); ?>
                            </div>
                        </form>
                    </div>

                    <!-- Internal Note Form -->
                    <div class="fm-internal-note-box" id="fm-internal-note-box">
                        <div class="fm-note-header">
                            <span class="dashicons dashicons-lock"></span>
                            <strong><?php esc_html_e( 'Internal Note', 'fanaloka-maintenance' ); ?></strong>
                            <a href="#" id="fm-toggle-note" class="fm-note-toggle-link"><?php esc_html_e( 'Add note', 'fanaloka-maintenance' ); ?></a>
                        </div>
                        <form method="post" id="fm-note-form" style="display:none;">
                            <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $this->ticket_id ); ?>" />
                            <?php wp_nonce_field( 'fm_add_internal_note' ); ?>
                            <div id="note-editor-wrap">
                                <?php
                                wp_editor( '', 'note_content', [
                                    'textarea_name' => 'note_content',
                                    'textarea_rows' => 4,
                                    'media_buttons' => false,
                                    'teeny'         => true,
                                    'quicktags'     => false,
                                    'tinymce'       => [
                                        'toolbar1' => 'bold,italic,underline',
                                        'toolbar2' => '',
                                    ],
                                ] );
                                ?>
                            </div>
                            <div class="fm-reply-footer">
                                <div></div>
                                <button type="submit" class="button button-primary fm-note-submit"><?php esc_html_e( 'Add Internal Note', 'fanaloka-maintenance' ); ?></button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="fm-ticket-sidebar">
                    <div class="fm-sidebar-badges">
                        <span class="fm-badge" style="background:<?php echo esc_attr( $status_color ); ?>;"><?php echo esc_html( ucfirst( str_replace( '-', ' ', $ticket['status'] ?? '' ) ) ); ?></span>
                        <span class="fm-badge fm-badge-outline" style="color:<?php echo esc_attr( $priority_color ); ?>; border-color:<?php echo esc_attr( $priority_color ); ?>;"><?php echo esc_html( ucfirst( $ticket['priority'] ?? '' ) ); ?></span>
                    </div>
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

                    <div class="fm-sidebar-section fm-sidebar-dates">
                        <h3 class="fm-sidebar-title"><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Dates', 'fanaloka-maintenance' ); ?></h3>
                        <div class="fm-sidebar-content">
                            <?php
                            $created_date = $ticket['date_created'] ?? '';
                            if ( $created_date ) :
                                $created_rel = $this->get_relative_time( $created_date );
                                $created_full = wp_date( 'D, d M Y \a\t g:i A', strtotime( $created_date ) );
                            ?>
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Created', 'fanaloka-maintenance' ); ?></label>
                                <span title="<?php echo esc_attr( $created_full ); ?>"><?php echo esc_html( $created_rel ); ?> (<?php echo esc_html( $created_full ); ?>)</span>
                            </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $ticket['last_updated'] ) ) :
                                $updated_dt = wp_date( 'Y-m-d H:i:s', (int) $ticket['last_updated'] );
                                $updated_rel = $this->get_relative_time( $updated_dt );
                                $updated_full = wp_date( 'D, d M Y \a\t g:i A', (int) $ticket['last_updated'] );
                            ?>
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Updated', 'fanaloka-maintenance' ); ?></label>
                                <span title="<?php echo esc_attr( $updated_full ); ?>"><?php echo esc_html( $updated_rel ); ?> (<?php echo esc_html( $updated_full ); ?>)</span>
                            </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $ticket['completion_date'] ) ) :
                                $completed_rel = $this->get_relative_time( $ticket['completion_date'] );
                                $completed_full = wp_date( 'D, d M Y \a\t g:i A', strtotime( $ticket['completion_date'] ) );
                            ?>
                            <div class="fm-field-row">
                                <label><?php esc_html_e( 'Completed', 'fanaloka-maintenance' ); ?></label>
                                <span title="<?php echo esc_attr( $completed_full ); ?>"><?php echo esc_html( $completed_rel ); ?> (<?php echo esc_html( $completed_full ); ?>)</span>
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

        <style>
        /* Shared Design System */
        .fm-page-wrap { max-width: 1400px; margin: 0 auto; padding: 0 0 40px; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-page-columns { display: flex; gap: 20px; align-items: flex-start; }
        .fm-page-main { flex: 1; min-width: 0; }
        .fm-page-sidebar { width: 300px; flex-shrink: 0; }

        /* Ticket Header */
        .fm-ticket-header { display: flex; align-items: center; justify-content: space-between; padding: 6px 14px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); position: fixed; top: 32px; left: 180px; right: 20px; z-index: 100; }
        .fm-ticket-header-left { display: flex; align-items: center; gap: 15px; }
        .fm-ticket-back { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #f0f0f1; color: #1d2327; text-decoration: none; transition: background 0.15s; }
        .fm-ticket-back:hover { background: #ddd; color: #1d2327; }
        .fm-ticket-title { margin: 0; font-size: 14px; line-height: 1.3; }
        .fm-ticket-number { font-size: 11px; color: #646970; }
        .fm-ticket-header-right { display: flex; gap: 8px; align-items: center; }
        @media (max-width: 782px) {
            .fm-ticket-title { display: none; }
        }

        /* Badges */
        .fm-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #fff; line-height: 1.4; }
        .fm-badge-outline { background: transparent; border: 1.5px solid; }
        .fm-badge-success { background: #00a32a; }
        .fm-badge-danger { background: #d63638; }

        /* Conversation & Sidebar */
        .fm-ticket-body { display: flex; gap: 20px; align-items: flex-start; margin-top: 100px; }
        .fm-ticket-conversation { flex: 1; min-width: 0; }
        .fm-ticket-sidebar { width: 300px; flex-shrink: 0; position: sticky; top: 120px; align-self: flex-start; max-height: calc(100vh - 130px); overflow-y: auto; }
        .fm-sidebar-badges { display: flex; gap: 8px; padding: 12px 16px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-sidebar-dates .fm-field-row { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; }
        .fm-sidebar-dates .fm-field-row span { text-align: left; }

        /* Entry */
        .fm-entry { display: flex; gap: 12px; padding: 16px 0; border-bottom: 1px dashed #3858e9; }
        .fm-entry:last-child { border-bottom: none; }
        .fm-entry-collapsed { display: none !important; }
        .fm-entry-collapse-toggle { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 0; margin: 8px 0; width: 100%; cursor: pointer; color: #2271b1; font-size: 13px; font-weight: 600; background: #f0f6fc; border: 1px solid #c3e4ff; border-radius: 8px; transition: all 0.15s; user-select: none; text-align: center; position: relative; z-index: 1; }
        .fm-entry-collapse-toggle:hover { background: #dbebf9; border-color: #2271b1; }
        .fm-entry-collapse-toggle .dashicons { font-size: 18px; width: 18px; height: 18px; transition: transform 0.2s; }
        .fm-entry-collapse-toggle.fm-expanded .dashicons { transform: rotate(180deg); }
        .fm-entry-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; font-weight: 700; flex-shrink: 0; }
        .fm-entry-body { flex: 1; min-width: 0; }
        .fm-entry-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
        .fm-entry-sender { font-size: 15px; font-weight: 500; color: #1d2327; }
        .fm-entry-action { font-size: 14px; color: #1d2327; font-weight: 400; }
        .fm-entry-type-badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .fm-entry-date { font-size: 12px; color: #8c8f94; }
        .fm-entry-recipients { margin-bottom: 6px; font-size: 12px; color: #646970; display: flex; flex-wrap: wrap; gap: 12px; }
        .fm-entry-recipients strong { color: #646970; }
        .fm-entry-content { font-size: 14px; line-height: 1.6; color: #1d2327; }
        .fm-entry-cc-bcc { margin-bottom: 6px; font-size: 12px; color: #646970; display: flex; flex-wrap: wrap; gap: 12px; }
        .fm-entry-cc-bcc strong { color: #1d2327; }
        .fm-entry-content p { margin: 0 0 8px; }
        .fm-entry-content p:last-child { margin-bottom: 0; }
        .fm-entry-attachments { margin-top: 10px; padding-top: 8px; border-top: 1px solid #eee; }
        .fm-attachments-label { font-size: 11px; font-weight: 600; color: #646970; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .fm-attachments-grid { display: flex; flex-direction: row; flex-wrap: wrap; gap: 8px; align-items: flex-start; }
        .fm-attachment-thumb { margin: 0; }
        .fm-attachment-thumb a { display: block; width: 10rem; height: 10rem; padding: 0; overflow: hidden; border-radius: 6px; border: 1px solid #ddd; transition: transform .2s ease; }
        .fm-attachment-thumb img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
        .fm-attachment-thumb a:hover { transform: scale(1.05); }
        .fm-attachment-file { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 6px; text-decoration: none; color: #1d2327; font-size: 13px; transition: all .2s ease; max-width: 280px; }
        .fm-attachment-file:hover { background: #2271b1; border-color: #2271b1; color: #fff; }
        .fm-attachment-icon { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 2px 6px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; font-size: 11px; font-weight: 700; color: #2271b1; text-transform: uppercase; flex-shrink: 0; }
        .fm-attachment-file:hover .fm-attachment-icon { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3); color: #fff; }
        .fm-attachment-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: inherit; }

        /* Empty State */
        .fm-empty-state { text-align: center; padding: 60px 20px; color: #8c8f94; }
        .fm-empty-state .dashicons { font-size: 48px; width: 48px; height: 48px; margin-bottom: 15px; color: #c3c4c7; }

        /* Reply Box */
        .fm-reply-box { background: #fff; border: 1px solid #e2e4e7; border-radius: 12px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
        .fm-reply-header { display: flex; align-items: center; gap: 8px; padding: 14px 18px; background: linear-gradient(180deg, #f9fafb, #f4f6f8); border-bottom: 1px solid #e2e4e7; font-size: 14px; color: #1d2327; }
        .fm-reply-header .dashicons { color: #2271b1; }
        .fm-reply-to { margin-left: auto; font-size: 12px; color: #646970; background: #fff; border: 1px solid #dcdcde; border-radius: 20px; padding: 3px 12px; max-width: 55%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fm-reply-box .inside { padding: 0; }
        .fm-reply-box #reply-editor-wrap { padding: 0; }
        .fm-reply-footer { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-top: 1px solid #e2e4e7; background: #f9f9f9; }
        .fm-reply-attach { display: flex; align-items: center; gap: 10px; }
        .fm-attach-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #fff; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-size: 13px; color: #1d2327; transition: background 0.15s; }
        .fm-attach-btn:hover { background: #f0f0f1; }
        .fm-reply-footer { flex-wrap: wrap; gap: 8px 0; }
        .fm-file-list { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; order: 3; }
        .fm-file-item { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #f0f6fc; border: 1px solid #c3e4ff; border-radius: 4px; font-size: 12px; color: #2271b1; }
        .fm-file-item.fm-file-error { background: #fcf0f1; border-color: #d63638; color: #d63638; }
        .fm-file-item.fm-file-ok { background: #edfaef; border-color: #00a32a; color: #00a32a; }
        .fm-file-name { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fm-file-size { color: #646970; font-size: 11px; }
        .fm-file-remove { cursor: pointer; color: #d63638; font-size: 16px; line-height: 1; margin-left: 2px; }
        .fm-file-remove:hover { color: #a00; }
        .fm-upload-status { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #646970; width: 100%; order: 4; }
        .fm-upload-status .spinner { margin: 0; }
        .fm-reply-submit { margin: 0 !important; }
        .fm-recipients { background: #fff; border-bottom: 1px solid #f0f0f1; }
        .fm-recipient-row { display: flex; align-items: center; gap: 10px; padding: 6px 16px; }
        .fm-recipient-row label { font-size: 12px; font-weight: 600; color: #8c8f94; min-width: 30px; text-transform: uppercase; letter-spacing: 0.5px; }
        .fm-recipient-row input { flex: 1; border: none; border-bottom: 1px solid #e0e1e3; padding: 5px 2px; font-size: 13px; color: #1d2327; background: transparent; border-radius: 0; box-shadow: none !important; transition: border-color 0.15s; }
        .fm-recipient-row input:hover { border-bottom-color: #c3c4c7; }
        .fm-recipient-row input:focus { border-bottom-color: #2271b1; outline: none; }
        .fm-bcc-toggle { font-size: 12px; font-weight: 600; color: #2271b1; text-decoration: none; padding: 3px 8px; border: 1px solid #c3c4c7; border-radius: 4px; line-height: 1.4; transition: all 0.15s; }
        .fm-bcc-toggle:hover { border-color: #2271b1; background: #f0f6fc; }
        .fm-bcc-toggle.fm-active { background: #2271b1; border-color: #2271b1; color: #fff; }
        .fm-bcc-row { display: flex; align-items: center; gap: 10px; padding: 6px 16px; background: #fafbfc; }

        .fm-internal-note-box { background: #fff; border: 1px solid #ffc107; border-radius: 8px; margin-top: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden; }
        .fm-note-header { display: flex; align-items: center; gap: 8px; padding: 10px 16px; background: #fef3cd; border-bottom: 1px solid #ffc107; font-size: 13px; }
        .fm-note-header .dashicons { color: #856404; }
        .fm-note-header strong { color: #856404; }
        .fm-note-toggle-link { margin-left: auto; font-size: 12px; color: #856404; text-decoration: underline; cursor: pointer; }
        .fm-entry-badge-internal { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #fef3cd; color: #856404; }

        /* Sidebar Sections */
        .fm-sidebar-section { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-sidebar-title { display: flex; align-items: center; gap: 6px; margin: 0; padding: 10px 14px; font-size: 13px; font-weight: 600; color: #1d2327; background: #f9f9f9; border-bottom: 1px solid #e2e4e7; }
        .fm-sidebar-title .dashicons { color: #2271b1; font-size: 16px; width: 16px; height: 16px; }
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
        <script>
        (function($) {
            $(document).on('click', '#fm-toggle-bcc', function(e) {
                e.preventDefault();
                var $row = $('#fm-bcc-row');
                $row.toggle();
                $(this).toggleClass('fm-active');
                if ($row.is(':visible')) {
                    $('#fm-reply-bcc').trigger('focus');
                }
            });

            $(document).on('click', '#fm-collapse-toggle', function(e) {
                e.preventDefault();
                var $toggle = $(this);
                var $targets = $('.fm-entry[data-collapse-target="1"]');
                if ($toggle.hasClass('fm-expanded')) {
                    $targets.addClass('fm-entry-collapsed');
                    $toggle.removeClass('fm-expanded');
                    $toggle.find('.dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                    $toggle.find('.fm-collapse-text').text($toggle.data('collapse-label'));
                } else {
                    $targets.removeClass('fm-entry-collapsed');
                    $toggle.addClass('fm-expanded');
                    $toggle.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                    $toggle.find('.fm-collapse-text').text($toggle.data('expand-label'));
                }
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Render a client email body inside a Gmail-style sandboxed iframe.
     *
     * @param string $html     Stored email HTML body.
     * @param int    $entry_id Conversation entry ID (unique frame id).
     * @return string
     */
    private function kses_html_body( string $html, int $entry_id ): string {
        return \Fanaloka\Maintenance\Email\EmailRenderer::render( $html, 'fm-email-' . $entry_id );
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
                \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Attachment skip: name=%s error=%d', $files['name'][ $i ] ?? '', $files['error'][ $i ] ?? -1 ) );
                continue;
            }

            // File size check.
            if ( $files['size'][ $i ] > $max_size ) {
                \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Attachment skip (too large): %s (%d bytes)', $files['name'][ $i ], $files['size'][ $i ] ) );
                continue;
            }

            // MIME type check — verify extension + real file type.
            $ext = strtolower( pathinfo( $files['name'][ $i ], PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $allowed_mimes, true ) ) {
                \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Attachment skip (disallowed type): %s', $files['name'][ $i ] ) );
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
                \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Attachment skip (mime mismatch): %s real=%s expected=%s', $files['name'][ $i ], $real_mime, $expected ) );
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

        // Append email signature.
        $signature = get_option( 'fm_email_signature', '' );
        if ( ! empty( $signature ) ) {
            $body_html  .= '<br><br>' . $signature;
            $body_plain .= "\n\n" . wp_strip_all_tags( $signature );
        }

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
