<?php

/**
 * Plugin Name: IDONEO Custom Fields
 * Description: Build custom fields, repeaters, flexible content, galleries and options pages for any post type. PRO-level functionality, IDONEO branded.
 * Version: 1.1.0
 * Plugin URI: https://github.com/diego-mascarenhas/idoneo-custom-fields
 * Update URI: https://github.com/diego-mascarenhas/idoneo-custom-fields
 * Author: IDONEO
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: idoneo-custom-fields
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('ICF_VERSION', '1.1.0');
define('ICF_PLUGIN_FILE', __FILE__);
define('ICF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ICF_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ICF_PLUGIN_DIR . 'includes/class-icf-fields.php';
require_once ICF_PLUGIN_DIR . 'includes/class-icf-storage.php';
require_once ICF_PLUGIN_DIR . 'includes/class-icf-field-group.php';
require_once ICF_PLUGIN_DIR . 'includes/class-icf-location.php';
require_once ICF_PLUGIN_DIR . 'includes/class-icf-builder.php';
require_once ICF_PLUGIN_DIR . 'includes/class-icf-meta-box.php';
require_once ICF_PLUGIN_DIR . 'includes/class-icf-options-page.php';
require_once ICF_PLUGIN_DIR . 'includes/class-icf-api.php';
require_once ICF_PLUGIN_DIR . 'includes/class-icf-rest.php';
require_once ICF_PLUGIN_DIR . 'includes/class-icf-updater.php';
require_once ICF_PLUGIN_DIR . 'includes/icf-functions.php';

function icf_init(): void
{
    load_plugin_textdomain('idoneo-custom-fields', false, dirname(plugin_basename(__FILE__)) . '/languages');

    ICF_Field_Group::get_instance();
    ICF_Builder::get_instance();
    ICF_Meta_Box::get_instance();
    ICF_Options_Page::get_instance();
    ICF_REST::get_instance();
    ICF_Updater::get_instance();
}
add_action('plugins_loaded', 'icf_init');

function icf_activate(): void
{
    // Register the CPT so its rewrite rules exist, then flush.
    ICF_Field_Group::register_post_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'icf_activate');

function icf_deactivate(): void
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'icf_deactivate');
