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
        ?>

        <div class="wrap">
            <h1>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-requests' ) ); ?>">&laquo;</a>
                <?php echo esc_html( $ticket['full_number'] . ' — ' . $ticket['subject'] ); ?>
            </h1>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">

                    <!-- Main Content -->
                    <div id="post-body-content">
                        <!-- Ticket Info Box -->
                        <div class="postbox">
                            <h2 class="hndle"><?php esc_html_e( 'Ticket Information', 'fanaloka-maintenance' ); ?></h2>
                            <div class="inside">
                                <table class="widefat">
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></strong></td>
                                        <td>
                                            <select class="fm-ajax-field"
                                                    data-ticket-id="<?php echo esc_attr( $this->ticket_id ); ?>"
                                                    data-field="status">
                                                <?php foreach ( TicketManager::STATUSES as $key => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $ticket['status'], $key ); ?>>
                                                        <?php echo esc_html( $label ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Priority', 'fanaloka-maintenance' ); ?></strong></td>
                                        <td>
                                            <select class="fm-ajax-field"
                                                    data-ticket-id="<?php echo esc_attr( $this->ticket_id ); ?>"
                                                    data-field="priority">
                                                <?php foreach ( TicketManager::PRIORITIES as $key => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $ticket['priority'], $key ); ?>>
                                                        <?php echo esc_html( $label ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Assigned Developer', 'fanaloka-maintenance' ); ?></strong></td>
                                        <td>
                                            <select class="fm-ajax-field"
                                                    data-ticket-id="<?php echo esc_attr( $this->ticket_id ); ?>"
                                                    data-field="developer_id">
                                                <option value="0"><?php esc_html_e( 'Unassigned', 'fanaloka-maintenance' ); ?></option>
                                                <?php foreach ( $developers as $id => $name ) : ?>
                                                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $ticket['assigned_dev'], $id ); ?>>
                                                        <?php echo esc_html( $name ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Client', 'fanaloka-maintenance' ); ?></strong></td>
                                        <td>
                                            <?php echo esc_html( $ticket['client_name'] ); ?>
                                            &lt;<?php echo esc_html( $ticket['client_email'] ); ?>&gt;
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Date Created', 'fanaloka-maintenance' ); ?></strong></td>
                                        <td><?php echo esc_html( $ticket['date_created'] ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Last Updated', 'fanaloka-maintenance' ); ?></strong></td>
                                        <td><?php echo esc_html( $ticket['last_updated'] ); ?></td>
                                    </tr>
                                    <?php if ( $ticket['completion_date'] ) : ?>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Completed', 'fanaloka-maintenance' ); ?></strong></td>
                                        <td><?php echo esc_html( $ticket['completion_date'] ); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <!-- Conversation Timeline -->
                        <div class="postbox">
                            <h2 class="hndle"><?php esc_html_e( 'Conversation', 'fanaloka-maintenance' ); ?></h2>
                            <div class="inside fm-conversation">
                                <?php if ( empty( $entries ) ) : ?>
                                    <p><?php esc_html_e( 'No conversation entries yet.', 'fanaloka-maintenance' ); ?></p>
                                <?php else : ?>
                                    <?php foreach ( $entries as $entry ) : ?>
                                        <div class="fm-conversation-entry fm-entry-<?php echo esc_attr( $entry['entry_type'] ); ?>">
                                            <div class="fm-entry-header">
                                                <strong><?php echo esc_html( $entry['sender'] ); ?></strong>
                                                <span class="fm-entry-type"><?php echo esc_html( ucfirst( $entry['entry_type'] ) ); ?></span>
                                                <span class="fm-entry-date"><?php echo esc_html( $entry['created_at'] ); ?></span>
                                            </div>
                                            <div class="fm-entry-content">
                                                <?php
                                                if ( 'developer' === $entry['entry_type'] || 'internal' === $entry['entry_type'] ) {
                                                    // HTML content from wp_editor.
                                                    echo wp_kses_post( $entry['body'] );
                                                } else {
                                                    // Plain text from email or system.
                                                    echo wp_kses_post( nl2br( esc_html( $entry['body'] ) ) );
                                                }
                                                ?>
                                            </div>
                                            <?php
                                            // Show attachments for this entry.
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
                                                                        <img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $name ); ?>" style="max-width:300px;max-height:200px;" />
                                                                    </a>
                                                                <?php else : ?>
                                                                    <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="fm-attachment-file">
                                                                        📎 <?php echo esc_html( $name ); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif;
                                                    endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Reply Form -->
                        <div class="postbox">
                            <h2 class="hndle"><?php esc_html_e( 'Reply', 'fanaloka-maintenance' ); ?></h2>
                            <div class="inside">
                                <form method="post" id="fm-reply-form" enctype="multipart/form-data">
                                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $this->ticket_id ); ?>" />
                                    <input type="hidden" name="fm_action" value="reply" />
                                    <?php wp_nonce_field( 'fm_reply_ticket' ); ?>
                                    <div id="reply-editor-wrap">
                                        <?php
                                        wp_editor( '', 'reply_content', [
                                            'textarea_name' => 'reply_content',
                                            'textarea_rows' => 8,
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
                                    <p style="margin-top:10px;">
                                        <label><strong><?php esc_html_e( 'Attachments:', 'fanaloka-maintenance' ); ?></strong></label><br>
                                        <input type="file" name="reply_attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip" />
                                        <span class="description"><?php esc_html_e( 'JPG, PNG, PDF, DOC, DOCX, ZIP (max 5MB each)', 'fanaloka-maintenance' ); ?></span>
                                    </p>
                                    <?php submit_button( __( 'Send Reply', 'fanaloka-maintenance' ), 'primary', 'submit' ); ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div id="postbox-container-1">
                        <!-- Attachments -->
                        <div class="postbox">
                            <h2 class="hndle"><?php esc_html_e( 'Attachments', 'fanaloka-maintenance' ); ?></h2>
                            <div class="inside">
                                <?php if ( empty( $files ) ) : ?>
                                    <p><?php esc_html_e( 'No attachments.', 'fanaloka-maintenance' ); ?></p>
                                <?php else : ?>
                                    <ul class="fm-attachment-list">
                                        <?php foreach ( $files as $file ) : ?>
                                            <li>
                                                <a href="<?php echo esc_url( $file['url'] ); ?>" target="_blank">
                                                    <?php echo esc_html( $file['name'] ); ?>
                                                </a>
                                                <span>(<?php echo esc_html( $file['size'] ); ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
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

        // Get attachment file paths from latest developer entry.
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
                        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Reply attachment file missing: att_id=%d file=%s', $att_id, $file ?: 'null' ) );
                    }
                }
                break;
            }
        }

        \Fanaloka\Maintenance\Logger\Logger::log( sprintf( 'Sending reply email to %s for ticket #%d with %d attachments', $to, $ticket_id, count( $attachment_files ) ) );

        $admin = Admin::instance();
        $admin->set_reply_headers( $message_id, $last_client_msg_id ?? '', $references, $attachment_files );

        $body = sprintf(
            '<p>Halo %s,</p>%s<p>Salam,<br>%s</p>',
            esc_html( $ticket['client_name'] ?? '' ),
            wp_kses_post( $content ),
            esc_html( get_bloginfo( 'name' ) )
        );

        add_action( 'phpmailer_init', [ $admin, 'set_reply_email_headers' ] );

        wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );

        remove_action( 'phpmailer_init', [ $admin, 'set_reply_email_headers' ] );

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
