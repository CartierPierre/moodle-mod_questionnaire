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
 * PHPUnit questionnaire generator tests
 *
 * @package    mod_questionnaire
 * @copyright  2015 Mike Churchward (mike@churchward.ca)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');
require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');
require_once($CFG->dirroot . '/mod/questionnaire/tests/generator_test.php');
require_once($CFG->dirroot . '/mod/questionnaire/tests/questiontypes_test.php');

class mod_questionnaire_responsetypes_testcase extends advanced_testcase {
    public function test_create_response_boolean() {
        global $DB;

        $this->resetAfterTest();

        $questionnaire = $this->create_test_questionnaire(QUESYESNO, 'questionnaire_question_yesno',
            array('content' => 'Enter yes or no'));
        $question = reset($questionnaire->questions);
        $_POST['q'.$question->id] = 'y';
        $responseid = $questionnaire->response_insert($question->survey_id, 1, 0, 1);

// ** Need to determine where and when "attempt" records get added.
// ...       $attempts = $DB->get_records('questionnaire_attempts', array('qid' => $questionnaire->id, 'userid' => 1, 'rid' => $responseid));
// ...       $this->assertEquals(count($attempts), 1);

        $responses = $DB->get_records('questionnaire_response', array('survey_id' => $question->survey_id));
        $this->assertEquals(1, count($responses));
        $response = reset($responses);
        $this->assertEquals($responseid, $response->id);

        $booleanresponses = $DB->get_records('questionnaire_response_bool', array('response_id' => $responseid));
        $this->assertEquals(1, count($booleanresponses));
        $booleanresponse = reset($booleanresponses);
        $this->assertEquals($question->id, $booleanresponse->question_id);
        $this->assertEquals('y', $booleanresponse->choice_id);
    }

    public function test_create_response_text() {
        global $DB;

        $this->resetAfterTest();

        $questiondata = array(
            'content' => 'Enter some text',
            'length' => 0,
            'precise' => 5);
        $questionnaire = $this->create_test_questionnaire(QUESESSAY, 'questionnaire_question_essay', $questiondata);
        $question = reset($questionnaire->questions);
        $_POST['q'.$question->id] = 'This is my essay.';
        $responseid = $questionnaire->response_insert($question->survey_id, 1, 0, 1);

        $responses = $DB->get_records('questionnaire_response', array('survey_id' => $question->survey_id));
        $this->assertEquals(1, count($responses));
        $response = reset($responses);
        $this->assertEquals($responseid, $response->id);

        $textresponses = $DB->get_records('questionnaire_response_text', array('response_id' => $responseid));
        $this->assertEquals(1, count($textresponses));
        $textresponse = reset($textresponses);
        $this->assertEquals($question->id, $textresponse->question_id);
        $this->assertEquals('This is my essay.', $textresponse->response);
    }

    public function test_create_response_date() {
        global $DB;

        $this->resetAfterTest();

        $questionnaire = $this->create_test_questionnaire(QUESDATE, 'questionnaire_question_date',
            array('content' => 'Enter a date'));
        $question = reset($questionnaire->questions);
        // Date format is configured per site. This won't work unless it matches the configured format.
        $_POST['q'.$question->id] = '27/1/2015';
        $responseid = $questionnaire->response_insert($question->survey_id, 1, 0, 1);

        $responses = $DB->get_records('questionnaire_response', array('survey_id' => $question->survey_id));
        $this->assertEquals(1, count($responses));
        $response = reset($responses);
        $this->assertEquals($responseid, $response->id);

        $dateresponses = $DB->get_records('questionnaire_response_date', array('response_id' => $responseid));
        $this->assertEquals(1, count($dateresponses));
        $dateresponse = reset($dateresponses);
        $this->assertEquals($question->id, $dateresponse->question_id);
        // The date is always stored in the database in the same way.
        $this->assertEquals('2015-01-27', $dateresponse->response);
    }

    public function test_create_response_single() {
        global $DB;

        $this->resetAfterTest();

        // Set up a questinnaire with one question with choices including an "other" option.
        $choicedata = array(
            (object)array('content' => 'One', 'value' => 1),
            (object)array('content' => 'Two', 'value' => 2),
            (object)array('content' => 'Three', 'value' => 3),
            (object)array('content' => '!other=Something else', 'value' => 4));
        $questionnaire = $this->create_test_questionnaire(QUESRADIO, 'questionnaire_question_radio',
            array('content' => 'Select one'), $choicedata);

        // Create a response using one of the choices.
        $question = reset($questionnaire->questions);
        $val = 'unknown';
        foreach ($question->choices as $cid => $choice) {
            if ($choice->content == 'Two') {
                $val = $cid;
            }
        }
        $_POST['q'.$question->id] = $val;
        $responseid = $questionnaire->response_insert($question->survey_id, 1, 0, 1);

        // Retrieve the responses for this questionnaire.
        $responses = $DB->get_records('questionnaire_response', array('survey_id' => $question->survey_id));
        $this->assertEquals(1, count($responses));
        $response = reset($responses);
        $this->assertEquals($responseid, $response->id);

        // Retrieve the specific single response.
        $singresponses = $DB->get_records('questionnaire_resp_single', array('response_id' => $responseid));
        $this->assertEquals(1, count($singresponses));
        $singresponse = reset($singresponses);
        $this->assertEquals($question->id, $singresponse->question_id);
        $this->assertEquals($val, $singresponse->choice_id);

        // Create anothe response using the '!other' choice.
        foreach ($question->choices as $cid => $choice) {
            if ($choice->content == '!other=Something else') {
                $val = $cid;
            }
        }
        $_POST['q'.$question->id] = $val;
        $_POST['q'.$question->id.'_'.$val] = 'Forty-four';
        $responseid = $questionnaire->response_insert($question->survey_id, 1, 0, 2);

        // Retrieve the responses for this questionnaire.
        $responses = $DB->get_records('questionnaire_response', array('survey_id' => $question->survey_id));
        $this->assertEquals(2, count($responses));
        $response = $responses[$responseid];
        $this->assertEquals($responseid, $response->id);

        // Retrieve the specific single response.
        $singresponses = $DB->get_records('questionnaire_resp_single', array('response_id' => $responseid));
        $this->assertEquals(1, count($singresponses));
        $singresponse = reset($singresponses);
        $this->assertEquals($question->id, $singresponse->question_id);
        $this->assertEquals($val, $singresponse->choice_id);

        // Retrieve the 'other' response data.
        $otherresponses = $DB->get_records('questionnaire_response_other',
            array('response_id' => $responseid, 'question_id' => $question->id));
        $this->assertEquals(1, count($otherresponses));
        $otherresponse = reset($otherresponses);
        $this->assertEquals($val, $otherresponse->choice_id);
        $this->assertEquals('Forty-four', $otherresponse->response);
    }

    public function test_create_response_multiple() {
        global $DB;

        $this->resetAfterTest();

        $choicedata = array(
            (object)array('content' => 'One', 'value' => 1),
            (object)array('content' => 'Two', 'value' => 2),
            (object)array('content' => 'Three', 'value' => 3),
            (object)array('content' => '!other=Another number', 'value' => 4));
        $questionnaire = $this->create_test_questionnaire(QUESCHECK, 'questionnaire_question_check',
            array('content' => 'Select any'), $choicedata);

        $question = reset($questionnaire->questions);
        $val = array();
        foreach ($question->choices as $cid => $choice) {
            if (($choice->content == 'Two') || ($choice->content == 'Three')) {
                $val[] = $cid;
            } else if ($choice->content == '!other=Another number') {
                $val2 = $cid;
            }
        }
        $_POST['q'.$question->id] = $val;
        $_POST['q'.$question->id.'_'.$val2] = 'Forty-four';
        $responseid = $questionnaire->response_insert($question->survey_id, 1, 0, 1);

        $responses = $DB->get_records('questionnaire_response', array('survey_id' => $question->survey_id));
        $this->assertEquals(1, count($responses));
        $response = reset($responses);
        $this->assertEquals($responseid, $response->id);

        $multresponses = $DB->get_records('questionnaire_resp_multiple', array('response_id' => $responseid));
        $this->assertEquals(3, count($multresponses));
        $multresponse = reset($multresponses);
        $this->assertEquals($question->id, $multresponse->question_id);
        $this->assertEquals(reset($val), $multresponse->choice_id);
        $multresponse = next($multresponses);
        $this->assertEquals($question->id, $multresponse->question_id);
        $this->assertEquals(next($val), $multresponse->choice_id);

        $otherresponses = $DB->get_records('questionnaire_response_other',
            array('response_id' => $responseid, 'question_id' => $question->id));
        $this->assertEquals(1, count($otherresponses));
        $otherresponse = reset($otherresponses);
        $this->assertEquals($val2, $otherresponse->choice_id);
        $this->assertEquals('Forty-four', $otherresponse->response);
    }

    public function test_create_response_rank() {
        global $DB;

        $this->resetAfterTest();

        $choicedata = array(
            (object)array('content' => 'One', 'value' => 1),
            (object)array('content' => 'Two', 'value' => 2),
            (object)array('content' => 'Three', 'value' => 3));
        $questiondata = array(
            'content' => 'Rank these',
            'length' => 5,
            'precise' => 0);
        $questionnaire = $this->create_test_questionnaire(QUESRATE, 'questionnaire_question_rank',
            $questiondata, $choicedata);

        $question = reset($questionnaire->questions);
        $vals = array();
        $i = 1;
        foreach ($question->choices as $cid => $choice) {
            $vals[$cid] = $i;
            $_POST['q'.$question->id.'_'.$cid] = $i++;
        }
        $responseid = $questionnaire->response_insert($question->survey_id, 1, 0, 1);

        $responses = $DB->get_records('questionnaire_response', array('survey_id' => $question->survey_id));
        $this->assertEquals(1, count($responses));
        $response = reset($responses);
        $this->assertEquals($responseid, $response->id);

        $multresponses = $DB->get_records('questionnaire_response_rank', array('response_id' => $responseid));
        $this->assertEquals(3, count($multresponses));
        foreach ($multresponses as $multresponse) {
            $this->assertEquals($question->id, $multresponse->question_id);
            $this->assertEquals($vals[$multresponse->choice_id], $multresponse->rank);
        }
    }

// General tests to call from specific tests above.

    public function create_test_questionnaire($qtype, $questionclass, $questiondata = array(), $choicedata = null) {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata['survey_id'] = $questionnaire->sid;
        $questiondata['name'] = isset($questiondata['name']) ? $questiondata['name'] : 'Q1';
        $questiondata['content'] = isset($questiondata['content']) ? $questiondata['content'] : 'Test content';
        $question = $generator->create_question($qtype, $questiondata, $choicedata);

        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);

        return $questionnaire;
    }
}