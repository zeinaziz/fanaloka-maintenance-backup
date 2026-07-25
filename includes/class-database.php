<?php
/**
 * Database - Create and manage custom tables.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance;

use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database Class.
 */
class Database {

    /**
     * Get database version.
     *
     * @return string
     */
    public static function get_version(): string {
        return get_option( 'fm_db_version', '0' );
    }

    /**
     * Check if tables exist and create/update if needed.
     *
     * @return void
     */
    public static function maybe_create_tables(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'fm_conversations';
        $installed_ver   = self::get_version();
        $required_ver    = '3';

        if ( $installed_ver === $required_ver ) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) unsigned NOT NULL,
            message_id varchar(255) NOT NULL DEFAULT '',
            parent_message_id varchar(255) NOT NULL DEFAULT '',
            in_reply_to varchar(255) NOT NULL DEFAULT '',
            references_header text NOT NULL,
            sender varchar(255) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            subject varchar(500) NOT NULL DEFAULT '',
            body longtext NOT NULL,
            entry_type varchar(20) NOT NULL DEFAULT 'client',
            attachments text NOT NULL,
            imap_uid int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY message_id (message_id(191)),
            KEY parent_message_id (parent_message_id(191)),
            KEY in_reply_to (in_reply_to(191)),
            KEY email (email),
            KEY entry_type (entry_type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'fm_db_version', $required_ver );

        Logger::log( 'Database tables created/updated' );
    }

    /**
     * Get the conversations table name.
     *
     * @return string
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'fm_conversations';
    }

    /**
     * Migrate old post meta conversations to new table.
     *
     * @return int Number of entries migrated.
     */
    public static function migrate_old_conversations(): int {
        global $wpdb;

        $table_name = self::table_name();
        $migrated   = 0;

        // Get all tickets with old conversation meta.
        $tickets = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_fm_conversation'
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'maintenance_request'
            )"
        );

        foreach ( $tickets as $ticket_id ) {
            $entries = get_post_meta( $ticket_id, '_fm_conversation', true );

            if ( ! is_array( $entries ) ) {
                continue;
            }

            foreach ( $entries as $entry ) {
                // Skip if message_id already exists.
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_name} WHERE message_id = %s AND ticket_id = %d",
                        $entry['message_id'] ?? '',
                        $ticket_id
                    )
                );

                if ( $exists > 0 ) {
                    continue;
                }

                $wpdb->insert(
                    $table_name,
                    [
                        'ticket_id'          => $ticket_id,
                        'message_id'         => $entry['message_id'] ?? '',
                        'parent_message_id'  => '',
                        'in_reply_to'        => '',
                        'references_header'  => '',
                        'sender'             => $entry['author'] ?? '',
                        'email'              => $entry['from_email'] ?? '',
                        'subject'            => $entry['subject'] ?? '',
                        'body'               => $entry['content'] ?? '',
                        'entry_type'         => $entry['type'] ?? 'client',
                        'imap_uid'           => 0,
                        'created_at'         => $entry['date'] ?? current_time( 'mysql' ),
                    ],
                    [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
                );

                $migrated++;
            }
        }

        if ( $migrated > 0 ) {
            Logger::log( sprintf( 'Migrated %d old conversation entries to new table', $migrated ) );
        }

        return $migrated;
    }
}
