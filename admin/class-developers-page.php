<?php
/**
 * Developers Page.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DevelopersPage Class.
 */
class DevelopersPage {

    /**
     * Render the developers page.
     *
     * @return void
     */
    public function render(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Developers', 'fanaloka-maintenance' ) . '</h1>';
        echo '</div>';
    }
}
