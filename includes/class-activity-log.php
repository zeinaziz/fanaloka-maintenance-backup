<?php
namespace Fanaloka\Maintenance\Log;

defined( 'ABSPATH' ) || exit;

use Fanaloka\Maintenance\Database;

class ActivityLog {

    public static function create_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'fm_activity_log';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_name VARCHAR(255) NOT NULL DEFAULT '',
            action VARCHAR(100) NOT NULL DEFAULT '',
            object_type VARCHAR(50) NOT NULL DEFAULT '',
            object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            details TEXT NOT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_action (action),
            KEY idx_object (object_type, object_id),
            KEY idx_user (user_id),
            KEY idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function log( string $action, string $object_type = '', int $object_id = 0, string $details = '' ): void {
        global $wpdb;

        $user = wp_get_current_user();
        $user_id = is_object( $user ) ? $user->ID : 0;
        $user_name = ( is_object( $user ) && ! empty( $user->display_name ) ) ? $user->display_name : 'System';
        $ip = self::get_client_ip();

        $result = $wpdb->insert(
            $wpdb->prefix . 'fm_activity_log',
            [
                'user_id'     => $user_id,
                'user_name'   => $user_name,
                'action'      => $action,
                'object_type' => $object_type,
                'object_id'   => $object_id,
                'details'     => $details,
                'ip_address'  => $ip,
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            \Fanaloka\Maintenance\Logger\Logger::log( 'Activity log insert failed: ' . $wpdb->last_error, \Fanaloka\Maintenance\Logger\Logger::LEVEL_ERROR );
        }
    }

    public static function get_logs( array $args = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'fm_activity_log';

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'action'   => '',
            'user_id'  => 0,
            'object'   => '',
            'object_id' => 0,
            'search'   => '',
            'since'    => '',
            'until'    => '',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where = '1=1';
        $prepare = [];

        if ( ! empty( $args['action'] ) ) {
            $where .= ' AND action = %s';
            $prepare[] = $args['action'];
        }
        if ( $args['user_id'] > 0 ) {
            $where .= ' AND user_id = %d';
            $prepare[] = $args['user_id'];
        }
        if ( ! empty( $args['object'] ) ) {
            $where .= ' AND object_type = %s';
            $prepare[] = $args['object'];
        }
        if ( $args['object_id'] > 0 ) {
            $where .= ' AND object_id = %d';
            $prepare[] = $args['object_id'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where .= ' AND (details LIKE %s OR user_name LIKE %s OR action LIKE %s)';
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $prepare[] = $like;
            $prepare[] = $like;
            $prepare[] = $like;
        }
        if ( ! empty( $args['since'] ) ) {
            $where .= ' AND created_at >= %s';
            $prepare[] = $args['since'];
        }
        if ( ! empty( $args['until'] ) ) {
            $where .= ' AND created_at <= %s';
            $prepare[] = $args['until'];
        }

        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        // Count total.
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        if ( ! empty( $prepare ) ) {
            $count_sql = $wpdb->prepare( $count_sql, ...$prepare );
        }
        $total = (int) $wpdb->get_var( $count_sql );

        // Fetch rows.
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $prepare[] = $args['per_page'];
        $prepare[] = $offset;
        $sql = $wpdb->prepare( $sql, ...$prepare );
        $rows = $wpdb->get_results( $sql, ARRAY_A ) ?: [];

        return [
            'items' => $rows,
            'total' => $total,
            'pages' => (int) ceil( $total / $args['per_page'] ),
        ];
    }

    public static function get_actions(): array {
        return [
            'ticket_created'       => __( 'Ticket Created', 'fanaloka-maintenance' ),
            'ticket_status_changed' => __( 'Status Changed', 'fanaloka-maintenance' ),
            'ticket_priority_changed' => __( 'Priority Changed', 'fanaloka-maintenance' ),
            'ticket_assigned'      => __( 'Developer Assigned', 'fanaloka-maintenance' ),
            'reply_sent'           => __( 'Reply Sent', 'fanaloka-maintenance' ),
            'internal_note_added'  => __( 'Internal Note Added', 'fanaloka-maintenance' ),
            'ticket_deleted'       => __( 'Ticket Deleted', 'fanaloka-maintenance' ),
            'bulk_deleted'         => __( 'Bulk Deleted', 'fanaloka-maintenance' ),
            'bulk_updated'         => __( 'Bulk Updated', 'fanaloka-maintenance' ),
            'email_synced'         => __( 'Email Synced', 'fanaloka-maintenance' ),
            'settings_changed'     => __( 'Settings Changed', 'fanaloka-maintenance' ),
            'attachment_uploaded'  => __( 'Attachment Uploaded', 'fanaloka-maintenance' ),
        ];
    }

    private static function get_client_ip(): string {
        // Use REMOTE_ADDR only — X-Forwarded-For/X-Real-IP can be spoofed.
        // SiteGround sets REMOTE_ADDR to the real client IP.
        return isset( $_SERVER['REMOTE_ADDR'] )
            ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : '';
    }
}
