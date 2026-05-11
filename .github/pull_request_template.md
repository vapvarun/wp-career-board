## Summary
<!-- 1-3 sentences: what this PR changes and why. Link to the plan/<dated>/ folder if this PR is part of a multi-PR plan. -->

## Plan reference
<!-- If this PR is part of an ongoing plan, link to the plan/ entry. Otherwise: N/A. -->

## Architecture checklist

Every box must be true (or have a baseline entry in `plan/INVARIANTS.yaml` with an `eta`):

- [ ] **U1** — No new raw `$wpdb` outside the Models layer or `activate()`
- [ ] **U2** — Every new Model class extends the plugin's Model base
- [ ] **U3** — Every new REST controller extends the plugin's Base_Controller
- [ ] **U4** — `audit/manifest.json` refreshed (or explained why not)
- [ ] **U5** — `plan/README.yaml` updated for any new file under `plan/`
- [ ] **U6** — Any new asset handle uses the plugin slug prefix
- [ ] **U7** — All new strings use the plugin text domain

<!-- Add any plugin-specific invariants below: -->
- [ ] **A**/**B**/**C**/etc — see `plan/INVARIANTS.yaml` for plugin-specific rules

If a box is intentionally false, explain here:

## Verification

- [ ] `composer arch-checks` passes (exit 0)
- [ ] `composer phpcs` passes
- [ ] `composer phpstan` passes
- [ ] Smoke-tested affected endpoints / surfaces

## Manifest

- [ ] Manifest refreshed via `/wp-plugin-onboard --refresh` if any structural change

## Notes for reviewer
