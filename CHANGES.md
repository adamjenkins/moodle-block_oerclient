# Release notes — 1.0.2

Resource titles and creator names in this block's "Shared from this site" and
"Recent on the Exchange" panels previously showed multilang markup as visible
literal text — `<span lang="en" class="multilang">…</span>` — instead of the
language the viewer is reading in. The values were HTML-escaped but never
passed through the site's text filters. They now use `format_string()`, so a
bilingual title collapses to the reader's language.

**Site requirement:** Moodle only runs the multilang filter over short strings
like titles when that filter is set to apply to *content and headings* rather
than content alone (Site administration → Plugins → Filters → Manage filters).

No database changes; no action required after upgrading beyond the usual
`admin/cli/upgrade.php`.
