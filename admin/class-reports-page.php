<?php
/**
 * Reports Page - Display reports, charts, and CSV export.
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
        // Handle CSV export.
        $this->handle_export();

        $report    = new ReportManager();
        $period    = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'month'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $summary   = $report->get_summary();
        $monthly   = $report->get_monthly_report( 12 );
        $status    = $report->get_count_by_status( $period );
        $priority  = $report->get_count_by_priority( $period );
        $dev_perf  = $report->get_developer_performance();
        ?>

        <div class="wrap">
            <h1><?php esc_html_e( 'Reports', 'fanaloka-maintenance' ); ?></h1>

            <!-- Period Filter -->
            <div class="fm-report-filters" style="margin:15px 0;">
                <a href="?page=fm-reports&period=all" class="button <?php echo 'all' === $period ? 'button-primary' : ''; ?>">
                    <?php esc_html_e( 'All Time', 'fanaloka-maintenance' ); ?>
                </a>
                <a href="?page=fm-reports&period=today" class="button <?php echo 'today' === $period ? 'button-primary' : ''; ?>">
                    <?php esc_html_e( 'Today', 'fanaloka-maintenance' ); ?>
                </a>
                <a href="?page=fm-reports&period=week" class="button <?php echo 'week' === $period ? 'button-primary' : ''; ?>">
                    <?php esc_html_e( 'This Week', 'fanaloka-maintenance' ); ?>
                </a>
                <a href="?page=fm-reports&period=month" class="button <?php echo 'month' === $period ? 'button-primary' : ''; ?>">
                    <?php esc_html_e( 'This Month', 'fanaloka-maintenance' ); ?>
                </a>
                <a href="?page=fm-reports&period=year" class="button <?php echo 'year' === $period ? 'button-primary' : ''; ?>">
                    <?php esc_html_e( 'This Year', 'fanaloka-maintenance' ); ?>
                </a>
                <a href="?page=fm-reports&action=export&period=<?php echo esc_attr( $period ); ?>" class="button" style="float:right;">
                    <?php esc_html_e( 'Export CSV', 'fanaloka-maintenance' ); ?>
                </a>
            </div>

            <div class="fm-report-columns" style="display:flex;gap:20px;">
                <!-- Main Content -->
                <div style="flex:3;">
                    <!-- Summary Cards -->
                    <div style="display:flex;gap:15px;margin-bottom:20px;">
                        <div class="postbox" style="flex:1;text-align:center;padding:15px;">
                            <h3 style="margin:0;"><?php echo esc_html( $summary['total'] ); ?></h3>
                            <p style="margin:5px 0 0;color:#666;"><?php esc_html_e( 'Total Tickets', 'fanaloka-maintenance' ); ?></p>
                        </div>
                        <div class="postbox" style="flex:1;text-align:center;padding:15px;">
                            <h3 style="margin:0;">
                                <?php
                                printf(
                                    /* translators: %s: hours */
                                    esc_html__( '%sh', 'fanaloka-maintenance' ),
                                    esc_html( $summary['avg_completion_h'] )
                                );
                                ?>
                            </h3>
                            <p style="margin:5px 0 0;color:#666;"><?php esc_html_e( 'Avg Completion', 'fanaloka-maintenance' ); ?></p>
                        </div>
                        <div class="postbox" style="flex:1;text-align:center;padding:15px;">
                            <h3 style="margin:0;">
                                <?php
                                printf(
                                    /* translators: %s: hours */
                                    esc_html__( '%sh', 'fanaloka-maintenance' ),
                                    esc_html( $summary['avg_response_h'] )
                                );
                                ?>
                            </h3>
                            <p style="margin:5px 0 0;color:#666;"><?php esc_html_e( 'Avg Response', 'fanaloka-maintenance' ); ?></p>
                        </div>
                    </div>

                    <!-- Monthly Chart -->
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e( 'Monthly Report (Last 12 Months)', 'fanaloka-maintenance' ); ?></h2>
                        <div class="inside" style="padding:10px;">
                            <canvas id="fm-report-chart" height="300"></canvas>
                        </div>
                    </div>

                    <!-- By Status -->
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e( 'By Status', 'fanaloka-maintenance' ); ?></h2>
                        <div class="inside">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></th>
                                        <th><?php esc_html_e( 'Count', 'fanaloka-maintenance' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $status as $key => $count ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( \Fanaloka\Maintenance\Ticket\TicketManager::STATUSES[ $key ] ?? $key ); ?></td>
                                            <td><strong><?php echo esc_html( $count ); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- By Priority -->
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e( 'By Priority', 'fanaloka-maintenance' ); ?></h2>
                        <div class="inside">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Priority', 'fanaloka-maintenance' ); ?></th>
                                        <th><?php esc_html_e( 'Count', 'fanaloka-maintenance' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $priority as $key => $count ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( \Fanaloka\Maintenance\Ticket\TicketManager::PRIORITIES[ $key ] ?? $key ); ?></td>
                                            <td><strong><?php echo esc_html( $count ); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div style="flex:1;min-width:300px;">
                    <!-- Developer Performance -->
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e( 'Developer Performance', 'fanaloka-maintenance' ); ?></h2>
                        <div class="inside">
                            <?php if ( empty( $dev_perf ) ) : ?>
                                <p><?php esc_html_e( 'No data yet.', 'fanaloka-maintenance' ); ?></p>
                            <?php else : ?>
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Developer', 'fanaloka-maintenance' ); ?></th>
                                            <th><?php esc_html_e( 'Total', 'fanaloka-maintenance' ); ?></th>
                                            <th><?php esc_html_e( 'Done', 'fanaloka-maintenance' ); ?></th>
                                            <th><?php esc_html_e( 'Avg Hrs', 'fanaloka-maintenance' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $dev_perf as $dev ) : ?>
                                            <tr>
                                                <td><?php echo esc_html( $dev['name'] ); ?></td>
                                                <td><?php echo esc_html( $dev['total'] ); ?></td>
                                                <td><?php echo esc_html( $dev['completed'] ); ?></td>
                                                <td><?php echo esc_html( $dev['avg_hours'] ); ?>h</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('fm-report-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo wp_json_encode( array_column( $monthly, 'month' ) ); ?>,
                    datasets: [
                        {
                            label: '<?php echo esc_js( __( 'Total', 'fanaloka-maintenance' ) ); ?>',
                            data: <?php echo wp_json_encode( array_column( $monthly, 'total' ) ); ?>,
                            backgroundColor: '#2271b1',
                            borderRadius: 3
                        },
                        {
                            label: '<?php echo esc_js( __( 'Completed', 'fanaloka-maintenance' ) ); ?>',
                            data: <?php echo wp_json_encode( array_column( $monthly, 'completed' ) ); ?>,
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

    /**
     * Handle CSV export.
     *
     * @return void
     */
    private function handle_export(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_GET['action'] ) || 'export' !== $_GET['action'] ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $report = new ReportManager();
        $csv    = $report->export_csv( [ 'status' => '', 'priority' => '' ] );

        $filename = sprintf( 'fm-report-%s-%s.csv', $period, date( 'Y-m-d' ) );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
}
