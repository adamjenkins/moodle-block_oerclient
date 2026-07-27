# Release notes — 0.1.1

Review-round hardening, plus the creator-attribution line that shipped in
code on 2026-07-20 but was never released. The "Recent OER available"
panel's Exchange search is now cached for five minutes (one network
round-trip per site, not one per Dashboard view — and a down Exchange costs
one timeout per five minutes instead of hanging every landing page).
Remote-supplied data is treated as untrusted end to end: creator profile
URLs pass a scheme whitelist before becoming links, and a malformed search
response degrades instead of breaking the Dashboard. The first panel is
retitled "Shared from this site" to say what it actually lists, and the
installation floor is corrected to Moodle 5.0.
