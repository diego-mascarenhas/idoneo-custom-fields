<?php

/**
 * Options pages (PRO-style): register admin pages whose field values are stored in the
 * options table instead of post meta. Field groups target a page via the
 * "Options Page" location rule. A default "Site Options" page ships enabled.
 */

if (! defined('ABSPATH')) {
    exit;
}

class ICF_Options_Page
{
    private static ?self $instance = null;

    /** @var array<string, array<string, mixed>> */
    private static array $pages = [];

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Default page so the feature is usable out of the box.
        self::add_page([
            'slug'        => 'icf-options-site',
            'title'       => __('Site Options', 'idoneo-custom-fields'),
            'menu_title'  => __('Site Options', 'idoneo-custom-fields'),
            'capability'  => 'manage_options',
            'parent'      => 'edit.php?post_type=' . ICF_Field_Group::POST_TYPE,
        ]);

        do_action('icf_register_options_pages');

        add_action('admin_menu', [$this, 'register_menus']);
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function add_page(array $args): string
    {
        $slug = sanitize_key($args['slug'] ?? '');
        if ($slug === '') {
            $slug = 'icf-options-' . wp_generate_password(6, false);
        }
        if (strpos($slug, 'icf-options') !== 0) {
            $slug = 'icf-options-' . $slug;
        }
        self::$pages[$slug] = [
            'slug'       => $slug,
            'title'      => $args['title'] ?? __('Options', 'idoneo-custom-fields'),
            'menu_title' => $args['menu_title'] ?? ($args['title'] ?? __('Options', 'idoneo-custom-fields')),
            'capability' => $args['capability'] ?? 'manage_options',
            'parent'     => $args['parent'] ?? '',
            'icon'       => $args['icon'] ?? 'dashicons-admin-generic',
            'position'   => $args['position'] ?? null,
        ];
        return $slug;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function registered(): array
    {
        return self::$pages;
    }

    public function register_menus(): void
    {
        foreach (self::$pages as $page) {
            if (! empty($page['parent'])) {
                add_submenu_page(
                    $page['parent'],
                    $page['title'],
                    $page['menu_title'],
                    $page['capability'],
                    $page['slug'],
                    [$this, 'render_page']
                );
            } else {
                add_menu_page(
                    $page['title'],
                    $page['menu_title'],
                    $page['capability'],
                    $page['slug'],
                    [$this, 'render_page'],
                    $page['icon'],
                    $page['position']
                );
            }
        }
    }

    public function render_page(): void
    {
        $slug = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (! isset(self::$pages[$slug])) {
            return;
        }
        $page = self::$pages[$slug];
        if (! current_user_can($page['capability'])) {
            return;
        }

        $this->maybe_save($slug);

        $context = ['options_page' => $slug];
        $groups = array_filter(ICF_Field_Group::all(), fn ($g) => ICF_Location::match($g['location'], $context) && ! empty($g['fields']));

        echo '<div class="wrap">';
        printf('<h1>%s</h1>', esc_html($page['title']));

        if (empty($groups)) {
            printf(
                '<p>%s</p>',
                esc_html__('No field groups are assigned to this options page yet. Create a field group and set its location to this options page.', 'idoneo-custom-fields')
            );
            echo '</div>';
            return;
        }

        echo '<form method="post" class="icf-options-form">';
        wp_nonce_field('icf_save_options_' . $slug, 'icf_options_nonce');
        $meta_box = ICF_Meta_Box::get_instance();
        foreach ($groups as $group) {
            echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html($group['title']) . '</h2></div><div class="inside">';
            $meta_box->render_group($group, [], 'option', 0);
            echo '</div></div>';
        }
        submit_button(__('Save Options', 'idoneo-custom-fields'));
        echo '</form>';
        echo '</div>';
    }

    private function maybe_save(string $slug): void
    {
        if (! isset($_POST['icf_options_nonce'])) {
            return;
        }
        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icf_options_nonce'])), 'icf_save_options_' . $slug)) {
            return;
        }
        if (! current_user_can(self::$pages[$slug]['capability'])) {
            return;
        }

        $submitted = isset($_POST['icf']) && is_array($_POST['icf']) ? wp_unslash($_POST['icf']) : [];
        $context = ['options_page' => $slug];

        foreach (ICF_Field_Group::all() as $group) {
            if (empty($group['fields']) || ! ICF_Location::match($group['location'], $context)) {
                continue;
            }
            foreach ($group['fields'] as $field) {
                if (ICF_Fields::is_presentational($field['type'] ?? 'text')) {
                    continue;
                }
                $raw = $submitted[$field['name']] ?? null;
                $value = ICF_Meta_Box::sanitize_field_value($field, $raw);
                ICF_Storage::save_value($field, $value, 'option', 0);
            }
        }

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Options saved.', 'idoneo-custom-fields') . '</p></div>';
        });
    }
}

/**
 * Public helper so themes/plugins can register options pages.
 *
 * @param array<string, mixed> $args
 */
function icf_add_options_page(array $args): string
{
    return ICF_Options_Page::add_page($args);
}
