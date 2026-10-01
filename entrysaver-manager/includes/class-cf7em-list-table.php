<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class CF7EM_List_Table extends WP_List_Table {

    private $form_id;
    private $available_fields = array();
    private $selected_fields  = array();

    /**
     * Constructor
     *
     * @param int $form_id
     */
    public function __construct($form_id = 0) {
        parent::__construct(array(
            'singular' => 'entry',
            'plural'   => 'entries',
            'ajax'     => false,
        ));

        $this->form_id = absint($form_id);
        $this->available_fields = CF7EM_DB::get_form_field_keys($this->form_id);
        $this->selected_fields  = $this->resolve_selected_columns();
    }

    /**
     * Resolve default or user-selected columns for current form
     */
    private function resolve_selected_columns() {
        $user_id = get_current_user_id();
        $option_key = 'cf7em_cols_' . $this->form_id . '_' . $user_id;
        $saved = get_user_meta($user_id, $option_key, true);

        if (is_array($saved) && !empty($saved)) {
            return $saved;
        }

        // Generate intelligent default: First 2 fields + Email field (if present)
        $default_cols = array();
        $email_field  = null;

        foreach ($this->available_fields as $field) {
            if (is_null($email_field) && (strpos(strtolower($field), 'email') !== false || strpos(strtolower($field), 'mail') !== false)) {
                $email_field = $field;
            }
        }

        // Select first two non-email fields, or just first two fields
        $count = 0;
        foreach ($this->available_fields as $field) {
            if ($count < 2) {
                $default_cols[] = $field;
                $count++;
            }
        }

        // Add email field if available and not already in first two
        if ($email_field && !in_array($email_field, $default_cols, true)) {
            $default_cols[] = $email_field;
        }

        return array_unique($default_cols);
    }

    /**
     * Get list table columns
     */
    public function get_columns() {
        $columns = array(
            'cb'           => '<input type="checkbox" />',
            'id'           => __('ID', 'entrysaver-manager'),
        );

        // Dynamically add selected field columns
        if (!empty($this->selected_fields)) {
            foreach ($this->selected_fields as $field_key) {
                // Pretty header title
                $label = ucwords(str_replace(array('-', '_'), ' ', $field_key));
                $columns['field_' . $field_key] = esc_html($label);
            }
        } else {
            $columns['fields_summary'] = __('Submission Data', 'entrysaver-manager');
        }

        $columns['submitted_at'] = __('Submission Date', 'entrysaver-manager');
        $columns['actions']      = __('Actions', 'entrysaver-manager');

        return $columns;
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'id'           => array('id', false),
            'submitted_at' => array('submitted_at', true),
        );
    }

    /**
     * Bulk actions
     */
    public function get_bulk_actions() {
        return array(
            'bulk_delete' => __('Delete', 'entrysaver-manager'),
        );
    }

    /**
     * Checkbox column render
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="entry_id[]" value="%d" />', esc_attr($item->id));
    }

    /**
     * Default column renderer
     */
    public function column_default($item, $column_name) {
        if ($column_name === 'id') {
            return '<strong>#' . esc_html($item->id) . '</strong>';
        }

        if ($column_name === 'submitted_at') {
            $date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->submitted_at));
            return esc_html($date);
        }

        if ($column_name === 'actions') {
            $view_btn = sprintf(
                '<button type="button" class="button button-small cf7em-view-btn" data-id="%d" title="%s"><span class="dashicons dashicons-visibility"></span></button>',
                esc_attr($item->id),
                esc_attr__('View Entry', 'entrysaver-manager')
            );

            $edit_btn = sprintf(
                '<button type="button" class="button button-small cf7em-edit-btn" data-id="%d" title="%s"><span class="dashicons dashicons-edit"></span></button>',
                esc_attr($item->id),
                esc_attr__('Edit Entry', 'entrysaver-manager')
            );

            $delete_btn = sprintf(
                '<button type="button" class="button button-small button-link-delete cf7em-delete-btn" data-id="%d" title="%s"><span class="dashicons dashicons-trash"></span></button>',
                esc_attr($item->id),
                esc_attr__('Delete Entry', 'entrysaver-manager')
            );

            return sprintf('<div class="cf7em-actions-wrap">%s %s %s</div>', $view_btn, $edit_btn, $delete_btn);
        }

        if ($column_name === 'fields_summary') {
            if (empty($item->fields_data)) {
                return '<span class="cf7em-empty-val">—</span>';
            }
            $summary_parts = array();
            foreach ($item->fields_data as $key => $val) {
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }
                $val = trim((string)$val);
                if ($val !== '') {
                    $label = ucwords(str_replace(array('-', '_'), ' ', $key));
                    $summary_parts[] = '<strong>' . esc_html($label) . ':</strong> ' . esc_html(mb_strimwidth($val, 0, 30, '...'));
                }
            }
            return !empty($summary_parts) ? implode(' <span style="color:#ccc; margin:0 4px;">|</span> ', array_slice($summary_parts, 0, 3)) : '<span class="cf7em-empty-val">—</span>';
        }

        // Render dynamic field columns
        if (strpos($column_name, 'field_') === 0) {
            $field_key = substr($column_name, 6);
            $val = isset($item->fields_data[$field_key]) ? $item->fields_data[$field_key] : '';

            if (is_array($val)) {
                $val = implode(', ', $val);
            }

            $val = trim((string)$val);
            if ($val === '') {
                return '<span class="cf7em-empty-val">—</span>';
            }

            // Truncate long text for table view
            $short_val = mb_strimwidth($val, 0, 45, '...');
            return esc_html($short_val);
        }

        return '';
    }

    /**
     * Prepare table items
     */
    public function prepare_items() {
        $per_page = $this->get_items_per_page('cf7em_entries_per_page', 20);
        $paged    = $this->get_pagenum();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are read-only list filtering parameters.
        $search   = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are read-only list filtering parameters.
        $orderby  = isset($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'id';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are read-only list filtering parameters.
        $order    = isset($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'DESC';

        $query_args = array(
            'form_id'  => $this->form_id,
            'per_page' => $per_page,
            'paged'    => $paged,
            'search'   => $search,
            'orderby'  => $orderby,
            'order'    => $order,
        );

        $results = CF7EM_DB::get_entries($query_args);

        $this->items = $results['items'];

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable, 'id');

        $this->set_pagination_args(array(
            'total_items' => $results['total'],
            'per_page'    => $per_page,
            'total_pages' => ceil($results['total'] / $per_page),
        ));
    }

    /**
     * Get available form fields for column customization modal
     */
    public function get_available_fields() {
        return $this->available_fields;
    }

    /**
     * Get selected form fields
     */
    public function get_selected_fields() {
        return $this->selected_fields;
    }
}
