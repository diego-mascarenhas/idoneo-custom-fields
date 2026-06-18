<?php

/**
 * Read API for field values, including loop helpers for repeater and flexible content.
 * Public theme functions in icf-functions.php delegate here.
 */

if (! defined('ABSPATH')) {
    exit;
}

class ICF_API
{
    /** @var array<int, array{rows: array, index: int}> Loop stack for have_rows()/the_row(). */
    private static array $loop = [];

    /**
     * Resolve a post id / store from a selector argument.
     *
     * @param int|string|bool $id
     * @return array{store:string, object_id:int}
     */
    public static function resolve($id): array
    {
        if ($id === 'option' || $id === 'options') {
            return ['store' => 'option', 'object_id' => 0];
        }
        if ($id === false || $id === null || $id === '') {
            return ['store' => 'post', 'object_id' => (int) get_the_ID()];
        }
        return ['store' => 'post', 'object_id' => (int) $id];
    }

    /**
     * Get a single field value.
     *
     * @param int|string|bool $id
     * @return mixed
     */
    public static function get_field(string $selector, $id = false)
    {
        // Inside a have_rows() loop, read from the current row.
        if (! empty(self::$loop)) {
            $current = end(self::$loop);
            $row = $current['rows'][$current['index']] ?? [];
            if (is_array($row) && array_key_exists($selector, $row)) {
                return $row[$selector];
            }
        }

        $target = self::resolve($id);
        if ($target['store'] === 'option') {
            $value = get_option('icf_opt_' . $selector, null);
        } else {
            $value = get_post_meta($target['object_id'], $selector, true);
        }
        return $value === '' ? null : $value;
    }

    /**
     * Begin (or continue) a repeater / flexible content loop.
     *
     * @param int|string|bool $id
     */
    public static function have_rows(string $selector, $id = false): bool
    {
        $target = self::resolve($id);
        $top_index = count(self::$loop) - 1;

        // 1) Continuation of the loop currently on top of the stack (same selector + object).
        if ($top_index >= 0) {
            $top = self::$loop[$top_index];
            if ($top['selector'] === $selector && $top['object_id'] === $target['object_id'] && $top['store'] === $target['store']) {
                if ($top['index'] + 1 < count($top['rows'])) {
                    return true;
                }
                array_pop(self::$loop);
                return false;
            }
        }

        // 2) Nested loop: read sub-rows from the current row of the parent frame.
        $rows = null;
        if ($top_index >= 0) {
            $parent = self::$loop[$top_index];
            $parent_row = $parent['rows'][$parent['index']] ?? [];
            if (is_array($parent_row) && isset($parent_row[$selector]) && is_array($parent_row[$selector])) {
                $rows = array_values($parent_row[$selector]);
            }
        }

        // 3) Fresh top-level loop from storage.
        if ($rows === null) {
            $value = $target['store'] === 'option'
                ? get_option('icf_opt_' . $selector, null)
                : get_post_meta($target['object_id'], $selector, true);
            $rows = is_array($value) ? array_values($value) : [];
        }

        if (empty($rows)) {
            return false;
        }

        self::$loop[] = [
            'selector'  => $selector,
            'store'     => $target['store'],
            'object_id' => $target['object_id'],
            'rows'      => $rows,
            'index'     => -1,
        ];
        return true;
    }

    public static function the_row(): array
    {
        $i = count(self::$loop) - 1;
        if ($i < 0) {
            return [];
        }
        self::$loop[$i]['index']++;
        return self::$loop[$i]['rows'][self::$loop[$i]['index']] ?? [];
    }

    /**
     * @return mixed
     */
    public static function get_sub_field(string $selector)
    {
        if (empty(self::$loop)) {
            return null;
        }
        $current = end(self::$loop);
        $row = $current['rows'][$current['index']] ?? [];
        return is_array($row) && array_key_exists($selector, $row) ? $row[$selector] : null;
    }

    public static function get_row_layout(): ?string
    {
        if (empty(self::$loop)) {
            return null;
        }
        $current = end(self::$loop);
        $row = $current['rows'][$current['index']] ?? [];
        return is_array($row) ? ($row['acf_fc_layout'] ?? null) : null;
    }

    /**
     * Reset the loop stack (safety valve for templates that break out early).
     */
    public static function reset_rows(): void
    {
        self::$loop = [];
    }

    /**
     * All field values for an object, keyed by field name (top-level fields only).
     *
     * @param int|string|bool $id
     * @return array<string, mixed>
     */
    public static function get_fields($id = false): array
    {
        $target = self::resolve($id);
        $out = [];
        foreach (ICF_Field_Group::all() as $group) {
            foreach ($group['fields'] as $field) {
                if (ICF_Fields::is_presentational($field['type'] ?? 'text')) {
                    continue;
                }
                $name = $field['name'];
                $value = $target['store'] === 'option'
                    ? get_option('icf_opt_' . $name, null)
                    : get_post_meta($target['object_id'], $name, true);
                if ($value !== '' && $value !== null) {
                    $out[$name] = $value;
                }
            }
        }
        return $out;
    }
}
