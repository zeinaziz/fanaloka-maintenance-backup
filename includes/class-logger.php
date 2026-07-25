<?php
/**
 * Logger.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logger Class.
 */
class Logger {

    /**
     * Log levels.
     */
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    /**
     * Write a log entry.
     *
     * @param string $message Log message.
     * @param string $level   Log level.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function log( string $message, string $level = self::LEVEL_INFO, array $context = [] ): void {
        $entry = [
            'time'    => current_time( 'mysql' ),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];

        $logs   = get_option( 'fm_logs', [] );
        $logs[] = $entry;

        // Keep only last 1000 entries.
        if ( count( $logs ) > 1000 ) {
            $logs = array_slice( $logs, -1000 );
        }

        update_option( 'fm_logs', $logs, false );
    }

    /**
     * Get all logs.
     *
     * @param int $limit Number of logs to retrieve.
     * @return array<int, array<string, mixed>>
     */
    public static function get_logs( int $limit = 100 ): array {
        $logs = get_option( 'fm_logs', [] );
        return array_slice( array_reverse( $logs ), 0, $limit );
    }

    /**
     * Clear all logs.
     *
     * @return void
     */
    public static function clear(): void {
        delete_option( 'fm_logs' );
    }
}
