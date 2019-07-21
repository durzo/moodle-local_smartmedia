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
 * Unit tesst for local_smartmedia convserion class.
 *
 * @package    filter
 * @subpackage smartmedia
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


class local_smartmedia_conversion_testcase extends advanced_testcase {

    /**
     *
     */
    function test_get_smart_media() {
        $this->resetAfterTest(true);

        // Setup for testing.
        $fs = new file_storage();
        $filerecord = array(
            'contextid' =>  31,
            'component' => 'mod_forum',
            'filearea' => 'attachment',
            'itemid' => 2,
            'filepath' => '/',
            'filename' => 'myfile1.txt');

        $file = $fs->create_file_from_string($filerecord, 'the first test file');
        $filepathnamehash = $file->get_pathnamehash();
        $href = moodle_url::make_pluginfile_url(
            $filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
            $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename']);

        $conversion = new \local_smartmedia\conversion();
      //  $smartmedia = $conversion->get_smart_media($href, false);

        //$this->assertEquals(1, $proxy);
    }

    /**
     * Test argument extraction from various plugin types.
     */
    function test_get_pathnamehash() {
        $this->resetAfterTest(true);
        #http://moodle.local/pluginfile.php/1461/mod_label/intro/SampleVideo1mb.mp4

        // Setup the files for testing.
        $fs = new file_storage();
        $filerecord1 = array(
            'contextid' =>  31,
            'component' => 'mod_forum',
            'filearea' => 'attachment',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'myfile1.txt');

        $file1 = $fs->create_file_from_string($filerecord1, 'the first test file');
        $filepathnamehash1 = $file1->get_pathnamehash();
        $href1 = moodle_url::make_pluginfile_url(
            $filerecord1['contextid'], $filerecord1['component'], $filerecord1['filearea'],
            null, $filerecord1['filepath'], $filerecord1['filename']);

        $filerecord2 = array(
            'contextid' =>  1386, // ID of context
            'component' => 'mod_folder',
            'filearea' => 'content',
            'itemid' => 2,
            'filepath' => '/',
            'filename' => 'myfile2.txt');

        $file2 = $fs->create_file_from_string($filerecord2, 'the second test file');
        $filepathnamehash2 = $file2->get_pathnamehash();
        $href2 = moodle_url::make_pluginfile_url(
            $filerecord2['contextid'], $filerecord2['component'], $filerecord2['filearea'],
            $filerecord2['itemid'], $filerecord2['filepath'], $filerecord2['filename']);

        $filerecord3 = array(
            'contextid' =>  1386, // ID of context
            'component' => 'mod_folder',
            'filearea' => 'content',
            'itemid' => 45,
            'filepath' => '/a/b/c/',
            'filename' => 'myfile3.txt');

        $file3 = $fs->create_file_from_string($filerecord3, 'the third test file');
        $filepathnamehash3 = $file3->get_pathnamehash();
        $href3 = moodle_url::make_pluginfile_url(
            $filerecord3['contextid'], $filerecord3['component'], $filerecord3['filearea'],
            $filerecord3['itemid'], $filerecord3['filepath'], $filerecord3['filename']);

        // Instansiate new conversion class
        $conversion = new \local_smartmedia\conversion();

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('\local_smartmedia\conversion', 'get_pathnamehash');
        $method->setAccessible(true); // Allow accessing of private method.

        $result1 = $method->invoke($conversion, $href1); // Get result of invoked method.
        $result2 = $method->invoke($conversion, $href2); // Get result of invoked method.
        $result3 = $method->invoke($conversion, $href3); // Get result of invoked method.

        $this->assertEquals($filepathnamehash1, $result1);
        $this->assertEquals($filepathnamehash2, $result2);
        $this->assertEquals($filepathnamehash3, $result3);

    }

    /**
     * Test method that gets conversion status when there is no existing
     * conversion record in the database.
     */
    function test_get_conversion_status_no_record() {
        $this->resetAfterTest(true);
        $conversion = new \local_smartmedia\conversion();

        $itemhash = '7eaaf63136bfcfe8be4978e72bdbad68453dbd72';

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('\local_smartmedia\conversion', 'get_conversion_status');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke($conversion, $itemhash);

        $this->assertEquals(404, $result);

    }

    /**
     * Test that initial conversion records are successfully created.
     */
    function test_create_conversion() {
        $this->resetAfterTest(true);
        global $DB;

        $conversion = new \local_smartmedia\conversion();
        $pathnamehash = '7eaaf63136bfcfe8be4978e72bdbad68453dbd72';

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('\local_smartmedia\conversion', 'create_conversion');
        $method->setAccessible(true); // Allow accessing of private method.
        $result = $method->invoke($conversion, $pathnamehash);
        $result = $method->invoke($conversion, $pathnamehash);  // Invoke twice to check error handling

        $result = $DB->record_exists('local_smartmedia_conv', array('pathnamehash' => '7eaaf63136bfcfe8be4978e72bdbad68453dbd72'));

        $this->assertTrue($result);

    }

}
