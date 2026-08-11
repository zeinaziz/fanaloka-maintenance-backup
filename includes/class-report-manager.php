<?php
/**
 * Report Manager - Generate reports and statistics.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Report;

use Fanaloka\Maintenance\Ticket\TicketManager;
use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ReportManager Class.
 */
class ReportManager {

    /**
     * Get ticket count by status.
     *
     * @param string $period Period: all, today, week, month, year.
     * @return array<string, int> Status => count.
     */
    public function get_count_by_status( string $period = 'all' ): array {
        $counts = [];

        foreach ( TicketManager::STATUSES as $key => $label ) {
            $args = [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    [
                        'key'   => '_fm_status',
                        'value' => $key,
                    ],
                ],
            ];

            $args = $this->apply_date_filter( $args, $period );

            $query          = new \WP_Query( $args );
            $counts[ $key ] = $query->found_posts;
        }

        return $counts;
    }

    /**
     * Get ticket count by priority.
     *
     * @param string $period Period: all, today, week, month, year.
     * @return array<string, int> Priority => count.
     */
    public function get_count_by_priority( string $period = 'all' ): array {
        $counts = [];

        foreach ( TicketManager::PRIORITIES as $key => $label ) {
            $args = [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    [
                        'key'   => '_fm_priority',
                        'value' => $key,
                    ],
                ],
            ];

            $args = $this->apply_date_filter( $args, $period );

            $query          = new \WP_Query( $args );
            $counts[ $key ] = $query->found_posts;
        }

        return $counts;
    }

    /**
     * Get average completion time in hours.
     *
     * @param string $period Period filter.
     * @return float Average hours or 0.
     */
    public function get_avg_completion_time( string $period = 'month' ): float {
        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => '_fm_status',
                    'value'   => 'completed',
                ],
                [
                    'key'     => '_fm_completion_date',
                    'value'   => '',
                    'compare' => '!=',
                ],
            ],
        ];

        $args = $this->apply_date_filter( $args, $period, '_fm_completion_date' );

        $query  = new \WP_Query( $args );
        $total  = 0;
        $count  = 0;

        foreach ( $query->posts as $post_id ) {
            $created = get_post_meta( $post_id, '_fm_date_created', true );
            $done    = get_post_meta( $post_id, '_fm_completion_date', true );

            if ( empty( $created ) || empty( $done ) ) {
                continue;
            }

            $ts_created = strtotime( $created );
            $ts_done    = strtotime( $done );

            if ( false === $ts_created || false === $ts_done ) {
                continue;
            }

            $diff_hours = ( $ts_done - $ts_created ) / 3600;
            $total += $diff_hours;
            $count++;
        }

        return $count > 0 ? round( $total / $count, 1 ) : 0;
    }

    /**
     * Get average first response time in hours.
     *
     * @param string $period Period filter.
     * @return float Average hours or 0.
     */
    public function get_avg_response_time( string $period = 'month' ): float {
        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        $args = $this->apply_date_filter( $args, $period );

        $query = new \WP_Query( $args );
        $total = 0;
        $count = 0;

        foreach ( $query->posts as $post_id ) {
            $entries = get_post_meta( $post_id, '_fm_conversation', true );

            if ( empty( $entries ) || ! is_array( $entries ) ) {
                continue;
            }

            // Find first developer reply.
            $first_dev_reply = null;
            foreach ( $entries as $entry ) {
                if ( 'developer' === $entry['type'] ) {
                    $first_dev_reply = $entry;
                    break;
                }
            }

            if ( null === $first_dev_reply ) {
                continue;
            }

            $created = get_post_meta( $post_id, '_fm_date_created', true );

            if ( empty( $created ) ) {
                continue;
            }

            $ts_created = strtotime( $created );
            $ts_reply   = strtotime( $first_dev_reply['date'] ?? '' );

            if ( false === $ts_created || false === $ts_reply ) {
                continue;
            }

            $diff_hours = ( $ts_reply - $ts_created ) / 3600;

            if ( $diff_hours >= 0 ) {
                $total += $diff_hours;
                $count++;
            }
        }

        return $count > 0 ? round( $total / $count, 1 ) : 0;
    }

    /**
     * Get developer performance stats.
     *
     * @param string $period Period: all, today, week, month, year.
     * @return array<int, array{id: int, name: string, total: int, completed: int, avg_hours: float}>
     */
    public function get_developer_performance( string $period = 'all' ): array {
        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => '_fm_assigned_dev',
                    'value'   => 0,
                    'compare' => '!=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ];

        $args = $this->apply_date_filter( $args, $period );

        $query = new \WP_Query( $args );
        $devs  = [];

        foreach ( $query->posts as $post_id ) {
            $dev_id = absint( get_post_meta( $post_id, '_fm_assigned_dev', true ) );

            if ( 0 === $dev_id ) {
                continue;
            }

            if ( ! isset( $devs[ $dev_id ] ) ) {
                $user = get_userdata( $dev_id );
                $devs[ $dev_id ] = [
                    'id'        => $dev_id,
                    'name'      => $user ? $user->display_name : __( 'Unknown', 'fanaloka-maintenance' ),
                    'total'     => 0,
                    'completed' => 0,
                    'hours'     => 0,
                ];
            }

            $devs[ $dev_id ]['total']++;

            $status = get_post_meta( $post_id, '_fm_status', true );

            if ( 'completed' === $status ) {
                $devs[ $dev_id ]['completed']++;

                $created = get_post_meta( $post_id, '_fm_date_created', true );
                $done    = get_post_meta( $post_id, '_fm_completion_date', true );

                if ( ! empty( $created ) && ! empty( $done ) ) {
                    $ts1 = strtotime( $created );
                    $ts2 = strtotime( $done );

                    if ( false !== $ts1 && false !== $ts2 ) {
                        $devs[ $dev_id ]['hours'] += ( $ts2 - $ts1 ) / 3600;
                    }
                }
            }
        }

        // Calculate averages.
        $result = [];
        foreach ( $devs as $dev ) {
            $result[] = [
                'id'        => $dev['id'],
                'name'      => $dev['name'],
                'total'     => $dev['total'],
                'completed' => $dev['completed'],
                'avg_hours' => $dev['completed'] > 0 ? round( $dev['hours'] / $dev['completed'], 1 ) : 0,
            ];
        }

        usort( $result, function ( $a, $b ) {
            return $b['completed'] <=> $a['completed'];
        } );

        return $result;
    }

    /**
     * Get monthly report data.
     *
     * @param int $months Number of months to look back.
     * @return array<int, array{month: string, total: int, completed: int, new_count: int}>
     */
    public function get_monthly_report( int $months = 12, string $period = 'all' ): array {
        $report = [];

        // Adjust months based on period.
        if ( 'today' === $period || 'week' === $period ) {
            $months = 1;
        } elseif ( 'month' === $period ) {
            $months = 1;
        } elseif ( 'year' === $period ) {
            $months = 12;
        }

        for ( $i = $months - 1; $i >= 0; $i-- ) {
            $month = strtotime( '-' . $i . ' months' );
            $start = date( 'Y-m-01', $month );
            $end   = date( 'Y-m-t', $month );

            // Total tickets.
            $total_query = new \WP_Query( [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    [
                        'key'     => '_fm_date_created',
                        'value'   => [ $start, $end ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE',
                    ],
                ],
            ] );

            // Completed tickets.
            $completed_query = new \WP_Query( [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    'relation' => 'AND',
                    [
                        'key'     => '_fm_date_created',
                        'value'   => [ $start, $end ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE',
                    ],
                    [
                        'key'   => '_fm_status',
                        'value' => 'completed',
                    ],
                ],
            ] );

            // New tickets.
            $new_query = new \WP_Query( [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    'relation' => 'AND',
                    [
                        'key'     => '_fm_date_created',
                        'value'   => [ $start, $end ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE',
                    ],
                    [
                        'key'   => '_fm_status',
                        'value' => 'new',
                    ],
                ],
            ] );

            $report[] = [
                'label'     => date( 'M Y', $month ),
                'total'     => $total_query->found_posts,
                'completed' => $completed_query->found_posts,
                'new_count' => $new_query->found_posts,
            ];
        }

        return $report;
    }

    /**
     * Get daily report for a given number of days.
     *
     * @param int    $days Number of days to look back.
     * @param string $period Period type.
     * @return array<int, array{label: string, total: int, completed: int, new_count: int}>
     */
    public function get_daily_report( int $days = 7, string $period = 'week' ): array {
        $report = [];

        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date  = strtotime( '-' . $i . ' days' );
            $start = date( 'Y-m-d', $date );
            $end   = $start;

            $total_query = new \WP_Query( [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    [
                        'key'     => '_fm_date_created',
                        'value'   => [ $start, $end . ' 23:59:59' ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATETIME',
                    ],
                ],
            ] );

            $completed_query = new \WP_Query( [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    'relation' => 'AND',
                    [
                        'key'     => '_fm_date_created',
                        'value'   => [ $start, $end . ' 23:59:59' ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATETIME',
                    ],
                    [
                        'key'   => '_fm_status',
                        'value' => 'completed',
                    ],
                ],
            ] );

            $new_query = new \WP_Query( [
                'post_type'      => 'maintenance_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    'relation' => 'AND',
                    [
                        'key'     => '_fm_date_created',
                        'value'   => [ $start, $end . ' 23:59:59' ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATETIME',
                    ],
                    [
                        'key'   => '_fm_status',
                        'value' => 'new',
                    ],
                ],
            ] );

            $label = 'today' === $period
                ? date( 'H:i', $date )
                : date( 'D, d M', $date );

            $report[] = [
                'label'     => $label,
                'total'     => $total_query->found_posts,
                'completed' => $completed_query->found_posts,
                'new_count' => $new_query->found_posts,
            ];
        }

        return $report;
    }

    /**
     * Export tickets to CSV.
     *
     * @param array<string, mixed> $args Query args.
     * @return string CSV content.
     */
    public function export_csv( array $args = [] ): string {
        $ticket_manager = new TicketManager();
        $result         = $ticket_manager->get_tickets( array_merge( $args, [
            'per_page' => 9999,
            'paged'    => 1,
        ] ) );

        $csv_rows = [];
        $csv_rows[] = [
            'Ticket',
            'Client',
            'Email',
            'Subject',
            'Status',
            'Priority',
            'Developer',
            'Created',
            'Completed',
        ];

        foreach ( $result['tickets'] as $ticket ) {
            $csv_rows[] = [
                $ticket['full_number'],
                $ticket['client_name'],
                $ticket['client_email'],
                $ticket['subject'],
                $ticket['status_label'],
                $ticket['priority_label'],
                $ticket['assigned_dev_name'],
                $ticket['date_created'],
                $ticket['completion_date'],
            ];
        }

        $output = '';

        foreach ( $csv_rows as $row ) {
            $output .= implode( ',', array_map( function ( $cell ) {
                // Prevent CSV formula injection — prefix dangerous characters.
                $cell = (string) $cell;
                if ( preg_match( '/^[=+\-@\t\r]/', $cell ) ) {
                    $cell = "'" . $cell;
                }
                return '"' . str_replace( '"', '""', $cell ) . '"';
            }, $row ) ) . "\n";
        }

        return $output;
    }

    /**
     * Apply date filter to query args.
     *
     * @param array<string, mixed> $args    Query args.
     * @param string               $period  Period filter.
     * @param string               $date_key Meta key for date comparison.
     * @return array<string, mixed> Modified args.
     */
    private function apply_date_filter( array $args, string $period, string $date_key = '_fm_date_created' ): array {
        if ( 'all' === $period ) {
            return $args;
        }

        $date_map = [
            'today' => current_time( 'Y-m-d' ),
            'week'  => gmdate( 'Y-m-d', strtotime( '-6 days' ) ),
            'month' => gmdate( 'Y-m-d', strtotime( '-1 month' ) ),
            'year'  => gmdate( 'Y-m-d', strtotime( '-1 year' ) ),
        ];

        if ( ! isset( $date_map[ $period ] ) ) {
            return $args;
        }

        $after_date = $date_map[ $period ];

        if ( ! isset( $args['meta_query'] ) ) {
            $args['meta_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        }

        $args['meta_query'][] = [
            'key'     => $date_key,
            'value'   => $after_date,
            'compare' => '>=',
            'type'    => 'DATE',
        ];

        return $args;
    }

    /**
     * Get summary stats.
     *
     * @param string $period Period: all, today, week, month, year.
     * @return array<string, mixed>
     */
    public function get_summary( string $period = 'all' ): array {
        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        $args    = $this->apply_date_filter( $args, $period );
        $query   = new \WP_Query( $args );
        $total   = $query->found_posts;

        return [
            'total'            => $total,
            'avg_completion_h' => $this->get_avg_completion_time( $period ),
            'avg_response_h'   => $this->get_avg_response_time( $period ),
            'by_status'        => $this->get_count_by_status( $period ),
            'by_priority'      => $this->get_count_by_priority( $period ),
            'developers'       => $this->get_developer_performance( $period ),
        ];
    }
}
