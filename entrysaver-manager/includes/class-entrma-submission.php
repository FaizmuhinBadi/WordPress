<?php
if (!defined('ABSPATH')) {
    exit;
}

class ENTRMA_Submission {

    /**
     * Initialize submission hook listener
     */
    public static function init() {
        add_action('wpcf7_before_send_mail', array(__CLASS__, 'save_submission'), 10, 3);
    }

    /**
     * Save submission data to database
     *
     * @param WPCF7_ContactForm $contact_form
     * @param bool &$abort
     * @param WPCF7_Submission $submission
     */
    public static function save_submission($contact_form, &$abort, $submission) {
        if (!$submission || !$contact_form) {
            return;
        }

        $form_id    = $contact_form->id();
        $form_title = $contact_form->title();

        $posted_data = $submission->get_posted_data();
        if (empty($posted_data)) {
            return;
        }

        // Filter system & internal CF7 tags
        $clean_fields = array();
        $skip_keys    = array('_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post', '_wpcf7_nonce');

        foreach ($posted_data as $key => $value) {
            if (in_array($key, $skip_keys, true) || strpos($key, '_wpcf7') === 0) {
                continue;
            }

            // Clean array or text values
            if (is_array($value)) {
                $clean_fields[$key] = array_map('sanitize_textarea_field', $value);
            } else {
                $clean_fields[$key] = sanitize_textarea_field($value);
            }
        }

        // Process uploaded files if available
        $uploaded_files = $submission->uploaded_files();
        $file_meta      = array();

        if (!empty($uploaded_files)) {
            $upload_dir = wp_upload_dir();
            foreach ($uploaded_files as $field_name => $file_paths) {
                $file_paths = (array) $file_paths;
                foreach ($file_paths as $file_path) {
                    if (file_exists($file_path)) {
                        $file_name = basename($file_path);
                        // Construct public URL if stored in wp-uploads
                        $relative_path = str_replace($upload_dir['basedir'], '', $file_path);
                        $file_url      = $upload_dir['baseurl'] . $relative_path;

                        $file_meta[] = array(
                            'field' => $field_name,
                            'name'  => $file_name,
                            'path'  => $file_path,
                            'url'   => esc_url_raw($file_url),
                        );
                    }
                }
            }
        }

        // Collect submission metadata
        $meta_data = array(
            // 'remote_ip'  => sanitize_text_field($submission->get_meta('remote_ip')),
            // 'user_agent' => sanitize_text_field($submission->get_meta('user_agent')),
            'url'        => esc_url_raw($submission->get_meta('url')),
            'timestamp'  => current_time('timestamp'),
            'files'      => $file_meta,
        );

        $settings = ENTRMA_Admin::get_settings();
        if ( ! empty( $settings['collect_user_data'] ) ) {
            $meta_data['remote_ip']  = sanitize_text_field( $submission->get_meta( 'remote_ip' ) );
            $meta_data['user_agent'] = sanitize_text_field( $submission->get_meta( 'user_agent' ) );
        }

        // Save to DB
        ENTRMA_DB::insert_entry($form_id, $form_title, $clean_fields, $meta_data);
    }
}
