<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Provides plugin updates from GitHub Releases.
 */
final class ICF_Updater
{
    private const API_URL = 'https://api.github.com/repos/diego-mascarenhas/idoneo-custom-fields/releases/latest';
    private const REPOSITORY_URL = 'https://github.com/diego-mascarenhas/idoneo-custom-fields';
    private const ASSET_NAME = 'idoneo-custom-fields.zip';
    private const CACHE_KEY = 'icf_latest_github_release';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'clear_cache'], 10, 2);
    }

    /**
     * Adds the latest GitHub release to WordPress' plugin update data.
     *
     * @param mixed $transient Update transient.
     * @return mixed
     */
    public function check_for_update($transient)
    {
        if (! is_object($transient)) {
            return $transient;
        }

        $release = $this->get_release();
        if (null === $release || version_compare(ICF_VERSION, $release['version'], '>=')) {
            return $transient;
        }

        $plugin_file = plugin_basename(ICF_PLUGIN_FILE);
        $transient->response[$plugin_file] = (object) [
            'id'           => self::REPOSITORY_URL,
            'slug'         => dirname($plugin_file),
            'plugin'       => $plugin_file,
            'new_version'  => $release['version'],
            'url'          => $release['url'],
            'package'      => $release['package'],
            'requires'     => '5.8',
            'requires_php' => '7.4',
            'tested'       => '6.6',
        ];

        return $transient;
    }

    /**
     * Supplies the "View details" modal shown by WordPress.
     *
     * @param false|object|array $result Existing result.
     * @param string             $action API action.
     * @param object             $args   API arguments.
     * @return false|object|array
     */
    public function plugin_information($result, string $action, object $args)
    {
        if ('plugin_information' !== $action || empty($args->slug)) {
            return $result;
        }

        $plugin_slug = dirname(plugin_basename(ICF_PLUGIN_FILE));
        if ($plugin_slug !== $args->slug) {
            return $result;
        }

        $release = $this->get_release();
        if (null === $release) {
            return $result;
        }

        return (object) [
            'name'          => 'IDONEO Custom Fields',
            'slug'          => $plugin_slug,
            'version'       => $release['version'],
            'author'        => '<a href="https://idoneo.com">IDONEO</a>',
            'homepage'      => self::REPOSITORY_URL,
            'requires'      => '5.8',
            'requires_php'  => '7.4',
            'tested'        => '6.6',
            'download_link' => $release['package'],
            'sections'      => [
                'description' => 'Build custom fields, repeaters, flexible content, galleries and options pages for any post type.',
                'changelog'   => wp_kses_post($release['notes']),
            ],
        ];
    }

    /**
     * Clears the cached release after this plugin is updated.
     *
     * @param mixed $upgrader Upgrader instance.
     * @param array $options  Upgrade options.
     */
    public function clear_cache($upgrader, array $options): void
    {
        if (
            'update' !== ($options['action'] ?? '')
            || 'plugin' !== ($options['type'] ?? '')
            || empty($options['plugins'])
            || ! in_array(plugin_basename(ICF_PLUGIN_FILE), (array) $options['plugins'], true)
        ) {
            return;
        }

        delete_site_transient(self::CACHE_KEY);
    }

    /**
     * Gets and normalizes the latest public GitHub release.
     *
     * @return array{version:string,url:string,package:string,notes:string}|null
     */
    private function get_release(): ?array
    {
        $cached = get_site_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            self::API_URL,
            [
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'IDONEO-Custom-Fields/' . ICF_VERSION,
                ],
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($data) || empty($data['tag_name']) || empty($data['assets'])) {
            return null;
        }

        $package = '';
        foreach ($data['assets'] as $asset) {
            if (self::ASSET_NAME === ($asset['name'] ?? '')) {
                $package = esc_url_raw($asset['browser_download_url'] ?? '');
                break;
            }
        }

        $version = ltrim((string) $data['tag_name'], 'vV');
        if (
            '' === $package
            || 'github.com' !== wp_parse_url($package, PHP_URL_HOST)
            || ! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)
        ) {
            return null;
        }

        $release = [
            'version' => $version,
            'url'     => esc_url_raw($data['html_url'] ?? self::REPOSITORY_URL),
            'package' => $package,
            'notes'   => $this->format_release_notes((string) ($data['body'] ?? '')),
        ];

        set_site_transient(self::CACHE_KEY, $release, self::CACHE_TTL);

        return $release;
    }

    private function format_release_notes(string $notes): string
    {
        if ('' === trim($notes)) {
            return '<p>See the GitHub release for details.</p>';
        }

        return wpautop(esc_html($notes));
    }
}
