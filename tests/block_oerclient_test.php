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

namespace block_oerclient;

/**
 * Tests for block_oerclient::render_shares_panel() — specifically the
 * capability gate that decides whether a share's title links to
 * local_oerclient's share_status.php (owner, or moodle/site:config) or
 * renders as plain text (everyone else). share_status.php itself denies
 * access under the same rule (see local_oerclient/share_status.php), so
 * this gate exists to avoid sending a non-owner into an access-denied page
 * from a passive Dashboard widget — this test locks that pairing in.
 *
 * Also covers both panels' text-filtering behaviour: titles and creator
 * names go through format_string() rather than s(), so a multilang-marked-up
 * name collapses to the viewer's language instead of rendering as visible
 * literal `<span lang="en" class="multilang">…` markup, and is escaped
 * exactly once on the way out.
 *
 * @package    block_oerclient
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\block_oerclient::class)]
final class block_oerclient_test extends \advanced_testcase {
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/../../moodleblock.class.php');
        require_once(__DIR__ . '/../block_oerclient.php');
        parent::setUpBeforeClass();
    }

    /**
     * Inserts a local_oerclient_shares row with sensible defaults, letting
     * the caller override any field.
     *
     * @param array $overrides
     * @return int the new share id
     */
    protected function insert_share(array $overrides = []): int {
        global $DB;

        $record = array_merge([
            'userid' => 2,
            'courseid' => 2,
            'cmid' => null,
            'type' => 'course',
            'title' => 'A shared course',
            'status' => 'published',
            'exchangeresourceid' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $overrides);

        return $DB->insert_record('local_oerclient_shares', (object) $record);
    }

    /**
     * Calls the protected render_shares_panel() method via reflection —
     * block_base's constructor only calls init(), so a bare instance is
     * safe to render without a full block-instance/page setup.
     *
     * @return string
     */
    protected function render_shares_panel(): string {
        $block = new \block_oerclient();
        $method = new \ReflectionMethod($block, 'render_shares_panel');
        return $method->invoke($block);
    }

    /**
     * Same reflection trick for the "Recent OER available" panel. That panel
     * normally reaches the Exchange over HTTP, but it reads its cache first
     * and only calls out on a miss — so seeding the cache lets the real
     * rendering path run in a unit test with no network at all.
     *
     * @param array $results the 'results' list an Exchange search would have returned
     * @return string
     */
    protected function render_recent_panel(array $results): string {
        set_config('exchangeurl', 'https://exchange.example.com', 'local_oerclient');
        set_config('sitetoken', 'test-token', 'local_oerclient');
        \cache::make('block_oerclient', 'recentoer')->set('state', ['failed' => false, 'results' => $results]);

        $block = new \block_oerclient();
        $method = new \ReflectionMethod($block, 'render_recent_panel');
        return $method->invoke($block);
    }

    /**
     * Enables the exact "content and headings" trio that format_string()
     * needs before any filter applies to a short string: the filter active
     * at system context, $CFG->filterall, and the filter listed in
     * $CFG->stringfilters. Set here rather than read from site config — a
     * unit test must not depend on how any one deployment happens to be
     * configured, and whether an admin has enabled multilang is exactly the
     * kind of setting that differs between the sites this block runs on.
     */
    protected function enable_multilang(): void {
        filter_set_global_state('multilang', TEXTFILTER_ON);
        set_config('filterall', 1);
        set_config('stringfilters', 'multilang');
        \filter_manager::reset_caches();
    }

    /**
     * A multilang-marked-up share title used to render as visible literal
     * `<span lang="en" class="multilang">…` markup, because s() escaped it
     * before any filter could collapse it. format_string() runs the filters.
     */
    public function test_share_title_multilang_collapses_to_the_current_language(): void {
        $this->resetAfterTest();
        $this->enable_multilang();

        $owner = $this->getDataGenerator()->create_user();
        $this->insert_share([
            'userid' => $owner->id,
            'title' => '<span lang="en" class="multilang">Chemistry</span>'
                . '<span lang="ja" class="multilang">化学</span>',
        ]);
        $this->setUser($owner);

        $html = $this->render_shares_panel();

        $this->assertStringContainsString('Chemistry', $html);
        $this->assertStringNotContainsString('化学', $html);
        // The regression this whole change exists to prevent: literal markup.
        $this->assertStringNotContainsString('multilang', $html);
        $this->assertStringNotContainsString('&lt;span', $html);
    }

    /**
     * The other span wins for a Japanese viewer — guards against a "fix"
     * that just hardcodes English.
     *
     * Sets $SESSION->forcelang directly rather than calling
     * force_current_language(), which gates on translation_exists('ja') and
     * so does nothing on a site with no Japanese language pack installed.
     * current_language() reads $SESSION->forcelang with no such gate, which
     * is exactly what ?lang=ja drives at request time.
     *
     * forcelang is set AFTER setUser(): setUser() clears it, so setting it
     * first silently leaves current_language() at 'en' and the assertion
     * below fails against correct production code. Confirmed by probe
     * (current_language() reads 'ja' before setUser() and 'en' after).
     */
    public function test_share_title_renders_japanese_when_current_language_is_ja(): void {
        global $SESSION;

        $this->resetAfterTest();
        $this->enable_multilang();

        $owner = $this->getDataGenerator()->create_user();
        $this->insert_share([
            'userid' => $owner->id,
            'title' => '<span lang="en" class="multilang">Chemistry</span>'
                . '<span lang="ja" class="multilang">化学</span>',
        ]);
        $this->setUser($owner);
        $SESSION->forcelang = 'ja';

        $html = $this->render_shares_panel();

        $this->assertStringContainsString('化学', $html);
        $this->assertStringNotContainsString('Chemistry', $html);
    }

    /**
     * format_string() escapes on the way out, so the title must NOT be
     * re-wrapped in s(). An ampersand appearing twice ('&amp;amp;') is the
     * signature of that double-escape.
     */
    public function test_share_title_ampersand_is_escaped_exactly_once(): void {
        $this->resetAfterTest();
        $this->enable_multilang();

        $owner = $this->getDataGenerator()->create_user();
        $this->insert_share(['userid' => $owner->id, 'title' => 'Fish & Chips']);
        $this->setUser($owner);

        $html = $this->render_shares_panel();

        $this->assertStringContainsString('Fish &amp; Chips', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * The "Recent OER available" panel renders titles and creator names that
     * came over the wire from the Exchange — the same defect, the same fix.
     */
    public function test_recent_panel_title_and_creator_collapse_to_one_language(): void {
        $this->resetAfterTest();
        $this->enable_multilang();
        $this->setUser($this->getDataGenerator()->create_user());

        $html = $this->render_recent_panel([
            [
                'id' => 42,
                'title' => '<span lang="en" class="multilang">Chemistry</span>'
                    . '<span lang="ja" class="multilang">化学</span>',
                'creatorname' => '<span lang="en" class="multilang">Ada Lovelace</span>'
                    . '<span lang="ja" class="multilang">エイダ・ラブレス</span>',
                // Supplied so cover_image::listitem() takes the plain-URL
                // branch; the default-thumbnail branch is not what's under
                // test here.
                'coverimageurl' => 'https://exchange.example.com/pluginfile.php/1/cover.jpg',
                'licenseshortname' => 'cc-sa-4.0',
            ],
        ]);

        $this->assertStringContainsString('Chemistry', $html);
        $this->assertStringContainsString('Ada Lovelace', $html);
        $this->assertStringNotContainsString('化学', $html);
        $this->assertStringNotContainsString('エイダ・ラブレス', $html);
        $this->assertStringNotContainsString('multilang', $html);
        // Licence shortnames are identifiers, not authored text: still s()-ed,
        // and still shown verbatim.
        $this->assertStringContainsString('cc-sa-4.0', $html);
    }

    /**
     * The recent panel's title sink must not double-escape either.
     */
    public function test_recent_panel_title_ampersand_is_escaped_exactly_once(): void {
        $this->resetAfterTest();
        $this->enable_multilang();
        $this->setUser($this->getDataGenerator()->create_user());

        $html = $this->render_recent_panel([
            [
                'id' => 43,
                'title' => 'Fish & Chips',
                'coverimageurl' => 'https://exchange.example.com/pluginfile.php/1/cover.jpg',
                'licenseshortname' => 'cc-sa-4.0',
            ],
        ]);

        $this->assertStringContainsString('Fish &amp; Chips', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_title_is_linked_for_the_owner(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $this->insert_share(['userid' => $owner->id, 'title' => 'Owner Share']);
        $this->setUser($owner);

        $html = $this->render_shares_panel();

        $this->assertStringContainsString('share_status.php', $html);
        $this->assertStringContainsString('Owner Share', $html);
    }

    public function test_title_is_plain_text_for_a_non_owner_without_capability(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $this->insert_share(['userid' => $owner->id, 'title' => 'Owner Share']);
        $this->setUser($viewer);

        $html = $this->render_shares_panel();

        // Same rule share_status.php enforces (userid match or
        // moodle/site:config) — a plain viewer must not get a link into a
        // page that will deny them.
        $this->assertStringNotContainsString('share_status.php', $html);
        $this->assertStringContainsString('Owner Share', $html);
    }

    public function test_title_is_linked_for_a_non_owner_with_site_config(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $this->insert_share(['userid' => $owner->id, 'title' => 'Owner Share']);
        $this->setAdminUser();

        $html = $this->render_shares_panel();

        $this->assertStringContainsString('share_status.php', $html);
    }

    /**
     * Share titles are other users' free text shown on every Dashboard, so
     * the panel's title sink is load-bearing for XSS.
     *
     * The sink is format_string() (not s(), which never filtered — see
     * test_share_title_multilang_collapses_to_the_current_language). This
     * asserts the security property that holds either way rather than one
     * particular escaping shape: format_string() removes the script element
     * outright — via strip_tags() when $CFG->formatstringstriptags is on
     * (core's default) and via clean_text()/HTMLPurifier when it is off —
     * so no assertion may depend on seeing '&lt;script&gt;' in the output.
     */
    public function test_share_titles_cannot_inject_script_markup(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $this->insert_share(['userid' => $owner->id, 'title' => '<script>alert(1)</script>Evil']);
        $this->setUser($this->getDataGenerator()->create_user());

        $html = $this->render_shares_panel();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('</script>', $html);
        // The harmless remainder of the title still reaches the page.
        $this->assertStringContainsString('Evil', $html);
    }

    /**
     * A status value this block doesn't know renders escaped, not as
     * markup.
     */
    public function test_unknown_status_falls_back_escaped(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $this->insert_share(['userid' => $owner->id, 'status' => '<b>odd</b>']);
        $this->setUser($owner);

        $html = $this->render_shares_panel();

        $this->assertStringNotContainsString('<b>odd</b>', $html);
    }
}
