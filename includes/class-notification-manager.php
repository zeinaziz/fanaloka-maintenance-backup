<?php
/**
 * Notification Manager - Send email notifications for ticket events.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Notification;

use Fanaloka\Maintenance\Ticket\TicketManager;
use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * NotificationManager Class.
 */
class NotificationManager {

    /**
     * Send a plain email.
     *
     * @param string $to      Recipient email.
     * @param string $subject Email subject.
     * @param string $body    Email body.
     * @return bool True on success.
     */
    public function send( string $to, string $subject, string $body ): bool {
        $site_name = get_bloginfo( 'name' );
        $headers   = [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf( 'From: %s <%s>', $site_name, get_option( 'fm_admin_email', get_option( 'admin_email' ) ) ),
        ];

        $result = wp_mail( $to, $subject, $body, $headers );

        if ( $result ) {
            Logger::log( sprintf( 'Email sent to %s: %s', $to, $subject ) );
        } else {
            Logger::log( sprintf( 'Email FAILED to %s: %s', $to, $subject ), Logger::LEVEL_ERROR );
        }

        return $result;
    }

    /**
     * Send an HTML email.
     *
     * @param string $to      Recipient email.
     * @param string $subject Email subject.
     * @param string $html    HTML body.
     * @return bool True on success.
     */
    public function send_html( string $to, string $subject, string $html ): bool {
        $site_name = get_bloginfo( 'name' );
        $headers   = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', $site_name, get_option( 'fm_admin_email', get_option( 'admin_email' ) ) ),
        ];

        $result = wp_mail( $to, $subject, $html, $headers );

        if ( $result ) {
            Logger::log( sprintf( 'HTML email sent to %s: %s', $to, $subject ) );
        } else {
            Logger::log( sprintf( 'HTML email FAILED to %s: %s', $to, $subject ), Logger::LEVEL_ERROR );
        }

        return $result;
    }

    /**
     * Notify admin when a new ticket is created.
     *
     * @param int $ticket_id Ticket post ID.
     * @return bool
     */
    public function notify_new_ticket( int $ticket_id ): bool {
        if ( 'yes' !== get_option( 'fm_notif_new_ticket', 'yes' ) ) {
            return false;
        }

        $ticket = $this->get_ticket_data( $ticket_id );

        if ( empty( $ticket ) ) {
            return false;
        }

        $admin_email = get_option( 'fm_admin_email', get_option( 'admin_email' ) );
        $subject     = sprintf( '[%s] New Maintenance Request', $ticket['full_number'] );
        $site_url    = admin_url( 'admin.php?page=fm-requests&action=view&id=' . $ticket_id );

        $html = $this->wrap_html( sprintf(
            '<h2>New Maintenance Request</h2>
            <p>A new maintenance request has been received.</p>
            <table style="width:100%%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Ticket</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Client</td><td style="padding:10px;border:1px solid #ddd;">%s &lt;%s&gt;</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Subject</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Priority</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Status</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
            </table>
            <p><a href="%s" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">View Ticket</a></p>',
            esc_html( $ticket['full_number'] ),
            esc_html( $ticket['client_name'] ),
            esc_html( $ticket['client_email'] ),
            esc_html( $ticket['subject'] ),
            esc_html( ucfirst( $ticket['priority'] ) ),
            esc_html( ucfirst( $ticket['status'] ) ),
            esc_url( $site_url )
        ) );

        return $this->send_html( $admin_email, $subject, $html );
    }

    /**
     * Notify admin when ticket status changes.
     *
     * @param int    $ticket_id    Ticket post ID.
     * @param string $old_status   Old status.
     * @param string $new_status   New status.
     * @return bool
     */
    public function notify_status_change( int $ticket_id, string $old_status, string $new_status ): bool {
        if ( 'yes' !== get_option( 'fm_notif_status_change', 'yes' ) ) {
            return false;
        }

        $ticket = $this->get_ticket_data( $ticket_id );

        if ( empty( $ticket ) ) {
            return false;
        }

        $admin_email = get_option( 'fm_admin_email', get_option( 'admin_email' ) );
        $subject     = sprintf( '[%s] Status Changed: %s', $ticket['full_number'], ucfirst( $new_status ) );
        $site_url    = admin_url( 'admin.php?page=fm-requests&action=view&id=' . $ticket_id );

        $html = $this->wrap_html( sprintf(
            '<h2>Ticket Status Changed</h2>
            <table style="width:100%%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Ticket</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Client</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Old Status</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">New Status</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
            </table>
            <p><a href="%s" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">View Ticket</a></p>',
            esc_html( $ticket['full_number'] ),
            esc_html( $ticket['client_name'] ),
            esc_html( ucfirst( $old_status ) ),
            esc_html( ucfirst( $new_status ) ),
            esc_url( $site_url )
        ) );

        return $this->send_html( $admin_email, $subject, $html );
    }

    /**
     * Notify admin when a reply is received.
     *
     * @param int    $ticket_id Ticket post ID.
     * @param string $from_name Sender name.
     * @param string $from_email Sender email.
     * @return bool
     */
    public function notify_reply_received( int $ticket_id, string $from_name, string $from_email ): bool {
        if ( 'yes' !== get_option( 'fm_notif_reply', 'yes' ) ) {
            return false;
        }

        $ticket = $this->get_ticket_data( $ticket_id );

        if ( empty( $ticket ) ) {
            return false;
        }

        $admin_email = get_option( 'fm_admin_email', get_option( 'admin_email' ) );
        $subject     = sprintf( '[%s] New Reply from %s', $ticket['full_number'], $from_name );
        $site_url    = admin_url( 'admin.php?page=fm-requests&action=view&id=' . $ticket_id );

        $html = $this->wrap_html( sprintf(
            '<h2>New Reply Received</h2>
            <p>A new reply has been received on ticket %s.</p>
            <table style="width:100%%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Ticket</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">From</td><td style="padding:10px;border:1px solid #ddd;">%s &lt;%s&gt;</td></tr>
            </table>
            <p><a href="%s" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">View Ticket</a></p>',
            esc_html( $ticket['full_number'] ),
            esc_html( $ticket['full_number'] ),
            esc_html( $from_name ),
            esc_html( $from_email ),
            esc_url( $site_url )
        ) );

        return $this->send_html( $admin_email, $subject, $html );
    }

    /**
     * Notify developer when assigned to a ticket.
     *
     * @param int $ticket_id  Ticket post ID.
     * @param int $developer_id Developer user ID.
     * @return bool
     */
    public function notify_developer_assigned( int $ticket_id, int $developer_id ): bool {
        if ( 'yes' !== get_option( 'fm_notif_assignment', 'yes' ) ) {
            return false;
        }

        $ticket = $this->get_ticket_data( $ticket_id );

        if ( empty( $ticket ) ) {
            return false;
        }

        $developer = get_user_by( 'id', $developer_id );

        if ( ! $developer ) {
            return false;
        }

        $subject  = sprintf( '[%s] You have been assigned to a ticket', $ticket['full_number'] );
        $site_url = admin_url( 'admin.php?page=fm-requests&action=view&id=' . $ticket_id );

        $html = $this->wrap_html( sprintf(
            '<h2>Ticket Assignment</h2>
            <p>You have been assigned to ticket %s.</p>
            <table style="width:100%%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Ticket</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Client</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Subject</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
                <tr><td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">Priority</td><td style="padding:10px;border:1px solid #ddd;">%s</td></tr>
            </table>
            <p><a href="%s" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">View Ticket</a></p>',
            esc_html( $ticket['full_number'] ),
            esc_html( $ticket['full_number'] ),
            esc_html( $ticket['client_name'] ),
            esc_html( $ticket['subject'] ),
            esc_html( ucfirst( $ticket['priority'] ) ),
            esc_url( $site_url )
        ) );

        return $this->send_html( $developer->user_email, $subject, $html );
    }

    /**
     * Wrap content in HTML email template.
     *
     * @param string $content Inner HTML content.
     * @return string Full HTML email.
     */
    private function wrap_html( string $content ): string {
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body style="margin:0;padding:0;background-color:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Oxygen-Sans,Ubuntu,Cantarell,Helvetica Neue,sans-serif;">
                <div style="max-width:600px;margin:0 auto;background-color:#ffffff;">
                    <div style="background-color:#2271b1;padding:20px;text-align:center;">
                        <h1 style="color:#ffffff;margin:0;font-size:20px;">%s</h1>
                    </div>
                    <div style="padding:20px;">
                        %s
                    </div>
                    <div style="background-color:#f0f0f0;padding:15px;text-align:center;font-size:12px;color:#666666;">
                        <p style="margin:0;">This is an automated notification from %s</p>
                    </div>
                </div>
            </body>
            </html>',
            esc_html( get_bloginfo( 'name' ) ),
            $content,
            esc_html( get_bloginfo( 'name' ) )
        );
    }

    /**
     * Get ticket data for notifications.
     *
     * @param int $ticket_id Ticket post ID.
     * @return array<string, mixed> Ticket data or empty array.
     */
    private function get_ticket_data( int $ticket_id ): array {
        $ticket_manager = new TicketManager();
        return $ticket_manager->get_ticket_meta( $ticket_id );
    }
}
