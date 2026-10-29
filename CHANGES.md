# Release notes — 1.0.7

Security release.

- The "Shared from this site" panel still lists shares from the whole site, but now filters them
  for each viewer. Someone who neither made a share nor administers the site sees it only when it
  is published and its course is one they may see listed, so the panel no longer shows pending or
  failed shares, or hidden courses' names, to students.
- Cover images in the recent-resources panel are shown only when they come from the configured
  Exchange; any other address falls back to the default thumbnail.
- CI now also tests Moodle 5.1 (`MOODLE_501_STABLE`), which the plugin has always declared
  supported.

No database or capability changes.
