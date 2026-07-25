<?php
/**
 * Frontend Handler.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\PublicArea;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend Class.
 */
class Frontend {

    /**
     * Single instance.
     *
     * @var Frontend|null
     */
    private static ?Frontend $instance = null;

    /**
     * Get single instance.
     *
     * @return Frontend
     */
    public static function instance(): Frontend {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Placeholder for public-facing functionality.
    }
}
