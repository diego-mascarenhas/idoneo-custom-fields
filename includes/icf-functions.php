<?php

/**
 * Public template functions. Mirror the familiar ACF-style API so themes are easy to write.
 * All functions are prefixed icf_*; ACF-compatible aliases are provided when those
 * function names are not already defined by another plugin.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @param int|string|bool $post_id
 * @return mixed
 */
function icf_get_field(string $selector, $post_id = false)
{
    return ICF_API::get_field($selector, $post_id);
}

/**
 * Echo a field value (scalars only; arrays are ignored for safety).
 *
 * @param int|string|bool $post_id
 */
function icf_the_field(string $selector, $post_id = false): void
{
    $value = ICF_API::get_field($selector, $post_id);
    if (is_scalar($value)) {
        echo esc_html((string) $value);
    }
}

/**
 * @param int|string|bool $post_id
 * @return array<string, mixed>
 */
function icf_get_fields($post_id = false): array
{
    return ICF_API::get_fields($post_id);
}

/**
 * @param int|string|bool $post_id
 */
function icf_have_rows(string $selector, $post_id = false): bool
{
    return ICF_API::have_rows($selector, $post_id);
}

/**
 * @return array<string, mixed>
 */
function icf_the_row(): array
{
    return ICF_API::the_row();
}

/**
 * @return mixed
 */
function icf_get_sub_field(string $selector)
{
    return ICF_API::get_sub_field($selector);
}

function icf_the_sub_field(string $selector): void
{
    $value = ICF_API::get_sub_field($selector);
    if (is_scalar($value)) {
        echo esc_html((string) $value);
    }
}

function icf_get_row_layout(): ?string
{
    return ICF_API::get_row_layout();
}

function icf_reset_rows(): void
{
    ICF_API::reset_rows();
}

// ACF-compatible aliases (only if no other plugin already defines them).
if (! function_exists('get_field')) {
    function get_field($selector, $post_id = false) // phpcs:ignore
    {
        return ICF_API::get_field((string) $selector, $post_id);
    }
}
if (! function_exists('the_field')) {
    function the_field($selector, $post_id = false): void // phpcs:ignore
    {
        icf_the_field((string) $selector, $post_id);
    }
}
if (! function_exists('have_rows')) {
    function have_rows($selector, $post_id = false): bool // phpcs:ignore
    {
        return ICF_API::have_rows((string) $selector, $post_id);
    }
}
if (! function_exists('the_row')) {
    function the_row(): array // phpcs:ignore
    {
        return ICF_API::the_row();
    }
}
if (! function_exists('get_sub_field')) {
    function get_sub_field($selector) // phpcs:ignore
    {
        return ICF_API::get_sub_field((string) $selector);
    }
}
if (! function_exists('the_sub_field')) {
    function the_sub_field($selector): void // phpcs:ignore
    {
        icf_the_sub_field((string) $selector);
    }
}
if (! function_exists('get_row_layout')) {
    function get_row_layout(): ?string // phpcs:ignore
    {
        return ICF_API::get_row_layout();
    }
}
