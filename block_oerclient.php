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

use block_oerclient\local\content_builder;
use local_oerclient\local\exchange_client;

/**
 * Dashboard block for the OER Client site: "What I've shared" (this site's
 * own local_oerclient_shares, no HTTP) and "Recent OER available" (the
 * Exchange's local_oerexchange_search web service, via local_oerclient's
 * exchange_client — the same mechanism browse.php already uses).
 *
 * @package    block_oerclient
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_oerclient extends block_base {
    /** @var int how many recent shares to show in "What I've shared" */
    const SHARES_LIMIT = 8;

    /** @var int how many catalogue resources to fetch for "Recent OER available" */
    const RECENT_PERPAGE = 5;

    /**
     * Sets the block title.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_oerclient');
    }

    /**
     * This block has no instance configuration.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * Only one instance of this block per page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Dashboard only.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true];
    }

    /**
     * Builds the block content: both panels.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $out = html_writer::start_tag('div', ['class' => 'block_oerclient']);
        $out .= $this->render_shares_panel();
        $out .= $this->render_recent_panel();
        $out .= html_writer::end_tag('div');

        $this->content->text = $out;

        return $this->content;
    }

    /**
     * "What I've shared": pure DB read via content_builder, safe to unit test.
     *
     * @return string
     */
    protected function render_shares_panel(): string {
        global $DB, $USER;

        // The userid is ignored while the site-wide default scope is in
        // force, but passing it makes content_builder's documented
        // "flip one constant" toggle actually work — without it the
        // per-user branch could never match.
        $shares = content_builder::get_recent_shares(self::SHARES_LIMIT, (int) $USER->id);

        // The block title is an h5 in Boost, so panels nest below it.
        $out = html_writer::tag('h6', get_string('sharespaneltitle', 'block_oerclient'));

        if (empty($shares)) {
            return $out . html_writer::tag('p', get_string('nosharesyet', 'block_oerclient'), ['class' => 'text-muted']);
        }

        $courseids = array_unique(array_map(fn($share) => (int) $share->courseid, $shares));
        $courses = $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname');
        $isadmin = has_capability('moodle/site:config', context_system::instance());

        $out .= html_writer::start_tag('ul', ['class' => 'list-unstyled mb-0']);
        foreach ($shares as $share) {
            $statuslabel = $this->status_label($share->status);
            $coursename = isset($courses[$share->courseid])
                ? format_string($courses[$share->courseid]->fullname, true, ['context' => context_system::instance()])
                : get_string('unknowncourse', 'block_oerclient');

            $title = s($share->title);
            $isowner = (int) $share->userid === (int) $USER->id;
            $canlink = $isowner || $isadmin;
            if ($canlink) {
                $url = new moodle_url('/local/oerclient/share_status.php', ['id' => $share->id]);
                $titlehtml = html_writer::link($url, $title);
            } else {
                $titlehtml = $title;
            }

            $out .= html_writer::start_tag('li', ['class' => 'mb-2']);
            $out .= html_writer::tag('span', $titlehtml, ['class' => 'fw-bold']);
            $out .= ' ' . html_writer::tag('span', $statuslabel, ['class' => 'badge bg-secondary']);
            // Course name is already output-safe here: either format_string()'s
            // return value or a static get_string() — wrapping it in s()
            // too would double-encode entities in course names.
            $out .= html_writer::tag('div', $coursename, ['class' => 'small text-muted']);
            $out .= html_writer::end_tag('li');
        }
        $out .= html_writer::end_tag('ul');

        return $out;
    }

    /**
     * Maps a raw status column value to its display label, falling back to
     * the raw value for a status this plugin doesn't yet know about (kept
     * forward-compatible with local_oerclient's status enum).
     *
     * @param string $status
     * @return string
     */
    protected function status_label(string $status): string {
        $stringid = 'status_' . $status;
        if (get_string_manager()->string_exists($stringid, 'block_oerclient')) {
            return get_string($stringid, 'block_oerclient');
        }
        return s($status);
    }

    /**
     * "Recent OER available": calls the Exchange's local_oerexchange_search
     * web service via local_oerclient's exchange_client, the same mechanism
     * browse.php already uses. Kept thin/separate from the panel-1 logic so
     * that logic stays cleanly unit-testable even though this network call
     * isn't (covered by live verification instead).
     *
     * @return string
     */
    protected function render_recent_panel(): string {
        $out = html_writer::tag('h6', get_string('recentpaneltitle', 'block_oerclient'), ['class' => 'mt-3']);

        $exchangeurl = get_config('local_oerclient', 'exchangeurl');
        $sitetoken = get_config('local_oerclient', 'sitetoken');

        if (empty($exchangeurl) || empty($sitetoken)) {
            return $out . html_writer::tag('p', get_string('error_notregistered', 'block_oerclient'), ['class' => 'text-muted']);
        }

        // One Exchange round-trip per five minutes, not one per Dashboard
        // pageview per user — and a DOWN Exchange costs one timeout per
        // five minutes rather than hanging every user's landing page. The
        // failure state is cached under the same TTL for the same reason.
        $cache = \cache::make('block_oerclient', 'recentoer');
        $state = $cache->get('state');
        if ($state === false) {
            try {
                $client = new exchange_client($exchangeurl);
                $result = $client->call('local_oerexchange_search', [
                    'query' => '', 'type' => '', 'page' => 0, 'perpage' => self::RECENT_PERPAGE,
                ], $sitetoken);
                $state = ['failed' => false, 'results' => $result['results'] ?? []];
            } catch (\Throwable $e) {
                // A Dashboard block failing loudly would be much worse than
                // one panel showing a helpful empty state — never let this
                // bubble up.
                $state = ['failed' => true, 'results' => []];
            }
            $cache->set('state', $state);
        }

        if (!empty($state['failed'])) {
            $message = get_string('error_exchangeunreachable', 'block_oerclient');
            return $out . html_writer::tag('p', $message, ['class' => 'text-muted']);
        }

        if (empty($state['results'])) {
            return $out . html_writer::tag('p', get_string('nocatalogresources', 'block_oerclient'), ['class' => 'text-muted']);
        }

        $out .= html_writer::start_tag('ul', ['class' => 'list-unstyled mb-0']);
        foreach ($state['results'] as $r) {
            // Defensive extraction: this array came over the network from
            // the Exchange. A malformed response must degrade, not take the
            // Dashboard down with undefined-key warnings after the cache/
            // call block above has already succeeded.
            $rid = (int) ($r['id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            $url = new moodle_url('/local/oerclient/resource_preview.php', ['id' => $rid]);

            // Thumbnail left, text right. The thumbnail is inside the same
            // link as the title but hidden from assistive tech, so it is a
            // bigger click target without becoming a second announced link
            // to the same place. PARAM_URL rejects javascript:/data: schemes
            // — this URL came over the network from the Exchange, same
            // distrust as creatorprofileurl below.
            $coverurl = clean_param((string) ($r['coverimageurl'] ?? ''), PARAM_URL);
            $thumb = html_writer::link(
                $url,
                \local_oerclient\local\cover_image::listitem($coverurl !== '' ? $coverurl : null),
                ['tabindex' => '-1', 'aria-hidden' => 'true', 'class' => 'flex-shrink-0']
            );

            $text = html_writer::link($url, s((string) ($r['title'] ?? '')), ['class' => 'fw-bold']);
            if (!empty($r['creatorname'])) {
                $creatorlabel = s((string) $r['creatorname']);
                // PARAM_URL rejects javascript:/data: and other non-web
                // schemes — html_writer only attribute-escapes, so without
                // this a malicious Exchange could plant a scriptable href.
                $profileurl = clean_param((string) ($r['creatorprofileurl'] ?? ''), PARAM_URL);
                if ($profileurl !== '') {
                    $creatorlabel = html_writer::link($profileurl, $creatorlabel);
                }
                $createdby = get_string('createdby', 'block_oerclient', $creatorlabel);
                $text .= html_writer::tag('div', $createdby, ['class' => 'small text-muted']);
            }
            $text .= html_writer::tag('div', s((string) ($r['licenseshortname'] ?? '')), ['class' => 'small text-muted']);

            $out .= html_writer::start_tag('li', ['class' => 'd-flex gap-2 align-items-start mb-2']);
            $out .= $thumb;
            $out .= html_writer::div($text, 'flex-grow-1', ['style' => 'min-width:0;']);
            $out .= html_writer::end_tag('li');
        }
        $out .= html_writer::end_tag('ul');

        $out .= html_writer::link(
            new moodle_url('/local/oerclient/browse.php'),
            get_string('browsemore', 'block_oerclient'),
            ['class' => 'btn btn-sm btn-outline-primary mt-1']
        );

        return $out;
    }
}
