# Changelog

All notable changes to this project are documented in this file, in
[Keep a Changelog](https://keepachangelog.com/) format.

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
