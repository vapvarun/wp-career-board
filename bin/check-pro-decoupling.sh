#!/usr/bin/env bash
#
# F-6: Pro coupling regression gate.
#
# Free must be Pro-blind in its actual code paths. Pro identifiers are only
# allowed in:
#   - documentation files (audit/, plan/, docs/) — they describe the contract
#   - comments — they describe what Pro does
#   - hardcoded default URLs to the marketing/store page (filterable)
#   - is_plugin_active("wp-career-board-pro/...") used during one-time
#     migrations; this is a runtime existence check, not a class/function
#     reference, and it cannot be replaced by a filter (the migration runs
#     before plugins are loaded enough to fire filters)
#
# Anything else — direct WCBP_ constant reads, WCB\Pro\ class references,
# wcbp_*() function calls, or property/option-key reads — is a coupling bug
# and must be replaced with a documented filter or action declared in
# core/class-pro-coordination.php.
#
# Wire into CI as a required check on every Free-plugin PR.

set -euo pipefail

cd "$(dirname "$0")/.."

# Patterns that always indicate coupling — no comment-allow exemption applies.
hard_patterns='(WCB\\\\Pro\\\\|new\s+WCB\\\\Pro\\\\|wcbp_[a-z_]+\s*\(|defined\(\s*.WCBP_[A-Z_]+.\s*\)|class_exists\(\s*..?WCB.?.?Pro)'

violations=$(grep -rEn "$hard_patterns" \
    --include="*.php" --include="*.js" \
    --exclude-dir=vendor --exclude-dir=node_modules \
    --exclude-dir=dist --exclude-dir=build \
    --exclude-dir=audit --exclude-dir=plan --exclude-dir=docs \
    --exclude-dir=tests \
    . 2>/dev/null \
    | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' \
    | grep -vE 'is_plugin_active.*wp-career-board-pro' \
    | grep -vE 'core/class-pro-coordination\.php' \
    || true)

if [ -n "$violations" ]; then
    echo "ERROR: Free plugin contains direct references to Pro identifiers."
    echo "Free must be Pro-blind. Replace with a filter declared in"
    echo "core/class-pro-coordination.php and let Pro hook it."
    echo
    echo "$violations"
    exit 1
fi

echo "OK — Free plugin is Pro-blind."
