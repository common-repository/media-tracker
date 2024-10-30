<?php

namespace Media_Tracker\Admin;

class Duplicate_Images {

    /**
     * Constructor to initialize the class and set up hooks.
     */
    public function __construct() {
        // Hooks to add filters and handle processing
        add_action('restrict_manage_posts', array($this, 'add_custom_media_filter'));
        add_action('pre_get_posts', array($this, 'filter_media_library_items'));
        add_action('media_tracker_batch_process', array($this, 'process_image_hashes_batch'));
        add_action('admin_footer-upload.php', array($this, 'add_custom_dropdown_to_media_library'));
        add_action('wp_ajax_get_duplicate_images', array($this, 'get_duplicate_images_via_ajax'));

        // Schedule the cron job for batch processing
        if (!wp_next_scheduled('media_tracker_batch_process')) {
            wp_schedule_event(time(), 'hourly', 'media_tracker_batch_process');
        }
    }

    /**
     * Adds a custom filter dropdown to the Media Library.
     */
    public function add_custom_media_filter() {
        $screen = get_current_screen();

        if ('upload' !== $screen->id) {
            return;
        }

        $filter_value = isset($_GET['media_duplicate_filter']) ? sanitize_text_field(wp_unslash($_GET['media_duplicate_filter'])) : '';
        ?>
        <select name="media_duplicate_filter" id="media-duplicate-filter">
            <option value=""><?php esc_html_e('Select Filter', 'media-tracker'); ?></option>
            <option value="duplicates" <?php selected($filter_value, 'duplicates'); ?>>
                <?php esc_html_e('Duplicate Images', 'media-tracker'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Filters Media Library items to show only duplicate images if the custom filter is set.
     *
     * @param WP_Query $query The current WP_Query instance.
     */
    public function filter_media_library_items($query) {
        global $pagenow;

        if ('upload.php' === $pagenow && isset($_GET['media_duplicate_filter']) && 'duplicates' === $_GET['media_duplicate_filter']) {
            $hashes = $this->get_image_hashes();
            $duplicate_ids = array();
            $ids_with_hashes = array();

            foreach ($hashes as $hash => $ids) {
                if (count($ids) > 1) {
                    $duplicate_ids = array_merge($duplicate_ids, $ids);
                    foreach ($ids as $id) {
                        $ids_with_hashes[$id] = $hash;
                    }
                }
            }

            if (!empty($duplicate_ids)) {
                usort($duplicate_ids, function($a, $b) use ($ids_with_hashes) {
                    return strcmp($ids_with_hashes[$a], $ids_with_hashes[$b]);
                });

                $query->set('post__in', $duplicate_ids);
                $query->set('orderby', 'post__in');
            } else {
                $query->set('post__in', array(0));
            }
        }
    }

    /**
     * Retrieves and caches perceptual image hashes for a batch of images.
     *
     * @param int $offset Offset for batch processing.
     * @param int $limit Number of images to process in each batch.
     * @return array Associative array of duplicate image hashes.
     */
    private function get_image_hashes($offset = 0, $limit = 300) {
        global $wpdb;

        $attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, guid FROM {$wpdb->posts}
            WHERE post_type = %s AND post_mime_type LIKE %s
            LIMIT %d, %d",
            'attachment',
            'image/%',
            $offset,
            $limit
        ));

        $hashes = array();
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);

            if (file_exists($file_path)) {
                $hash = get_post_meta($attachment->ID, '_media_tracker_hash', true);

                // Generate hash if not already cached
                if (empty($hash)) {
                    try {
                        $hash = $this->generate_image_hash($file_path);
                        update_post_meta($attachment->ID, '_media_tracker_hash', $hash);
                    } catch (Exception $e) {
                        error_log(sprintf('Error hashing image ID %d: %s', $attachment->ID, $e->getMessage()));
                        continue;
                    }
                }

                if (!isset($hashes[$hash])) {
                    $hashes[$hash] = array();
                }
                $hashes[$hash][] = $attachment->ID;
            }
        }

        // Only return hashes that have more than one image (duplicates)
        $duplicate_hashes = array_filter($hashes, function($ids) {
            return count($ids) > 1;
        });

        return $duplicate_hashes;
    }

    /**
     * Generate perceptual hash of an image using native PHP.
     *
     * @param string $file_path Path to the image file.
     * @return string The perceptual hash.
     */
    private function generate_image_hash($file_path) {
        global $wp_filesystem;

        // Initialize the WordPress filesystem.
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $file_content = $wp_filesystem->get_contents($file_path);
        $image = imagecreatefromstring($file_content);
        $resized_image = imagescale($image, 8, 8); // Scale to 8x8
        $gray_image = imagecreatetruecolor(8, 8);

        // Convert to grayscale
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($resized_image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray = (int)(($r + $g + $b) / 3);
                $gray_color = imagecolorallocate($gray_image, $gray, $gray, $gray);
                imagesetpixel($gray_image, $x, $y, $gray_color);
            }
        }

        // Calculate the average grayscale value
        $sum = 0;
        $pixels = array();
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $gray = imagecolorat($gray_image, $x, $y) & 0xFF;
                $pixels[] = $gray;
                $sum += $gray;
            }
        }
        $average = $sum / 64;

        // Generate hash based on average value
        $hash = '';
        foreach ($pixels as $pixel) {
            $hash .= ($pixel > $average) ? '1' : '0';
        }

        return $hash;
    }

    /**
     * Batch processes image hashes and stores them in post meta.
     */
    public function process_image_hashes_batch() {
        $limit = 300;
        $offset = get_option('media_tracker_offset', 0);

        // Process image hashes in batches
        $hashes = $this->get_image_hashes($offset, $limit);

        if (empty($hashes)) {
            // If no more images, reset the offset
            delete_option('media_tracker_offset');
        } else {
            // Update the offset for the next batch
            update_option('media_tracker_offset', $offset + $limit);
        }
    }

    /**
     * Add custom dropdown to media library.
     */
    public function add_custom_dropdown_to_media_library() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Add the dropdown after the existing grid/list view buttons
                var dropdownHTML = `
                    <select id="custom-filter-dropdown">
                        <option value=""><?php esc_html_e('Select Filter', 'media-tracker'); ?></option>
                        <option value="duplicate-images"><?php esc_html_e('Duplicate Images', 'media-tracker'); ?></option>
                    </select>`;

                // Append it to the media toolbar
                $('#media-attachment-date-filters').after(dropdownHTML);

                // Trigger AJAX when dropdown is changed
                $('#custom-filter-dropdown').change(function() {
                    var filterValue = $(this).val();

                    if (filterValue === 'duplicate-images') {
                        $.ajax({
                            url: ajaxurl, // Ensure ajaxurl is defined
                            type: 'POST',
                            data: {
                                action: 'get_duplicate_images',
                                security: '<?php echo esc_js( wp_create_nonce( 'media_tracker_nonce' ) ); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Remove existing attachments and display duplicate images
                                    $('.attachments').html(response.data.html);
                                } else {
                                    alert('<?php esc_html_e('No duplicate images found', 'media-tracker'); ?>');
                                }
                            },
                            error: function() {
                                alert('<?php esc_html_e('Error fetching duplicate images', 'media-tracker'); ?>');
                            }
                        });
                    } else {
                        location.reload(); // For other filters, reload the page
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX handler to get duplicate images.
     */
    public function get_duplicate_images_via_ajax() {
        check_ajax_referer('media_tracker_nonce', 'security');

        $hashes = $this->get_image_hashes();
        $duplicate_ids = array();
        $ids_with_hashes = array();

        // Loop through image hashes to find duplicates
        foreach ($hashes as $hash => $ids) {
            if (count($ids) > 1) { // Ensure only duplicates are processed
                $duplicate_ids = array_merge($duplicate_ids, $ids);
                foreach ($ids as $id) {
                    $ids_with_hashes[$id] = $hash;
                }
            }
        }

        if (!empty($duplicate_ids)) {
            // Sort duplicate IDs based on their image hashes
            usort($duplicate_ids, function($a, $b) use ($ids_with_hashes) {
                return strcmp($ids_with_hashes[$a], $ids_with_hashes[$b]);
            });

            // Fetch the actual image posts from the sorted duplicate IDs
            $duplicates = array();
            foreach ($duplicate_ids as $id) {
                $duplicates[] = get_post($id);
            }

            ob_start();
            foreach ($duplicates as $image) {
                echo '<li tabindex="0" role="checkbox" aria-label="' . esc_attr($image->post_title) . '" aria-checked="false" data-id="' . esc_attr($image->ID) . '" class="attachment save-ready">';
                    echo '<div class="attachment-preview js--select-attachment type-image subtype-png landscape">';
                        echo '<a href="' . esc_url(get_edit_post_link($image->ID)) . '">';
                            echo '<div class="thumbnail">';
                                echo '<div class="centered">';
                                    echo '<img src="' . esc_url(wp_get_attachment_thumb_url($image->ID)) . '" alt="' . esc_attr($image->post_title) . '">';
                                echo '</div>';
                            echo '</div>';
                        echo '</a>';
                    echo '</div>';
                echo '</li>';
            }
            $html = ob_get_clean();
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_error();
        }
    }
}
