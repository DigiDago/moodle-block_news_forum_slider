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
 * Form for editing specific news_slider instances.
 *
 * @package   block_news_slider
 * @copyright 2017 Manoj Solanki (Coventry University)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * News Slider edit form implementation class.
 *
 * @package block_news_slider
 * @copyright 2017 Manoj Solanki (Coventry University)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_news_slider_edit_form extends block_edit_form {

    /**
     * Override specific definition to provide news slider instance settings.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $CFG;
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Display mode, all news, site news or course news
        $displaymodeoptions = array(
                block_news_slider::DISPLAY_MODE_ALL_NEWS    => get_string('displaymodestringall', 'block_news_slider'),
                block_news_slider::DISPLAY_MODE_SITE_NEWS   => get_string('displaymodestringsite', 'block_news_slider'),
                block_news_slider::DISPLAY_MODE_COURSE_NEWS => get_string('displaymodestringcourse', 'block_news_slider')
        );

        $mform->addElement('select', 'config_displaymode', get_string('displaymode', 'block_news_slider'), $displaymodeoptions);
        $mform->setDefault('config_displaymode', block_news_slider::DISPLAY_MODE_ALL_NEWS);
        $mform->setType('config_displaymode', PARAM_INT);

        $mform->addElement('text', 'config_siteitemstoshow', get_string('siteitemstoshow', 'block_news_slider'));
        $mform->setDefault('config_siteitemstoshow', block_news_slider::NEWS_SLIDER_DEFAULT_SITE_NEWS_ITEMS);
        $mform->setType('config_siteitemstoshow', PARAM_TEXT);

        $mform->addElement('text', 'config_siteitemsperiod', get_string('siteitemsperiod', 'block_news_slider'));
        $mform->setDefault('config_siteitemsperiod', block_news_slider::NEWS_SLIDER_DEFAULT_SITE_NEWS_PERIOD);
        $mform->setType('config_siteitemsperiod', PARAM_TEXT);

    }
}
