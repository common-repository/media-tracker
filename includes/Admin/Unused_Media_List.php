<?php

namespace Media_Tracker\Admin;

use WP_List_Table;

/**
 * Custom WP_List_Table implementation for managing unused media attachments.
 */
class Unused_Media_List extends WP_List_Table {

    /**
     * The search term used to filter media items.
     *
     * @var string
     */
    public $search;

    /**
     * Author ID for filtering unused media attachments.
     *
     * @var int
     */
    public $author_id;

    /**
     * Constructor method to initialize the list table.
     *
     * @param string   $search    The search term for filtering media.
     * @param int|null $author_id Optional. Author ID to filter media by author.
     */
    public function __construct( $search, $author_id = null ) {
        parent::__construct( array(
            'singular' => 'media',
            'plural'   => 'media',
            'ajax'     => false,
         ) );

        $this->search    = $search;
        $this->author_id = $author_id;
        $this->process_bulk_action();
    }

    /**
     * Define the columns that should be displayed in the table.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'          => '<input type="checkbox" />',
            'post_title'  => __( 'File', 'media-tracker' ),
            'post_author' => __( 'Author', 'media-tracker' ),
            'size'        => __( 'Size', 'media-tracker' ),
            'post_date'   => __( 'Date', 'media-tracker' ),
        );
    }

    /**
     * Render default column output.
     *
     * @param object $item        The current item being displayed.
     * @param string $column_name The name of the column.
     * @return string HTML output for the column.
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'post_title':
                $edit_link     = get_edit_post_link( $item->ID );
                $delete_link   = get_delete_post_link( $item->ID, '', true );
                $view_link     = wp_get_attachment_url( $item->ID );
                $thumbnail     = wp_get_attachment_image( $item->ID, [ 60, 60 ], true );
                $file_path     = get_attached_file( $item->ID );
                $file_name     = $item->post_title;
                $file_extension = $file_path ? pathinfo( $file_path, PATHINFO_EXTENSION ) : '';
                $full_file_name = $file_name . '.' . $file_extension;

                // Define row actions
                $actions = [
                    'edit'    => '<span class="edit"><a href="' . esc_url( $edit_link ) . '">' . __( 'Edit', 'media-tracker' ) . '</a></span>',
                    'view'    => '<span class="view"><a href="' . esc_url( $view_link ) . '">' . __( 'View', 'media-tracker' ) . '</a></span>',
                    'delete'  => '<span class="delete"><a href="' . esc_url( $delete_link ) . '" class="submitdelete">' . __( 'Delete Permanently', 'media-tracker' ) . '</a></span>'
                ];

                /* translators: %s: post title */
                $aria_label = sprintf( __( '“%s” (Edit)', 'media-tracker' ), $item->post_title );

                $output = '<strong class="has-media-icon">
                    <a href="' . esc_url( $edit_link ) . '" aria-label="' . esc_attr( $aria_label ) . '">
                        <span class="media-icon image-icon">' . $thumbnail . '</span>
                        ' . esc_html( $file_name ) . '
                    </a>
                </strong>
                <p class="filename">
                    <span class="screen-reader-text">' . __( 'File name:', 'media-tracker' ) . '</span>
                    ' . esc_html( $full_file_name ) . '
                </p>';

                // Append row actions
                $output .= $this->row_actions( $actions );
                return $output;
            case 'post_author':
                $author_name = get_the_author_meta( 'display_name', $item->post_author );
                $author_url  = add_query_arg(
                    [
                        'page'   => 'unused-media-cleaner',
                        'author' => $item->post_author,
                    ],
                    admin_url( 'upload.php' )
                );
                return '<a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a>';
            case 'size':
                $file_path = get_attached_file( $item->ID );
                if ( $file_path && file_exists( $file_path ) ) {
                    return size_format( filesize( $file_path ) );
                } else {
                    return '-'; // Return a placeholder or handle the case when the file is missing.
                }
            case 'post_date':
                return date_i18n( 'Y/m/d', strtotime( $item->post_date ) );
            default:
                return print_r( $item, true );
        }
    }

    /**
     * Define sortable columns for the table.
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return array(
            'post_title'  => array( 'post_title', false ),
            'post_author' => array( 'post_author', false ),
            'post_date'   => array( 'post_date', false ),
            'size'        => array( 'size', false ),
        );
    }

    /**
     * Get number of items to display per page.
     *
     * @param string $option  Name of the option to retrieve from user meta.
     * @param int    $default Default number of items per page.
     * @return int Number of items per page.
     */
    protected function get_items_per_page( $option, $default = 20 ) {
        $per_page = (int) get_user_meta( get_current_user_id(), $option, true );
        return empty( $per_page ) || $per_page < 1 ? $default : $per_page;
    }

    /**
     * Prepare the table's items for display.
     */
    public function prepare_items() {
        global $wpdb;

        // Display delete message
        $this->display_delete_message();

        // Retrieve search term from request.
        $search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

        // Determine pagination information.
        $per_page     = $this->get_items_per_page( 'unused_media_cleaner_per_page', 20 );
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        // Get all post IDs that use Elementor.
        $elementor_posts = $wpdb->get_col("
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_elementor_data'
        ");

        // Collect image IDs used by Elementor.
        $used_image_ids = [];

        foreach ( $elementor_posts as $post_id ) {
            $elementor_data = get_post_meta( $post_id, '_elementor_data', true );

            if ( $elementor_data ) {
                $elementor_data = json_decode( $elementor_data, true );

                if ( is_array( $elementor_data ) ) {
                    array_walk_recursive( $elementor_data, function( $item, $key ) use ( &$used_image_ids ) {
                        if ( $key === 'id' && is_numeric( $item ) ) {
                            $used_image_ids[] = $item;
                        }
                    } );
                }
            }
        }

        // Ensure unique IDs.
        $used_image_ids = array_unique( $used_image_ids );

        // Build the base query to retrieve unused attachments.
        $query = "
            SELECT p.ID, p.post_title, p.guid, p.post_author, p.post_date
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.meta_value AND pm.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->posts} pp ON pp.post_content LIKE CONCAT('%wp-image-', p.ID, '%')
            WHERE p.post_type = 'attachment'
            AND pm.meta_value IS NULL
            AND pp.ID IS NULL
        ";

        // Exclude used image IDs.
        if ( ! empty( $used_image_ids ) ) {
            $used_image_ids_placeholder = implode( ',', array_fill( 0, count( $used_image_ids ), '%d' ) );
            $query .= $wpdb->prepare(
                " AND p.ID NOT IN ($used_image_ids_placeholder)",
                $used_image_ids
            );
        }

        // Filter by author ID if provided.
        if ( $this->author_id ) {
            $query .= $wpdb->prepare( ' AND p.post_author = %d', $this->author_id );
        }

        // If there is a search query, add the search condition.
        if ( $search ) {
            $query .= $wpdb->prepare( ' AND p.post_title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
        }

        // Order by post_date in descending order.
        $query .= ' ORDER BY p.post_date DESC';

        // Calculate total items count.
        $total_items_query = $wpdb->prepare( "SELECT COUNT(*) FROM ($query) as total_query" );
        $total_items       = $wpdb->get_var( $total_items_query );

        // Add pagination to the main query.
        $query .= " LIMIT $offset, $per_page";

        // Retrieve items based on the constructed query.
        $this->items = $wpdb->get_results( $query );

        // Define column headers, hidden columns, and sortable columns.
        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        // Set pagination arguments for display.
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
         ) );
    }

    /**
     * Get total number of items, optionally filtered by search term.
     *
     * @param string $search Optional. Search term to filter items.
     * @return int Total number of items.
     */
    public function get_total_items( $search = '' ) {
        global $wpdb;

        // Get all post IDs that use Elementor.
        $elementor_posts = $wpdb->get_col("
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_elementor_data'
        ");

        // Collect image IDs used by Elementor.
        $used_image_ids = [];

        foreach ( $elementor_posts as $post_id ) {
            $elementor_data = get_post_meta( $post_id, '_elementor_data', true );

            if ( $elementor_data ) {
                $elementor_data = json_decode( $elementor_data, true );

                if ( is_array( $elementor_data ) ) {
                    array_walk_recursive( $elementor_data, function( $item, $key ) use ( &$used_image_ids ) {
                        if ( $key === 'id' && is_numeric( $item ) ) {
                            $used_image_ids[] = $item;
                        }
                    } );
                }
            }
        }

        // Ensure unique IDs.
        $used_image_ids = array_unique( $used_image_ids );

        // Build the base query to count unused attachments.
        $query = "
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.meta_value AND pm.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->posts} pp ON pp.post_content LIKE CONCAT('%wp-image-', p.ID, '%')
            WHERE p.post_type = 'attachment'
            AND pm.meta_value IS NULL
            AND pp.ID IS NULL
        ";

        // Exclude used image IDs.
        if ( ! empty( $used_image_ids ) ) {
            $used_image_ids_placeholder = implode( ',', array_fill( 0, count( $used_image_ids ), '%d' ) );
            $query .= $wpdb->prepare(
                " AND p.ID NOT IN ($used_image_ids_placeholder)",
                $used_image_ids
            );
        }

        // Filter by author ID if provided.
        if ( $this->author_id ) {
            $query .= $wpdb->prepare( ' AND p.post_author = %d', $this->author_id );
        }

        // If there is a search query, add the search condition.
        if ( $search ) {
            $query .= $wpdb->prepare( ' AND p.post_title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
        }

        // Get total items count.
        $total_items = $wpdb->get_var( $query );

        return $total_items;
    }

    /**
     * Render the checkbox column for bulk actions.
     *
     * @param object $item The current item being displayed.
     * @return string HTML output for the checkbox column.
     */
    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="media[]" value="%s" />', $item->ID
        );
    }

    /**
     * Define bulk actions for the table.
     *
     * @return array Associative array of bulk actions.
     */
    protected function get_bulk_actions() {
        return [
            'delete' => __( 'Delete permanently', 'media-tracker' ),
        ];
    }

    /**
     * Process the bulk action when a form is submitted.
     */
    protected function process_bulk_action() {
        if ( 'delete' === $this->current_action() ) {
            $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';

            if ( ! wp_verify_nonce( $nonce, 'bulk-media' ) ) {
                die( 'Security check failed' );
            } else {
                $media_ids = isset( $_REQUEST['media'] ) ? array_map( 'absint', (array) $_REQUEST['media'] ) : [];

                if ( ! empty( $media_ids ) ) {
                    foreach ( $media_ids as $media_id ) {
                        // Delete the attachment
                        wp_delete_attachment( $media_id, true );
                    }

                    // Set a transient to display a success message
                    $deleted_count = count( $media_ids );

                    /* translators: %d: number of deleted media files */
                    set_transient( 'unused_media_delete_message', sprintf( __( '%d media file(s) deleted successfully.', 'media-tracker' ), $deleted_count ), 30 );
                }
            }
        }
    }

    /**
     * Display the success message if a media file was deleted.
     */
    public function display_delete_message() {
        if ( $message = get_transient( 'unused_media_delete_message' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
            delete_transient( 'unused_media_delete_message' ); // Remove the message after it's displayed
        }
    }

    /**
     * Display the table.
     */
    public function display() {
        wp_nonce_field( 'bulk-media' );
        parent::display();
    }
}
