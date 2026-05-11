#!/usr/bin/env bash
# bin/architecture-checks.sh — WP Career Board architecture-invariants gate.
#
# Data-driven: the canonical list of invariants lives at
# plan/INVARIANTS.yaml. This script:
#   1. Parses INVARIANTS.yaml (via PHP + symfony/yaml — dev dep)
#   2. For each invariant where `gate_function` is non-null, calls the
#      named function.
#   3. Asserts that every gate_function in the YAML has a matching
#      check_<id> function defined here (coverage gate).
#   4. Honours the `baseline` section — pre-existing violations report
#      as warnings without failing the build.
#   5. Exits 0 if zero violations, 1 otherwise.
#
# Run via:   composer arch-checks
# Or direct: bash bin/architecture-checks.sh

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
INVARIANTS_FILE="$PLUGIN_DIR/plan/INVARIANTS.yaml"

# Plugin-specific configuration. Adjust these to match the plugin's layout.
PLUGIN_SLUG="wp-career-board"                        # e.g. learnomy-pro
PLUGIN_TEXT_DOMAIN="wp-career-board"          # usually same as slug
MODELS_GLOB="modules/**/class-*.php"                        # e.g. includes/models/class-*.php  or  includes/extensions/*/models/*.php
MODEL_BASE_REGEX="extends\s+Model\b"              # e.g. extends\s+(\\\\?Learnomy\\\\Models\\\\)?Model\b
CONTROLLER_GLOB="api/endpoints/class-*-endpoint.php"                # e.g. includes/api/class-*-controller.php  or  includes/extensions/*/rest/class-controller.php
CONTROLLER_BASE_REGEX="extends\s+REST_Controller\b"    # e.g. extends\s+Base_Controller\b

VIOLATIONS_FILE="$(mktemp)"
WARNINGS_FILE="$(mktemp)"
BASELINE_JSON=""
trap 'rm -f "$VIOLATIONS_FILE" "$WARNINGS_FILE"' EXIT

violation() { echo "✗ $*"; echo "v" >> "$VIOLATIONS_FILE"; }
ok()        { echo "✓ $*"; }
warning()   { echo "⚠ $*"; echo "w" >> "$WARNINGS_FILE"; }
violations_count() { wc -l < "$VIOLATIONS_FILE" | tr -d ' '; }
warnings_count()   { wc -l < "$WARNINGS_FILE"   | tr -d ' '; }

# is_baselined GATE PATH
# Returns 0 if (gate, path) is in plan/INVARIANTS.yaml's baseline; 1 otherwise.
is_baselined() {
    local gate="$1"
    local path_check="$2"
    [ -z "$BASELINE_JSON" ] && return 1
    local baselined_paths
    baselined_paths=$(echo "$BASELINE_JSON" | jq -r --arg gate "$gate" \
        '.baseline[]? | select(.gate == $gate) | .path' 2>/dev/null)
    while IFS= read -r p; do
        [ -z "$p" ] && continue
        if echo "$path_check" | grep -qF "$p"; then
            return 0
        fi
    done <<< "$baselined_paths"
    return 1
}

# YAML → JSON via PHP. Prefer the yaml extension; fall back to symfony/yaml
# (declared as a dev dependency in composer.json).
yaml_to_json() {
    php -d display_errors=stderr -r '
        $file = $argv[1];
        $autoload = "'"$PLUGIN_DIR"'/vendor/autoload.php";
        if (file_exists($autoload)) { require $autoload; }
        if (function_exists("yaml_parse_file")) {
            $data = yaml_parse_file($file);
        } elseif (class_exists("Symfony\\Component\\Yaml\\Yaml")) {
            $data = Symfony\Component\Yaml\Yaml::parseFile($file);
        } else {
            fwrite(STDERR, "No YAML parser available. Run: composer install\n");
            exit(2);
        }
        echo json_encode($data);
    ' "$1"
}

# ==============================================================================
# Universal checks (U-group) — apply to every plugin
# ==============================================================================

check_U1() {
    # Zero raw $wpdb outside the Models layer or inside activate().
    local hits
    hits=$(grep -rEn '\$wpdb->' "$PLUGIN_DIR/includes/" 2>/dev/null \
        | grep -v "/models/\|/db/" \
        | grep -v "vendor/\|node_modules/\|tests/" \
        | grep -v "^[^:]*\.php:[0-9]*: *\* " \
        | grep -v "^[^:]*\.php:[0-9]*: *// " \
        | grep -v "get_charset_collate" \
        | grep -v "ALTER TABLE" \
        | grep -v "ADD COLUMN" \
        | grep -v "ADD KEY" \
        | grep -v "INFORMATION_SCHEMA" \
        || true)

    local real_hits=""
    local baselined_hits=""
    while IFS= read -r hit; do
        [ -z "$hit" ] && continue
        local file=$(echo "$hit" | cut -d: -f1)
        local line=$(echo "$hit" | cut -d: -f2)
        # Skip lines inside activate() — window-scan for `function activate` above.
        local start=$((line > 80 ? line - 80 : 1))
        if sed -n "${start},${line}p" "$file" 2>/dev/null | grep -q "function activate"; then
            continue
        fi
        if is_baselined U1 "$file"; then
            baselined_hits="${baselined_hits}${hit}\n"
        else
            real_hits="${real_hits}${hit}\n"
        fi
    done <<< "$hits"

    if [ -n "$baselined_hits" ]; then
        local count=$(echo -e "$baselined_hits" | grep -c .)
        warning "U1: $count baselined raw \$wpdb hit(s)"
    fi
    if [ -n "$real_hits" ]; then
        violation "U1: raw \$wpdb outside Models + activate():"
        echo -e "$real_hits" | sed 's/^/    /' | head -30
    fi
}

check_U2() {
    local violators=""
    local baselined=""
    while IFS= read -r f; do
        [ -z "$f" ] && continue
        # Skip the abstract base itself.
        if grep -qE "^abstract\s+class" "$f"; then
            continue
        fi
        if ! grep -qE "$MODEL_BASE_REGEX" "$f"; then
            if is_baselined U2 "$f"; then
                baselined="${baselined}${f}\n"
            else
                violators="${violators}${f}\n"
            fi
        fi
    done < <(find "$PLUGIN_DIR" -path "*/${MODELS_GLOB}" -type f 2>/dev/null \
             | grep -v vendor/ | grep -v node_modules/)

    if [ -n "$baselined" ]; then
        local count=$(echo -e "$baselined" | grep -c .)
        warning "U2: $count baselined Model(s)"
    fi
    if [ -n "$violators" ]; then
        violation "U2: Model class(es) not extending the plugin's Model base:"
        echo -e "$violators" | sed 's/^/    /'
    fi
}

check_U3() {
    local violators=""
    local baselined=""
    while IFS= read -r f; do
        [ -z "$f" ] && continue
        if grep -qE "^abstract\s+class" "$f"; then
            continue
        fi
        if grep -qE "class\s+\w*Controller\b" "$f"; then
            if ! grep -qE "$CONTROLLER_BASE_REGEX" "$f"; then
                if is_baselined U3 "$f"; then
                    baselined="${baselined}${f}\n"
                else
                    violators="${violators}${f}\n"
                fi
            fi
        fi
    done < <(find "$PLUGIN_DIR" -path "*/${CONTROLLER_GLOB}" -type f 2>/dev/null \
             | grep -v vendor/ | grep -v node_modules/)

    if [ -n "$baselined" ]; then
        local count=$(echo -e "$baselined" | grep -c .)
        warning "U3: $count baselined controller(s)"
    fi
    if [ -n "$violators" ]; then
        violation "U3: REST controller(s) not extending the plugin's Base_Controller:"
        echo -e "$violators" | sed 's/^/    /'
    fi
}

check_U4() {
    local manifest="$PLUGIN_DIR/audit/manifest.json"
    [ ! -f "$manifest" ] && { warning "U4: audit/manifest.json missing — run /wp-plugin-onboard"; return; }

    local at
    at=$(jq -r '.generated.at // empty' "$manifest" 2>/dev/null)
    if [ -z "$at" ]; then
        warning "U4: audit/manifest.json missing .generated.at"
        return
    fi

    local at_epoch
    at_epoch=$(date -j -f "%Y-%m-%dT%H:%M:%SZ" "$at" "+%s" 2>/dev/null || \
               date -d "$at" "+%s" 2>/dev/null || echo "0")
    local now_epoch
    now_epoch=$(date "+%s")
    local age_days=$(( (now_epoch - at_epoch) / 86400 ))

    if [ "$age_days" -gt 30 ]; then
        local commits_since
        commits_since=$(cd "$PLUGIN_DIR" && git log --since="$at" --name-only --pretty=format: 2>/dev/null \
            | grep -E "\.php$|class-.*\.php" \
            | wc -l | tr -d ' ')
        if [ "$commits_since" -gt 0 ]; then
            warning "U4: manifest is $age_days days stale and $commits_since structural commits have shipped — run /wp-plugin-onboard --refresh"
        fi
    fi
}

check_U5() {
    local readme="$PLUGIN_DIR/plan/README.yaml"
    if [ ! -f "$readme" ]; then
        violation "U5: plan/README.yaml missing — single source of truth required"
        return
    fi

    local index_json
    index_json=$(yaml_to_json "$readme" 2>/dev/null)
    if [ -z "$index_json" ]; then
        violation "U5: could not parse plan/README.yaml"
        return
    fi

    local indexed_paths
    indexed_paths=$(echo "$index_json" | jq -r '
        ([.living[]?.path] + [.dated[]?.path]) | .[]
    ' 2>/dev/null | sed 's:/$::')

    local orphans=""
    while IFS= read -r entry; do
        [ -z "$entry" ] && continue
        local base=$(basename "$entry")
        case "$base" in
            README.yaml|INVARIANTS.yaml) continue ;;
        esac
        if ! echo "$indexed_paths" | grep -qxF "$entry"; then
            orphans="${orphans}${entry}\n"
        fi
    done < <(cd "$PLUGIN_DIR/plan" && find . -maxdepth 1 \( -name "*.md" -o -name "*.yaml" -o -type d \) ! -path . | sed 's|^./||')

    if [ -n "$orphans" ]; then
        violation "U5: plan/ entries not listed in plan/README.yaml:"
        echo -e "$orphans" | sed 's/^/    /'
    fi
}

check_U6() {
    # All wp_register_script / wp_enqueue_script / wp_register_style / wp_enqueue_style
    # handles must start with the plugin slug.
    local hits
    hits=$(grep -rEn "wp_(register|enqueue)_(script|style)\(\s*['\"][a-z]" "$PLUGIN_DIR/includes/" 2>/dev/null \
            | grep -vE "['\"]${PLUGIN_SLUG}-" \
            | grep -v "vendor/\|node_modules/\|tests/" || true)
    if [ -n "$hits" ]; then
        # Filter baseline.
        local real_hits=""
        local baselined_hits=""
        while IFS= read -r hit; do
            [ -z "$hit" ] && continue
            local file=$(echo "$hit" | cut -d: -f1)
            if is_baselined U6 "$file"; then
                baselined_hits="${baselined_hits}${hit}\n"
            else
                real_hits="${real_hits}${hit}\n"
            fi
        done <<< "$hits"

        if [ -n "$baselined_hits" ]; then
            local count=$(echo -e "$baselined_hits" | grep -c .)
            warning "U6: $count baselined asset handle(s)"
        fi
        if [ -n "$real_hits" ]; then
            warning "U6: asset handle(s) missing '${PLUGIN_SLUG}-' prefix:"
            echo -e "$real_hits" | sed 's/^/    /' | head -10
        fi
    fi
}

# ==============================================================================
# Plugin-specific checks
#
# Add check_<id>() functions here for any plugin-specific invariants you
# declare in plan/INVARIANTS.yaml. The meta-check (below) refuses to run
# if a YAML entry's `gate_function` doesn't have a matching function here.
# ==============================================================================

# Example plugin-specific check — uncomment and customise:
#
# check_A1() {
#     # Your plugin-specific rule
#     :
# }

# ==============================================================================
# Meta-check: every gate_function in INVARIANTS.yaml has a check_<id> here
# ==============================================================================

check_coverage() {
    if [ ! -f "$INVARIANTS_FILE" ]; then
        violation "META: plan/INVARIANTS.yaml missing"
        return
    fi
    local invariants_json
    invariants_json=$(yaml_to_json "$INVARIANTS_FILE" 2>/dev/null)
    if [ -z "$invariants_json" ]; then
        violation "META: could not parse plan/INVARIANTS.yaml"
        return
    fi

    local missing
    missing=$(echo "$invariants_json" | jq -r '.invariants[] | select(.gate_function != null) | .id')

    local missing_funcs=""
    for id in $missing; do
        if ! declare -F "check_${id}" > /dev/null; then
            missing_funcs="${missing_funcs}    check_${id}() — declared in INVARIANTS.yaml but not implemented\n"
        fi
    done
    if [ -n "$missing_funcs" ]; then
        violation "META: gate coverage incomplete:"
        echo -e "$missing_funcs"
    fi

    local orphan_funcs=""
    while IFS= read -r fn; do
        local fid="${fn#check_}"
        [ "$fid" = "coverage" ] && continue
        if ! echo "$invariants_json" | jq -e --arg id "$fid" '.invariants[] | select(.id == $id)' > /dev/null 2>&1; then
            orphan_funcs="${orphan_funcs}    ${fn}() — defined in script but not in INVARIANTS.yaml\n"
        fi
    done < <(declare -F | awk '{print $3}' | grep "^check_")

    if [ -n "$orphan_funcs" ]; then
        violation "META: orphan check functions (not in INVARIANTS.yaml):"
        echo -e "$orphan_funcs"
    fi
}

# ==============================================================================
# Driver
# ==============================================================================

echo "=== WP Career Board architecture-invariants gate ==="
echo "Plugin:     $PLUGIN_DIR"
echo "Invariants: $INVARIANTS_FILE"
echo ""

check_coverage
if [ "$(violations_count)" -gt 0 ]; then
    echo ""
    echo "Coverage gate failed. Fix INVARIANTS.yaml ↔ script mismatch first."
    exit 1
fi
ok "META coverage (YAML ↔ script)"
echo ""

INVARIANTS_JSON=$(yaml_to_json "$INVARIANTS_FILE")
BASELINE_JSON="$INVARIANTS_JSON"

for id in $(echo "$INVARIANTS_JSON" | jq -r '.invariants[] | select(.gate_function != null) | .id'); do
    if declare -F "check_${id}" > /dev/null; then
        before=$(violations_count)
        check_${id}
        after=$(violations_count)
        if [ "$before" = "$after" ]; then
            title=$(echo "$INVARIANTS_JSON" | jq -r --arg id "$id" '.invariants[] | select(.id == $id) | .title')
            ok "$id $title"
        fi
    fi
done

echo ""
WCOUNT=$(warnings_count)
VCOUNT=$(violations_count)
if [ "$WCOUNT" -gt 0 ]; then
    echo "$WCOUNT baselined warning(s). Track them in plan/INVARIANTS.yaml baseline."
fi
if [ "$VCOUNT" -eq 0 ]; then
    echo "All architecture invariants pass."
    exit 0
else
    echo "$VCOUNT violation(s) found. See plan/INVARIANTS.yaml."
    exit 1
fi
