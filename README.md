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

## Development and releases

Development is done in feature branches and merged into `main` through pull requests.
Every push to `main` runs the release workflow:

1. The three version declarations are validated.
2. If that version already has a GitHub Release, no release is created.
3. If the version is newer, the workflow builds `idoneo-custom-fields.zip`.
4. A `vX.Y.Z` GitHub Release is created and the ZIP is attached.
5. Installed copies of the plugin discover the release through WordPress' update system.

Pull requests run a separate validation workflow that checks PHP syntax, version
consistency, changelog presence, and the final ZIP before the branch is merged.

> The repository and its GitHub Releases must be public for WordPress sites to
> discover and download updates without credentials. For a private repository,
> use a separate public release repository or an authenticated update server.

Existing `1.0.0` installations need one manual replacement with `1.1.0`, because
the old package does not contain the updater yet. Updates after `1.1.0` are
discovered automatically.

To prepare a new release:

```bash
php scripts/set-version.php 1.1.0
```

Then add the matching changelog section to `readme.txt`, test the plugin, and merge
the pull request into `main`. The ZIP and release are generated on GitHub; no local
release build is required.

Validate the version and build the same package locally with:

```bash
php scripts/validate-version.php
./build-zip.sh
```

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
