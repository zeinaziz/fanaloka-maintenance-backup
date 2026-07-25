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

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Maintenance Requests', 'fanaloka-maintenance' ) . '</h1>';
        echo '<a href="#" class="page-title-action" id="fm-sync-btn">' . esc_html__( 'Sync Now', 'fanaloka-maintenance' ) . '</a>';
        echo '<hr class="wp-header-end">';

        // Display notices.
        if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ticket deleted.', 'fanaloka-maintenance' ) . '</p></div>';
        }

        $table = new Requests_List_Table();
        $table->prepare_items();
        $table->display();

        echo '</div>';
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
            'new'            => '#2271b1',
            'open'           => '#dba617',
            'in-progress'    => '#00a32a',
            'waiting-client' => '#996800',
            'completed'      => '#00a32a',
            'cancelled'      => '#d63638',
        ];

        $color  = $colors[ $item['status'] ] ?? '#646970';
        $label  = $item['status_label'] ?? $item['status'];

        return sprintf(
            '<span style="color:%s;font-weight:600;">%s</span>',
            esc_attr( $color ),
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
            'low'      => '#646970',
            'medium'   => '#dba617',
            'high'     => '#d63638',
            'critical' => '#d63638',
        ];

        $color = $colors[ $item['priority'] ] ?? '#646970';
        $label = $item['priority_label'] ?? $item['priority'];

        return sprintf(
            '<span style="color:%s;font-weight:600;">%s</span>',
            esc_attr( $color ),
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
