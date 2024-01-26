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

use ILIAS\TestQuestionPool\Questions\QuestionLMExportable;
use ILIAS\TestQuestionPool\Questions\QuestionAutosaveable;

/**
 * Class for text questions
 *
 * assTextQuestion is a class for text questions
 *
 * @author	Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author	Björn Heyser <bheyser@databay.de>
 * @author	Maximilian Becker <mbecker@databay.de>
 *
 * @version		$Id$
 *
 * @ingroup		ModulesTestQuestionPool
 */
class assTextQuestion extends assQuestion implements ilObjQuestionScoringAdjustable, ilObjAnswerScoringAdjustable, QuestionLMExportable, QuestionAutosaveable
{
    protected const HAS_SPECIFIC_FEEDBACK = false;

    public const SCORING_MODE_KEYWORD_RELATION_NONE = 'non';
    public const SCORING_MODE_KEYWORD_RELATION_ANY = 'any';
    public const SCORING_MODE_KEYWORD_RELATION_ALL = 'all';
    public const SCORING_MODE_KEYWORD_RELATION_ONE = 'one';

    private int $max_num_of_chars = 0;
    private bool $word_counter_enabled = false;
    private string $text_rating;
    private int $matchcondition = 0;
    private string $keyword_relation = self::SCORING_MODE_KEYWORD_RELATION_NONE;

    /**
     *
     * @var array<string>
     */
    public array $keywords;

    /**
     *
     * @var array<string>
     */
    public array $answers = [];

    /**
     * assTextQuestion constructor
     *
     * The constructor takes possible arguments an creates an instance of the assTextQuestion object.
     *
     * @param string $title A title string to describe the question
     * @param string $comment A comment string to describe the question
     * @param string $author A string containing the name of the questions author
     * @param integer $owner A numerical ID to identify the owner/creator
     * @param string $question The question string of the text question
     *
     */
    public function __construct(
        string $title = "",
        string $comment = "",
        string $author = "",
        int $owner = -1,
        string $question = ""
    ) {
        parent::__construct($title, $comment, $author, $owner, $question);
        $this->points = 1;
    }

    public function getMatchcondition(): int
    {
        return $this->matchcondition;
    }

    public function setMatchcondition(int $matchcondition): void
    {
        $this->matchcondition = $matchcondition;
    }

    /**
    * Returns true, if a multiple choice question is complete for use
    *
    * @return boolean True, if the multiple choice question is complete for use, otherwise false
    */
    public function isComplete(): bool
    {
        if (strlen($this->title)
            && $this->author
            && $this->question
            && $this->getMaximumPoints() > 0
        ) {
            return true;
        }
        return false;
    }

    /**
     * Saves a assTextQuestion object to a database
     *
     * @param string $original_id
     */
    public function saveToDb($original_id = ""): void
    {
        if ($original_id == '') {
            $this->saveQuestionDataToDb();
        } else {
            $this->saveQuestionDataToDb($original_id);
        }

        $this->saveAdditionalQuestionDataToDb();
        $this->saveAnswerSpecificDataToDb();
        parent::saveToDb();
    }

    /**
    * Loads a assTextQuestion object from a database
    *
    * @param object $db A pear DB object
    * @param integer $question_id A unique key which defines the text question in the database
    * @access public
    */
    public function loadFromDb($question_id): void
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        $result = $ilDB->queryF(
            "SELECT qpl_questions.*, " . $this->getAdditionalTableName() . ".* FROM qpl_questions LEFT JOIN " . $this->getAdditionalTableName() . " ON " . $this->getAdditionalTableName() . ".question_fi = qpl_questions.question_id WHERE qpl_questions.question_id = %s",
            ["integer"],
            [$question_id]
        );
        if ($ilDB->numRows($result) == 1) {
            $data = $ilDB->fetchAssoc($result);
            $this->setId($question_id);
            $this->setObjId($data["obj_fi"]);
            $this->setTitle((string) $data["title"]);
            $this->setComment((string) $data["description"]);
            $this->setOriginalId($data["original_id"]);
            $this->setNrOfTries($data['nr_of_tries']);
            $this->setAuthor($data["author"]);
            $this->setPoints((float) $data["points"]);
            $this->setOwner($data["owner"]);
            $this->setQuestion(ilRTE::_replaceMediaObjectImageSrc((string) $data["question_text"], 1));
            $this->setShuffle(false);
            $this->setWordCounterEnabled((bool) $data['word_cnt_enabled']);
            $this->setMaxNumOfChars($data["maxnumofchars"] ?? 0);
            $this->setTextRating($this->isValidTextRating($data["textgap_rating"]) ? $data["textgap_rating"] : assClozeGap::TEXTGAP_RATING_CASEINSENSITIVE);
            $this->matchcondition = (isset($data['matchcondition'])) ? (int) $data['matchcondition'] : 0;
            $this->setKeywordRelation(($data['keyword_relation']));

            try {
                $this->setLifecycle(ilAssQuestionLifecycle::getInstance($data['lifecycle']));
            } catch (ilTestQuestionPoolInvalidArgumentException $e) {
                $this->setLifecycle(ilAssQuestionLifecycle::getDraftInstance());
            }

            try {
                $this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
            } catch (ilTestQuestionPoolException $e) {
            }
        }

        $result = $ilDB->queryF(
            "SELECT * FROM qpl_a_essay WHERE question_fi = %s",
            ['integer'],
            [$this->getId()]
        );

        $this->flushAnswers();
        while ($row = $ilDB->fetchAssoc($result)) {
            $this->addAnswer($row['answertext'], $row['points']);
        }

        parent::loadFromDb($question_id);
    }

    /**
    * Duplicates an assTextQuestion
    *
    * @access public
    */
    public function duplicate(bool $for_test = true, string $title = "", string $author = "", int $owner = -1, $testObjId = null): int
    {
        if ($this->id <= 0) {
            // The question has not been saved. It cannot be duplicated
            return -1;
        }
        // duplicate the question in database
        $this_id = $this->getId();
        $thisObjId = $this->getObjId();

        $clone = $this;

        $original_id = $this->questioninfo->getOriginalId($this->id);
        $clone->id = -1;

        if ((int) $testObjId > 0) {
            $clone->setObjId($testObjId);
        }

        if ($title) {
            $clone->setTitle($title);
        }

        if ($author) {
            $clone->setAuthor($author);
        }
        if ($owner) {
            $clone->setOwner($owner);
        }

        if ($for_test) {
            $clone->saveToDb($original_id);
        } else {
            $clone->saveToDb();
        }

        // copy question page content
        $clone->copyPageOfQuestion($this_id);
        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($this_id);
        #$clone->duplicateAnswers($this_id);

        $clone->onDuplicate($thisObjId, $this_id, $clone->getObjId(), $clone->getId());

        return $clone->id;
    }

    /**
    * Copies an assTextQuestion object
    *
    * @access public
    */
    public function copyObject($target_questionpool_id, $title = ""): int
    {
        if ($this->getId() <= 0) {
            throw new RuntimeException('The question has not been saved. It cannot be duplicated');
        }
        // duplicate the question in database
        $clone = $this;

        $original_id = $this->questioninfo->getOriginalId($this->id);
        $clone->id = -1;
        $source_questionpool_id = $this->getObjId();
        $clone->setObjId($target_questionpool_id);
        if ($title) {
            $clone->setTitle($title);
        }
        $clone->saveToDb();
        // copy question page content
        $clone->copyPageOfQuestion($original_id);
        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($original_id);
        // duplicate answers
        #$clone->duplicateAnswers($original_id);

        $clone->onCopy($source_questionpool_id, $original_id, $clone->getObjId(), $clone->getId());

        return $clone->id;
    }

    public function createNewOriginalFromThisDuplicate(
        int $target_parent_id,
        string $target_question_title = ""
    ): int {
        if ($this->getId() <= 0) {
            throw new RuntimeException('The question has not been saved. It cannot be duplicated');
        }

        $source_question_id = $this->id;
        $source_parent_id = $this->getObjId();

        // duplicate the question in database
        $clone = $this;
        $clone->id = -1;

        $clone->setObjId($target_parent_id);

        if ($target_question_title !== '') {
            $clone->setTitle($target_question_title);
        }

        $clone->saveToDb();
        $clone->copyPageOfQuestion($source_question_id);
        $clone->copyXHTMLMediaObjectsOfQuestion($source_question_id);
        $clone->onCopy($source_parent_id, $source_question_id, $clone->getObjId(), $clone->getId());

        return $clone->id;
    }

    public function getMaxNumOfChars(): int
    {
        return $this->max_num_of_chars;
    }

    public function setMaxNumOfChars(int $maxchars = 0): void
    {
        $this->max_num_of_chars = $maxchars;
    }

    public function isWordCounterEnabled(): bool
    {
        return $this->word_counter_enabled;
    }

    public function setWordCounterEnabled(bool $word_counter_enabled): void
    {
        $this->word_counter_enabled = $word_counter_enabled;
    }

    /**
    * Returns the maximum points, a learner can reach answering the question
    *
    * @access public
    * @see $points
    */
    public function getMaximumPoints(): float
    {
        if (in_array($this->getKeywordRelation(), self::getScoringModesWithPointsByQuestion())) {
            return parent::getPoints();
        }

        $points = 0;

        foreach ($this->answers as $answer) {
            if ($answer->getPoints() > 0) {
                $points = $points + $answer->getPoints();
            }
        }

        return $points;
    }

    public function getMinimumPoints()
    {
        if (in_array($this->getKeywordRelation(), self::getScoringModesWithPointsByQuestion())) {
            return 0;
        }

        $points = 0;

        foreach ($this->answers as $answer) {
            if ($answer->getPoints() < 0) {
                $points = $points + $answer->getPoints();
            }
        }

        return $points;
    }

    private function isValidTextRating($textRating): bool
    {
        switch ($textRating) {
            case assClozeGap::TEXTGAP_RATING_CASEINSENSITIVE:
            case assClozeGap::TEXTGAP_RATING_CASESENSITIVE:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN1:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN2:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN3:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN4:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN5:
                return true;
        }

        return false;
    }

    /**
    * Checks if one of the keywords matches the answertext
    *
    * @param string $answertext The answertext of the user
    * @param string $a_keyword The keyword which should be checked
    * @return boolean TRUE if the keyword matches, FALSE otherwise
    * @access private
    */
    public function isKeywordMatching($answertext, $a_keyword): bool
    {
        global $DIC;
        $refinery = $DIC->refinery();
        $result = false;
        $textrating = $this->getTextRating();

        switch ($textrating) {
            case assClozeGap::TEXTGAP_RATING_CASEINSENSITIVE:
                if (ilStr::strPos(ilStr::strToLower($answertext), ilStr::strToLower($a_keyword), 0) !== false) {
                    return true;
                }
                break;
            case assClozeGap::TEXTGAP_RATING_CASESENSITIVE:
                if (ilStr::strPos($answertext, $a_keyword) !== false) {
                    return true;
                }
                break;
        }

        // "<p>red</p>" would not match "red" even with distance of 5
        $answertext = strip_tags($answertext);
        $answerwords = [];
        if (preg_match_all("/([^\s.]+)/", $answertext, $matches)) {
            foreach ($matches[1] as $answerword) {
                array_push($answerwords, trim($answerword));
            }
        }

        // create correct transformation
        switch ($textrating) {
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN1:
                $transformation = $refinery->string()->levenshtein()->standard($a_keyword, 1);
                break;
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN2:
                $transformation = $refinery->string()->levenshtein()->standard($a_keyword, 2);
                break;
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN3:
                $transformation = $refinery->string()->levenshtein()->standard($a_keyword, 3);
                break;
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN4:
                $transformation = $refinery->string()->levenshtein()->standard($a_keyword, 4);
                break;
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN5:
                $transformation = $refinery->string()->levenshtein()->standard($a_keyword, 5);
                break;
        }

        // run answers against Levenshtein methods
        foreach ($answerwords as $a_original) {
            if (isset($transformation) && $transformation->transform($a_original) >= 0) {
                return true;
            }
        }
        return $result;
    }

    protected function calculateReachedPointsForSolution($solution)
    {
        $solution = html_entity_decode($solution);
        // Return min points when keyword relation is NON KEYWORDS
        if ($this->getKeywordRelation() === self::SCORING_MODE_KEYWORD_RELATION_NONE) {
            return $this->getMinimumPoints();
        }

        // Return min points if there are no answers present.
        $answers = $this->getAnswers();

        if (count($answers) == 0) {
            return $this->getMinimumPoints();
        }

        switch ($this->getKeywordRelation()) {
            case 'any':
                $points = 0;
                foreach ($answers as $answer) {
                    $qst_answer = $answer->getAnswertext();
                    $user_answer = '  ' . $solution;
                    if ($this->isKeywordMatching($user_answer, $qst_answer)) {
                        $points += $answer->getPoints();
                    }
                }
                break;

            case 'all':
                $points = $this->getMaximumPoints();
                foreach ($answers as $answer) {
                    $qst_answer = $answer->getAnswertext();
                    $user_answer = '  ' . $solution;
                    if (!$this->isKeywordMatching($user_answer, $qst_answer)) {
                        $points = 0;
                        break;
                    }
                }
                break;

            case 'one':
                $points = 0;
                foreach ($answers as $answer) {
                    $qst_answer = $answer->getAnswertext();
                    $user_answer = '  ' . $solution;
                    if ($this->isKeywordMatching($user_answer, $qst_answer)) {
                        $points = $this->getMaximumPoints();
                        break;
                    }
                }
                break;
        }

        return $points;
    }

    /**
     * Returns the points, a learner has reached answering the question.
     * The points are calculated from the given answers.
     *
     * @access public
     * @param integer $active_id
     * @param integer $pass
     * @param boolean $returndetails (deprecated !!)
     * @return integer/array $points/$details (array $details is deprecated !!)
     */
    public function calculateReachedPoints($active_id, $pass = null, $authorizedSolution = true, $returndetails = false)
    {
        if ($returndetails) {
            throw new ilTestException('return details not implemented for ' . __METHOD__);
        }

        global $DIC;
        $ilDB = $DIC['ilDB'];

        $points = 0;
        if (is_null($pass)) {
            $pass = $this->getSolutionMaxPass($active_id);
        }

        $result = $this->getCurrentSolutionResultSet($active_id, $pass, $authorizedSolution);

        // Return min points when no answer was given.
        if ($ilDB->numRows($result) == 0) {
            return $this->getMinimumPoints();
        }

        // Return points of points are already on the row.
        $row = $ilDB->fetchAssoc($result);
        if ($row["points"] != null) {
            return $row["points"];
        }

        return $this->calculateReachedPointsForSolution($row['value1']);
    }

    /**
     * Saves the learners input of the question to the database.
     *
     * @access public
     * @param integer $active_id Active id of the user
     * @param integer $pass Test pass
     * @return boolean $status
     */
    public function saveWorkingData($active_id, $pass = null, $authorized = true): bool
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];
        $ilUser = $DIC['ilUser'];

        if (is_null($pass)) {
            $pass = ilObjTest::_getPass($active_id);
        }

        $entered_values = 0;
        $text = $this->getSolutionSubmit();

        $this->getProcessLocker()->executeUserSolutionUpdateLockOperation(function () use (&$entered_values, $active_id, $pass, $authorized, $text) {
            $this->removeCurrentSolution($active_id, $pass, $authorized);

            if (strlen($text)) {
                $this->saveCurrentSolution($active_id, $pass, trim($text), null, $authorized);
                $entered_values++;
            }
        });

        return true;
    }

    /**
     * @return mixed|string
     */
    public function getSolutionSubmit()
    {
        if (ilObjAdvancedEditing::_getRichTextEditor() === 'tinymce') {
            $text = ilUtil::stripSlashes($_POST["TEXT"], false);
        } else {
            $text = htmlentities($_POST["TEXT"]);
        }

        if (ilUtil::isHTML($text)) {
            $text = $this->getHtmlUserSolutionPurifier()->purify($text);
        }

        return $text;
    }

    public function saveAdditionalQuestionDataToDb()
    {
        /** @var ilDBInterface $ilDB */
        global $DIC;
        $ilDB = $DIC['ilDB'];
        $ilDB->manipulateF(
            "DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s",
            ['integer'],
            [$this->getId()]
        );

        $fields = [
            'question_fi' => ['integer', $this->getId()],
            'maxnumofchars' => ['integer', $this->getMaxNumOfChars()],
            'word_cnt_enabled' => ['integer', (int) $this->isWordCounterEnabled()],
            'keywords' => ['text', null],
            'textgap_rating' => ['text', $this->getTextRating()],
            'matchcondition' => ['integer', $this->getMatchcondition()],
            'keyword_relation' => ['text', $this->getKeywordRelation()]
        ];

        $ilDB->insert($this->getAdditionalTableName(), $fields);
    }

    public function saveAnswerSpecificDataToDb()
    {
        /** @var ilDBInterface $ilDB */
        global $DIC;
        $ilDB = $DIC['ilDB'];

        $ilDB->manipulateF(
            "DELETE FROM qpl_a_essay WHERE question_fi = %s",
            ['integer'],
            [$this->getId()]
        );

        foreach ($this->answers as $answer) {
            /** @var $answer ASS_AnswerMultipleResponseImage */
            $nextID = $ilDB->nextId('qpl_a_essay');
            $ilDB->manipulateF(
                "INSERT INTO qpl_a_essay (answer_id, question_fi, answertext, points) VALUES (%s, %s, %s, %s)",
                ['integer', 'integer', 'text', 'float'],
                [
                    $nextID,
                    $this->getId(),
                    $answer->getAnswertext(),
                    $answer->getPoints()
                ]
            );
        }
    }

    /**
    * Returns the question type of the question
    *
    * @return integer The question type of the question
    * @access public
    */
    public function getQuestionType(): string
    {
        return "assTextQuestion";
    }

    /**
    * Returns the rating option for text comparisons
    *
    * @return string The rating option for text comparisons
    * @see $text_rating
    * @access public
    */
    public function getTextRating(): string
    {
        return $this->text_rating;
    }

    /**
    * Sets the rating option for text comparisons
    *
    * @param string $a_textgap_rating The rating option for text comparisons
    * @see $textgap_rating
    * @access public
    */
    public function setTextRating($a_text_rating): void
    {
        switch ($a_text_rating) {
            case assClozeGap::TEXTGAP_RATING_CASEINSENSITIVE:
            case assClozeGap::TEXTGAP_RATING_CASESENSITIVE:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN1:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN2:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN3:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN4:
            case assClozeGap::TEXTGAP_RATING_LEVENSHTEIN5:
                $this->text_rating = $a_text_rating;
                break;
            default:
                $this->text_rating = assClozeGap::TEXTGAP_RATING_CASEINSENSITIVE;
                break;
        }
    }

    /**
    * Returns the name of the additional question data table in the database
    *
    * @return string The additional table name
    * @access public
    */
    public function getAdditionalTableName(): string
    {
        return "qpl_qst_essay";
    }

    /**
    * Collects all text in the question which could contain media objects
    * which were created with the Rich Text Editor
    */
    public function getRTETextWithMediaObjects(): string
    {
        return parent::getRTETextWithMediaObjects();
    }

    /**
     * {@inheritdoc}
     */
    public function setExportDetailsXLSX(ilAssExcelFormatHelper $worksheet, int $startrow, int $col, int $active_id, int $pass): int
    {
        parent::setExportDetailsXLSX($worksheet, $startrow, $col, $active_id, $pass);

        $solutions = $this->getSolutionValues($active_id, $pass);

        $i = 1;
        $worksheet->setCell($startrow + $i, $col, $this->lng->txt("result"));
        $worksheet->setBold($worksheet->getColumnCoord($col) . ($startrow + $i));

        $assessment_folder = new ilObjTestFolder();

        $string_escaping_org_value = $worksheet->getStringEscaping();
        if ($assessment_folder->getExportEssayQuestionsWithHtml()) {
            $worksheet->setStringEscaping(false);
        }

        if (array_key_exists(0, $solutions) && strlen($solutions[0]["value1"])) {
            $worksheet->setCell($startrow + $i, $col + 2, html_entity_decode($solutions[0]["value1"]));
        }
        $i++;

        $worksheet->setStringEscaping($string_escaping_org_value);
        return $startrow + $i + 1;
    }

    /**
    * Returns a JSON representation of the question
    */
    public function toJSON(): string
    {
        $result = [];
        $result['id'] = $this->getId();
        $result['type'] = (string) $this->getQuestionType();
        $result['title'] = $this->getTitle();
        $result['question'] = $this->formatSAQuestion($this->getQuestion());
        $result['nr_of_tries'] = $this->getNrOfTries();
        $result['shuffle'] = $this->getShuffle();
        $result['maxlength'] = $this->getMaxNumOfChars();
        return json_encode($result);
    }

    public function getAnswerCount(): int
    {
        return count($this->answers);
    }

    /**
     * Adds a possible answer for a multiple choice question. A ASS_AnswerBinaryStateImage object will be
     * created and assigned to the array $this->answers.
     *
     * @param string $answertext The answer text
     * @param double $points The points for selecting the answer (even negative points can be used)
     * @param boolean $state Defines the answer as correct (TRUE) or incorrect (FALSE)
     * @param integer $order A possible display order of the answer
     * @param double $points The points for not selecting the answer (even negative points can be used)
     * @access public
     * @see $answers
     * @see ASS_AnswerBinaryStateImage
     */
    public function addAnswer(
        $answertext = "",
        $points = 0.0,
        $points_unchecked = 0.0,
        $order = 0,
        $answerimage = ""
    ): void {
        $answer = new ASS_AnswerMultipleResponseImage($answertext, $points);
        $this->answers[] = $answer;
    }

    /**
     *
     * @return array<ASS_AnswerMultipleResponseImage>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /**
     * Returns an answer with a given index. The index of the first
     * answer is 0, the index of the second answer is 1 and so on.
     *
     * @param integer $index A nonnegative index of the n-th answer
     * @return object ASS_AnswerBinaryStateImage-Object containing the answer
     * @access public
     * @see $answers
     */
    public function getAnswer($index = 0): ?object
    {
        if ($index < 0) {
            return null;
        }
        if (count($this->answers) < 1) {
            return null;
        }
        if ($index >= count($this->answers)) {
            return null;
        }

        return $this->answers[$index];
    }

    /**
     * Deletes an answer with a given index. The index of the first
     * answer is 0, the index of the second answer is 1 and so on.
     *
     * @param integer $index A nonnegative index of the n-th answer
     * @access public
     * @see $answers
     */
    public function deleteAnswer($index = 0): void
    {
        if ($index < 0) {
            return;
        }
        if (count($this->answers) < 1) {
            return;
        }
        if ($index >= count($this->answers)) {
            return;
        }
        $answer = $this->answers[$index];
        //if (strlen($answer->getImage())) {
        //    $this->deleteImage($answer->getImage());
        //}
        unset($this->answers[$index]);
        $this->answers = array_values($this->answers);
        for ($i = 0, $iMax = count($this->answers); $i < $iMax; $i++) {
            if ($this->answers[$i]->getOrder() > $index) {
                $this->answers[$i]->setOrder($i);
            }
        }
    }

    public function getAnswerTableName(): string
    {
        return 'qpl_a_essay';
    }

    /**
     * Deletes all answers
     *
     * @access public
     * @see $answers
     */
    public function flushAnswers(): void
    {
        $this->answers = [];
    }

    public function setAnswers($answers): void
    {
        if (isset($answers['answer'])) {
            $count = count($answers['answer']);
            $withPoints = true;
        } else {
            $count = count($answers);
            $withPoints = false;
        }

        $this->flushAnswers();

        for ($i = 0; $i < $count; $i++) {
            if ($withPoints) {
                $this->addAnswer($answers['answer'][$i], $answers['points'][$i]);
            } else {
                $this->addAnswer($answers[$i], 0);
            }
        }
    }

    public function duplicateAnswers($original_id): void
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        $result = $ilDB->queryF(
            "SELECT * FROM qpl_a_essay WHERE question_fi = %s",
            ['integer'],
            [$original_id]
        );
        if ($result->numRows()) {
            while ($row = $ilDB->fetchAssoc($result)) {
                $next_id = $ilDB->nextId('qpl_a_essay');
                $affectedRows = $ilDB->manipulateF(
                    "INSERT INTO qpl_a_essay (answer_id, question_fi, answertext, points)
					 VALUES (%s, %s, %s, %s)",
                    ['integer','integer','text','float'],
                    [$next_id, $this->getId(), $row["answertext"], $row["points"]]
                );
            }
        }
    }

    public function getKeywordRelation()
    {
        return $this->keyword_relation;
    }

    /**
     * This method implements a default behaviour. During the creation of a text question, the record which holds
     * the keyword relation is not existing, so keyword_relation defaults to 'one'.
     */
    public function setKeywordRelation(?string $relation): void
    {
        if ($relation !== null) {
            $this->keyword_relation = $relation;
        }
    }

    public static function getValidScoringModes(): array
    {
        return array_merge(self::getScoringModesWithPointsByQuestion(), self::getScoringModesWithPointsByKeyword());
    }

    public static function getScoringModesWithPointsByQuestion(): array
    {
        return [
            self::SCORING_MODE_KEYWORD_RELATION_NONE,
            self::SCORING_MODE_KEYWORD_RELATION_ALL,
            self::SCORING_MODE_KEYWORD_RELATION_ONE
        ];
    }

    public static function getScoringModesWithPointsByKeyword(): array
    {
        return [
            self::SCORING_MODE_KEYWORD_RELATION_ANY
        ];
    }

    /**
     * returns boolean wether it is possible to set
     * this question type as obligatory or not
     * considering the current question configuration
     *
     * (overwrites method in class assQuestion)
     *
     * @param integer $questionId
     * @return boolean $obligationPossible
     */
    public static function isObligationPossible(int $questionId): bool
    {
        return true;
    }

    public function countLetters($text): int
    {
        $text = strip_tags($text);

        $text = str_replace('&gt;', '>', $text);
        $text = str_replace('&lt;', '<', $text);
        $text = str_replace('&nbsp;', ' ', $text);
        $text = str_replace('&amp;', '&', $text);

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\n", "", $text);

        return ilStr::strLen($text);
    }

    public function countWords($text): int
    {
        $text = str_replace('&nbsp;', ' ', $text);

        $text = preg_replace('/[.,:;!?\-_#\'"+*\\/=()&%§$]/m', '', $text);

        $text = preg_replace('/^\s*/m', '', $text);
        $text = preg_replace('/\s*$/m', '', $text);
        $text = preg_replace('/\s+/m', ' ', $text);

        return count(explode(' ', $text));
    }

    public function getLatestAutosaveContent($active_id)
    {
        $question_fi = $this->getId();

        // Do we have an unauthorized result?
        $cntresult = $this->db->query(
            '
            SELECT count(solution_id) cnt
            FROM tst_solutions
            WHERE active_fi = ' . $this->db->quote($active_id, 'int') . '
            AND question_fi = ' . $this->db->quote($this->getId(), 'int') . '
            AND authorized = ' . $this->db->quote(0, 'int')
        );
        $row = $this->db->fetchAssoc($cntresult);
        if ($row['cnt'] > 0) {
            $tresult = $this->db->query(
                '
            SELECT value1
            FROM tst_solutions
            WHERE active_fi = ' . $this->db->quote($active_id, 'int') . '
            AND question_fi = ' . $this->db->quote($this->getId(), 'int') . '
            AND authorized = ' . $this->db->quote(0, 'int')
            );
            $trow = $this->db->fetchAssoc($tresult);
            return $trow['value1'];
        }
        return '';
    }

    public function toLog(): array
    {

        $result = [
            'question_id' => $this->getId(),
            'question_type' => (string) $this->getQuestionType(),
            'question_title' => $this->getTitle(),
            'tst_question' => $this->formatSAQuestion($this->getQuestion()),
            'points' => $this->getMaximumPoints(),
            'qst_essay_wordcounter_enabled' => $this->isWordCounterEnabled() ? '{{ enabled }}' : '{{ disabled }}',
            'maxchars' => $this->getMaxNumOfChars(),
            'essay_scoring_mode' => '{{ ' . $this->getScoringModeLangVar($this->getKeywordRelation()) . ' }}',
            'keywords' => array_map(
                fn(ASS_AnswerMultipleResponseImage $answer) => [
                    'answer' => $answer->getAnswertext(),
                    'points' => $answer->getPoints() === 0.0 ? '' : $answer->getPoints()
                ],
                $this->getAnswers()
            ),
            'tst_feedback' => [
                'feedback_incomplete_solution' => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), false)),
                'feedback_complete_solution' => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), true))
            ]
        ];
    }

    private function getScoringModeLangVar(string $scoring_mode): string
    {
        switch($scoring_mode) {
            case assTextQuestion::SCORING_MODE_KEYWORD_RELATION_NONE:
                return 'essay_scoring_mode_without_keywords';
            case assTextQuestion::SCORING_MODE_KEYWORD_RELATION_ANY:
                return 'essay_scoring_mode_keyword_relation_any';
            case assTextQuestion::SCORING_MODE_KEYWORD_RELATION_ALL:
                return 'essay_scoring_mode_keyword_relation_all';
            case assTextQuestion::SCORING_MODE_KEYWORD_RELATION_ONE:
                return 'essay_scoring_mode_keyword_relation_one';
            default:
                return '';
        }
    }
}
