<?php
/**
 * Ticket Manager - Create and manage maintenance tickets.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Ticket;

use Fanaloka\Maintenance\Attachment\AttachmentManager;
use Fanaloka\Maintenance\Notification\NotificationManager;
use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TicketManager Class.
 */
class TicketManager {

    /**
     * Status options.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'new'            => 'New',
        'open'           => 'Open',
        'in-progress'    => 'In Progress',
        'waiting-client' => 'Waiting Client',
        'completed'      => 'Completed',
        'cancelled'      => 'Cancelled',
    ];

    /**
     * Priority options.
     *
     * @var array<string, string>
     */
    public const PRIORITIES = [
        'low'      => 'Low',
        'medium'   => 'Medium',
        'high'     => 'High',
        'critical' => 'Critical',
    ];

    /**
     * Find the ticket ID that an incoming email belongs to.
     *
     * Uses 4-priority detection:
     * P1: In-Reply-To header → search by Message-ID
     * P2: References header → search by Message-ID
     * P3: Subject normalization + sender email → match open ticket
     * P4: Subject normalization only → match open ticket
     *
     * @param array<string, mixed> $parsed Parsed email data.
     * @return int|false Ticket post ID or false if not found (create new).
     */
    public function find_ticket_for_email( array $parsed ) {
        $conversation = new ConversationManager();

        // PRIORITY 1: In-Reply-To header.
        $in_reply_to = $parsed['in_reply_to'] ?? '';
        if ( ! empty( $in_reply_to ) ) {
            $ticket_id = $conversation->find_ticket_by_in_reply_to( $in_reply_to );
            if ( false !== $ticket_id ) {
                Logger::log( sprintf( 'P1 match: In-Reply-To %s → ticket #%d', $in_reply_to, $ticket_id ) );
                return $ticket_id;
            }
        }

        // PRIORITY 2: References header.
        $references = $parsed['references'] ?? '';
        if ( ! empty( $references ) ) {
            $ticket_id = $conversation->find_ticket_by_references( $references );
            if ( false !== $ticket_id ) {
                Logger::log( sprintf( 'P2 match: References → ticket #%d', $ticket_id ) );
                return $ticket_id;
            }
        }

        // PRIORITY 3: Subject + sender email.
        $subject        = $parsed['subject'] ?? '';
        $sender_email   = $parsed['sender_email'] ?? '';
        $normalized_subject = $conversation->normalize_subject( $subject );

        if ( ! empty( $normalized_subject ) && ! empty( $sender_email ) ) {
            $ticket_id = $conversation->find_ticket_by_subject_and_email( $normalized_subject, $sender_email );
            if ( false !== $ticket_id ) {
                Logger::log( sprintf( 'P3 match: Subject+Email → ticket #%d', $ticket_id ) );
                return $ticket_id;
            }
        }

        // PRIORITY 4: Subject only (any sender).
        if ( ! empty( $normalized_subject ) ) {
            $ticket_id = $conversation->find_ticket_by_subject( $normalized_subject );
            if ( false !== $ticket_id ) {
                // Only match if same sender email.
                $ticket_email = get_post_meta( $ticket_id, '_fm_client_email', true );
                if ( strtolower( $ticket_email ) === strtolower( $sender_email ) ) {
                    Logger::log( sprintf( 'P4 match: Subject only → ticket #%d', $ticket_id ) );
                    return $ticket_id;
                }
            }
        }

        return false;
    }

    /**
     * Create a new ticket from parsed email data.
     *
     * @param array<string, mixed> $parsed Parsed email data.
     * @return int|false Ticket post ID or false on failure.
     */
    public function create_ticket_from_email( array $parsed ) {
        // Deduplication: skip if ticket with same message_id already exists.
        $message_id = $parsed['message_id'] ?? '';
        if ( ! empty( $message_id ) ) {
            $conversation = new ConversationManager();
            if ( $conversation->message_id_exists( $message_id ) ) {
                Logger::log( sprintf( 'Skip duplicate: message_id %s already exists', $message_id ) );
                return false;
            }
        }

        // Deduplication: skip if same sender + subject within last 5 minutes.
        $sender_email = $parsed['sender_email'] ?? '';
        $subject      = $parsed['subject'] ?? '';
        if ( ! empty( $sender_email ) && ! empty( $subject ) ) {
            $existing = $this->find_recent_ticket( $sender_email, $subject );
            if ( false !== $existing ) {
                Logger::log( sprintf( 'Skip duplicate: same sender/subject already in ticket #%d', $existing ) );
                return false;
            }
        }

        $prefix = get_option( 'fm_ticket_prefix', 'REQ' );
        $number = $this->get_next_ticket_number();

        $ticket_title = sprintf( '[%s-%d] %s', $prefix, $number, $parsed['subject'] );

        $post_data = [
            'post_title'   => $ticket_title,
            'post_content' => $parsed['body'] ?? '',
            'post_type'    => 'maintenance_request',
            'post_status'  => 'publish',
            'post_date'    => $parsed['date'] ?? current_time( 'mysql' ),
        ];

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            Logger::log( 'Failed to create ticket: ' . $post_id->get_error_message(), Logger::LEVEL_ERROR );
            return false;
        }

        // Save meta fields.
        $this->save_ticket_meta( $post_id, [
            '_fm_ticket_number'   => $number,
            '_fm_ticket_prefix'   => $prefix,
            '_fm_client_name'     => $parsed['sender_name'] ?? '',
            '_fm_client_email'    => $parsed['sender_email'] ?? '',
            '_fm_subject'         => $parsed['subject'] ?? '',
            '_fm_status'          => get_option( 'fm_default_status', 'new' ),
            '_fm_priority'        => $this->detect_priority( $parsed ),
            '_fm_assigned_dev'    => 0,
            '_fm_date_created'    => $parsed['date'] ?? current_time( 'mysql' ),
            '_fm_last_updated'    => current_time( 'mysql' ),
            '_fm_sla'             => '',
            '_fm_completion_date' => '',
            '_fm_message_id'      => $parsed['message_id'] ?? '',
            '_fm_in_reply_to'     => $parsed['in_reply_to'] ?? '',
            '_fm_references'      => $parsed['references'] ?? '',
            '_fm_source'          => 'email',
        ] );

        // Save initial conversation entry.
        $conversation = new ConversationManager();
        $conversation->add_entry( $post_id, 'client', $parsed['body'] ?? '', [
            'from_name'  => $parsed['sender_name'] ?? '',
            'from_email' => $parsed['sender_email'] ?? '',
            'subject'    => $parsed['original_subject'] ?? $parsed['subject'] ?? '',
            'date'       => $parsed['date'] ?? current_time( 'mysql' ),
            'message_id' => $parsed['message_id'] ?? '',
            'body_html'  => $parsed['body_html'] ?? '',
            'imap_uid'   => $parsed['msg_number'] ?? 0,
        ] );

        // Save attachments and store IDs in conversation entry.
        if ( ! empty( $parsed['attachments'] ) ) {
            $attachment_manager = new AttachmentManager();
            $saved_ids = $attachment_manager->save_attachments_from_email( $post_id, $parsed );
            if ( ! empty( $saved_ids ) ) {
                // Update the latest conversation entry with attachment IDs.
                $last_entry = $conversation->get_last_entry( $post_id );
                if ( $last_entry ) {
                    global $wpdb;
                    $table = \Fanaloka\Maintenance\Database::table_name();
                    $wpdb->update( $table, [ 'attachments' => implode( ',', $saved_ids ) ], [ 'id' => $last_entry['id'] ], [ '%s' ], [ '%d' ] );
                }
            }
        }

        // Send notification.
        $this->maybe_notify_new_ticket( $post_id );

        Logger::log( sprintf( 'Ticket #%d created from email by %s', $number, $parsed['sender_email'] ) );

        return $post_id;
    }

    /**
     * Add a reply to an existing ticket.
     *
     * @param int                    $ticket_id Ticket post ID.
     * @param array<string, mixed>   $parsed    Parsed email data.
     * @return bool True on success.
     */
    public function add_reply_to_ticket( int $ticket_id, array $parsed ): bool {
        $conversation = new ConversationManager();

        // Deduplication: skip if this message_id already exists.
        $message_id = $parsed['message_id'] ?? '';
        if ( ! empty( $message_id ) && $conversation->message_id_exists( $message_id ) ) {
            Logger::log( sprintf( 'Skip duplicate reply: message_id already exists in ticket #%d', $ticket_id ) );
            return false;
        }

        // Update last updated timestamp.
        update_post_meta( $ticket_id, '_fm_last_updated', current_time( 'mysql' ) );

        // If status is completed or cancelled, reopen.
        $current_status = get_post_meta( $ticket_id, '_fm_status', true );
        if ( in_array( $current_status, [ 'completed', 'cancelled' ], true ) ) {
            update_post_meta( $ticket_id, '_fm_status', 'open' );
        }

        // Add conversation entry.
        $result = $conversation->add_entry( $ticket_id, 'client', $parsed['body'] ?? '', [
            'from_name'  => $parsed['sender_name'] ?? '',
            'from_email' => $parsed['sender_email'] ?? '',
            'subject'    => $parsed['original_subject'] ?? $parsed['subject'] ?? '',
            'date'       => $parsed['date'] ?? current_time( 'mysql' ),
            'message_id' => $parsed['message_id'] ?? '',
            'in_reply_to' => $parsed['in_reply_to'] ?? '',
            'references'  => $parsed['references'] ?? '',
            'body_html'  => $parsed['body_html'] ?? '',
            'imap_uid'   => $parsed['msg_number'] ?? 0,
        ] );

        // Save new attachments and store IDs in conversation entry.
        if ( ! empty( $parsed['attachments'] ) ) {
            $attachment_manager = new AttachmentManager();
            $saved_ids = $attachment_manager->save_attachments_from_email( $ticket_id, $parsed );
            if ( ! empty( $saved_ids ) ) {
                $last_entry = $conversation->get_last_entry( $ticket_id );
                if ( $last_entry ) {
                    global $wpdb;
                    $table = \Fanaloka\Maintenance\Database::table_name();
                    $wpdb->update( $table, [ 'attachments' => implode( ',', $saved_ids ) ], [ 'id' => $last_entry['id'] ], [ '%s' ], [ '%d' ] );
                }
            }
        }

        Logger::log( sprintf( 'Reply added to ticket #%d', $ticket_id ) );

        return false !== $result;
    }

    /**
     * Find a ticket by its ticket number.
     *
     * @param int $number Ticket number.
     * @return int|false Ticket post ID or false if not found.
     */
    public function find_ticket_by_number( int $number ) {
        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_fm_ticket_number',
                    'value' => $number,
                    'type'  => 'NUMERIC',
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
     * Find recent ticket with same sender and subject (within 5 minutes).
     *
     * @param string $sender_email Sender email.
     * @param string $subject      Email subject.
     * @return int|false Ticket post ID or false if not found.
     */
    private function find_recent_ticket( string $sender_email, string $subject ) {
        $five_min_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-5 minutes' ) );

        $args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'date_query'     => [
                [
                    'after' => $five_min_ago,
                ],
            ],
            'meta_query' => [
                [
                    'key'   => '_fm_client_email',
                    'value' => $sender_email,
                ],
                [
                    'key'   => '_fm_subject',
                    'value' => $subject,
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
     * Auto-detect priority from email content.
     *
     * @param array<string, mixed> $parsed Parsed email data.
     * @return string Detected priority.
     */
    private function detect_priority( array $parsed ): string {
        $text = strtolower(
            ( $parsed['subject'] ?? '' ) . ' ' . ( $parsed['body'] ?? '' )
        );

        $critical_keywords = [
            'urgent', 'critical', 'emergency', 'down', 'outage', 'hacked',
            'security breach', 'data loss', 'server down', 'site down',
            'website down', 'fatal error', 'unreachable', 'inaccessible',
            'severely broken', 'catastrophic',
        ];

        foreach ( $critical_keywords as $keyword ) {
            if ( false !== strpos( $text, $keyword ) ) {
                return 'critical';
            }
        }

        $high_keywords = [
            'asap', 'immediately', 'as soon as possible', 'pressing', 'important',
            'broken', 'error', 'bug', 'crash', 'fail', 'failed',
            'not working', 'malfunctioning', 'problem', 'issue',
            'disrupted', 'disturbed', 'fix', 'repair',
        ];

        foreach ( $high_keywords as $keyword ) {
            if ( false !== strpos( $text, $keyword ) ) {
                return 'high';
            }
        }

        $low_keywords = [
            'question', 'inquiry', 'info', 'information', 'when',
            'how', 'can you', 'could you', 'help', 'please',
            'request', 'update', 'change', 'modification',
            'slight', 'small', 'minor', 'cosmetic',
        ];

        foreach ( $low_keywords as $keyword ) {
            if ( false !== strpos( $text, $keyword ) ) {
                return 'low';
            }
        }

        return get_option( 'fm_default_priority', 'medium' );
    }

    /**
     * Update ticket status.
     *
     * @param int    $ticket_id   Ticket post ID.
     * @param string $new_status  New status value.
     * @return bool True on success.
     */
    public function update_status( int $ticket_id, string $new_status ): bool {
        if ( ! isset( self::STATUSES[ $new_status ] ) ) {
            return false;
        }

        $old_status = get_post_meta( $ticket_id, '_fm_status', true );

        update_post_meta( $ticket_id, '_fm_status', $new_status );
        update_post_meta( $ticket_id, '_fm_last_updated', current_time( 'mysql' ) );

        if ( 'completed' === $new_status ) {
            update_post_meta( $ticket_id, '_fm_completion_date', current_time( 'mysql' ) );
        }

        $conversation = new ConversationManager();
        $conversation->add_entry( $ticket_id, 'system', sprintf(
            'Status changed from %s to %s',
            self::STATUSES[ $old_status ] ?? $old_status,
            self::STATUSES[ $new_status ]
        ) );

        Logger::log( sprintf( 'Ticket #%d status: %s → %s', $ticket_id, $old_status, $new_status ) );

        $notification = new NotificationManager();
        $notification->notify_status_change( $ticket_id, $old_status, $new_status );

        if ( 'completed' === $new_status ) {
            $notification->notify_ticket_completed( $ticket_id );
        }

        return true;
    }

    /**
     * Assign developer to ticket.
     *
     * @param int $ticket_id     Ticket post ID.
     * @param int $developer_id  User ID of developer.
     * @return bool True on success.
     */
    public function assign_developer( int $ticket_id, int $developer_id ): bool {
        update_post_meta( $ticket_id, '_fm_assigned_dev', $developer_id );
        update_post_meta( $ticket_id, '_fm_last_updated', current_time( 'mysql' ) );

        $conversation = new ConversationManager();
        $user         = get_userdata( $developer_id );
        $name         = $user ? $user->display_name : __( 'Unknown', 'fanaloka-maintenance' );

        $conversation->add_entry( $ticket_id, 'system', sprintf(
            'Assigned to %s',
            $name
        ) );

        Logger::log( sprintf( 'Ticket #%d assigned to %s', $ticket_id, $name ) );

        $notification = new NotificationManager();
        $notification->notify_developer_assigned( $ticket_id, $developer_id );

        return true;
    }

    /**
     * Update ticket priority.
     *
     * @param int    $ticket_id     Ticket post ID.
     * @param string $new_priority  New priority.
     * @return bool True on success.
     */
    public function update_priority( int $ticket_id, string $new_priority ): bool {
        if ( ! isset( self::PRIORITIES[ $new_priority ] ) ) {
            return false;
        }

        update_post_meta( $ticket_id, '_fm_priority', $new_priority );
        update_post_meta( $ticket_id, '_fm_last_updated', current_time( 'mysql' ) );

        return true;
    }

    /**
     * Get ticket meta data.
     *
     * @param int $ticket_id Ticket post ID.
     * @return array<string, mixed> Ticket meta data.
     */
    public function get_ticket_meta( int $ticket_id ): array {
        $ticket = get_post( $ticket_id );

        if ( ! $ticket || 'maintenance_request' !== $ticket->post_type ) {
            return [];
        }

        $prefix       = get_post_meta( $ticket_id, '_fm_ticket_prefix', true ) ?: 'REQ';
        $number       = get_post_meta( $ticket_id, '_fm_ticket_number', true );
        $assigned_dev = absint( get_post_meta( $ticket_id, '_fm_assigned_dev', true ) );
        $dev_user     = $assigned_dev ? get_userdata( $assigned_dev ) : false;

        return [
            'id'               => $ticket_id,
            'number'           => $number,
            'full_number'      => $prefix . '-' . $number,
            'title'            => $ticket->post_title,
            'description'      => $ticket->post_content,
            'client_name'      => get_post_meta( $ticket_id, '_fm_client_name', true ),
            'client_email'     => get_post_meta( $ticket_id, '_fm_client_email', true ),
            'subject'          => get_post_meta( $ticket_id, '_fm_subject', true ),
            'status'           => get_post_meta( $ticket_id, '_fm_status', true ),
            'status_label'     => self::STATUSES[ get_post_meta( $ticket_id, '_fm_status', true ) ] ?? '',
            'priority'         => get_post_meta( $ticket_id, '_fm_priority', true ),
            'priority_label'   => self::PRIORITIES[ get_post_meta( $ticket_id, '_fm_priority', true ) ] ?? '',
            'assigned_dev'     => $assigned_dev,
            'assigned_dev_name' => $dev_user ? $dev_user->display_name : '-',
            'date_created'     => get_post_meta( $ticket_id, '_fm_date_created', true ),
            'last_updated'     => get_post_meta( $ticket_id, '_fm_last_updated', true ),
            'sla'              => get_post_meta( $ticket_id, '_fm_sla', true ),
            'completion_date'  => get_post_meta( $ticket_id, '_fm_completion_date', true ),
            'source'           => get_post_meta( $ticket_id, '_fm_source', true ),
            'message_id'       => get_post_meta( $ticket_id, '_fm_message_id', true ),
            'post_date'        => $ticket->post_date,
        ];
    }

    /**
     * Save multiple meta fields at once.
     *
     * @param int                    $ticket_id Ticket post ID.
     * @param array<string, string>  $meta      Meta key => value pairs.
     * @return void
     */
    private function save_ticket_meta( int $ticket_id, array $meta ): void {
        foreach ( $meta as $key => $value ) {
            update_post_meta( $ticket_id, $key, $value );
        }
    }

    /**
     * Get next ticket number.
     *
     * @return int Next ticket number.
     */
    private function get_next_ticket_number(): int {
        $last = get_option( 'fm_last_ticket_number', 0 );
        $next = $last + 1;
        update_option( 'fm_last_ticket_number', $next );
        return $next;
    }

    /**
     * Send notification for new ticket.
     *
     * @param int $ticket_id Ticket post ID.
     * @return void
     */
    private function maybe_notify_new_ticket( int $ticket_id ): void {
        $enabled = get_option( 'fm_notif_new_ticket', 'yes' );

        if ( 'yes' !== $enabled ) {
            return;
        }

        $admin_email = get_option( 'fm_admin_email', get_option( 'admin_email' ) );
        $prefix      = get_post_meta( $ticket_id, '_fm_ticket_prefix', true ) ?: 'REQ';
        $number      = get_post_meta( $ticket_id, '_fm_ticket_number', true );
        $subject     = get_post_meta( $ticket_id, '_fm_subject', true );
        $client      = get_post_meta( $ticket_id, '_fm_client_email', true );

        $email_subject = sprintf(
            '[%s-%d] New Maintenance Request: %s',
            $prefix,
            $number,
            $subject
        );

        $email_body = sprintf(
            "A new maintenance request has been created.\n\nTicket: %s-%d\nClient: %s\nSubject: %s\n\nView: %s",
            $prefix,
            $number,
            $client,
            $subject,
            admin_url( 'admin.php?page=fm-requests&action=view&id=' . $ticket_id )
        );

        $notification = new NotificationManager();
        $notification->send( $admin_email, $email_subject, $email_body );
    }

    /**
     * Get all tickets with optional filters.
     *
     * @param array<string, mixed> $args Query arguments.
     * @return array{tickets: array<int, array<string, mixed>>, total: int, pages: int}
     */
    public function get_tickets( array $args = [] ): array {
        $per_page = $args['per_page'] ?? 20;
        $paged    = $args['paged'] ?? 1;
        $status   = $args['status'] ?? '';
        $priority = $args['priority'] ?? '';
        $dev      = $args['developer'] ?? 0;
        $search   = $args['search'] ?? '';
        $orderby  = $args['orderby'] ?? 'date';
        $order    = $args['order'] ?? 'DESC';

        $meta_query = [];

        if ( $status ) {
            $meta_query[] = [
                'key'   => '_fm_status',
                'value' => $status,
            ];
        }

        if ( $priority ) {
            $meta_query[] = [
                'key'   => '_fm_priority',
                'value' => $priority,
            ];
        }

        if ( $dev ) {
            $meta_query[] = [
                'key'     => '_fm_assigned_dev',
                'value'   => $dev,
                'type'    => 'NUMERIC',
            ];
        }

        $query_args = [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => $orderby,
            'order'          => $order,
        ];

        if ( ! empty( $meta_query ) ) {
            $query_args['meta_query'] = $meta_query;
        }

        if ( $search ) {
            $query_args['s'] = $search;
        }

        $query    = new \WP_Query( $query_args );
        $tickets  = [];

        foreach ( $query->posts as $post ) {
            $tickets[] = $this->get_ticket_meta( $post->ID );
        }

        return [
            'tickets' => $tickets,
            'total'   => $query->found_posts,
            'pages'   => $query->max_num_pages,
        ];
    }

    /**
     * Delete a ticket.
     *
     * @param int $ticket_id Ticket post ID.
     * @return bool True on success.
     */
    public function delete_ticket( int $ticket_id ): bool {
        $result = wp_delete_post( $ticket_id, true );

        if ( false !== $result ) {
            Logger::log( sprintf( 'Ticket #%d deleted', $ticket_id ) );
            return true;
        }

        return false;
    }
}
