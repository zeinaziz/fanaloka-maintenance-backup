<?php
namespace Fanaloka\Maintenance\Admin;

defined( 'ABSPATH' ) || exit;

use Fanaloka\Maintenance\Log\ActivityLog;

class ActivityLogPage {

    public function render(): void {
        $actions = ActivityLog::get_actions();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $current_action = sanitize_text_field( wp_unslash( $_GET['filter_action'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );

        $args = [
            'per_page' => 30,
            'page'     => $paged,
            'action'   => $current_action,
            'search'   => $search,
        ];

        $result  = ActivityLog::get_logs( $args );
        $logs    = $result['items'];
        $total   = $result['total'];
        $pages   = $result['pages'];
        ?>
        <div class="fm-page-wrap">
            <div class="fm-page-header">
                <h1 class="fm-page-title">
                    <span class="fm-icon"><?php echo \Fanaloka\Maintenance\Icons::get( 'requests' ); ?></span>
                    <?php esc_html_e( 'Activity Log', 'fanaloka-maintenance' ); ?>
                </h1>
                <div class="fm-page-header-right">
                    <span class="fm-log-count"><?php printf( esc_html__( '%d activities', 'fanaloka-maintenance' ), $total ); ?></span>
                </div>
            </div>

            <div class="fm-card" id="fm-log-card">
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select id="fm-log-filter-action" name="filter_action">
                            <option value=""><?php esc_html_e( 'All actions', 'fanaloka-maintenance' ); ?></option>
                            <?php foreach ( $actions as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_action, $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="search" id="fm-log-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search activities...', 'fanaloka-maintenance' ); ?>" />
                    </div>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php printf( esc_html__( '%d items', 'fanaloka-maintenance' ), $total ); ?></span>
                        <?php
                        if ( $pages > 1 ) {
                            echo wp_kses(
                                paginate_links( [
                                    'base'    => add_query_arg( 'paged', '%#%' ),
                                    'format'  => '',
                                    'current' => $paged,
                                    'total'   => $pages,
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
                            <th style="width:160px;"><?php esc_html_e( 'Time', 'fanaloka-maintenance' ); ?></th>
                            <th style="width:140px;"><?php esc_html_e( 'User', 'fanaloka-maintenance' ); ?></th>
                            <th style="width:160px;"><?php esc_html_e( 'Action', 'fanaloka-maintenance' ); ?></th>
                            <th><?php esc_html_e( 'Details', 'fanaloka-maintenance' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'IP', 'fanaloka-maintenance' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $logs ) ) : ?>
                            <tr><td colspan="5" style="text-align:center;padding:30px;color:#646970;"><?php esc_html_e( 'No activity found.', 'fanaloka-maintenance' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $logs as $log ) :
                                $action_class = 'fm-log-action-' . esc_attr( str_replace( '_', '-', $log['action'] ) );
                                $time = strtotime( $log['created_at'] );
                                $relative = $this->get_relative_time( $log['created_at'] );
                                $full = wp_date( 'D, d M Y \a\t g:i:s A', $time );
                                $details = $this->format_details( $log );
                            ?>
                                <tr class="<?php echo esc_attr( $action_class ); ?>">
                                    <td><span title="<?php echo esc_attr( $full ); ?>"><?php echo esc_html( $relative ); ?></span><br><small style="color:#8c8f94;"><?php echo esc_html( wp_date( 'd M Y H:i:s', $time ) ); ?></small></td>
                                    <td><?php echo esc_html( $log['user_name'] ?: 'System' ); ?></td>
                                    <td><span class="fm-log-badge fm-log-badge-<?php echo esc_attr( str_replace( '_', '-', $log['action'] ) ); ?>"><?php echo esc_html( $actions[ $log['action'] ] ?? $log['action'] ); ?></span></td>
                                    <td><?php echo wp_kses_post( $details ); ?></td>
                                    <td><small style="color:#8c8f94;"><?php echo esc_html( $log['ip_address'] ); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        .fm-log-count { font-size: 13px; color: #646970; }
        #fm-log-filter-action { margin-right: 8px; }
        #fm-log-search { width: 250px; }
        .fm-log-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: #f0f0f1; color: #1d2327; }
        .fm-log-badge-ticket-created { background: #d4edda; color: #155724; }
        .fm-log-badge-ticket-status-changed { background: #cce5ff; color: #004085; }
        .fm-log-badge-ticket-priority-changed { background: #fff3cd; color: #856404; }
        .fm-log-badge-ticket-assigned { background: #d1ecf1; color: #0c5460; }
        .fm-log-badge-reply-sent { background: #d4edda; color: #155724; }
        .fm-log-badge-internal-note-added { background: #fef3cd; color: #856404; }
        .fm-log-badge-ticket-deleted, .fm-log-badge-bulk-deleted { background: #f8d7da; color: #721c24; }
        .fm-log-badge-bulk-updated { background: #cce5ff; color: #004085; }
        .fm-log-badge-email-synced { background: #e2e3e5; color: #383d41; }
        .fm-log-badge-settings-changed { background: #e2d5f1; color: #4a148c; }
        .fm-log-badge-attachment-uploaded { background: #d4edda; color: #155724; }
        .fm-log-details-link { color: #2271b1; text-decoration: none; }
        .fm-log-details-link:hover { text-decoration: underline; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $(document).on('change', '#fm-log-filter-action', function() {
                var action = $(this).val();
                var url = new URL(window.location.href);
                url.searchParams.set('filter_action', action);
                url.searchParams.delete('paged');
                window.location.href = url.toString();
            });
            $(document).on('keypress', '#fm-log-search', function(e) {
                if (e.which === 13) {
                    var q = $(this).val();
                    var url = new URL(window.location.href);
                    url.searchParams.set('s', q);
                    url.searchParams.delete('paged');
                    window.location.href = url.toString();
                }
            });
        });
        </script>
        <?php
    }

    private function format_details( array $log ): string {
        $details = esc_html( $log['details'] );
        $object_id = absint( $log['object_id'] );
        $object_type = $log['object_type'] ?? '';

        // Link to ticket if object is ticket.
        if ( 'ticket' === $object_type && $object_id > 0 ) {
            $url = admin_url( 'admin.php?page=fm-requests&action=view&id=' . $object_id );
            $details = '<a href="' . esc_url( $url ) . '" class="fm-log-details-link">' . $details . '</a>';
        }

        return $details;
    }

    private function get_relative_time( string $datetime ): string {
        $now = new \DateTime();
        $ago = new \DateTime( $datetime );
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
