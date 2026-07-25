<?php
/**
 * Clients Page.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ClientsPage Class.
 */
class ClientsPage {

    /**
     * Render the clients page.
     *
     * @return void
     */
    public function render(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Clients', 'fanaloka-maintenance' ) . '</h1>';
        echo '</div>';
    }
}
