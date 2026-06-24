=== IDONEO Custom Fields ===
Contributors: idoneo
Tags: custom fields, acf, repeater, flexible content, options page, meta box
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build custom fields, repeaters, flexible content, galleries and options pages for any post type. PRO-level functionality, IDONEO branded.

== Description ==

IDONEO Custom Fields lets you attach custom fields to any post type, page, or options page — no code required. It mirrors the workflow of the popular field plugins (including PRO features) while staying fully self-hosted and IDONEO branded.

**Field types**

* Basic: Text, Text Area, Number, Email, URL, Password
* Content: WYSIWYG, Image, File, Gallery, oEmbed
* Choice: Select, Checkbox, Radio, True/False
* Relational: Post Object, Taxonomy, Link
* jQuery: Date Picker, Color Picker
* Layout (PRO): Repeater, Group, Flexible Content, Tab, Message

**PRO-level features included**

* Repeater fields (unlimited nesting)
* Flexible Content with multiple layouts
* Gallery field
* Group field
* Options pages (a "Site Options" page ships ready to use)
* Conditional logic (show/hide a field based on another field's value)
* Location rules (post type, post status, specific post, page template, taxonomy, options page)

**Developer API**

Use familiar template tags in your theme:

`<?php
$value = get_field('subtitle');
the_field('subtitle');

if ( have_rows('slides') ) {
    while ( have_rows('slides') ) {
        the_row();
        the_sub_field('title');
        $img = get_sub_field('image'); // attachment ID
    }
}

// Flexible content
if ( have_rows('sections') ) {
    while ( have_rows('sections') ) {
        the_row();
        if ( get_row_layout() === 'hero' ) {
            the_sub_field('headline');
        }
    }
}

// Options page values
$logo = get_field('site_logo', 'option');
?>`

All functions are also available with the `icf_` prefix (e.g. `icf_get_field()`), so they never collide with another plugin.

== Installation ==

1. Upload the `idoneo-custom-fields` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **IDONEO Fields → Add New** to create your first field group.
4. Add fields, set the location rules, and publish.

== Frequently Asked Questions ==

= Where are values stored? =
Post field values are stored in standard post meta (one row per top-level field). Options page values are stored in the options table prefixed with `icf_opt_`. Repeater, group and flexible content values are stored as structured arrays.

= Does it conflict with ACF? =
No. The `get_field()` family of functions is only declared if no other plugin already defines them. Always-safe `icf_*` functions are also provided.

== Changelog ==

= 1.1.2 =
* Verified the complete pull request, release, and WordPress update flow.

= 1.1.1 =
* Verified the public GitHub Releases update channel.

= 1.1.0 =
* Added automatic update checks through public GitHub Releases.
* Added automated validation, packaging, and release workflows for GitHub Actions.
* Added portable version validation and ZIP build tools.

= 1.0.0 =
* Initial release: field builder, location rules, repeater, flexible content, group, gallery, options pages, conditional logic, and the developer API.
