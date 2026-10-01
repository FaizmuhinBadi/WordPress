<?php
if (!defined('ABSPATH')) {
    exit;
}

class CF7EM_Admin {

    /**
     * Initialize admin component
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'), 99);

        // Register setting
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        
        // AJAX actions
        add_action('wp_ajax_cf7em_get_entry', array(__CLASS__, 'ajax_get_entry'));
        add_action('wp_ajax_cf7em_update_entry', array(__CLASS__, 'ajax_update_entry'));
        add_action('wp_ajax_cf7em_delete_entry', array(__CLASS__, 'ajax_delete_entry'));
        add_action('wp_ajax_cf7em_save_column_settings', array(__CLASS__, 'ajax_save_column_settings'));
        
        // Export CSV action
        add_action('admin_init', array(__CLASS__, 'handle_csv_export'));
    }

    /**
     * Register Admin Menu
     */
    public static function register_menu() {
        add_menu_page(
            __('EntrySaver', 'entrysaver-manager'),
            __('EntrySaver', 'entrysaver-manager'),
            'manage_options',
            'entrysaver',
            array(__CLASS__, 'render_admin_page'),
            'dashicons-feedback',
            30
        );

        add_submenu_page(
            'entrysaver',
            __('Settings', 'entrysaver-manager'),
            __('Settings', 'entrysaver-manager'),
            'manage_options',
            'entrysaver-settings',
            array(__CLASS__, 'setting_admin_page'),
            30
        );
    }

    /**
     * Enqueue Admin CSS & JavaScript
     *
     * @param string $hook
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'entrysaver') === false) {
            return;
        }

        global $wp_styles;

        wp_enqueue_style(
            'cf7em-admin-css',
            CF7EM_URL . 'assets/css/admin-style.css',
            array(),
            CF7EM_VERSION
        );

        wp_enqueue_script(
            'cf7em-admin-js',
            CF7EM_URL . 'assets/js/admin-script.js',
            array('jquery'),
            CF7EM_VERSION,
            true
        );

        wp_localize_script('cf7em-admin-js', 'cf7emData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('cf7em_admin_nonce'),
            'i18n'    => array(
                'confirmDelete'     => __('Are you sure you want to delete this entry?', 'entrysaver-manager'),
                'confirmBulkDelete' => __('Are you sure you want to delete selected entries?', 'entrysaver-manager'),
                'saving'            => __('Saving...', 'entrysaver-manager'),
                'saved'             => __('Settings Saved!', 'entrysaver-manager'),
                'error'             => __('An error occurred. Please try again.', 'entrysaver-manager'),
            ),
        ));


        if ( empty( $wp_styles->queue ) ) {
            return;
        }

        foreach ( $wp_styles->queue as $handle ) {

            if ( empty( $wp_styles->registered[ $handle ] ) ) {
                continue;
            }

            $style = $wp_styles->registered[ $handle ];

            if ( empty( $style->src ) ) {
                continue;
            }

            $src = $style->src;

            // Keep WordPress core styles.
            if (
                false !== strpos( $src, '/wp-admin/' ) ||
                false !== strpos( $src, '/wp-includes/' )
            ) {
                continue;
            }

            // Keep plugin styles.
            if ( false !== strpos( $src, '/wp-content/plugins/entrysaver-manager/' ) ) {
                continue;
            }

            // Remove third-party plugin/theme styles.
            if ( false !== strpos( $src, '/wp-content/' ) ) {
                wp_dequeue_style( $handle );
            }
        }
    }

    /**
     * Render main admin router
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'entrysaver-manager'));
        }

        $form_id = filter_input( INPUT_GET, 'form_id', FILTER_VALIDATE_INT );

        if ( false === $form_id || null === $form_id ) {
            $form_id = 0;
        }

        if ($form_id > 0) {
            self::render_entries_list_page($form_id);
        } else {
            self::render_forms_dashboard_page();
        }
    }

    /**
     * Register plugin settings.
     */
    public static function register_settings() {

        register_setting(
            'cf7_settings_group',
            'cf7_settings',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                'default'           => self::get_defaults(),
            )
        );

        add_settings_section(
            'cf7_general_section',
            __( 'General Settings', 'entrysaver-manager' ),
            '__return_false',
            'cf7_settings'
        );

        add_settings_field(
            'cf7_show_zero_entry_forms',
            __( 'Show forms with 0 entries', 'entrysaver-manager' ),
            array( __CLASS__, 'render_show_zero_entry_field' ),
            'cf7_settings',
            'cf7_general_section'
        );

        add_settings_section(
            'cf7_user_data_section',
            __( 'User Data Collection', 'entrysaver-manager' ),
            '__return_false',
            'cf7_settings'
        );

        add_settings_field(
            'cf7_collect_user_data',
            __( 'Get user IP address and browser data', 'entrysaver-manager' ),
            array( __CLASS__, 'render_collect_user_data_field' ),
            'cf7_settings',
            'cf7_user_data_section'
        );
    }

    /**
     * Sanitize settings.
     */
    public static function sanitize_settings( $input ) {

        if ( ! is_array( $input ) ) {
            $input = array();
        }

        return array(
            'show_zero_entry_forms' => ! empty( $input['show_zero_entry_forms'] ) ? 1 : 0,
            'collect_user_data'     => ! empty( $input['collect_user_data'] ) ? 1 : 0,
        );
    }

    public static function get_defaults() {
        return array(
            'show_zero_entry_forms' => 1,
            'collect_user_data'     => 0,
        );
    }

    public static function get_settings() {
        return wp_parse_args(
            get_option( 'cf7_settings', array() ),
            self::get_defaults()
        );
    }

    /**
     * Render setting admin router
     */
    public static function setting_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'entrysaver-manager'));
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'EntrySaver Manager Settings', 'entrysaver-manager' ); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'cf7_settings_group' );
                do_settings_sections( 'cf7_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Show Zero Entry Field 
     */
    public static function render_show_zero_entry_field() {
        $options = self::get_settings();
        $value   = ! empty( $options['show_zero_entry_forms'] );
        ?>

        <label>
            <input
                type="checkbox"
                name="cf7_settings[show_zero_entry_forms]"
                value="1"
                <?php checked( $value, true ); ?>
            >
            <?php esc_html_e( 'Show forms that have no entries.', 'entrysaver-manager' ); ?>
        </label>

        <?php
    }

    /**
     * Render Collect User Data Field 
     */
    public static function render_collect_user_data_field() {
        $options = self::get_settings();
        $value   = ! empty( $options['collect_user_data'] );
        ?>

        <label>
            <input
                type="checkbox"
                name="cf7_settings[collect_user_data]"
                value="1"
                <?php checked( $value, true ); ?>
            >
            <?php esc_html_e( 'Store the visitor IP address and browser information with each submission.', 'entrysaver-manager' ); ?>
        </label>

        <?php
    }

    /**
     * Page 1: Main Admin Dashboard - Shows all forms with entry count
     */
    private static function render_forms_dashboard_page() {
        $forms_summary = CF7EM_DB::get_all_cf7_forms_summary();
        ?>
        <div class="wrap cf7em-wrap">
            <div class="cf7em-header">
                <div class="cf7em-header-title">
                    <h1>
                        <span class="dashicons dashicons-feedback"></span> 
                        <?php esc_html_e('All Contact Forms', 'entrysaver-manager'); ?>
                    </h1>
                    <p class="cf7em-subtitle"><?php esc_html_e('Select a contact form to view, edit, search, and export submitted entries.', 'entrysaver-manager'); ?></p>
                </div>
            </div>

            <?php if (empty($forms_summary)) : ?>
                <div class="notice notice-info cf7em-notice">
                    <p><?php esc_html_e('No Contact Form 7 forms were found on your site. Create a form first using Contact Form 7 plugin.', 'entrysaver-manager'); ?></p>
                </div>
            <?php else : ?>
                <div class="cf7em-cards-grid">
                    <?php foreach ($forms_summary as $form) : 
                        $settings = self::get_settings();
                        if(empty($settings['show_zero_entry_forms']) && $form->entry_count < 1 ){
                            continue;
                        }
                        ?>
                        <div class="cf7em-card">
                            <div class="cf7em-card-header">
                                <h3 class="cf7em-card-title"><?php echo esc_html($form->title); ?></h3>
                                <span class="cf7em-badge <?php echo $form->entry_count > 0 ? 'cf7em-badge-active' : 'cf7em-badge-zero'; ?>">
                                    <?php
                                        /* translators: %d: number of entries */ 
                                        echo esc_html(sprintf(_n('%d Entry', '%d Entries', $form->entry_count, 'entrysaver-manager'), $form->entry_count));
                                    ?>
                                </span>
                            </div>

                            <div class="cf7em-card-body">
                                <div class="cf7em-info-row">
                                    <span class="cf7em-info-label"><?php esc_html_e('Shortcode:', 'entrysaver-manager'); ?></span>
                                    <code class="cf7em-code"><?php echo esc_html($form->shortcode); ?></code>
                                </div>
                                <div class="cf7em-info-row">
                                    <span class="cf7em-info-label"><?php esc_html_e('Last Submission:', 'entrysaver-manager'); ?></span>
                                    <span class="cf7em-info-val">
                                        <?php 
                                        if ($form->last_submission) {
                                            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($form->last_submission)));
                                        } else {
                                            echo '<em style="color:#888;">' . esc_html__('No entries yet', 'entrysaver-manager') . '</em>';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="cf7em-card-footer">
                                <a href="<?php echo esc_url(add_query_arg(array('page' => 'entrysaver', 'action' => 'view_entries', 'form_id' => $form->id), admin_url('admin.php'))); ?>" class="button button-primary cf7em-view-entries-btn">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <?php esc_html_e('View Entries', 'entrysaver-manager'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Page 2: Form Entries List Page with dynamic column selector, pagination, CSV export
     *
     * @param int $form_id
     */
    private static function render_entries_list_page($form_id) {
        $form_post = get_post($form_id);
        $form_title = $form_post ? $form_post->post_title : __('Form Entries', 'entrysaver-manager');

        $list_table = new CF7EM_List_Table($form_id);
        $list_table->prepare_items();

        // Handle bulk delete action if submitted via GET/POST
        if ($list_table->current_action() === 'bulk_delete') {
            check_admin_referer('bulk-entries');
            $entry_ids = isset( $_REQUEST['entry_id'] )
                        ? array_map( 'absint', (array) wp_unslash( $_REQUEST['entry_id'] ) )
                        : array();
            if (!empty($entry_ids)) {
                $deleted_count = CF7EM_DB::bulk_delete_entries($entry_ids);
                /* translators: %d: number of entries */ 
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d entry deleted.', '%d entries deleted.', $deleted_count, 'entrysaver-manager'), $deleted_count)) . '</p></div>';
                // Refresh list table items
                $list_table->prepare_items();
            }
        }

        $available_fields = $list_table->get_available_fields();
        $selected_fields  = $list_table->get_selected_fields();

        $export_url = wp_nonce_url(
            add_query_arg(array(
                'page'      => 'entrysaver',
                'action'    => 'export_csv',
                'form_id'   => $form_id,
            ), admin_url('admin.php')),
            'cf7em_export_nonce'
        );
        ?>
        <div class="wrap cf7em-wrap">
            <div class="cf7em-header">
                <div class="cf7em-breadcrumb">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=entrysaver')); ?>">
                        &larr; <?php esc_html_e('All Contact Forms', 'entrysaver-manager'); ?>
                    </a>
                </div>
                <div class="cf7em-header-main">
                    <h1>
                        <?php echo esc_html($form_title); ?>
                        <span class="cf7em-header-count">
                            (<?php echo esc_html(number_format_i18n($list_table->get_pagination_arg('total_items'))); ?>)
                        </span>
                    </h1>
                    <div class="cf7em-header-actions">
                        <button type="button" id="cf7em-customize-cols-btn" class="button button-secondary">
                            <span class="dashicons dashicons-columns"></span>
                            <?php esc_html_e('Customize Columns', 'entrysaver-manager'); ?>
                        </button>
                        <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Export CSV', 'entrysaver-manager'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- List Table Form -->
            <form id="cf7em-entries-table-form" method="get">
                <input type="hidden" name="page" value="entrysaver" />
                <input type="hidden" name="action" value="view_entries" />
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>" />
                <?php wp_nonce_field('bulk-entries'); ?>

                <?php
                $list_table->search_box(__('Search Entries', 'entrysaver-manager'), 'cf7em-search');
                $list_table->display();
                ?>
            </form>

            <!-- Column Selection Modal/Drawer -->
            <div id="cf7em-cols-modal" class="cf7em-modal" style="display:none;">
                <div class="cf7em-modal-overlay"></div>
                <div class="cf7em-modal-content">
                    <div class="cf7em-modal-header">
                        <h3><span class="dashicons dashicons-columns"></span> <?php esc_html_e('Select Columns to Display', 'entrysaver-manager'); ?></h3>
                        <button type="button" class="cf7em-modal-close">&times;</button>
                    </div>
                    <div class="cf7em-modal-body">
                        <p class="description">
                            <?php esc_html_e('Check the form fields you would like to display as columns in the entries listing table:', 'entrysaver-manager'); ?>
                        </p>
                        <form id="cf7em-cols-form">
                            <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>" />
                            <div class="cf7em-cols-grid">
                                <?php if (!empty($available_fields)) : ?>
                                    <?php foreach ($available_fields as $field) : 
                                        $checked = in_array($field, $selected_fields, true) ? 'checked="checked"' : '';
                                        $label   = ucwords(str_replace(array('-', '_'), ' ', $field));
                                    ?>
                                        <label class="cf7em-col-checkbox">
                                            <input type="checkbox" name="selected_columns[]" value="<?php echo esc_attr($field); ?>" <?php echo esc_attr($checked); ?> />
                                            <span><?php echo esc_html($label); ?> <code style="font-size:11px; color:#666;">(<?php echo esc_html($field); ?>)</code></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p><?php esc_html_e('No fields detected for this form yet.', 'entrysaver-manager'); ?></p>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="cf7em-modal-footer">
                        <button type="button" class="button cf7em-modal-close"><?php esc_html_e('Cancel', 'entrysaver-manager'); ?></button>
                        <button type="button" id="cf7em-save-cols-btn" class="button button-primary"><?php esc_html_e('Save Column Preferences', 'entrysaver-manager'); ?></button>
                    </div>
                </div>
            </div>

            <!-- View Entry Detail Modal -->
            <div id="cf7em-view-modal" class="cf7em-modal" style="display:none;">
                <div class="cf7em-modal-overlay"></div>
                <div class="cf7em-modal-content cf7em-modal-lg">
                    <div class="cf7em-modal-header">
                        <h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Entry Details', 'entrysaver-manager'); ?></h3>
                        <button type="button" class="cf7em-modal-close">&times;</button>
                    </div>
                    <div class="cf7em-modal-body" id="cf7em-view-modal-body">
                        <div class="cf7em-loading"><span class="spinner is-active"></span> <?php esc_html_e('Loading entry details...', 'entrysaver-manager'); ?></div>
                    </div>
                    <div class="cf7em-modal-footer">
                        <button type="button" class="button cf7em-modal-close"><?php esc_html_e('Close', 'entrysaver-manager'); ?></button>
                    </div>
                </div>
            </div>

            <!-- Edit Entry Modal -->
            <div id="cf7em-edit-modal" class="cf7em-modal" style="display:none;">
                <div class="cf7em-modal-overlay"></div>
                <div class="cf7em-modal-content cf7em-modal-lg">
                    <div class="cf7em-modal-header">
                        <h3><span class="dashicons dashicons-edit"></span> <?php esc_html_e('Edit Entry', 'entrysaver-manager'); ?></h3>
                        <button type="button" class="cf7em-modal-close">&times;</button>
                    </div>
                    <div class="cf7em-modal-body" id="cf7em-edit-modal-body">
                        <div class="cf7em-loading"><span class="spinner is-active"></span> <?php esc_html_e('Loading entry editor...', 'entrysaver-manager'); ?></div>
                    </div>
                    <div class="cf7em-modal-footer">
                        <button type="button" class="button cf7em-modal-close"><?php esc_html_e('Cancel', 'entrysaver-manager'); ?></button>
                        <button type="button" id="cf7em-save-edit-btn" class="button button-primary"><?php esc_html_e('Update Entry', 'entrysaver-manager'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Get Entry details for View / Edit Modal
     */
    public static function ajax_get_entry() {
        check_ajax_referer('cf7em_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'entrysaver-manager'));
        }

        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
        $mode     = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'view';

        $entry = CF7EM_DB::get_entry($entry_id);
        if (!$entry) {
            wp_send_json_error(__('Entry not found.', 'entrysaver-manager'));
        }

        // Mark as read
        CF7EM_DB::mark_status($entry_id, 'read');

        ob_start();
        if ($mode === 'edit') {
            self::render_edit_modal_content($entry);
        } else {
            self::render_view_modal_content($entry);
        }
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Render View Entry Modal HTML
     */
    private static function render_view_modal_content($entry) {
        $date_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->submitted_at));
        ?>
        <div class="cf7em-entry-detail">
            <div class="cf7em-detail-meta-box">
                <div class="cf7em-meta-item">
                    <strong><?php esc_html_e('Entry ID:', 'entrysaver-manager'); ?></strong> #<?php echo esc_html($entry->id); ?>
                </div>
                <div class="cf7em-meta-item">
                    <strong><?php esc_html_e('Form:', 'entrysaver-manager'); ?></strong> <?php echo esc_html($entry->form_title); ?>
                </div>
                <div class="cf7em-meta-item">
                    <strong><?php esc_html_e('Submitted At:', 'entrysaver-manager'); ?></strong> <?php echo esc_html($date_formatted); ?>
                </div>
                <?php if (!empty($entry->meta_data['remote_ip'])) : ?>
                    <div class="cf7em-meta-item">
                        <strong><?php esc_html_e('IP Address:', 'entrysaver-manager'); ?></strong> <?php echo esc_html($entry->meta_data['remote_ip']); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($entry->meta_data['url'])) : ?>
                    <div class="cf7em-meta-item">
                        <strong><?php esc_html_e('Submitted Page:', 'entrysaver-manager'); ?></strong> 
                        <a href="<?php echo esc_url($entry->meta_data['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($entry->meta_data['url']); ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <h4><span class="dashicons dashicons-text-page"></span> <?php esc_html_e('Submitted Form Data', 'entrysaver-manager'); ?></h4>
            <table class="widefat striped cf7em-detail-table">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php esc_html_e('Field Name', 'entrysaver-manager'); ?></th>
                        <th><?php esc_html_e('Submitted Value', 'entrysaver-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($entry->fields_data)) : ?>
                        <?php foreach ($entry->fields_data as $key => $val) : 
                            $label = ucwords(str_replace(array('-', '_'), ' ', $key));
                            if (is_array($val)) {
                                $val = implode(', ', $val);
                            }
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($label); ?></strong>
                                    <br/><code style="font-size:11px; color:#888;"><?php echo esc_html($key); ?></code>
                                </td>
                                <td>
                                    <?php echo nl2br(esc_html($val)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="2"><?php esc_html_e('No fields data recorded for this entry.', 'entrysaver-manager'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($entry->meta_data['files'])) : ?>
                <h4 style="margin-top:20px;"><span class="dashicons dashicons-paperclip"></span> <?php esc_html_e('Uploaded File Attachments', 'entrysaver-manager'); ?></h4>
                <ul class="cf7em-file-list">
                    <?php foreach ($entry->meta_data['files'] as $file) : ?>
                        <li>
                            <span class="dashicons dashicons-media-default"></span>
                            <strong><?php echo esc_html($file['field']); ?>:</strong> 
                            <a href="<?php echo esc_url($file['url']); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html($file['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Edit Entry Modal Content
     */
    private static function render_edit_modal_content($entry) {
        ?>
        <form id="cf7em-edit-entry-form">
            <input type="hidden" name="entry_id" value="<?php echo esc_attr($entry->id); ?>" />
            <table class="widefat striped cf7em-edit-table">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php esc_html_e('Field Name', 'entrysaver-manager'); ?></th>
                        <th><?php esc_html_e('Value', 'entrysaver-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($entry->fields_data)) : ?>
                        <?php foreach ($entry->fields_data as $key => $val) : 
                            $label = ucwords(str_replace(array('-', '_'), ' ', $key));
                            $value_str = is_array($val) ? implode(', ', $val) : (string)$val;
                        ?>
                            <tr>
                                <td>
                                    <label for="field_<?php echo esc_attr($key); ?>">
                                        <strong><?php echo esc_html($label); ?></strong>
                                    </label>
                                    <br/><code style="font-size:11px; color:#888;"><?php echo esc_html($key); ?></code>
                                </td>
                                <td>
                                    <?php if (strlen($value_str) > 60 || strpos($value_str, "\n") !== false) : ?>
                                        <textarea name="fields[<?php echo esc_attr($key); ?>]" id="field_<?php echo esc_attr($key); ?>" class="large-text" rows="3"><?php echo esc_textarea($value_str); ?></textarea>
                                    <?php else : ?>
                                        <input type="text" name="fields[<?php echo esc_attr($key); ?>]" id="field_<?php echo esc_attr($key); ?>" class="regular-text" value="<?php echo esc_attr($value_str); ?>" />
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
        <?php
    }

    /**
     * AJAX: Update Entry fields data
     */
    public static function ajax_update_entry() {
        check_ajax_referer('cf7em_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'entrysaver-manager'));
        }

        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
        $fields   = isset($_POST['fields']) && is_array($_POST['fields']) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fields'])) : array();

        if (!$entry_id) {
            wp_send_json_error(__('Invalid entry ID.', 'entrysaver-manager'));
        }

        $sanitized_fields = array();
        foreach ($fields as $key => $val) {
            $key_clean = sanitize_key($key);
            if (is_array($val)) {
                $sanitized_fields[$key_clean] = array_map('sanitize_textarea_field', $val);
            } else {
                $sanitized_fields[$key_clean] = sanitize_textarea_field(wp_unslash($val));
            }
        }

        $updated = CF7EM_DB::update_entry($entry_id, $sanitized_fields);

        if ($updated) {
            wp_send_json_success(__('Entry updated successfully.', 'entrysaver-manager'));
        } else {
            wp_send_json_error(__('Failed to update entry or no changes were made.', 'entrysaver-manager'));
        }
    }

    /**
     * AJAX: Delete single entry
     */
    public static function ajax_delete_entry() {
        check_ajax_referer('cf7em_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'entrysaver-manager'));
        }

        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;

        if (!$entry_id) {
            wp_send_json_error(__('Invalid entry ID.', 'entrysaver-manager'));
        }

        $deleted = CF7EM_DB::delete_entry($entry_id);

        if ($deleted) {
            wp_send_json_success(__('Entry deleted.', 'entrysaver-manager'));
        } else {
            wp_send_json_error(__('Failed to delete entry.', 'entrysaver-manager'));
        }
    }

    /**
     * AJAX: Save column preferences
     */
    public static function ajax_save_column_settings() {
        check_ajax_referer('cf7em_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'entrysaver-manager'));
        }

        $form_id          = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $selected_columns = isset($_POST['selected_columns']) && is_array($_POST['selected_columns']) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_columns'])) : array();

        if (!$form_id) {
            wp_send_json_error(__('Invalid form ID.', 'entrysaver-manager'));
        }

        $sanitized_cols = array_map('sanitize_text_field', wp_unslash($selected_columns));
        $user_id        = get_current_user_id();
        $option_key     = 'cf7em_cols_' . $form_id . '_' . $user_id;

        update_user_meta($user_id, $option_key, $sanitized_cols);

        wp_send_json_success(__('Column preferences saved successfully.', 'entrysaver-manager'));
    }

    /**
     * CSV Export Handler
     */
    public static function handle_csv_export() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'entrysaver' || !isset($_GET['action']) || $_GET['action'] !== 'export_csv') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'entrysaver-manager'));
        }

        check_admin_referer('cf7em_export_nonce');

        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        if (!$form_id) {
            wp_die(esc_html__('Invalid Form ID for export.', 'entrysaver-manager'));
        }

        $form_post  = get_post($form_id);
        $form_title = $form_post ? sanitize_title($form_post->post_title) : 'form-' . $form_id;

        $results = CF7EM_DB::get_entries(array(
            'form_id'  => $form_id,
            'per_page' => 5000, // Reasonable max export
            'paged'    => 1,
        ));

        $entries = $results['items'];
        $fields  = CF7EM_DB::get_form_field_keys($form_id);

        $filename = sprintf('entrysaver-%s-%s.csv', $form_title, gmdate('Y-m-d'));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV Header
        $headers = array('Entry ID', 'Submission Date');
        foreach ($fields as $field) {
            $headers[] = ucwords(str_replace(array('-', '_'), ' ', $field));
        }
        $headers[] = 'IP Address';
        $headers[] = 'Page URL';
        fputcsv($output, $headers);

        // CSV Rows
        if (!empty($entries)) {
            foreach ($entries as $entry) {
                $row = array(
                    $entry->id,
                    $entry->submitted_at,
                );

                foreach ($fields as $field) {
                    $val = isset($entry->fields_data[$field]) ? $entry->fields_data[$field] : '';
                    if (is_array($val)) {
                        $val = implode(', ', $val);
                    }
                    $row[] = $val;
                }

                $row[] = isset($entry->meta_data['remote_ip']) ? $entry->meta_data['remote_ip'] : '';
                $row[] = isset($entry->meta_data['url']) ? $entry->meta_data['url'] : '';

                fputcsv($output, $row);
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the PHP output stream after streaming CSV data.
        fclose($output);
        exit;
    }
}
