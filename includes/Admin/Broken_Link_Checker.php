<?php

namespace Media_Tracker\Admin;

use WP_List_Table;

class Broken_Link_Checker extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'media',
            'plural'   => 'media',
            'ajax'     => false,
        ) );

        $this->prepare_items();
        $this->handle_replace_broken_link();
    }

    public function get_columns() {
        return array(
            'post_title'  => __( 'Source', 'media-tracker' ),
            'media_url'   => __( 'URL', 'media-tracker' ),
            'post_type'   => __( 'Type', 'media-tracker' ),
            'status'      => __( 'Status', 'media-tracker' ),
        );
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'post_title':
                $edit_link = $this->get_edit_link($item);
                $view_link = $this->get_view_link($item);

                $actions = [
                    'edit'       => '<a href="' . esc_url( $edit_link ) . '" target="_blank">' . __( 'Edit', 'media-tracker' ) . '</a>',
                    'view'       => '<a href="' . esc_url( $view_link ) . '" target="_blank">' . __( 'View', 'media-tracker' ) . '</a>',
                    'quick_edit' => '<a href="#" class="editinline quick-edit-item-' . $item['index'] . '">' . __( 'Edit Link', 'media-tracker' ) . '</a>',
                ];

                $output = '<strong><a href="' . esc_url( $edit_link ) . '">' . esc_html( $item['post_title'] ) . '</a></strong>';
                $output .= $this->row_actions( $actions );
                $output .= '<div class="quick-edit-form hidden quick-edit-item-' . $item['index'] . '">' . $this->render_edit_link_form( $item, $item['index'] ) . '</div>';

                return $output;

            case 'media_url':
                $url = esc_url( $item['media_url'] );
                $link_text = esc_html( $item['media_url'] );
                if ($item['link_type'] === 'image') {
                    $link_text .= ' (Image)';
                }
                return '<a href="' . $url . '" target="_blank">' . $link_text . '</a>';

            case 'post_type':
                return esc_html( $item['post_type'] );

            case 'status':
                return esc_html( $item['status'] );

            default:
                return print_r( $item, true );
        }
    }

    protected function get_edit_link($item) {
        switch ($item['post_type']) {
            case 'header':
            case 'footer':
                return admin_url('customize.php');
            case 'widget':
                return admin_url('widgets.php');
            default:
                return get_edit_post_link($item['post_id']);
        }
    }

    protected function get_view_link($item) {
        switch ($item['post_type']) {
            case 'header':
            case 'footer':
            case 'widget':
                return home_url();
            default:
                return get_permalink($item['post_id']);
        }
    }

    protected function render_edit_link_form( $item, $index ) {
        $output = '<form method="post" action="">
            <fieldset class="replace-broken-link inline-edit-col-left">
                <h4>' . __( 'Edit Link', 'media-tracker' ) . '</h4>
                <div>
                    <label>
                        <span class="input-text-wrap">
                            <input type="hidden" name="broken_link_post_id" value="' . esc_attr( $item['post_id'] ) . '" />
                            <input type="hidden" name="broken_link_old_url" value="' . esc_attr( $item['media_url'] ) . '" />
                            <input type="hidden" name="broken_link_post_type" value="' . esc_attr( $item['post_type'] ) . '" />
                            <input type="url" name="new_media_url" value="' . esc_attr( $item['media_url'] ) . '" required />
                        </span>
                    </label>
                </div>
            </fieldset>
            <div class="submit inline-edit-save">
                <button type="submit" class="button button-primary">' . __( 'Update', 'media-tracker' ) . '</button>
                <button type="button" class="button cancel">' . __( 'Cancel', 'media-tracker' ) . '</button>
            </div>
            ' . wp_nonce_field( 'replace_broken_link', 'replace_broken_link_nonce', true, false ) . '
        </form>';
        return $output;
    }

    public function prepare_items() {
        $media_links = get_transient( 'broken_links_scan_results' );

        if ( false === $media_links ) {
            $media_links = $this->scan_for_broken_media();
            set_transient( 'broken_links_scan_results', $media_links, HOUR_IN_SECONDS ); // HOUR_IN_SECONDS
        }

        $per_page = $this->get_items_per_page( 'broken_links_per_page', 20 );
        $current_page = $this->get_pagenum();
        $total_items = count( $media_links );
        $paged_items = array_slice( $media_links, ( $current_page - 1 ) * $per_page, $per_page );

        $index = 0;
        foreach ( $paged_items as &$item ) {
            $item['index'] = $index;
            $index++;
        }

        $this->items = $paged_items;

        $columns = $this->get_columns();
        $hidden = [];

        $this->_column_headers = [ $columns, $hidden ];

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }

    public function handle_replace_broken_link() {
        if (isset($_POST['replace_broken_link_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['replace_broken_link_nonce'])), 'replace_broken_link')) {
            if ( isset( $_POST['broken_link_post_id'] ) && isset( $_POST['broken_link_old_url'] ) && isset( $_POST['new_media_url'] ) && isset( $_POST['broken_link_post_type'] ) ) {
                $post_id = intval( sanitize_text_field( wp_unslash( $_POST['broken_link_post_id'] ) ) );
                $old_url = esc_url_raw( wp_unslash( $_POST['broken_link_old_url'] ) );
                $new_url = esc_url_raw( wp_unslash( $_POST['new_media_url'] ) );
                $post_type = sanitize_text_field( wp_unslash( $_POST['broken_link_post_type'] ) );

                switch ($post_type) {
                    case 'header':
                    case 'footer':
                        $this->update_theme_mod($old_url, $new_url);
                        break;
                    case 'widget':
                        $this->update_widget($post_id, $old_url, $new_url);
                        break;
                    default:
                        $this->update_post_content($post_id, $old_url, $new_url);
                        break;
                }

                delete_transient( 'broken_links_scan_results' );
            }
        }
    }

    private function update_theme_mod($old_url, $new_url) {
        $theme_mods = get_theme_mods();
        foreach ($theme_mods as $key => $value) {
            if (is_string($value) && strpos($value, $old_url) !== false) {
                $new_value = str_replace($old_url, $new_url, $value);
                set_theme_mod($key, $new_value);
            }
        }
    }

    private function update_widget($widget_id, $old_url, $new_url) {
        $widget_type = $this->get_widget_type_from_id($widget_id);
        $widget_instances = get_option('widget_' . $widget_type);

        if ($widget_instances) {
            $widget_number = $this->get_widget_number_from_id($widget_id);
            if (isset($widget_instances[$widget_number])) {
                $widget_data = $widget_instances[$widget_number];
                foreach ($widget_data as $key => $value) {
                    if (is_string($value) && strpos($value, $old_url) !== false) {
                        $widget_data[$key] = str_replace($old_url, $new_url, $value);
                    }
                }
                $widget_instances[$widget_number] = $widget_data;
                update_option('widget_' . $widget_type, $widget_instances);
            }
        }
    }

    private function update_post_content($post_id, $old_url, $new_url) {
        $post = get_post($post_id);
        if ($post) {
            $updated_content = $this->replace_url_in_content($post->post_content, $old_url, $new_url);
            $updated_post = array(
                'ID'           => $post_id,
                'post_content' => wp_kses_post($updated_content),
            );
            wp_update_post($updated_post);

            // Update Elementor data if present
            $this->update_elementor_data($post_id, $old_url, $new_url);
        }
    }

    public function scan_for_broken_media() {
        $links_status = [];

        // Scan posts and pages
        $links_status = array_merge($links_status, $this->scan_post_types());

        // Scan widgets (including footer)
        $links_status = array_merge($links_status, $this->scan_widgets());

        // Scan header and footer
        $links_status = array_merge($links_status, $this->scan_header_footer());

        return $links_status;
    }

    private function scan_post_types() {
        $links_status = [];
        $post_types = get_post_types(array('public' => true));

        foreach ($post_types as $post_type) {
            $posts = get_posts(array(
                'post_type'      => $post_type,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            ));

            foreach ($posts as $post) {
                $links_status = array_merge($links_status, $this->check_content_for_broken_links($post->post_content, $post->ID, $post->post_title, $post_type));
            }
        }

        return $links_status;
    }

    private function scan_widgets() {
        $links_status = [];
        $sidebars_widgets = wp_get_sidebars_widgets();

        foreach ($sidebars_widgets as $sidebar_id => $widgets) {
            foreach ($widgets as $widget_id) {
                $widget_type = $this->get_widget_type_from_id($widget_id);
                $widget_instances = get_option('widget_' . $widget_type);

                if ($widget_instances) {
                    $widget_number = $this->get_widget_number_from_id($widget_id);
                    if (isset($widget_instances[$widget_number])) {
                        $widget_data = $widget_instances[$widget_number];
                        $widget_content = $this->get_widget_content($widget_data);
                        $links_status = array_merge($links_status, $this->check_content_for_broken_links($widget_content, $widget_id, "Widget: $widget_type", 'widget'));
                    }
                }
            }
        }

        return $links_status;
    }

    private function scan_header_footer() {
        $links_status = [];
        $theme_mods = get_theme_mods();

        foreach ($theme_mods as $key => $value) {
            if (is_string($value)) {
                $links_status = array_merge($links_status, $this->check_content_for_broken_links($value, $key, "Theme Mod: $key", strpos($key, 'header') !== false ? 'header' : 'footer'));
            }
        }

        return $links_status;
    }

    private function get_widget_type_from_id($widget_id) {
        return preg_replace('/-[0-9]+$/', '', $widget_id);
    }

    private function get_widget_number_from_id($widget_id) {
        return (int) preg_replace('/^.*-([0-9]+)$/', '$1', $widget_id);
    }

    private function get_widget_content($widget_data) {
        $content = '';
        foreach ($widget_data as $key => $value) {
            if (is_string($value)) {
                $content .= ' ' . $value;
            }
        }
        return $content;
    }

    private function check_content_for_broken_links($content, $id, $title, $type) {
        $links_status = [];

        // Check for broken links (a href)
        preg_match_all('/<a[^>]+href=([\'"])(.*?)\1[^>]*>/i', $content, $matches);
        foreach ($matches[2] as $url) {
            if (empty($url) || $url === '#' || preg_match('/^#.+$/', $url)) {
                continue;
            }

            if ($this->is_broken_link($url)) {
                $links_status[] = array(
                    'post_id'    => $id,
                    'post_title' => $title,
                    'media_url'  => $url,
                    'status'     => 'Broken',
                    'post_type'  => $type,
                    'link_type'  => 'url'
                );
            }
        }

        // Check for broken images (img src)
        preg_match_all('/<img[^>]+src=([\'"])(.*?)\1[^>]*>/i', $content, $matches);
        foreach ($matches[2] as $url) {
            if (empty($url) || $url === '#' || preg_match('/^#.+$/', $url) || preg_match('/^data:image/', $url)) {
                continue;
            }

            if ($this->is_broken_image($url)) {
                $links_status[] = array(
                    'post_id'    => $id,
                    'post_title' => $title,
                    'media_url'  => $url,
                    'status'     => 'Broken',
                    'post_type'  => $type,
                    'link_type'  => 'image'
                );
            }
        }

        return $links_status;
    }

    public function is_broken_link( $url ) {
        $url = esc_url_raw( $url );

        // Ignore hash-only URLs
        if ( $url === '#' || preg_match('/^#.+$/', $url) ) {
            return false;
        }

        // Handle URLs with hash
        $url_parts = explode('#', $url);
        $url = $url_parts[0];

        if (strpos($url, home_url()) === 0) {
            $file_path = str_replace(home_url('/'), ABSPATH, $url);
            return !file_exists($file_path);
        }

        $response = wp_remote_head($url, array('timeout' => 5));
        return is_wp_error($response) || wp_remote_retrieve_response_code($response) === 404;
    }

    public function is_broken_image($url) {
        $url = esc_url_raw($url);

        if (strpos($url, home_url()) === 0) {
            // Local image
            $file_path = str_replace(home_url('/'), ABSPATH, $url);
            return !file_exists($file_path);
        } else {
            // External image
            $response = wp_remote_get($url, array('timeout' => 5));
            if (is_wp_error($response)) {
                return true;
            }
            $headers = wp_remote_retrieve_headers($response);
            $content_type = $headers['content-type'];
            return strpos($content_type, 'image/') !== 0;
        }
    }

    private function extract_urls_from_blocks( $blocks ) {
        $urls = '';
        foreach ( $blocks as $block ) {
            if ( ! empty( $block['attrs'] ) ) {
                $urls .= ' ' . wp_json_encode( $block['attrs'] );
            }
            if ( ! empty( $block['innerBlocks'] ) ) {
                $urls .= ' ' . $this->extract_urls_from_blocks( $block['innerBlocks'] );
            }
        }
        return $urls;
    }

    private function replace_url_in_content( $content, $old_url, $new_url ) {
        $updated_content = str_replace( $old_url, $new_url, $content );

        // Handle Gutenberg blocks
        if ( has_blocks( $content ) ) {
            $updated_content = $this->replace_url_in_blocks( parse_blocks( $updated_content ), $old_url, $new_url );
        }

        return $updated_content;
    }

    private function replace_url_in_blocks( $blocks, $old_url, $new_url ) {
        foreach ( $blocks as &$block ) {
            if ( ! empty( $block['attrs'] ) ) {
                $block['attrs'] = $this->replace_url_in_array( $block['attrs'], $old_url, $new_url );
            }
            if ( ! empty( $block['innerBlocks'] ) ) {
                $block['innerBlocks'] = $this->replace_url_in_blocks( $block['innerBlocks'], $old_url, $new_url );
            }
        }
        return serialize_blocks( $blocks );
    }

    private function replace_url_in_array( $array, $old_url, $new_url ) {
        foreach ( $array as $key => $value ) {
            if ( is_array( $value ) ) {
                $array[$key] = $this->replace_url_in_array( $value, $old_url, $new_url );
            } elseif ( is_string( $value ) ) {
                $array[$key] = str_replace( $old_url, $new_url, $value );
            }
        }
        return $array;
    }

    private function update_elementor_data( $post_id, $old_url, $new_url ) {
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        if ( $elementor_data ) {
            $updated_data = $this->replace_url_in_array( json_decode( $elementor_data, true ), $old_url, $new_url );
            update_post_meta( $post_id, '_elementor_data', wp_json_encode( $updated_data ) );
        }
    }

    public function display_transient_cleaner_button() {
        $nonce = wp_create_nonce('clear_broken_links_transient_nonce');
        echo '<button id="clear-broken-links-transient" class="button button-secondary" data-nonce="' . esc_attr($nonce) . '">Clear Transient Cache</button>';
        echo '<div id="success-message"></div>';
    }

    public function handle_clear_transient() {
        check_ajax_referer('clear_broken_links_transient_nonce', 'nonce');

        if (delete_transient('broken_links_scan_results')) {
            wp_send_json_success('Transient cleared successfully.');
        } else {
            wp_send_json_error('Failed to clear transient.');
        }
    }
}
