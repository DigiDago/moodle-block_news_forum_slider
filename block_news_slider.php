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
 * Version details
 *
 * @package block_news_slider
 * @copyright 2017 Manoj Solanki (Coventry University)
 * @copyright
 * @copyright
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once(dirname(__FILE__) . '/lib.php');

/**
 * News Slider block implementation class.
 *
 * @package block_news_slider
 * @copyright 2017 Manoj Solanki (Coventry University)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_news_slider extends block_base {

    /** @var int Display Mode all news */
    const DISPLAY_MODE_ALL_NEWS = 1;
    /** @var int Display Mode Site news only */
    const DISPLAY_MODE_SITE_NEWS = 2;
    /** @var int Display Mode Course news only */
    const DISPLAY_MODE_COURSE_NEWS = 3;

    /** @var int Default site items to show */
    const NEWS_SLIDER_DEFAULT_SITE_NEWS_ITEMS = 4;

    /** @var int Default site news period to show */
    const NEWS_SLIDER_DEFAULT_SITE_NEWS_PERIOD = 7; // In days.

    /** @var int Default no news display text */
    const DISPLAY_NO_NEWS_TEXT = "You do not have any unread news posts at the moment";

    /**
     * Adds title to block instance.
     */
    public function init() {
        $this->title = get_string('blocktitle', 'block_news_slider');
    }

    /**
     * Calls functions to load js and css and returns block instance content.
     */
    public function get_content() {
        global $COURSE, $USER, $OUTPUT, $PAGE;

        $config = get_config("block_news_slider");
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;

        // Do not display on any course pages except the main course.
        if ($COURSE->id != 1) {
            $this->content = '';
            return $this->content;
        }

        $newsblock = $this->get_courses_news();

        $newscontentjson = new stdClass();

        $newscontentjson->title = get_string('bannertitle', 'block_news_slider');
        $newscontentjson->news = array_values($newsblock);

        $PAGE->requires->css('/blocks/news_slider/slick/slick.css');
        $PAGE->requires->css('/blocks/news_slider/slick/slick-theme.css');

        $newscontentfinal = $OUTPUT->render_from_template('block_news_slider/slider', $newscontentjson);

        $this->content->text = html_writer::tag('div',
                $newscontentfinal);

        return $this->content;
    }

    /**
     * Gets course news for relevant courses.
     *
     * @return array An array of news posts
     */
    private function get_courses_news() {
        global $COURSE, $USER, $OUTPUT, $CFG, $SITE;

        // Get all courses news.

        $allcourses = news_slider_get_overview(enrol_get_my_courses('id, shortname',
                'visible DESC,sortorder ASC'));

        foreach ($allcourses as $c) {
            if (isset($USER->lastcourseaccess[$c->id])) {
                $c->lastaccess = $USER->lastcourseaccess[$c->id];
            } else {
                $c->lastaccess = 0;
            }
        }

        // This variable is created to pass in an argument in calls to functions outside of this class
        // (i.e. news_slider_get_course_news).  This is done when the slider is displayed when a user
        // is not logged in, as the code complains about the non-existence of config instances in
        // functions called that are outside of this class.
        $sliderconfig = new stdClass();

        // Check what type of news to display from config.
        if (!empty($this->config->displaymode)) {
            $newstype = $this->config->displaymode;
        } else {
            $newstype = $this::DISPLAY_MODE_ALL_NEWS;
        }

        if (!empty($this->config->siteitemstoshow)) {
            $sliderconfig->siteitemstoshow = $this->config->siteitemstoshow;
        } else {
            $sliderconfig->siteitemstoshow = $this::NEWS_SLIDER_DEFAULT_SITE_NEWS_ITEMS;
        }

        if (!empty($this->config->siteitemsperiod)) {
            $sliderconfig->siteitemsperiod = $this->config->siteitemsperiod;
        } else {
            $sliderconfig->siteitemsperiod = $this::NEWS_SLIDER_DEFAULT_SITE_NEWS_PERIOD;
        }

        $newsblock = new stdClass;
        $newsblock->headlines = array();
        $newsblock->newsitems = array();
        $coursenews = array();
        $tempnews = array();

        $newscontent = array();

        if ( ($newstype == $this::DISPLAY_MODE_ALL_NEWS) || ($newstype == $this::DISPLAY_MODE_COURSE_NEWS) ) {
            foreach ($allcourses as $course) {
                $tempnews = news_slider_get_course_news($course);
                if (!empty($tempnews)) {
                    $this->format_course_news_items ($course, $tempnews, $coursenews);
                }

            } // End foreach.
        }

        if ( ($newstype == $this::DISPLAY_MODE_ALL_NEWS) || ($newstype == $this::DISPLAY_MODE_SITE_NEWS) ) {
            global $SITE;
            $tempnews = news_slider_get_course_news($SITE, true, $sliderconfig);
            if (!empty($tempnews)) {
                $this->format_course_news_items ($SITE, $tempnews, $coursenews);
            }
        }

        if (empty($coursenews)) {
            $coursenews[] = array(
                    'message' => get_string('nonewsitems', 'block_news_slider')
            );
        } else {
            // Sort course news items.
            foreach ($coursenews as $key => $row) {
                // Replace 0 with the field's index/key.
                $dates[$key]  = $row['datemodified'];
            }
            array_multisort($dates, SORT_DESC, $coursenews);

        }

        return $coursenews;
    }

    /**
     * Format news items ready for display and rendering by a template.
     *
     * @param stdClass $course The course from which to get the news items for the current user
     * @param array    $newsitems Array of news items to format
     * @param array    $returnedcoursenews The array to which to formatted news items
     *
     * @return None
     *
     */
    private function format_course_news_items($course, $newsitems, &$returnedcoursenews) {
        global $SITE;

        $config = get_config("block_news_slider");
        $excerptlength = $config->excerptlength;
        $subjectmaxlength = $config->subjectmaxlength;

        foreach ($newsitems as $news) {
            $newslink = new moodle_url('/mod/forum/discuss.php', array('d' => $news['discussion']));

            // Subject.  Trim if longer than $subjectmaxlength.
            $subject = $news['subject'];

            if (strlen($subject) > $subjectmaxlength) {
                $subject = preg_replace('/\s+?(\S+)?$/', '', substr($subject, 0, $subjectmaxlength)) . " ... [ Read More ]";
            }

            $headline = html_writer::tag('div', html_writer::link(new moodle_url('/mod/forum/discuss.php',
                    array('d' => $news['discussion'])), $news['subject']),
                    array('class' => 'news_sliderNewsHeadline'));

            if ( (!empty($excerptlength)) && ($excerptlength == 0) ) {
                $newsmessage = $news['message'];
            } else if (strlen($news['message']) > $excerptlength) {
                $newsmessage = news_slider_truncate_news($news['message'], $excerptlength, " .. [ Read More ]");
            } else {
                $newsmessage = $news['message'];
            }

            $newsmessage = '<a href="' . $newslink . '">' . $newsmessage . '</a>';

            // For small screen displays, prepare a shorter version of news message, regardless
            // of excerpt length config.
            $shortnewsexcerptlength = 70;
            $shortnewsmessage = news_slider_truncate_news($news['message'], $shortnewsexcerptlength, " .. [ Read More ]");
            $shortnewsmessage = '<a href="' . $newslink . '">' . $shortnewsmessage . '</a>';

            $returnedcoursenews[] = array('headline'  => $headline,
                    'author'          => ', by ' . $news['author'],
                    'courseshortname' => ($course->id == $SITE->id) ? "Site Announcement" : $course->shortname,
                    'message'         => $newsmessage,
                    'shortmessage'    => $shortnewsmessage,
                    'userdayofdate'   => date('l', $news['modified']),
                    'datemodified'    => $news['modified'],
                    'userdate'        => date('d/m/Y', $news['modified']),
                    'userid'          => $news['userid'],
                    'userpicture'     => $news['userpicture'],
                    'link'            => $newslink,
                    'profilelink'     => new moodle_url('/user/view.php', array('id' => $news['userid'], 'course' => $course->id))
            );

        }
    }

    /**
     * Allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Sets block header to be hidden or visible
     *
     * @return bool if true then header will be visible.
     */
    public function hide_header() {
        return true;
    }

}
