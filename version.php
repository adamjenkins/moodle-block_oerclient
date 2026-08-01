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
 * Version information for block_oerclient.
 *
 * @package    block_oerclient
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_oerclient';
$plugin->version   = 2026080101;
// 2025041400 = the Moodle 5.0 branching version — matches $supported's floor
// and composer.json. The previous value (2024100700) was Moodle 4.5 while
// its comment claimed 5.0; 4.5 sites could install a block never tested
// there (and styled with Bootstrap 5 classes 4.5 themes lack).
$plugin->requires  = 2025041400;
$plugin->supported = [500, 502];
$plugin->release   = '1.0.3';
$plugin->maturity  = MATURITY_STABLE;

// Moodle's real enforcement mechanism for "this block needs its parent
// local plugin installed" — block types can't be subplugins (see
// BLOCKS-DESIGN.md "Why not subplugins"), so this dependency declaration is
// it: the installer refuses to install/upgrade block_oerclient unless
// local_oerclient is present.
// Pinned rather than ANY_VERSION since 1.0.3: the block renders licence codes
// through \local_oerclient\local\licence_display, which arrived in
// local_oerclient 1.0.4 (2026080101). With ANY_VERSION the installer would
// happily pair this block with an older local_oerclient and the block would
// then fatal with "Class not found" the first time it rendered a resource.
$plugin->dependencies = ['local_oerclient' => 2026080101];
