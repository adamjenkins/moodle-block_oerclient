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

/**
 * Tests for block_oerclient::render_shares_panel() — specifically the
 * capability gate that decides whether a share's title links to
 * local_oerclient's share_status.php (owner, or moodle/site:config) or
 * renders as plain text (everyone else). share_status.php itself denies
 * access under the same rule (see local_oerclient/share_status.php), so
 * this gate exists to avoid sending a non-owner into an access-denied page
 * from a passive Dashboard widget — this test locks that pairing in.
 *
 * @package    block_oerclient
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_oerclient
 */
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
        $method->setAccessible(true);
        return $method->invoke($block);
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
}
