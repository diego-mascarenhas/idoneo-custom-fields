<?php

/**
 * The field group editor: meta boxes on the Field Group edit screen that let admins
 * build fields, configure location rules and group settings. The UI is JS-driven and
 * persists its state into hidden JSON inputs that this class parses on save.
 */

if (! defined('ABSPATH')) {
    exit;
}

class ICF_Builder
{
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . ICF_Field_Group::POST_TYPE, [$this, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function add_meta_boxes(): void
    {
        add_meta_box(
            'icf-builder',
            __('Fields', 'idoneo-custom-fields'),
            [$this, 'render_fields_box'],
            ICF_Field_Group::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'icf-location',
            __('Location Rules', 'idoneo-custom-fields'),
            [$this, 'render_location_box'],
            ICF_Field_Group::POST_TYPE,
            'normal',
            'default'
        );
        add_meta_box(
            'icf-settings',
            __('Group Settings', 'idoneo-custom-fields'),
            [$this, 'render_settings_box'],
            ICF_Field_Group::POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_fields_box(WP_Post $post): void
    {
        wp_nonce_field('icf_save_group', 'icf_nonce');
        $fields = ICF_Field_Group::get_fields($post->ID);
        printf(
            '<input type="hidden" id="icf-fields-json" name="icf_fields_json" value="%s" />',
            esc_attr(wp_json_encode($fields))
        );
        echo '<div id="icf-builder-app" class="icf-builder"></div>';
        echo '<p><button type="button" class="button button-primary icf-add-field">' . esc_html__('+ Add Field', 'idoneo-custom-fields') . '</button></p>';
    }

    public function render_location_box(WP_Post $post): void
    {
        $rules = ICF_Field_Group::get_location($post->ID);
        printf(
            '<input type="hidden" id="icf-location-json" name="icf_location_json" value="%s" />',
            esc_attr(wp_json_encode($rules))
        );
        echo '<p class="description">' . esc_html__('Show this field group when ALL the following rules match.', 'idoneo-custom-fields') . '</p>';
        echo '<div id="icf-location-app" class="icf-location"></div>';
        echo '<p><button type="button" class="button icf-add-rule">' . esc_html__('+ Add Rule', 'idoneo-custom-fields') . '</button></p>';
    }

    public function render_settings_box(WP_Post $post): void
    {
        $options = ICF_Field_Group::get_options($post->ID);
        $position = $options['position'] ?? 'normal';
        $style = $options['style'] ?? 'default';
        $hide = $options['hide_on_screen'] ?? [];
        ?>
        <p>
            <label><strong><?php esc_html_e('Position', 'idoneo-custom-fields'); ?></strong></label><br>
            <select name="icf_options[position]" class="widefat">
                <option value="normal" <?php selected($position, 'normal'); ?>><?php esc_html_e('Normal (after content)', 'idoneo-custom-fields'); ?></option>
                <option value="side" <?php selected($position, 'side'); ?>><?php esc_html_e('Side', 'idoneo-custom-fields'); ?></option>
                <option value="advanced" <?php selected($position, 'advanced'); ?>><?php esc_html_e('Advanced', 'idoneo-custom-fields'); ?></option>
            </select>
        </p>
        <p>
            <label><strong><?php esc_html_e('Style', 'idoneo-custom-fields'); ?></strong></label><br>
            <select name="icf_options[style]" class="widefat">
                <option value="default" <?php selected($style, 'default'); ?>><?php esc_html_e('Standard (WP metabox)', 'idoneo-custom-fields'); ?></option>
                <option value="seamless" <?php selected($style, 'seamless'); ?>><?php esc_html_e('Seamless (no box)', 'idoneo-custom-fields'); ?></option>
            </select>
        </p>
        <p>
            <label><strong><?php esc_html_e('Hide on screen', 'idoneo-custom-fields'); ?></strong></label><br>
            <?php
            $hideables = [
                'the_content' => __('Content Editor', 'idoneo-custom-fields'),
                'excerpt'     => __('Excerpt', 'idoneo-custom-fields'),
                'discussion'  => __('Discussion', 'idoneo-custom-fields'),
                'comments'    => __('Comments', 'idoneo-custom-fields'),
                'featured_image' => __('Featured Image', 'idoneo-custom-fields'),
            ];
            foreach ($hideables as $key => $label) {
                printf(
                    '<label style="display:block"><input type="checkbox" name="icf_options[hide_on_screen][]" value="%s" %s> %s</label>',
                    esc_attr($key),
                    in_array($key, (array) $hide, true) ? 'checked' : '',
                    esc_html($label)
                );
            }
            ?>
        </p>
        <?php
    }

    public function enqueue(string $hook): void
    {
        $screen = get_current_screen();
        if (! $screen || $screen->post_type !== ICF_Field_Group::POST_TYPE) {
            return;
        }
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        wp_enqueue_style('icf-builder', ICF_PLUGIN_URL . 'assets/css/builder.css', [], ICF_VERSION);
        wp_enqueue_script('icf-builder', ICF_PLUGIN_URL . 'assets/js/builder.js', ['jquery', 'jquery-ui-sortable'], ICF_VERSION, true);

        wp_localize_script('icf-builder', 'ICF_BUILDER', [
            'types'     => ICF_Fields::types(),
            'layout'    => ['repeater', 'group', 'flexible_content'],
            'params'    => ICF_Location::params(),
            'operators' => ICF_Location::operators(),
            'choices'   => $this->all_location_choices(),
            'i18n'      => [
                'field'        => __('Field', 'idoneo-custom-fields'),
                'label'        => __('Field Label', 'idoneo-custom-fields'),
                'name'         => __('Field Name', 'idoneo-custom-fields'),
                'type'         => __('Field Type', 'idoneo-custom-fields'),
                'instructions' => __('Instructions', 'idoneo-custom-fields'),
                'required'     => __('Required', 'idoneo-custom-fields'),
                'default'      => __('Default Value', 'idoneo-custom-fields'),
                'placeholder'  => __('Placeholder', 'idoneo-custom-fields'),
                'choices'      => __('Choices (one per line, "value : Label")', 'idoneo-custom-fields'),
                'multiple'     => __('Allow multiple', 'idoneo-custom-fields'),
                'message'      => __('Message / Text', 'idoneo-custom-fields'),
                'post_type'    => __('Filter by post type (comma separated, optional)', 'idoneo-custom-fields'),
                'taxonomy'     => __('Taxonomy', 'idoneo-custom-fields'),
                'sub_fields'   => __('Sub Fields', 'idoneo-custom-fields'),
                'layouts'      => __('Layouts', 'idoneo-custom-fields'),
                'add_field'    => __('+ Add Field', 'idoneo-custom-fields'),
                'add_sub'      => __('+ Add Sub Field', 'idoneo-custom-fields'),
                'add_layout'   => __('+ Add Layout', 'idoneo-custom-fields'),
                'remove'       => __('Remove', 'idoneo-custom-fields'),
                'duplicate'    => __('Duplicate', 'idoneo-custom-fields'),
                'collapse'     => __('Collapse / Expand', 'idoneo-custom-fields'),
                'conditional'  => __('Conditional logic: show this field when another field equals a value', 'idoneo-custom-fields'),
                'cond_enable'  => __('Enable conditional logic', 'idoneo-custom-fields'),
                'cond_field'   => __('Field name', 'idoneo-custom-fields'),
                'cond_value'   => __('equals value', 'idoneo-custom-fields'),
                'no_fields'    => __('No fields yet. Click "Add Field" to start.', 'idoneo-custom-fields'),
                'no_rules'     => __('No rules yet.', 'idoneo-custom-fields'),
                'untitled'     => __('(no label)', 'idoneo-custom-fields'),
            ],
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function all_location_choices(): array
    {
        $choices = [];
        foreach (array_keys(ICF_Location::params()) as $param) {
            $choices[$param] = ICF_Location::choices($param);
        }
        return $choices;
    }

    public function save(int $post_id, WP_Post $post): void
    {
        if (! isset($_POST['icf_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icf_nonce'])), 'icf_save_group')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('manage_options')) {
            return;
        }

        // Fields.
        $fields_json = isset($_POST['icf_fields_json']) ? wp_unslash($_POST['icf_fields_json']) : '[]';
        $fields = json_decode((string) $fields_json, true);
        $fields = is_array($fields) ? self::sanitize_fields($fields) : [];
        update_post_meta($post_id, ICF_Field_Group::META_FIELDS, wp_slash(wp_json_encode($fields)));

        // Location.
        $location_json = isset($_POST['icf_location_json']) ? wp_unslash($_POST['icf_location_json']) : '[]';
        $location = json_decode((string) $location_json, true);
        $location = is_array($location) ? self::sanitize_location($location) : [];
        update_post_meta($post_id, ICF_Field_Group::META_LOCATION, wp_slash(wp_json_encode($location)));

        // Options.
        $options_raw = isset($_POST['icf_options']) && is_array($_POST['icf_options']) ? wp_unslash($_POST['icf_options']) : [];
        $options = [
            'position'       => in_array($options_raw['position'] ?? 'normal', ['normal', 'side', 'advanced'], true) ? $options_raw['position'] : 'normal',
            'style'          => in_array($options_raw['style'] ?? 'default', ['default', 'seamless'], true) ? $options_raw['style'] : 'default',
            'hide_on_screen' => array_map('sanitize_text_field', (array) ($options_raw['hide_on_screen'] ?? [])),
        ];
        update_post_meta($post_id, ICF_Field_Group::META_OPTIONS, wp_slash(wp_json_encode($options)));
    }

    /**
     * Recursively sanitize the field definitions coming from the builder.
     *
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, array<string, mixed>>
     */
    public static function sanitize_fields(array $fields): array
    {
        $clean = [];
        $valid_types = ICF_Fields::type_keys();

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $type = in_array($field['type'] ?? '', $valid_types, true) ? $field['type'] : 'text';
            $label = sanitize_text_field($field['label'] ?? '');
            $name = sanitize_key($field['name'] ?? '');
            if ($name === '' && $label !== '') {
                $name = sanitize_key(str_replace('-', '_', sanitize_title($label)));
            }
            if ($name === '') {
                continue;
            }

            $entry = [
                'label'        => $label,
                'name'         => $name,
                'type'         => $type,
                'instructions' => sanitize_textarea_field($field['instructions'] ?? ''),
                'required'     => ! empty($field['required']) ? 1 : 0,
                'default'      => sanitize_text_field($field['default'] ?? ''),
                'placeholder'  => sanitize_text_field($field['placeholder'] ?? ''),
                'choices'      => sanitize_textarea_field($field['choices'] ?? ''),
                'multiple'     => ! empty($field['multiple']) ? 1 : 0,
                'message'      => wp_kses_post($field['message'] ?? ''),
                'post_type'    => sanitize_text_field($field['post_type'] ?? ''),
                'taxonomy'     => sanitize_key($field['taxonomy'] ?? ''),
            ];

            // Conditional logic.
            if (! empty($field['conditional']['enabled'])) {
                $entry['conditional'] = [
                    'enabled' => 1,
                    'field'   => sanitize_key($field['conditional']['field'] ?? ''),
                    'value'   => sanitize_text_field($field['conditional']['value'] ?? ''),
                ];
            }

            // Sub fields for repeater/group.
            if (in_array($type, ['repeater', 'group'], true) && ! empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                $entry['sub_fields'] = self::sanitize_fields($field['sub_fields']);
            }

            // Layouts for flexible content.
            if ($type === 'flexible_content' && ! empty($field['layouts']) && is_array($field['layouts'])) {
                $layouts = [];
                foreach ($field['layouts'] as $layout) {
                    if (! is_array($layout)) {
                        continue;
                    }
                    $layout_name = sanitize_key($layout['name'] ?? '');
                    if ($layout_name === '') {
                        continue;
                    }
                    $layouts[] = [
                        'name'       => $layout_name,
                        'label'      => sanitize_text_field($layout['label'] ?? ''),
                        'sub_fields' => ! empty($layout['sub_fields']) && is_array($layout['sub_fields'])
                            ? self::sanitize_fields($layout['sub_fields'])
                            : [],
                    ];
                }
                $entry['layouts'] = $layouts;
            }

            $clean[] = $entry;
        }

        return $clean;
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, array<string, string>>
     */
    public static function sanitize_location(array $rules): array
    {
        $params = array_keys(ICF_Location::params());
        $clean = [];
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $param = in_array($rule['param'] ?? '', $params, true) ? $rule['param'] : '';
            if ($param === '') {
                continue;
            }
            $clean[] = [
                'param'    => $param,
                'operator' => ($rule['operator'] ?? '==') === '!=' ? '!=' : '==',
                'value'    => sanitize_text_field($rule['value'] ?? ''),
            ];
        }
        return $clean;
    }
}
