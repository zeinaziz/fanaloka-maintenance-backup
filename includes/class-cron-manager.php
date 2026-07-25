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

            Logger::log( sprintf( 'Found %d unseen emails', $results['total'] ) );

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

                    // Log email processing.
                    Logger::log( sprintf(
                        'Processing: subject="%s" in_reply_to="%s" sender="%s" message_id="%s"',
                        $parsed['subject'] ?? '',
                        $parsed['in_reply_to'] ?? 'empty',
                        $parsed['sender_email'] ?? 'unknown',
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
        } catch ( \Exception $e ) {
            Logger::log( 'Sync error: ' . $e->getMessage(), Logger::LEVEL_ERROR );
        } finally {
            $reader->disconnect();
            delete_transient( 'fm_sync_lock' );
        }

        // Save sync stats.
        update_option( 'fm_last_sync', [
            'time'        => current_time( 'mysql' ),
            'total'       => $results['total'],
            'created'     => $results['created'],
            'replies'     => $results['replies'],
            'errors'      => $results['errors'],
            'incremental' => true,
        ] );

        Logger::log(
            sprintf(
                'Sync completed: %d total, %d created, %d replies, %d errors',
                $results['total'],
                $results['created'],
                $results['replies'],
                $results['errors']
            )
        );
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
                /* translators: 1: total emails, 2: created tickets, 3: replies */
                __( 'Sync complete: %1$d emails found, %2$d tickets created, %3$d replies added.', 'fanaloka-maintenance' ),
                $results['total'],
                $results['created'],
                $results['replies']
            ),
            'results' => $results,
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
            return [ 'total' => 0, 'created' => 0, 'replies' => 0, 'errors' => 0, 'skipped' => true ];
        }
        set_transient( $lock_key, true, 120 );

        Logger::log( 'Manual sync triggered programmatically' );

        $reader  = new IMAPReader();
        $parser  = new EmailParser();
        $ticket  = new TicketManager();
        $results = [
            'total'   => 0,
            'created' => 0,
            'replies' => 0,
            'errors'  => 0,
        ];

        $connect = $reader->connect();

        if ( ! $connect['success'] ) {
            Logger::log( 'Manual sync failed: ' . $connect['message'], Logger::LEVEL_ERROR );
            return $results;
        }

        try {
            $emails = $reader->get_unseen_emails();
            $results['total'] = count( $emails );

            foreach ( $emails as $email_data ) {
                try {
                    $parsed = $parser->parse_email( $email_data );

                    if ( false === $parsed ) {
                        $results['errors']++;
                        continue;
                    }

                    $existing_ticket = $ticket->find_ticket_for_email( $parsed );

                    if ( false !== $existing_ticket ) {
                        $ticket->add_reply_to_ticket( $existing_ticket, $parsed );
                        $results['replies']++;
                    } else {
                        $ticket_id = $ticket->create_ticket_from_email( $parsed );
                        if ( $ticket_id ) {
                            $results['created']++;
                        } else {
                            $results['errors']++;
                        }
                    }

                    $reader->mark_as_seen( $email_data['msg_number'] );

                } catch ( \Exception $e ) {
                    $results['errors']++;
                    Logger::log( 'Email processing error: ' . $e->getMessage(), Logger::LEVEL_ERROR );
                }
            }
        } catch ( \Exception $e ) {
            Logger::log( 'Manual sync error: ' . $e->getMessage(), Logger::LEVEL_ERROR );
        } finally {
            $reader->disconnect();
        }

        update_option( 'fm_last_sync', [
            'time'        => current_time( 'mysql' ),
            'total'       => $results['total'],
            'created'     => $results['created'],
            'replies'     => $results['replies'],
            'errors'      => $results['errors'],
            'incremental' => true,
        ] );

        Logger::log(
            sprintf(
                'Manual sync completed: %d total, %d created, %d replies, %d errors',
                $results['total'],
                $results['created'],
                $results['replies'],
                $results['errors']
            )
        );

        delete_transient( 'fm_sync_lock' );

        return $results;
    }
}
