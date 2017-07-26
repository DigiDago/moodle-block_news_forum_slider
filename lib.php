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

define('NEWS_SLIDER_EXCERPT_LENGTH', 750);

/**
 * Get an overview of activity per course for the currecnt user
 *
 * @global type $CFG
 * @global type $USER
 * @global type $DB
 * @global type $OUTPUT
 * @param type $courses array of courses that the user is on
 * @param array $remotecourses
 * @return array An array of the overview course activity for each course since the users last access
 */
function news_slider_get_overview($courses, array $remotecourses = array()) {
    global $CFG, $USER, $DB;

    $htmlarray = array();

    foreach ($courses as $course) {

        if (isset($USER->lastcourseaccess[$course->id])) {
            $course->lastaccess = $USER->lastcourseaccess[$course->id];
        } else {
            $course->lastaccess = 0;
        }
        $course->mods = array();
    }

    $modules = $DB->get_records('modules');
    if ($modules) {
        foreach ($modules as $mod) {
            if (file_exists($CFG->dirroot . '/mod/' . $mod->name . '/lib.php')) {
                include_once($CFG->dirroot . '/mod/' . $mod->name . '/lib.php');
                $fname = $mod->name . '_print_overview';
                if (function_exists($fname)) {
                    $fname($courses, $htmlarray);
                }
            }
        }
    }

    $returncourses = array();

    foreach ($courses as $course) {

        if (array_key_exists($course->id, $htmlarray)) {

            foreach ($htmlarray[$course->id] as $modname => $html) {
                $course->mods[$modname]['displaytext'] = $html;

                $course->mods[$modname]['icon'] = '/theme/image.php/' . $CFG->theme . '/' . $modname . '/1/icon';
            }
        }

        $returncourses[$course->shortname] = $course;
    }

    foreach ($remotecourses as $course) {
        $course->remoteurl = true;
        $returncourses[$course->shortname] = $course;
    }
    return $returncourses;
}


/**
 * Get the news items that need to be displayed
 *
 * @global type $USER
 * @param type $course a course to get the news items from for the current user
 * @return array List of news items to show
 */
function news_slider_get_course_news($course) {
    global $USER;

    $posttext = '';

    $newsitems = array();
    $lastlogin = 0;
    if (!isset($USER->lastcourseaccess[$course->id])) {
        $USER->lastcourseaccess[$course->id] = $lastlogin;
    }
    $newsforum = forum_get_course_forum($course->id, 'news');
    $cm = get_coursemodule_from_instance('forum', $newsforum->id, $newsforum->course);

    $strftimerecent = get_string('strftimerecent');
    $discussions = forum_get_discussions($cm);
    $notread = forum_get_discussions_unread($cm);
    if (count($notread) < 1) {
        $discussions = array();
    }

    foreach ($discussions as $discussion) {

        if (empty($notread[$discussion->discussion])) {
            continue;
        }
        $newsitems[$discussion->id]['course'] = $course->shortname;
        $newsitems[$discussion->id]['courseid'] = $course->id;
        $newsitems[$discussion->id]['discussion'] = $discussion->discussion;
        $newsitems[$discussion->id]['modified'] = $discussion->modified;
        $newsitems[$discussion->id]['author'] = $discussion->firstname . ' ' . $discussion->lastname;
        $newsitems[$discussion->id]['subject'] = $discussion->subject;
        $newsitems[$discussion->id]['message'] = $discussion->message;
        $newsitems[$discussion->id]['userdate'] = userdate($discussion->modified, $strftimerecent);

        $posttext .= $discussion->subject;
        $posttext .= userdate($discussion->modified, $strftimerecent);
        $posttext .= $discussion->message . "\n";
    }
    return $newsitems;
}


/**
 * Get the course summary for a given course.
 *
 * @global type $DB
 * @param type $course The course to ge the summary for.
 * @return string The course summary for the given course.
 */
function news_slider_get_course_summary($course) {
    global $DB;

    $rec = $DB->get_record('course', array('id' => $course->id));

    return $rec->summary;
}


/**
 * Truncates the News Item so it fits in the news tabs nicely.
 *
 * @param type $text The news item text.
 * @param type $length The length to trim it down to.
 * @param type $ending What to display at the end of the string if we have trimmed the item.
 * @param type $exact
 * @param type $considerhtml If the html make up tages should be ignored in the lenght to trim the text down to.S
 * @return string
 */
function news_slider_truncate_news($text, $length = 100, $ending = '...', $exact = false, $considerhtml = true) {
    if ($considerhtml) {
        // If the plain text is shorter than the maximum length, return the whole text.
        if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
            return $text;
        }
        // Splits all html-tags to scanable lines.
        preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
        $totallength = strlen($ending);
        $opentags = array();
        $truncate = '';
        foreach ($lines as $linematchings) {
            // If there is any html-tag in this line, handle it and add it (uncounted) to the output.
            if (!empty($linematchings[1])) {
                // If it's an "empty element" with or without xhtml-conform closing slash.
                if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $linematchings[1], $tagmatchings)) {
                    // Delete tag from $opentags list.
                    $pos = array_search($tagmatchings[1], $opentags);
                    if ($pos !== false) {
                        unset($opentags[$pos]);
                    }
                    // If tag is an opening tag.
                } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $linematchings[1], $tagmatchings)) {
                    // Add tag to the beginning of $opentags list.
                    array_unshift($opentags, strtolower($tagmatchings[1]));
                }
                // Add html-tag to $truncate'd text.
                $truncate .= $linematchings[1];
            }
            // Calculate the length of the plain text part of the line; handle entities as one character.
            $contentlength = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $linematchings[2]));
            if ($totallength + $contentlength > $length) {
                // The number of characters which are left.
                $left = $length - $totallength;
                $entitieslength = 0;
                // Search for html entities.
                if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i',
                                    $linematchings[2],
                                    $entities,
                                    PREG_OFFSET_CAPTURE)) {
                    // Calculate the real length of all entities in the legal range.
                    foreach ($entities[0] as $entity) {
                        if ($entity[1] + 1 - $entitieslength <= $left) {
                            $left--;
                            $entitieslength += strlen($entity[0]);
                        } else {
                            // No more characters left.
                            break;
                        }
                    }
                }
                $truncate .= substr($linematchings[2], 0, $left + $entitieslength);
                // Maximum length is reached, so get off the loop.
                break;
            } else {
                $truncate .= $linematchings[2];
                $totallength += $contentlength;
            }
            // If the maximum length is reached, get off the loop.
            if ($totallength >= $length) {
                break;
            }
        }
    } else {
        if (strlen($text) <= $length) {
            return $text;
        } else {
            $truncate = substr($text, 0, $length - strlen($ending));
        }
    }
    // If the words shouldn't be cut in the middle...
    if (!$exact) {
        // ...search the last occurance of a space...
        $spacepos = strrpos($truncate, ' ');
        if (isset($spacepos)) {
            // ...and cut the text in this position.
            $truncate = substr($truncate, 0, $spacepos);
        }
    }
    // Add the defined ending to the text.
    $truncate .= $ending;
    if ($considerhtml) {
        // Close all unclosed html-tags.
        foreach ($opentags as $tag) {
            $truncate .= '</' . $tag . '>';
        }
    }
    return $truncate;
}



