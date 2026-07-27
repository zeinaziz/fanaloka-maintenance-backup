<?php
/**
 * IMAP Reader - Connect and read emails via IMAP.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\IMAP;

use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * IMAPReader Class.
 */
class IMAPReader {

    /**
     * IMAP connection object (IMAP\Connection in PHP 8.2+ or resource in older versions).
     *
     * @var object|resource|null
     */
    private $connection = null;

    /**
     * Settings from options.
     *
     * @var array<string, string>
     */
    private array $settings = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load IMAP settings from wp_options.
     *
     * @return void
     */
    private function load_settings(): void {
        $this->settings = [
            'host'     => get_option( 'fm_imap_host', '' ),
            'port'     => get_option( 'fm_imap_port', '993' ),
            'ssl'      => get_option( 'fm_imap_ssl', 'ssl' ),
            'username' => get_option( 'fm_imap_username', '' ),
            'password' => get_option( 'fm_imap_password', '' ),
            'folder'   => get_option( 'fm_imap_folder', 'INBOX' ),
        ];
    }

    /**
     * Build IMAP mailbox string.
     *
     * @return string
     */
    private function build_mailbox(): string {
        $ssl_map = [
            'ssl'    => '/imap/ssl/novalidate-cert',
            'tls'    => '/imap/ssl/tls/novalidate-cert',
            'notls'  => '/imap/notls',
        ];

        $flags = $ssl_map[ $this->settings['ssl'] ] ?? '/imap/ssl/novalidate-cert';

        return sprintf(
            '{%s:%s%s}%s',
            $this->settings['host'],
            $this->settings['port'],
            $flags,
            $this->settings['folder']
        );
    }

    /**
     * Connect to IMAP server.
     *
     * @return array{success: bool, message: string}
     */
    public function connect(): array {
        if ( ! function_exists( 'imap_open' ) ) {
            return [
                'success' => false,
                'message' => __( 'PHP IMAP extension is not installed.', 'fanaloka-maintenance' ),
            ];
        }

        if ( empty( $this->settings['host'] ) ) {
            return [
                'success' => false,
                'message' => __( 'IMAP host is not configured.', 'fanaloka-maintenance' ),
            ];
        }

        if ( empty( $this->settings['username'] ) || empty( $this->settings['password'] ) ) {
            return [
                'success' => false,
                'message' => __( 'IMAP credentials are not configured.', 'fanaloka-maintenance' ),
            ];
        }

        $mailbox = $this->build_mailbox();

        // Suppress IMAP warnings and errors.
        $this->connection = @imap_open(
            $mailbox,
            $this->settings['username'],
            $this->settings['password'],
            0,
            1,
            [ 'DISABLE_AUTHENTICATOR' => 'GSSAPI' ]
        );

        if ( false === $this->connection ) {
            $error = imap_errors();
            $error_msg = is_array( $error ) ? implode( '; ', $error ) : __( 'Unknown IMAP error.', 'fanaloka-maintenance' );

            Logger::log(
                sprintf( 'IMAP connection failed to %s: %s', $this->settings['host'], $error_msg ),
                Logger::LEVEL_ERROR
            );

            return [
                'success' => false,
                'message' => sprintf(
                    __( 'Connection failed: %s', 'fanaloka-maintenance' ),
                    $error_msg
                ),
            ];
        }

        Logger::log(
            sprintf( 'IMAP connected to %s as %s', $this->settings['host'], $this->settings['username'] )
        );

        return [
            'success' => true,
            'message' => __( 'Connection successful.', 'fanaloka-maintenance' ),
        ];
    }

    /**
     * Test IMAP connection.
     *
     * @return array{success: bool, message: string}
     */
    public function test_connection(): array {
        $result = $this->connect();
        $this->disconnect();

        return $result;
    }

    /**
     * Check if IMAP connection is active.
     *
     * @return bool
     */
    private function is_connected(): bool {
        return null !== $this->connection && false !== $this->connection;
    }

    /**
     * Disconnect from IMAP server.
     *
     * @return void
     */
    public function disconnect(): void {
        if ( $this->is_connected() ) {
            imap_close( $this->connection );
            $this->connection = null;
        }
    }

    /**
     * Build IMAP mailbox string for a specific folder.
     *
     * @param string $folder Folder name (e.g., '[Gmail]/Sent Mail').
     * @return string Mailbox string.
     */
    private function build_mailbox_for_folder( string $folder ): string {
        $ssl_map = [
            'ssl'    => '/imap/ssl/novalidate-cert',
            'tls'    => '/imap/ssl/tls/novalidate-cert',
            'notls'  => '/imap/notls',
        ];

        $flags = $ssl_map[ $this->settings['ssl'] ] ?? '/imap/ssl/novalidate-cert';

        return sprintf(
            '{%s:%s%s}%s',
            $this->settings['host'],
            $this->settings['port'],
            $flags,
            $folder
        );
    }

    /**
     * Open a specific folder without disconnecting the main connection.
     *
     * Uses imap_reopen to switch to another folder on the same connection.
     *
     * @param string $folder Folder name (e.g., '[Gmail]/Sent Mail').
     * @return bool True on success.
     */
    public function open_folder( string $folder ): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $result = @imap_reopen( $this->connection, $this->build_mailbox_for_folder( $folder ) );

        if ( false === $result ) {
            Logger::log(
                sprintf( 'Failed to open folder: %s', $folder ),
                Logger::LEVEL_WARNING
            );
            return false;
        }

        return true;
    }

    /**
     * Get all emails (including seen) since a date from current folder.
     *
     * @param string $since_date Date string in IMAP format (e.g., "01-Jan-2024").
     * @return array<int, array<string, mixed>> Array of email data.
     */
    public function get_all_emails_since( string $since_date ): array {
        if ( ! $this->is_connected() ) {
            $connect_result = $this->connect();
            if ( ! $connect_result['success'] ) {
                return [];
            }
        }

        $search = @imap_search( $this->connection, 'SINCE "' . $since_date . '"' );

        if ( false === $search || ! is_array( $search ) ) {
            return [];
        }

        $emails = [];
        foreach ( $search as $msg_number ) {
            $email = $this->get_email( $msg_number );
            if ( false !== $email ) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Search for unread emails.
     *
     * @return array<int, int> Array of message numbers.
     */
    public function search_unseen(): array {
        if ( ! $this->is_connected() ) {
            $connect_result = $this->connect();
            if ( ! $connect_result['success'] ) {
                return [];
            }
        }

        $search = @imap_search( $this->connection, 'UNSEEN' );

        if ( false === $search || ! is_array( $search ) ) {
            return [];
        }

        return $search;
    }

    /**
     * Get email headers for a specific message.
     *
     * @param int $msg_number Message number.
     * @return array<string, mixed>|false Headers array or false on failure.
     */
    public function get_headers( int $msg_number ) {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $header = @imap_headerinfo( $this->connection, $msg_number );

        if ( false === $header ) {
            Logger::log(
                sprintf( 'Failed to get headers for message #%d', $msg_number ),
                Logger::LEVEL_WARNING
            );
            return false;
        }

        return $this->normalize_headers( $header );
    }

    /**
     * Get email body for a specific message.
     *
     * @param int $msg_number Message number.
     * @return string|false Body text or false on failure.
     */
    public function get_body( int $msg_number ) {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $structure = @imap_fetchstructure( $this->connection, $msg_number );

        if ( false === $structure ) {
            Logger::log(
                sprintf( 'Failed to get structure for message #%d', $msg_number ),
                Logger::LEVEL_WARNING
            );
            return false;
        }

        return $this->extract_body( $msg_number, $structure );
    }

    /**
     * Get attachments for a specific message.
     *
     * @param int $msg_number Message number.
     * @return array<int, array{name: string, type: string, size: int, part: int}> Attachment data.
     */
    public function get_attachments( int $msg_number ): array {
        if ( ! $this->is_connected() ) {
            return [];
        }

        $structure = @imap_fetchstructure( $this->connection, $msg_number );

        if ( false === $structure || empty( $structure->parts ) ) {
            return [];
        }

        return $this->extract_attachments( $msg_number, $structure );
    }

    /**
     * Get full email data for a specific message.
     *
     * @param int $msg_number Message number.
     * @return array<string, mixed>|false Full email data or false on failure.
     */
    public function get_email( int $msg_number ) {
        $headers = $this->get_headers( $msg_number );

        if ( false === $headers ) {
            return false;
        }

        $structure = @imap_fetchstructure( $this->connection, $msg_number );

        if ( false !== $structure ) {
            $both = $this->extract_body_with_html( $msg_number, $structure );
            $body      = $both['body'];
            $body_html = $both['body_html'];
        } else {
            $body      = $this->get_body( $msg_number );
            $body_html = '';
        }

        $attachments = $this->get_attachments( $msg_number );

        return [
            'msg_number'  => $msg_number,
            'headers'     => $headers,
            'body'        => $body !== false ? $body : '',
            'body_html'   => $body_html,
            'attachments' => $attachments,
        ];
    }

    /**
     * Get all unread emails.
     *
     * @return array<int, array<string, mixed>> Array of email data.
     */
    public function get_unseen_emails(): array {
        $msg_numbers = $this->search_unseen();
        $emails      = [];

        foreach ( $msg_numbers as $msg_number ) {
            $email = $this->get_email( $msg_number );

            if ( false !== $email ) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Get emails received since a specific date.
     *
     * @param string $since_date Date string in IMAP format (e.g., "01-Jan-2024").
     * @return array<int, array<string, mixed>> Array of email data.
     */
    public function get_emails_since( string $since_date ): array {
        if ( ! $this->is_connected() ) {
            $connect_result = $this->connect();
            if ( ! $connect_result['success'] ) {
                return [];
            }
        }

        $search = @imap_search( $this->connection, 'UNSEEN SINCE "' . $since_date . '"' );

        if ( false === $search || ! is_array( $search ) ) {
            return [];
        }

        $emails = [];
        foreach ( $search as $msg_number ) {
            $email = $this->get_email( $msg_number );
            if ( false !== $email ) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Mark a message as seen.
     *
     * @param int $msg_number Message number.
     * @return bool True on success.
     */
    public function mark_as_seen( int $msg_number ): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $result = @imap_setflag_full( $this->connection, (string) $msg_number, '\\Seen' );

        if ( false === $result ) {
            Logger::log(
                sprintf( 'Failed to mark message #%d as seen', $msg_number ),
                Logger::LEVEL_WARNING
            );
            return false;
        }

        return true;
    }

    /**
     * Get total number of messages in mailbox.
     *
     * @return int Message count.
     */
    public function get_message_count(): int {
        if ( ! $this->is_connected() ) {
            return 0;
        }

        $status = @imap_status( $this->connection, $this->build_mailbox(), SA_ALL );

        if ( false === $status ) {
            return 0;
        }

        return $status->messages;
    }

    /**
     * Normalize IMAP header object to array.
     *
     * @param object $header IMAP header object.
     * @return array<string, mixed>
     */
    private function normalize_headers( object $header ): array {
        $from = [];
        if ( ! empty( $header->from ) && is_array( $header->from ) ) {
            $first = $header->from[0];
            $from  = [
                'name'  => $first->mailbox . '@' . $first->host,
                'email' => $first->mailbox . '@' . $first->host,
            ];
            if ( ! empty( $first->personal ) ) {
                $from['name'] = $first->personal;
            }
        }

        $to = [];
        if ( ! empty( $header->to ) && is_array( $header->to ) ) {
            foreach ( $header->to as $recipient ) {
                $to[] = [
                    'name'  => $recipient->personal ?? $recipient->mailbox . '@' . $recipient->host,
                    'email' => $recipient->mailbox . '@' . $recipient->host,
                ];
            }
        }

        return [
            'message_id' => $header->message_id ?? '',
            'subject'    => isset( $header->subject ) ? mb_decode_mimeheader( $header->subject ) : '',
            'date'       => $header->date ?? '',
            'from'       => $from,
            'to'         => $to,
            'in_reply_to' => $header->in_reply_to ?? '',
            'references' => $header->references ?? '',
            'uid'        => $header->Uid ?? 0,
            'recent'     => $header->Recent ?? false,
            'unseen'     => $header->Unseen ?? false,
        ];
    }

    /**
     * Extract email body from IMAP structure.
     *
     * @param int    $msg_number Message number.
     * @param object $structure Email structure.
     * @return string Body text.
     */
    private function extract_body( int $msg_number, object $structure ): string {
        $body = '';

        // Simple message (no parts).
        if ( empty( $structure->parts ) ) {
            $encoding = $structure->encoding ?? 0;

            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
            if ( 0 === $encoding ) {
                // Plain text, no encoding.
                $body = @imap_body( $this->connection, $msg_number ) ?: '';
            } elseif ( 3 === $encoding ) {
                // BASE64.
                $raw  = @imap_base64( @imap_body( $this->connection, $msg_number ) ) ?: '';
                $body = $raw;
            } elseif ( 4 === $encoding ) {
                // Quoted-Printable.
                $raw  = @imap_qprint( @imap_body( $this->connection, $msg_number ) ) ?: '';
                $body = $raw;
            } else {
                $body = @imap_body( $this->connection, $msg_number ) ?: '';
            }

            return $this->clean_body( $body, $structure );
        }

        // Multipart message.
        $plain_body = '';
        $html_body  = '';

        foreach ( $structure->parts as $part_number => $part ) {
            $mime_type = $this->get_mime_type( $part );
            $encoding  = $part->encoding ?? 0;
            $part_number_index = $part_number + 1;

            // Handle nested multipart (e.g., multipart/alternative inside multipart/mixed).
            if ( ! empty( $part->parts ) ) {
                $nested = $this->extract_body_from_parts( $msg_number, $part->parts, $part_number_index );
                if ( ! empty( $nested['plain'] ) && empty( $plain_body ) ) {
                    $plain_body = $nested['plain'];
                }
                if ( ! empty( $nested['html'] ) && empty( $html_body ) ) {
                    $html_body = $nested['html'];
                }
                continue;
            }

            $raw = @imap_fetchbody( $this->connection, $msg_number, (string) $part_number_index ) ?: '';

            if ( 3 === $encoding ) {
                $raw = @imap_base64( $raw ) ?: '';
            } elseif ( 4 === $encoding ) {
                $raw = @imap_qprint( $raw ) ?: '';
            }

            if ( 'text/plain' === $mime_type && empty( $plain_body ) ) {
                $plain_body = $raw;
            } elseif ( 'text/html' === $mime_type && empty( $html_body ) ) {
                $html_body = $raw;
            }
        }

        // Prefer plain text, fallback to HTML.
        $body = ! empty( $plain_body ) ? $plain_body : $html_body;

        return $this->clean_body( $body, $structure );
    }

    /**
     * Extract body and HTML body from email structure.
     *
     * @param int      $msg_number Message number.
     * @param object   $structure Email structure.
     * @return array{body: string, body_html: string}
     */
    private function extract_body_with_html( int $msg_number, object $structure ): array {
        if ( empty( $structure->parts ) ) {
            $encoding = $structure->encoding ?? 0;

            if ( 0 === $encoding ) {
                $body = @imap_body( $this->connection, $msg_number ) ?: '';
            } elseif ( 3 === $encoding ) {
                $body = @imap_base64( @imap_body( $this->connection, $msg_number ) ) ?: '';
            } elseif ( 4 === $encoding ) {
                $body = @imap_qprint( @imap_body( $this->connection, $msg_number ) ) ?: '';
            } else {
                $body = @imap_body( $this->connection, $msg_number ) ?: '';
            }

            $mime_type = $this->get_mime_type( $structure );
            if ( 'text/html' === $mime_type ) {
                return [
                    'body'      => '',
                    'body_html' => $this->clean_body( $body, $structure ),
                ];
            }

            return [
                'body'      => $this->clean_body( $body, $structure ),
                'body_html' => '',
            ];
        }

        $plain_body = '';
        $html_body  = '';

        foreach ( $structure->parts as $part_number => $part ) {
            $mime_type = $this->get_mime_type( $part );
            $encoding  = $part->encoding ?? 0;
            $part_number_index = $part_number + 1;

            if ( ! empty( $part->parts ) ) {
                $nested = $this->extract_body_from_parts( $msg_number, $part->parts, $part_number_index );
                if ( ! empty( $nested['plain'] ) && empty( $plain_body ) ) {
                    $plain_body = $nested['plain'];
                }
                if ( ! empty( $nested['html'] ) && empty( $html_body ) ) {
                    $html_body = $nested['html'];
                }
                continue;
            }

            $raw = @imap_fetchbody( $this->connection, $msg_number, (string) $part_number_index ) ?: '';

            if ( 3 === $encoding ) {
                $raw = @imap_base64( $raw ) ?: '';
            } elseif ( 4 === $encoding ) {
                $raw = @imap_qprint( $raw ) ?: '';
            }

            if ( 'text/plain' === $mime_type && empty( $plain_body ) ) {
                $plain_body = $raw;
            } elseif ( 'text/html' === $mime_type && empty( $html_body ) ) {
                $html_body = $raw;
            }
        }

        return [
            'body'      => ! empty( $plain_body ) ? $this->clean_body( $plain_body, $structure ) : $this->clean_body( $html_body, $structure ),
            'body_html' => ! empty( $html_body ) ? $this->sanitize_html_body( $html_body ) : '',
        ];
    }

    /**
     * Sanitize HTML body - keep formatting tags, strip dangerous ones.
     *
     * @param string $body Raw HTML body.
     * @return string Sanitized HTML.
     */
    private function sanitize_html_body( string $body ): string {
        // Remove <style> tags and their content.
        $body = preg_replace( '/<style\b[^>]*>.*?<\/style\s*>/is', '', $body );

        // Remove <script> tags and their content.
        $body = preg_replace( '/<script\b[^>]*>.*?<\/script\s*>/is', '', $body );

        // Remove HTML comments.
        $body = preg_replace( '/<!--.*?-->/s', '', $body );

        // Normalize line endings.
        $body = str_replace( [ "\r\n", "\r" ], "\n", $body );
        $body = trim( $body );

        // Use wp_kses_post to keep formatting tags (b, i, strong, em, p, br, etc).
        $body = wp_kses_post( $body );

        return $body;
    }

    /**
     * Extract body from nested parts.
     *
     * @param int      $msg_number Message number.
     * @param object[] $parts      Email parts.
     * @param string   $prefix     Part number prefix.
     * @return array{plain: string, html: string}
     */
    private function extract_body_from_parts( int $msg_number, array $parts, string $prefix = '' ): array {
        $result = [ 'plain' => '', 'html' => '' ];

        foreach ( $parts as $i => $part ) {
            $mime_type = $this->get_mime_type( $part );
            $encoding  = $part->encoding ?? 0;
            $part_num  = $prefix ? $prefix . '.' . ( $i + 1 ) : (string) ( $i + 1 );

            if ( ! empty( $part->parts ) ) {
                $nested = $this->extract_body_from_parts( $msg_number, $part->parts, $part_num );
                if ( ! empty( $nested['plain'] ) && empty( $result['plain'] ) ) {
                    $result['plain'] = $nested['plain'];
                }
                if ( ! empty( $nested['html'] ) && empty( $result['html'] ) ) {
                    $result['html'] = $nested['html'];
                }
                continue;
            }

            $raw = @imap_fetchbody( $this->connection, $msg_number, $part_num ) ?: '';

            if ( 3 === $encoding ) {
                $raw = @imap_base64( $raw ) ?: '';
            } elseif ( 4 === $encoding ) {
                $raw = @imap_qprint( $raw ) ?: '';
            }

            if ( 'text/plain' === $mime_type && empty( $result['plain'] ) ) {
                $result['plain'] = $raw;
            } elseif ( 'text/html' === $mime_type && empty( $result['html'] ) ) {
                $result['html'] = $raw;
            }
        }

        return $result;
    }

    /**
     * Extract attachments from IMAP structure.
     *
     * @param int    $msg_number Message number.
     * @param object $structure Email structure.
     * @return array<int, array{name: string, type: string, size: int, part: int}>
     */
    private function extract_attachments( int $msg_number, object $structure ): array {
        $attachments = [];

        if ( empty( $structure->parts ) ) {
            return $attachments;
        }

        foreach ( $structure->parts as $part_number => $part ) {
            // Skip text parts.
            if ( 0 === $part->type && empty( $part->parts ) ) {
                continue;
            }

            // Inline parts with content-id are usually embedded images.
            $disposition = $part->disposition ?? 'inline';
            $is_attachment = 'attachment' === $disposition || ( ! empty( $part->parameters ) && $this->has_attachment_filename( $part ) );

            if ( ! $is_attachment ) {
                continue;
            }

            $filename = $this->get_attachment_filename( $part );
            $mime_type = $this->get_mime_type( $part );
            $part_number_index = $part_number + 1;

            // Handle nested multipart.
            if ( ! empty( $part->parts ) ) {
                $nested = $this->extract_attachments( $msg_number, $part );
                $attachments = array_merge( $attachments, $nested );
                continue;
            }

            $attachments[] = [
                'name'     => $filename ?: sprintf( 'attachment_%d', $part_number_index ),
                'type'     => $mime_type,
                'size'     => $part->bytes ?? 0,
                'part'     => $part_number_index,
            ];
        }

        return $attachments;
    }

    /**
     * Get MIME type from part.
     *
     * @param object $part IMAP part.
     * @return string MIME type.
     */
    private function get_mime_type( object $part ): string {
        $primary   = $part->type ?? 0;
        $secondary = $part->subtype ?? 'plain';

        $types = [
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'video',
            6 => 'image',
        ];

        $mime = ( $types[ $primary ] ?? 'application' ) . '/' . strtolower( $secondary );

        // Fix incorrect MIME types based on filename extension.
        $filename = $this->get_attachment_filename( $part );
        if ( ! empty( $filename ) ) {
            $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
            $ext_map = [
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'pdf'  => 'application/pdf',
                'doc'  => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'zip'  => 'application/zip',
            ];
            if ( isset( $ext_map[ $ext ] ) ) {
                $mime = $ext_map[ $ext ];
            }
        }

        return $mime;
    }

    /**
     * Check if part has attachment filename.
     *
     * @param object $part IMAP part.
     * @return bool
     */
    private function has_attachment_filename( object $part ): bool {
        if ( empty( $part->parameters ) ) {
            return false;
        }

        foreach ( $part->parameters as $param ) {
            if ( 'name' === strtolower( $param->attribute ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get attachment filename from part.
     *
     * @param object $part IMAP part.
     * @return string Filename.
     */
    private function get_attachment_filename( object $part ): string {
        if ( empty( $part->parameters ) ) {
            return '';
        }

        foreach ( $part->parameters as $param ) {
            if ( 'name' === strtolower( $param->attribute ) ) {
                return mb_decode_mimeheader( $param->value );
            }
        }

        return '';
    }

    /**
     * Clean body text.
     *
     * @param string $body      Raw body.
     * @param object $structure Email structure.
     * @return string Cleaned body.
     */
    private function clean_body( string $body, object $structure ): string {
        // Decode HTML to plain text if subtype is html.
        $subtype = strtolower( $structure->subtype ?? 'plain' );

        if ( 'html' === $subtype ) {
            // Strip HTML tags but keep line breaks.
            $body = wp_strip_all_tags( $body, true );
        }

        // Normalize line endings.
        $body = str_replace( [ "\r\n", "\r" ], "\n", $body );

        // Trim whitespace.
        $body = trim( $body );

        return $body;
    }

    /**
     * Get raw attachment data for saving.
     *
     * @param int    $msg_number Message number.
     * @param int    $part       Part number.
     * @return string|false Raw attachment data or false on failure.
     */
    public function get_attachment_data( int $msg_number, int $part ) {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $data = @imap_fetchbody( $this->connection, $msg_number, (string) $part, FT_PEEK );

        if ( false === $data ) {
            Logger::log(
                sprintf( 'Failed to get attachment data for message #%d part %d', $msg_number, $part ),
                Logger::LEVEL_WARNING
            );
            return false;
        }

        // Get structure to check encoding.
        $structure = @imap_fetchstructure( $this->connection, $msg_number );

        if ( false !== $structure && ! empty( $structure->parts[ $part - 1 ] ) ) {
            $encoding = $structure->parts[ $part - 1 ]->encoding ?? 0;

            if ( 3 === $encoding ) {
                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
                $data = @imap_base64( $data ) ?: '';
            } elseif ( 4 === $encoding ) {
                $data = @imap_qprint( $data ) ?: '';
            }
        }

        return $data;
    }
}
