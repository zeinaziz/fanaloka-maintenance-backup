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

        $devs = $this->get_developers();
        ?>
        <div class="fm-page-wrap">
            <div class="fm-page-header">
                <h1 class="fm-page-title">
                    <span class="dashicons dashicons-welcome-view-site" style="color:#2271b1"></span>
                    <?php esc_html_e( 'All Requests', 'fanaloka-maintenance' ); ?>
                </h1>
                <button type="button" class="fm-btn fm-btn-primary fm-sync-btn" id="fm-sync-btn">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Sync Now', 'fanaloka-maintenance' ); ?>
                </button>
            </div>

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
                        </select>
                        <input type="button" id="fm-bulk-apply" class="button action" value="<?php esc_attr_e( 'Apply', 'fanaloka-maintenance' ); ?>" />
                    </div>
                    <div class="alignleft actions">
                        <select id="fm-filter-status">
                            <option value=""><?php esc_html_e( 'All Statuses', 'fanaloka-maintenance' ); ?></option>
                            <?php foreach ( TicketManager::STATUSES as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="fm-filter-priority">
                            <option value=""><?php esc_html_e( 'All Priorities', 'fanaloka-maintenance' ); ?></option>
                            <?php foreach ( TicketManager::PRIORITIES as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="button" id="fm-filter-apply" class="button" value="<?php esc_attr_e( 'Filter', 'fanaloka-maintenance' ); ?>" />
                        <input type="button" id="fm-filter-clear" class="button" value="<?php esc_attr_e( 'Clear', 'fanaloka-maintenance' ); ?>" />
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
                            <th scope="col" class="manage-column column-ticket_number column-primary"><?php esc_html_e( 'Ticket', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-client"><?php esc_html_e( 'Client', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-subject"><?php esc_html_e( 'Subject', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-priority"><?php esc_html_e( 'Priority', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-assigned_dev"><?php esc_html_e( 'Developer', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-date_created"><?php esc_html_e( 'Date', 'fanaloka-maintenance' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="fm-requests-tbody">
                        <tr><td colspan="8" style="text-align:center;padding:30px;color:#8c8f94;">
                            <span class="spinner is-active"></span> <?php esc_html_e( 'Loading...', 'fanaloka-maintenance' ); ?>
                        </td></tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input id="cb-select-all-2" type="checkbox" />
                            </td>
                            <th scope="col" class="manage-column column-ticket_number column-primary"><?php esc_html_e( 'Ticket', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-client"><?php esc_html_e( 'Client', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-subject"><?php esc_html_e( 'Subject', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-priority"><?php esc_html_e( 'Priority', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-assigned_dev"><?php esc_html_e( 'Developer', 'fanaloka-maintenance' ); ?></th>
                            <th scope="col" class="manage-column column-date_created"><?php esc_html_e( 'Date', 'fanaloka-maintenance' ); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <style>
        .fm-page-wrap { max-width: 1400px; margin: 0 auto; padding: 0 0 40px; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; overflow: visible; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-card .wp-list-table { border: none; }
        .fm-card .tablenav { padding: 10px 12px; border-bottom: 1px solid #e2e4e7; background: #f9f9f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .fm-card .tablenav.top { border-radius: 8px 8px 0 0; }
        .fm-card .tablenav .alignleft.actions { display: flex; align-items: center; gap: 6px; }
        .fm-card .tablenav select { height: 32px; border-radius: 5px; border-color: #c3c4c7; font-size: 13px; }
        .fm-card .tablenav .button { height: 32px; line-height: 30px; border-radius: 5px; font-size: 13px; padding: 0 12px; }
        .fm-card .tablenav .bulkactions { display: flex; align-items: center; gap: 6px; }
        .fm-card .tablenav .tablenav-pages { margin: 0; }
        .widefat td, .widefat th { padding: 10px 14px; }
        .widefat thead th { background: #f9f9f9; border-bottom: 1px solid #e2e4e7; font-size: 13px; color: #646970; font-weight: 600; }
        .widefat td { border-bottom: 1px solid #f0f0f1; font-size: 14px; }
        .widefat tr:hover td { background: #f9f9f9; }
        .fm-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #fff; line-height: 1.4; white-space: nowrap; }
        .fm-badge-primary { background: #2271b1; }
        .fm-badge-success { background: #00a32a; }
        .fm-badge-warning { background: #dba617; color: #1d2327; }
        .fm-badge-danger { background: #d63638; }
        .fm-badge-default { background: #8c8f94; }
        .fm-sync-btn { white-space: nowrap; }
        .fm-notice { position: fixed; top: 50px; right: 20px; padding: 12px 20px; border-radius: 6px; z-index: 100000; font-size: 14px; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: fm-slide-in 0.3s ease; max-width: 400px; }
        .fm-notice-success { background: #e6f9e6; color: #00a32a; border: 1px solid #b8e6b8; }
        .fm-notice-error { background: #fde7e7; color: #d63638; border: 1px solid #f5c6c6; }
        @keyframes fm-slide-in { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
        </style>

        <script>
        var fmRequestsAjax = {
            url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
            nonce: '<?php echo esc_js( wp_create_nonce( 'fm_admin_nonce' ) ); ?>',
        };
        (function($) {
            var state = {
                paged: 1,
                status: '',
                priority: '',
                orderby: 'date',
                order: 'DESC',
                per_page: 20,
            };

            function loadTickets() {
                var $tbody = $('#fm-requests-tbody');
                $tbody.html('<tr><td colspan="8" style="text-align:center;padding:30px;color:#8c8f94;"><span class="spinner is-active"></span> Loading...</td></tr>');

                $.post(fmRequestsAjax.url, {
                    action: 'fm_list_requests',
                    nonce: fmRequestsAjax.nonce,
                    paged: state.paged,
                    status: state.status,
                    priority: state.priority,
                    orderby: state.orderby,
                    order: state.order,
                    per_page: state.per_page,
                }, function(res) {
                    if (res.success) {
                        $tbody.html(res.data.html);
                        $('#fm-displaying-num').text(res.data.displaying);
                        $('#fm-pagination').html(res.data.pagination);
                        $('#fm-requests-table thead .column-cb input').prop('checked', false);
                    } else {
                        $tbody.html('<tr><td colspan="8" style="text-align:center;padding:30px;color:#d63638;">Error loading tickets.</td></tr>');
                    }
                }).fail(function() {
                    $tbody.html('<tr><td colspan="8" style="text-align:center;padding:30px;color:#d63638;">Request failed.</td></tr>');
                });
            }

            // Filter apply
            $('#fm-filter-apply').on('click', function() {
                state.status = $('#fm-filter-status').val();
                state.priority = $('#fm-filter-priority').val();
                state.paged = 1;
                loadTickets();
            });

            // Filter clear
            $('#fm-filter-clear').on('click', function() {
                $('#fm-filter-status').val('');
                $('#fm-filter-priority').val('');
                state.status = '';
                state.priority = '';
                state.paged = 1;
                loadTickets();
            });

            // Auto-filter on select change
            $('#fm-filter-status, #fm-filter-priority').on('change', function() {
                state.status = $('#fm-filter-status').val();
                state.priority = $('#fm-filter-priority').val();
                state.paged = 1;
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
                $('#fm-requests-tbody input[name="ticket[]"]').prop('checked', checked);
            });

            // Bulk delete
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
                }
            });

            // Simple notice helper
            function showNotice(msg, type) {
                var icon = type === 'success' ? 'yes-alt' : 'warning';
                var $n = $('<div class="fm-notice fm-notice-' + type + '"><span class="dashicons dashicons-' + icon + '"></span>' + msg + '</div>');
                $('.fm-page-wrap').first().prepend($n);
                setTimeout(function(){ $n.fadeOut(300, function(){ $(this).remove(); }); }, 4000);
            }
            $(document).on('click', '#fm-requests-table thead th:not(.column-cb)', function() {
                var $th = $(this);
                var col = $th.attr('id') || $th.data('col');
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
                loadTickets();
            });

            // Initial load
            loadTickets();

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
}
