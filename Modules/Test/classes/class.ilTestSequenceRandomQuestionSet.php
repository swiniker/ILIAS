<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

/**
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 *
 * @package     Modules/Test
 */
class ilTestSequenceRandomQuestionSet extends ilTestSequence implements ilTestRandomQuestionSequence
{
    private $responsibleSourcePoolDefinitionByQuestion = [];

    public function loadQuestions(): void
    {
        $this->questions = [];

        $result = $this->db->queryF(
            "SELECT tst_test_rnd_qst.* FROM tst_test_rnd_qst, qpl_questions WHERE tst_test_rnd_qst.active_fi = %s AND qpl_questions.question_id = tst_test_rnd_qst.question_fi AND tst_test_rnd_qst.pass = %s ORDER BY sequence",
            array('integer','integer'),
            array($this->active_id, $this->pass)
        );
        // The following is a fix for random tests prior to ILIAS 3.8. If someone started a random test in ILIAS < 3.8, there
        // is only one test pass (pass = 0) in tst_test_rnd_qst while with ILIAS 3.8 there are questions for every test pass.
        // To prevent problems with tests started in an older version and continued in ILIAS 3.8, the first pass should be taken if
        // no questions are present for a newer pass.
        if ($result->numRows() == 0) {
            $result = $this->db->queryF(
                "SELECT tst_test_rnd_qst.* FROM tst_test_rnd_qst, qpl_questions WHERE tst_test_rnd_qst.active_fi = %s AND qpl_questions.question_id = tst_test_rnd_qst.question_fi AND tst_test_rnd_qst.pass = 0 ORDER BY sequence",
                array('integer'),
                array($this->active_id)
            );
        }

        $index = 1;

        while ($data = $this->db->fetchAssoc($result)) {
            $this->questions[$index++] = $data["question_fi"];

            $this->responsibleSourcePoolDefinitionByQuestion[$data['question_fi']] = $data['src_pool_def_fi'];
        }
    }

    /**
     * !!! LEGACY CODE !!!
     *
     * Checkes wheather a random test has already created questions for a given pass or not
     *
     * @access private
     * @param $active_id int Active id of the test
     * @param $pass int Pass of the test
     * @return boolean TRUE if the test already contains questions, FALSE otherwise
     */
    public function hasRandomQuestionsForPass(int $active_id, int $pass): bool
    {
        $result = $this->db->queryF(
            "SELECT test_random_question_id FROM tst_test_rnd_qst WHERE active_fi = %s AND pass = %s",
            array('integer','integer'),
            array($active_id, $pass)
        );
        return ($result->numRows() > 0) ? true : false;
    }

    public function getResponsibleSourcePoolDefinitionId(int $question_id): ?int
    {
        if (isset($this->responsibleSourcePoolDefinitionByQuestion[$question_id])) {
            return $this->responsibleSourcePoolDefinitionByQuestion[$question_id];
        }

        return null;
    }
}
