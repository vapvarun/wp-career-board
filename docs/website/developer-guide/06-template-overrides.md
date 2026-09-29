# Template Overrides

A theme can replace the page template WP Career Board uses for a single
job, company or resume. Copy the plugin's template into your theme, edit
the copy, and WordPress uses yours instead.

## Which templates can be overridden

| Page | Copy this plugin file | To this path in your theme |
|---|---|---|
| Single job | `wp-career-board/modules/jobs/templates/single-wcb_job.php` | `single-wcb_job.php` |
| Single company | `wp-career-board/modules/employers/templates/single-wcb_company.php` | `single-wcb_company.php` |
| Single resume (Pro) | `wp-career-board-pro/modules/resume/templates/single-wcb_resume.php` | `single-wcb_resume.php` |

A child theme's copy wins over its parent theme's copy, and either wins
over the plugin's.

## Keep your copy up to date

Each template carries a version line in its header:

```php
 * Template version: 1.0
```

When a plugin update changes a template's markup, that number goes up.
WP Career Board compares your copy with the plugin's and reports any copy
that is older, or has no version line, under **Tools > Site Health >
Recommended improvements**. The item names your file and the plugin file
to compare it with.

To bring a copy up to date:

1. Open the plugin file listed in Site Health next to your copy.
2. Carry your customisations over to a fresh copy of the plugin file
   (or merge the plugin's changes into your copy).
3. Set the `Template version` line in your copy to the plugin's version.

Site Health re-checks on every visit, so the item clears as soon as the
versions match.

## Registering another template

An add-on that ships its own overridable template can add it to the check:

```php
add_filter( 'wcb_overridable_templates', function ( array $templates ) {
    $templates['single-my_type.php'] = MY_ADDON_DIR . 'templates/single-my_type.php';
    return $templates;
} );
```

The key is the file name WordPress looks for in the theme; the value is
the full path of the add-on's own copy, which must carry a
`Template version` line.

## Filters

| Filter | Purpose |
|---|---|
| `wcb_overridable_templates` | Add or remove templates from the version check. |
| `wcb_template_override_docs_url` | Change the "Learn how template overrides work" link in Site Health. |
