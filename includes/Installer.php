<?php

namespace Media_Tracker;

/**
 * Installer class
 */
class Installer {

    /**
     * Run the installer
     *
     * @since   1.0.0
     * @access  public
     * @param   none
     * @return  void
     */
    public function run() {
        $this->add_version();
    }

    /**
     * Add time and version on DB
     *
     * @since   1.0.0
     * @access  public
     * @param   none
     * @return  void
     */
    public function add_version() {
        $installed = get_option( 'media_tracker_installed' );

        if ( ! $installed ) {
            update_option( 'media_tracker_installed', time() );
        }

        update_option( 'media_tracker_version', MEDIA_TRACKER_VERSION );
    }

    public static function deactivate() {
        add_action( 'wp_ajax_mt_save_feedback', array( __CLASS__, 'save_feedback' ) );
        add_action( 'admin_footer', array( __CLASS__, 'feedback_modal_html' ) );
    }

    /**
     * AJAX handler to save feedback
     */
    public static function save_feedback() {
        check_ajax_referer('mediaTacker_nonce', 'nonce');

        $feedback = isset($_POST['feedback']) ? sanitize_textarea_field(wp_unslash($_POST['feedback'])) : '';

        if (!empty($feedback)) {
            $to = 'hello@thebitcraft.com';
            $subject = __( 'Media Tracker Plugin Feedback', 'media-tracker' );
            $message = "Feedback:\n\n" . $feedback;
            $headers = array('Content-Type: text/plain; charset=UTF-8');

            wp_mail($to, $subject, $message, $headers);

            wp_send_json_success();
        }
    }

    /**
     * Output HTML for feedback modal
     */
    public static function feedback_modal_html() { ?>
        <div id="mt-feedback-modal">
            <div class="mt-feedback-modal-content">
                <header class="mt-feedback-modal-header">
                    <span class="close">&times;</span>
                    <h3><?php esc_html_e( "If you have a moment, we'd love to know why you're deactivating the Media Tracker plugin!", "media-tracker" ); ?></h3>
                </header>

                <div class="mt-feedback-modal-body">
                    <textarea name="feedback" placeholder="<?php esc_html_e( 'Enter your feedback here...', 'media-tracker' ) ?>"></textarea>
                </div>

                <footer class="mt-feedback-modal-footer">
                    <button id="mt-skip-feedback"><?php esc_html_e( 'Skip & Deactivate', 'media-tracker' ); ?></button>
                    <button id="mt-submit-feedback"><?php esc_html_e( 'Submit & Deactivate', 'media-tracker' ); ?></button>
                </footer>
            </div>
        </div>
        <?php
    }
}
