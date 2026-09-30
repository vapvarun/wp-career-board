# AI block and developer surface

You can place AI Chat Search on any page, and developers can read the filters the Free plugin uses to show or hide AI controls.

## AI Chat Search block

Add the **AI Chat Search** block (`wcb/ai-chat-search`) from the block inserter. Pro provides it. Pro also provides the `[wcbp_ai_chat_search]` shortcode.

## Gate filters in Free

Free shows an AI control only when the matching filter returns true. Each defaults to false.

| Filter | Controls |
|---|---|
| `wcb_ai_description_enabled` | The **Generate with AI** button on the post-a-job forms |
| `wcb_ai_ranking_available` | The applicant ranking button on the employer dashboard |
| `wcb_ai_matching_available` | Recommended jobs on the candidate dashboard |
| `wcb_ai_completion_available` | The cover letter writer in the apply panel |

## Where to read the full guide

REST routes, provider hooks and limits are in the WP Career Board Pro docs, on the pages named **AI features**, **AI providers and endpoints** and **Hooks reference**.
