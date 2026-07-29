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
 * Tests for content_builder::get_recent_shares() — the pure-DB panel-1
 * data source. The panel-2 web service call is deliberately not unit
 * tested here (network call); it's covered by live verification instead.
 *
 * @package    block_oerclient
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(content_builder::class)]
final class content_builder_test extends \advanced_testcase {
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

    public function test_returns_empty_array_when_nothing_shared(): void {
        $this->resetAfterTest();

        $this->assertSame([], content_builder::get_recent_shares());
    }

    public function test_orders_by_timemodified_descending(): void {
        $this->resetAfterTest();

        $oldest = $this->insert_share(['title' => 'Oldest', 'timemodified' => 100]);
        $newest = $this->insert_share(['title' => 'Newest', 'timemodified' => 300]);
        $middle = $this->insert_share(['title' => 'Middle', 'timemodified' => 200]);

        $shares = array_values(content_builder::get_recent_shares());

        $this->assertCount(3, $shares);
        $this->assertSame($newest, (int) $shares[0]->id);
        $this->assertSame($middle, (int) $shares[1]->id);
        $this->assertSame($oldest, (int) $shares[2]->id);
    }

    public function test_respects_the_limit(): void {
        $this->resetAfterTest();

        for ($i = 0; $i < 5; $i++) {
            $this->insert_share(['title' => "Share $i", 'timemodified' => $i]);
        }

        $shares = content_builder::get_recent_shares(3);

        $this->assertCount(3, $shares);
    }

    public function test_default_limit_is_eight(): void {
        $this->resetAfterTest();

        for ($i = 0; $i < 10; $i++) {
            $this->insert_share(['title' => "Share $i", 'timemodified' => $i]);
        }

        $shares = content_builder::get_recent_shares();

        $this->assertCount(8, $shares);
    }

    public function test_status_is_returned_plainly_for_display(): void {
        $this->resetAfterTest();

        $this->insert_share(['status' => 'failed']);

        $shares = array_values(content_builder::get_recent_shares());

        $this->assertSame('failed', $shares[0]->status);
    }

    public function test_default_scope_is_sitewide_across_users(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->insert_share(['userid' => $user1->id, 'title' => 'User 1 share', 'timemodified' => 100]);
        $this->insert_share(['userid' => $user2->id, 'title' => 'User 2 share', 'timemodified' => 200]);

        $shares = content_builder::get_recent_shares();

        $this->assertCount(2, $shares);
    }

    public function test_can_be_scoped_to_a_single_user_via_the_scope_param(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->insert_share(['userid' => $user1->id, 'title' => 'User 1 share', 'timemodified' => 100]);
        $this->insert_share(['userid' => $user2->id, 'title' => 'User 2 share', 'timemodified' => 200]);

        $shares = array_values(content_builder::get_recent_shares(8, $user1->id, true));

        $this->assertCount(1, $shares);
        $this->assertSame((int) $user1->id, (int) $shares[0]->userid);
    }
}
