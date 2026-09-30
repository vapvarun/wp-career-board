# Multilingual: WPML and Polylang

You can translate jobs, companies, resumes, job taxonomies, email subjects and email bodies with WPML or Polylang. The plugin ships a `wpml-config.xml` file, so you do not need to register anything by hand.

## What you can translate

| Content | Translatable |
|---|---|
| Jobs (`wcb_job`) | Yes |
| Companies (`wcb_company`) | Yes |
| Resumes (`wcb_resume`) | Yes |
| Applications (`wcb_application`) | No |
| Boards (`wcb_board`) | No |
| Job categories, job types, tags, locations, experience levels | Yes |
| Email sender name, sender email, subjects, bodies and footer text | Yes, as admin texts |
| Plugin interface strings | Yes, through `.po` / `.mo` files |

The Pro plugin adds no post types or taxonomies of its own. It only adds its own settings and meta to the same config.

## Set up WPML

1. Activate WPML and WP Career Board. WPML reads `wpml-config.xml` from the plugin folder.
2. Go to **WPML > Settings** and open the post type translation section.
3. Confirm Jobs, Companies and Resumes are set as translatable.
4. Open **WPML > Taxonomy Translation** to translate the five job taxonomies.

## Set up Polylang

Polylang reads the same `wpml-config.xml`. Open the Polylang settings, go to the custom post types and taxonomies section, and confirm the job board post types and taxonomies are ticked. Save.

## Translate plugin strings

The template file is `languages/wp-career-board.pot`. You can:

- Put a translated `.po` / `.mo` pair in `wp-content/languages/plugins/`.
- Use Loco Translate, WPML String Translation or Polylang string translation.

## Email language

Each email is sent in the recipient's own language, taken from the language set on their user profile.
