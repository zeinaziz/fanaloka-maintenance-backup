<?php
/**
 * Conversation Manager - Handle ticket conversations using database table.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Ticket;

use Fanaloka\Maintenance\Database;
use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ConversationManager Class.
 */
class ConversationManager {

    /**
     * Add a conversation entry.
     *
     * @param int                    $ticket_id Ticket post ID.
     * @param string                 $type      Entry type: client, developer, system, internal.
     * @param string                 $body      Entry content.
     * @param array<string, string>  $meta      Optional meta data.
     * @return int|false Entry ID on success, false on failure.
     */
    public function add_entry( int $ticket_id, string $type, string $body, array $meta = [] ) {
        global $wpdb;

        $table = Database::table_name();

        $data = [
            'ticket_id'          => $ticket_id,
            'message_id'         => $meta['message_id'] ?? '',
            'parent_message_id'  => $meta['parent_message_id'] ?? '',
            'in_reply_to'        => $meta['in_reply_to'] ?? '',
            'references_header'  => $meta['references'] ?? '',
            'sender'             => $this->get_sender_name( $type, $meta ),
            'email'              => $meta['from_email'] ?? '',
            'subject'            => $meta['subject'] ?? '',
            'body'               => $body,
            'body_html'          => $meta['body_html'] ?? '',
            'entry_type'         => $type,
            'imap_uid'           => absint( $meta['imap_uid'] ?? 0 ),
            'created_at'         => $meta['date'] ?? current_time( 'mysql' ),
        ];

        $format = [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ];

        $result = $wpdb->insert( $table, $data, $format );

        if ( false === $result ) {
            Logger::log( 'Failed to add conversation entry: ' . $wpdb->last_error, Logger::LEVEL_ERROR );
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Add a reply from developer (from dashboard).
     *
     * @param int    $ticket_id Ticket post ID.
     * @param string $body      Reply content.
     * @return int|false Entry ID on success, false on failure.
     */
    public function add_reply_from_developer( int $ticket_id, string $body ) {
        global $wpdb;

        $table = Database::table_name();

        // Get last client message_id for threading.
        $last_client_msg_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT message_id FROM {$table}
                WHERE ticket_id = %d AND entry_type = 'client' AND message_id != ''
                ORDER BY id DESC LIMIT 1",
                $ticket_id
            )
        );

        // Generate new Message-ID for this reply.
        $message_id = ( new \Fanaloka\Maintenance\Email\EmailParser() )->generate_message_id( $ticket_id );

        // Build references chain.
        $references = $this->build_references_chain( $ticket_id, $last_client_msg_id );

        return $this->add_entry( $ticket_id, 'developer', $body, [
            'message_id'         => $message_id,
            'parent_message_id'  => $last_client_msg_id ?? '',
            'in_reply_to'        => $last_client_msg_id ?? '',
            'references'         => $references,
            'from_email'         => get_option( 'fm_imap_username', '' ),
        ] );
    }

    /**
     * Build references chain for outbound email.
     *
     * @param int         $ticket_id       Ticket post ID.
     * @param string|null $in_reply_to     In-Reply-To message_id.
     * @return string Space-separated Message-IDs.
     */
    private function build_references_chain( int $ticket_id, ?string $in_reply_to ): string {
        global $wpdb;

        $table    = Database::table_name();
        $refs     = [];

        if ( ! empty( $in_reply_to ) ) {
            // Get all message_ids in the thread, ordered by id.
            $all_refs = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT message_id FROM {$table}
                    WHERE ticket_id = %d AND message_id != ''
                    ORDER BY id ASC",
                    $ticket_id
                )
            );

            $refs = array_filter( $all_refs );
        }

        return implode( ' ', $refs );
    }

    /**
     * Add a system note.
     *
     * @param int    $ticket_id Ticket post ID.
     * @param string $body      Note content.
     * @return int|false Entry ID on success, false on failure.
     */
    public function add_system_note( int $ticket_id, string $body ) {
        return $this->add_entry( $ticket_id, 'system', $body );
    }

    /**
     * Add an internal note.
     *
     * @param int    $ticket_id Ticket post ID.
     * @param string $body      Note content.
     * @return int|false Entry ID on success, false on failure.
     */
    public function add_internal_note( int $ticket_id, string $body ) {
        return $this->add_entry( $ticket_id, 'internal', $body );
    }

    /**
     * Get all conversation entries for a ticket.
     *
     * @param int $ticket_id Ticket post ID.
     * @return array<int, array<string, mixed>> Conversation entries.
     */
    public function get_entries( int $ticket_id ): array {
        global $wpdb;

        $table = Database::table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY id ASC",
                $ticket_id
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get the last entry for a ticket.
     *
     * @param int $ticket_id Ticket post ID.
     * @return array<string, mixed>|false Last entry or false.
     */
    public function get_last_entry( int $ticket_id ) {
        global $wpdb;

        $table = Database::table_name();

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY id DESC LIMIT 1",
                $ticket_id
            ),
            ARRAY_A
        );

        return $result ?: false;
    }

    /**
     * Count entries for a ticket.
     *
     * @param int $ticket_id Ticket post ID.
     * @return int Entry count.
     */
    public function count_entries( int $ticket_id ): int {
        global $wpdb;

        $table = Database::table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE ticket_id = %d",
                $ticket_id
            )
        );
    }

    /**
     * Find ticket by Message-ID (searches conversation table + post meta).
     *
     * @param string $message_id Message-ID to search.
     * @return int|false Ticket post ID or false if not found.
     */
    public function find_ticket_by_message_id( string $message_id ) {
        if ( empty( $message_id ) ) {
            return false;
        }

        global $wpdb;

        $table = Database::table_name();

        // Search conversation table.
        $ticket_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ticket_id FROM {$table} WHERE message_id = %s LIMIT 1",
                $message_id
            )
        );

        if ( $ticket_id ) {
            return (int) $ticket_id;
        }

        // Also check post meta (_fm_message_id for original emails, _fm_last_dev_message_id for dev replies).
        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => '_fm_message_id',
                    'value' => $message_id,
                ],
                [
                    'key'   => '_fm_last_dev_message_id',
                    'value' => $message_id,
                ],
            ],
        ];

        $query = new \WP_Query( $args );

        if ( $query->have_posts() ) {
            return $query->posts[0];
        }

        return false;
    }

    /**
     * Find ticket by In-Reply-To header.
     *
     * @param string $in_reply_to In-Reply-To message_id.
     * @return int|false Ticket post ID or false if not found.
     */
    public function find_ticket_by_in_reply_to( string $in_reply_to ) {
        if ( empty( $in_reply_to ) ) {
            return false;
        }

        return $this->find_ticket_by_message_id( trim( $in_reply_to ) );
    }

    /**
     * Find ticket by References header.
     *
     * @param string $references References header value.
     * @return int|false Ticket post ID or false if not found.
     */
    public function find_ticket_by_references( string $references ) {
        if ( empty( $references ) ) {
            return false;
        }

        $refs = preg_split( '/\s+/', trim( $references ) );

        // Search from last to first (most recent reference first).
        $refs = array_reverse( $refs );

        foreach ( $refs as $ref ) {
            $ref = trim( $ref );
            if ( empty( $ref ) ) {
                continue;
            }

            $ticket_id = $this->find_ticket_by_message_id( $ref );
            if ( false !== $ticket_id ) {
                return $ticket_id;
            }
        }

        return false;
    }

    /**
     * Find ticket by normalized subject + sender email.
     *
     * @param string $normalized_subject Normalized subject.
     * @param string $sender_email      Sender email.
     * @param int    $exclude_ticket_id  Ticket ID to exclude from results.
     * @return int|false Ticket post ID or false if not found.
     */
    public function find_ticket_by_subject_and_email( string $normalized_subject, string $sender_email, int $exclude_ticket_id = 0 ) {
        if ( empty( $normalized_subject ) || empty( $sender_email ) ) {
            return false;
        }

        global $wpdb;

        // Search tickets by client email and subject.
        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => 5,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_fm_client_email',
                    'value' => $sender_email,
                ],
            ],
            'orderby'  => 'date',
            'order'    => 'DESC',
        ];

        $query = new \WP_Query( $args );

        foreach ( $query->posts as $post_id ) {
            if ( $post_id === $exclude_ticket_id ) {
                continue;
            }

            $ticket_subject = get_post_meta( $post_id, '_fm_subject', true );

            if ( empty( $ticket_subject ) ) {
                continue;
            }

            $normalized_ticket_subject = $this->normalize_subject( $ticket_subject );

            if ( $normalized_ticket_subject === $normalized_subject ) {
                // Check if ticket is active (not closed > 30 days).
                $status = get_post_meta( $post_id, '_fm_status', true );

                if ( in_array( $status, [ 'completed', 'cancelled' ], true ) ) {
                    $completion_date = get_post_meta( $post_id, '_fm_completion_date', true );

                    if ( ! empty( $completion_date ) ) {
                        $thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

                        if ( $completion_date < $thirty_days_ago ) {
                            // Closed more than 30 days ago, skip.
                            continue;
                        }
                    }
                }

                return $post_id;
            }
        }

        return false;
    }

    /**
     * Find ticket by normalized subject (any sender).
     *
     * @param string $normalized_subject Normalized subject.
     * @return int|false Ticket post ID or false if not found.
     */
    public function find_ticket_by_subject( string $normalized_subject ) {
        if ( empty( $normalized_subject ) ) {
            return false;
        }

        global $wpdb;

        // Get all tickets ordered by date.
        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => 5,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query( $args );

        foreach ( $query->posts as $post_id ) {
            $ticket_subject = get_post_meta( $post_id, '_fm_subject', true );

            if ( empty( $ticket_subject ) ) {
                continue;
            }

            $normalized_ticket_subject = $this->normalize_subject( $ticket_subject );

            if ( $normalized_ticket_subject === $normalized_subject ) {
                return $post_id;
            }
        }

        return false;
    }

    /**
     * Normalize email subject by removing reply/forward prefixes.
     *
     * @param string $subject Raw subject.
     * @return string Normalized subject.
     */
    public function normalize_subject( string $subject ): string {
        $subject = trim( $subject );

        // Decode MIME encoded subjects.
        $subject = mb_decode_mimeheader( $subject );

        // Remove ticket number prefix [REQ-XXX].
        $prefix = get_option( 'fm_ticket_prefix', 'REQ' );
        $subject = preg_replace( '/^\[' . preg_quote( $prefix, '/' ) . '-\d+\]\s*/i', '', $subject );

        // Remove multiple levels of Re:/Fwd: prefixes.
        // Matches: Re:, RE:, Fw:, FW:, Fwd:, FWD:, Aw:, Jawab:, Balasan:, etc.
        $subject = preg_replace(
            '/^(?:' .
            'Re(?:\:\s*Re(?:\:\s*)*)*' . '|' .
            'RE(?:\:\s*RE(?:\:\s*)*)*' . '|' .
            'Fw(?:\:\s*Fw(?:\:\s*)*)*' . '|' .
            'FW(?:\:\s*FW(?:\:\s*)*)*' . '|' .
            'Fwd(?:\:\s*Fwd(?:\:\s*)*)*' . '|' .
            'FWD(?:\:\s*FWD(?:\:\s*)*)*' . '|' .
            'Aw(?:\:\s*Aw(?:\:\s*)*)*' . '|' .
            'Jawab(?:\:\s*Jawab(?:\:\s*)*)*' . '|' .
            'Balasan(?:\:\s*Balasan(?:\:\s*)*)*' .
            ')\s*/i',
            '',
            $subject
        );

        // Trim whitespace.
        $subject = trim( $subject );

        return $subject;
    }

    /**
     * Check if a message_id already exists in the conversation table.
     *
     * @param string $message_id Message-ID to check.
     * @return bool True if found.
     */
    public function message_id_exists( string $message_id ): bool {
        if ( empty( $message_id ) ) {
            return false;
        }

        global $wpdb;

        $table = Database::table_name();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE message_id = %s",
                $message_id
            )
        );

        return $count > 0;
    }

    /**
     * Get last client message_id for a ticket (for In-Reply-To).
     *
     * @param int $ticket_id Ticket post ID.
     * @return string|null Message-ID or null.
     */
    public function get_last_client_message_id( int $ticket_id ): ?string {
        global $wpdb;

        $table = Database::table_name();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT message_id FROM {$table}
                WHERE ticket_id = %d AND entry_type = 'client' AND message_id != ''
                ORDER BY id DESC LIMIT 1",
                $ticket_id
            )
        );

        return $result ?: null;
    }

    /**
     * Get last developer message_id for a ticket (for In-Reply-To).
     *
     * @param int $ticket_id Ticket post ID.
     * @return string|null Message-ID or null.
     */
    public function get_last_developer_message_id( int $ticket_id ): ?string {
        global $wpdb;

        $table = Database::table_name();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT message_id FROM {$table}
                WHERE ticket_id = %d AND entry_type = 'developer' AND message_id != ''
                ORDER BY id DESC LIMIT 1",
                $ticket_id
            )
        );

        return $result ?: null;
    }

    /**
     * Delete a conversation entry.
     *
     * @param int $entry_id Entry ID to delete.
     * @return bool True on success.
     */
    public function delete_entry( int $entry_id ): bool {
        global $wpdb;

        $table = Database::table_name();

        $result = $wpdb->delete( $table, [ 'id' => $entry_id ], [ '%d' ] );

        return false !== $result;
    }

    /**
     * Get sender name based on type.
     *
     * @param string                $type Entry type.
     * @param array<string, string> $meta Optional meta.
     * @return string Sender name.
     */
    private function get_sender_name( string $type, array $meta = [] ): string {
        switch ( $type ) {
            case 'client':
                return $meta['from_name'] ?? $meta['from_email'] ?? __( 'Client', 'fanaloka-maintenance' );

            case 'developer':
                $user = wp_get_current_user();
                return $user ? $user->display_name : __( 'Developer', 'fanaloka-maintenance' );

            case 'system':
                return __( 'System', 'fanaloka-maintenance' );

            case 'internal':
                $user = wp_get_current_user();
                return $user ? $user->display_name : __( 'Admin', 'fanaloka-maintenance' );

            default:
                return __( 'Unknown', 'fanaloka-maintenance' );
        }
    }
}
