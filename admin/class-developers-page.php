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
        ?>
        <div class="fm-page-wrap">
            <div class="fm-page-header">
                <h1 class="fm-page-title">
                    <span class="dashicons dashicons-admin-users" style="color:#2271b1"></span>
                    <?php esc_html_e( 'Developers', 'fanaloka-maintenance' ); ?>
                </h1>
            </div>

            <div class="fm-card">
                <div class="fm-card-body">
                    <p class="fm-no-data"><?php esc_html_e( 'Developer management coming soon.', 'fanaloka-maintenance' ); ?></p>
                </div>
            </div>
        </div>

        <style>
        .fm-page-wrap { max-width: 1200px; margin: 0 auto; padding: 0 0 40px; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-card-body { padding: 18px; }
        .fm-no-data { text-align: center; padding: 40px; color: #8c8f94; font-size: 14px; }
        </style>
        <?php
    }
}
