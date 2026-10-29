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

    /**
     * The most recent shares the viewing user may see, newest first.
     *
     * The panel stays site-wide (see DEFAULT_SCOPE_TO_USER), but each row is
     * filtered by the viewer's access. The share's owner and holders of
     * moodle/site:config see every row. Anyone else sees a share only when it
     * is 'published' AND its source course is visible to them in course
     * listings (core_course_category::can_view_course_info(): a hidden
     * course needs moodle/course:viewhiddencourses). A share whose course no
     * longer exists is shown to its owner and admins only. Without this a
     * student's Dashboard listed other users' pending/failed share titles and
     * the names of hidden courses (2026-10-28 security audit, F1).
     *
     * Rows are over-fetched in batches and trimmed after filtering, so the
     * panel still fills up to $limit rows when some are filtered out.
     *
     * @param int $limit maximum rows to return
     * @param int $userid the viewing user
     * @param bool|null $scopetouser overrides self::DEFAULT_SCOPE_TO_USER for this call
     * @return array<int, \stdClass> local_oerclient_shares rows, keyed by id
     */
    public static function get_visible_shares(int $limit, int $userid, ?bool $scopetouser = null): array {
        global $DB;

        $scopetouser = $scopetouser ?? self::DEFAULT_SCOPE_TO_USER;
        $conditions = $scopetouser ? ['userid' => $userid] : [];
        $isadmin = has_capability('moodle/site:config', \context_system::instance(), $userid);

        $visible = [];
        $batchsize = max($limit * 4, 20);
        // Bounded: at most this many batches are read, however many rows the
        // viewer is not allowed to see.
        $maxbatches = 5;
        for ($batch = 0; $batch < $maxbatches && count($visible) < $limit; $batch++) {
            $shares = $DB->get_records(
                'local_oerclient_shares',
                $conditions,
                'timemodified DESC, id DESC',
                '*',
                $batch * $batchsize,
                $batchsize
            );
            if (empty($shares)) {
                break;
            }

            $courseids = array_unique(array_map(fn($share) => (int) $share->courseid, $shares));
            $courses = $DB->get_records_list('course', 'id', $courseids, '', 'id, category, visible');

            foreach ($shares as $id => $share) {
                if (self::can_view_share($share, $courses[$share->courseid] ?? null, $userid, $isadmin)) {
                    $visible[$id] = $share;
                    if (count($visible) >= $limit) {
                        break;
                    }
                }
            }

            if (count($shares) < $batchsize) {
                break;
            }
        }

        return $visible;
    }

    /**
     * An Exchange-supplied cover image URL, accepted only when it is served
     * from the configured Exchange's own host.
     *
     * The URL lands in an img src on every viewer's Dashboard, so a cover on
     * any other host would make each viewer's browser contact that host
     * (2026-10-28 security audit, F2). Anything else returns null, so the
     * caller draws the default thumbnail instead.
     *
     * @param string $coverurl the cover URL from the Exchange
     * @param string $exchangeurl the configured local_oerclient exchangeurl
     * @return string|null the PARAM_URL-cleaned cover URL, or null for the default thumbnail
     */
    public static function exchange_cover_url(string $coverurl, string $exchangeurl): ?string {
        $coverurl = clean_param($coverurl, PARAM_URL);
        if ($coverurl === '') {
            return null;
        }
        $coverhost = parse_url($coverurl, PHP_URL_HOST);
        $exchangehost = parse_url($exchangeurl, PHP_URL_HOST);
        if (!is_string($coverhost) || !is_string($exchangehost) || $exchangehost === '') {
            return null;
        }
        if (\core_text::strtolower($coverhost) !== \core_text::strtolower($exchangehost)) {
            return null;
        }
        return $coverurl;
    }

    /**
     * Whether one share row may be shown to the viewing user.
     *
     * @param \stdClass $share local_oerclient_shares row
     * @param \stdClass|null $course the share's course (id, category, visible), null if it no longer exists
     * @param int $userid the viewing user
     * @param bool $isadmin whether the viewer holds moodle/site:config
     * @return bool
     */
    protected static function can_view_share(\stdClass $share, ?\stdClass $course, int $userid, bool $isadmin): bool {
        if ($isadmin || (int) $share->userid === $userid) {
            return true;
        }
        if ($share->status !== 'published' || $course === null) {
            return false;
        }
        return \core_course_category::can_view_course_info($course, $userid);
    }
}
