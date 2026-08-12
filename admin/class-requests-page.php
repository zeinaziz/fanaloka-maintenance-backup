<?php
/**
 * Requests Page - AJAX-powered ticket list.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

use Fanaloka\Maintenance\Ticket\TicketManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RequestsPage Class.
 */
class RequestsPage {

    /**
     * Render the requests page.
     *
     * @return void
     */
    public function render(): void {
        // Handle single ticket view.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_GET['action'] ) && 'view' === $_GET['action'] ) {
            $detail = new TicketDetailPage();
            $detail->render();
            return;
        }

        $devs     = $this->get_developers();
        $interval = max( 60, absint( get_option( 'fm_sync_interval', 5 ) ) * 60 );
        $counts   = \Fanaloka\Maintenance\Admin\Admin::get_filter_counts();
        ?>
        <div class="fm-page-wrap">
            <div class="fm-requests-layout">
            <div class="fm-requests-main">
            <div class="fm-card" id="fm-requests-card">
                <!-- Tablenav Top -->
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text">
                            <?php esc_html_e( 'Select bulk action', 'fanaloka-maintenance' ); ?>
                        </label>
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1"><?php esc_html_e( 'Bulk actions', 'fanaloka-maintenance' ); ?></option>
                            <option value="delete"><?php esc_html_e( 'Delete', 'fanaloka-maintenance' ); ?></option>
                            <option value="status"><?php esc_html_e( 'Change Status', 'fanaloka-maintenance' ); ?></option>
                            <option value="priority"><?php esc_html_e( 'Change Priority', 'fanaloka-maintenance' ); ?></option>
                            <option value="developer_id"><?php esc_html_e( 'Assign Developer', 'fanaloka-maintenance' ); ?></option>
                        </select>
                        <select name="bulk_value" id="bulk-value-selector" style="display:none;">
                            <option value=""><?php esc_html_e( 'Select...', 'fanaloka-maintenance' ); ?></option>
                        </select>
                        <input type="button" id="fm-bulk-apply" class="button action" value="<?php esc_attr_e( 'Apply', 'fanaloka-maintenance' ); ?>" />
                    </div>
                    <div class="tablenav-pages">
                        <span class="displaying-num" id="fm-displaying-num"></span>
                        <span class="pagination-links" id="fm-pagination"></span>
                    </div>
                    <br class="clear" />
                </div>

                <!-- Table -->
                <table class="wp-list-table widefat fixed striped table-view-list requests" id="fm-requests-table">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column">
                                <input id="cb-select-all" type="checkbox" />
                            </td>
                            <th scope="col" id="ticket_number" class="manage-column column-ticket_number column-primary sortable"><?php esc_html_e( 'Ticket', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-client"><?php esc_html_e( 'Client', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" id="status" class="manage-column column-status sortable"><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" id="priority" class="manage-column column-priority sortable"><?php esc_html_e( 'Priority', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-assigned_dev"><?php esc_html_e( 'Developer', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" id="date_created" class="manage-column column-date_created sortable"><?php esc_html_e( 'Date', 'fanaloka-maintenance' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="fm-requests-tbody">
                        <tr><td colspan="7" style="text-align:center;padding:30px;color:#8c8f94;">
                            <span class="spinner is-active"></span> <?php esc_html_e( 'Loading...', 'fanaloka-maintenance' ); ?>
                        </td></tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input id="cb-select-all-2" type="checkbox" />
                            </td>
                            <th scope="col" id="ticket_number-2" class="manage-column column-ticket_number column-primary sortable"><?php esc_html_e( 'Ticket', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-client"><?php esc_html_e( 'Client', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" id="status-2" class="manage-column column-status sortable"><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" id="priority-2" class="manage-column column-priority sortable"><?php esc_html_e( 'Priority', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-assigned_dev"><?php esc_html_e( 'Developer', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" id="date_created-2" class="manage-column column-date_created sortable"><?php esc_html_e( 'Date', 'fanaloka-maintenance' ); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            </div>

            <!-- Freshdesk-style filter sidebar -->
            <aside class="fm-filter-sidebar" id="fm-filter-sidebar">
                <div class="fm-filter-card">
                    <div class="fm-filter-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" id="fm-requests-search" placeholder="<?php esc_attr_e( 'Search tickets...', 'fanaloka-maintenance' ); ?>" />
                    </div>
                </div>

                <div class="fm-filter-card">
                    <div class="fm-filter-card-title">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php esc_html_e( 'Client', 'fanaloka-maintenance' ); ?>
                    </div>
                    <select id="fm-filter-client">
                        <option value=""><?php esc_html_e( 'All Clients', 'fanaloka-maintenance' ); ?></option>
                        <?php
                        $clients = $this->get_unique_clients();
                        foreach ( $clients as $email => $name ) :
                            ?>
                            <option value="<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fm-filter-card">
                    <div class="fm-filter-card-title">
                        <span class="dashicons dashicons-flag"></span>
                        <?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?>
                    </div>
                    <div class="fm-filter-options">
                        <?php
                        $status_total = array_sum( $counts['status'] );
                        $status_colors = [
                            'new'            => '#1a73e8',
                            'open'           => '#b06000',
                            'in-progress'    => '#0b8043',
                            'waiting-client' => '#9334e6',
                            'completed'      => '#188038',
                            'cancelled'      => '#c5221f',
                        ];
                        ?>
                        <button type="button" class="fm-filter-option" data-filter="status" data-value="">
                            <span class="fm-filter-option-label"><?php esc_html_e( 'All statuses', 'fanaloka-maintenance' ); ?></span>
                            <span class="fm-filter-count"><?php echo (int) $status_total; ?></span>
                            <span class="dashicons dashicons-check"></span>
                        </button>
                        <?php foreach ( TicketManager::STATUSES as $key => $label ) : ?>
                            <button type="button" class="fm-filter-option" data-filter="status" data-value="<?php echo esc_attr( $key ); ?>">
                                <span class="fm-filter-dot" style="background:<?php echo esc_attr( $status_colors[ $key ] ?? '#5f6368' ); ?>;"></span>
                                <span class="fm-filter-option-label"><?php echo esc_html( $label ); ?></span>
                                <span class="fm-filter-count"><?php echo (int) ( $counts['status'][ $key ] ?? 0 ); ?></span>
                                <span class="dashicons dashicons-check"></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="fm-filter-card">
                    <div class="fm-filter-card-title">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e( 'Priority', 'fanaloka-maintenance' ); ?>
                    </div>
                    <div class="fm-filter-options">
                        <?php
                        $priority_total = array_sum( $counts['priority'] );
                        $priority_colors = [
                            'low'      => '#5f6368',
                            'medium'   => '#b06000',
                            'high'     => '#d93025',
                            'critical' => '#c5221f',
                        ];
                        ?>
                        <button type="button" class="fm-filter-option" data-filter="priority" data-value="">
                            <span class="fm-filter-option-label"><?php esc_html_e( 'All priorities', 'fanaloka-maintenance' ); ?></span>
                            <span class="fm-filter-count"><?php echo (int) $priority_total; ?></span>
                            <span class="dashicons dashicons-check"></span>
                        </button>
                        <?php foreach ( TicketManager::PRIORITIES as $key => $label ) : ?>
                            <button type="button" class="fm-filter-option" data-filter="priority" data-value="<?php echo esc_attr( $key ); ?>">
                                <span class="fm-filter-dot" style="background:<?php echo esc_attr( $priority_colors[ $key ] ?? '#5f6368' ); ?>;"></span>
                                <span class="fm-filter-option-label"><?php echo esc_html( $label ); ?></span>
                                <span class="fm-filter-count"><?php echo (int) ( $counts['priority'][ $key ] ?? 0 ); ?></span>
                                <span class="dashicons dashicons-check"></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="fm-filter-card">
                    <div class="fm-filter-actions">
                        <input type="button" id="fm-filter-clear" class="button" value="<?php esc_attr_e( 'Clear Filters', 'fanaloka-maintenance' ); ?>" />
                        <input type="button" id="fm-order-reset" class="button" value="<?php esc_attr_e( 'Reset Order', 'fanaloka-maintenance' ); ?>" />
                    </div>
                </div>
            </aside>
            </div>
        </div>

        <style>
        .fm-page-wrap { max-width: 1400px; margin: 0 auto; padding: 0 24px 40px; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-card { background: #fff; border: 1px solid #e8eaed; border-radius: 12px; overflow: visible; box-shadow: 0 1px 2px rgba(60, 64, 67, 0.06), 0 2px 8px rgba(60, 64, 67, 0.04); }
        .fm-card .wp-list-table { border: none; }
        .fm-card .tablenav { padding: 14px 16px; border-bottom: 1px solid #e8eaed; background: #fff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .fm-card .tablenav.top { border-radius: 12px 12px 0 0; }
        .fm-card .tablenav .alignleft.actions { display: flex; align-items: center; gap: 6px; }
        .fm-card .tablenav select { height: 34px; border-radius: 8px; border-color: #dadce0; font-size: 13px; }
        .fm-card .tablenav .button { height: 34px; line-height: 32px; border-radius: 8px; font-size: 13px; padding: 0 14px; }
        .fm-card .tablenav .bulkactions { display: flex; align-items: center; gap: 6px; }
        .fm-card .tablenav .tablenav-pages { margin: 0; }
        .widefat td, .widefat th { padding: 10px 5px; }
        .widefat thead th { background: #fafbfc; border-bottom: 1px solid #e8eaed; font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; color: #5f6368; font-weight: 600; padding: 12px 14px !important; }
        .widefat tfoot th { background: #fafbfc; border-top: 1px solid #e8eaed; font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; color: #5f6368; font-weight: 600; padding: 12px 14px !important; }
        .widefat thead th.sortable, .widefat tfoot th.sortable { cursor: pointer; user-select: none; }
        .widefat thead th.sortable:hover, .widefat tfoot th.sortable:hover { color: #1a73e8; background: #f0f6fd; }
        .fm-sort-indicator { color: #1a73e8; font-size: 10px; }
        #fm-requests-table { table-layout: auto; border-collapse: separate; border-spacing: 0; }
        #fm-requests-table .column-cb { width: 40px; padding-right: 4px; }
        #fm-requests-table .column-status { width: 110px; }
        #fm-requests-table .column-priority { width: 90px; }
        #fm-requests-table .column-assigned_dev { width: 120px; }
        #fm-requests-table .column-date_created { width: 130px; white-space: nowrap; }
        #fm-requests-table .column-ticket_number { width: auto; max-width: 220px; min-width: fit-content; }
        #fm-requests-table .column-client { max-width: 260px; }
        #fm-requests-tbody td, #fm-requests-tbody td strong { font-weight: 400; }
        #fm-requests-table tbody td { padding: 12px 14px; border-bottom: 1px solid #f0f1f3; font-size: 13.5px; vertical-align: middle; }
        #fm-requests-table.striped tbody tr:nth-child(odd) td { background: #fff; }
        #fm-requests-table tbody tr:last-child td { border-bottom: none; }
        #fm-requests-table tbody tr { transition: background 0.12s ease; }
        #fm-requests-table tbody tr:hover td { background: #f5f8fd; }
        #fm-requests-table tbody tr:hover td.column-ticket_number strong { color: #1a73e8; }
        #fm-requests-table tbody tr.is-selected td { background: #eef4fd; }
        #fm-requests-table .check-column input { accent-color: #1a73e8; }
        #fm-requests-table .column-ticket_number strong { font-size: 14px; font-weight: 600; color: #1d2327; }
        /* Modern soft badges */
        #fm-requests-table .fm-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; line-height: 1.4; white-space: nowrap; }
        #fm-requests-table .fm-badge-primary { background: #e8f0fe; color: #1a73e8; }
        #fm-requests-table .fm-badge-success { background: #e6f4ea; color: #137333; }
        #fm-requests-table .fm-badge-warning { background: #fef7e0; color: #b06000; }
        #fm-requests-table .fm-badge-danger { background: #fce8e6; color: #c5221f; }
        #fm-requests-table .fm-badge-default { background: #f1f3f4; color: #5f6368; }
        #fm-requests-table .column-priority .fm-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        /* Client cell */
        .fm-client-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .fm-client-avatar { width: 32px; height: 32px; border-radius: 50%; color: #fff; font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .fm-client-meta { display: flex; flex-direction: column; min-width: 0; }
        .fm-client-name { font-size: 13px; font-weight: 500; color: #1d2327; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fm-client-email { font-size: 11px; color: #80868b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        /* Last reply pill */
        .fm-last-act { display: flex; align-items: center; gap: 5px; max-width: max-content; margin-bottom: 10px; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; line-height: 1.4; white-space: nowrap; }
        .fm-last-act-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .fm-last-act-client { background: #e6f4ea; color: #137333; }
        .fm-last-act-client .fm-last-act-dot { background: #137333; }
        .fm-last-act-admin { background: #e8f0fe; color: #1a73e8; }
        .fm-last-act-admin .fm-last-act-dot { background: #1a73e8; }
        #fm-requests-tbody .column-ticket_number strong { color: inherit; }
        @media (max-width: 782px) {
            .fm-card { overflow-x: auto; }
            .fm-page-header { flex-wrap: wrap; }
            .fm-requests-search { width: 100%; min-width: 0; box-sizing: border-box; }
            #fm-requests-table .column-ticket_number { min-width: 0; max-width: none; }
            #fm-requests-table { width: 100%; }
            #fm-requests-tbody td { width: auto; min-width: 0; }
        }
        .widefat td { border-bottom: 1px solid #f0f0f1; font-size: 14px; }
        .widefat tr:hover td { background: #f9f9f9; }
        #fm-requests-tbody tr:hover td { background: #eaf2fa; }
        #fm-requests-tbody tr:hover td.column-ticket_number strong { color: #2271b1; }
        .fm-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #fff; line-height: 1.4; white-space: nowrap; }
        .fm-badge-primary { background: #2271b1; }
        .fm-badge-success { background: #00a32a; }
        .fm-badge-warning { background: #dba617; color: #1d2327; }
        .fm-badge-danger { background: #d63638; }
        .fm-badge-default { background: #8c8f94; }
        .fm-sync-btn { white-space: nowrap; }
        .fm-auto-refresh-info { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #646970; }
        .fm-auto-refresh-info .dashicons { font-size: 16px; color: #00a32a; }
        .fm-requests-search { display: flex; align-items: center; gap: 6px; background: #f0f0f1; border-radius: 6px; padding: 6px 12px; }
        .fm-requests-search .dashicons { color: #8c8f94; font-size: 16px; }
        .fm-requests-search input { border: none; background: transparent; font-size: 14px; outline: none; width: 220px; max-width: 100%; }
        /* Freshdesk-style layout: scrollable table left, sticky filter sidebar right */
        .fm-requests-layout { display: flex; gap: 20px; align-items: stretch; }
        .fm-requests-main { flex: 1; min-width: 0; height: calc(100vh - 80px); min-height: 420px; overflow-y: auto; border-radius: 12px; padding-right: 6px; }
        .fm-requests-main::-webkit-scrollbar { width: 8px; }
        .fm-requests-main::-webkit-scrollbar-track { background: transparent; }
        .fm-requests-main::-webkit-scrollbar-thumb { background: #dadce0; border-radius: 4px; }
        .fm-requests-main::-webkit-scrollbar-thumb:hover { background: #c3c7cc; }
        .fm-card .tablenav.top { position: sticky; top: 0; z-index: 5; }
        .fm-filter-sidebar { width: 280px; flex-shrink: 0; position: sticky; top: 48px; align-self: flex-start; max-height: calc(100vh - 96px); overflow-y: auto; }
        .fm-filter-card { background: #fff; border: 1px solid #e8eaed; border-radius: 12px; box-shadow: 0 1px 2px rgba(60, 64, 67, 0.06); margin-bottom: 14px; overflow: hidden; }
        .fm-filter-card-title { display: flex; align-items: center; gap: 6px; padding: 11px 14px 9px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #5f6368; border-bottom: 1px solid #f0f1f3; }
        .fm-filter-card-title .dashicons { font-size: 14px; width: 14px; height: 14px; color: #80868b; }
        .fm-filter-search { padding: 12px; position: relative; }
        .fm-filter-search .dashicons { position: absolute; left: 22px; top: 50%; transform: translateY(-50%); color: #80868b; font-size: 16px; }
        .fm-filter-search input { width: 100%; height: 36px; border: 1px solid #dadce0; border-radius: 8px; padding: 0 12px 0 34px; font-size: 13px; box-shadow: none; }
        .fm-filter-search input:focus { border-color: #1a73e8; box-shadow: 0 0 0 1px #1a73e8; outline: none; }
        .fm-filter-card select { width: calc(100% - 24px); margin: 12px; height: 36px; border-radius: 8px; border-color: #dadce0; font-size: 13px; }
        .fm-filter-options { padding: 4px 0; }
        .fm-filter-option { display: flex; align-items: center; gap: 8px; width: 100%; padding: 8px 14px; border: 0; background: none; font-size: 13px; color: #1d2327; cursor: pointer; text-align: left; font-family: inherit; transition: background 0.1s ease; }
        .fm-filter-option:hover { background: #f5f8fd; }
        .fm-filter-option.active { background: #eef4fd; color: #1a73e8; font-weight: 500; }
        .fm-filter-option-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fm-filter-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .fm-filter-count { margin-left: auto; font-size: 11px; color: #80868b; min-width: 18px; text-align: right; }
        .fm-filter-option.active .fm-filter-count { color: #1a73e8; }
        .fm-filter-option .dashicons-check { font-size: 14px; width: 14px; height: 14px; color: #1a73e8; opacity: 0; margin-left: 2px; }
        .fm-filter-option.active .dashicons-check { opacity: 1; }
        .fm-filter-actions { display: flex; gap: 8px; padding: 12px; }
        .fm-filter-actions .button { flex: 1; height: 34px; line-height: 32px; border-radius: 8px; font-size: 12.5px; margin: 0; }
        @media (max-width: 1100px) {
            .fm-requests-layout { flex-direction: column; }
            .fm-requests-main { height: auto; max-height: 70vh; }
            .fm-filter-sidebar { position: static; width: 100%; max-height: none; overflow: visible; }
            .fm-card .tablenav.top { position: static; }
            .fm-filter-card select { width: calc(100% - 24px); }
        }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .fm-notice { position: fixed; top: 50px; right: 20px; padding: 12px 20px; border-radius: 6px; z-index: 100000; font-size: 14px; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: fm-slide-in 0.3s ease; max-width: 400px; }
        .fm-notice-success { background: #e6f9e6; color: #00a32a; border: 1px solid #b8e6b8; }
        .fm-notice-error { background: #fde7e7; color: #d63638; border: 1px solid #f5c6c6; }
        @keyframes fm-slide-in { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
        </style>

        <script>
        var fmRequestsAjax = {
            url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
            nonce: '<?php echo esc_js( wp_create_nonce( 'fm_admin_nonce' ) ); ?>',
            interval: <?php echo esc_js( $interval ); ?>,
        };
        (function($) {
            var STORAGE_KEY = 'fm_requests_filters';
            var state = {
                paged: 1,
                client: '',
                status: '',
                priority: '',
                search: '',
                orderby: '_fm_last_updated',
                order: 'DESC',
                per_page: 20,
            };
            var refreshTimer = null;
            var lastCount = 0;

            // Restore filters from localStorage.
            function restoreFilters() {
                // URL params take priority over localStorage.
                var urlParams = new URLSearchParams(window.location.search);
                var urlStatus = urlParams.get('status') || '';
                var urlClient = urlParams.get('client') || '';

                try {
                    var saved = JSON.parse(localStorage.getItem(STORAGE_KEY));
                    if (saved && typeof saved === 'object') {
                        state.client = urlClient || saved.client || '';
                        state.status = urlStatus || saved.status || '';
                        state.priority = saved.priority || '';
                        state.search = saved.search || '';
                        state.orderby = saved.orderby || '_fm_last_updated';
                        state.order = saved.order === 'ASC' ? 'ASC' : 'DESC';
                        $('#fm-filter-client').val(state.client);
                        if (state.search) {
                            $('#fm-requests-search').val(state.search);
                        }
                    }
                } catch(e) {}

                // If URL has params, apply them and clear URL.
                if (urlStatus || urlClient) {
                    if (urlStatus) state.status = urlStatus;
                    if (urlClient) state.client = urlClient;
                    $('#fm-filter-client').val(state.client);
                    // Clean URL without reload.
                    window.history.replaceState({}, '', window.location.pathname + '?page=fm-requests');
                }

                updateFilterOptions();
            }

            // Save filters to localStorage.
            function saveFilters() {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({
                        client: state.client,
                        status: state.status,
                        priority: state.priority,
                        search: state.search,
                        orderby: state.orderby,
                        order: state.order,
                    }));
                } catch(e) {}
            }

            function loadTickets() {
                var $tbody = $('#fm-requests-tbody');
                $tbody.html('<tr><td colspan="7" style="text-align:center;padding:30px;color:#8c8f94;"><span class="spinner is-active"></span> Loading...</td></tr>');

                $.post(fmRequestsAjax.url, {
                    action: 'fm_list_requests',
                    nonce: fmRequestsAjax.nonce,
                    paged: state.paged,
                    client: state.client,
                    status: state.status,
                    priority: state.priority,
                    search: state.search,
                    orderby: state.orderby,
                    order: state.order,
                    per_page: state.per_page,
                }, function(res) {
                    if (res.success) {
                        var newCount = res.data.total || 0;
                        $tbody.html(res.data.html);
                        $('#fm-displaying-num').text(res.data.displaying);
                        $('#fm-pagination').html(res.data.pagination);
                        $('#fm-requests-table thead .column-cb input').prop('checked', false);

                        // Notify if new tickets arrived
                        if (lastCount > 0 && newCount > lastCount) {
                            showNotice((newCount - lastCount) + ' new request(s) received!', 'success');
                        }
                        lastCount = newCount;
                    } else {
                        $tbody.html('<tr><td colspan="7" style="text-align:center;padding:30px;color:#d63638;">Error loading tickets.</td></tr>');
                    }
                }).fail(function() {
                    $tbody.html('<tr><td colspan="7" style="text-align:center;padding:30px;color:#d63638;">Request failed.</td></tr>');
                });
            }

            // Freshdesk-style sidebar filter options (status / priority).
            function updateFilterOptions() {
                $('.fm-filter-option').each(function() {
                    var $opt = $(this);
                    var filter = $opt.data('filter');
                    var value = $opt.data('value') || '';
                    $opt.toggleClass('active', state[filter] === value);
                });
            }

            $(document).on('click', '.fm-filter-option', function() {
                var $opt = $(this);
                var filter = $opt.data('filter');
                var value = $opt.data('value') || '';
                state[filter] = value;
                state.paged = 1;
                saveFilters();
                updateFilterOptions();
                loadTickets();
            });

            // Filter clear
            $('#fm-filter-clear').on('click', function() {
                var $btn = $(this);
                $('#fm-filter-client').val('');
                $('#fm-requests-search').val('');
                state.client = '';
                state.status = '';
                state.priority = '';
                state.search = '';
                state.paged = 1;
                updateFilterOptions();
                try { localStorage.removeItem(STORAGE_KEY); } catch(e) {}
                $btn.prop('disabled', true);
                loadTickets();
                setTimeout(function(){ $btn.prop('disabled', false); }, 2000);
            });

            // Reset sort order to default (last updated, DESC).
            $('#fm-order-reset').on('click', function() {
                var $btn = $(this);
                state.orderby = '_fm_last_updated';
                state.order = 'DESC';
                state.paged = 1;
                saveFilters();
                updateSortIndicators();
                $btn.prop('disabled', true);
                loadTickets();
                setTimeout(function(){ $btn.prop('disabled', false); }, 2000);
            });

            // Search with debounce
            var searchTimer = null;
            $('#fm-requests-search').on('input', function() {
                var val = $(this).val();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    state.search = val;
                    state.paged = 1;
                    saveFilters();
                    loadTickets();
                }, 400);
            });

            // Auto-filter on client select change
            $('#fm-filter-client').on('change', function() {
                state.client = $(this).val();
                state.paged = 1;
                saveFilters();
                loadTickets();
            });

            // Pagination
            $(document).on('click', '#fm-pagination a', function(e) {
                e.preventDefault();
                var page = $(this).data('page');
                if (page) {
                    state.paged = parseInt(page);
                    loadTickets();
                }
            });

            // Select all checkboxes
            $('#cb-select-all, #cb-select-all-2').on('change', function() {
                var checked = $(this).prop('checked');
                $('#fm-requests-tbody input[name="ticket[]"]').prop('checked', checked).closest('tr').toggleClass('is-selected', checked);
            });

            // Row highlight on individual check
            $(document).on('change', '#fm-requests-tbody input[name="ticket[]"]', function() {
                $(this).closest('tr').toggleClass('is-selected', this.checked);
            });

            // Bulk action — show/hide value selector.
            var bulkOptions = {
                status: <?php echo wp_json_encode( array_map( fn( $k, $v ) => [ 'value' => $k, 'label' => $v ], array_keys( TicketManager::STATUSES ), array_values( TicketManager::STATUSES ) ) ); ?>,
                priority: <?php echo wp_json_encode( array_map( fn( $k, $v ) => [ 'value' => $k, 'label' => $v ], array_keys( TicketManager::PRIORITIES ), array_values( TicketManager::PRIORITIES ) ) ); ?>,
                developer_id: <?php echo wp_json_encode( array_map( fn( $u ) => [ 'value' => (string) $u->ID, 'label' => $u->display_name ], get_users( [ 'role__in' => [ 'administrator', 'editor', 'author', 'contributor' ], 'fields' => [ 'ID', 'display_name' ] ] ) ) ); ?>,
            };

            $('#bulk-action-selector-top').on('change', function() {
                var action = $(this).val();
                var $val = $('#bulk-value-selector');
                if (bulkOptions[action]) {
                    var html = '<option value=""><?php echo esc_js( __( 'Select...', 'fanaloka-maintenance' ) ); ?></option>';
                    $.each(bulkOptions[action], function(i, opt) {
                        html += '<option value="' + opt.value + '">' + opt.label + '</option>';
                    });
                    $val.html(html).show();
                } else {
                    $val.hide().val('');
                }
            });

            // Bulk apply
            $('#fm-bulk-apply').on('click', function() {
                var action = $('#bulk-action-selector-top').val();
                if ('-1' === action) {
                    return;
                }

                var ids = [];
                $('#fm-requests-tbody input[name="ticket[]"]:checked').each(function() {
                    ids.push($(this).val());
                });

                if (0 === ids.length) {
                    return;
                }

                if ('delete' === action) {
                    if (!confirm('Are you sure you want to delete ' + ids.length + ' ticket(s)?')) {
                        return;
                    }

                    var $btn = $(this);
                    $btn.prop('disabled', true).val('Deleting...');

                    $.post(fmRequestsAjax.url, {
                        action: 'fm_bulk_delete_requests',
                        nonce: fmRequestsAjax.nonce,
                        ids: ids,
                    }, function(res) {
                        $btn.prop('disabled', false).val('Apply');
                        $('#bulk-action-selector-top').val('-1');
                        $('#bulk-value-selector').hide().val('');
                        if (res.success) {
                            showNotice(res.data.message, 'success');
                            loadTickets();
                        } else {
                            showNotice(res.data.message || 'Delete failed', 'error');
                        }
                    }).fail(function() {
                        $btn.prop('disabled', false).val('Apply');
                        showNotice('Request failed', 'error');
                    });
                } else {
                    // Bulk update (status, priority, developer_id).
                    var bulkVal = $('#bulk-value-selector').val();
                    if (!bulkVal) {
                        showNotice('Please select a value.', 'error');
                        return;
                    }

                    var labels = { status: 'Status', priority: 'Priority', developer_id: 'Developer' };
                    if (!confirm('Update ' + ids.length + ' ticket(s) ' + labels[action] + '?')) {
                        return;
                    }

                    var $btn = $(this);
                    $btn.prop('disabled', true).val('Updating...');

                    $.post(fmRequestsAjax.url, {
                        action: 'fm_bulk_update_requests',
                        nonce: fmRequestsAjax.nonce,
                        ids: ids,
                        bulk_field: action,
                        bulk_value: bulkVal,
                    }, function(res) {
                        $btn.prop('disabled', false).val('Apply');
                        $('#bulk-action-selector-top').val('-1');
                        $('#bulk-value-selector').hide().val('');
                        if (res.success) {
                            showNotice(res.data.message, 'success');
                            loadTickets();
                        } else {
                            showNotice(res.data.message || 'Update failed', 'error');
                        }
                    }).fail(function() {
                        $btn.prop('disabled', false).val('Apply');
                        showNotice('Request failed', 'error');
                    });
                }
            });

            // Simple notice helper
            function showNotice(msg, type) {
                var icon = type === 'success' ? 'yes-alt' : 'warning';
                var $n = $('<div class="fm-notice fm-notice-' + type + '"><span class="dashicons dashicons-' + icon + '"></span>' + msg + '</div>');
                $('.fm-page-wrap').first().prepend($n);
                setTimeout(function(){ $n.fadeOut(300, function(){ $(this).remove(); }); }, 4000);
            }
            $(document).on('click', '#fm-requests-table thead th.sortable, #fm-requests-table tfoot th.sortable', function() {
                var $th = $(this);
                var col = $th.attr('id') ? $th.attr('id').replace(/-2$/, '') : '';
                if (!col) return;

                var sortable = { ticket_number: 'ticket_number', status: 'status', priority: 'priority', date_created: 'date' };
                if (!sortable[col]) return;

                if (state.orderby === sortable[col]) {
                    state.order = state.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    state.orderby = sortable[col];
                    state.order = 'ASC';
                }
                state.paged = 1;
                updateSortIndicators();
                saveFilters();
                loadTickets();
            });

            function updateSortIndicators() {
                var cols = { ticket_number: 'ticket_number', status: 'status', priority: 'priority', date_created: 'date' };
                $('#fm-requests-table thead th.sortable, #fm-requests-table tfoot th.sortable').each(function() {
                    var $th = $(this);
                    var col = $th.attr('id') ? $th.attr('id').replace(/-2$/, '') : '';
                    var arrow = '';
                    if (cols[col] === state.orderby) {
                        arrow = state.order === 'ASC'
                            ? '<span class="fm-sort-indicator"> &#9650;</span>'
                            : '<span class="fm-sort-indicator"> &#9660;</span>';
                    }
                    $th.find('.fm-sort-indicator').remove();
                    if (arrow) {
                        $th.append(arrow);
                    }
                });
            }

            // Restore saved filters and initial load
            restoreFilters();
            loadTickets();
            updateSortIndicators();

            // Auto-refresh
            function startAutoRefresh() {
                if (refreshTimer) clearInterval(refreshTimer);
                refreshTimer = setInterval(function() {
                    loadTickets();
                }, fmRequestsAjax.interval * 1000);
            }
            startAutoRefresh();

        })(jQuery);
        </script>
        <?php
    }

    /**
     * Get developers list.
     *
     * @return array<int, string>
     */
    private function get_developers(): array {
        $users = get_users( [ 'role__in' => [ 'administrator', 'editor', 'author' ], 'fields' => [ 'ID', 'display_name' ] ] );
        $devs  = [];
        foreach ( $users as $user ) {
            $devs[ $user->ID ] = $user->display_name;
        }
        return $devs;
    }

    /**
     * Get unique clients from tickets.
     *
     * @return array<string, string> email => name.
     */
    private function get_unique_clients(): array {
        $query = new \WP_Query( [
            'post_type'      => 'maintenance_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        $clients = [];
        foreach ( $query->posts as $post_id ) {
            $email = get_post_meta( $post_id, '_fm_client_email', true );
            $name  = get_post_meta( $post_id, '_fm_client_name', true );
            if ( $email && ! isset( $clients[ $email ] ) ) {
                $clients[ $email ] = $name ?: $email;
            }
        }

        asort( $clients );
        return $clients;
    }
}
