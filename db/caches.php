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
 * Cache definitions for block_oerclient.
 *
 * @package    block_oerclient
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // The "Recent OER available" panel's Exchange search response. The
    // catalogue is site-wide (not per-user), so one application-level entry
    // serves every Dashboard; the 5-minute TTL bounds both the staleness of
    // the panel and how often a down Exchange can cost anyone a timeout.
    'recentoer' => [
        'mode' => cache_store::MODE_APPLICATION,
        'ttl' => 300,
        'simplekeys' => true,
        'simpledata' => true,
    ],
];
