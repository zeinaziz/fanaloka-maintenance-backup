<?php
/**
 * Clients Page - AJAX-powered client list.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

use Fanaloka\Maintenance\Ticket\TicketManager;

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
        ?>
        <div class="fm-page-wrap">
            <div class="fm-page-header">
                <h1 class="fm-page-title">
                    <span class="fm-icon"><?php echo \Fanaloka\Maintenance\Icons::get( 'clients', '#646970' ); ?></span>
                    <?php esc_html_e( 'Clients', 'fanaloka-maintenance' ); ?>
                </h1>
                <div class="fm-clients-search">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" id="fm-client-search" placeholder="<?php esc_attr_e( 'Search clients...', 'fanaloka-maintenance' ); ?>" />
                </div>
            </div>

            <!-- Stats -->
            <div class="fm-stats-row" id="fm-client-stats">
                <div class="fm-stat-card">
                    <div class="fm-stat-icon" style="background:#e7f0fd;color:#2271b1;">
                        <span class="dashicons dashicons-businessperson"></span>
                    </div>
                    <div class="fm-stat-content">
                        <span class="fm-stat-number" id="fm-total-clients">-</span>
                        <span class="fm-stat-label"><?php esc_html_e( 'Total Clients', 'fanaloka-maintenance' ); ?></span>
                    </div>
                </div>
                <div class="fm-stat-card">
                    <div class="fm-stat-icon" style="background:#e6f9e6;color:#00a32a;">
                        <span class="dashicons dashicons-email-alt"></span>
                    </div>
                    <div class="fm-stat-content">
                        <span class="fm-stat-number" id="fm-total-client-tickets">-</span>
                        <span class="fm-stat-label"><?php esc_html_e( 'Total Tickets', 'fanaloka-maintenance' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Client List -->
            <div class="fm-card" id="fm-clients-card">
                <div class="fm-card-header">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'All Clients', 'fanaloka-maintenance' ); ?>
                </div>
                <div id="fm-clients-list">
                    <div style="text-align:center;padding:30px;color:#8c8f94;">
                        <span class="spinner is-active"></span> <?php esc_html_e( 'Loading...', 'fanaloka-maintenance' ); ?>
                    </div>
                </div>
            </div>

            <!-- Client Detail Modal -->
            <div id="fm-client-modal" class="fm-modal" style="display:none;">
                <div class="fm-modal-content">
                    <div class="fm-modal-header">
                        <h2 id="fm-modal-client-name"></h2>
                        <button type="button" class="fm-modal-close" id="fm-modal-close">&times;</button>
                    </div>
                    <div class="fm-modal-body">
                        <div class="fm-modal-info" id="fm-modal-client-info"></div>
                        <div class="fm-modal-tickets" id="fm-modal-client-tickets">
                            <div style="text-align:center;padding:20px;color:#8c8f94;">
                                <span class="spinner is-active"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .fm-page-wrap { max-width: 1200px; margin: 0 auto; padding: 0 0 40px; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-clients-search { display: flex; align-items: center; gap: 6px; background: #f0f0f1; border-radius: 6px; padding: 6px 12px; }
        .fm-clients-search .dashicons { color: #8c8f94; font-size: 16px; }
        .fm-clients-search input { border: none; background: transparent; font-size: 14px; outline: none; width: 200px; }
        .fm-stats-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
        .fm-stat-card { display: flex; align-items: center; gap: 14px; padding: 18px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-stat-icon { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 10px; }
        .fm-stat-icon .dashicons { font-size: 22px; width: 22px; height: 22px; }
        .fm-stat-number { font-size: 28px; font-weight: 700; line-height: 1.1; color: #1d2327; display: block; }
        .fm-stat-label { font-size: 13px; color: #646970; display: block; margin-top: 2px; }
        .fm-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-card-header { display: flex; align-items: center; gap: 8px; padding: 14px 18px; border-bottom: 1px solid #e2e4e7; font-size: 15px; font-weight: 600; color: #1d2327; background: #f9f9f9; }
        .fm-card-header .dashicons { color: #8c8f94; font-size: 18px; }
        .fm-client-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #f0f0f1; cursor: pointer; transition: background 0.15s; }
        .fm-client-row:last-child { border-bottom: none; }
        .fm-client-row:hover { background: #f9f9f9; }
        .fm-client-info { display: flex; align-items: center; gap: 12px; flex: 1; }
        .fm-client-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; font-weight: 700; flex-shrink: 0; }
        .fm-client-name { font-size: 15px; font-weight: 600; color: #1d2327; }
        .fm-client-email { font-size: 13px; color: #646970; margin-top: 2px; }
        .fm-client-stats { display: flex; gap: 16px; align-items: center; }
        .fm-client-stat { text-align: center; }
        .fm-client-stat-num { font-size: 18px; font-weight: 700; color: #1d2327; display: block; }
        .fm-client-stat-label { font-size: 11px; color: #8c8f94; text-transform: uppercase; letter-spacing: 0.5px; }
        .fm-client-arrow { color: #c3c4c7; font-size: 20px; }
        .fm-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; display: flex; align-items: center; justify-content: center; }
        .fm-modal-content { background: #fff; border-radius: 8px; width: 90%; max-width: 800px; max-height: 80vh; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
        .fm-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #e2e4e7; background: #f9f9f9; }
        .fm-modal-header h2 { margin: 0; font-size: 18px; }
        .fm-modal-close { background: none; border: none; font-size: 28px; cursor: pointer; color: #646970; padding: 0 4px; line-height: 1; }
        .fm-modal-close:hover { color: #1d2327; }
        .fm-modal-body { padding: 20px; overflow-y: auto; max-height: calc(80vh - 60px); }
        .fm-modal-info { display: flex; gap: 16px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f0f0f1; }
        .fm-modal-info-email { flex: 0 0 240px; }
        .fm-modal-info-main { flex: 0 0 240px; display: flex; flex-direction: column; gap: 8px; }
        .fm-modal-info-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; flex: 1; }
        .fm-modal-info-item { background: #f9f9f9; border: 1px solid #e2e4e7; border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #646970; min-width: 0; }
        .fm-modal-info-item strong { color: #1d2327; display: block; font-size: 16px; font-weight: 700; margin-bottom: 2px; overflow-wrap: anywhere; }
        .fm-modal-ticket-row { display: flex; align-items: center; gap: 12px; padding: 11px 10px; border-bottom: 1px solid #f0f0f1; text-decoration: none; transition: background 0.15s; }
        .fm-modal-ticket-row:last-child { border-bottom: none; }
        .fm-modal-ticket-row:hover { background: #f0f6fc; }
        .fm-modal-ticket-num { font-weight: 600; color: #2271b1; flex-shrink: 0; }
        .fm-modal-ticket-subject { flex: 1; margin: 0; font-size: 14px; color: #1d2327; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        @media (max-width: 782px) {
            .fm-modal-content { width: 95%; max-height: 85vh; }
            .fm-modal-body { padding: 14px; }
            .fm-modal-info { flex-direction: column; gap: 10px; }
            .fm-modal-info-email, .fm-modal-info-main { flex: none; width: 100%; }
            .fm-modal-ticket-subject { white-space: normal; }
            .fm-modal-ticket-row { flex-wrap: wrap; gap: 6px 12px; }
            .fm-modal-ticket-row .fm-badge { margin-left: auto; }
        }
        .fm-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #fff; line-height: 1.4; white-space: nowrap; }
        .fm-badge-primary { background: #2271b1; }
        .fm-badge-success { background: #00a32a; }
        .fm-badge-warning { background: #dba617; color: #1d2327; }
        .fm-badge-danger { background: #d63638; }
        .fm-badge-default { background: #8c8f94; }
        .fm-notice { position: fixed; top: 50px; right: 20px; padding: 12px 20px; border-radius: 6px; z-index: 100001; font-size: 14px; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: fm-slide-in 0.3s ease; max-width: 400px; }
        .fm-notice-success { background: #e6f9e6; color: #00a32a; border: 1px solid #b8e6b8; }
        .fm-notice-error { background: #fde7e7; color: #d63638; border: 1px solid #f5c6c6; }
        @keyframes fm-slide-in { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
        </style>

        <script>
        var fmClientsAjax = {
            url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
            nonce: '<?php echo esc_js( wp_create_nonce( 'fm_admin_nonce' ) ); ?>',
        };
        (function($) {
            var searchTimer = null;

            function loadClients( search ) {
                var $list = $('#fm-clients-list');
                $list.html('<div style="text-align:center;padding:30px;color:#8c8f94;"><span class="spinner is-active"></span> Loading...</div>');

                $.post(fmClientsAjax.url, {
                    action: 'fm_list_clients',
                    nonce: fmClientsAjax.nonce,
                    search: search || '',
                }, function(res) {
                    if (res.success) {
                        $list.html(res.data.html);
                        $('#fm-total-clients').text(res.data.total_clients);
                        $('#fm-total-client-tickets').text(res.data.total_tickets);
                    } else {
                        $list.html('<div style="text-align:center;padding:30px;color:#d63638;">Error loading clients.</div>');
                    }
                }).fail(function() {
                    $list.html('<div style="text-align:center;padding:30px;color:#d63638;">Request failed.</div>');
                });
            }

            // Search
            $('#fm-client-search').on('input', function() {
                var val = $(this).val();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    loadClients(val);
                }, 300);
            });

            // Click client row
            $(document).on('click', '.fm-client-row', function() {
                var email = $(this).data('email');
                var name = $(this).data('name');
                openClientModal(email, name);
            });

            // Close modal
            $('#fm-modal-close').on('click', function() {
                $('#fm-client-modal').hide();
            });
            $('#fm-client-modal').on('click', function(e) {
                if ($(e.target).is('#fm-client-modal')) {
                    $('#fm-client-modal').hide();
                }
            });

            function openClientModal(email, name) {
                $('#fm-modal-client-name').text(name);
                $('#fm-modal-client-info').html('<div class="fm-modal-info-item"><strong>' + email + '</strong>Email</div>');
                $('#fm-modal-client-tickets').html('<div style="text-align:center;padding:20px;"><span class="spinner is-active"></span></div>');
                $('#fm-client-modal').show();

                $.post(fmClientsAjax.url, {
                    action: 'fm_get_client_tickets',
                    nonce: fmClientsAjax.nonce,
                    email: email,
                }, function(res) {
                    if (res.success) {
                        $('#fm-modal-client-info').html(res.data.info_html);
                        $('#fm-modal-client-tickets').html(res.data.tickets_html);
                    } else {
                        $('#fm-modal-client-tickets').html('<div style="text-align:center;padding:20px;color:#d63638;">Error loading tickets.</div>');
                    }
                });
            }

            // Initial load
            loadClients();

        })(jQuery);
        </script>
        <?php
    }
}
