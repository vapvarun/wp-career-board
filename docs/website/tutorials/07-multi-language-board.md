# Multi-language job board

You can run your board in several languages with WPML or Polylang. Career Board ships a configuration file that both plugins read, and a set of ready-made translations. This page shows what is translatable and how to set it up.

## What is translatable

| Item | How it is handled |
|---|---|
| Jobs, companies and resumes | Marked translatable. You create a translated copy of each in WPML or Polylang. |
| Applications and boards | Marked not translatable. |
| Categories, job types, tags, locations, experience levels | Marked translatable. |
| Company name and tagline | Translated per copy of the company. |
| Salary, currency, pay period, deadline, remote flag, featured flag, apply URL, apply email and company links | Copied unchanged from the original to each translation. |
| Sender name, sender email, admin notification email, and every email subject and body | Registered as translatable strings. |
| Interface text | Translatable through the `wp-career-board` text domain. |

Applications are not duplicated across languages, and Career Board does not store a language on each application.

## Ready-made translations

Career Board includes German, French, Spanish, Dutch and Korean translations. If your site language is one of these, the matching translation loads automatically. For other languages, use the template file at `languages/wp-career-board.pot` to create your own, or translate strings inline in WPML String Translation or Polylang Strings translations.

## Emails and languages

Emails to a registered member are sent in that member's language. Guests receive the site language.

To translate the wording of an email, edit its subject and body under **Career Board > Settings > Emails**, then translate them in WPML String Translation or Polylang Strings translations.

## Set up with WPML

1. Install WPML Multilingual CMS and the WPML String Translation add-on, and add your languages.
2. Go to **WPML > Settings > Post Types Translation** and **Taxonomies Translation**, and check the settings shown above. They come from Career Board's configuration file, so you usually do not need to change them.
3. Go to **WPML > String Translation** and filter by the `wp-career-board` domain to translate interface text.
4. Create a test job in your default language, then create its translation from the post editor and translate the title and description.
5. Switch language on the front end and check the translated job appears.
6. Apply as a candidate in the second language and check the form labels and the confirmation email.

## Set up with Polylang

1. Install Polylang and add your languages under **Languages > Languages**.
2. Go to **Languages > Settings > Custom post types and Taxonomies**. Job, company and resume post types and the Career Board taxonomies are enabled by the configuration file. Leave applications and boards off.
3. Go to **Languages > Strings translations** and filter by `wp-career-board` to translate interface text.
4. Create a translation of a test job and check it on the front end.

## Pro and languages

Pro adds boards, custom fields and AI features that have their own translation needs. See the Pro docs pages **Multi-board**, **Field builder** and **AI features**.

## Currency and salary

A salary is stored as a number with a currency code. WPML and Polylang do not convert it, so a job shows the currency its employer chose.

## Testing checklist

- Switch the site language. The job list and job pages show the translated content.
- Search in each language.
- Apply to a translated job and check the confirmation email arrives in your language.
- Open the Employer Dashboard in each language.

## Where to go next

- [Your first day as a site owner](01-first-day-as-site-owner.md) - set up the base board first.
- [Email notifications](../admin-guide/02-email-notifications.md) - review your email templates.
