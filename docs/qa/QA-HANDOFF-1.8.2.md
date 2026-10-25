# QA hand-off - WP Career Board 1.8.2

Every card in Basecamp **Ready for Testing** for Free + Pro 1.8.2, grouped by the journey that re-checks it. Generated from the board on 2026-10-24. The card's own comment stays the full brief: cause, fix, proof against the old code, and the code-level and browser steps.

## Before you start

- **Branch:** `1.8.2` in both `wp-career-board` and `wp-career-board-pro` (`git fetch && git checkout 1.8.2 && git pull`). Never `main`.
- **Code level:** in each plugin run `composer ci:no-journeys` (PHPStan, WPCS, coding rules, manifest and route gates). Both pass on the commits below.
- **Browser level:** check every card at 1440 and 390 wide. Log in with `?autologin=<login>`. Prefix test data `QA-RFT` and delete it afterwards. Personas: `varundubey` (admin), `mateo.ruiz` and `ahmed.karim` (employers), `adrian.kowalski` (candidate).
- **Per card:** open the card, run its **QA - code level** and **QA - browser** steps, then walk the journey named here. The journey's `card:<id>` line is the regression check. Pass: move to Done. Fail: move to Bugs with what you saw.
- **By design** rows were answered with a reason, plus an extension point where useful, instead of a code change. Verify the stated behaviour, not the original request.

## Round 3 - 2026-10-25 (current Ready for Testing)

The last Bugs and Possible Bugs cards, each replicated first. Commits on `1.8.2`: Free `989933f`, `7d89f49`, `598c1b1`, `2647822`; Pro `a485b5f`, `68fcaed`, `8481437` (bundles Credits SDK `v1.11.0`, PR vapvarun/wbcom-credits-sdk#33). `composer ci:no-journeys` passes in both plugins. Each card's comment has the code-level and browser steps.

| Card | Title | Journey | Runbook | Kind |
|---|---|---|---|---|
| [10439797157](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10439797157) | Pro Resumes and Listings settings show two save notices | admin/admin-settings-tabs-render 8f | `D.own-notices-visible` | New (filed in round 2) |
| [10422002927](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10422002927) | Email verification: a link opened in another browser lands on a bare login page | customer/signup-email-verification 12a | `D.verify-other-device` | Card as written did not replicate; gap fixed |
| [10422877832](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10422877832) | Field Builder "File upload" type is only a URL box | Pro:admin/walkthrough-field-builder 13c | `D.file-field-label` | Relabelled "File link (URL)" (owner) |
| [10437969521](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10437969521) | Credit ledger notes stored in English | Pro:customer/walkthrough-credit-system 12a | `D.ledger-notes-i18n` | SDK 1.11.0 + Pro (owner) |
| [10439881291](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10439881291) | Credits SDK 1.11.0 bundle in Career Board Pro 1.8.2 | money paths on the card | - | Bundle bump |

## Round 2 - 2026-10-25

The 2026-10-24 pass (round 1, below) sent six cards back to Bugs. They are re-fixed here, together with five new cards. Commits: Free `44d061c`, Pro `e5e89b3`. `composer ci:no-journeys` passes in both plugins. Each card's comment shows the old-code failure and the new-code result. 10417521347's comment also corrects an earlier hand-off that wrongly said the sort worked.

| Card | Title | Journey | Runbook | Kind |
|---|---|---|---|---|
| [10417317114](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417317114) | [Candidates]No Separate Filter for Suspended Candidates | admin/admin-lists-views-hold | `D.admin-lists` | Re-fix |
| [10417326173](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417326173) | [Employers]No Filter for Banned Employers | admin/admin-lists-views-hold | `D.admin-lists` | Re-fix |
| [10417521347](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417521347) | [Taxonomy Terms] Term list tables have no Order column and cannot be reordered without opening each term | admin/admin-lists-views-hold | `D.admin-lists` | Re-fix |
| [10402937387](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10402937387) | [WP Career Board - Jobs Directory] Multi-Currency Sort & Filter Defects: "Highest Salary" compares raw numeric values across currencies, and salary filter silently drops non-default currency listings | customer/salary-filter-other-currencies | `D.salary-other-currencies` | Re-fix |
| [10421957903](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421957903) | [Field Builder] Required custom fields are not validated on Job, Company, Candidate and Resume submissions | Pro:admin/walkthrough-field-builder | `D.job-write-contract` | Re-fix |
| [10409511899](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10409511899) | Find Candidates cards ignore the resume headline and location | Pro:customer/pro-my-resumes-row-actions | `D.pro-candidate-card-headline` | Re-fix |
| [10439524614](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10439524614) | [Free] Kadence: Job Map My location icon turns white on a light button on hover | customer/classic-theme-controls-hold | `D.classic-theme-controls` | Fix |
| [10439524512](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10439524512) | [Pro] Settings > Credits: Stripe gateway fields run past the block edge at 1024px | admin/admin-settings-tabs-render | `D.settings-fit` | Fix |
| [10439586916](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10439586916) | Emails tab: Save changes shows Settings saved but discards an edited Subject | admin/admin-emails-page-renders | `D.emails-one-save` | Fix |
| [10439586892](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10439586892) | Post a Job: board-scoped custom field groups never render | customer/walkthrough-employer-post-job | `D.job-fields-per-board` | Fix |
| [10439524696](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10439524696) | [Free] Post a Job has no page heading, unlike every other front screen | customer/dashboard-feedback-holds | `D.post-job-heading` | Fix |

**Theme checks:** check 10439524614 on Kadence. To keep the shared site's theme unchanged, a cookie-scoped `stylesheet`/`template` filter in a temporary mu-plugin works well. **Data:** 10439586892 needs two boards, a global group excluded on one board, and a board-scoped group created through `POST /wcb/v1/fields/groups`.

Round 1 (2026-10-24): **119 cards:** 115 fixes, 4 by design.


## admin-pro-boards-list-renders

Pro `audit/journeys/admin/admin-pro-boards-list-renders.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10418288730](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10418288730) | [Boards] Delete confirmation message shows empty title: Deleted "". Removed 5 stage(s); unlinked 0 job(s). | `276b11f` `7013828` | `D.no-untitled-records` | Fix |
| [10418220510](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10418220510) | [Board] Can Be Published Without Filling Required Fields | `276b11f` `7013828` | `D.no-untitled-records` | Fix |
| [10409512100](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10409512100) | Boards Jobs column counts pending, closed, draft and trashed jobs | `dd33d5b` `e58076d` | `D.boards-jobs-live-count` | Fix |
| [10407360943](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10407360943) | Jobs saved in wp-admin are missing from the board filter and the Boards count | `dd33d5b` `e58076d` | `D.jobs-store-default-board` | Fix |

## admin-pro-migration-import

Pro `audit/journeys/admin/admin-pro-migration-import.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10421607707](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421607707) | [Pro] [UX][Medium] Admin credit adjustment: the 'User #N balance' result is hidden by Free's notice suppression, so the admin gets no confirmation | see card | `D.own-notices-visible` | Fix |
| [10403057955](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10403057955) | Import feedback notices are invisible due to missing form action redirect and tab state reset | see card | `D.own-notices-visible` | Fix |
| [10403056516](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10403056516) | CSV Job Import Fails on Windows Server Environments | see card | `D.pro-csv-import-windows-path` | Fix |

## admin-resume-meta-box-remove-entry

Pro `audit/journeys/admin/admin-resume-meta-box-remove-entry.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10403985914](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10403985914) | Admin Resume Meta Box Fails to Remove or Clear Section Entries (University, Skills, etc.) Upon Saving | see card | `D.pro-resume-meta-box-remove` | Fix |

## pro-map-provider-sets-tiles

Pro `audit/journeys/admin/pro-map-provider-sets-tiles.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10407369087](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10407369087) | Selecting Google Maps / Mapbox in Map Settings does not change frontend visual map (hardcoded to Leaflet / OpenStreetMap) | `4325590` `73c18e4` `a7755b8` | `D.pro-map-provider-tiles` | Fix |

## walkthrough-analytics

Pro `audit/journeys/admin/walkthrough-analytics.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10437433879](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10437433879) | [Pro] Analytics: 'Top jobs by views' table doesn't fill its card at 390px | `03e3297` `0c39fb9` | `D.plain-admin-tables-phone` | Fix |

## walkthrough-csv-migration

Pro `audit/journeys/admin/walkthrough-csv-migration.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10420281295](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10420281295) | [Pro] CSV import result: Review pending jobs opens the stock WordPress list, not Career Board Jobs | `44bf0d7` `93ed042` | `D.csv-import-status-honest` | Fix |
| [10420281094](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10420281094) | [Pro] CSV import card says all imported jobs are set to Pending, but the status column is honoured | `44bf0d7` `93ed042` | `D.csv-import-status-honest` | Fix |

## walkthrough-field-builder

Pro `audit/journeys/admin/walkthrough-field-builder.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10421191546](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421191546) | [Pro] Visibility on Application questions does nothing: an Employer only question is still shown to applicants | `aa24344` `e4d87aa` | `D.application-questions-no-visibility` | Fix |

## pro-my-resumes-row-actions

Pro `audit/journeys/customer/pro-my-resumes-row-actions.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10437714777](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10437714777) | [Pro] Deleting a resume leaves its uploaded CV file on the server | `340637a` `7327599` | `D.pro-resume-delete-removes-cv` | Fix |
| [10409511899](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10409511899) | Find Candidates cards ignore the resume headline and location | `4a5c6dc` | `D.pro-candidate-card-headline` | Fix |
| [10417744413](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417744413) | [Pro] Resume builder shows Years of experience blank for any stored value that is not one of its five options | `4a5c6dc` | `D.pro-resume-years-number` | Fix |
| [10417744155](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417744155) | [Pro] Upload CV: the new row shows Edit (built-resume actions) until the page is reloaded | `4a5c6dc` `e7c7d5d` | `D.pro-my-resumes-row-actions` | Fix |
| [10417743899](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417743899) | [Pro] My Resumes: a built resume turns into an "Open PDF" row once a PDF is attached, losing Edit and View | `4a5c6dc` `613f725` `e7c7d5d` | `D.pro-my-resumes-row-actions` | Fix |

## pro-notifications-bell-unread

Pro `audit/journeys/customer/pro-notifications-bell-unread.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10407361055](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10407361055) | Bell notifications link to the home page when they were created before the dashboard pages existed | `4144f38` `e3c3312` | `D.bell-links-before-pages` | Fix |

## pro-resume-upload-mime

Pro `audit/journeys/customer/pro-resume-upload-mime.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10417744280](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417744280) | [Pro] A DOC or DOCX CV upload gets no Open PDF or Re-upload, and Edit opens an empty resume builder | `4a5c6dc` `613f725` | `D.pro-my-resumes-row-actions` | Fix |

## walkthrough-ai-hiring-tools

Pro `audit/journeys/customer/walkthrough-ai-hiring-tools.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10404086478](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10404086478) | [Pro] [i18n][Low] AI-written job descriptions, cover letters and match reasons are always English | `1ba052d` | `D.pro-ai-reply-language` | Fix |
| [10421191334](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421191334) | [Pro] Generate with AI shows on the job form when only a Matching provider is set, but the generator needs a Completion provider | `1ba052d` `ef15f42` | `D.pro-ai-description-gate` | Fix |

## walkthrough-find-candidates

Pro `audit/journeys/customer/walkthrough-find-candidates.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10422481384](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10422481384) | [Resume Settings] ResumeAccess::normalize() fails OPEN — any unexpected value silently becomes 'public' | `a9ca0ba` | `D.approved-employers-only` | Fix |
| [10422480510](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10422480510) | [Resume Settings] "Approved employers only" also grants Editors and Shop Managers candidate directory + profile access | `a9ca0ba` | `D.approved-employers-only` | Fix |

## admin-candidates-page-renders

Free `audit/journeys/admin/admin-candidates-page-renders.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10420280930](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10420280930) | [Free] Candidates: the Applications link opens every application, not that candidate's | `6260a12` | `D.admin-count-links-filter` | Fix |

## admin-emails-page-renders

Free `audit/journeys/admin/admin-emails-page-renders.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10430711101](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10430711101) | Settings > Emails: the Subject box is too narrow, so most email subjects are cut off and can't be read without clicking in | `3dd3508` `a5fa987` | `D.settings-fit` | Fix |
| [10420281996](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10420281996) | [Free] Emails log Template column shows internal ids (job-approved) instead of template names | `8eac42b` | `D.email-log-template-names` | Fix |
| [10407661790](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10407661790) | Email template Preview shows (test send) in the job title | `8eac42b` | `D.email-log-template-names` | Fix |

## admin-employers-page-renders

Free `audit/journeys/admin/admin-employers-page-renders.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10420280748](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10420280748) | [Free] Employers: the Active Jobs link opens the whole Jobs list, not that employer's jobs | `6260a12` | `D.admin-count-links-filter` | Fix |

## admin-job-editor-fields

Free `audit/journeys/admin/admin-job-editor-fields.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10431344323](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10431344323) | Company edit screen: the "Trust Level" dropdown is wider than the sidebar, so its arrow is cut off | `3dd3508` `a5fa987` | `D.settings-fit` | Fix |
| [10417402598](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417402598) | [Jobs] Salary min > max is accepted everywhere (no cross-field validation) | `276b11f` `7013828` | `D.job-write-contract` | Fix |
| [10412612838](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412612838) | [Required Field Validation] Job Can Be Published Without a Title or Required Fields | `276b11f` `7013828` | `D.no-untitled-records` | Fix |

## admin-lists-views-hold

Free `audit/journeys/admin/admin-lists-views-hold.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10417521347](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417521347) | [Taxonomy Terms] Term list tables have no Order column and cannot be reordered without opening each term | `6af39b8` | `D.admin-lists` | Fix |
| [10412591612](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412591612) | [Settings] Missing Documentation Link in Career Board Settings | `6af39b8` | `D.admin-lists` | Fix |
| [10430657014](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10430657014) | Admin Applications: the empty state tells you to "adjust the search or status filter above" when no filter is set and there are no applications | `6af39b8` | `D.admin-lists` | Fix |
| [10430677630](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10430677630) | Career Board admin menu loses its highlight on Add New Job and Edit Job, so the screen looks like it belongs to no menu | `6af39b8` | `D.admin-lists` | Fix |
| [10417326173](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417326173) | [Employers]No Filter for Banned Employers | `6af39b8` | `D.admin-lists` | Fix |
| [10417317114](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417317114) | [Candidates]No Separate Filter for Suspended Candidates | `6af39b8` | `D.admin-lists` | Fix |
| [10412595845](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412595845) | [Missing] “Delete Permanently” Bulk Action in Trash | `6af39b8` | `D.admin-lists` | Fix |

## admin-settings-antispam-tab-save

Free `audit/journeys/admin/admin-settings-antispam-tab-save.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10401941395](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10401941395) | [Free] [Admin][Low] Visitor IP header: explain the proxy chain; one CDN plus a load balancer pools every visitor | `8eac42b` | `D.client-ip-two-proxies` | Fix |

## admin-settings-tabs-render

Free `audit/journeys/admin/admin-settings-tabs-render.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10430704186](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10430704186) | Settings > Brand: a red "Remove" button shows next to "Choose Image" when no logo is set | `3dd3508` `a5fa987` | `D.settings-fit` | Fix |
| [10431561385](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10431561385) | Settings tabs: at tablet / narrow desktop width the text, URL, email and key inputs run past the right edge of the card and are cut off | `3dd3508` `a5fa987` | `D.settings-fit` | Fix |
| [10420281820](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10420281820) | [Free] Jobs Flags column counts every reporter but auto-hide counts only reporters with standing; help text omits it | `8eac42b` | `D.settings-help-no-versions` | Fix |
| [10420281633](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10420281633) | [Free] Settings deadline note hard-codes a release number: (set before 1.8.0) | `8eac42b` | `D.settings-help-no-versions` | Fix |
| [10420281502](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10420281502) | [Free] Require a location, deadline auto-close and email verification default on for new sites, off for older ones, with no help text saying so | `8eac42b` | `D.settings-help-no-versions` | Fix |
| [10407662478](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10407662478) | Default order is called Featured, then newest in admin but Newest first for visitors | `8eac42b` | `D.settings-copy-one-name` | Fix |
| [10407360998](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10407360998) | Integrations tab promises Jetonomy, Learnomy and MediaVerse features that do not exist | `8eac42b` | `D.settings-copy-one-name` | Fix |
| [10421607738](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421607738) | [Pro] [Bug][Low] Featured upgrade help text says 'Settings > Listings'; the tab is called Jobs | `3c5d214` `7cf57f1` | `D.settings-help-names-tabs` | Fix |

## admin-user-role-change

Free `audit/journeys/admin/admin-user-role-change.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10404085367](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10404085367) | [Free+Pro] [i18n][Medium] Bell rows, ledger notes, stage names and role labels are stored in one language | `5fe91ac` `b23b86d` | `D.stored-text-reader-language` | Fix |

## import-and-custom-fields-hold

Free `audit/journeys/admin/import-and-custom-fields-hold.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10412755150](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412755150) | [Featured Image ]Option Missing in the Frontend Employer Dashboard When Posting a Job | `164f12e` `8433cfd` | `D.import-and-custom-fields` | By design |
| [10412714930](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412714930) | [Jobs]Featured Image Not Displaying on the Frontend Job Listing | `164f12e` `8433cfd` | `D.import-and-custom-fields` | By design |
| [10421858162](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421858162) | [Job Fields]Custom Job Fields Form Is Poorly Structured on the Frontend | `164f12e` `8433cfd` | `D.import-and-custom-fields` | Fix |
| [10427552850](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10427552850) | CSV re-import sets live jobs back to Pending | `164f12e` `8433cfd` | `D.import-and-custom-fields` | Fix |
| [10427554352](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10427554352) | CSV import turns salaries like 80,000, 80k or $80000 into 80, 80 or 0, with no warning | `164f12e` `8433cfd` | `D.import-and-custom-fields` | Fix |

## walkthrough-admin-setup-and-settings

Free `audit/journeys/admin/walkthrough-admin-setup-and-settings.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10407662564](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10407662564) | The two Run Setup Wizard links on Settings go to different screens | `8eac42b` | `D.settings-copy-one-name` | Fix |
| [10407360877](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10407360877) | "Invite an employer" in Getting started never ticks | `8eac42b` | `D.dashboard-invite-employer-tick` | Fix |

## walkthrough-gdpr-export-erase

Free `audit/journeys/admin/walkthrough-gdpr-export-erase.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10422460924](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10422460924) | [Advanced][Keep Email History] Retention prunes email/notification history EARLY on sites with a negative UTC offset (data loss) | `3df10af` | `D.privacy-log-complete` | Fix |
| [10422413943](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10422413943) | [Privacy Request Log][Docs Mismatch] Guest erase/export requests are displayed as "Deleted member (#0)", contradicting the tab's promise to list guest requests | `3df10af` | `D.privacy-log-complete` | Fix |
| [10422411926](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10422411926) | [Privacy Request Log] Account deletions via delete_user are never written to the GDPR audit log | `3df10af` | `D.privacy-log-complete` | Fix |

## classic-theme-controls-hold

Free `audit/journeys/customer/classic-theme-controls-hold.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10412882677](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412882677) | [Pro] Job Map: the "Search" button icon sits higher than its label | `3dd3508` `a5fa987` | `D.classic-theme-controls` | Fix |
| [10430753987](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10430753987) | Job description editor (Post a Job and Edit job): bullet and numbered lists show no bullets or numbers while you edit, but they do on the public job page | `3dd3508` `a5fa987` | `D.classic-theme-controls` | Fix |
| [10436356200](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10436356200) | Employer dashboard: the rating stars turn into solid blue squares on hover (Astra) | `3dd3508` `a5fa987` | `D.classic-theme-controls` | Fix |
| [10436294696](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10436294696) | Resume builder: the text areas have no top padding, so text sits against the top border (all themes) | `3dd3508` `a5fa987` | `D.classic-theme-controls` | Fix |
| [10436217621](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10436217621) | Credit balance block (Pro): "Transaction History" turns dark blue on hover with dark text (Astra, Kadence) | `3dd3508` `a5fa987` | `D.classic-theme-controls` | Fix |
| [10436196754](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10436196754) | Employer dashboard "My Jobs": the search box is a square, shorter field next to the rounded filter controls (Kadence) | `3dd3508` `a5fa987` | `D.classic-theme-controls` | Fix |
| [10436185528](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10436185528) | Find Jobs: the salary range sliders render as bordered boxes with the thumb floating inside (Kadence) | `3dd3508` `a5fa987` | `D.classic-theme-controls` | Fix |
| [10436093928](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10436093928) | Link-styled buttons show an underlined label with Astra (Apply and View buttons on job, company and dashboard pages) | `3dd3508` `a5fa987` | `D.classic-theme-controls` | Fix |
| [10436072102](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10436072102) | Employer dashboard: the stat tiles ("Total Jobs", "Live", "Applications", "New This Week") turn solid Astra blue on hover | `3dd3508` `a5fa987` | `D.classic-theme-controls` | Fix |

## dashboard-feedback-holds

Free `audit/journeys/customer/dashboard-feedback-holds.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10417182252](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417182252) | [Apply Now and View Application] Buttons Displayed Twice | `4f2d0d9` `e6146c6` | `D.screen-feedback` | By design |
| [10412873869](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412873869) | [Free] Employer Registration: required fields have no asterisk, and errors use the browser's default popup | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10412904033](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412904033) | [Accessibility] Company Profile: "Upload Logo" can't be reached or used with the keyboard | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10412868653](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412868653) | [Free, shows when Pro is active] Company profile "Set Up Alerts" button lands on the dashboard Overview, not Job Alerts | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10430670040](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10430670040) | Post a Job step 4 "Preview" leaves out the job description, so employers submit without seeing the part candidates read | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10431286004](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10431286004) | Find Jobs list view: the closing date appears as a bare date ("December 29, 2026") next to "3 weeks ago", with no label | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10431407539](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10431407539) | Resume builder: after "Save Resume" nothing visible happens at the top. The "Resume saved!" message appears only at the very bottom of the page, for 3 seconds. | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10431435128](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10431435128) | Employer Registration: a logged-in member is told "Create an Employer Account", then "Account created! You are now logged in". They already had an account. | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10427548090](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10427548090) | Find Candidates says "No resumes match your filters / Try removing a filter" when no filter is set and there are no resumes | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10430719814](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10430719814) | Dashboard Notifications: the empty state is one small grey sentence, unlike every other dashboard tab (icon, title, hint and button) | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10430648050](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10430648050) | Employer Overview: "No active jobs" and "No applications yet" show while the data is still loading, next to "8 Live" | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |
| [10412854820](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412854820) | [Free] Employer Dashboard → My Jobs: an empty status filter says "No jobs yet, post your first job" | `4f2d0d9` `e6146c6` | `D.screen-feedback` | Fix |

## data-stays-in-step

Free `audit/journeys/customer/data-stays-in-step.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10427556196](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10427556196) | Job Alerts block drops the Experience filter, so the alert matches every level | `46b0bbd` `7ae0e4b` | `D.data-in-step` | Fix |
| [10427558911](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10427558911) | Company page "Load more jobs" repeats a card and can drop a featured job | `46b0bbd` `7ae0e4b` | `D.data-in-step` | Fix |
| [10427560929](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10427560929) | Analytics > Export Credit Ledger CSV: the "Employer" column holds a user ID, and "Job" holds a raw item ID | `46b0bbd` `7ae0e4b` | `D.data-in-step` | Fix |
| [10422290745](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10422290745) | [Credits] "Employers can't buy credits yet" warning and buy-credits link disagree with the employer Credits tab | `46b0bbd` `7ae0e4b` | `D.data-in-step` | Fix |
| [10422461443](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10422461443) | [Advanced][Content Width] Consumer docblock says 'no admin control ships for it' but the Advanced tab ships one (also wrong default) | `46b0bbd` `7ae0e4b` | `D.data-in-step` | Fix |
| [10417521278](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417521278) | [Job Form] Location dropdown ignores the term Order field | `46b0bbd` `7ae0e4b` | `D.data-in-step` | Fix |
| [10417521506](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417521506) | [Experience Levels] Seniority fallback matches slugs by substring and can sort levels wrongly | `46b0bbd` `7ae0e4b` | `D.data-in-step` | Fix |
| [10418357457](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10418357457) | [SEO / JobPosting] hiringOrganization.url emits 404 ?post_type=wcb_company&p=ID URL for draft/trashed companies | `46b0bbd` `7ae0e4b` | `D.data-in-step` | Fix |
| [10427543204](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10427543204) | Renaming a company does not rename it on its jobs: search finds nothing under the new name, cards keep the old name | `46b0bbd` `7ae0e4b` | `D.data-in-step` | Fix |

## employer-applicants-list

Free `audit/journeys/customer/employer-applicants-list.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10417743552](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417743552) | [Free] Applications: the delete cross shows on team-mates' notes and clicking it silently does nothing | `6f75439` | `D.note-delete-own-only` | Fix |
| [10417743215](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417743215) | [Free] Applications: clicking a status chip that hides the selected applicant leaves an empty "?" applicant panel | `fea3a86` | `D.apps-filter-drops-open-applicant` | Fix |

## employer-buys-credits-and-features

Free `audit/journeys/customer/employer-buys-credits-and-features.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10421607769](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421607769) | [Free+Pro] [Bug][Medium] Feature with too few credits shows a bare 402 line: no link to Credits, although the code comment and the docs say the employer is sent there | `c4be54c` `ddbafb3` `e7c7d5d` | `D.feature-short-credits-buy-link` | Fix |

## employer-edit-company

Free `audit/journeys/customer/employer-edit-company.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10412849387](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412849387) | [Free] Company Profile saves with an empty Company Name, which blanks the company | `276b11f` `7013828` | `D.no-untitled-records` | Fix |

## job-listings-board-filter

Free `audit/journeys/customer/job-listings-board-filter.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10439305049](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10439305049) | Deleting the default board leaves wcb_default_board_id pointing at a dead post | `d1b0795` `ee8fefb` | `D.default-board-live` | Fix |

## job-page-view-is-counted

Free `audit/journeys/customer/job-page-view-is-counted.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10403606771](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10403606771) | Analytics "Job Views (30 days)" Permanently Shows 0 Due to Missing Frontend Web View Tracking | see card | `D.job-view-counted` | Fix |

## paid-board-job-charged-once

Free `audit/journeys/customer/paid-board-job-charged-once.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10401941213](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10401941213) | [Free+Pro] [Money][Medium] Two board moves at once can leave a job on the dearer board at the cheaper price | see card | `D.board-move-race` | Fix |

## salary-filter-other-currencies

Free `audit/journeys/customer/salary-filter-other-currencies.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10417744512](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417744512) | [Docs] Job alert and salary filter docs contradict each other and the code | `495f262` `c4be54c` | `D.salary-other-currencies` | Fix |
| [10402937387](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10402937387) | [WP Career Board - Jobs Directory] Multi-Currency Sort & Filter Defects: "Highest Salary" compares raw numeric values across currencies, and salary filter silently drops non-default currency listings | `495f262` `c4be54c` `f5f8066` | `D.salary-other-currencies` | Fix |

## walkthrough-account-and-notifications

Free `audit/journeys/customer/walkthrough-account-and-notifications.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10402675985](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10402675985) | [Pro] [UX][Low] Credits off: employer Settings still offers the 'Low Credit Balance Warning' email | `9deccbc` `f9b04dd` | `D.credit-emails-follow-switch` | Fix |
| [10404086322](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10404086322) | [Free+Pro] [i18n][Medium] Guest and admin emails are sent in the site language, not the recipient's | `5650527` `621d0f6` | `D.email-recipient-language` | Fix |

## walkthrough-candidate-dashboard

Free `audit/journeys/customer/walkthrough-candidate-dashboard.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10402676028](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10402676028) | [Free] [UX][Low] Candidate Overview: Welcome band is a box in a box (tinted band holding three bordered step cards) | `b5dc226` | `D.overview-one-frame` | Fix |
| [10417743747](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417743747) | [Free] Candidate Profile shows "Edit my resume" on sites without Pro, where the My Resumes tab does not exist | `0fc4771` `613f725` | `D.profile-resume-note-gate` | Fix |

## walkthrough-employer-post-job

Free `audit/journeys/customer/walkthrough-employer-post-job.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10418355276](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10418355276) | [REST API] per_page > 100 returns HTTP 400 instead of clamping to 100 | `276b11f` | `D.job-write-contract` | Fix |
| [10421957903](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421957903) | [Field Builder] Required custom fields are not validated on Job, Company, Candidate and Resume submissions | `276b11f` `7013828` | `D.job-write-contract` | Fix |
| [10417400763](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417400763) | [Job Edit API] PATCH location_custom appends to existing location terms instead of replacing | `276b11f` `7013828` | `D.job-write-contract` | Fix |
| [10417401942](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417401942) | [Jobs API] remote=false (string) is stored as remote=1 | `276b11f` `7013828` | `D.job-write-contract` | Fix |
| [10417436809](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417436809) | [Job Edit API] PATCH /wcb/v1/jobs/{id} with empty title blanks a published job | `276b11f` `7013828` | `D.no-untitled-records` | Fix |

## walkthrough-job-search-filters

Free `audit/journeys/customer/walkthrough-job-search-filters.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10412857828](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412857828) | [Free] Find Jobs: the search term disappears from the box on reload or a shared link, with no way to clear it | `7d49efb` | `D.find-jobs-keyword-reload` | Fix |

## anonymous-can-apply-cleanly

Free `audit/journeys/security/anonymous-can-apply-cleanly.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10417749956](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417749956) | [Job Single / Apply] Guest job pages leak every published resume into public Interactivity state | `3df10af` `a9ca0ba` | `D.guest-job-page-no-resumes` | Fix |
| [10412797699](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10412797699) | [Job Single / Apply] Logged-out visitors' job page leaks other users' resumes into page state and defeats guest resume validation | `3df10af` `a9ca0ba` | `D.guest-job-page-no-resumes` | Fix |

## company-handover-drops-old-employer

Free `audit/journeys/security/company-handover-drops-old-employer.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10401941306](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10401941306) | [Free] [Security][Low] Linked-company user meta grants company access without an authorship check | `229555f` `f5f8066` | `D.company-handover-drops-link` | Fix |

## private-candidate-profile-answers-404

Free `audit/journeys/security/private-candidate-profile-answers-404.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10409466614](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10409466614) | Candidate guard 404s author pages of members who publish blog posts | `8d4d9a3` | `D.candidate-writer-author-page` | Fix |

## turnstile-challenge-can-be-passed

Free `audit/journeys/security/turnstile-challenge-can-be-passed.md`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10417125333](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417125333) | [SUPPORT] Turnstile CAPTCHA fails for challenged visitors: widget rendered off-screen with invalid size 'invisible' | `d7f539d` | `D.turnstile-challenge` | Fix |

## Runbook row `D.no-untitled-records`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10417581044](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10417581044) | [Resume] Can Be Published Without Filling Any Fields | `276b11f` `7013828` | `D.no-untitled-records` | Fix |

## Runbook row `D.pro-docs-ai-stages`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10421191478](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421191478) | [Docs] Pipeline stages doc says update accepts only label, color and sort_order, but it also accepts terminal_outcome | `9e9d649` | `D.pro-docs-ai-stages` | Fix |
| [10421191406](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10421191406) | [Docs] AI overview says the cover letter button is Generate, but the apply panel button is Write with AI | `9e9d649` | `D.pro-docs-ai-stages` | Fix |

## Runbook row `D.registration-site-name`

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10409511702](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10409511702) | Employer Registration heading says Join WP Career Board instead of the site name | `8eac42b` | `D.registration-site-name` | Fix |
| [10407662647](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10407662647) | Job Listings block description says infinite scroll but the board uses Load more jobs | `8eac42b` | `D.registration-site-name` | Fix |

## Card only

| Card | Title | Commits | Runbook | Verdict |
|---|---|---|---|---|
| [10418069277](https://3.basecamp.com/5798509/buckets/46502739/card_tables/cards/10418069277) | [Cover Letter] Heading Displayed When No Cover Letter Is Added | see card | - | By design |
