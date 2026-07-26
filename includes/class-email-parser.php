<?php
/**
 * Email Parser - Parse email data into structured format.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Email;

use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * EmailParser Class.
 */
class EmailParser {

    /**
     * Domains to ignore (system notifications, not client requests).
     *
     * @var array<int, string>
     */
    private const IGNORED_DOMAINS = [
        'niftypm.com',
        'slackhq.com',
        'slack.com',
        'google.com',
        'gmail.com',
        'docs.google.com',
        'accounts.google.com',
        'drive-shares-noreply.google.com',
        'loom.com',
        'mail.loom.com',
        'pantheon.io',
        '10web.io',
        'wordpress.com',
        'wordpress.org',
        'github.com',
        'gather.town',
        'lottiefiles.com',
        'mail.lottiefiles.com',
        'lottielab.com',
        'mail.lottielab.com',
        'substack.com',
        'coursera.org',
        'm.learn.coursera.org',
        'element.how',
        'youvegotjam.email',
        'teleprompterpro.com',
        'theosoti.com',
        'templatemonster.com',
        'wandb.ai',
        'mail.wandb.ai',
        'iqonic.design',
        'iqonic.on.crisp.email',
        'crisp.email',
        'tagshop.ai',
        'nitropack.com',
        'renewableenergy.id',
    ];

    /**
     * Local domain (team emails, not client requests).
     *
     * @var string
     */
    private const LOCAL_DOMAIN = 'fanaloka.co';

    /**
     * Prefixes that indicate automated/system emails.
     *
     * @var array<int, string>
     */
    private const IGNORED_SENDER_PREFIXES = [
        'noreply',
        'no-reply',
        'donotreply',
        'do-not-reply',
        'notifications',
        'notification',
        'alerts',
        'alert',
        'updates',
        'mailer-daemon',
        'postmaster',
        'system',
        'admin',
        'support',
        'info',
        'hello',
        'newsletter',
        'marketing',
        'hello',
        'team',
        'feedback',
        'sales',
        'promo',
        'campaign',
        'workspace-noreply',
        'drive-shares-noreply',
        'comments-noreply',
        'strawberryjam',
        'drew',
        'lora',
        'maxime',
    ];

    /**
     * Reply/Forward prefixes to strip from subject.
     *
     * @var array<int, string>
     */
    private const REPLY_PREFIXES = [
        'Re',
        'RE',
        'Fw',
        'FW',
        'Fwd',
        'FWD',
        'Aw',
        'Jawab',
        'Balasan',
    ];

    /**
     * Parse email data from IMAP reader into structured format.
     *
     * @param array<string, mixed> $email_data Raw email data from IMAPReader.
     * @return array<string, mixed>|false Parsed email data or false on failure.
     */
    public function parse_email( array $email_data ) {
        if ( empty( $email_data['headers'] ) ) {
            Logger::log( 'Email parse failed: missing headers', Logger::LEVEL_WARNING );
            return false;
        }

        $headers = $email_data['headers'];
        $body    = $email_data['body'] ?? '';
        $attachments = $email_data['attachments'] ?? [];

        // Extract basic fields.
        $sender_name  = $this->extract_sender_name( $headers );
        $sender_email = $this->extract_sender_email( $headers );
        $subject      = $headers['subject'] ?? '';
        $date         = $this->parse_date( $headers['date'] ?? '' );
        $message_id   = trim( $headers['message_id'] ?? '' );
        $in_reply_to  = trim( $headers['in_reply_to'] ?? '' );
        $references   = trim( $headers['references'] ?? '' );

        if ( empty( $sender_email ) ) {
            Logger::log( 'Email parse failed: missing sender email', Logger::LEVEL_WARNING );
            return false;
        }

        // Normalize subject (remove Re:/Fwd: prefixes but keep ticket number).
        $normalized_subject = $this->normalize_subject( $subject );

        // Clean body.
        $clean_body = $this->clean_body( $body );

        // Filter allowed attachment types.
        $filtered_attachments = $this->filter_attachments( $attachments );

        // Build HTML body from raw HTML if available.
        $body_html = $email_data['body_html'] ?? '';
        if ( ! empty( $body_html ) ) {
            // Remove <style> and <script> tags.
            $body_html = preg_replace( '/<style\b[^>]*>.*?<\/style\s*>/is', '', $body_html );
            $body_html = preg_replace( '/<script\b[^>]*>.*?<\/script\s*>/is', '', $body_html );
            $body_html = preg_replace( '/<!--.*?-->/s', '', $body_html );

            // Sanitize HTML but keep formatting tags.
            $body_html = wp_kses_post( $body_html );
            $body_html = str_replace( [ "\r\n", "\r" ], "\n", $body_html );
            $body_html = preg_replace( "/\n{3,}/", "\n\n", $body_html );
            $body_html = trim( $body_html );
        }

        return [
            'sender_name'        => $sender_name,
            'sender_email'       => $sender_email,
            'subject'            => $normalized_subject,
            'original_subject'   => $subject,
            'body'               => $clean_body,
            'body_html'          => $body_html,
            'raw_body'           => $body,
            'date'               => $date,
            'message_id'         => $message_id,
            'in_reply_to'        => $in_reply_to,
            'references'         => $references,
            'attachments'        => $filtered_attachments,
            'msg_number'         => $email_data['msg_number'] ?? 0,
            'uid'                => $email_data['headers']['uid'] ?? 0,
        ];
    }

    /**
     * Extract sender name from headers.
     *
     * @param array<string, mixed> $headers Email headers.
     * @return string Sender name.
     */
    private function extract_sender_name( array $headers ): string {
        $from = $headers['from'] ?? [];

        if ( ! empty( $from['name'] ) && $from['name'] !== ( $from['email'] ?? '' ) ) {
            return sanitize_text_field( $from['name'] );
        }

        $email = $from['email'] ?? '';
        if ( ! empty( $email ) ) {
            $parts = explode( '@', $email );
            return ucfirst( sanitize_text_field( $parts[0] ) );
        }

        return '';
    }

    /**
     * Extract sender email from headers.
     *
     * @param array<string, mixed> $headers Email headers.
     * @return string Sender email.
     */
    private function extract_sender_email( array $headers ): string {
        $from = $headers['from'] ?? [];
        return sanitize_email( $from['email'] ?? '' );
    }

    /**
     * Normalize subject by removing reply/forward prefixes and ticket number.
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

        // Remove multiple levels of reply/forward prefixes.
        // Pattern: (Re|RE|Fw|FW|Fwd|FWD|Aw|Jawab|Balasan):\s* repeated
        $prefix_pattern = implode( '|', self::REPLY_PREFIXES );
        $subject = preg_replace(
            '/^(?:' . $prefix_pattern . ')(?:\:\s*(?:' . $prefix_pattern . ')(?:\:\s*)*)*\s*/i',
            '',
            $subject
        );

        return trim( $subject );
    }

    /**
     * Parse date string to MySQL datetime format.
     *
     * @param string $date_str Date string from email header.
     * @return string MySQL datetime format.
     */
    private function parse_date( string $date_str ): string {
        if ( empty( $date_str ) ) {
            return current_time( 'mysql' );
        }

        $timestamp = strtotime( $date_str );

        if ( false === $timestamp ) {
            return current_time( 'mysql' );
        }

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Extract ticket ID from subject containing [REQ-XXXX].
     *
     * @param string $subject Email subject.
     * @return int|false Ticket number or false if not found.
     */
    public function extract_ticket_id_from_subject( string $subject ) {
        $prefix = get_option( 'fm_ticket_prefix', 'REQ' );
        $pattern = '/\[' . preg_quote( $prefix, '/' ) . '-(\d+)\]/i';

        if ( preg_match( $pattern, $subject, $matches ) ) {
            return absint( $matches[1] );
        }

        return false;
    }

    /**
     * Clean email body text.
     *
     * @param string $body Raw body.
     * @return string Cleaned body.
     */
    private function clean_body( string $body ): string {
        $body = html_entity_decode( $body, ENT_QUOTES, 'UTF-8' );
        $body = wp_strip_all_tags( $body, true );
        $body = html_entity_decode( $body );
        $body = str_replace( [ "\r\n", "\r" ], "\n", $body );
        $body = preg_replace( "/\n{3,}/", "\n\n", $body );
        $body = trim( $body );

        return $body;
    }

    /**
     * Check if an email should be ignored.
     *
     * @param string $sender_email Sender email address.
     * @return bool True if email should be ignored.
     */
    public function should_ignore( string $sender_email ): bool {
        if ( empty( $sender_email ) ) {
            return true;
        }

        $email_lower = strtolower( $sender_email );
        $domain      = substr( strrchr( $email_lower, '@' ), 1 );
        $local_part  = explode( '@', $email_lower )[0];

        if ( self::LOCAL_DOMAIN === $domain ) {
            return true;
        }

        if ( in_array( $domain, self::IGNORED_DOMAINS, true ) ) {
            return true;
        }

        foreach ( self::IGNORED_SENDER_PREFIXES as $prefix ) {
            if ( 0 === strpos( $local_part, $prefix ) ) {
                return true;
            }
        }

        $custom_patterns = get_option( 'fm_ignore_sender_patterns', '' );
        if ( ! empty( $custom_patterns ) ) {
            $patterns = array_map( 'trim', explode( "\n", $custom_patterns ) );
            foreach ( $patterns as $pattern ) {
                if ( empty( $pattern ) ) {
                    continue;
                }
                if ( 0 === strpos( $pattern, '@' ) ) {
                    if ( $domain === ltrim( $pattern, '@' ) ) {
                        return true;
                    }
                } elseif ( false !== strpos( $pattern, '*' ) ) {
                    $regex = '/^' . str_replace( [ '*', '.' ], [ '.*', '\.' ], preg_quote( $pattern, '/' ) ) . '$/i';
                    if ( preg_match( $regex, $email_lower ) ) {
                        return true;
                    }
                } elseif ( $email_lower === strtolower( $pattern ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Filter attachments to only allow specific types.
     *
     * @param array<int, array{name: string, type: string, size: int, part: int}> $attachments All attachments.
     * @return array<int, array{name: string, type: string, size: int, part: int}> Filtered attachments.
     */
    private function filter_attachments( array $attachments ): array {
        $allowed_types = [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
            'application/x-gzip',
        ];

        $allowed_extensions = [
            'jpg',
            'jpeg',
            'png',
            'pdf',
            'doc',
            'docx',
            'zip',
            'gz',
        ];

        return array_filter( $attachments, function ( $attachment ) use ( $allowed_types, $allowed_extensions ) {
            $type = strtolower( $attachment['type'] ?? '' );
            $name = strtolower( $attachment['name'] ?? '' );
            $ext  = pathinfo( $name, PATHINFO_EXTENSION );

            if ( in_array( $type, $allowed_types, true ) ) {
                return true;
            }

            if ( in_array( $ext, $allowed_extensions, true ) ) {
                return true;
            }

            return false;
        } );
    }

    /**
     * Generate a unique Message-ID for outgoing emails.
     *
     * @param int $ticket_id Ticket post ID.
     * @return string Unique Message-ID.
     */
    public function generate_message_id( int $ticket_id ): string {
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        $hash   = substr( md5( uniqid( (string) $ticket_id, true ) ), 0, 16 );

        return sprintf( '<fm-%d-%s@%s>', $ticket_id, $hash, $domain );
    }

    /**
     * Get ticket number from subject.
     *
     * @param string $subject Email subject.
     * @return int|false Ticket number or false if not found.
     */
    public function get_ticket_number_from_subject( string $subject ) {
        return $this->extract_ticket_id_from_subject( $subject );
    }
}
