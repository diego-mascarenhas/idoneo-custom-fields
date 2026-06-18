<?php

/**
 * Runtime rendering of matched field groups on post edit screens, plus saving the
 * submitted values to post meta. Repeater, group and flexible content are rendered
 * recursively; new rows are cloned client-side from <script> templates.
 */

if (! defined('ABSPATH')) {
    exit;
}

class ICF_Meta_Box
{
    private static ?self $instance = null;

    /** Placeholder used in row templates, replaced by JS with a real index. */
    public const ROW_PLACEHOLDER = '__icf_row__';

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register'], 10, 2);
        add_action('save_post', [$this, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * @param string  $post_type
     * @param WP_Post $post
     */
    public function register($post_type, $post): void
    {
        if ($post_type === ICF_Field_Group::POST_TYPE) {
            return;
        }
        $post = $post instanceof WP_Post ? $post : get_post();
        if (! $post) {
            return;
        }

        $context = $this->context_for_post($post);

        foreach (ICF_Field_Group::all() as $group) {
            if (empty($group['fields']) || ! ICF_Location::match($group['location'], $context)) {
                continue;
            }

            $options = $group['options'];
            $position = $options['position'] ?? 'normal';

            add_meta_box(
                'icf-group-' . $group['id'],
                $group['title'],
                function (WP_Post $post) use ($group): void {
                    $this->render_group($group, get_post_meta($post->ID), 'post', $post->ID);
                },
                $post_type,
                $position === 'side' ? 'side' : ($position === 'advanced' ? 'advanced' : 'normal'),
                'default'
            );

            $this->apply_hide_on_screen($options['hide_on_screen'] ?? []);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function context_for_post(WP_Post $post): array
    {
        return [
            'post_type'     => $post->post_type,
            'post_status'   => $post->post_status,
            'post'          => (string) $post->ID,
            'page_template' => get_page_template_slug($post->ID) ?: 'default',
        ];
    }

    /**
     * @param array<string, mixed> $hideables
     */
    private function apply_hide_on_screen(array $hideables): void
    {
        $screen = get_current_screen();
        foreach ($hideables as $item) {
            switch ($item) {
                case 'the_content':
                    if ($screen) {
                        remove_post_type_support($screen->post_type, 'editor');
                    }
                    break;
                case 'excerpt':
                    add_filter('default_hidden_meta_boxes', fn ($hidden) => array_merge($hidden, ['postexcerpt']));
                    break;
                case 'featured_image':
                    add_filter('default_hidden_meta_boxes', fn ($hidden) => array_merge($hidden, ['postimagediv']));
                    break;
            }
        }
    }

    /**
     * Render every field in a group.
     *
     * @param array<string, mixed> $group
     * @param array<string, mixed> $all_meta Raw get_post_meta() output or options store.
     */
    public function render_group(array $group, array $all_meta, string $store, int $object_id): void
    {
        wp_nonce_field('icf_save_values', 'icf_values_nonce');
        $seamless = ($group['options']['style'] ?? 'default') === 'seamless';
        echo '<div class="icf-fields ' . ($seamless ? 'icf-seamless' : '') . '">';
        foreach ($group['fields'] as $field) {
            $value = ICF_Storage::get_value($field, $store, $object_id);
            $this->render_field($field, $value, 'icf[' . $field['name'] . ']');
        }
        echo '</div>';
    }

    /**
     * Render a single field row (label + control), dispatching layout types.
     *
     * @param array<string, mixed> $field
     * @param mixed                $value
     */
    public function render_field(array $field, $value, string $input_name): void
    {
        $type = $field['type'] ?? 'text';
        $wrapper_classes = 'icf-field icf-field-' . esc_attr($type);

        $cond_attr = '';
        if (! empty($field['conditional']['enabled'])) {
            $cond_attr = sprintf(
                ' data-cond-field="%s" data-cond-value="%s"',
                esc_attr($field['conditional']['field'] ?? ''),
                esc_attr($field['conditional']['value'] ?? '')
            );
        }

        printf('<div class="%s" data-name="%s"%s>', esc_attr($wrapper_classes), esc_attr($field['name']), $cond_attr); // phpcs:ignore

        if (! ICF_Fields::is_presentational($type)) {
            printf('<label class="icf-label">%s%s</label>', esc_html($field['label'] ?? ''), ! empty($field['required']) ? ' <span class="icf-required">*</span>' : '');
            if (! empty($field['instructions'])) {
                printf('<p class="icf-instructions">%s</p>', esc_html($field['instructions']));
            }
        }

        echo '<div class="icf-input">';
        if ($type === 'repeater') {
            $this->render_repeater($field, is_array($value) ? $value : [], $input_name);
        } elseif ($type === 'group') {
            $this->render_subgroup($field, is_array($value) ? $value : [], $input_name);
        } elseif ($type === 'flexible_content') {
            $this->render_flexible($field, is_array($value) ? $value : [], $input_name);
        } else {
            if (($value === '' || $value === null) && isset($field['default']) && $field['default'] !== '') {
                $value = $field['default'];
            }
            ICF_Fields::render_input($field, $value, $input_name);
        }
        echo '</div>';

        echo '</div>';
    }

    /**
     * @param array<string, mixed> $field
     * @param array<int, mixed>    $rows
     */
    private function render_repeater(array $field, array $rows, string $input_name): void
    {
        $sub_fields = $field['sub_fields'] ?? [];
        echo '<div class="icf-repeater" data-name="' . esc_attr($field['name']) . '">';
        echo '<div class="icf-repeater-rows">';
        foreach (array_values($rows) as $i => $row) {
            $this->render_repeater_row($sub_fields, is_array($row) ? $row : [], $input_name, (string) $i);
        }
        echo '</div>';

        // Template for new rows.
        echo '<script type="text/html" class="icf-repeater-template">';
        $this->render_repeater_row($sub_fields, [], $input_name, self::ROW_PLACEHOLDER);
        echo '</script>';

        printf('<p><button type="button" class="button icf-repeater-add">%s</button></p>', esc_html__('+ Add Row', 'idoneo-custom-fields'));
        echo '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $sub_fields
     * @param array<string, mixed>             $row
     */
    private function render_repeater_row(array $sub_fields, array $row, string $input_name, string $index): void
    {
        echo '<div class="icf-repeater-row">';
        echo '<div class="icf-row-handle dashicons dashicons-move"></div>';
        echo '<div class="icf-row-fields">';
        foreach ($sub_fields as $sub) {
            $sub_value = $row[$sub['name']] ?? '';
            $this->render_field($sub, $sub_value, $input_name . '[' . $index . '][' . $sub['name'] . ']');
        }
        echo '</div>';
        printf('<button type="button" class="button-link icf-row-remove" title="%s">&times;</button>', esc_attr__('Remove', 'idoneo-custom-fields'));
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $value
     */
    private function render_subgroup(array $field, array $value, string $input_name): void
    {
        echo '<div class="icf-group-fields">';
        foreach ($field['sub_fields'] ?? [] as $sub) {
            $sub_value = $value[$sub['name']] ?? '';
            $this->render_field($sub, $sub_value, $input_name . '[' . $sub['name'] . ']');
        }
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $field
     * @param array<int, mixed>    $rows
     */
    private function render_flexible(array $field, array $rows, string $input_name): void
    {
        $layouts = $field['layouts'] ?? [];
        $layouts_by_name = [];
        foreach ($layouts as $layout) {
            $layouts_by_name[$layout['name']] = $layout;
        }

        echo '<div class="icf-flexible" data-name="' . esc_attr($field['name']) . '">';
        echo '<div class="icf-flexible-rows">';
        foreach (array_values($rows) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $layout_name = $row['acf_fc_layout'] ?? '';
            if (! isset($layouts_by_name[$layout_name])) {
                continue;
            }
            $this->render_flexible_row($layouts_by_name[$layout_name], $row, $input_name, (string) $i);
        }
        echo '</div>';

        // One template per layout.
        foreach ($layouts as $layout) {
            printf('<script type="text/html" class="icf-flexible-template" data-layout="%s">', esc_attr($layout['name']));
            $this->render_flexible_row($layout, ['acf_fc_layout' => $layout['name']], $input_name, self::ROW_PLACEHOLDER);
            echo '</script>';
        }

        echo '<div class="icf-flexible-add">';
        echo '<select class="icf-flexible-layout">';
        printf('<option value="">%s</option>', esc_html__('+ Add Layout', 'idoneo-custom-fields'));
        foreach ($layouts as $layout) {
            printf('<option value="%s">%s</option>', esc_attr($layout['name']), esc_html($layout['label'] ?: $layout['name']));
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $layout
     * @param array<string, mixed> $row
     */
    private function render_flexible_row(array $layout, array $row, string $input_name, string $index): void
    {
        echo '<div class="icf-flexible-row" data-layout="' . esc_attr($layout['name']) . '">';
        echo '<div class="icf-row-handle dashicons dashicons-move"></div>';
        printf('<span class="icf-flexible-label">%s</span>', esc_html($layout['label'] ?: $layout['name']));
        printf('<input type="hidden" name="%s[%s][acf_fc_layout]" value="%s" />', esc_attr($input_name), esc_attr($index), esc_attr($layout['name']));
        echo '<div class="icf-row-fields">';
        foreach ($layout['sub_fields'] ?? [] as $sub) {
            $sub_value = $row[$sub['name']] ?? '';
            $this->render_field($sub, $sub_value, $input_name . '[' . $index . '][' . $sub['name'] . ']');
        }
        echo '</div>';
        printf('<button type="button" class="button-link icf-row-remove" title="%s">&times;</button>', esc_attr__('Remove', 'idoneo-custom-fields'));
        echo '</div>';
    }

    /**
     * @param int $post_id
     */
    public function save($post_id, $post = null): void
    {
        if (! isset($_POST['icf_values_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icf_values_nonce'])), 'icf_save_values')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        $post = $post ?: get_post($post_id);
        if (! $post || ! current_user_can('edit_post', $post_id)) {
            return;
        }

        $submitted = isset($_POST['icf']) && is_array($_POST['icf']) ? wp_unslash($_POST['icf']) : [];
        $context = $this->context_for_post($post);

        foreach (ICF_Field_Group::all() as $group) {
            if (empty($group['fields']) || ! ICF_Location::match($group['location'], $context)) {
                continue;
            }
            foreach ($group['fields'] as $field) {
                if (ICF_Fields::is_presentational($field['type'] ?? 'text')) {
                    continue;
                }
                $raw = $submitted[$field['name']] ?? null;
                $value = self::sanitize_field_value($field, $raw);
                ICF_Storage::save_value($field, $value, 'post', $post_id);
            }
        }
    }

    /**
     * Recursively sanitize a submitted value based on the field definition.
     *
     * @param array<string, mixed> $field
     * @param mixed                $raw
     * @return mixed
     */
    public static function sanitize_field_value(array $field, $raw)
    {
        $type = $field['type'] ?? 'text';

        if ($type === 'repeater') {
            if (! is_array($raw)) {
                return [];
            }
            $rows = [];
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $clean_row = [];
                foreach ($field['sub_fields'] ?? [] as $sub) {
                    $clean_row[$sub['name']] = self::sanitize_field_value($sub, $row[$sub['name']] ?? null);
                }
                $rows[] = $clean_row;
            }
            return array_values($rows);
        }

        if ($type === 'group') {
            $raw = is_array($raw) ? $raw : [];
            $clean = [];
            foreach ($field['sub_fields'] ?? [] as $sub) {
                $clean[$sub['name']] = self::sanitize_field_value($sub, $raw[$sub['name']] ?? null);
            }
            return $clean;
        }

        if ($type === 'flexible_content') {
            if (! is_array($raw)) {
                return [];
            }
            $layouts_by_name = [];
            foreach ($field['layouts'] ?? [] as $layout) {
                $layouts_by_name[$layout['name']] = $layout;
            }
            $rows = [];
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $layout_name = sanitize_key($row['acf_fc_layout'] ?? '');
                if (! isset($layouts_by_name[$layout_name])) {
                    continue;
                }
                $clean_row = ['acf_fc_layout' => $layout_name];
                foreach ($layouts_by_name[$layout_name]['sub_fields'] ?? [] as $sub) {
                    $clean_row[$sub['name']] = self::sanitize_field_value($sub, $row[$sub['name']] ?? null);
                }
                $rows[] = $clean_row;
            }
            return array_values($rows);
        }

        return ICF_Fields::sanitize($field, $raw);
    }

    public function enqueue(string $hook): void
    {
        $screen = get_current_screen();
        if (! $screen) {
            return;
        }
        $is_post_edit = in_array($hook, ['post.php', 'post-new.php'], true) && $screen->post_type !== ICF_Field_Group::POST_TYPE;
        $is_options = strpos($hook, 'icf-options-') !== false || (isset($_GET['page']) && strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'icf-options') === 0);

        if (! $is_post_edit && ! $is_options) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('icf-fields', ICF_PLUGIN_URL . 'assets/css/fields.css', [], ICF_VERSION);
        wp_enqueue_script(
            'icf-fields',
            ICF_PLUGIN_URL . 'assets/js/fields.js',
            ['jquery', 'jquery-ui-sortable', 'wp-color-picker'],
            ICF_VERSION,
            true
        );
        wp_localize_script('icf-fields', 'ICF_FIELDS', [
            'rowPlaceholder' => self::ROW_PLACEHOLDER,
            'i18n'           => [
                'selectImage' => __('Select image', 'idoneo-custom-fields'),
                'selectFile'  => __('Select file', 'idoneo-custom-fields'),
                'use'         => __('Use this', 'idoneo-custom-fields'),
            ],
        ]);
    }
}
