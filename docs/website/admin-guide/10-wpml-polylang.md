# Multilingual: WPML / Polylang

WP Career Board ships with WPML and Polylang configuration files out
of the box, so multilingual sites can translate job board CPTs,
taxonomies, and key strings without manual setup.

## What's translatable

| Object | WPML | Polylang |
|---|---|---|
| `wcb_job` (Jobs) | Yes | Yes |
| `wcb_application` (Applications) | Yes - but typically copied per-language to keep the original applicant context | Yes |
| `wcb_resume` (Resumes) | Yes | Yes |
| `wcb_company` (Companies) | Yes | Yes |
| `wcb_board` (Boards) | Yes | Yes |
| `wcb_credit_package` (Pro only) | Yes | Yes |
| Taxonomies (`wcb_category`, `wcb_job_type`, `wcb_location`, `wcb_experience`, `wcb_tag`) | Yes | Yes |
| Plugin admin strings | Yes, via `.po` / `.mo` | Yes, via `.po` / `.mo` |
| Block attributes (e.g. heading text on Job Listings block) | Yes | Yes |

## WPML setup

WP Career Board ships a `wpml-config.xml` at the plugin root.
Activating WPML on a site that already has the plugin active picks
up the config automatically.

To verify:

1. **WPML → Translation Management** - Jobs, Applications, Resumes,
   Companies, and Boards should all appear in the post-type selector.
2. **WPML → Settings → Custom Posts** - each WCB post type should be
   set to "Translatable - only show translated items".
3. **WPML → Taxonomy Translation** - all 5 WCB taxonomies should be
   listed and translatable.

## Polylang setup

Polylang reads the same `wpml-config.xml` as WPML (the format is
shared between the two plugins for cross-compatibility).

To verify:

1. **Languages → Settings → Custom post types and Taxonomies** -    tick the WCB post types and taxonomies under "Activate languages
   and translations management for".
2. Save.

The plugin then exposes the standard Polylang language switcher in
the admin list tables and front-end blocks.

## Front-end behavior

Job listings, the Find Jobs page, and the Apply form all respect the
current request language:

- A user browsing the site in `fr_FR` sees only French jobs in the
  listings (or all jobs if "Display all languages" is on in WPML /
  Polylang settings).
- The Apply form labels, validation messages, and confirmation
  message render in the request language.
- REST API endpoints such as `GET /wcb/v1/jobs` return language-scoped
  results automatically, because WPML and Polylang filter the
  underlying `WP_Query` for the active request language - there is no
  separate `lang` query parameter to pass.

## Translating plugin strings

The plugin's source `.pot` file lives at `languages/wp-career-board.pot`.
Translators can:

- Drop a translated `.po` / `.mo` pair under `wp-content/languages/plugins/`
  (override location).
- OR use Loco Translate, WPML's String Translation, or Polylang's
  String Translation interface to translate inline.

## Currency & locale formatting

Salary formatting respects the site locale:

- `en_US` → `$60,000–$120,000/yr`
- `fr_FR` → `60 000 €–120 000 €/an`
- `de_DE` → `60.000 €–120.000 €/Jahr`

Date formatting (job posted dates, application dates) follows
WordPress's `date_format` + `time_format` site settings, localized
per language.

## Known limitations

- **Custom field schema** - fields registered via
  `wcb_job_form_fields` are translatable using WPML's String
  Translation, but Polylang requires manual translation entries.
- **Email templates** - the email body is sent in the recipient's
  user-locale (set on each user's profile). Sites without per-user
  locales fall back to the site default.
- **AI features (Pro)** - embeddings + AI matching work
  language-agnostically, but the UI strings respect the request
  language.
