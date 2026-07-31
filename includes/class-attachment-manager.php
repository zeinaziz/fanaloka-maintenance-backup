<?php
/**
 * Attachment Manager - Save email attachments to Media Library.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Attachment;

use Fanaloka\Maintenance\IMAP\IMAPReader;
use Fanaloka\Maintenance\Logger\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AttachmentManager Class.
 */
class AttachmentManager {

    /**
     * Allowed MIME types.
     *
     * @var array<int, string>
     */
    private const ALLOWED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-zip-compressed',
        'application/x-gzip',
    ];

    /**
     * Save attachments from an email to a ticket.
     *
     * @param int                    $ticket_id Ticket post ID.
     * @param array<string, mixed>   $parsed    Parsed email data.
     * @return array<int, int> Array of attachment IDs.
     */
    public function save_attachments_from_email( int $ticket_id, array $parsed ): array {
        $attachments = $parsed['attachments'] ?? [];
        $msg_number  = $parsed['msg_number'] ?? 0;

        if ( empty( $attachments ) || 0 === $msg_number ) {
            return [];
        }

        $reader = new IMAPReader();
        $connect = $reader->connect();

        if ( ! $connect['success'] ) {
            Logger::log( 'Failed to connect for attachments: ' . $connect['message'], Logger::LEVEL_WARNING );
            return [];
        }

        $saved_ids   = [];
        $inline_ids  = [];

        foreach ( $attachments as $attachment ) {
            $data = $reader->get_attachment_data( $msg_number, $attachment['part'] );

            if ( false === $data ) {
                continue;
            }

            $attachment_id = $this->save_to_media_library( $data, $attachment['name'], $attachment['type'], $ticket_id );

            if ( $attachment_id ) {
                $saved_ids[] = $attachment_id;
                if ( ! empty( $attachment['inline'] ) ) {
                    $inline_ids[] = $attachment_id;
                }
            }
        }

        $reader->disconnect();

        if ( ! empty( $saved_ids ) ) {
            update_post_meta( $ticket_id, '_fm_attachment_ids', $saved_ids );
            Logger::log( sprintf( 'Saved %d attachments to ticket #%d (%d inline)', count( $saved_ids ), $ticket_id, count( $inline_ids ) ) );
        }

        return [
            'all'    => $saved_ids,
            'inline' => $inline_ids,
        ];
    }

    /**
     * Save a single file to WordPress Media Library.
     *
     * @param string $file_data   Raw file data.
     * @param string $filename    Original filename.
     * @param string $mime_type   MIME type.
     * @param int    $ticket_id   Related ticket ID.
     * @return int|false Attachment ID or false on failure.
     */
    public function save_to_media_library( string $file_data, string $filename, string $mime_type, int $ticket_id ) {
        if ( ! in_array( $mime_type, self::ALLOWED_TYPES, true ) ) {
            Logger::log( sprintf( 'Skipped disallowed attachment type: %s', $mime_type ), Logger::LEVEL_WARNING );
            return false;
        }

        // Ensure uploads directory exists.
        $upload_dir = wp_upload_dir();
        $ticket_dir = $upload_dir['path'] . '/fm_tickets/' . $ticket_id;

        if ( ! file_exists( $ticket_dir ) ) {
            wp_mkdir_p( $ticket_dir );
        }

        // Sanitize filename.
        $filename = sanitize_file_name( $filename );

        // Avoid overwrites.
        $file_path = $ticket_dir . '/' . $filename;
        $counter   = 1;
        while ( file_exists( $file_path ) ) {
            $pathinfo   = pathinfo( $filename );
            $file_path  = $ticket_dir . '/' . $pathinfo['filename'] . '-' . $counter . '.' . $pathinfo['extension'];
            $counter++;
        }

        // Write file.
        $written = file_put_contents( $file_path, $file_data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

        if ( false === $written ) {
            Logger::log( sprintf( 'Failed to write attachment file: %s', $filename ), Logger::LEVEL_ERROR );
            return false;
        }

        // Get relative path for WordPress.
        $relative_path = str_replace( $upload_dir['basedir'] . '/', '', $file_path );

        // Prepare attachment for wp_insert_attachment.
        $attachment_data = [
            'post_title'     => $filename,
            'post_mime_type' => $mime_type,
            'post_status'    => 'inherit',
            'post_content'   => '',
            'guid'           => $upload_dir['baseurl'] . '/' . $relative_path,
        ];

        $attachment_id = wp_insert_attachment( $attachment_data, $file_path );

        if ( is_wp_error( $attachment_id ) ) {
            Logger::log( 'Failed to insert attachment: ' . $attachment_id->get_error_message(), Logger::LEVEL_ERROR );
            return false;
        }

        // Generate thumbnail for images.
        if ( strpos( $mime_type, 'image/' ) === 0 ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
            wp_update_attachment_metadata( $attachment_id, $attach_data );
        }

        return $attachment_id;
    }

    /**
     * Get attachments for a ticket.
     *
     * @param int $ticket_id Ticket post ID.
     * @return array<int, array{id: int, name: string, url: string, type: string, size: string}>
     */
    public function get_ticket_attachments( int $ticket_id ): array {
        $ids = get_post_meta( $ticket_id, '_fm_attachment_ids', true );

        if ( empty( $ids ) || ! is_array( $ids ) ) {
            return [];
        }

        $attachments = [];

        foreach ( $ids as $id ) {
            $attachment = get_post( absint( $id ) );

            if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
                continue;
            }

            $filesize = filesize( get_attached_file( $attachment->ID ) ?: '' );

            $attachments[] = [
                'id'   => $attachment->ID,
                'name' => $attachment->post_title,
                'url'  => wp_get_attachment_url( $attachment->ID ),
                'type' => $attachment->post_mime_type,
                'size' => $filesize ? size_format( $filesize ) : '-',
            ];
        }

        return $attachments;
    }

    /**
     * Delete all attachments for a ticket.
     *
     * @param int $ticket_id Ticket post ID.
     * @return void
     */
    public function delete_ticket_attachments( int $ticket_id ): void {
        $ids = get_post_meta( $ticket_id, '_fm_attachment_ids', true );

        if ( empty( $ids ) || ! is_array( $ids ) ) {
            return;
        }

        foreach ( $ids as $id ) {
            wp_delete_attachment( absint( $id ), true );
        }

        delete_post_meta( $ticket_id, '_fm_attachment_ids' );
    }
}
