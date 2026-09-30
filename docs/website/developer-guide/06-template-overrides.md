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

## Block themes

A block theme does not use PHP page templates. WordPress renders the theme's own block templates, so edit the job and company pages in **Appearance > Editor > Templates**. The PHP copies described here apply to classic themes.

For the archives, the plugin registers these block templates so a block theme shows the plugin's listing instead of its generic blog loop. A file with the same slug in your theme takes priority.

| Template | Slug | Shows |
|---|---|---|
| Job Archive | `archive-wcb_job` | The job listings |
| Company Archive | `archive-wcb_company` | The company directory |
| Job Category, Job Type, Job Tag, Job Location, Experience Level Archive | `taxonomy-wcb_category`, `taxonomy-wcb_job_type`, `taxonomy-wcb_tag`, `taxonomy-wcb_location`, `taxonomy-wcb_experience` | The job listings for the term, titled with the term name |

On classic themes the taxonomy archives use the plugin's own template and show the same term-scoped listing.

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
3. Set the `Template version` line in your copy to the version in the plugin file's header.

The check runs each time Site Health runs its tests, so the item clears as soon as the versions match.

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
