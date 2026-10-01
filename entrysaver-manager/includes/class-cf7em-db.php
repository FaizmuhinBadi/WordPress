<?php
if (!defined('ABSPATH')) {
    exit;
}

class CF7EM_DB {

    /**
     * Get table name
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'cf7_entries';
    }

    /**
     * Create custom database tables
     */
    public static function create_tables() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            form_title varchar(255) NOT NULL,
            fields_data longtext NOT NULL,
            meta_data longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'unread' NOT NULL,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY submitted_at (submitted_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Check if database version needs update
     */
    public static function check_update() {
        $installed_ver = get_option('cf7em_db_version');
        if ($installed_ver !== CF7EM_DB_VERSION) {
            self::create_tables();
            update_option('cf7em_db_version', CF7EM_DB_VERSION);
        }
    }

    /**
     * Insert a new entry
     *
     * @param int $form_id
     * @param string $form_title
     * @param array $fields_data
     * @param array $meta_data
     * @return int|false
     */
    public static function insert_entry($form_id, $form_title, $fields_data, $meta_data = array()) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'form_id'      => absint($form_id),
                'form_title'    => sanitize_text_field($form_title),
                'fields_data'   => wp_json_encode($fields_data),
                'meta_data'     => wp_json_encode($meta_data),
                'status'        => 'unread',
                'submitted_at'  => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Get single entry by ID
     *
     * @param int $entry_id
     * @return object|false
     */
    public static function get_entry($entry_id) {
        global $wpdb;
        $table_name = self::get_table_name();

        $entry = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", absint($entry_id))
        );

        if ($entry) {
            $entry->fields_data = json_decode($entry->fields_data, true) ?: array();
            $entry->meta_data   = json_decode($entry->meta_data, true) ?: array();
        }

        return $entry;
    }

    /**
     * Update an entry's fields data
     *
     * @param int $entry_id
     * @param array $fields_data
     * @return bool
     */
    public static function update_entry($entry_id, $fields_data) {
        global $wpdb;
        $table_name = self::get_table_name();

        $result = $wpdb->update(
            $table_name,
            array('fields_data' => wp_json_encode($fields_data)),
            array('id' => absint($entry_id)),
            array('%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Mark entry status (read / unread)
     */
    public static function mark_status($entry_id, $status = 'read') {
        global $wpdb;
        $table_name = self::get_table_name();
        return $wpdb->update(
            $table_name,
            array('status' => sanitize_key($status)),
            array('id' => absint($entry_id)),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Delete an entry by ID
     *
     * @param int $entry_id
     * @return bool
     */
    public static function delete_entry($entry_id) {
        global $wpdb;
        $table_name = self::get_table_name();
        return (bool) $wpdb->delete($table_name, array('id' => absint($entry_id)), array('%d'));
    }

    /**
     * Bulk delete entries by array of IDs
     *
     * @param array $entry_ids
     * @return int Number of rows deleted
     */
    public static function bulk_delete_entries($entry_ids) {
        global $wpdb;
        $table_name = self::get_table_name();
        $entry_ids  = array_map('absint', (array) $entry_ids);
        $entry_ids  = array_filter($entry_ids);

        if (empty($entry_ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($entry_ids), '%d'));
        return $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ({$placeholders})", $entry_ids));

    }

    /**
     * Query entries for list table
     *
     * @param array $args
     * @return array array('items' => array(), 'total' => int)
     */
    /**
     * Query entries for list table
     *
     * @param array $args
     * @return array array('items' => array(), 'total' => int)
     */
    public static function get_entries($args = array()) {
        global $wpdb;
        $table_name = self::get_table_name();

        $defaults = array(
            'form_id'  => 0,
            'per_page' => 20,
            'paged'    => 1,
            'search'   => '',
            'orderby'  => 'id',
            'order'    => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $where  = array('1=1');
        $params = array();

        if (!empty($args['form_id'])) {
            $where[]  = 'form_id = %d';
            $params[] = absint($args['form_id']);
        }

        if (!empty($args['search'])) {
            $where[]     = '(form_title LIKE %s OR fields_data LIKE %s)';
            $search_like = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $params[]    = $search_like;
            $params[]    = $search_like;
        }

        $where_sql = implode(' AND ', $where);

        // Count total items
        $count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $count_sql is internally generated and safe.
            $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $count_sql is internally generated and safe.
            $total = (int) $wpdb->get_var($count_sql);
        }

        // Sorting
        $allowed_orderby = array('id', 'submitted_at', 'form_title', 'status');
        $orderby = in_array(strtolower($args['orderby']), $allowed_orderby, true) ? $args['orderby'] : 'id';
        $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Pagination
        $per_page = max(1, absint($args['per_page']));
        $paged    = max(1, absint($args['paged']));
        $offset   = ($paged - 1) * $per_page;

        $items_sql = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $items_params = array_merge($params, array($per_page, $offset));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $items_sql is internally generated and safe.
        $items = $wpdb->get_results($wpdb->prepare($items_sql, $items_params));

        if (!empty($items)) {
            foreach ($items as &$item) {
                $item->fields_data = json_decode($item->fields_data, true) ?: array();
                $item->meta_data   = json_decode($item->meta_data, true) ?: array();
            }
        }

        return array(
            'items' => $items ? $items : array(),
            'total' => $total,
        );
    }

    /**
     * Get summary of all Contact Form 7 forms with entry count and last submission date
     *
     * @return array List of form summary objects
     */
    public static function get_all_cf7_forms_summary() {
        global $wpdb;
        $table_name = self::get_table_name();

        // Get all CF7 form posts
        $forms = get_posts(array(
            'post_type'      => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Aggregate entry stats from db
        $stats = $wpdb->get_results(
            "SELECT form_id, COUNT(*) as total_entries, MAX(submitted_at) as last_submission 
             FROM {$table_name} 
             GROUP BY form_id",
            OBJECT_K
        );

        $result = array();

        if (!empty($forms)) {
            foreach ($forms as $form) {
                $form_id = $form->ID;
                $entry_count = isset($stats[$form_id]) ? (int) $stats[$form_id]->total_entries : 0;
                $last_date   = isset($stats[$form_id]) ? $stats[$form_id]->last_submission : null;

                $result[] = (object) array(
                    'id'            => $form_id,
                    'title'         => $form->post_title,
                    'shortcode'     => sprintf('[contact-form-7 id="%d" title="%s"]', $form_id, esc_attr($form->post_title)),
                    'entry_count'   => $entry_count,
                    'last_submission' => $last_date,
                );
            }
        }

        return $result;
    }

    /**
     * Get unique field names for a specific form
     * First checks form tag definitions from Contact Form 7 object if available,
     * and extracts submitted field keys from DB to merge all available keys.
     *
     * @param int $form_id
     * @return array Array of field keys/names
     */
    public static function get_form_field_keys($form_id) {
        $form_id = absint($form_id);
        $fields  = array();

        // 1. Try fetching from WPCF7_ContactForm scan
        if (class_exists('WPCF7_ContactForm')) {
            $contact_form = WPCF7_ContactForm::get_instance($form_id);
            if ($contact_form && method_exists($contact_form, 'scan_form_tags')) {
                $tags = $contact_form->scan_form_tags();
                if (!empty($tags)) {
                    $ignore_types = array('submit', 'captchac', 'capthar', 'recaptcha', 'all_fields', 'response');
                    foreach ($tags as $tag) {
                        $type = isset($tag->type) ? $tag->type : '';
                        $basetype = isset($tag->basetype) ? $tag->basetype : '';
                        if (!empty($tag->name) && strpos($tag->name, '_') !== 0 && !in_array($type, $ignore_types, true) && !in_array($basetype, $ignore_types, true)) {
                            $fields[] = $tag->name;
                        }
                    }
                }
            }
        }

        // 2. Also inspect DB records to collect any submitted field keys
        // global $wpdb;
        // $table_name = self::get_table_name();
        // $recent_entries = $wpdb->get_results(
        //     $wpdb->prepare("SELECT fields_data FROM {$table_name} WHERE form_id = %d ORDER BY id DESC LIMIT 20", $form_id)
        // );

        // if (!empty($recent_entries)) {
        //     foreach ($recent_entries as $row) {
        //         $decoded = json_decode($row->fields_data, true);
        //         if (is_array($decoded)) {
        //             foreach (array_keys($decoded) as $key) {
        //                 if (strpos($key, '_') !== 0 && !in_array($key, $fields, true)) {
        //                     $fields[] = $key;
        //                 }
        //             }
        //         }
        //     }
        // }

        return array_values(array_unique($fields));
    }
}
