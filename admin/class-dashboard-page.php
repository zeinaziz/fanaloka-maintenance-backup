<?php
/**
 * Dashboard Page - Stats, Charts, Recent Activities.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

use Fanaloka\Maintenance\Ticket\TicketManager;
use Fanaloka\Maintenance\Cron\CronManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DashboardPage Class.
 */
class DashboardPage {

    /**
     * Render the dashboard page.
     *
     * @return void
     */
    public function render(): void {
        $stats    = $this->get_stats();
        $chart    = $this->get_monthly_chart_data();
        $recent   = $this->get_recent_activities();
        $last_sync = CronManager::instance()->get_last_sync();
        ?>

        <div class="wrap fm-page-wrap">
            <!-- Page Header -->
            <div class="fm-page-header">
                <h1 class="fm-page-title"><span class="fm-icon"><?php echo \Fanaloka\Maintenance\Icons::get( 'dashboard' ); ?></span> <?php esc_html_e( 'Dashboard', 'fanaloka-maintenance' ); ?></h1>
            </div>

            <!-- Stats Row -->
            <div class="fm-stats-row">
                <div class="fm-stat-card">
                    <div class="fm-stat-card-icon" style="background:#2271b115;color:#2271b1;"><span class="dashicons dashicons-feedback"></span></div>
                    <div class="fm-stat-card-info">
                        <span class="fm-stat-card-count"><?php echo esc_html( $stats['total'] ); ?></span>
                        <span class="fm-stat-card-label"><?php esc_html_e( 'Total Requests', 'fanaloka-maintenance' ); ?></span>
                    </div>
                </div>
                <div class="fm-stat-card">
                    <div class="fm-stat-card-icon" style="background:#dba61715;color:#dba617;"><span class="dashicons dashicons-admin-comments"></span></div>
                    <div class="fm-stat-card-info">
                        <span class="fm-stat-card-count"><?php echo esc_html( $stats['open'] ); ?></span>
                        <span class="fm-stat-card-label"><?php esc_html_e( 'Open', 'fanaloka-maintenance' ); ?></span>
                    </div>
                </div>
                <div class="fm-stat-card">
                    <div class="fm-stat-card-icon" style="background:#00a32a15;color:#00a32a;"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="fm-stat-card-info">
                        <span class="fm-stat-card-count"><?php echo esc_html( $stats['completed_today'] ); ?></span>
                        <span class="fm-stat-card-label"><?php esc_html_e( 'Completed Today', 'fanaloka-maintenance' ); ?></span>
                    </div>
                </div>
                <div class="fm-stat-card">
                    <div class="fm-stat-card-icon" style="background:#99680015;color:#996800;"><span class="dashicons dashicons-clock"></span></div>
                    <div class="fm-stat-card-info">
                        <span class="fm-stat-card-count"><?php echo esc_html( $stats['waiting_client'] ); ?></span>
                        <span class="fm-stat-card-label"><?php esc_html_e( 'Waiting', 'fanaloka-maintenance' ); ?></span>
                    </div>
                </div>
                <div class="fm-stat-card">
                    <div class="fm-stat-card-icon" style="background:#d6363815;color:#d63638;"><span class="dashicons dashicons-warning"></span></div>
                    <div class="fm-stat-card-info">
                        <span class="fm-stat-card-count"><?php echo esc_html( $stats['critical'] ); ?></span>
                        <span class="fm-stat-card-label"><?php esc_html_e( 'Critical', 'fanaloka-maintenance' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="fm-page-columns">
                <!-- Main -->
                <div class="fm-page-main">
                    <!-- Chart -->
                    <div class="fm-card">
                        <div class="fm-card-header">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <strong><?php esc_html_e( 'Tickets per Month', 'fanaloka-maintenance' ); ?></strong>
                        </div>
                        <div class="fm-card-body">
                            <canvas id="fm-monthly-chart" height="260"></canvas>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="fm-card">
                        <div class="fm-card-header">
                            <span class="dashicons dashicons-list-view"></span>
                            <strong><?php esc_html_e( 'Recent Activities', 'fanaloka-maintenance' ); ?></strong>
                        </div>
                        <div class="fm-card-body fm-card-body-np">
                            <?php if ( empty( $recent ) ) : ?>
                                <p class="fm-empty-text"><?php esc_html_e( 'No recent activities.', 'fanaloka-maintenance' ); ?></p>
                            <?php else : ?>
                                <table class="fm-table">
                                    <thead>
                                        <tr>
                                            <th style="width:160px;"><?php esc_html_e( 'Time', 'fanaloka-maintenance' ); ?></th>
                                            <th style="width:80px;"><?php esc_html_e( 'Level', 'fanaloka-maintenance' ); ?></th>
                                            <th><?php esc_html_e( 'Message', 'fanaloka-maintenance' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $recent as $log ) : ?>
                                            <tr>
                                                <td class="fm-nowrap"><?php echo esc_html( $log['time'] ); ?></td>
                                                <td><span class="fm-badge fm-badge-<?php echo esc_attr( $log['level'] ); ?>"><?php echo esc_html( strtoupper( $log['level'] ) ); ?></span></td>
                                                <td><?php echo esc_html( $log['message'] ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="fm-page-sidebar">
                    <!-- Quick Actions -->
                    <div class="fm-sidebar-card">
                        <div class="fm-sidebar-card-header">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <strong><?php esc_html_e( 'Quick Actions', 'fanaloka-maintenance' ); ?></strong>
                        </div>
                        <div class="fm-sidebar-card-body">
                            <button type="button" class="fm-btn fm-btn-primary fm-btn-block fm-btn-sync" id="fm-sync-now">
                                <span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Sync Now', 'fanaloka-maintenance' ); ?>
                            </button>
                            <div id="fm-sync-status" style="display:none;"></div>
                            <div class="fm-btn-row">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-settings' ) ); ?>" class="fm-btn fm-btn-outline">
                                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'fanaloka-maintenance' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-requests' ) ); ?>" class="fm-btn fm-btn-outline">
                                    <span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Requests', 'fanaloka-maintenance' ); ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Last Sync -->
                    <div class="fm-sidebar-card">
                        <div class="fm-sidebar-card-header">
                            <span class="dashicons dashicons-email-alt"></span>
                            <strong><?php esc_html_e( 'Last Sync', 'fanaloka-maintenance' ); ?></strong>
                        </div>
                        <div class="fm-sidebar-card-body">
                            <div class="fm-info-row">
                                <span class="fm-info-label"><?php esc_html_e( 'Time', 'fanaloka-maintenance' ); ?></span>
                                <span class="fm-info-value">
                                    <?php
                                    if ( '-' === $last_sync['time'] ) {
                                        echo esc_html( $last_sync['time'] );
                                    } else {
                                        $sync_ts = strtotime( $last_sync['time'] );
                                        $full    = wp_date( 'D, d M Y \a\t g:i A', $sync_ts );
                                        echo esc_html( human_time_diff( $sync_ts, time() ) . ' ago' );
                                        echo ' <span class="fm-info-tooltip" title="' . esc_attr( $full ) . '">(' . esc_html( wp_date( 'g:i A', $sync_ts ) ) . ')</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="fm-info-row">
                                <span class="fm-info-label"><?php esc_html_e( 'Emails', 'fanaloka-maintenance' ); ?></span>
                                <span class="fm-info-value"><?php echo esc_html( $last_sync['total'] ); ?></span>
                            </div>
                            <div class="fm-info-row">
                                <span class="fm-info-label"><?php esc_html_e( 'Created', 'fanaloka-maintenance' ); ?></span>
                                <span class="fm-info-value"><?php echo esc_html( $last_sync['created'] ); ?></span>
                            </div>
                            <div class="fm-info-row">
                                <span class="fm-info-label"><?php esc_html_e( 'Replies', 'fanaloka-maintenance' ); ?></span>
                                <span class="fm-info-value"><?php echo esc_html( $last_sync['replies'] ); ?></span>
                            </div>
                            <div class="fm-info-row">
                                <span class="fm-info-label"><?php esc_html_e( 'Ignored', 'fanaloka-maintenance' ); ?></span>
                                <span class="fm-info-value"><?php echo esc_html( $last_sync['ignored'] ?? 0 ); ?></span>
                            </div>
                            <div class="fm-info-row">
                                <span class="fm-info-label"><?php esc_html_e( 'Sent Synced', 'fanaloka-maintenance' ); ?></span>
                                <span class="fm-info-value"><?php echo esc_html( $last_sync['sent_synced'] ?? 0 ); ?></span>
                            </div>
                            <div class="fm-info-row">
                                <span class="fm-info-label"><?php esc_html_e( 'Errors', 'fanaloka-maintenance' ); ?></span>
                                <span class="fm-info-value" style="color:<?php echo $last_sync['errors'] > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo esc_html( $last_sync['errors'] ); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Auto Sync -->
                    <div class="fm-sidebar-card">
                        <div class="fm-sidebar-card-header">
                            <span class="dashicons dashicons-controls-play"></span>
                            <strong><?php esc_html_e( 'Auto Sync', 'fanaloka-maintenance' ); ?></strong>
                        </div>
                        <div class="fm-sidebar-card-body">
                            <?php
                            $cron = CronManager::instance();
                            $auto_sync = get_option( 'fm_auto_sync', 'yes' );
                            $interval  = get_option( 'fm_sync_interval', 5 );
                            ?>
                            <div class="fm-info-row">
                                <span class="fm-info-label"><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></span>
                                <?php if ( 'yes' === $auto_sync && $cron->is_scheduled() ) : ?>
                                    <span class="fm-badge fm-badge-success"><?php esc_html_e( 'Active', 'fanaloka-maintenance' ); ?></span>
                                <?php else : ?>
                                    <span class="fm-badge fm-badge-danger"><?php esc_html_e( 'Inactive', 'fanaloka-maintenance' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="fm-info-row">
                                <span class="fm-info-label"><?php esc_html_e( 'Interval', 'fanaloka-maintenance' ); ?></span>
                                <span class="fm-info-value"><?php printf( esc_html__( '%d min', 'fanaloka-maintenance' ), $interval ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('fm-monthly-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo wp_json_encode( $chart['labels'] ); ?>,
                    datasets: [{
                        label: '<?php echo esc_js( __( 'Tickets', 'fanaloka-maintenance' ) ); ?>',
                        data: <?php echo wp_json_encode( $chart['data'] ); ?>,
                        backgroundColor: '#2271b1',
                        borderColor: '#135e96',
                        borderWidth: 1,
                        borderRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        });
        </script>

        <style>
        /* Shared Design System */
        .fm-page-wrap { max-width: 100%; margin: 0; padding: 0 20px 0 0; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-page-title .dashicons { color: #2271b1; }
        .fm-page-columns { display: flex; gap: 20px; align-items: flex-start; }
        .fm-page-main { flex: 1; min-width: 0; }
        .fm-page-sidebar { width: 300px; flex-shrink: 0; }

        /* Stat Cards */
        .fm-stats-row { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .fm-stat-card { flex: 1; min-width: 150px; display: flex; align-items: center; gap: 12px; padding: 16px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: transform 0.15s, box-shadow 0.15s; }
        .fm-stat-card:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.08); }
        .fm-stat-card-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .fm-stat-card-info { display: flex; flex-direction: column; }
        .fm-stat-card-count { font-size: 24px; font-weight: 700; line-height: 1.2; color: #1d2327; }
        .fm-stat-card-label { font-size: 12px; color: #646970; font-weight: 500; }

        /* Cards */
        .fm-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-card-header { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid #e2e4e7; font-size: 14px; }
        .fm-card-header .dashicons { color: #2271b1; }
        .fm-card-body { padding: 16px; }
        .fm-card-body-np { padding: 0; }

        /* Sidebar Cards */
        .fm-sidebar-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-sidebar-card-header { display: flex; align-items: center; gap: 6px; padding: 10px 14px; background: #f9f9f9; border-bottom: 1px solid #e2e4e7; font-size: 13px; border-radius: 8px 8px 0 0; }
        .fm-sidebar-card-header .dashicons { color: #2271b1; font-size: 16px; width: 16px; height: 16px; }
        .fm-sidebar-card-body { padding: 12px 14px; }

        /* Info Rows */
        .fm-info-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #f0f0f1; }
        .fm-info-row:last-child { border-bottom: none; }
        .fm-info-label { font-size: 12px; color: #646970; }
        .fm-info-value { font-size: 13px; color: #1d2327; font-weight: 500; }

        /* Table */
        .fm-table { width: 100%; border-collapse: collapse; }
        .fm-table th { text-align: left; padding: 10px 14px; font-size: 12px; font-weight: 600; color: #646970; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #e2e4e7; background: #f9f9f9; }
        .fm-table td { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid #f0f0f1; color: #1d2327; }
        .fm-table tr:hover td { background: #f9f9f9; }
        .fm-nowrap { white-space: nowrap; }

        /* Badges */
        .fm-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; line-height: 1.4; }
        .fm-badge-info { background: #f0f6fc; color: #2271b1; }
        .fm-badge-warning { background: #fcf9e8; color: #996800; }
        .fm-badge-success { background: #edfaef; color: #00a32a; }
        .fm-badge-danger { background: #fcf0f1; color: #d63638; }

        /* Buttons */
        .fm-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid transparent; text-decoration: none; transition: all 0.15s; }
        .fm-btn-primary { background: #2271b1; color: #fff; border-color: #2271b1; }
        .fm-btn-primary:hover { background: #135e96; color: #fff; }
        .fm-btn-outline { background: #fff; color: #1d2327; border-color: #ccc; }
        .fm-btn-outline:hover { background: #f0f0f1; color: #1d2327; }
        .fm-btn-block { width: 100%; }
        .fm-btn-row { display: flex; gap: 8px; margin-top: 10px; }
        .fm-btn-row .fm-btn { flex: 1; }
        .fm-empty-text { padding: 20px; text-align: center; color: #8c8f94; }

        /* Mobile Responsive */
        @media (max-width: 782px) {
            .fm-page-header { padding: 12px 14px; }
            .fm-page-title { font-size: 17px; }
            .fm-page-columns { flex-direction: column; }
            .fm-page-main { width: 100%; }
            .fm-page-sidebar { width: 100%; }
            .fm-stats-row { gap: 8px; }
            .fm-stat-card { min-width: 100%; box-sizing: border-box; }
            .fm-card-body-np { overflow-x: auto; }
            .fm-table { min-width: 520px; }
            /* Make the scroll affordance visible instead of relying on an invisible
               overlay scrollbar that makes the truncated text look permanently cut off. */
            .fm-card-body-np::-webkit-scrollbar { height: 8px; }
            .fm-card-body-np::-webkit-scrollbar-track { background: transparent; }
            .fm-card-body-np::-webkit-scrollbar-thumb { background: #dadce0; border-radius: 4px; }
        }
        </style>
        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.fm-btn-sync', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $status = $('#fm-sync-status');

                $btn.prop('disabled', true).text(fmAdmin.syncing || 'Syncing...');
                $status.show().html('<span style="color:#2271b1;">Syncing emails...</span>');

                $.post(fmAdmin.ajaxUrl, {
                    action: 'fm_sync_now',
                    nonce: fmAdmin.nonce,
                }, function(response) {
                    if (response.success) {
                        $btn.text(fmAdmin.syncComplete || 'Sync Complete!');
                        $status.html('<span style="color:#00a32a;">' + response.data.message + '</span>');
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $btn.text(fmAdmin.failed || 'Failed');
                        $status.html('<span style="color:#d63638;">' + (response.data.message || 'Error') + '</span>');
                    }
                    setTimeout(function() {
                        $btn.prop('disabled', false).text(fmAdmin.syncNow || 'Sync Now');
                    }, 3000);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get dashboard statistics.
     *
     * @return array{total: int, open: int, completed_today: int, waiting_client: int, critical: int}
     */
    private function get_stats(): array {
        $total = wp_count_posts( 'maintenance_request' );
        $all   = $total->publish + ( $total->draft ?? 0 );

        return [
            'total'           => $all,
            'open'            => $this->count_by_status( [ 'new', 'open', 'in-progress' ] ),
            'completed_today' => $this->count_by_status_date( 'completed', current_time( 'Y-m-d' ) ),
            'waiting_client'  => $this->count_by_status( [ 'waiting-client' ] ),
            'critical'        => $this->count_by_priority( 'critical', [ 'new', 'open', 'in-progress', 'waiting-client' ] ),
        ];
    }

    /**
     * Count tickets by status.
     *
     * @param array<int, string> $statuses Status values.
     * @return int Count.
     */
    private function count_by_status( array $statuses ): int {
        $count = 0;

        foreach ( $statuses as $status ) {
            $args = [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    [
                        'key'   => '_fm_status',
                        'value' => $status,
                    ],
                ],
            ];

            $query = new \WP_Query( $args );
            $count += $query->found_posts;
        }

        return $count;
    }

    /**
     * Count tickets by status and completion date.
     *
     * @param string $status Status value.
     * @param string $date   Date string (Y-m-d).
     * @return int Count.
     */
    private function count_by_status_date( string $status, string $date ): int {
        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => '_fm_status',
                    'value'   => $status,
                ],
                [
                    'key'     => '_fm_completion_date',
                    'value'   => $date,
                    'compare' => 'LIKE',
                ],
            ],
        ];

        $query = new \WP_Query( $args );
        return $query->found_posts;
    }

    /**
     * Count tickets by priority and status.
     *
     * @param string              $priority Priority value.
     * @param array<int, string>  $statuses Status values.
     * @return int Count.
     */
    private function count_by_priority( string $priority, array $statuses ): int {
        $meta_query = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            [
                'key'   => '_fm_priority',
                'value' => $priority,
            ],
            [
                'key'     => '_fm_status',
                'value'   => $statuses,
                'compare' => 'IN',
            ],
        ];

        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ];

        $query = new \WP_Query( $args );
        return $query->found_posts;
    }

    /**
     * Get chart data for last 12 months.
     *
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    private function get_monthly_chart_data(): array {
        $labels = [];
        $data   = [];

        for ( $i = 11; $i >= 0; $i-- ) {
            $month = strtotime( '-' . $i . ' months' );
            $labels[] = date( 'M Y', $month );

            $start = date( 'Y-m-01', $month );
            $end   = date( 'Y-m-t', $month );

            $args = [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'date_query'     => [
                    [
                        'after'     => $start,
                        'before'    => $end,
                        'inclusive' => true,
                    ],
                ],
            ];

            $query  = new \WP_Query( $args );
            $data[] = $query->found_posts;
        }

        return [
            'labels' => $labels,
            'data'   => $data,
        ];
    }

    /**
     * Get recent log activities.
     *
     * @param int $limit Number of entries.
     * @return array<int, array<string, mixed>>
     */
    private function get_recent_activities( int $limit = 15 ): array {
        return \Fanaloka\Maintenance\Logger\Logger::get_logs( $limit );
    }
}
