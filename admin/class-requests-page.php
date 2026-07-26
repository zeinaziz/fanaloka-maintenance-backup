<?php
/**
 * Requests Page - WP_List_Table for tickets.
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

        echo '<div class="fm-page-wrap">';
        echo '<div class="fm-page-header">';
        echo '<h1 class="fm-page-title"><span class="dashicons dashicons-welcome-view-site" style="color:#2271b1"></span> ' . esc_html__( 'All Requests', 'fanaloka-maintenance' ) . '</h1>';
        echo '<button type="button" class="fm-btn fm-btn-primary fm-sync-btn" id="fm-sync-btn"><span class="dashicons dashicons-update"></span> ' . esc_html__( 'Sync Now', 'fanaloka-maintenance' ) . '</button>';
        echo '</div>';

        // Display notices.
        if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            echo '<div class="fm-notice fm-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Ticket deleted.', 'fanaloka-maintenance' ) . '</div>';
        }

        echo '<div class="fm-card" style="padding:0;">';
        $table = new Requests_List_Table();
        $table->process_bulk_action();
        $table->prepare_items();
        $table->display();
        echo '</div>';

        echo '</div>';

        // Shared design CSS.
        ?>
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
        .fm-card .tablenav .button { height: 32px; line-height: 30px; border-radius: 5px; font-size: 13px; }
        .fm-card .tablenav .bulkactions { display: flex; align-items: center; gap: 6px; }
        .fm-card .tablenav .bulkactions select { height: 32px; border-radius: 5px; font-size: 13px; }
        .fm-card .tablenav .bulkactions .button { height: 32px; line-height: 30px; border-radius: 5px; font-size: 13px; }
        .fm-card .tablenav .search-plugins { display: flex; align-items: center; gap: 6px; }
        .fm-card .tablenav .search-plugins input { height: 32px; border-radius: 5px; font-size: 13px; }
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
        .fm-notice { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .fm-notice-success { background: #e6f9e6; color: #00a32a; border: 1px solid #b8e6b8; }
        .fm-notice-success .dashicons { font-size: 18px; }
        </style>
        <?php
    }
}

/**
 * WP_List_Table for Maintenance Requests.
 */
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Requests_List_Table Class.
 */
class Requests_List_Table extends \WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct( [
            'singular' => 'request',
            'plural'   => 'requests',
            'ajax'     => false,
        ] );
    }

    /**
     * Get table columns.
     *
     * @return array<string, string> Column slug => label.
     */
    public function get_columns(): array {
        return [
            'cb'              => '<input type="checkbox" />',
            'ticket_number'   => __( 'Ticket', 'fanaloka-maintenance' ),
            'client'          => __( 'Client', 'fanaloka-maintenance' ),
            'subject'         => __( 'Subject', 'fanaloka-maintenance' ),
            'status'          => __( 'Status', 'fanaloka-maintenance' ),
            'priority'        => __( 'Priority', 'fanaloka-maintenance' ),
            'assigned_dev'    => __( 'Developer', 'fanaloka-maintenance' ),
            'date_created'    => __( 'Date', 'fanaloka-maintenance' ),
        ];
    }

    /**
     * Get sortable columns.
     *
     * @return array<string, array{0: string, 1: bool}> Sortable args.
     */
    public function get_sortable_columns(): array {
        return [
            'ticket_number' => [ 'ticket_number', false ],
            'status'        => [ 'status', false ],
            'priority'      => [ 'priority', false ],
            'date_created'  => [ 'date', true ],
        ];
    }

    /**
     * Prepare items.
     *
     * @return void
     */
    public function prepare_items(): void {
        $per_page = 20;
        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

        $args = [
            'per_page' => $per_page,
            'paged'    => $paged,
            'status'   => isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'priority' => isset( $_GET['filter_priority'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_priority'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
        ];

        // Handle sorting.
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $args['orderby'] = $orderby;
        $args['order']   = $order;

        $ticket_manager = new TicketManager();
        $result         = $ticket_manager->get_tickets( $args );

        $this->items = $result['tickets'];

        $this->set_pagination_args( [
            'total_items' => $result['total'],
            'per_page'    => $per_page,
            'total_pages' => $result['pages'],
        ] );

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    /**
     * Extra table navigation (filters).
     *
     * @param string $which Top or bottom.
     * @return void
     */
    public function extra_tablenav( $which ): void {
        if ( 'top' !== $which ) {
            return;
        }

        $current_status  = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $current_priority = isset( $_GET['filter_priority'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_priority'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        ?>
        <div class="alignleft actions">
            <select name="filter_status" id="filter-status">
                <option value=""><?php esc_html_e( 'All Statuses', 'fanaloka-maintenance' ); ?></option>
                <?php foreach ( TicketManager::STATUSES as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_status, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="filter_priority" id="filter-priority">
                <option value=""><?php esc_html_e( 'All Priorities', 'fanaloka-maintenance' ); ?></option>
                <?php foreach ( TicketManager::PRIORITIES as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_priority, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php submit_button( __( 'Filter', 'fanaloka-maintenance' ), '', 'filter_action', false ); ?>
        </div>
        <?php
    }

    /**
     * Render checkbox column.
     *
     * @param array<string, mixed> $item Ticket data.
     * @return string
     */
    public function column_cb( $item ): string {
        return sprintf( '<input type="checkbox" name="ticket[]" value="%d" />', $item['id'] );
    }

    /**
     * Render ticket number column.
     *
     * @param array<string, mixed> $item Ticket data.
     * @return string
     */
    public function column_ticket_number( $item ): string {
        $url = admin_url( 'admin.php?page=fm-requests&action=view&id=' . $item['id'] );
        return sprintf(
            '<a href="%s"><strong>%s</strong></a>',
            esc_url( $url ),
            esc_html( $item['full_number'] )
        );
    }

    /**
     * Render client column.
     *
     * @param array<string, mixed> $item Ticket data.
     * @return string
     */
    public function column_client( $item ): string {
        return sprintf(
            '<strong>%s</strong><br><span class="row-title-description">%s</span>',
            esc_html( $item['client_name'] ),
            esc_html( $item['client_email'] )
        );
    }

    /**
     * Render subject column.
     *
     * @param array<string, mixed> $item Ticket data.
     * @return string
     */
    public function column_subject( $item ): string {
        $url = admin_url( 'admin.php?page=fm-requests&action=view&id=' . $item['id'] );
        return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $item['subject'] ) );
    }

    /**
     * Render status column.
     *
     * @param array<string, mixed> $item Ticket data.
     * @return string
     */
    public function column_status( $item ): string {
        $colors = [
            'new'            => 'fm-badge-primary',
            'open'           => 'fm-badge-warning',
            'in-progress'    => 'fm-badge-success',
            'waiting-client' => 'fm-badge-warning',
            'completed'      => 'fm-badge-success',
            'cancelled'      => 'fm-badge-danger',
        ];

        $class = $colors[ $item['status'] ] ?? 'fm-badge-default';
        $label = $item['status_label'] ?? $item['status'];

        return sprintf(
            '<span class="fm-badge %s">%s</span>',
            esc_attr( $class ),
            esc_html( $label )
        );
    }

    /**
     * Render priority column.
     *
     * @param array<string, mixed> $item Ticket data.
     * @return string
     */
    public function column_priority( $item ): string {
        $colors = [
            'low'      => 'fm-badge-default',
            'medium'   => 'fm-badge-warning',
            'high'     => 'fm-badge-danger',
            'critical' => 'fm-badge-danger',
        ];

        $class = $colors[ $item['priority'] ] ?? 'fm-badge-default';
        $label = $item['priority_label'] ?? $item['priority'];

        return sprintf(
            '<span class="fm-badge %s">%s</span>',
            esc_attr( $class ),
            esc_html( $label )
        );
    }

    /**
     * Render developer column.
     *
     * @param array<string, mixed> $item Ticket data.
     * @return string
     */
    public function column_assigned_dev( $item ): string {
        return esc_html( $item['assigned_dev_name'] );
    }

    /**
     * Render date column.
     *
     * @param array<string, mixed> $item Ticket data.
     * @return string
     */
    public function column_date_created( $item ): string {
        return esc_html( $item['date_created'] );
    }

    /**
     * Default column handler.
     *
     * @param array<string, mixed> $item        Ticket data.
     * @param string               $column_name Column name.
     * @return string
     */
    public function column_default( $item, $column_name ): string {
        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
    }

    /**
     * Get bulk actions.
     *
     * @return array<string, string> Action slug => label.
     */
    public function get_bulk_actions(): array {
        return [
            'delete' => __( 'Delete', 'fanaloka-maintenance' ),
        ];
    }

    /**
     * Process bulk actions.
     *
     * @return void
     */
    public function process_bulk_action(): void {
        if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-requests' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $action = $this->current_action();

        if ( 'delete' === $action && isset( $_REQUEST['ticket'] ) ) {
            $ticket_manager = new TicketManager();
            $ids            = array_map( 'absint', (array) $_REQUEST['ticket'] );

            foreach ( $ids as $id ) {
                $ticket_manager->delete_ticket( $id );
            }

            wp_redirect( admin_url( 'admin.php?page=fm-requests&deleted=1' ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
            exit;
        }
    }
}
