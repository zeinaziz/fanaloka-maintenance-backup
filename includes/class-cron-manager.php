<?php
/**
 * Cron Manager - Handle scheduled email sync.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Cron;

use Fanaloka\Maintenance\IMAP\IMAPReader;
use Fanaloka\Maintenance\Email\EmailParser;
use Fanaloka\Maintenance\Ticket\TicketManager;
use Fanaloka\Maintenance\Ticket\ConversationManager;
use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CronManager Class.
 */
class CronManager {

    /**
     * Cron hook name.
     *
     * @var string
     */
    public const CRON_HOOK = 'fm_sync_emails';

    /**
     * Single instance.
     *
     * @var CronManager|null
     */
    private static ?CronManager $instance = null;

    /**
     * Get single instance.
     *
     * @return CronManager
     */
    public static function instance(): CronManager {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( self::CRON_HOOK, [ $this, 'run_sync' ] );
        add_action( 'init', [ $this, 'schedule_cron' ] );
        add_action( 'wp_ajax_fm_sync_now', [ $this, 'ajax_sync_now' ] );
        add_action( 'admin_init', [ $this, 'add_sync_button_to_settings' ] );
    }

    /**
     * Schedule the cron event if not already scheduled.
     *
     * @return void
     */
    public function schedule_cron(): void {
        $auto_sync = get_option( 'fm_auto_sync', 'yes' );

        if ( 'yes' !== $auto_sync ) {
            // Ensure cron is unscheduled if auto sync is disabled.
            $this->unschedule_cron();
            return;
        }

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            $interval = $this->get_interval_minutes();
            wp_schedule_event( time(), 'fm_' . $interval . '_min', self::CRON_HOOK );

            Logger::log( sprintf( 'Cron scheduled every %d minutes', $interval ) );
        }
    }

    /**
     * Unschedule the cron event.
     *
     * @return void
     */
    public function unschedule_cron(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
            Logger::log( 'Cron unscheduled' );
        }
    }

    /**
     * Register custom cron intervals.
     *
     * @param array<int, array{display: string, interval: int}> $schedules Existing intervals.
     * @return array<int, array{display: string, interval: int}>
     */
    public function add_cron_interval( array $schedules ): array {
        $minutes = $this->get_interval_minutes();

        $schedules[ 'fm_' . $minutes . '_min' ] = [
            'display'  => sprintf(
                /* translators: %d: number of minutes */
                __( 'Every %d Minutes (Fanaloka)', 'fanaloka-maintenance' ),
                $minutes
            ),
            'interval' => $minutes * 60,
        ];

        return $schedules;
    }

    /**
     * Get sync interval in minutes.
     *
     * @return int
     */
    private function get_interval_minutes(): int {
        $interval = absint( get_option( 'fm_sync_interval', 5 ) );
        return max( 1, $interval );
    }

    /**
     * Run the email sync process.
     *
     * @return void
     */
    public function run_sync(): void {
        // Prevent concurrent syncs.
        $lock_key = 'fm_sync_lock';
        if ( get_transient( $lock_key ) ) {
            Logger::log( 'Cron sync skipped: another sync is already running' );
            return;
        }
        set_transient( $lock_key, true, 120 );

        Logger::log( 'Sync started via cron' );

        $reader  = new IMAPReader();
        $parser  = new EmailParser();
        $ticket  = new TicketManager();
        $results = [
            'total'   => 0,
            'created' => 0,
            'replies' => 0,
            'ignored' => 0,
            'errors'  => 0,
        ];

        // Connect to IMAP.
        $connect = $reader->connect();

        if ( ! $connect['success'] ) {
            Logger::log( 'Sync failed: ' . $connect['message'], Logger::LEVEL_ERROR );
            return;
        }

        try {
            // Check last sync date for incremental sync.
            $last_sync    = get_option( 'fm_last_sync', [] );
            $last_time    = $last_sync['time'] ?? '';
            $is_incremental = ! empty( $last_time ) && isset( $last_sync['incremental'] );

            if ( $is_incremental ) {
                // Only get emails since last sync.
                $since_date = gmdate( 'd-M-Y', strtotime( $last_time ) );
                $emails     = $reader->get_emails_since( $since_date );
                Logger::log( sprintf( 'Incremental sync: emails since %s', $since_date ) );
            } else {
                // First run: get all unseen emails.
                $emails = $reader->get_unseen_emails();
                Logger::log( 'Full sync: all unseen emails' );
            }

            $results['total'] = count( $emails );

            // Local dedup: skip emails already processed (Gmail UNSEEN bug workaround).
            $processed_ids = get_option( 'fm_processed_message_ids', [] );
            $new_emails = [];
            foreach ( $emails as $email_data ) {
                $mid = $email_data['message_id'] ?? '';
                if ( ! empty( $mid ) && in_array( $mid, $processed_ids, true ) ) {
                    Logger::log( sprintf( 'Skip already-processed: %s', $mid ), Logger::LEVEL_DEBUG );
                    continue;
                }
                $new_emails[] = $email_data;
            }
            $emails = $new_emails;
            $results['total'] = count( $emails );

            Logger::log( sprintf( 'Found %d new emails', $results['total'] ) );

            foreach ( $emails as $email_data ) {
                try {
                    $parsed = $parser->parse_email( $email_data );

                    if ( false === $parsed ) {
                        Logger::log(
                            sprintf( 'Failed to parse email from %s', $email_data['headers']['from']['email'] ?? 'unknown' ),
                            Logger::LEVEL_WARNING
                        );
                        $results['errors']++;
                        continue;
                    }

                    // Check if sender should be ignored.
                    $sender_email = $parsed['sender_email'] ?? '';
                    if ( $parser->should_ignore( $sender_email ) ) {
                        Logger::log(
                            sprintf( 'Ignored email from %s (matches ignore pattern)', $sender_email ),
                            Logger::LEVEL_DEBUG
                        );
                        $results['ignored']++;
                        continue;
                    }

                    // Log email processing.
                    $cc = $parsed['cc'] ?? '';
                    Logger::log( sprintf(
                        'Processing: subject="%s" in_reply_to="%s" sender="%s"%s message_id="%s"',
                        $parsed['subject'] ?? '',
                        $parsed['in_reply_to'] ?? 'empty',
                        $parsed['sender_email'] ?? 'unknown',
                        ! empty( $cc ) ? ' cc="' . $cc . '"' : '',
                        $parsed['message_id'] ?? 'empty'
                    ) );

                    // Find existing ticket using 4-priority detection.
                    $existing_ticket = $ticket->find_ticket_for_email( $parsed );

                    if ( false !== $existing_ticket ) {
                        // Add as conversation reply.
                        $ticket->add_reply_to_ticket( $existing_ticket, $parsed );
                        $results['replies']++;

                        Logger::log(
                            sprintf(
                                'Reply added to ticket #%d from %s',
                                $existing_ticket,
                                $parsed['sender_email'] ?? 'unknown'
                            )
                        );
                    } else {
                        // Create new ticket.
                        $ticket_id = $ticket->create_ticket_from_email( $parsed );

                        if ( $ticket_id ) {
                            $results['created']++;

                            Logger::log(
                                sprintf(
                                    'Ticket #%d created from email from %s',
                                    $ticket_id,
                                    $parsed['sender_email'] ?? 'unknown'
                                )
                            );
                        } else {
                            $results['errors']++;
                        }
                    }

                    // Track processed message_id for local dedup (Gmail UNSEEN workaround).
                    $mid = $email_data['message_id'] ?? '';
                    if ( ! empty( $mid ) ) {
                        $processed_ids[] = $mid;
                        $processed_ids = array_slice( array_unique( $processed_ids ), -500 );
                        update_option( 'fm_processed_message_ids', $processed_ids, false );
                    }

                    // Mark email as seen.
                    $reader->mark_as_seen( $email_data['msg_number'] );

                } catch ( \Exception $e ) {
                    $results['errors']++;

                    Logger::log(
                        sprintf( 'Error processing email: %s', $e->getMessage() ),
                        Logger::LEVEL_ERROR
                    );
                }
            }

            // --- Sent folder sync ---
            $results['sent_synced'] = 0;
            $sent_folder = '[Gmail]/Sent Mail';
            if ( $reader->open_folder( $sent_folder ) ) {
                Logger::log( sprintf( 'Syncing Sent folder: %s', $sent_folder ) );
                $since_date_sent = gmdate( 'd-M-Y', strtotime( '-30 days' ) );
                $sent_emails     = $reader->get_all_emails_since( $since_date_sent );
                Logger::log( sprintf( 'Found %d emails in Sent folder', count( $sent_emails ) ) );

                foreach ( $sent_emails as $sent_email ) {
                    try {
                        $parsed = $parser->parse_email( $sent_email );
                        if ( false === $parsed ) {
                            continue;
                        }

                        // Skip if from admin (zein@fanaloka.co) — it's our own sent email.
                        $from_email = $parsed['sender_email'] ?? '';
                        $admin_email = get_option( 'fm_imap_username', '' );
                        if ( strtolower( $from_email ) !== strtolower( $admin_email ) ) {
                            continue;
                        }

                        // Find matching ticket by subject.
                        $subject            = $parsed['subject'] ?? '';
                        $normalized_subject = ( new ConversationManager() )->normalize_subject( $subject );

                        if ( empty( $normalized_subject ) ) {
                            continue;
                        }

                        // Find ticket by subject.
                        $ticket_id = $this->find_ticket_by_sent_subject( $normalized_subject );
                        if ( false === $ticket_id ) {
                            continue;
                        }

                        // Check deduplication by message_id.
                        $message_id = $parsed['message_id'] ?? '';
                        $conversation = new ConversationManager();
                        if ( ! empty( $message_id ) && $conversation->message_id_exists( $message_id ) ) {
                            Logger::log( sprintf( 'Sent email skip duplicate: message_id %s already exists', $message_id ), Logger::LEVEL_DEBUG );
                            continue;
                        }

                        // Also check by sender+time (website sends generate different Message-IDs).
                        if ( $conversation->has_recent_developer_reply( $ticket_id, $from_email ) ) {
                            Logger::log( sprintf( 'Sent email skip duplicate: similar developer reply already exists in ticket #%d', $ticket_id ), Logger::LEVEL_DEBUG );
                            continue;
                        }

                        // Add as developer reply.
                        $conversation->add_entry( $ticket_id, 'developer', $parsed['body'] ?? '', [
                            'message_id'  => $message_id,
                            'from_email'  => $from_email,
                            'from_name'   => $parsed['sender_name'] ?? '',
                            'subject'     => $subject,
                            'date'        => $parsed['date'] ?? current_time( 'mysql' ),
                            'body_html'   => $this->strip_email_signature( $parsed['body_html'] ?? '' ),
                            'imap_uid'    => $sent_email['msg_number'] ?? 0,
                        ] );

                        update_post_meta( $ticket_id, '_fm_last_updated', time() );
                        $results['sent_synced']++;

                        Logger::log( sprintf( 'Sent email synced to ticket #%d from %s', $ticket_id, $from_email ) );

                    } catch ( \Exception $e ) {
                        Logger::log( 'Sent email processing error: ' . $e->getMessage(), Logger::LEVEL_ERROR );
                    }
                }
            }

        } catch ( \Exception $e ) {
            Logger::log( 'Sync error: ' . $e->getMessage(), Logger::LEVEL_ERROR );
        } finally {
            $reader->disconnect();
            delete_transient( 'fm_sync_lock' );
        }

        // Save sync stats.
        update_option( 'fm_last_sync', [
            'time'         => current_time( 'mysql' ),
            'total'        => $results['total'],
            'created'      => $results['created'],
            'replies'      => $results['replies'],
            'ignored'      => $results['ignored'],
            'errors'       => $results['errors'],
            'sent_synced'  => $results['sent_synced'] ?? 0,
            'incremental'  => true,
        ] );

        Logger::log(
            sprintf(
                'Sync completed: %d total, %d created, %d replies, %d ignored, %d sent synced, %d errors',
                $results['total'],
                $results['created'],
                $results['replies'],
                $results['ignored'],
                $results['sent_synced'] ?? 0,
                $results['errors']
            )
        );
    }

    /**
     * Find a ticket by normalized subject (for Sent folder matching).
     *
     * @param string $normalized_subject Normalized subject.
     * @return int|false Ticket post ID or false if not found.
     */
    private function strip_email_signature( string $html ): string {
        $signature = get_option( 'fm_email_signature', '' );
        if ( empty( $signature ) ) {
            return $html;
        }
        // Remove HTML signature and preceding <br><br>.
        $html = preg_replace( '/<br\s*\/?\s*>\s*<br\s*\/?\s*>\s*' . preg_quote( $signature, '/' ) . '$/i', '', $html );
        $html = str_replace( $signature, '', $html );
        // Also remove plain-text version.
        $plain = wp_strip_all_tags( $signature );
        if ( ! empty( $plain ) ) {
            $plain_lines = array_filter( array_map( 'trim', explode( "\n", $plain ) ) );
            foreach ( $plain_lines as $line ) {
                $html = str_replace( $line, '', $html );
            }
        }
        return trim( $html );
    }

    private function find_ticket_by_sent_subject( string $normalized_subject ) {
        $conversation = new ConversationManager();
        return $conversation->find_ticket_by_subject( $normalized_subject );
    }

    /**
     * AJAX handler for manual sync.
     *
     * @return void
     */
    public function ajax_sync_now(): void {
        check_ajax_referer( 'fm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [
                'message' => __( 'Permission denied.', 'fanaloka-maintenance' ),
            ] );
        }

        Logger::log( 'Manual sync triggered via AJAX' );

        // Run sync directly for immediate results.
        $results = $this->manual_sync();

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: 1: total emails, 2: created tickets, 3: replies, 4: ignored, 5: sent synced */
                __( 'Sync complete: %1$d emails found, %2$d tickets created, %3$d replies added, %4$d ignored, %5$d sent synced.', 'fanaloka-maintenance' ),
                $results['total'],
                $results['created'],
                $results['replies'],
                $results['ignored'],
                $results['sent_synced'] ?? 0
            ),
            'results' => $results,
            'steps'   => $results['steps'] ?? [],
        ] );
    }

    /**
     * Add sync button next to save button in settings.
     *
     * @return void
     */
    public function add_sync_button_to_settings(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $screen = get_current_screen();

        if ( ! $screen || 'settings_page_fm-settings' !== $screen->id ) {
            return;
        }

        add_action( 'admin_footer', [ $this, 'render_sync_button_script' ] );
    }

    /**
     * Render sync button script in footer.
     *
     * @return void
     */
    public function render_sync_button_script(): void {
        ?>
        <script>
        (function($) {
            if ($('#fm-test-connection').length) {
                $('#fm-test-connection').after(
                    ' <button type="button" class="button fm-btn-sync" id="fm-sync-now"><?php echo esc_js( __( 'Sync Now', 'fanaloka-maintenance' ) ); ?></button>'
                );
            }
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Get last sync info.
     *
     * @return array<string, mixed>
     */
    public function get_last_sync(): array {
        $sync = get_option( 'fm_last_sync', [] );

        if ( empty( $sync ) ) {
            return [
                'time'    => '-',
                'total'   => 0,
                'created' => 0,
                'replies' => 0,
                'errors'  => 0,
            ];
        }

        return $sync;
    }

    /**
     * Check if cron is currently scheduled.
     *
     * @return bool
     */
    public function is_scheduled(): bool {
        return (bool) wp_next_scheduled( self::CRON_HOOK );
    }

    /**
     * Manually trigger sync.
     *
     * @return array{total: int, created: int, replies: int, errors: int}
     */
    public function manual_sync(): array {
        // Prevent concurrent syncs.
        $lock_key = 'fm_sync_lock';
        if ( get_transient( $lock_key ) ) {
            Logger::log( 'Sync skipped: another sync is already running' );
            return [ 'total' => 0, 'created' => 0, 'replies' => 0, 'ignored' => 0, 'errors' => 0, 'skipped' => true, 'steps' => [] ];
        }
        set_transient( $lock_key, true, 120 );

        $steps = [];
        $steps[] = [ 'step' => 'lock_acquired', 'time' => gmdate( 'H:i:s' ), 'msg' => 'Sync lock acquired' ];
        Logger::log( 'Manual sync triggered programmatically' );

        $reader  = new IMAPReader();
        $parser  = new EmailParser();
        $ticket  = new TicketManager();
        $results = [
            'total'   => 0,
            'created' => 0,
            'replies' => 0,
            'ignored' => 0,
            'errors'  => 0,
        ];

        // Step: Connect to IMAP.
        $steps[] = [ 'step' => 'imap_connect', 'time' => gmdate( 'H:i:s' ), 'msg' => 'Connecting to IMAP server...' ];
        Logger::log( 'Step: Connecting to IMAP' );
        $connect_start = microtime( true );
        $connect = $reader->connect();
        $connect_time = round( microtime( true ) - $connect_start, 2 );

        if ( ! $connect['success'] ) {
            $steps[] = [ 'step' => 'imap_failed', 'time' => gmdate( 'H:i:s' ), 'msg' => 'IMAP connect failed: ' . $connect['message'] ];
            Logger::log( 'Manual sync failed: ' . $connect['message'], Logger::LEVEL_ERROR );
            $results['steps'] = $steps;
            return $results;
        }
        $steps[] = [ 'step' => 'imap_connected', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( 'IMAP connected in %.1fs', $connect_time ) ];
        Logger::log( sprintf( 'IMAP connected in %.1fs', $connect_time ) );

        try {
            // Step: Fetch unseen emails.
            $steps[] = [ 'step' => 'fetch_inbox', 'time' => gmdate( 'H:i:s' ), 'msg' => 'Fetching unseen emails from INBOX...' ];
            Logger::log( 'Step: Fetching unseen emails from INBOX' );
            $fetch_start = microtime( true );
            $emails = $reader->get_unseen_emails();
            $fetch_time = round( microtime( true ) - $fetch_start, 2 );

            // Local dedup: skip emails already processed (Gmail UNSEEN bug workaround).
            $processed_ids = get_option( 'fm_processed_message_ids', [] );
            $new_emails = [];
            foreach ( $emails as $email_data ) {
                $mid = $email_data['message_id'] ?? '';
                if ( ! empty( $mid ) && in_array( $mid, $processed_ids, true ) ) {
                    Logger::log( sprintf( 'Skip already-processed: %s', $mid ), Logger::LEVEL_DEBUG );
                    continue;
                }
                $new_emails[] = $email_data;
            }
            $emails = $new_emails;

            $results['total'] = count( $emails );
            $steps[] = [ 'step' => 'inbox_fetched', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( 'Found %d new emails in INBOX (%.1fs)', count( $emails ), $fetch_time ) ];
            Logger::log( sprintf( 'Found %d new emails in INBOX (%.1fs)', count( $emails ), $fetch_time ) );

            // Step: Process each email.
            $email_index = 0;
            foreach ( $emails as $email_data ) {
                $email_index++;
                $subject = $email_data['subject'] ?? '(no subject)';
                $from = $email_data['from'] ?? '';
                $steps[] = [ 'step' => 'process_email', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( '[%d/%d] Processing: "%s" from %s', $email_index, count( $emails ), mb_substr( $subject, 0, 50 ), $from ) ];
                Logger::log( sprintf( 'Processing email %d/%d: "%s" from %s', $email_index, count( $emails ), $subject, $from ) );

                try {
                    $parse_start = microtime( true );
                    $parsed = $parser->parse_email( $email_data );
                    $parse_time = round( microtime( true ) - $parse_start, 2 );

                    if ( false === $parsed ) {
                        $results['errors']++;
                        $steps[] = [ 'step' => 'parse_failed', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( '  Parse failed (%.1fs)', $parse_time ) ];
                        continue;
                    }

                    // Check if sender should be ignored.
                    $sender_email = $parsed['sender_email'] ?? '';
                    if ( $parser->should_ignore( $sender_email ) ) {
                        $steps[] = [ 'step' => 'ignored', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( '  Ignored: %s matches ignore pattern (%.1fs)', $sender_email, $parse_time ) ];
                        Logger::log( sprintf( 'Ignored email from %s (matches ignore pattern)', $sender_email ), Logger::LEVEL_DEBUG );
                        $results['ignored']++;
                        continue;
                    }

                    // Step: Find matching ticket.
                    $find_start = microtime( true );
                    $existing_ticket = $ticket->find_ticket_for_email( $parsed );
                    $find_time = round( microtime( true ) - $find_start, 2 );

                    if ( false !== $existing_ticket ) {
                        $ticket->add_reply_to_ticket( $existing_ticket, $parsed );
                        $results['replies']++;
                        $steps[] = [ 'step' => 'reply_added', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( '  Reply added to ticket #%d (%.1fs parse + %.1fs find)', $existing_ticket, $parse_time, $find_time ) ];
                        Logger::log( sprintf( 'Reply added to ticket #%d from %s', $existing_ticket, $sender_email ) );
                        \Fanaloka\Maintenance\Log\ActivityLog::log( 'email_synced', 'ticket', $existing_ticket, sprintf( 'Email reply from %s to ticket #%d', $sender_email, $existing_ticket ) );
                    } else {
                        $create_start = microtime( true );
                        $ticket_id = $ticket->create_ticket_from_email( $parsed );
                        $create_time = round( microtime( true ) - $create_start, 2 );
                        if ( $ticket_id ) {
                            $results['created']++;
                            $steps[] = [ 'step' => 'ticket_created', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( '  Ticket #%d created (%.1fs parse + %.1fs create)', $ticket_id, $parse_time, $create_time ) ];
                            Logger::log( sprintf( 'Ticket #%d created from %s', $ticket_id, $sender_email ) );
                            \Fanaloka\Maintenance\Log\ActivityLog::log( 'ticket_created', 'ticket', $ticket_id, sprintf( 'Ticket #%d created from email by %s', $ticket_id, $sender_email ) );
                        } else {
                            $results['errors']++;
                            $steps[] = [ 'step' => 'create_failed', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( '  Failed to create ticket (%.1fs)', $create_time ) ];
                        }
                    }

                    // Log CC if present.
                    $cc = $parsed['cc'] ?? '';
                    if ( ! empty( $cc ) ) {
                        Logger::log( sprintf( 'Email CC: %s for ticket #%s', $cc, $existing_ticket ?: $ticket_id ?? 'new' ) );
                    }

                    $reader->mark_as_seen( $email_data['msg_number'] );

                } catch ( \Exception $e ) {
                    $results['errors']++;
                    $steps[] = [ 'step' => 'error', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( '  Error: %s', $e->getMessage() ) ];
                    Logger::log( 'Email processing error: ' . $e->getMessage(), Logger::LEVEL_ERROR );
                }
            }

            // Step: Sent folder sync.
            $steps[] = [ 'step' => 'fetch_sent', 'time' => gmdate( 'H:i:s' ), 'msg' => 'Syncing Sent folder...' ];
            Logger::log( 'Step: Syncing Sent folder' );
            $results['sent_synced'] = 0;
            $sent_folder = '[Gmail]/Sent Mail';
            if ( $reader->open_folder( $sent_folder ) ) {
                Logger::log( sprintf( 'Syncing Sent folder: %s', $sent_folder ) );
                $since_date_sent = gmdate( 'd-M-Y', strtotime( '-30 days' ) );
                $sent_start = microtime( true );
                $sent_emails     = $reader->get_all_emails_since( $since_date_sent );
                $sent_time = round( microtime( true ) - $sent_start, 2 );
                $steps[] = [ 'step' => 'sent_fetched', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( 'Found %d emails in Sent folder (%.1fs)', count( $sent_emails ), $sent_time ) ];
                Logger::log( sprintf( 'Found %d emails in Sent folder (%.1fs)', count( $sent_emails ), $sent_time ) );

                $sent_index = 0;
                foreach ( $sent_emails as $sent_email ) {
                    $sent_index++;
                    try {
                        $parsed = $parser->parse_email( $sent_email );
                        if ( false === $parsed ) {
                            continue;
                        }

                        $from_email  = $parsed['sender_email'] ?? '';
                        $admin_email = get_option( 'fm_imap_username', '' );
                        if ( strtolower( $from_email ) !== strtolower( $admin_email ) ) {
                            continue;
                        }

                        $subject            = $parsed['subject'] ?? '';
                        $normalized_subject = ( new ConversationManager() )->normalize_subject( $subject );

                        if ( empty( $normalized_subject ) ) {
                            continue;
                        }

                        $ticket_id = $this->find_ticket_by_sent_subject( $normalized_subject );
                        if ( false === $ticket_id ) {
                            $steps[] = [ 'step' => 'sent_skip', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( '  Sent [%d/%d] No matching ticket for: "%s"', $sent_index, count( $sent_emails ), mb_substr( $subject, 0, 40 ) ) ];
                            continue;
                        }

                        $message_id   = $parsed['message_id'] ?? '';
                        $conversation = new ConversationManager();
                        if ( ! empty( $message_id ) && $conversation->message_id_exists( $message_id ) ) {
                            Logger::log( sprintf( 'Sent email skip duplicate: message_id %s already exists', $message_id ), Logger::LEVEL_DEBUG );
                            continue;
                        }

                        if ( $conversation->has_recent_developer_reply( $ticket_id, $from_email ) ) {
                            Logger::log( sprintf( 'Sent email skip duplicate: similar developer reply already exists in ticket #%d', $ticket_id ), Logger::LEVEL_DEBUG );
                            continue;
                        }

                        $conversation->add_entry( $ticket_id, 'developer', $parsed['body'] ?? '', [
                            'message_id'  => $message_id,
                            'from_email'  => $from_email,
                            'from_name'   => $parsed['sender_name'] ?? '',
                            'subject'     => $subject,
                            'date'        => $parsed['date'] ?? current_time( 'mysql' ),
                            'body_html'   => $this->strip_email_signature( $parsed['body_html'] ?? '' ),
                            'imap_uid'    => $sent_email['msg_number'] ?? 0,
                        ] );

                        update_post_meta( $ticket_id, '_fm_last_updated', time() );
                        $results['sent_synced']++;
                        $steps[] = [ 'step' => 'sent_synced', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( '  Sent [%d/%d] Synced to ticket #%d: "%s"', $sent_index, count( $sent_emails ), $ticket_id, mb_substr( $subject, 0, 40 ) ) ];
                        Logger::log( sprintf( 'Sent email synced to ticket #%d from %s', $ticket_id, $from_email ) );

                    } catch ( \Exception $e ) {
                        Logger::log( 'Sent email processing error: ' . $e->getMessage(), Logger::LEVEL_ERROR );
                    }
                }
            } else {
                $steps[] = [ 'step' => 'sent_folder_failed', 'time' => gmdate( 'H:i:s' ), 'msg' => 'Could not open Sent folder' ];
            }

        } catch ( \Exception $e ) {
            $steps[] = [ 'step' => 'fatal_error', 'time' => gmdate( 'H:i:s' ), 'msg' => 'Fatal: ' . $e->getMessage() ];
            Logger::log( 'Manual sync error: ' . $e->getMessage(), Logger::LEVEL_ERROR );
        } finally {
            $reader->disconnect();
            delete_transient( 'fm_sync_lock' );
            $steps[] = [ 'step' => 'disconnected', 'time' => gmdate( 'H:i:s' ), 'msg' => 'IMAP disconnected, lock released' ];
        }

        update_option( 'fm_last_sync', [
            'time'         => current_time( 'mysql' ),
            'total'        => $results['total'],
            'created'      => $results['created'],
            'replies'      => $results['replies'],
            'ignored'      => $results['ignored'],
            'errors'       => $results['errors'],
            'sent_synced'  => $results['sent_synced'] ?? 0,
            'incremental'  => true,
        ] );

        $steps[] = [ 'step' => 'completed', 'time' => gmdate( 'H:i:s' ), 'msg' => sprintf( 'Done! %d emails, %d created, %d replies, %d ignored, %d sent synced, %d errors', $results['total'], $results['created'], $results['replies'], $results['ignored'], $results['sent_synced'] ?? 0, $results['errors'] ) ];
        Logger::log(
            sprintf(
                'Manual sync completed: %d total, %d created, %d replies, %d ignored, %d sent synced, %d errors',
                $results['total'],
                $results['created'],
                $results['replies'],
                $results['ignored'],
                $results['sent_synced'] ?? 0,
                $results['errors']
            )
        );

        $results['steps'] = $steps;
        return $results;
    }
}
