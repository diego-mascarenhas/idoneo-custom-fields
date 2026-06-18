<?php

/**
 * Registers the "Field Group" custom post type and provides read/write helpers for the
 * field group configuration (fields + location rules) stored in post meta as JSON.
 */

if (! defined('ABSPATH')) {
    exit;
}

class ICF_Field_Group
{
    private static ?self $instance = null;

    public const POST_TYPE = 'icf_field_group';
    public const META_FIELDS = '_icf_fields';
    public const META_LOCATION = '_icf_location';
    public const META_OPTIONS = '_icf_options';

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'column_content'], 10, 2);
    }

    public static function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'               => __('Custom Fields', 'idoneo-custom-fields'),
                'singular_name'      => __('Field Group', 'idoneo-custom-fields'),
                'add_new'            => __('Add New', 'idoneo-custom-fields'),
                'add_new_item'       => __('Add New Field Group', 'idoneo-custom-fields'),
                'edit_item'          => __('Edit Field Group', 'idoneo-custom-fields'),
                'new_item'           => __('New Field Group', 'idoneo-custom-fields'),
                'view_item'          => __('View Field Group', 'idoneo-custom-fields'),
                'search_items'       => __('Search Field Groups', 'idoneo-custom-fields'),
                'not_found'          => __('No field groups found', 'idoneo-custom-fields'),
                'menu_name'          => __('IDONEO Fields', 'idoneo-custom-fields'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-feedback',
            'menu_position'       => 80,
            'capability_type'     => 'post',
            'capabilities'        => ['create_posts' => 'manage_options'],
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'supports'            => ['title'],
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'show_in_rest'        => false,
        ]);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['icf_fields'] = __('Fields', 'idoneo-custom-fields');
                $new['icf_location'] = __('Location', 'idoneo-custom-fields');
            }
        }
        return $new;
    }

    public function column_content(string $column, int $post_id): void
    {
        if ($column === 'icf_fields') {
            $fields = self::get_fields($post_id);
            echo esc_html((string) count($fields));
        }
        if ($column === 'icf_location') {
            $rules = self::get_location($post_id);
            $parts = [];
            foreach ($rules as $rule) {
                $parts[] = ($rule['param'] ?? '') . ' ' . ($rule['operator'] ?? '') . ' ' . ($rule['value'] ?? '');
            }
            echo esc_html($parts ? implode(' / ', $parts) : '—');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_fields(int $group_id): array
    {
        $json = get_post_meta($group_id, self::META_FIELDS, true);
        $data = is_string($json) && $json !== '' ? json_decode($json, true) : (is_array($json) ? $json : []);
        return is_array($data) ? $data : [];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function get_location(int $group_id): array
    {
        $json = get_post_meta($group_id, self::META_LOCATION, true);
        $data = is_string($json) && $json !== '' ? json_decode($json, true) : (is_array($json) ? $json : []);
        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_options(int $group_id): array
    {
        $json = get_post_meta($group_id, self::META_OPTIONS, true);
        $data = is_string($json) && $json !== '' ? json_decode($json, true) : (is_array($json) ? $json : []);
        return is_array($data) ? $data : [];
    }

    /**
     * All published field groups with their parsed config.
     *
     * @return array<int, array{id:int, title:string, fields:array, location:array, options:array}>
     */
    public static function all(): array
    {
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ]);

        $groups = [];
        foreach ($posts as $post) {
            $groups[] = [
                'id'       => $post->ID,
                'title'    => $post->post_title,
                'fields'   => self::get_fields($post->ID),
                'location' => self::get_location($post->ID),
                'options'  => self::get_options($post->ID),
            ];
        }
        return $groups;
    }

    /**
     * Find a top-level field definition by name across all published field groups.
     *
     * @return array<string, mixed>|null
     */
    public static function find_field(string $name): ?array
    {
        foreach (self::all() as $group) {
            foreach ($group['fields'] as $field) {
                if (($field['name'] ?? '') === $name) {
                    return $field;
                }
            }
        }
        return null;
    }
}
