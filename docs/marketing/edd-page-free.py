#!/usr/bin/env python3
"""
EDD Elementor Page Generator — WP Career Board (Free)
Usage:
  1. Create the EDD product on wbcomdesigns.com (Downloads → Add New)
  2. Set edd_item_id below to the real EDD download ID
  3. Copy snipshare-edd-template.json next to this script
  4. Run: python3 edd-page-free.py
  5. Import wp-career-board-edd-page.json via Elementor → Templates → Import
"""
import json, copy, re

# ─── FILL IN BEFORE RUNNING ─────────────────────────────────────────────────
# TODO: Replace 0 with the real EDD download ID after creating the product
EDD_ITEM_ID = 1659888   # ← wbcomdesigns.com EDD item ID for WP Career Board (free)
# ─────────────────────────────────────────────────────────────────────────────

PLUGIN_DATA = {
    'name':    'WP Career Board',
    'slug':    'wp-career-board',
    'edd_item_id': EDD_ITEM_ID,

    # Hero
    'badge':   '✨ Free · Block-First · BuddyPress · Reign Theme',
    'h1':      'A job board that actually belongs in your WordPress site.',
    'subhead':  'WP Career Board is built on Gutenberg blocks and the WordPress Interactivity API — no shortcodes, no jQuery, no page reloads. Employer dashboards, candidate applications, company profiles, and BuddyPress integration. Free. Included with Reign Theme.',

    # Problem section
    'problem_headline': 'Every other WordPress job board plugin is built on technology from 2012.',
    'problem_points': [
        ('✗ Shortcodes everywhere',       'Paste [shortcode] on a page and hope it renders correctly with your theme'),
        ('✗ jQuery on every page load',   'Loaded globally even on pages with zero job board content'),
        ('✗ Features that should be free cost extra', 'Guest applications, GDPR, spam protection — add-ons at $49 each'),
        ('✗ No community awareness',      'BuddyPress integration either doesn\'t exist or needs yet another paid extension'),
    ],
    'problem_resolution': 'WP Career Board fixes all of this. Block-first. Interactivity API. Free.',

    # "Only one" section
    'only_one_headline': 'The only job board plugin built for modern WordPress.',
    'only_one_subhead':  'WP Job Manager uses shortcodes. JobBoardWP has no block editor support. WP Career Board was designed from the ground up for Gutenberg 6.9+ — and it\'s the only one bundled with Reign Theme and with first-class BuddyPress integration.',
    'differentiator_1_title': 'The only block-first job board',
    'differentiator_1_body':  'Every screen — job listings, dashboards, application forms, company profiles — is a Gutenberg block. You place them with the block editor, they inherit your theme\'s design, and there are no shortcodes to memorize.',
    'differentiator_2_title': 'The only one built on the Interactivity API',
    'differentiator_2_body':  'Job filters update without a page reload. Application status changes instantly. All of it runs on the WordPress Interactivity API — the same runtime as the Query Loop block. No external JavaScript framework.',
    'differentiator_3_title': 'The only one bundled with Reign Theme',
    'differentiator_3_body':  'WP Career Board is included with every Reign Theme license. Run the Setup Wizard and your community site has a professional job board — styled to match Reign automatically, with BuddyPress integration from day one.',

    # Features section
    'features_heading': 'A complete job board. No paid add-ons required.',
    'features': [
        ('Live job search and filters',    'Keyword, location, type, category, salary range, remote policy — all live, no page reload.'),
        ('Employer and candidate frontends','Post and manage jobs. Track applications. Neither role needs wp-admin access.'),
        ('Full application tracking',      'Five statuses (Submitted → Reviewing → Shortlisted → Rejected / Hired) with automatic email on each change.'),
        ('Guest applications',             'Candidates apply with name and email only — no account registration required.'),
        ('Company profiles',               'Public company pages — logo, description, website, team size. Candidates see who they\'re applying to.'),
        ('Email notifications',            'Automatic emails on application received, status changed, and job approved. Fully customizable templates.'),
        ('reCAPTCHA v3 + GDPR',            'Invisible spam protection on job form and application form. Personal data export and erasure built in — both free.'),
        ('JobPosting schema.org',          'Valid structured data on every job page. Eligible for Google Jobs without any SEO plugin.'),
        ('BuddyPress integration',         'Job activity in the community feed. Employer and candidate roles as BuddyPress member types. Auto-detected.'),
    ],

    # BuddyPress / Reign spotlight
    'spotlight_badge':    'Included with Reign Theme · BuddyPress-ready',
    'spotlight_headline': 'A job board that looks like part of your community',
    'spotlight_body':     'WP Career Board is bundled with Reign Theme and detects BuddyPress automatically. Activate, run the wizard, and the job board adopts Reign\'s design system — colors, typography, card styles. Job posts appear in the BuddyPress activity stream without any configuration.',
    'spotlight_bullets': [
        'Included in every Reign Theme license — already yours',
        'Reign design system adopted automatically at activation',
        'BuddyPress activity integration — zero configuration',
    ],

    # Pricing — Free download
    'pricing_heading': "Free. No license key, no expiry, no locked features.",
    'pricing_support': '<p>WP Career Board is permanently free. Every feature listed on this page is included. WP Career Board Pro is a separate paid extension for advanced features.</p>',
    'annual_prices':   [0, 0, 0],
    'lifetime_prices': [0, 0, 0],
    'annual_includes': [
        ['Unlimited site installs', 'Full job board — all features', 'Employer and candidate dashboards', 'BuddyPress + Reign Theme integration', 'Community support'],
        ['Unlimited site installs', 'Full job board — all features', 'Employer and candidate dashboards', 'BuddyPress + Reign Theme integration', 'Community support'],
        ['Unlimited site installs', 'Full job board — all features', 'Employer and candidate dashboards', 'BuddyPress + Reign Theme integration', 'Community support'],
    ],
    'lifetime_includes': [
        ['Unlimited site installs', 'Full job board — all features', 'Employer and candidate dashboards', 'BuddyPress + Reign Theme integration', 'Community support'],
        ['Unlimited site installs', 'Full job board — all features', 'Employer and candidate dashboards', 'BuddyPress + Reign Theme integration', 'Community support'],
        ['Unlimited site installs', 'Full job board — all features', 'Employer and candidate dashboards', 'BuddyPress + Reign Theme integration', 'Community support'],
    ],

    # FAQ
    'faq': [
        ('Is the free version actually free, or is it a limited trial?',
         'Permanently free. The free version is a complete job board — employer and candidate dashboards, application tracking, BuddyPress integration, reCAPTCHA, GDPR, and JobPosting schema are all included. No trial period, no feature locks. WP Career Board Pro is a separate paid product for advanced features like a Kanban pipeline, multiple boards, and Stripe monetization.'),
        ('Do I get WP Career Board if I have a Reign Theme license?',
         'Yes. WP Career Board is included with all Reign Theme licenses. Log in to your Wbcom account, go to Downloads, and download WP Career Board. Run the Setup Wizard and the Reign integration activates automatically.'),
        ('Does it require BuddyPress?',
         'No. WP Career Board works on any WordPress site. BuddyPress is optional — when active, it unlocks activity stream integration, employer and candidate member types, and profile linking. No configuration needed.'),
        ('Will it slow down my site?',
         'No JavaScript framework is loaded. The frontend runs on the WordPress Interactivity API — the same runtime WordPress uses for the Query Loop block. No jQuery, no React bundle, no additional libraries.'),
        ('Is there a money-back guarantee?',
         'It\'s free — no payment required. If you need the Pro version and it doesn\'t work for your use case, the Pro license comes with a 30-day money-back guarantee.'),
        ('Is there documentation?',
         'Yes. Full documentation covering installation, the Setup Wizard, block placement, settings, integrations, and troubleshooting. Available at store.wbcomdesigns.com/wp-career-board/docs/'),
    ],

    # CTA
    'cta_headline': 'Add a complete job board to your WordPress site — free.',
    'cta_subtext':  'Included with Reign Theme. Works with BuddyPress, BuddyBoss, and any Gutenberg-compatible theme.',
    'cta_button':   'Download WP Career Board Free',

    # EDD checkout URL — free download, no pricing tiers
    'checkout_urls': {
        'annual_personal':    f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=1',
        'annual_developer':   f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=1',
        'annual_agency':      f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=1',
        'lifetime_personal':  f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=1',
        'lifetime_developer': f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=1',
        'lifetime_agency':    f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=1',
    },
}

# ─── ENGINE (do not modify below) ─────────────────────────────────────────────

TEMPLATE = {
    'badge':        '✨ Self-hosted · BuddyPress · REST API',
    'h1':           'Code sharing that belongs to your community.',
    'subhead':      'SnipShare turns any WordPress site into a self-hosted paste platform — with syntax highlighting, revision history, BuddyPress integration, and a full REST API. No SaaS dependency. No per-user pricing. Your data stays on your server.',
    'problem_headline': 'Your members are sharing code right now. Just not on your site.',
    'problem_points': [
        ('✗ On Pastebin',      'With ads, no community context, and links that rot'),
        ('✗ In chat messages', 'Where it gets lost in minutes and can never be searched'),
        ('✗ On GitHub Gist',   'Requiring a GitHub account your members may not have'),
        ('✗ Nowhere at all',   'Because your platform has no native place for code'),
    ],
    'problem_resolution': 'SnipShare gives your community a code-sharing home — self-hosted on your server, searchable and owned by you.',
    'only_one_headline': 'There is no other WordPress plugin like SnipShare.',
    'only_one_subhead':  'We checked the WordPress plugin directory, CodeCanyon, and every major marketplace. There is no WordPress alternative. Every other option is a SaaS tool with no knowledge of WordPress roles, BuddyPress communities, or self-hosting.',
    'differentiator_1_title': 'The only self-hosted option',
    'differentiator_1_body':  'Every other code-sharing tool is a SaaS product. SnipShare runs on your server. Your data never leaves your WordPress install.',
    'differentiator_2_title': 'The only BuddyPress-native integration',
    'differentiator_2_body':  'No other tool posts paste activity to your BuddyPress feed, adds a Pastes tab to member profiles, or sends notifications when someone stars a paste.',
    'differentiator_3_title': 'The only versioned community solution',
    'differentiator_3_body':  'Pastebin and Gist offer no community ownership. SnipShare has revision history, diffs, forking, starring, and collections.',
    'features_heading': 'Everything included. Every plan.',
    'features': [
        ('Multi-file pastes',     'Group related files under one URL. Up to 10 files per paste.'),
        ('Syntax highlighting',   '40+ languages with CodeMirror 6 editor and Prism.js viewer.'),
        ('5 privacy levels',      'Public, unlisted, private, password-protected, and burn-after-read.'),
        ('Expiration & burn',     'Pastes auto-expire after 1 hour, 1 day, 1 week, or 1 month.'),
        ('Full revision history', 'Every edit versioned with side-by-side diff view. Roll back anytime.'),
        ('Fork & remix',          'Clone any public paste into your account with its own revision history.'),
        ('Stars & collections',   'Bookmark pastes. Group into named public or private collections.'),
        ('BuddyPress integration','Activity feed, member profile tab, and notifications built in.'),
        ('Full REST API',         'Complete CRUD for pastes, files, revisions, collections, and stars.'),
    ],
    'spotlight_badge':    'BuddyPress native',
    'spotlight_headline': 'Code sharing becomes a social activity',
    'spotlight_body':     'SnipShare is built for BuddyPress communities. Every paste becomes a social event — posted to the activity stream, visible on member profiles, and generating notifications.',
    'spotlight_bullets': [
        'New pastes posted to activity stream automatically',
        'Dedicated Pastes tab on every member profile',
        'Star notifications keep members engaged on your platform',
    ],
    'pricing_heading': "Pick your license. Everything's included.",
    'pricing_support': '<p>Every plan includes all features, full documentation, and email support. No feature gating, no usage limits, no surprises.</p>',
    'annual_tier_names':   ['Personal License', 'Professional License', 'Agency License'],
    'lifetime_tier_names': ['Personal License', 'Professional License', 'Agency License'],
    'annual_descs':   ['For single-site owners and personal projects',
                       'For developers managing up to 5 client or personal sites',
                       'For agencies deploying across unlimited client projects'],
    'lifetime_descs': ['For single-site owners who want to pay once',
                       'For developers who want permanent access on 5 sites',
                       'For agencies wanting unlimited lifetime deployment'],
    'annual_prices':   [49, 79, 149],
    'lifetime_prices': [149, 199, 399],
    'cta_headline': 'Ready to bring code sharing home?',
    'cta_subtext':  'Self-hosted on your WordPress install. Works standalone or with BuddyPress, BuddyBoss, and PeepSo.',
    'cta_button':   'Get SnipShare Now',
}

p = PLUGIN_DATA
t = TEMPLATE

def strip_html(s):
    return re.sub(r'<[^>]+>', '', s).strip()

TITLE_REPLACEMENTS = {
    t['badge']: p['badge'], t['h1']: p['h1'],
    t['problem_headline']: p['problem_headline'],
    t['only_one_headline']: p['only_one_headline'],
    t['differentiator_1_title']: p['differentiator_1_title'],
    t['differentiator_2_title']: p['differentiator_2_title'],
    t['differentiator_3_title']: p['differentiator_3_title'],
    t['features_heading']: p['features_heading'],
    t['spotlight_headline']: p['spotlight_headline'],
    t['cta_headline']: p['cta_headline'],
    t['cta_button']: p['cta_button'],
    str(t['annual_prices'][0]): str(p['annual_prices'][0]),
    str(t['annual_prices'][1]): str(p['annual_prices'][1]),
    str(t['annual_prices'][2]): str(p['annual_prices'][2]),
    str(t['lifetime_prices'][0]): str(p['lifetime_prices'][0]),
    str(t['lifetime_prices'][1]): str(p['lifetime_prices'][1]),
    str(t['lifetime_prices'][2]): str(p['lifetime_prices'][2]),
    f"${t['annual_prices'][0]}": f"${p['annual_prices'][0]}",
    f"${t['annual_prices'][1]}": f"${p['annual_prices'][1]}",
    f"${t['annual_prices'][2]}": f"${p['annual_prices'][2]}",
    f"${t['lifetime_prices'][0]}": f"${p['lifetime_prices'][0]}",
    f"${t['lifetime_prices'][1]}": f"${p['lifetime_prices'][1]}",
    f"${t['lifetime_prices'][2]}": f"${p['lifetime_prices'][2]}",
}

EDITOR_REPLACEMENTS = {
    t['subhead']: p['subhead'],
    strip_html(t['problem_resolution']): strip_html(p['problem_resolution']),
    strip_html(t['only_one_subhead']): strip_html(p['only_one_subhead']),
    t['differentiator_1_body']: p['differentiator_1_body'],
    t['differentiator_2_body']: p['differentiator_2_body'],
    t['differentiator_3_body']: p['differentiator_3_body'],
    strip_html(t['spotlight_body']): strip_html(p['spotlight_body']),
    strip_html(t['pricing_support']): strip_html(p['pricing_support']),
    t['cta_subtext']: p['cta_subtext'],
}

FEATURE_MAP = {old: (new_t, new_b) for (old, _), (new_t, new_b) in zip(t['features'], p['features'])}
PROBLEM_MAP = {old_t: (new_t, new_n) for (old_t, _), (new_t, new_n) in zip(t['problem_points'], p['problem_points'])}
SPOTLIGHT_BULLET_MAP = {old: new for old, new in zip(t['spotlight_bullets'], p['spotlight_bullets'])}
TIER_DESC_MAP = {}
for old, new in zip(t['annual_descs'], p.get('annual_descs', t['annual_descs'])): TIER_DESC_MAP[old] = new
for old, new in zip(t['lifetime_descs'], p.get('lifetime_descs', t['lifetime_descs'])): TIER_DESC_MAP[old] = new

SNIPSHARE_ID = 1652519
URL_REPLACEMENTS = {}
if 'checkout_urls' in p:
    for pid, key in [(1,'annual_personal'),(2,'annual_developer'),(3,'annual_agency'),(4,'lifetime_personal'),(5,'lifetime_developer'),(6,'lifetime_agency')]:
        old = f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={SNIPSHARE_ID}&edd_options%5Bprice_id%5D={pid}'
        if key in p['checkout_urls']:
            URL_REPLACEMENTS[old] = p['checkout_urls'][key].replace('[price_id]', '%5Bprice_id%5D')

ANNUAL_INCLUDES = p.get('annual_includes', [[], [], []])
LIFETIME_INCLUDES = p.get('lifetime_includes', [[], [], []])
FAQ_DATA = p.get('faq', [])

def process_editor(val):
    plain = strip_html(val)
    for old_plain, new_plain in EDITOR_REPLACEMENTS.items():
        if old_plain and old_plain in plain:
            if '<p style=' in val:
                m = re.match(r'(<p style="[^"]*">)(.*?)(</p>)', val, re.DOTALL)
                if m: return m.group(1) + new_plain + m.group(3)
            return new_plain
    for old_t, (new_t, new_b) in FEATURE_MAP.items():
        old_b = next((b for tt, b in t['features'] if tt == old_t), None)
        if old_b and old_b in val: return new_b
    for old_d, new_d in TIER_DESC_MAP.items():
        if f'<p>{old_d}</p>' == val.strip() or old_d in plain:
            return f'<p>{new_d}</p>'
    return val

def process_title(val):
    if val in TITLE_REPLACEMENTS: return TITLE_REPLACEMENTS[val]
    if val in FEATURE_MAP: return FEATURE_MAP[val][0]
    if val in PROBLEM_MAP: return PROBLEM_MAP[val][0]
    return val

def process_node(node, ati=[0], lti=[0]):
    if not isinstance(node, dict): return node
    wtype = node.get('widgetType', '')
    s = node.get('settings', {})
    if 'title' in s: s['title'] = process_title(s['title'])
    if 'editor' in s: s['editor'] = process_editor(s['editor'])
    if wtype == 'button':
        if 'text' in s: s['text'] = TITLE_REPLACEMENTS.get(s['text'], s['text'])
        link = s.get('link', {})
        if isinstance(link, dict) and 'url' in link:
            link['url'] = URL_REPLACEMENTS.get(link['url'], link['url'])
    if wtype == 'icon-box':
        tt = s.get('title_text', '')
        if tt in PROBLEM_MAP: s['title_text'], s['description_text'] = PROBLEM_MAP[tt]
    if wtype == 'icon-list' and 'icon_list' in s:
        items = s['icon_list']
        if items:
            first = items[0].get('text', '')
            if first in SPOTLIGHT_BULLET_MAP:
                s['icon_list'] = [{**it, 'text': SPOTLIGHT_BULLET_MAP.get(it.get('text',''), it.get('text',''))} for it in items]
            elif any(kw in first for kw in ['Single Site', '5 Site License', 'Unlimited Licenses']):
                if 'Year' in str(items):
                    idx = ati[0]
                    if idx < len(ANNUAL_INCLUDES) and ANNUAL_INCLUDES[idx]:
                        s['icon_list'] = [{'_id': it.get('_id',''), 'text': txt} for it, txt in zip(items, ANNUAL_INCLUDES[idx])]
                        for txt in ANNUAL_INCLUDES[idx][len(items):]: s['icon_list'].append({'_id': '', 'text': txt})
                    ati[0] += 1
                else:
                    idx = lti[0]
                    if idx < len(LIFETIME_INCLUDES) and LIFETIME_INCLUDES[idx]:
                        s['icon_list'] = [{'_id': it.get('_id',''), 'text': txt} for it, txt in zip(items, LIFETIME_INCLUDES[idx])]
                        for txt in LIFETIME_INCLUDES[idx][len(items):]: s['icon_list'].append({'_id': '', 'text': txt})
                    lti[0] += 1
    if wtype == 'nested-accordion' and 'items' in s:
        for i, item in enumerate(s['items']):
            if i < len(FAQ_DATA): item['item_title'] = FAQ_DATA[i][0]
    if wtype == 'text-editor' and 'editor' in s:
        orig_answers = ['No. SnipShare is the only WordPress plugin', 'Yes. BuddyPress features are completely optional',
                        '40+ languages including PHP', 'Yes. Restrict creation to logged-in users',
                        'Yes — 30-day no-questions-asked', 'Yes. SnipShare includes full documentation']
        for i, orig in enumerate(orig_answers):
            if orig in s['editor'] and i < len(FAQ_DATA):
                s['editor'] = FAQ_DATA[i][1]; break
    node['settings'] = s
    node['elements'] = [process_node(child, ati, lti) for child in node.get('elements', [])]
    return node

import os
script_dir = os.path.dirname(os.path.abspath(__file__))
template_file = os.path.join(os.path.dirname(script_dir), '..', '..', '..', '..', '..',
    'Documents', 'store.wbcomdesigns.com', 'src', 'content', 'products', '..', '..', '..', '..',
    '.claude', 'skills', 'store-product-publisher', 'snipshare-edd-template.json')

# Try local first, then skill folder
local_template = os.path.join(script_dir, 'snipshare-edd-template.json')
skill_template = os.path.expanduser('~/.claude/skills/store-product-publisher/snipshare-edd-template.json')
tfile = local_template if os.path.exists(local_template) else skill_template

with open(tfile) as f:
    data = json.load(f)

data['content'] = [process_node(sec) for sec in data['content']]
data['title'] = p['name']

out = os.path.join(script_dir, f"{p['slug']}-edd-page.json")

# Serialize first, then do a raw string pass to catch anything the structured
# traversal missed (e.g. scroll-button text, docs URLs, stray product name)
json_str = json.dumps(data, indent=2, ensure_ascii=False)
for old, new in [
    ('"Get SnipShare"',                                 '"Download WP Career Board Free"'),
    ('https://docs.wbcomdesigns.com/docs/snipshare/',   'https://store.wbcomdesigns.com/wp-career-board/docs/'),
    ('store.wbcomdesigns.com/snipshare/',               'store.wbcomdesigns.com/wp-career-board/'),
    ('download_id=1652519',                             f'download_id={EDD_ITEM_ID}'),
    # Spotlight badge (in HTML span, not caught by TITLE_REPLACEMENTS)
    ('BuddyPress native',                               p['spotlight_badge']),
    # differentiator_2_body: actual JSON text differs slightly from TEMPLATE
    ('No other tool posts paste activity to your BuddyPress feed, adds a Pastes tab to profiles, or sends notifications when someone stars a paste.',
     p['differentiator_2_body']),
    # Spotlight screenshot images
    ('admin-pastes.png',                                'employer-dashboard-applications.png'),
    ('archive-pastes.png',                              'jobs-page-layout.png'),
    ('edit-paste.png',                                  'employer-dashboard-overview.png'),
    ('paste-diff.png',                                  'candidate-dashboard-overview.png'),
    ('single-paste.png',                                'job-single-page.png'),
    # Catch-all for any remaining SnipShare mentions
    ('SnipShare',                                       'WP Career Board'),
]:
    json_str = json_str.replace(old, new)

with open(out, 'w', encoding='utf-8') as f:
    f.write(json_str)

print(f"Generated: {out}")
print(f"Next: Import via Elementor → Templates → Import → {p['slug']}-edd-page.json")
print(f"Publish at: wbcomdesigns.com/downloads/{p['slug']}/")
if EDD_ITEM_ID == 0:
    print("⚠ WARNING: edd_item_id is still 0 — update it before importing to WordPress!")
