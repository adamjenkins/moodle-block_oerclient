# Changelog

All notable changes to this project are documented in this file, in
[Keep a Changelog](https://keepachangelog.com/) format.

## [Unreleased]

### Changed

- The licence code in the block's resource lists is rendered through
  `\local_oerclient\local\licence_display` (a declared dependency), which wraps
  it in `<span class="oer-licence-name">` and leaves the capitals to CSS. The
  block follows `local_oerclient`'s **Show licence codes in capitals** setting,
  so the code matches the rest of the site instead of being styled
  independently.

## [1.0.2] - 2026-08-01

### Fixed

- Share titles, recent-resource titles and creator names in both block panels
  are passed through `format_string()` instead of bare `s()`, so multilang
  markup is filtered rather than rendered as literal `<span>` text.

## [1.0.1] - 2026-07-29

### Changed

- The camp release-publishing workflow now uses the registry's current
  tokenless template (OIDC trusted publishing, camp-tools v0.2.35). The
  previous template pinned camp-tools v0.2.25, whose index-entry schema
  predates the registry's `source-repo-id` field, so publication of v1.0.0
  could not succeed. No change to the plugin itself.

## [1.0.0] - 2026-07-29

First stable release. `$plugin->maturity` is now `MATURITY_STABLE`.

### Added

- Cover-image thumbnails on the "Recent OER available" panel, via
  `local_oerclient\local\cover_image::listitem()`, with a neutral panel of the
  same size where a resource has no cover so rows stay aligned.

### Changed

- Rows are laid out thumbnail-left, text-right. The thumbnail is inside the
  same link as the title but `aria-hidden`, so it widens the click target
  without announcing the same destination twice.

### Security

- The `coverimageurl` returned by the Exchange's search service is passed
  through `clean_param(..., PARAM_URL)` before rendering, as the
  creator-profile URL already was.

## [0.1.1] - 2026-07-27

### Added

- Creator attribution on the "Recent OER available" panel (code landed
  2026-07-20 but was never in a release), now with the Japanese
  translation it was missing.
- A five-minute application cache (`recentoer`) for the Exchange search
  behind "Recent OER available": one network round-trip per site instead
  of one per Dashboard view, and a down Exchange costs one bounded timeout
  per five minutes. The failure state is cached under the same TTL.
- PHPUnit: escaping tests for share titles (other users' free text) and
  unknown status values.

### Fixed

- Creator profile URLs received from the Exchange are cleaned with
  `PARAM_URL` before becoming links — `html_writer` only attribute-escapes,
  so a malicious or compromised Exchange could previously plant a
  `javascript:` href on every client user's Dashboard.
- A malformed Exchange search response (missing keys) now degrades
  gracefully instead of raising warnings after the panel's try/catch.
- The shares panel passes the viewing user's id, making content_builder's
  documented scope toggle real (previously the per-user branch could never
  match); its title now says "Shared from this site", which is what the
  site-wide default actually lists.
- `$plugin->requires` corrected from Moodle 4.5 to 5.0 (2025041400).

### Changed

- Panel headings dropped to `h6` under the block's own `h5` title;
  `format_string()` gets an explicit system context; capability check
  hoisted out of the row loop; unjustified `addinstance` riskbitmask
  removed; tests namespaced and `@covers` migrated to attributes.

## [0.1.0] - 2026-07-19

### Added

- "What I've shared" panel: the site's own `local_oerclient_shares` rows,
  newest first, with status labels.
- "Recent OER available" panel: newest resources from the connected
  Exchange via `local_oerexchange_search`, reusing `local_oerclient`'s
  `exchange_client`.
- Graceful empty state when the site isn't registered with an Exchange, or
  the Exchange can't be reached.
- `version.php` dependency on `local_oerclient`.
