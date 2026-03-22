#!/usr/bin/env python3
"""
EDD Elementor Page Generator — WP Career Board Pro
Usage:
  1. Create the EDD product on wbcomdesigns.com (Downloads → Add New)
  2. Set edd_item_id below to the real EDD download ID
  3. Copy snipshare-edd-template.json next to this script
  4. Run: python3 edd-page-pro.py
  5. Import wp-career-board-pro-edd-page.json via Elementor → Templates → Import
"""
import json, copy, re

# ─── FILL IN BEFORE RUNNING ─────────────────────────────────────────────────
# TODO: Replace 0 with the real EDD download ID after creating the Pro product
EDD_ITEM_ID = 1659890   # ← wbcomdesigns.com EDD item ID for WP Career Board Pro
# ─────────────────────────────────────────────────────────────────────────────

PLUGIN_DATA = {
    'name':    'WP Career Board Pro',
    'slug':    'wp-career-board-pro',
    'edd_item_id': EDD_ITEM_ID,

    # Hero
    'badge':   '🚀 Kanban Pipeline · Multi-Board · Stripe · Resume Search',
    'h1':      'Your job board is growing. Your hiring tools should grow with it.',
    'subhead':  'WP Career Board Pro adds an ATS-style Kanban pipeline, unlimited boards, resume search, and Stripe monetization to the free WP Career Board plugin. Everything your community\'s hiring process needs — inside WordPress.',

    # Problem section
    'problem_headline': 'The free version handles posting and applying. Pro handles hiring at scale.',
    'problem_points': [
        ('✗ Tracking 30 candidates in a status list',        'You can\'t see the whole pipeline at a glance — who\'s in screening, who\'s at offer stage'),
        ('✗ Running two different job boards from one install','Tech Jobs and Marketing Jobs are completely different pipelines but you have one flat list'),
        ('✗ Employers posting jobs for free when your board has real value', 'Your niche audience is worth paying for — but there\'s no way to charge per post'),
        ('✗ Candidates applying with no context about their background', 'No structured resume means employers are sifting through cover letters for basic facts'),
    ],
    'problem_resolution': 'WP Career Board Pro solves all of this — built directly on top of the free plugin.',

    # "Only one" section
    'only_one_headline': 'The only ATS-style pipeline built into WordPress community infrastructure.',
    'only_one_subhead':  'Every standalone ATS (Greenhouse, Lever, Workable) is a separate SaaS product with no WordPress integration. WP Career Board Pro runs entirely inside your site — connected to BuddyPress, styled by Reign Theme, owning your data.',
    'differentiator_1_title': 'Kanban pipeline inside WordPress',
    'differentiator_1_body':  'No other WordPress job board plugin offers a configurable Kanban hiring pipeline. Every ATS that does is a separate SaaS subscription. WP Career Board Pro does it inside your existing WordPress install — with your data, on your server.',
    'differentiator_2_title': 'Multiple boards with per-board everything',
    'differentiator_2_body':  'Run "Tech Jobs", "Marketing Jobs", and "Remote Only" as completely independent boards — each with its own jobs, employers, pipeline stages, and Stripe credit pricing — from a single WordPress install. No other plugin does this.',
    'differentiator_3_title': 'Stripe monetization with no platform cut',
    'differentiator_3_body':  'Charge employers to post jobs via Stripe credit packages. You keep 100% of revenue minus Stripe\'s standard processing fee. There is no Wbcom platform percentage, no per-posting royalty, no revenue sharing.',

    # Features section
    'features_heading': 'Pro features. One license. No add-on stacking.',
    'features': [
        ('Application Pipeline — Kanban board',  'Configurable hiring stages with drag-and-drop Kanban view. Define Screening, Interview, Offer, and any other stage you need. Per-board stage configuration.'),
        ('Multi-Board Engine',                   'Run unlimited independent job boards from one WordPress install — each with its own jobs, employers, pipeline stages, and Stripe credit pricing.'),
        ('Resume Builder + Search',              'Candidates build structured resumes inside WordPress. Employers search by skills, location, and experience. Active recruiting, not just passive application review.'),
        ('Job Alerts',                           'Candidates subscribe to keyword and location combinations. New matching jobs trigger immediate email notifications — keeps candidates engaged between visits.'),
        ('Stripe Credit System',                 'Create credit packages at any price. Employers buy via Stripe Checkout. Credits added automatically via webhook. 100% revenue to you minus Stripe\'s fee.'),
        ('Custom Field Builder',                 'Add custom fields to job listings, company profiles, and candidate profiles — text, select, checkbox, date, file upload. No code required.'),
        ('Job Map',                              'Geographic map view of job listings. Candidates filter by radius and see jobs plotted by location — essential for location-specific boards.'),
        ('AI Job Description Writing',           'AI-assisted job description generation from within the Post a Job form. Employers enter a role title and key requirements — AI drafts the description.'),
        ('Job Feed',                             'RSS, JSON, and XML feeds for every board. Aggregate listings on external sites, import into job aggregators, or pipe into other systems.'),
    ],

    # Pipeline spotlight
    'spotlight_badge':    'Hire like you mean it',
    'spotlight_headline': 'A Kanban hiring pipeline inside your WordPress site',
    'spotlight_body':     'Replace the free version\'s five-status list with a fully configurable ATS-style pipeline. Define your own stages — Phone Screen, Technical Test, Panel Interview, Offer. Drag candidates between columns. Terminal stages (Hired / Rejected) fire automatic emails and update your counters.',
    'spotlight_bullets': [
        'Custom stages — name, color, terminal outcome, drag-to-reorder',
        'Kanban board view — every column is a stage, cards show time at stage',
        'Per-board configuration — tech board has a coding test, marketing board doesn\'t',
    ],

    # Pricing — three tiers
    'pricing_heading': "One license. All Pro features. No add-on stacking.",
    'pricing_support': '<p>All tiers include every Pro feature: Kanban pipeline, multi-board, resume search, Stripe credits, custom fields, AI writing, job alerts, job map, and job feed. The only difference is the number of site licenses. Requires the free WP Career Board plugin.</p>',
    'annual_prices':   [49, 79, 149],
    'lifetime_prices': [149, 199, 399],
    'annual_descs': [
        'For single-site operators and startup founders',
        'For developers and agencies managing up to 5 sites',
        'For agencies deploying across unlimited client sites',
    ],
    'lifetime_descs': [
        'Pay once, own it forever on 1 site',
        'Permanent access across 5 sites',
        'Unlimited lifetime deployment for agencies',
    ],
    'annual_includes': [
        ['1 site license', 'All Pro features included', 'Application Pipeline (Kanban)', 'Multi-Board Engine', 'Resume Builder + Search', 'Stripe Credit System', '1 year of updates & support'],
        ['5 site licenses', 'All Pro features included', 'Application Pipeline (Kanban)', 'Multi-Board Engine', 'Resume Builder + Search', 'Custom Field Builder', 'Priority support', '1 year of updates & support'],
        ['Unlimited site licenses', 'All Pro features included', 'Application Pipeline (Kanban)', 'Multi-Board Engine + Custom Fields + AI + Job Feed', 'White-label ready', 'Priority support', '1 year of updates & support'],
    ],
    'lifetime_includes': [
        ['1 site license', 'All Pro features included', 'Application Pipeline (Kanban)', 'Multi-Board Engine', 'Resume Builder + Search', 'Stripe Credit System', 'Lifetime updates & support'],
        ['5 site licenses', 'All Pro features included', 'Application Pipeline (Kanban)', 'Multi-Board Engine', 'Resume Builder + Search', 'Custom Field Builder', 'Priority support', 'Lifetime updates & support'],
        ['Unlimited site licenses', 'All Pro features included', 'Application Pipeline (Kanban)', 'Multi-Board Engine + Custom Fields + AI + Job Feed', 'White-label ready', 'Priority support', 'Lifetime updates & support'],
    ],

    # FAQ
    'faq': [
        ('Do I need the free plugin too?',
         'Yes. WP Career Board Pro extends the free WP Career Board plugin — both must be active on your site. Pro adds features on top of the free foundation; it doesn\'t replace it. The free plugin is available at wbcomdesigns.com/downloads/wp-career-board/.'),
        ('What\'s included in every Pro tier?',
         'All tiers include the full Pro feature set: Application Pipeline (Kanban), Multi-Board Engine, Resume Builder and Search, Job Alerts, Job Map, Stripe Credit System, Custom Field Builder, AI job description writing, and Job Feed. The difference between tiers is the number of site licenses.'),
        ('How does the Stripe Credit System work?',
         'You create credit packages at any price (e.g., 1 post for $29, 5 posts for $99). Employers buy packages via Stripe Checkout. When payment completes, Stripe sends a webhook to your site and credits are added automatically. You receive 100% of the purchase price minus Stripe\'s standard processing fee — there is no Wbcom cut.'),
        ('Can I run two separate job boards from one WordPress install?',
         'Yes — that\'s the Multi-Board Engine. Create as many boards as you need, each with its own jobs, employers, pipeline stages, and credit pricing. A Board Switcher block lets visitors tab between boards on one page.'),
        ('Is there a money-back guarantee?',
         'Yes — 30-day no-questions-asked money-back guarantee. If WP Career Board Pro doesn\'t work for your use case within 30 days, contact support for a full refund.'),
        ('Does Pro work with Reign Theme and BuddyPress?',
         'Yes. All integrations from the free plugin (Reign Theme, BuddyPress, BuddyBoss, BuddyX Pro) carry through to Pro. If the free plugin works with your theme, Pro does too.'),
    ],

    # CTA
    'cta_headline': 'Turn your job board into a full hiring platform.',
    'cta_subtext':  'Requires WP Career Board (free). Works with Reign Theme, BuddyPress, BuddyBoss, and any Gutenberg-compatible theme.',
    'cta_button':   'Get WP Career Board Pro',

    # EDD checkout URLs — 6 price IDs (3 annual + 3 lifetime)
    'checkout_urls': {
        'annual_personal':    f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=1',
        'annual_developer':   f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=2',
        'annual_agency':      f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=3',
        'lifetime_personal':  f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=4',
        'lifetime_developer': f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=5',
        'lifetime_agency':    f'https://wbcomdesigns.com/checkout/?nocache=true&edd_action=add_to_cart&download_id={EDD_ITEM_ID}&edd_options[price_id]=6',
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
    ('"Get SnipShare"',                                 f'"Get WP Career Board Pro"'),
    ('https://docs.wbcomdesigns.com/docs/snipshare/',   'https://store.wbcomdesigns.com/wp-career-board-pro/docs/'),
    ('store.wbcomdesigns.com/snipshare/',               'store.wbcomdesigns.com/wp-career-board-pro/'),
    ('download_id=1652519',                             f'download_id={EDD_ITEM_ID}'),
    # Spotlight badge (in HTML span, not caught by TITLE_REPLACEMENTS)
    ('BuddyPress native',                               p['spotlight_badge']),
    # differentiator_2_body: actual JSON text differs slightly from TEMPLATE
    # Escape inner double quotes so the replacement doesn't break the JSON string
    ('No other tool posts paste activity to your BuddyPress feed, adds a Pastes tab to profiles, or sends notifications when someone stars a paste.',
     p['differentiator_2_body'].replace('"', '\\"')),
    # Spotlight screenshot images
    ('admin-pastes.png',                                'pipeline-kanban.png'),
    ('archive-pastes.png',                              'resume-builder-full.png'),
    ('edit-paste.png',                                  'field-builder-admin.png'),
    ('paste-diff.png',                                  'credits-package-add.png'),
    ('single-paste.png',                                'jobs-page-layout.png'),
    # Catch-all for any remaining SnipShare mentions
    ('SnipShare',                                       'WP Career Board Pro'),
]:
    json_str = json_str.replace(old, new)

with open(out, 'w', encoding='utf-8') as f:
    f.write(json_str)

print(f"Generated: {out}")
print(f"Next: Import via Elementor → Templates → Import → {p['slug']}-edd-page.json")
print(f"Publish at: wbcomdesigns.com/downloads/{p['slug']}/")
if EDD_ITEM_ID == 0:
    print("⚠ WARNING: edd_item_id is still 0 — update it before importing to WordPress!")
