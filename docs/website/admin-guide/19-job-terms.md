# Categories, types, locations and experience levels

You can shape the lists employers pick from and candidates filter by. Career Board has five lists, each under **Career Board** in wp-admin:

| List | Menu item | Can have parents |
|---|---|---|
| Categories | **Job categories** | Yes |
| Job types | **Job Types** | No |
| Locations | **Job Locations** | Yes |
| Experience levels | **Experience Levels** | No |
| Tags | **Job Tags** | No |

Add, rename and delete terms the same way as WordPress categories and tags.

## Choose the order

Every term screen has an **Order** field, on both the Add and the Edit form. Enter a whole number. Lower numbers come first in every filter and every form, including the job form, the Job Filters block and the job listings filter panel. Leave it empty to sort after the terms that have a number, alphabetically.

Experience levels you have not numbered sort by seniority, taken from the term's slug: intern, entry, junior, mid, senior, lead, principal or staff, manager, director, executive. A term whose slug has none of those words goes last. Set **Order** on a level to override this.

## Nest terms under a parent

Categories and locations accept a **Parent**. A child term shows under its parent in the filters and in the job form, indented, for example "Engineering" with "Frontend" and "Backend" below it. Order applies within each parent.

When the category list has more than 12 entries, the filter panel adds a **Search categories** box so visitors do not scroll a long list.

## Who can create new terms

Employers pick from the lists you keep. A job submission cannot create a new category, type, location or experience level unless the person is a moderator. Tags are free-form. Developers can change this with the `wcb_job_allow_new_terms` filter. See the [hooks reference](../developer-guide/02-hooks-reference.md).

## For developers

The order is stored as the integer term meta `wcb_order`, which is available in the REST API. To list these terms in the same order the plugin uses, call `WCB\Core\Taxonomy::options( $taxonomy )`. See the [Hooks Reference](../developer-guide/02-hooks-reference.md) section "Deadlines and term order".
