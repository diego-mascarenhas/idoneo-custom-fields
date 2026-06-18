<?php

/**
 * Field type registry. Knows the available field types, how to render their inputs
 * on an edit screen, and how to sanitize submitted values.
 */

if (! defined('ABSPATH')) {
    exit;
}

class ICF_Fields
{
    /**
     * @return array<string, array<string, mixed>> Grouped field types for the builder dropdown.
     */
    public static function types(): array
    {
        return [
            __('Basic', 'idoneo-custom-fields') => [
                'text'      => __('Text', 'idoneo-custom-fields'),
                'textarea'  => __('Text Area', 'idoneo-custom-fields'),
                'number'    => __('Number', 'idoneo-custom-fields'),
                'email'     => __('Email', 'idoneo-custom-fields'),
                'url'       => __('URL', 'idoneo-custom-fields'),
                'password'  => __('Password', 'idoneo-custom-fields'),
            ],
            __('Content', 'idoneo-custom-fields') => [
                'wysiwyg'   => __('WYSIWYG Editor', 'idoneo-custom-fields'),
                'image'     => __('Image', 'idoneo-custom-fields'),
                'file'      => __('File', 'idoneo-custom-fields'),
                'gallery'   => __('Gallery (PRO)', 'idoneo-custom-fields'),
                'oembed'    => __('oEmbed', 'idoneo-custom-fields'),
            ],
            __('Choice', 'idoneo-custom-fields') => [
                'select'    => __('Select', 'idoneo-custom-fields'),
                'checkbox'  => __('Checkbox', 'idoneo-custom-fields'),
                'radio'     => __('Radio Button', 'idoneo-custom-fields'),
                'true_false' => __('True / False', 'idoneo-custom-fields'),
            ],
            __('Relational', 'idoneo-custom-fields') => [
                'post_object' => __('Post Object', 'idoneo-custom-fields'),
                'taxonomy'    => __('Taxonomy', 'idoneo-custom-fields'),
                'link'        => __('Link', 'idoneo-custom-fields'),
            ],
            __('jQuery', 'idoneo-custom-fields') => [
                'date_picker'  => __('Date Picker', 'idoneo-custom-fields'),
                'color_picker' => __('Color Picker', 'idoneo-custom-fields'),
            ],
            __('Layout (PRO)', 'idoneo-custom-fields') => [
                'repeater'         => __('Repeater', 'idoneo-custom-fields'),
                'group'            => __('Group', 'idoneo-custom-fields'),
                'flexible_content' => __('Flexible Content', 'idoneo-custom-fields'),
                'tab'              => __('Tab', 'idoneo-custom-fields'),
                'message'          => __('Message', 'idoneo-custom-fields'),
            ],
        ];
    }

    /** @return array<int, string> Flat list of all valid type keys. */
    public static function type_keys(): array
    {
        $keys = [];
        foreach (self::types() as $group) {
            foreach (array_keys($group) as $key) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    public static function is_layout_type(string $type): bool
    {
        return in_array($type, ['repeater', 'group', 'flexible_content'], true);
    }

    /** Field types that don't store a value (presentational only). */
    public static function is_presentational(string $type): bool
    {
        return in_array($type, ['tab', 'message'], true);
    }

    /**
     * Render the input control for a field on a post/options edit screen.
     *
     * @param array<string, mixed> $field
     * @param mixed                $value
     */
    public static function render_input(array $field, $value, string $input_name): void
    {
        $type = $field['type'] ?? 'text';
        $id = 'icf-' . sanitize_html_class(str_replace(['[', ']'], ['-', ''], $input_name));
        $required = ! empty($field['required']) ? 'required' : '';
        $placeholder = isset($field['placeholder']) ? esc_attr($field['placeholder']) : '';

        switch ($type) {
            case 'textarea':
                printf(
                    '<textarea id="%s" name="%s" rows="5" class="widefat" placeholder="%s" %s>%s</textarea>',
                    esc_attr($id),
                    esc_attr($input_name),
                    $placeholder,
                    esc_attr($required),
                    esc_textarea((string) $value)
                );
                break;

            case 'number':
                printf(
                    '<input type="number" id="%s" name="%s" value="%s" class="widefat" step="%s" %s />',
                    esc_attr($id),
                    esc_attr($input_name),
                    esc_attr((string) $value),
                    esc_attr($field['step'] ?? 'any'),
                    esc_attr($required)
                );
                break;

            case 'email':
            case 'url':
            case 'password':
                printf(
                    '<input type="%s" id="%s" name="%s" value="%s" class="widefat" placeholder="%s" %s />',
                    esc_attr($type),
                    esc_attr($id),
                    esc_attr($input_name),
                    esc_attr((string) $value),
                    $placeholder,
                    esc_attr($required)
                );
                break;

            case 'wysiwyg':
                $editor_id = preg_replace('/[^a-z0-9]/', '', strtolower($id));
                wp_editor((string) $value, $editor_id, [
                    'textarea_name' => $input_name,
                    'media_buttons' => true,
                    'textarea_rows' => 8,
                ]);
                break;

            case 'image':
            case 'file':
                self::render_media($field, $value, $input_name, $id, $type);
                break;

            case 'gallery':
                self::render_gallery($value, $input_name, $id);
                break;

            case 'oembed':
                printf(
                    '<input type="url" id="%s" name="%s" value="%s" class="widefat icf-oembed" placeholder="https://..." />',
                    esc_attr($id),
                    esc_attr($input_name),
                    esc_attr((string) $value)
                );
                if ($value) {
                    echo '<div class="icf-oembed-preview">' . wp_oembed_get((string) $value) . '</div>'; // phpcs:ignore
                }
                break;

            case 'select':
            case 'checkbox':
            case 'radio':
                self::render_choice($field, $value, $input_name, $id, $type);
                break;

            case 'true_false':
                printf(
                    '<label class="icf-toggle"><input type="hidden" name="%s" value="0" /><input type="checkbox" id="%s" name="%s" value="1" %s /> %s</label>',
                    esc_attr($input_name),
                    esc_attr($id),
                    esc_attr($input_name),
                    checked((string) $value, '1', false),
                    esc_html($field['message'] ?? '')
                );
                break;

            case 'post_object':
                self::render_post_object($field, $value, $input_name, $id);
                break;

            case 'taxonomy':
                self::render_taxonomy($field, $value, $input_name, $id);
                break;

            case 'link':
                self::render_link($value, $input_name, $id);
                break;

            case 'date_picker':
                printf(
                    '<input type="date" id="%s" name="%s" value="%s" class="widefat" %s />',
                    esc_attr($id),
                    esc_attr($input_name),
                    esc_attr((string) $value),
                    esc_attr($required)
                );
                break;

            case 'color_picker':
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="icf-color-picker" data-default-color="" />',
                    esc_attr($id),
                    esc_attr($input_name),
                    esc_attr((string) $value)
                );
                break;

            case 'message':
                echo '<div class="icf-message">' . wp_kses_post(wpautop($field['message'] ?? '')) . '</div>';
                break;

            case 'text':
            default:
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="widefat" placeholder="%s" %s />',
                    esc_attr($id),
                    esc_attr($input_name),
                    esc_attr((string) $value),
                    $placeholder,
                    esc_attr($required)
                );
                break;
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed                $value
     */
    private static function render_media(array $field, $value, string $input_name, string $id, string $type): void
    {
        $value = (int) $value;
        $preview = '';
        $has = $value > 0;
        if ($has) {
            $preview = $type === 'image'
                ? wp_get_attachment_image($value, 'thumbnail')
                : esc_html(get_the_title($value));
        }
        printf('<div class="icf-media" data-type="%s">', esc_attr($type));
        printf('<input type="hidden" id="%s" name="%s" value="%s" class="icf-media-id" />', esc_attr($id), esc_attr($input_name), esc_attr((string) ($value ?: '')));
        echo '<div class="icf-media-preview">' . $preview . '</div>'; // phpcs:ignore
        printf('<button type="button" class="button icf-media-select">%s</button> ', esc_html__('Select', 'idoneo-custom-fields'));
        printf('<button type="button" class="button icf-media-remove" %s>%s</button>', $has ? '' : 'style="display:none"', esc_html__('Remove', 'idoneo-custom-fields'));
        echo '</div>';
    }

    /**
     * @param mixed $value
     */
    private static function render_gallery($value, string $input_name, string $id): void
    {
        $ids = is_array($value) ? array_map('intval', $value) : array_filter(array_map('intval', explode(',', (string) $value)));
        echo '<div class="icf-gallery">';
        printf('<input type="hidden" id="%s" name="%s" value="%s" class="icf-gallery-ids" />', esc_attr($id), esc_attr($input_name), esc_attr(implode(',', $ids)));
        echo '<ul class="icf-gallery-list">';
        foreach ($ids as $aid) {
            printf('<li data-id="%d">%s<button type="button" class="icf-gallery-remove">&times;</button></li>', $aid, wp_get_attachment_image($aid, 'thumbnail')); // phpcs:ignore
        }
        echo '</ul>';
        printf('<button type="button" class="button icf-gallery-add">%s</button>', esc_html__('Add images', 'idoneo-custom-fields'));
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed                $value
     */
    private static function render_choice(array $field, $value, string $input_name, string $id, string $type): void
    {
        $choices = self::parse_choices($field['choices'] ?? '');
        $multiple = ! empty($field['multiple']);
        $selected = is_array($value) ? array_map('strval', $value) : [(string) $value];

        if ($type === 'select') {
            $name = $multiple ? $input_name . '[]' : $input_name;
            printf('<select id="%s" name="%s" class="widefat" %s>', esc_attr($id), esc_attr($name), $multiple ? 'multiple' : '');
            if (! $multiple) {
                printf('<option value="">%s</option>', esc_html__('— Select —', 'idoneo-custom-fields'));
            }
            foreach ($choices as $val => $label) {
                printf('<option value="%s" %s>%s</option>', esc_attr($val), in_array((string) $val, $selected, true) ? 'selected' : '', esc_html($label));
            }
            echo '</select>';
            return;
        }

        echo '<ul class="icf-choices">';
        foreach ($choices as $val => $label) {
            if ($type === 'checkbox') {
                printf(
                    '<li><label><input type="checkbox" name="%s[]" value="%s" %s /> %s</label></li>',
                    esc_attr($input_name),
                    esc_attr($val),
                    in_array((string) $val, $selected, true) ? 'checked' : '',
                    esc_html($label)
                );
            } else {
                printf(
                    '<li><label><input type="radio" name="%s" value="%s" %s /> %s</label></li>',
                    esc_attr($input_name),
                    esc_attr($val),
                    in_array((string) $val, $selected, true) ? 'checked' : '',
                    esc_html($label)
                );
            }
        }
        echo '</ul>';
        if ($type === 'checkbox') {
            // Ensure an empty submit clears the value.
            printf('<input type="hidden" name="%s[__empty]" value="1" />', esc_attr($input_name));
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed                $value
     */
    private static function render_post_object(array $field, $value, string $input_name, string $id): void
    {
        $post_type = ! empty($field['post_type']) ? (array) $field['post_type'] : ['post', 'page'];
        $multiple = ! empty($field['multiple']);
        $selected = is_array($value) ? array_map('intval', $value) : array_filter([(int) $value]);

        $posts = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'any',
        ]);

        $name = $multiple ? $input_name . '[]' : $input_name;
        printf('<select id="%s" name="%s" class="widefat" %s>', esc_attr($id), esc_attr($name), $multiple ? 'multiple' : '');
        if (! $multiple) {
            printf('<option value="">%s</option>', esc_html__('— Select —', 'idoneo-custom-fields'));
        }
        foreach ($posts as $p) {
            printf('<option value="%d" %s>%s</option>', $p->ID, in_array($p->ID, $selected, true) ? 'selected' : '', esc_html($p->post_title ?: '#' . $p->ID));
        }
        echo '</select>';
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed                $value
     */
    private static function render_taxonomy(array $field, $value, string $input_name, string $id): void
    {
        $taxonomy = ! empty($field['taxonomy']) ? (string) $field['taxonomy'] : 'category';
        $multiple = ! empty($field['multiple']);
        $selected = is_array($value) ? array_map('intval', $value) : array_filter([(int) $value]);

        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        if (is_wp_error($terms)) {
            $terms = [];
        }
        $name = $multiple ? $input_name . '[]' : $input_name;
        printf('<select id="%s" name="%s" class="widefat" %s>', esc_attr($id), esc_attr($name), $multiple ? 'multiple' : '');
        if (! $multiple) {
            printf('<option value="">%s</option>', esc_html__('— Select —', 'idoneo-custom-fields'));
        }
        foreach ($terms as $term) {
            printf('<option value="%d" %s>%s</option>', $term->term_id, in_array($term->term_id, $selected, true) ? 'selected' : '', esc_html($term->name));
        }
        echo '</select>';
    }

    /**
     * @param mixed $value
     */
    private static function render_link($value, string $input_name, string $id): void
    {
        $value = is_array($value) ? $value : ['url' => '', 'title' => '', 'target' => ''];
        echo '<div class="icf-link">';
        printf(
            '<p><label>%s<br><input type="url" name="%s[url]" value="%s" class="widefat" placeholder="https://..." /></label></p>',
            esc_html__('URL', 'idoneo-custom-fields'),
            esc_attr($input_name),
            esc_attr($value['url'] ?? '')
        );
        printf(
            '<p><label>%s<br><input type="text" name="%s[title]" value="%s" class="widefat" /></label></p>',
            esc_html__('Link text', 'idoneo-custom-fields'),
            esc_attr($input_name),
            esc_attr($value['title'] ?? '')
        );
        printf(
            '<p><label><input type="checkbox" name="%s[target]" value="_blank" %s /> %s</label></p>',
            esc_attr($input_name),
            checked($value['target'] ?? '', '_blank', false),
            esc_html__('Open in new tab', 'idoneo-custom-fields')
        );
        echo '</div>';
    }

    /**
     * Sanitize a submitted value according to its field type.
     *
     * @param array<string, mixed> $field
     * @param mixed                $raw
     * @return mixed
     */
    public static function sanitize(array $field, $raw)
    {
        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'textarea':
                return sanitize_textarea_field((string) $raw);

            case 'wysiwyg':
                return wp_kses_post((string) $raw);

            case 'number':
                return $raw === '' ? '' : (is_numeric($raw) ? $raw + 0 : 0);

            case 'email':
                return sanitize_email((string) $raw);

            case 'url':
            case 'oembed':
                return esc_url_raw((string) $raw);

            case 'image':
            case 'file':
            case 'post_object':
                if (is_array($raw)) {
                    return array_values(array_filter(array_map('intval', $raw)));
                }
                return $raw === '' ? '' : (int) $raw;

            case 'taxonomy':
                if (is_array($raw)) {
                    return array_values(array_filter(array_map('intval', $raw)));
                }
                return $raw === '' ? '' : (int) $raw;

            case 'gallery':
                $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
                return array_values(array_filter(array_map('intval', $ids)));

            case 'checkbox':
                if (! is_array($raw)) {
                    return [];
                }
                unset($raw['__empty']);
                return array_values(array_map('sanitize_text_field', $raw));

            case 'true_false':
                return (string) $raw === '1' ? 1 : 0;

            case 'color_picker':
                return sanitize_hex_color((string) $raw) ?: '';

            case 'link':
                return [
                    'url'    => esc_url_raw($raw['url'] ?? ''),
                    'title'  => sanitize_text_field($raw['title'] ?? ''),
                    'target' => ($raw['target'] ?? '') === '_blank' ? '_blank' : '',
                ];

            case 'select':
            case 'radio':
            case 'date_picker':
            case 'password':
            case 'text':
            default:
                if (is_array($raw)) {
                    return array_values(array_map('sanitize_text_field', $raw));
                }
                return sanitize_text_field((string) $raw);
        }
    }

    /**
     * Parse a textarea of "value : Label" lines (ACF style) into an associative array.
     *
     * @param string $raw
     * @return array<string, string>
     */
    public static function parse_choices($raw): array
    {
        $choices = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (strpos($line, ':') !== false) {
                [$val, $label] = array_map('trim', explode(':', $line, 2));
            } else {
                $val = $label = $line;
            }
            $choices[$val] = $label;
        }
        return $choices;
    }
}
