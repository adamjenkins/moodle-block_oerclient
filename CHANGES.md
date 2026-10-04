# Release notes — 1.0.5

- The Japanese language pack (lang/ja) is no longer included: releases ship the English strings
  only, as the Moodle Plugins directory expects. Japanese is provided through Moodle's language
  packs.
- `composer.json` now requires `moodle/moodle` `^5.0` instead of `>=5.0 <5.4`, so Composer no
  longer excludes newer Moodle 5.x releases.
- Continuous integration now tests against the Moodle 5.3 stable branch (MOODLE_503_STABLE)
  instead of Moodle's development branch.

No code, database or capability changes. No action is required after upgrading.
