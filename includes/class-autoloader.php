<?php
/**
 * PSR-4 Autoloader for Fanaloka Maintenance.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Autoloader Class.
 */
class Autoloader {

    /**
     * Namespace prefix.
     *
     * @var string
     */
    private const NAMESPACE_PREFIX = 'Fanaloka\\Maintenance\\';

    /**
     * Base directory for the namespace.
     *
     * @var string
     */
    private const BASE_DIR = FM_PLUGIN_DIR;

    /**
     * Class map for direct file mapping.
     *
     * @var array<string, string>
     */
    private const CLASS_MAP = [
        'Fanaloka\Maintenance\Admin\Admin'                => 'admin/class-admin.php',
        'Fanaloka\Maintenance\Admin\SettingsPage'         => 'admin/class-settings-page.php',
        'Fanaloka\Maintenance\Admin\DashboardPage'        => 'admin/class-dashboard-page.php',
        'Fanaloka\Maintenance\Admin\RequestsPage'         => 'admin/class-requests-page.php',
        'Fanaloka\Maintenance\Admin\ClientsPage'          => 'admin/class-clients-page.php',
        'Fanaloka\Maintenance\Admin\DevelopersPage'       => 'admin/class-developers-page.php',
        'Fanaloka\Maintenance\Admin\ReportsPage'          => 'admin/class-reports-page.php',
        'Fanaloka\Maintenance\Admin\TicketDetailPage'     => 'admin/class-ticket-detail-page.php',
        'Fanaloka\Maintenance\Cron\CronManager'           => 'includes/class-cron-manager.php',
        'Fanaloka\Maintenance\IMAP\IMAPReader'            => 'includes/class-imap-reader.php',
        'Fanaloka\Maintenance\Email\EmailParser'          => 'includes/class-email-parser.php',
        'Fanaloka\Maintenance\Ticket\TicketManager'       => 'includes/class-ticket-manager.php',
        'Fanaloka\Maintenance\Ticket\ConversationManager' => 'includes/class-conversation-manager.php',
        'Fanaloka\Maintenance\Notification\NotificationManager' => 'includes/class-notification-manager.php',
        'Fanaloka\Maintenance\Attachment\AttachmentManager'     => 'includes/class-attachment-manager.php',
        'Fanaloka\Maintenance\Report\ReportManager'       => 'includes/class-report-manager.php',
        'Fanaloka\Maintenance\REST\RESTController'        => 'includes/class-rest-controller.php',
        'Fanaloka\Maintenance\Logger\Logger'              => 'includes/class-logger.php',
        'Fanaloka\Maintenance\PublicArea\Frontend'        => 'public/class-frontend.php',
        'Fanaloka\Maintenance\Database'                   => 'includes/class-database.php',
    ];

    /**
     * Initialize autoloader.
     *
     * @return void
     */
    public static function init(): void {
        spl_autoload_register( [ new self(), 'autoload' ] );
    }

    /**
     * Autoload a class.
     *
     * @param string $class The class name.
     * @return void
     */
    public function autoload( string $class ): void {
        // Check if class is within our namespace.
        if ( 0 !== strpos( $class, self::NAMESPACE_PREFIX ) ) {
            return;
        }

        // Check class map first.
        if ( isset( self::CLASS_MAP[ $class ] ) ) {
            $file = self::BASE_DIR . self::CLASS_MAP[ $class ];
            if ( file_exists( $file ) ) {
                require_once $file;
            }
            return;
        }

        // Fallback to PSR-4 convention.
        $relative_class = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
        $file           = self::BASE_DIR . 'includes/class-' . strtolower(
            str_replace( [ '\\', '_' ], [ '/', '-' ], $relative_class )
        ) . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
