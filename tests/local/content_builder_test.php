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

    /**
     * A student sees no other user's unpublished share and no share from a
     * hidden course; the owner still sees all of their own (audit F1).
     */
    public function test_visible_shares_are_filtered_by_the_viewers_access(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $owner = $generator->create_user();
        $student = $generator->create_user();
        $visible = $generator->create_course(['summary' => '']);
        $hidden = $generator->create_course(['summary' => '', 'visible' => 0]);
        $generator->enrol_user($student->id, $visible->id, 'student');
        $generator->enrol_user($student->id, $hidden->id, 'student');

        $published = $this->insert_share(['userid' => $owner->id, 'courseid' => $visible->id, 'timemodified' => 100]);
        $pending = $this->insert_share([
            'userid' => $owner->id, 'courseid' => $visible->id, 'status' => 'pending', 'timemodified' => 200,
        ]);
        $failed = $this->insert_share([
            'userid' => $owner->id, 'courseid' => $visible->id, 'status' => 'failed', 'timemodified' => 300,
        ]);
        $inhidden = $this->insert_share(['userid' => $owner->id, 'courseid' => $hidden->id, 'timemodified' => 400]);
        $ownpending = $this->insert_share([
            'userid' => $student->id, 'courseid' => $visible->id, 'status' => 'pending', 'timemodified' => 500,
        ]);

        $this->assertSame(
            [$ownpending, $published],
            array_map('intval', array_keys(content_builder::get_visible_shares(8, (int) $student->id)))
        );
        // moodle/site:config sees every row.
        $this->assertSame(
            [$ownpending, $inhidden, $failed, $pending, $published],
            array_map('intval', array_keys(content_builder::get_visible_shares(8, (int) get_admin()->id)))
        );
        $this->assertSame(
            [$inhidden, $failed, $pending, $published],
            array_map('intval', array_keys(content_builder::get_visible_shares(8, (int) $owner->id)))
        );
    }

    /**
     * A teacher who can see the hidden course (moodle/course:viewhiddencourses)
     * sees its published shares; a share whose course was deleted is never
     * shown to a non-owner.
     */
    public function test_visible_shares_hidden_course_needs_viewhiddencourses(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $owner = $generator->create_user();
        $teacher = $generator->create_user();
        $hidden = $generator->create_course(['summary' => '', 'visible' => 0]);
        $generator->enrol_user($teacher->id, $hidden->id, 'editingteacher');

        $inhidden = $this->insert_share(['userid' => $owner->id, 'courseid' => $hidden->id, 'timemodified' => 100]);
        $this->insert_share(['userid' => $owner->id, 'courseid' => 999999, 'timemodified' => 200]);

        $this->assertSame(
            [$inhidden],
            array_map('intval', array_keys(content_builder::get_visible_shares(8, (int) $teacher->id)))
        );
    }

    /**
     * Filtered-out rows don't shrink the panel: newer rows the viewer can't
     * see are skipped and older visible rows fill it up to the limit, across
     * more than one over-fetch batch.
     */
    public function test_visible_shares_still_fill_the_limit(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $owner = $generator->create_user();
        $student = $generator->create_user();
        $course = $generator->create_course(['summary' => '']);
        $generator->enrol_user($student->id, $course->id, 'student');

        $expected = [];
        for ($i = 0; $i < 10; $i++) {
            $expected[] = $this->insert_share(['userid' => $owner->id, 'courseid' => $course->id, 'timemodified' => 100 + $i]);
        }
        // 40 newer pending shares: more than the first batch (8 * 4 = 32).
        for ($i = 0; $i < 40; $i++) {
            $this->insert_share([
                'userid' => $owner->id, 'courseid' => $course->id, 'status' => 'pending', 'timemodified' => 1000 + $i,
            ]);
        }

        $shares = content_builder::get_visible_shares(8, (int) $student->id);

        $this->assertSame(
            array_slice(array_reverse($expected), 0, 8),
            array_map('intval', array_keys($shares))
        );
    }

    /**
     * Cover URLs are accepted only on the configured Exchange's host (audit F2).
     *
     * @param string $coverurl
     * @param string|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('exchange_cover_url_provider')]
    public function test_exchange_cover_url(string $coverurl, ?string $expected): void {
        $this->assertSame($expected, content_builder::exchange_cover_url($coverurl, 'https://exchange.example.com/'));
    }

    /**
     * Cases for test_exchange_cover_url().
     *
     * @return array
     */
    public static function exchange_cover_url_provider(): array {
        $own = 'https://exchange.example.com/pluginfile.php/1/cover.jpg';
        return [
            'same host' => [$own, $own],
            'same host, other case' => ['https://Exchange.Example.com/c.jpg', 'https://Exchange.Example.com/c.jpg'],
            'foreign host' => ['https://tracker.example.net/pixel.gif', null],
            'lookalike suffix host' => ['https://exchange.example.com.evil.test/c.jpg', null],
            'subdomain' => ['https://cdn.exchange.example.com/c.jpg', null],
            'empty' => ['', null],
            'javascript scheme' => ['javascript:alert(1)', null],
        ];
    }

    /**
     * With no usable exchangeurl, no remote cover is accepted at all.
     */
    public function test_exchange_cover_url_without_an_exchange_host(): void {
        $this->assertNull(content_builder::exchange_cover_url('https://exchange.example.com/c.jpg', ''));
    }
}
