<?php

/**
 * Evaluates field group location rules against a given context (edit screen / options page)
 * to decide whether a field group should be displayed.
 */

if (! defined('ABSPATH')) {
    exit;
}

class ICF_Location
{
    /**
     * Available location parameters for the builder UI.
     *
     * @return array<string, string>
     */
    public static function params(): array
    {
        return [
            'post_type'     => __('Post Type', 'idoneo-custom-fields'),
            'post_status'   => __('Post Status', 'idoneo-custom-fields'),
            'post'          => __('Post', 'idoneo-custom-fields'),
            'page_template' => __('Page Template', 'idoneo-custom-fields'),
            'taxonomy'      => __('Taxonomy', 'idoneo-custom-fields'),
            'options_page'  => __('Options Page', 'idoneo-custom-fields'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function operators(): array
    {
        return [
            '=='  => __('is equal to', 'idoneo-custom-fields'),
            '!='  => __('is not equal to', 'idoneo-custom-fields'),
        ];
    }

    /**
     * Choices for a given param, used to populate the value dropdown in the builder.
     *
     * @return array<string, string>
     */
    public static function choices(string $param): array
    {
        switch ($param) {
            case 'post_type':
                $types = get_post_types(['show_ui' => true], 'objects');
                $out = [];
                foreach ($types as $type) {
                    if (in_array($type->name, [ICF_Field_Group::POST_TYPE, 'attachment'], true)) {
                        continue;
                    }
                    $out[$type->name] = $type->labels->singular_name ?? $type->name;
                }
                return $out;

            case 'post_status':
                return [
                    'publish' => __('Published', 'idoneo-custom-fields'),
                    'draft'   => __('Draft', 'idoneo-custom-fields'),
                    'pending' => __('Pending', 'idoneo-custom-fields'),
                    'private' => __('Private', 'idoneo-custom-fields'),
                ];

            case 'page_template':
                $templates = wp_get_theme()->get_page_templates();
                return array_merge(['default' => __('Default Template', 'idoneo-custom-fields')], $templates);

            case 'taxonomy':
                $taxes = get_taxonomies(['show_ui' => true], 'objects');
                $out = [];
                foreach ($taxes as $tax) {
                    $out[$tax->name] = $tax->labels->singular_name ?? $tax->name;
                }
                return $out;

            case 'options_page':
                $out = [];
                foreach (ICF_Options_Page::registered() as $slug => $page) {
                    $out[$slug] = $page['title'];
                }
                return $out;

            default:
                return [];
        }
    }

    /**
     * Whether a group's location rules match the current context.
     * Rules are OR-groups of AND-conditions, but for simplicity we treat the flat
     * list as a single AND group (ACF default "and" behaviour within one group).
     *
     * @param array<int, array<string, string>> $rules
     * @param array<string, mixed>              $context
     */
    public static function match(array $rules, array $context): bool
    {
        if (empty($rules)) {
            return false;
        }

        foreach ($rules as $rule) {
            if (! self::match_rule($rule, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, string> $rule
     * @param array<string, mixed>  $context
     */
    private static function match_rule(array $rule, array $context): bool
    {
        $param = $rule['param'] ?? '';
        $operator = $rule['operator'] ?? '==';
        $value = $rule['value'] ?? '';

        $actual = $context[$param] ?? null;

        if ($actual === null) {
            // A rule referencing a param not present in this context cannot match.
            return $operator === '!=';
        }

        $is_equal = (string) $actual === (string) $value;

        return $operator === '!=' ? ! $is_equal : $is_equal;
    }
}
