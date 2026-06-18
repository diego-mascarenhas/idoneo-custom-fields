# IDONEO Custom Fields – WordPress Plugin

A **WordPress plugin** to build custom fields, repeaters, flexible content, galleries and options pages for any post type — PRO-level functionality, IDONEO branded and fully self-hosted.

> Full plugin details and changelog are in [`readme.txt`](readme.txt) (WordPress.org format).

## Requirements

- WordPress 5.8+
- PHP 7.4+

## Installation

1. Copy the `idoneo-custom-fields` folder to `wp-content/plugins/` (or upload the ZIP via Plugins → Add New → Upload Plugin).
2. In WordPress admin go to **Plugins** and activate **IDONEO Custom Fields**.
3. Go to **IDONEO Fields → Add New** to create your first field group, add fields, set the location rules and publish.

## Field types

- Basic: Text, Text Area, Number, Email, URL, Password
- Content: WYSIWYG, Image, File, Gallery, oEmbed
- Choice: Select, Checkbox, Radio, True/False
- Relational: Post Object, Taxonomy, Link
- jQuery: Date Picker, Color Picker
- Layout (PRO): Repeater, Group, Flexible Content, Tab, Message

## Developer API

```php
$value = get_field('subtitle');
the_field('subtitle');

if ( have_rows('slides') ) {
    while ( have_rows('slides') ) { the_row();
        the_sub_field('title');
    }
}

$logo = get_field('site_logo', 'option'); // options page
```

All functions are also available with the `icf_` prefix (e.g. `icf_get_field()`) to avoid collisions.

## Humano integration

Field values are exposed on the WordPress REST API (`icf_fields`) so they sync automatically with **Humano**.

## License

GPLv2 or later.
