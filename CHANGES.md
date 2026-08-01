# Release notes — 1.0.3

Licence codes in the block's "Shared from this site" and "Recent on the
Exchange" panels are now displayed the same way as everywhere else on the site.
Previously the block styled them on its own, so a code could read differently
here than on the page it linked to.

The block now follows the OER Client plugin's **Show licence codes in capitals**
setting (on by default, so codes read `CC-SA-4.0`). The capitals come from CSS
rather than from rewriting the text, so text copied from the block matches what
the Exchange actually holds, and a screen reader reads the code out rather than
spelling out capital letters.

**Requires OER Client 1.0.4 or later**, which supplies the shared display
helper and the setting.

No database changes; no action required after upgrading beyond the usual
`admin/cli/upgrade.php`.
