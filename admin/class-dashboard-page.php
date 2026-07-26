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

        <div class="wrap">
            <h1><?php esc_html_e( 'Maintenance Dashboard', 'fanaloka-maintenance' ); ?></h1>

            <!-- Stats Widgets -->
            <div class="fm-dashboard-widgets">
                <div class="fm-stat-box">
                    <div class="fm-stat-icon dashicons dashicons-feedback"></div>
                    <span class="count"><?php echo esc_html( $stats['total'] ); ?></span>
                    <span class="label"><?php esc_html_e( 'Total Requests', 'fanaloka-maintenance' ); ?></span>
                </div>
                <div class="fm-stat-box open">
                    <div class="fm-stat-icon dashicons dashicons-admin-comments"></div>
                    <span class="count"><?php echo esc_html( $stats['open'] ); ?></span>
                    <span class="label"><?php esc_html_e( 'Open Requests', 'fanaloka-maintenance' ); ?></span>
                </div>
                <div class="fm-stat-box completed">
                    <div class="fm-stat-icon dashicons dashicons-yes-alt"></div>
                    <span class="count"><?php echo esc_html( $stats['completed_today'] ); ?></span>
                    <span class="label"><?php esc_html_e( 'Completed Today', 'fanaloka-maintenance' ); ?></span>
                </div>
                <div class="fm-stat-box waiting">
                    <div class="fm-stat-icon dashicons dashicons-clock"></div>
                    <span class="count"><?php echo esc_html( $stats['waiting_client'] ); ?></span>
                    <span class="label"><?php esc_html_e( 'Waiting Client', 'fanaloka-maintenance' ); ?></span>
                </div>
                <div class="fm-stat-box critical">
                    <div class="fm-stat-icon dashicons dashicons-warning"></div>
                    <span class="count"><?php echo esc_html( $stats['critical'] ); ?></span>
                    <span class="label"><?php esc_html_e( 'Critical', 'fanaloka-maintenance' ); ?></span>
                </div>
            </div>

            <div class="fm-dashboard-columns">
                <!-- Main Content -->
                <div class="fm-dashboard-main">
                    <!-- Monthly Chart -->
                    <div class="postbox">
                        <h2 class="hndle"><span class="dashicons dashicons-chart-bar" style="margin-right:5px;"></span><?php esc_html_e( 'Tickets per Month', 'fanaloka-maintenance' ); ?></h2>
                        <div class="inside">
                            <canvas id="fm-monthly-chart" height="280"></canvas>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="postbox">
                        <h2 class="hndle"><span class="dashicons dashicons-list-view" style="margin-right:5px;"></span><?php esc_html_e( 'Recent Activities', 'fanaloka-maintenance' ); ?></h2>
                        <div class="inside">
                            <?php if ( empty( $recent ) ) : ?>
                                <p style="padding:15px;"><?php esc_html_e( 'No recent activities.', 'fanaloka-maintenance' ); ?></p>
                            <?php else : ?>
                                <table class="widefat striped" style="margin:0;">
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
                                                <td style="white-space:nowrap;"><?php echo esc_html( $log['time'] ); ?></td>
                                                <td>
                                                    <span class="fm-log-badge fm-log-<?php echo esc_attr( $log['level'] ); ?>">
                                                        <?php echo esc_html( strtoupper( $log['level'] ) ); ?>
                                                    </span>
                                                </td>
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
                <div class="fm-dashboard-sidebar">
                    <!-- Quick Actions -->
                    <div class="postbox fm-sidebar-box">
                        <h2 class="hndle"><span class="dashicons dashicons-admin-settings" style="margin-right:5px;"></span><?php esc_html_e( 'Quick Actions', 'fanaloka-maintenance' ); ?></h2>
                        <div class="inside">
                            <button type="button" class="button button-primary button-hero fm-btn-sync" id="fm-sync-now" style="width:100%; margin-bottom:10px;">
                                <span class="dashicons dashicons-update" style="margin-right:5px;"></span><?php esc_html_e( 'Sync Now', 'fanaloka-maintenance' ); ?>
                            </button>
                            <p id="fm-sync-status" style="display:none; margin-top:10px;"></p>
                            <div style="display:flex; gap:8px; margin-top:10px;">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-settings' ) ); ?>" class="button" style="flex:1; text-align:center;">
                                    <span class="dashicons dashicons-admin-generic" style="margin-right:3px;"></span><?php esc_html_e( 'Settings', 'fanaloka-maintenance' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-requests' ) ); ?>" class="button" style="flex:1; text-align:center;">
                                    <span class="dashicons dashicons-list-view" style="margin-right:3px;"></span><?php esc_html_e( 'Requests', 'fanaloka-maintenance' ); ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Last Sync -->
                    <div class="postbox fm-sidebar-box">
                        <h2 class="hndle"><span class="dashicons dashicons-email-alt" style="margin-right:5px;"></span><?php esc_html_e( 'Last Sync', 'fanaloka-maintenance' ); ?></h2>
                        <div class="inside">
                            <table class="widefat" style="border:0;">
                                <tr>
                                    <td style="padding:8px 10px; border:0;"><strong><?php esc_html_e( 'Time', 'fanaloka-maintenance' ); ?></strong></td>
                                    <td style="padding:8px 10px; border:0;"><?php echo esc_html( $last_sync['time'] ); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 10px; border:0;"><strong><?php esc_html_e( 'Emails Found', 'fanaloka-maintenance' ); ?></strong></td>
                                    <td style="padding:8px 10px; border:0;"><?php echo esc_html( $last_sync['total'] ); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 10px; border:0;"><strong><?php esc_html_e( 'Tickets Created', 'fanaloka-maintenance' ); ?></strong></td>
                                    <td style="padding:8px 10px; border:0;"><?php echo esc_html( $last_sync['created'] ); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 10px; border:0;"><strong><?php esc_html_e( 'Replies Added', 'fanaloka-maintenance' ); ?></strong></td>
                                    <td style="padding:8px 10px; border:0;"><?php echo esc_html( $last_sync['replies'] ); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 10px; border:0;"><strong><?php esc_html_e( 'Errors', 'fanaloka-maintenance' ); ?></strong></td>
                                    <td style="padding:8px 10px; border:0; color:<?php echo $last_sync['errors'] > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo esc_html( $last_sync['errors'] ); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Auto Sync Status -->
                    <div class="postbox fm-sidebar-box">
                        <h2 class="hndle"><span class="dashicons dashicons-controls-play" style="margin-right:5px;"></span><?php esc_html_e( 'Auto Sync', 'fanaloka-maintenance' ); ?></h2>
                        <div class="inside">
                            <?php
                            $cron = CronManager::instance();
                            $auto_sync = get_option( 'fm_auto_sync', 'yes' );
                            $interval  = get_option( 'fm_sync_interval', 5 );
                            ?>
                            <p style="margin:8px 0;">
                                <strong><?php esc_html_e( 'Status:', 'fanaloka-maintenance' ); ?></strong>
                                <?php if ( 'yes' === $auto_sync && $cron->is_scheduled() ) : ?>
                                    <span class="fm-sync-active"><?php esc_html_e( 'Active', 'fanaloka-maintenance' ); ?></span>
                                <?php else : ?>
                                    <span class="fm-sync-inactive"><?php esc_html_e( 'Inactive', 'fanaloka-maintenance' ); ?></span>
                                <?php endif; ?>
                            </p>
                            <p style="margin:8px 0;">
                                <strong><?php esc_html_e( 'Interval:', 'fanaloka-maintenance' ); ?></strong>
                                <?php
                                printf(
                                    /* translators: %d: interval in minutes */
                                    esc_html__( '%d minutes', 'fanaloka-maintenance' ),
                                    $interval
                                );
                                ?>
                            </p>
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
        .fm-dashboard-widgets { display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0; }
        .fm-stat-box { flex: 1; min-width: 160px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; padding: 20px 15px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: transform 0.15s, box-shadow 0.15s; }
        .fm-stat-box:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.08); }
        .fm-stat-icon { font-size: 28px; color: #2271b1; margin-bottom: 8px; }
        .fm-stat-box .count { font-size: 36px; font-weight: 700; display: block; line-height: 1.2; color: #1d2327; }
        .fm-stat-box .label { color: #646970; font-size: 13px; margin-top: 5px; display: block; font-weight: 500; }
        .fm-stat-box.critical { border-top: 3px solid #d63638; }
        .fm-stat-box.critical .fm-stat-icon { color: #d63638; }
        .fm-stat-box.open { border-top: 3px solid #dba617; }
        .fm-stat-box.open .fm-stat-icon { color: #dba617; }
        .fm-stat-box.completed { border-top: 3px solid #00a32a; }
        .fm-stat-box.completed .fm-stat-icon { color: #00a32a; }
        .fm-stat-box.waiting { border-top: 3px solid #996800; }
        .fm-stat-box.waiting .fm-stat-icon { color: #996800; }
        .fm-stat-box:first-child { border-top: 3px solid #2271b1; }
        .fm-dashboard-columns { display: flex; gap: 20px; margin-top: 20px; align-items: flex-start; }
        .fm-dashboard-main { flex: 3; min-width: 0; }
        .fm-dashboard-sidebar { flex: 1; min-width: 280px; max-width: 340px; }
        .fm-dashboard-sidebar .postbox { margin-bottom: 15px; }
        #poststuff .postbox .hndle { padding: 10px 15px; margin: 0; }
        #poststuff .postbox .inside { padding: 0 15px 15px; margin: 0; }
        .fm-sidebar-box .hndle { cursor: default; }
        .fm-log-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; line-height: 1.6; }
        .fm-log-info { background: #f0f6fc; color: #2271b1; }
        .fm-log-warning { background: #fcf0f1; color: #996800; }
        .fm-log-error { background: #fcf0f1; color: #d63638; }
        .fm-sync-active { color: #00a32a; font-weight: 600; }
        .fm-sync-inactive { color: #d63638; font-weight: 600; }
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
