<?php

namespace Media_Tracker\Admin;

/**
 * The Menu handler class
 */
class Media_Usage {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_media_meta_box' ) );
    }

    /**
     * Add a custom meta box to the media file details page
     *
     * @return void
     */
    public function add_media_meta_box() {
        add_meta_box(
            'media-usage',
            __( 'Media Usage', 'media-tracker' ),
            array( $this, 'display_media_callback_func' ),
            'attachment',
            'normal',
            'high'
        );
    }

    /**
     * Display the meta box content
     *
     * @param WP_Post $post The post object.
     * @return void
     */
    public function display_media_callback_func( $post ) {

        global $wpdb;
        $attachment_id = $post->ID;

        // Standard query to find posts using this attachment as thumbnail
        $query = $wpdb->prepare("
            SELECT DISTINCT p.ID, p.post_title, p.post_type, pm.meta_key, pm.meta_value
            FROM $wpdb->posts p
            JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
            WHERE pm.meta_value = %d
            AND pm.meta_key = '_thumbnail_id'
            AND p.post_status = 'publish'
            ",
            $attachment_id
        );

        $results = $wpdb->get_results( $query );

        // Add logic to check for Elementor usage
        $elementor_query = "
            SELECT DISTINCT p.ID, p.post_title, p.post_type
            FROM $wpdb->posts p
            JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
            WHERE pm.meta_key = %s
            AND p.post_status = %s
        ";

        $elementor_query = $wpdb->prepare($elementor_query, '_elementor_data', 'publish');
        $elementor_posts = $wpdb->get_results($elementor_query);

        foreach ($elementor_posts as $elementor_post) {
            $elementor_data = get_post_meta($elementor_post->ID, '_elementor_data', true);

            if ($elementor_data) {
                $elementor_data = json_decode($elementor_data, true);

                if (is_array($elementor_data)) {
                    array_walk_recursive($elementor_data, function($item, $key) use ($attachment_id, &$results, $elementor_post) {
                        if ($key === 'id' && is_numeric($item) && $item == $attachment_id) {
                            $results[] = $elementor_post;
                        }
                    });
                }
            }
        }

        // Add logic to check for Gutenberg block usage
        $gutenberg_query = $wpdb->prepare("
            SELECT DISTINCT p.ID, p.post_title, p.post_type
            FROM $wpdb->posts p
            WHERE p.post_status = 'publish'
            AND p.post_content LIKE %s
        ", '%"id":'.$attachment_id.'%');

        $gutenberg_posts = $wpdb->get_results( $gutenberg_query );

        foreach ( $gutenberg_posts as $gutenberg_post ) {
            $results[] = $gutenberg_post;
        }

        echo '<div class="mediatracker-usage-table">';
        if ( $results ) {
            echo '
            <table>
                <thead>
                    <tr>
                        <th>' . esc_html__( '#', 'media-tracker' ) . '</th>
                        <th>' . esc_html__( 'Title', 'media-tracker' ) . '</th>
                        <th>' . esc_html__( 'Type', 'media-tracker' ) . '</th>
                        <th>' . esc_html__( 'Date Added', 'media-tracker' ) . '</th>
                        <th>' . esc_html__( 'Actions', 'media-tracker' ) . '</th>
                    </tr>
                </thead>
                <tbody>';
            $sn = 1;
            foreach ( $results as $result ) {
                $date_added            = get_the_date( 'Y-m-d H:i:s', $result->ID );
                $date_added_timestamp  = get_the_time( 'U', $result->ID );

                // Translators: This is a time difference string. The %s placeholder will be replaced by a human-readable time difference (e.g., "5 minutes ago").
                $date_added = sprintf( esc_html__('%s ago', 'media-tracker'), human_time_diff($date_added_timestamp, current_time('timestamp')));

                $admin_view_url        = get_edit_post_link( $result->ID );
                $frontend_view_url     = get_permalink( $result->ID );

                // Debugging
                if ( empty($admin_view_url) ) {
                    $admin_view_url = '#'; // Fallback or debugging message
                }
                if ( empty($frontend_view_url) ) {
                    $frontend_view_url = '#'; // Fallback or debugging message
                }

                echo '
                <tr>
                    <td>' . esc_html( $sn++ ) . '</td>
                    <td><a href="' . esc_url( $frontend_view_url ) . '" target="_blank">' . esc_html( $result->post_title ) . '</a></td>
                    <td>' . esc_html( $result->post_type ) . '</td>
                    <td>' . esc_html( $date_added ) . '</td>
                    <td>
                        <a href="' . esc_url( $admin_view_url ) . '" class="button button-primary" target="_blank">' . esc_html__( 'Admin View', 'media-tracker' ) . '</a>
                        <a href="' . esc_url( $frontend_view_url ) . '" class="button" target="_blank">' . esc_html__( 'Frontend View', 'media-tracker' ) . '</a>
                    </td>
                </tr>';
            }
            echo '
                </tbody>
            </table>';
        } else {
            echo '<p>' . esc_html__( 'No posts or pages found using this media file.', 'media-tracker' ) . '</p>';
        }
        echo '</div>';
    }
}
