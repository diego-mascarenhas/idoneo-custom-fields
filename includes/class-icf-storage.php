<?php

/**
 * Abstraction over where field values live: post meta for posts, or the options table
 * for options pages. Values may be scalars or nested arrays (repeater/flexible/group).
 */

if (! defined('ABSPATH')) {
    exit;
}

class ICF_Storage
{
    private const OPTION_PREFIX = 'icf_opt_';

    /**
     * @param array<string, mixed> $field
     * @return mixed
     */
    public static function get_value(array $field, string $store, int $object_id)
    {
        $name = $field['name'] ?? '';
        if ($name === '') {
            return '';
        }

        if ($store === 'option') {
            $value = get_option(self::OPTION_PREFIX . $name, null);
        } else {
            $value = get_post_meta($object_id, $name, true);
        }

        if ($value === null || $value === false || $value === '') {
            return self::is_array_type($field) ? [] : '';
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed                $value
     */
    public static function save_value(array $field, $value, string $store, int $object_id): void
    {
        $name = $field['name'] ?? '';
        if ($name === '') {
            return;
        }

        if ($store === 'option') {
            update_option(self::OPTION_PREFIX . $name, $value);
            return;
        }

        if ($value === '' || $value === [] || $value === null) {
            delete_post_meta($object_id, $name);
            return;
        }
        update_post_meta($object_id, $name, wp_slash($value));
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function is_array_type(array $field): bool
    {
        $type = $field['type'] ?? 'text';
        if (in_array($type, ['repeater', 'flexible_content', 'group', 'gallery', 'checkbox', 'link'], true)) {
            return true;
        }
        return ! empty($field['multiple']);
    }
}
