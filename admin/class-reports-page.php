<?php
/**
 * Reports Page - Display reports and charts.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

use Fanaloka\Maintenance\Report\ReportManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ReportsPage Class.
 */
class ReportsPage {

    /**
     * Render the reports page.
     *
     * @return void
     */
    public function render(): void {
        $report   = new ReportManager();
        $period   = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'month'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $summary  = $report->get_summary( $period );
        $is_daily = in_array( $period, [ 'today', 'week' ], true );
        $chart_data = $is_daily
            ? $report->get_daily_report( 'today' === $period ? 1 : 7, $period )
            : $report->get_monthly_report( 12, $period );
        $status   = $report->get_count_by_status( $period );
        $priority = $report->get_count_by_priority( $period );
        $dev_perf = $report->get_developer_performance( $period );
        ?>

        <div class="fm-page-wrap">
            <div class="fm-page-header">
                <h1 class="fm-page-title">
                    <span class="fm-icon"><?php echo \Fanaloka\Maintenance\Icons::get( 'reports', '#646970' ); ?></span>
                    <?php esc_html_e( 'Reports', 'fanaloka-maintenance' ); ?>
                </h1>
                <div class="fm-page-header-right">
                    <!-- Period Filters -->
                    <div class="fm-period-filters">
                        <a href="?page=fm-reports&period=all" class="fm-btn-filter <?php echo 'all' === $period ? 'active' : ''; ?>"><?php esc_html_e( 'All Time', 'fanaloka-maintenance' ); ?></a>
                        <a href="?page=fm-reports&period=today" class="fm-btn-filter <?php echo 'today' === $period ? 'active' : ''; ?>"><?php esc_html_e( 'Today', 'fanaloka-maintenance' ); ?></a>
                        <a href="?page=fm-reports&period=week" class="fm-btn-filter <?php echo 'week' === $period ? 'active' : ''; ?>"><?php esc_html_e( 'This Week', 'fanaloka-maintenance' ); ?></a>
                        <a href="?page=fm-reports&period=month" class="fm-btn-filter <?php echo 'month' === $period ? 'active' : ''; ?>"><?php esc_html_e( 'This Month', 'fanaloka-maintenance' ); ?></a>
                        <a href="?page=fm-reports&period=year" class="fm-btn-filter <?php echo 'year' === $period ? 'active' : ''; ?>"><?php esc_html_e( 'This Year', 'fanaloka-maintenance' ); ?></a>
                    </div>
                </div>
            </div>

            <div class="fm-page-columns">
                <!-- Main Content -->
                <div class="fm-page-main">
                    <!-- Summary Cards -->
                    <div class="fm-stats-row">
                        <div class="fm-stat-card">
                            <div class="fm-stat-icon" style="background:#e7f0fd;color:#2271b1;">
                                <span class="dashicons dashicons-feedback"></span>
                            </div>
                            <div class="fm-stat-content">
                                <span class="fm-stat-number"><?php echo esc_html( $summary['total'] ); ?></span>
                                <span class="fm-stat-label"><?php esc_html_e( 'Total Tickets', 'fanaloka-maintenance' ); ?></span>
                            </div>
                        </div>
                        <div class="fm-stat-card">
                            <div class="fm-stat-icon" style="background:#e6f9e6;color:#00a32a;">
                                <span class="dashicons dashicons-clock"></span>
                            </div>
                            <div class="fm-stat-content">
                                <span class="fm-stat-number">
                                    <?php
                                    printf(
                                        /* translators: %s: hours */
                                        esc_html__( '%sh', 'fanaloka-maintenance' ),
                                        esc_html( $summary['avg_completion_h'] )
                                    );
                                    ?>
                                </span>
                                <span class="fm-stat-label"><?php esc_html_e( 'Avg Completion', 'fanaloka-maintenance' ); ?></span>
                            </div>
                        </div>
                        <div class="fm-stat-card">
                            <div class="fm-stat-icon" style="background:#fef3cd;color:#856404;">
                                <span class="dashicons dashicons-email-alt"></span>
                            </div>
                            <div class="fm-stat-content">
                                <span class="fm-stat-number">
                                    <?php
                                    printf(
                                        /* translators: %s: hours */
                                        esc_html__( '%sh', 'fanaloka-maintenance' ),
                                        esc_html( $summary['avg_response_h'] )
                                    );
                                    ?>
                                </span>
                                <span class="fm-stat-label"><?php esc_html_e( 'Avg Response Time', 'fanaloka-maintenance' ); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Chart -->
                    <div class="fm-card">
                        <div class="fm-card-header">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php
                            $chart_titles = [
                                'all'   => __( 'Ticket Report (All Time)', 'fanaloka-maintenance' ),
                                'today' => __( 'Ticket Report (Today)', 'fanaloka-maintenance' ),
                                'week'  => __( 'Ticket Report (This Week)', 'fanaloka-maintenance' ),
                                'month' => __( 'Ticket Report (This Month)', 'fanaloka-maintenance' ),
                                'year'  => __( 'Ticket Report (This Year)', 'fanaloka-maintenance' ),
                            ];
                            echo esc_html( $chart_titles[ $period ] ?? $chart_titles['all'] );
                            ?>
                        </div>
                        <div class="fm-card-body">
                            <canvas id="fm-report-chart" height="300"></canvas>
                        </div>
                    </div>

                    <!-- By Status -->
                    <div class="fm-card">
                        <div class="fm-card-header">
                            <span class="dashicons dashicons-admin-list"></span>
                            <?php esc_html_e( 'By Status', 'fanaloka-maintenance' ); ?>
                        </div>
                        <div class="fm-card-body" style="padding:0;">
                            <table class="fm-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></th>
                                        <th style="text-align:right;"><?php esc_html_e( 'Count', 'fanaloka-maintenance' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $status as $key => $count ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( \Fanaloka\Maintenance\Ticket\TicketManager::STATUSES[ $key ] ?? $key ); ?></td>
                                            <td style="text-align:right;"><strong><?php echo esc_html( $count ); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- By Priority -->
                    <div class="fm-card">
                        <div class="fm-card-header">
                            <span class="dashicons dashicons-flag"></span>
                            <?php esc_html_e( 'By Priority', 'fanaloka-maintenance' ); ?>
                        </div>
                        <div class="fm-card-body" style="padding:0;">
                            <table class="fm-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Priority', 'fanaloka-maintenance' ); ?></th>
                                        <th style="text-align:right;"><?php esc_html_e( 'Count', 'fanaloka-maintenance' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $priority as $key => $count ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( \Fanaloka\Maintenance\Ticket\TicketManager::PRIORITIES[ $key ] ?? $key ); ?></td>
                                            <td style="text-align:right;"><strong><?php echo esc_html( $count ); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="fm-page-sidebar">
                    <!-- Developer Performance -->
                    <div class="fm-sidebar-card">
                        <div class="fm-sidebar-card-header">
                            <span class="dashicons dashicons-groups"></span>
                            <?php esc_html_e( 'Developer Performance', 'fanaloka-maintenance' ); ?>
                        </div>
                        <div class="fm-sidebar-card-body">
                            <?php if ( empty( $dev_perf ) ) : ?>
                                <p class="fm-no-data"><?php esc_html_e( 'No data yet.', 'fanaloka-maintenance' ); ?></p>
                            <?php else : ?>
                                <?php foreach ( $dev_perf as $dev ) : ?>
                                    <div class="fm-dev-stat">
                                        <div class="fm-dev-info">
                                            <strong><?php echo esc_html( $dev['name'] ); ?></strong>
                                        </div>
                                        <div class="fm-dev-numbers">
                                            <span class="fm-dev-total"><?php echo esc_html( $dev['completed'] ); ?> / <?php echo esc_html( $dev['total'] ); ?></span>
                                            <span class="fm-dev-avg"><?php echo esc_html( $dev['avg_hours'] ); ?>h</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .fm-page-wrap { max-width: 100%; margin: 0; padding: 0 20px 40px; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-page-header-right { display: flex; align-items: center; gap: 12px; }
        .fm-page-columns { display: flex; gap: 20px; align-items: flex-start; }
        .fm-page-main { flex: 1; min-width: 0; }
        .fm-page-sidebar { width: 300px; flex-shrink: 0; }
        .fm-stats-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
        .fm-stat-card { display: flex; align-items: center; gap: 14px; padding: 18px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-stat-icon { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 10px; }
        .fm-stat-icon .dashicons { font-size: 22px; width: 22px; height: 22px; }
        .fm-stat-number { font-size: 28px; font-weight: 700; line-height: 1.1; color: #1d2327; display: block; }
        .fm-stat-label { font-size: 13px; color: #646970; display: block; margin-top: 2px; }
        .fm-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-card-header { display: flex; align-items: center; gap: 8px; padding: 14px 18px; border-bottom: 1px solid #e2e4e7; font-size: 15px; font-weight: 600; color: #1d2327; background: #f9f9f9; }
        .fm-card-header .dashicons { color: #8c8f94; font-size: 18px; }
        .fm-card-body { padding: 18px; }
        .fm-table { width: 100%; border-collapse: collapse; }
        .fm-table th { padding: 10px 18px; background: #f9f9f9; border-bottom: 1px solid #e2e4e7; font-size: 13px; color: #646970; font-weight: 600; text-align: left; }
        .fm-table td { padding: 10px 18px; border-bottom: 1px solid #f0f0f1; font-size: 14px; color: #1d2327; }
        .fm-table tr:hover td { background: #f9f9f9; }
        .fm-sidebar-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-sidebar-card-header { display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: #f9f9f9; border-bottom: 1px solid #e2e4e7; font-size: 14px; font-weight: 600; color: #1d2327; }
        .fm-sidebar-card-header .dashicons { color: #8c8f94; font-size: 16px; }
        .fm-sidebar-card-body { padding: 14px 16px; }
        .fm-dev-stat { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f1; }
        .fm-dev-stat:last-child { border-bottom: none; }
        .fm-dev-info { font-size: 14px; }
        .fm-dev-numbers { text-align: right; }
        .fm-dev-total { font-size: 14px; font-weight: 600; color: #1d2327; display: block; }
        .fm-dev-avg { font-size: 12px; color: #8c8f94; }
        .fm-no-data { text-align: center; padding: 20px; color: #8c8f94; font-size: 14px; }
        .fm-period-filters { display: flex; gap: 0; background: #f0f0f1; border-radius: 6px; padding: 2px; }
        .fm-btn-filter { padding: 6px 14px; border-radius: 5px; font-size: 13px; color: #646970; text-decoration: none; font-weight: 500; transition: all 0.15s; }
        .fm-btn-filter:hover { background: #fff; color: #1d2327; }
        .fm-btn-filter.active { background: #fff; color: #1d2327; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        </style>

        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('fm-report-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo wp_json_encode( array_column( $chart_data, 'label' ) ); ?>,
                    datasets: [
                        {
                            label: '<?php echo esc_js( __( 'Total', 'fanaloka-maintenance' ) ); ?>',
                            data: <?php echo wp_json_encode( array_column( $chart_data, 'total' ) ); ?>,
                            backgroundColor: '#2271b1',
                            borderRadius: 3
                        },
                        {
                            label: '<?php echo esc_js( __( 'Completed', 'fanaloka-maintenance' ) ); ?>',
                            data: <?php echo wp_json_encode( array_column( $chart_data, 'completed' ) ); ?>,
                            backgroundColor: '#00a32a',
                            borderRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        });
        </script>
        <?php
    }
}
