# Release notes — Unreleased

- The Japanese language pack (lang/ja) is no longer included: releases ship the English strings
  only, as the Moodle Plugins directory expects. Japanese is provided through Moodle's language
  packs.

# Release notes — 1.0.4

The block now declares support for Moodle 5.3 (supported range 5.0–5.3), and
its `composer.json` allows `moodle/moodle` `>=5.0 <5.4`, so Composer can
install it on a Moodle 5.3 site. No code changes.

The distribution ZIP now includes the plugin's `tests/` directory.
