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
 * News slider block helper functions and callbacks.
 *
 * @package block_news_forum_slider
 * @copyright 2017 Manoj Solanki (Coventry University)
 * @copyright 2017 John Tutchings (Coventry University)
 * @copyright 2018 DigiDago
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . "/mod/forum/lib.php");

define('NEWS_FORUM_SLIDER_EXCERPT_LENGTH', 110);
define('NEWS_FORUM_SLIDER_SUBJECT_MAX_LENGTH', 30);
define('NEWS_FORUM_SLIDER_CACHING_TTL', 300);

$defaultblocksettings = array(
    'excerptlength' => NEWS_FORUM_SLIDER_EXCERPT_LENGTH,
    'subjectmaxlength' => NEWS_FORUM_SLIDER_SUBJECT_MAX_LENGTH
);

/**
 * Get news items that need to be displayed.
 *
 * @param stdClass $course a course to get the news items from for the current user
 * @param bool $getsitenews optional flag.  If set to true, get site news instead
 * @param stdClass $sliderconfig Object containing config data
 *
 * @param null $currenttotalcoursesretrieved
 * @return array List of news items to show
 * @throws coding_exception
 * @throws moodle_exception
 */
function news_forum_slider_get_course_news($course, $getsitenews, $sliderconfig = null, &$currenttotalcoursesretrieved = null)
{
    global $OUTPUT, $COURSE;

    $posttext = '';

    $newsitems = array();
    if ($getsitenews) {
        $discussions = [];
        foreach ($getsitenews as $forumid) {
            $cm = get_coursemodule_from_instance('forum',
                $forumid, $COURSE->id, false);
            // prevent case where when we export/import course old forum not exist
            if ($cm === false){
                continue;
            }
            $totalpoststoshow = $sliderconfig->siteitemstoshow;
            $tempdiscussions = forum_get_discussions($cm, "", true,
                null, $totalpoststoshow, null, null,
                null, null, null);
            $discussions = array_merge($discussions,$tempdiscussions);
        }
    } else {
        // Get course posts.
        return $newsitems;
    }

    // If getsitenews is set to true, get site news instead.
    $strftimerecent = get_string('strftimerecent');

    // If this is a site page, do not pin course posts.
    $getpinnedposts = true;
    if (($COURSE->id <= 1) && ($course->id > 1)) {
        $getpinnedposts = false;
    }
    if (isset($discussions)) {
        foreach ($discussions as $discussion) {

            // Get user profile picture.

            // Build an object that represents the posting user.
            $postuser = new stdClass;
            $postuserfields = \core_user\fields::for_userpic()->get_required_fields();
            $postuser = username_load_fields_from_object($postuser, $discussion, null, $postuserfields);
            $postuser->id = $discussion->userid;
            $postuser->fullname = $discussion->firstname . ' ' . $discussion->lastname;
            $postuser->profilelink = new moodle_url('/user/view.php', array('id' => $discussion->userid, 'course' => $course->id));

            $userpicture = $OUTPUT->user_picture($postuser, array('courseid' => $course->id, 'size' => 80));

            $newsitems[$discussion->id]['course'] = $course->shortname;
            $newsitems[$discussion->id]['courseid'] = $course->id;
            $newsitems[$discussion->id]['discussion'] = $discussion->discussion;
            $newsitems[$discussion->id]['modified'] = $discussion->modified;
            $newsitems[$discussion->id]['author'] = $discussion->firstname . ' ' . $discussion->lastname;
            $newsitems[$discussion->id]['subject'] = $discussion->subject;
            $newsitems[$discussion->id]['message'] = $discussion->message;
            $newsitems[$discussion->id]['pinned'] = (($COURSE->id <= 1) && ($course->id > 1)) ? "" : $discussion->pinned;
            $newsitems[$discussion->id]['userdate'] = userdate($discussion->modified, $strftimerecent);
            $newsitems[$discussion->id]['userid'] = $discussion->userid;
            $newsitems[$discussion->id]['userpicture'] = $userpicture;

            // Check if message is pinned.
            if ($getpinnedposts == true) {
                if (FORUM_DISCUSSION_PINNED == $discussion->pinned) {
                    $newsitems[$discussion->id]['pinned'] = $OUTPUT->pix_icon('i/pinned', get_string('discussionpinned', 'forum'),
                        'mod_forum', array('style' => ' display: inline-block; vertical-align: middle;'));
                } else {
                    $newsitems[$discussion->id]['pinned'] = "";
                }
            }

            $posttext .= $discussion->subject;
            $posttext .= userdate($discussion->modified, $strftimerecent);
            $posttext .= $discussion->message . "\n";
        }
    }
    return $newsitems;
}

/**
 * Truncates the News item so it fits in the news tabs nicely
 *
 * @param stdClass $text The news item text.
 * @param int $length The length to trim it down to.
 * @param string $ending What to display at the end of the string if we have trimmed the item.
 * @param bool $exact
 * @param bool $considerhtml If the html make up tages should be ignored in the length to trim the text down to.
 * @return string
 */
function news_forum_slider_truncate_news($text, $length = 100, $ending = '...', $exact = false, $considerhtml = true)
{
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