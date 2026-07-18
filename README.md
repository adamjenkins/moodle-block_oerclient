# block_oerclient

A Dashboard block for the **OER Exchange** platform's client-side plugin,
[`local_oerclient`](https://github.com/adamjenkins/moodle-local_oerclient).
Install it alongside `local_oerclient` on a teacher's own Moodle site to
give the Exchange a presence on the Dashboard, without navigating to a
dedicated page first.

## What it shows

- **What I've shared** — the site's own outgoing shares to the Exchange
  (course or activity), with their status (queued, building a backup,
  uploading, published, or failed). Read directly from the local database —
  no network call.
- **Recent OER available** — a handful of the newest resources published on
  the connected Exchange, fetched via the same search web service
  `local_oerclient`'s browse page already uses.

If the site isn't yet registered with an Exchange (or the Exchange can't be
reached), the second panel shows a plain explanatory message instead of an
error.

## Requirements

- `local_oerclient` must already be installed and configured (Exchange URL
  + site token) — this block declares it as a hard dependency in
  `version.php`, so Moodle's plugin installer will refuse to install this
  block without it.
- Moodle 5.0–5.2 (`$plugin->supported`).

## Installation

1. Install `local_oerclient` first, if it isn't already, and complete its
   registration with an Exchange.
2. Copy (or `git clone`) this repository into `blocks/oerclient` in your
   Moodle installation.
3. Visit Site administration > Notifications to complete the install.
4. Add the "OER Exchange" block to your Dashboard from the block drawer.
   Moodle always adds a new block to the side panel first — drag it into
   the main content column (below Course overview/Timeline) for better
   visibility; this is a one-time step per Dashboard.

## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
