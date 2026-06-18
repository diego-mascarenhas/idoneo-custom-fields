<?php

/**
 * Exposes custom field values on the WordPress REST API so external systems (e.g. Humano)
 * can read and write them. Adds an "icf_fields" field to every relevant post type that
 * returns a flat map of field_name => value, and accepts the same shape on write.
 */

if (! defined('ABSPATH')) {
    exit;
}

class ICF_REST
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
        add_action('rest_api_init', [$this, 'register']);
    }

    public function register(): void
    {
        foreach ($this->target_post_types() as $post_type) {
            register_rest_field($post_type, 'icf_fields', [
                'get_callback'    => [$this, 'get_value'],
                'update_callback' => [$this, 'update_value'],
                'schema'          => [
                    'description' => __('IDONEO custom field values keyed by field name.', 'idoneo-custom-fields'),
                    'type'        => 'object',
                    'context'     => ['view', 'edit'],
                ],
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function target_post_types(): array
    {
        $types = get_post_types(['show_ui' => true], 'names');
        unset($types[ICF_Field_Group::POST_TYPE], $types['attachment']);
        return array_values($types);
    }

    /**
     * @param array<string, mixed> $post_arr
     * @return array<string, mixed>
     */
    public function get_value(array $post_arr): array
    {
        $post_id = (int) ($post_arr['id'] ?? 0);
        if ($post_id === 0) {
            return [];
        }
        return ICF_API::get_fields($post_id);
    }

    /**
     * @param mixed   $value
     * @param WP_Post $post
     */
    public function update_value($value, $post)
    {
        if (! is_array($value)) {
            return true;
        }
        if (! current_user_can('edit_post', $post->ID)) {
            return new WP_Error('icf_forbidden', __('You cannot edit this post.', 'idoneo-custom-fields'), ['status' => 403]);
        }

        foreach ($value as $name => $raw) {
            $field = ICF_Field_Group::find_field((string) $name);
            if (! $field) {
                continue;
            }
            $clean = ICF_Meta_Box::sanitize_field_value($field, $raw);
            ICF_Storage::save_value($field, $clean, 'post', (int) $post->ID);
        }
        return true;
    }
}
