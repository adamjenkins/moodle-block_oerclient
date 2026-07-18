<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_oerclient\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure-DB data for the "What I've shared" panel — no network calls, so this
 * is what gets unit-tested (unlike the Exchange web service call for the
 * "Recent OER available" panel, which the block class calls directly).
 *
 * @package    block_oerclient
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_builder {
    /**
     * Default scoping for get_recent_shares(): whether "what I've shared"
     * shows only the viewing user's own shares (true) or the whole site's
     * shares (false). BLOCKS-DESIGN.md leans site-wide — course-level
     * sharing is usually a team decision, not a personal one — but flags it
     * as an open call. Flip this one constant (or pass $scopetouser
     * explicitly) if that judgment needs revisiting; nothing else in the
     * query needs to change.
     */
    const DEFAULT_SCOPE_TO_USER = false;

    /**
     * The most recently updated shares, newest first.
     *
     * @param int $limit maximum rows to return
     * @param int|null $userid the viewing user, only used when scoping to user
     * @param bool|null $scopetouser overrides self::DEFAULT_SCOPE_TO_USER for this call
     * @return array<int, \stdClass> local_oerclient_shares rows, keyed by id
     */
    public static function get_recent_shares(int $limit = 8, ?int $userid = null, ?bool $scopetouser = null): array {
        global $DB;

        $scopetouser = $scopetouser ?? self::DEFAULT_SCOPE_TO_USER;
        $conditions = ($scopetouser && $userid !== null) ? ['userid' => $userid] : [];

        return $DB->get_records('local_oerclient_shares', $conditions, 'timemodified DESC', '*', 0, $limit);
    }
}
