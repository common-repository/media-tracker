<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="wrap broken-link-checker">
    <h1 class="wp-heading-inline dashicons-before dashicons-editor-unlink">
        <?php echo esc_html__( 'Broken Media Links Detection', 'media-tracker' ); ?>
    </h1>

    <?php $broken_link_checker->display_transient_cleaner_button(); ?>
    <?php $broken_link_checker->display(); ?>
</div>
