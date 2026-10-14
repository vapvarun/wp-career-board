---
id: client-ip-uses-rightmost-forwarded-hop
priority: high
personas: anonymous
requires: seed:jobs
last_verified: 2026-10-13
needs: cli
bug_ref: 10355318941
---

# A client cannot rotate its rate-limit bucket by writing the left end of X-Forwarded-For

**Why this journey exists:** every per-IP limit (guest apply 10/hour, sign-ups 5/hour, app sign-in) keys on `AppCredentials::client_ip()`. With a proxy header configured it used the LEFTMOST value, which the client writes, so one attacker could pick a new address per request and never hit the limit. Since 1.8.1 the RIGHTMOST value (the hop the site's own proxy appended) is used.

## Steps

1. Baseline with no header configured: `wp eval '$_SERVER["REMOTE_ADDR"]="10.0.0.1"; $_SERVER["HTTP_X_FORWARDED_FOR"]="1.1.1.1, 203.0.113.9"; echo WCB\Auth\AppCredentials::client_ip();'` → `10.0.0.1` (an unconfigured forwarded header is never trusted).
2. Configure the header: `wp eval '$_SERVER["REMOTE_ADDR"]="10.0.0.1"; $_SERVER["HTTP_X_FORWARDED_FOR"]="1.1.1.1, 2.2.2.2, 203.0.113.9"; add_filter( "wcb_client_ip_header", fn() => "HTTP_X_FORWARDED_FOR" ); echo WCB\Auth\AppCredentials::client_ip();'` → `203.0.113.9` (rightmost), NOT `1.1.1.1`.
3. Single-value header (Cloudflare style): same snippet with `HTTP_CF_CONNECTING_IP="198.51.100.7"` and the filter returning that key → `198.51.100.7`.
4. Negative - malformed rightmost hop falls back: `HTTP_X_FORWARDED_FOR="1.1.1.1, not-an-ip"` → `10.0.0.1` (REMOTE_ADDR), never the attacker's text as a bucket key. Spaces are trimmed: `"1.1.1.1 ,  203.0.113.9 "` → `203.0.113.9`.
5. End to end: with the filter active via an mu-plugin and the proxy appending `203.0.113.9`, send 11 guest applications (`POST /wp-json/wcb/v1/jobs/<id>/apply`, distinct `guest_email` each) with `X-Forwarded-For: <n>.0.0.1, 203.0.113.9` where `<n>` changes every call → the 11th is 429 `wcb_rate_limited`. Before 1.8.1 every call looked like a new visitor and none was limited.
6. Settings path: Settings > Anti-Spam > Visitor IP address set to the X-Forwarded-For option behaves the same (the setting and the `wcb_client_ip_header` filter feed the same function).
7. tail debug.log diff → expect ZERO new fatal/warning lines.

## Teardown

```bash
wp transient delete --all
rm -f wp-content/mu-plugins/zz-ip-header.php
```

## Notes

- Docs: `docs/HOOKS.md` (`wcb_client_ip_header`) and `docs/website/developer-guide/03-rest-api.md` describe the rightmost rule.
- Only configure a forwarded header your own proxy always overwrites or appends to. With the rightmost rule, a proxy that does NOT append (passes the client's header untouched) still lets the client choose the value.
