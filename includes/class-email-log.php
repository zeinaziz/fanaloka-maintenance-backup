<?php
/**
 * Email Log - Log outgoing (sent) emails sent by the plugin.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * EmailLog Class.
 */
class EmailLog {

    /**
     * Create the email log table.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;

        $table    = $wpdb->prefix . 'fm_email_log';
        $charset  = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) unsigned NOT NULL DEFAULT 0,
            to_email varchar(500) NOT NULL DEFAULT '',
            cc varchar(500) NOT NULL DEFAULT '',
            bcc varchar(500) NOT NULL DEFAULT '',
            subject varchar(500) NOT NULL DEFAULT '',
            body longtext NOT NULL,
            headers text NOT NULL,
            context varchar(50) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'sent',
            error text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY status (status),
            KEY context (context),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Send an email through wp_mail and log the outcome.
     *
     * @param string          $to       Recipient email address.
     * @param string          $subject  Email subject.
     * @param string          $body     Email body.
     * @param string|string[] $headers  Email headers (string or array).
     * @param string          $context  Context (e.g., 'notification', 'reply', 'test').
     * @param int             $ticket_id Related ticket ID (0 if none).
     * @return bool True on success.
     */
    public static function send( string $to, string $subject, string $body, $headers = [], string $context = '', int $ticket_id = 0 ): bool {
        $result = wp_mail( $to, $subject, $body, $headers );

        if ( $result ) {
            self::log( $to, $subject, $body, $headers, $context, 'sent', '', $ticket_id );
        } else {
            $error = '';
            if ( isset( $GLOBALS['phpmailer'] ) && $GLOBALS['phpmailer'] instanceof \PHPMailer\PHPMailer\PHPMailer ) {
                $error = (string) $GLOBALS['phpmailer']->ErrorInfo;
            }
            self::log( $to, $subject, $body, $headers, $context, 'failed', $error, $ticket_id );
        }

        return $result;
    }

    /**
     * Insert a log entry.
     *
     * @param string          $to       Recipient email address.
     * @param string          $subject  Email subject.
     * @param string          $body     Email body.
     * @param string|string[] $headers  Email headers (string or array).
     * @param string          $context  Context.
     * @param string          $status   Status: 'sent' or 'failed'.
     * @param string          $error    Error message (for failed emails).
     * @param int             $ticket_id Related ticket ID.
     * @return void
     */
    public static function log( string $to, string $subject, string $body, $headers = [], string $context = '', string $status = 'sent', string $error = '', int $ticket_id = 0 ): void {
        global $wpdb;

        if ( is_array( $headers ) ) {
            $headers = implode( "\n", $headers );
        }

        $cc  = '';
        $bcc = '';
        if ( ! empty( $headers ) ) {
            if ( preg_match( '/^CC:\s*(.+)$/mi', $headers, $m ) ) {
                $cc = trim( $m[1] );
            }
            if ( preg_match( '/^BCC:\s*(.+)$/mi', $headers, $m ) ) {
                $bcc = trim( $m[1] );
            }
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'fm_email_log',
            [
                'ticket_id'  => absint( $ticket_id ),
                'to_email'   => $to,
                'cc'         => $cc,
                'bcc'        => $bcc,
                'subject'    => $subject,
                'body'       => $body,
                'headers'    => $headers,
                'context'    => $context,
                'status'     => $status,
                'error'      => $error,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            \Fanaloka\Maintenance\Logger\Logger::log( 'Email log insert failed: ' . $wpdb->last_error, \Fanaloka\Maintenance\Logger\Logger::LEVEL_ERROR );
        }
    }

    /**
     * Get paginated log entries.
     *
     * @param array<string, mixed> $args Query args.
     * @return array{items: array<int, array<string, mixed>>, total: int, pages: int}
     */
    public static function get_logs( array $args = [] ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'fm_email_log';

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'status'   => '',
            'context'  => '',
            'search'   => '',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where   = '1=1';
        $prepare = [];

        if ( ! empty( $args['status'] ) ) {
            $where    .= ' AND status = %s';
            $prepare[] = $args['status'];
        }
        if ( ! empty( $args['context'] ) ) {
            $where    .= ' AND context = %s';
            $prepare[] = $args['context'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where    .= ' AND (to_email LIKE %s OR subject LIKE %s)';
            $like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $prepare[] = $like;
            $prepare[] = $like;
        }

        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        if ( ! empty( $prepare ) ) {
            $count_sql = $wpdb->prepare( $count_sql, ...$prepare );
        }
        $total = (int) $wpdb->get_var( $count_sql );

        $sql        = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $prepare[]  = $args['per_page'];
        $prepare[]  = $offset;
        $sql        = $wpdb->prepare( $sql, ...$prepare );
        $rows       = $wpdb->get_results( $sql, ARRAY_A ) ?: [];

        return [
            'items' => $rows,
            'total' => $total,
            'pages' => (int) ceil( $total / $args['per_page'] ),
        ];
    }

    /**
     * Delete a single log entry.
     *
     * @param int $id Log entry ID.
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;
        return false !== $wpdb->delete( $wpdb->prefix . 'fm_email_log', [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Delete all log entries.
     *
     * @return bool
     */
    public static function clear(): bool {
        global $wpdb;
        return false !== $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}fm_email_log" );
    }
}
