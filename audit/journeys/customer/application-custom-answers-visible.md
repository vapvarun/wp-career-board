---
id: application-custom-answers-visible
priority: critical
personas: candidate.figma, employer.figma, admin
requires: mu:autologin
last_verified: 2026-07-27
needs: cli
bug_ref: 10134659689
---

# Answers to a job's custom application questions are visible to the employer and admin

**Why this journey exists:** questions registered through the `wcb_application_form_fields_groups` filter rendered on the apply form and were persisted as `_wcb_application_field_<key>` postmeta — and then read by absolutely nothing. No REST envelope, no employer dashboard pane, no admin metabox, no email. Site owners collected answers that only existed in the database. The submit endpoint also read `$_POST['custom_fields']` directly, so any JSON (non-multipart) REST client's answers were silently dropped before they were even saved.

## Steps

1. Register one question via an mu-plugin fixture:
   ```php
   add_filter( 'wcb_application_form_fields_groups', fn( $g ) => array( array( 'title' => 'Screening', 'fields' => array( array( 'key' => 'notice_period', 'type' => 'text', 'label' => 'Notice period' ) ) ) ) );
   ```
2. As `candidate.figma`, apply to a job with `custom_fields[notice_period]` set → expect HTTP 201
3. `wp post meta get <app-id> _wcb_application_field_notice_period` → equals the submitted answer (sanitised)
4. `GET /wp-json/wcb/v1/applications/<app-id>` as the employer who owns the job → response has a `custom_fields` array containing `{key:"notice_period", label:"Notice period", type:"text", value:<answer>}`
5. Same GET as `admin` → identical `custom_fields` entry (admin inherits the candidate envelope)
6. `GET /wp-json/wcb/v1/employers/me/applications` as that employer → the application row carries the same `custom_fields` array
7. As `admin`, open `wp-admin/post.php?post=<app-id>&action=edit` → the Application overview metabox shows an "Application answers" section listing the label and value
8. Apply again to a second job with a **JSON** (`Content-Type: application/json`) body rather than multipart → step 3's assertion holds identically (the endpoint reads `$request->get_param()`, not `$_POST`)
9. tail debug.log diff → expect ZERO new fatal/warning lines

## Teardown

```bash
wp post delete <app-id> --force
rm wp-content/mu-plugins/wcb-qa-custom-fields.php
```

## Notes

- Persistence and reads both go through `\WCB\Core\FormCustomFields` — `save_values()` / `load_values()` / `labelled_values()`, each taking a `$key_prefix` (`ApplicationsEndpoint::FIELD_META_PREFIX`). The endpoint no longer carries its own sanitiser; a new field type only has to be taught to `FormCustomFields::sanitise_value()`.
- A field removed from the filter after the answer was stored simply stops being listed — `labelled_values()` sources labels from the live filter output, and a display surface has no label to render for an unknown key. The meta row is left untouched.
- The aggregate `_wcb_application_custom_fields` meta was dropped in 1.7.1: it was written and never read, and the per-key meta is the read path.
