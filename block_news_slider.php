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

require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once(dirname(__FILE__) . '/lib.php');

class block_news_slider extends block_base {

    /** @var int Display Mode all news */
    const DISPLAY_MODE_ALL_NEWS = 1;
    /** @var int Display Mode Site news only */
    const DISPLAY_MODE_SITE_NEWS = 2;
    /** @var int Display Mode Course news only */
    const DISPLAY_MODE_COURSE_NEWS = 3;

    public function init() {
        $this->title = get_string('blocktitle', 'block_news_slider');
    }

    public function has_config() {
        return true;
    }

    public function hide_header() {
        return true;
    }

    public function get_content() {
        global $COURSE, $USER, $OUTPUT, $PAGE;

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
        $newscontentjson->news = array_values($newsblock);
        
        // print_r ($newscontentjson);
        
        $PAGE->requires->css('/blocks/news_slider/slick/slick.css');
        $PAGE->requires->css('/blocks/news_slider/slick/slick-theme.css');

        $newscontentfinal = $OUTPUT->render_from_template('block_news_slider/slider', $newscontentjson);

        $this->content->text = html_writer::tag('div',
                $newscontentfinal,
                array('id' => 'cucourseLayout', 'class' => 'cucourseLayout'));

        return $this->content;
    }

    private function get_courses_news() {
        global $COURSE, $USER, $OUTPUT, $CFG;

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

        // Check what type of news to display from config.
        if (!empty($this->config->displaymode)) {
            $newsType = $this->config->displaymode;
        } else {
            $newsType = $this::DISPLAY_MODE_ALL_NEWS;
        }
        
        // echo "Display type is " . $newsType . '<br>';

        // Extract any assignments that are due and any news items.
        $assignments = array();
        $newsblock = new stdClass;
        $newsblock->headlines = array();
        $newsblock->newsitems = array();
        $coursenews = array();
        $tempnews = array();
        $isteacher = false;
        
        $newscontent = array();
        
        foreach ($allcourses as $course) {
            $tempnews = news_slider_get_course_news($course);
            if (!empty($tempnews)) {
                foreach ($tempnews as $news) {
                    $headline = html_writer::tag('div', html_writer::link(new moodle_url('/mod/forum/discuss.php',
                            array('d' => $news['discussion'])), $news['subject']),
                            array('class' => 'news_sliderNewsHeadline'));

                    if (isset($CFG->news_slider_excerpt_length) && ($CFG->news_slider_excerpt_length == 0) ) {
                        $newsmessage = $news['message'];
                    } else if (strlen($news['message']) > $CFG->news_slider_excerpt_length) {
                        $newsmessage = news_slider_truncate_news($news['message'], $CFG->news_slider_excerpt_length);
                    } else {
                        $newsmessage = $news['message'];
                    }
                    
                    $coursenews[] = array('headline'  => $headline,
                            'author'          => $news['author'],
                            'courseshortname' => $course->shortname,
                            'subject'         => $news['subject'],
                            'message'         => $newsmessage,
                            'userdate'        => date('l d/m/Y', $news['modified'])
                    );
                }
            }

        }

        return $coursenews;

    }
    
}