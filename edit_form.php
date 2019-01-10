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
 * Form for editing specific news_forum_slider instances.
 *
 * @package   block_news_forum_slider
 * @copyright 2017 Manoj Solanki (Coventry University)
 * @copyright 2018 DigiDago
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * News Slider edit form implementation class.
 *
 * @package block_news_forum_slider
 * @copyright 2017 Manoj Solanki (Coventry University)
 * @copyright 2018 DigiDago
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_news_forum_slider_edit_form extends block_edit_form
{

    /**
     * Override specific definition to provide news slider instance settings.
     *
     * @param MoodleQuickForm $mform
     * @throws coding_exception
     */
    protected function specific_definition($mform)
    {
        global $DB,$COURSE;
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $forums = $DB->get_records_select("forum", "course = ?", array($COURSE->id), "id ASC");

        // Display mode, all news, site news or course news.
        $displaymodeoptions = [];

        foreach ($forums as $forum) {
            $displaymodeoptions[$forum->id] = $forum->name;
        }

        $mform->addElement('text', 'config_bannertitle', get_string('bannertitle', 'block_news_forum_slider'));
        $mform->setDefault('config_bannertitle', block_news_forum_slider::NEWS_FORUM_SLIDER_DEFAULT_TITLE_BANNER);
        $mform->setType('config_bannertitle', PARAM_TEXT);

        $mform->addElement('select', 'config_displaymode', get_string('displaymode', 'block_news_forum_slider'), $displaymodeoptions);
        $mform->setDefault('config_displaymode', 0);
        $mform->setType('config_displaymode', PARAM_TEXT);
        $mform->getElement('config_displaymode')->setMultiple(true);

        $mform->addElement('text', 'config_siteitemstoshow', get_string('siteitemstoshow', 'block_news_forum_slider'));
        $mform->setDefault('config_siteitemstoshow', block_news_forum_slider::NEWS_FORUM_SLIDER_DEFAULT_SITE_NEWS_ITEMS);
        $mform->setType('config_siteitemstoshow', PARAM_TEXT);

        $mform->addElement('text', 'config_siteitemsperiod', get_string('siteitemsperiod', 'block_news_forum_slider'));
        $mform->setDefault('config_siteitemsperiod', block_news_forum_slider::NEWS_FORUM_SLIDER_DEFAULT_SITE_NEWS_PERIOD);
        $mform->setType('config_siteitemsperiod', PARAM_TEXT);

        $mform->addElement('text', 'config_courseitemstoshow', get_string('courseitemstoshow', 'block_news_forum_slider'));
        $mform->setDefault('config_courseitemstoshow', block_news_forum_slider::NEWS_FORUM_SLIDER_DEFAULT_COURSE_NEWS_ITEMS);
        $mform->setType('config_courseitemstoshow', PARAM_TEXT);

        $mform->addElement('text', 'config_courseitemsperiod', get_string('courseitemsperiod', 'block_news_forum_slider'));
        $mform->setDefault('config_courseitemsperiod', block_news_forum_slider::NEWS_FORUM_SLIDER_DEFAULT_COURSE_NEWS_PERIOD);
        $mform->setType('config_courseitemsperiod', PARAM_TEXT);

        // Show old site news link yes / no option.
        $mform->addElement('selectyesno', 'config_showoldnews', get_string('showoldnews', 'block_news_forum_slider'));
        $mform->setDefault('config_showoldnews', 0);

        // Show slick dot navigation (bullets) on bottom.
        $mform->addElement('selectyesno', 'config_showdots', get_string('showdots', 'block_news_forum_slider'));
        $mform->setDefault('config_showdots', 1);
    }
}
