<?php
namespace Fanaloka\Maintenance\Admin;

defined( 'ABSPATH' ) || exit;

use Fanaloka\Maintenance\Email\EmailLog;

class EmailLogPage {

    public function render(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $context = sanitize_text_field( wp_unslash( $_GET['context'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );

        $result = EmailLog::get_logs( [
            'per_page' => 30,
            'page'     => $paged,
            'status'   => $status,
            'context'  => $context,
            'search'   => $search,
        ] );

        $logs  = $result['items'];
        $total = $result['total'];
        $pages = $result['pages'];

        $contexts = [
            'notification' => __( 'Notification', 'fanaloka-maintenance' ),
            'reply'        => __( 'Reply', 'fanaloka-maintenance' ),
            'test'         => __( 'Test', 'fanaloka-maintenance' ),
        ];
        ?>
        <div class="fm-page-wrap">
            <?php if ( isset( $_GET['fm_deleted'] ) || isset( $_GET['fm_cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
                <div class="fm-notice fm-notice-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Email log entries deleted.', 'fanaloka-maintenance' ); ?>
                </div>
            <?php endif; ?>

            <div class="fm-card" id="fm-email-log-card">
                <div class="fm-card-toolbar">
                    <span class="fm-log-count"><?php printf( esc_html__( '%d emails', 'fanaloka-maintenance' ), $total ); ?></span>
                    <?php if ( $total > 0 ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fm_email_log_clear' ), 'fm_clear_email_log' ) ); ?>"
                           class="button button-link-delete fm-email-clear"
                           onclick="return confirm('<?php esc_attr_e( 'Delete all email log entries?', 'fanaloka-maintenance' ); ?>');">
                            <?php esc_html_e( 'Clear Log', 'fanaloka-maintenance' ); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select id="fm-email-filter-status" name="status">
                            <option value=""><?php esc_html_e( 'All statuses', 'fanaloka-maintenance' ); ?></option>
                            <option value="sent" <?php selected( $status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'fanaloka-maintenance' ); ?></option>
                            <option value="failed" <?php selected( $status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'fanaloka-maintenance' ); ?></option>
                        </select>
                        <select id="fm-email-filter-context" name="context">
                            <option value=""><?php esc_html_e( 'All contexts', 'fanaloka-maintenance' ); ?></option>
                            <?php foreach ( $contexts as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $context, $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="search" id="fm-email-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search recipient or subject...', 'fanaloka-maintenance' ); ?>" />
                    </div>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php printf( esc_html__( '%d items', 'fanaloka-maintenance' ), $total ); ?></span>
                        <?php
                        if ( $pages > 1 ) {
                            echo wp_kses(
                                paginate_links( [
                                    'base'      => add_query_arg( 'paged', '%#%' ),
                                    'format'    => '',
                                    'current'   => $paged,
                                    'total'     => $pages,
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                ] ),
                                [ 'a' => [ 'href' => [], 'class' => [] ] ]
                            );
                        }
                        ?>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-time"><?php esc_html_e( 'Time', 'fanaloka-maintenance' ); ?></th>
                            <th class="column-to"><?php esc_html_e( 'To', 'fanaloka-maintenance' ); ?></th>
                            <th class="column-subject"><?php esc_html_e( 'Subject', 'fanaloka-maintenance' ); ?></th>
                            <th class="column-context"><?php esc_html_e( 'Context', 'fanaloka-maintenance' ); ?></th>
                            <th class="column-status"><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></th>
                            <th class="column-action"><?php esc_html_e( 'Action', 'fanaloka-maintenance' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $logs ) ) : ?>
                            <tr><td colspan="6" class="fm-empty"><?php esc_html_e( 'No emails sent yet.', 'fanaloka-maintenance' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $logs as $log ) :
                                $time     = strtotime( $log['created_at'] );
                                $relative = $this->get_relative_time( $log['created_at'] );
                                $full     = wp_date( 'D, d M Y \a\t g:i:s A', $time );
                                $is_sent  = 'sent' === $log['status'];
                            ?>
                                <tr class="fm-email-row" data-id="<?php echo esc_attr( $log['id'] ); ?>">
                                    <td class="column-time"><span title="<?php echo esc_attr( $full ); ?>"><?php echo esc_html( $relative ); ?></span><br><small class="fm-muted"><?php echo esc_html( wp_date( 'd M Y H:i:s', $time ) ); ?></small></td>
                                    <td class="column-to">
                                        <?php echo esc_html( $log['to_email'] ); ?>
                                        <?php if ( ! empty( $log['cc'] ) ) : ?><br><small class="fm-muted">CC: <?php echo esc_html( $log['cc'] ); ?></small><?php endif; ?>
                                        <?php if ( ! empty( $log['bcc'] ) ) : ?><br><small class="fm-muted">BCC: <?php echo esc_html( $log['bcc'] ); ?></small><?php endif; ?>
                                    </td>
                                    <td class="column-subject">
                                        <a href="#" class="fm-email-view" data-id="<?php echo esc_attr( $log['id'] ); ?>"><?php echo esc_html( $log['subject'] ); ?></a>
                                        <?php if ( ! empty( $log['ticket_id'] ) ) : ?>
                                            <br><small class="fm-muted">
                                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-requests&action=view&id=' . absint( $log['ticket_id'] ) ) ); ?>">#<?php echo esc_html( $log['ticket_id'] ); ?></a>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-context"><?php echo esc_html( $contexts[ $log['context'] ] ?? $log['context'] ); ?></td>
                                    <td class="column-status">
                                        <span class="fm-badge <?php echo $is_sent ? 'fm-badge-success' : 'fm-badge-danger'; ?>">
                                            <?php echo esc_html( $is_sent ? __( 'Sent', 'fanaloka-maintenance' ) : __( 'Failed', 'fanaloka-maintenance' ) ); ?>
                                        </span>
                                    </td>
                                    <td class="column-action">
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fm_email_log_delete&id=' . absint( $log['id'] ) ), 'fm_delete_email_log_' . absint( $log['id'] ) ) ); ?>"
                                           class="button button-link-delete"
                                           onclick="return confirm('<?php esc_attr_e( 'Delete this log entry?', 'fanaloka-maintenance' ); ?>');"><?php esc_html_e( 'Delete', 'fanaloka-maintenance' ); ?></a>
                                    </td>
                                </tr>
                                <tr class="fm-email-detail-row" id="fm-email-detail-<?php echo esc_attr( $log['id'] ); ?>" style="display:none;">
                                    <td colspan="6">
                                        <?php if ( 'failed' === $log['status'] && ! empty( $log['error'] ) ) : ?>
                                            <div class="fm-email-error">
                                                <strong><?php esc_html_e( 'Error:', 'fanaloka-maintenance' ); ?></strong>
                                                <code><?php echo esc_html( $log['error'] ); ?></code>
                                            </div>
                                        <?php endif; ?>
                                        <div class="fm-email-body">
                                            <?php echo wp_kses_post( wpautop( $log['body'] ) ); ?>
                                        </div>
                                        <?php if ( ! empty( $log['headers'] ) ) : ?>
                                            <details class="fm-email-headers">
                                                <summary><?php esc_html_e( 'Headers', 'fanaloka-maintenance' ); ?></summary>
                                                <pre><?php echo esc_html( $log['headers'] ); ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        #fm-email-log-card table { table-layout: auto; }
        #fm-email-filter-status, #fm-email-filter-context { margin-right: 8px; }
        #fm-email-search { width: 250px; }
        /* WordPress core hides .tablenav .actions at <=782px (meant for bulk-action
           controls); this page reuses that class for its own filter/search bar, so
           override it back to visible and let it wrap tidily instead of vanishing. */
        @media (max-width: 782px) {
            .tablenav.top .alignleft.actions {
                display: flex !important;
                flex-wrap: wrap;
                gap: 8px;
                float: none;
                width: 100%;
                margin-bottom: 8px;
            }
            .tablenav.top .alignleft.actions select,
            #fm-email-search {
                width: auto;
                flex: 1 1 160px;
                min-width: 0;
                margin-right: 0;
            }
        }
        #fm-email-log-card .column-time,
        #fm-email-log-card .column-context,
        #fm-email-log-card .column-status,
        #fm-email-log-card .column-action { white-space: nowrap; width: auto; }
        #fm-email-log-card .column-to { white-space: nowrap; }
        .fm-log-count { font-size: 13px; color: #646970; font-weight: 500; }
        .fm-muted { color: #8c8f94; }
        .fm-email-view { color: #0a7bff; text-decoration: none; font-weight: 500; }
        .fm-email-view:hover { color: #005cbf; text-decoration: underline; }
        .fm-email-detail-row td { padding: 16px 20px; background: #f6f7f7; border-bottom: 1px solid #e5e7eb; }
        .fm-empty { text-align: center; padding: 30px !important; color: #646970; }
        .fm-email-error { background: #fce8e6; border: 1px solid #f5c6cb; color: #c5221f; padding: 10px 12px; border-radius: 6px; margin-bottom: 12px; }
        .fm-email-body { max-height: 400px; overflow: auto; border: 1px solid #e8eaed; border-radius: 8px; background: #fff; padding: 14px; }
        .fm-email-body img { max-width: 100%; height: auto; }
        .fm-email-headers { margin-top: 10px; }
        .fm-email-headers summary { cursor: pointer; color: #0a7bff; font-size: 13px; }
        .fm-email-headers pre { background: #f6f7f7; padding: 10px; border-radius: 6px; overflow: auto; font-size: 12px; max-height: 200px; }
        .fm-notice { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .fm-notice-success { background: #e6f9e6; color: #00a32a; border: 1px solid #b8e6b8; }
        .fm-notice-success .dashicons { font-size: 18px; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $(document).on('change', '#fm-email-filter-status, #fm-email-filter-context', function() {
                var url = new URL(window.location.href);
                url.searchParams.set('status', $('#fm-email-filter-status').val());
                url.searchParams.set('context', $('#fm-email-filter-context').val());
                url.searchParams.delete('paged');
                window.location.href = url.toString();
            });
            $(document).on('keypress', '#fm-email-search', function(e) {
                if (e.which === 13) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('s', $(this).val());
                    url.searchParams.delete('paged');
                    window.location.href = url.toString();
                }
            });
            $(document).on('click', '.fm-email-view', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                $('#fm-email-detail-' + id).toggle();
            });
        });
        </script>
        <?php
    }

    /**
     * Handle delete / clear actions via admin-post (nonce protected).
     *
     * @return void
     */
    public function handle_actions(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=fm-email-log' ) );
            exit;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

        if ( 'fm_email_log_delete' === $action ) {
            $id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $id > 0 && wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ), 'fm_delete_email_log_' . $id ) ) {
                EmailLog::delete( $id );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=fm-email-log&fm_deleted=1' ) );
            exit;
        }

        if ( 'fm_email_log_clear' === $action ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ), 'fm_clear_email_log' ) ) {
                EmailLog::clear();
                \Fanaloka\Maintenance\Logger\Logger::log( 'Email log cleared' );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=fm-email-log&fm_cleared=1' ) );
            exit;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=fm-email-log' ) );
        exit;
    }

    private function get_relative_time( string $datetime ): string {
        $now  = new \DateTime();
        $ago  = new \DateTime( $datetime );
        $diff = $now->diff( $ago );

        if ( $diff->y > 0 ) {
            return sprintf( _n( '%d year ago', '%d years ago', $diff->y, 'fanaloka-maintenance' ), $diff->y );
        }
        if ( $diff->m > 0 ) {
            return sprintf( _n( '%d month ago', '%d months ago', $diff->m, 'fanaloka-maintenance' ), $diff->m );
        }
        if ( $diff->d > 0 ) {
            if ( $diff->d >= 7 ) {
                $weeks = (int) floor( $diff->d / 7 );
                return sprintf( _n( '%d week ago', '%d weeks ago', $weeks, 'fanaloka-maintenance' ), $weeks );
            }
            return sprintf( _n( '%d day ago', '%d days ago', $diff->d, 'fanaloka-maintenance' ), $diff->d );
        }
        if ( $diff->h > 0 ) {
            return sprintf( _n( '%d hour ago', '%d hours ago', $diff->h, 'fanaloka-maintenance' ), $diff->h );
        }
        if ( $diff->i > 0 ) {
            return sprintf( _n( '%d minute ago', '%d minutes ago', $diff->i, 'fanaloka-maintenance' ), $diff->i );
        }
        return __( 'just now', 'fanaloka-maintenance' );
    }
}
