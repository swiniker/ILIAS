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

/**
 * Matching question GUI representation
 *
 * The assMatchingQuestionGUI class encapsulates the GUI representation
 * for matching questions.
 *
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * @author		Maximilian Becker <mbecker@databay.de>
 * @version	$Id$
 *
 * @ingroup components\ILIASTestQuestionPool
 * @ilCtrl_Calls assMatchingQuestionGUI: ilFormPropertyDispatchGUI
 */
class assMatchingQuestionGUI extends assQuestionGUI implements ilGuiQuestionScoringAdjustable, ilGuiAnswerScoringAdjustable
{
    /**
     * assMatchingQuestionGUI constructor
     *
     * The constructor takes possible arguments an creates an instance of the assMatchingQuestionGUI object.
     *
     * @param integer $id The database id of a image map question object
     * @param integer $id The database id of a image map question object
     */
    public function __construct($id = -1)
    {
        parent::__construct();
        $this->object = new assMatchingQuestion();
        $this->setErrorMessage($this->lng->txt("msg_form_save_error"));
        if ($id >= 0) {
            $this->object->loadFromDb($id);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function writePostData(bool $always = false): int
    {
        $hasErrors = (!$always) ? $this->editQuestion(true) : false;
        if (!$hasErrors) {
            $this->writeQuestionGenericPostData();
            $this->writeQuestionSpecificPostData(new ilPropertyFormGUI());
            $this->writeAnswerSpecificPostData(new ilPropertyFormGUI());
            $this->saveTaxonomyAssignments();
            return 0;
        }
        return 1;
    }

    public function writeAnswerSpecificPostData(ilPropertyFormGUI $form): void
    {
        // Delete all existing answers and create new answers from the form data
        $this->object->flushMatchingPairs();
        $this->object->flushTerms();
        $this->object->flushDefinitions();

        $uploads = $this->request->getProcessedUploads();
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];

        if ($this->request->isset('terms')) {
            $answers = $this->request->raw('terms')['answer'] ?? [];
            $terms_image_names = $this->request->raw('terms')['imagename'] ?? [];
            $terms_identifiers = $this->request->raw('terms')['identifier'] ?? [];

            foreach ($answers as $index => $answer) {
                $filename = $terms_image_names[$index] ?? '';

                $upload_tmp_name = $this->request->getUploadFilename(['terms', 'image'], $index);

                if (isset($uploads[$upload_tmp_name]) && $uploads[$upload_tmp_name]->isOk() &&
                    in_array($uploads[$upload_tmp_name]->getMimeType(), $allowed_mime_types)) {
                    $filename = '';
                    $name = $uploads[$upload_tmp_name]->getName();
                    if ($this->object->setImageFile(
                        $uploads[$upload_tmp_name]->getPath(),
                        $this->object->getEncryptedFilename($name)
                    )) {
                        $filename = $this->object->getEncryptedFilename($name);
                    }
                }
                // @PHP8-CR: There seems to be a bigger issue lingering here and won't suppress / "quickfix" this but
                // postpone further analysis, eventually involving T&A TechSquad (see also remark in assMatchingQuestionGUI
                $this->object->addTerm(
                    new assAnswerMatchingTerm(
                        ilUtil::stripSlashes(htmlentities($answer)),
                        $filename,
                        $terms_identifiers[$index] ?? 0
                    )
                );
            }
        }

        if ($this->request->isset('definitions')) {
            $answers = $this->request->raw('definitions')['answer'] ?? [];
            $definitions_image_names = $this->request->raw('definitions')['imagename'] ?? [];
            $definitions_identifiers = $this->request->raw('definitions')['identifier'] ?? [];

            foreach ($answers as $index => $answer) {
                $filename = $definitions_image_names[$index] ?? '';

                $upload_tmp_name = $this->request->getUploadFilename(['definitions', 'image'], $index);

                if (isset($uploads[$upload_tmp_name]) && $uploads[$upload_tmp_name]->isOk() &&
                    in_array($uploads[$upload_tmp_name]->getMimeType(), $allowed_mime_types)) {
                    $filename = '';
                    $name = $uploads[$upload_tmp_name]->getName();
                    if ($this->object->setImageFile(
                        $uploads[$upload_tmp_name]->getPath(),
                        $this->object->getEncryptedFilename($name)
                    )) {
                        $filename = $this->object->getEncryptedFilename($name);
                    }
                }

                $this->object->addDefinition(
                    new assAnswerMatchingDefinition(
                        ilUtil::stripSlashes(htmlentities($answer)),
                        $filename,
                        $definitions_identifiers[$index] ?? 0
                    )
                );
            }
        }

        if ($this->request->isset('pairs')) {
            $points_of_pairs = $this->request->raw('pairs')['points'] ?? [];
            $pair_terms = $this->request->raw('pairs')['term'] ?? [];
            $pair_definitions = $this->request->raw('pairs')['definition'] ?? [];

            foreach ($points_of_pairs as $index => $points) {
                $term_id = $pair_terms[$index] ?? 0;
                $definition_id = $pair_definitions[$index] ?? 0;
                $this->object->addMatchingPair(
                    $this->object->getTermWithIdentifier($term_id),
                    $this->object->getDefinitionWithIdentifier($definition_id),
                    (float) str_replace(',', '.', $points)
                );
            }
        }
    }

    public function writeQuestionSpecificPostData(ilPropertyFormGUI $form): void
    {
        if (!$this->object->getSelfAssessmentEditingMode()) {
            $this->object->setShuffle($_POST["shuffle"] ?? '0');
            $this->object->setShuffleMode($_POST["shuffle"] ?? '0');
        } else {
            $this->object->setShuffle(1);
            $this->object->setShuffleMode(1);
        }
        $this->object->setThumbGeometry($_POST["thumb_geometry"] ?? 0);
        $this->object->setMatchingMode($_POST['matching_mode']);
    }

    public function uploadterms(): void
    {
        $this->writePostData(true);
        $this->editQuestion();
    }

    public function removeimageterms(): void
    {
        $this->writePostData(true);
        $position = key($_POST['cmd']['removeimageterms']);
        $this->object->removeTermImage($position);
        $this->editQuestion();
    }

    public function uploaddefinitions(): void
    {
        $this->writePostData(true);
        $this->editQuestion();
    }

    public function removeimagedefinitions(): void
    {
        $this->writePostData(true);
        $position = key($_POST['cmd']['removeimagedefinitions']);
        $this->object->removeDefinitionImage($position);
        $this->editQuestion();
    }

    public function addterms(): void
    {
        $this->writePostData(true);
        $position = key($_POST["cmd"]["addterms"]);
        $this->object->insertTerm($position + 1);
        $this->editQuestion();
    }

    public function removeterms(): void
    {
        $this->writePostData(true);
        $position = key($_POST["cmd"]["removeterms"]);
        $this->object->deleteTerm($position);
        $this->editQuestion();
    }

    public function adddefinitions(): void
    {
        $this->writePostData(true);
        $position = key($_POST["cmd"]["adddefinitions"]);
        $this->object->insertDefinition($position + 1);
        $this->editQuestion();
    }

    public function removedefinitions(): void
    {
        $this->writePostData(true);
        $position = key($_POST["cmd"]["removedefinitions"]);
        $this->object->deleteDefinition($position);
        $this->editQuestion();
    }

    public function addpairs(): void
    {
        $this->writePostData(true);
        $position = key($_POST["cmd"]["addpairs"]);
        $this->object->insertMatchingPair($position + 1);
        $this->editQuestion();
    }

    public function removepairs(): void
    {
        $this->writePostData(true);
        $position = key($_POST["cmd"]["removepairs"]);
        $this->object->deleteMatchingPair($position);
        $this->editQuestion();
    }

    public function editQuestion(bool $checkonly = false): bool
    {
        $save = $this->isSaveCommand();
        $this->getQuestionTemplate();

        $form = new ilPropertyFormGUI();
        $this->editForm = $form;

        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->outQuestionType());
        $form->setMultipart(true);
        $form->setTableWidth("100%");
        $form->setId("matching");

        $this->addBasicQuestionFormProperties($form);
        $this->populateQuestionSpecificFormPart($form);
        $this->populateAnswerSpecificFormPart($form);
        $this->populateTaxonomyFormSection($form);
        $this->addQuestionFormCommandButtons($form);

        $errors = false;
        if ($save) {
            $form->setValuesByPost();
            $errors = !$form->checkInput();
            $form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes
            if (!$errors && !$this->isValidTermAndDefinitionAmount($form) && !$this->object->getSelfAssessmentEditingMode()) {
                $errors = true;
                $terms = $form->getItemByPostVar('terms');
                $terms->setAlert($this->lng->txt("msg_number_of_terms_too_low"));
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('form_input_not_valid'));
            }
            if ($errors) {
                $checkonly = false;
            }
        }

        if (!$checkonly) {
            $this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
        }
        return $errors;
    }

    private function isDefImgUploadCommand(): bool
    {
        return $this->ctrl->getCmd() == 'uploaddefinitions';
    }

    private function isTermImgUploadCommand(): bool
    {
        return $this->ctrl->getCmd() == 'uploadterms';
    }

    /**
     * for mode 1:1 terms count must not be less than definitions count
     * for mode n:n this limitation is cancelled
     *
     * @param ilPropertyFormGUI $form
     * @return bool
     */
    private function isValidTermAndDefinitionAmount(ilPropertyFormGUI $form): bool
    {
        $matchingMode = $form->getItemByPostVar('matching_mode')->getValue();

        if ($matchingMode == assMatchingQuestion::MATCHING_MODE_N_ON_N) {
            return true;
        }

        $numTerms = count($form->getItemByPostVar('terms')->getValues());
        $numDefinitions = count($form->getItemByPostVar('definitions')->getValues());

        if ($numTerms >= $numDefinitions) {
            return true;
        }

        return false;
    }

    public function populateAnswerSpecificFormPart(\ilPropertyFormGUI $form): ilPropertyFormGUI
    {
        $definitions = new ilMatchingWizardInputGUI($this->lng->txt("definitions"), "definitions");
        if ($this->object->getSelfAssessmentEditingMode()) {
            $definitions->setHideImages(true);
        }

        $stripHtmlEntitesFromValues = function (assAnswerMatchingTerm $value) {
            return $value->withText(html_entity_decode($value->getText()));
        };

        $definitions->setRequired(true);
        $definitions->setQuestionObject($this->object);
        $definitions->setTextName($this->lng->txt('definition_text'));
        $definitions->setImageName($this->lng->txt('definition_image'));
        if (!count($this->object->getDefinitions())) {
            $this->object->addDefinition(new assAnswerMatchingDefinition());
        }
        $definitionvalues = array_map($stripHtmlEntitesFromValues, $this->object->getDefinitions());
        $definitions->setValues($definitionvalues);
        if ($this->isDefImgUploadCommand()) {
            $definitions->checkInput();
        }
        $form->addItem($definitions);

        $terms = new ilMatchingWizardInputGUI($this->lng->txt("terms"), "terms");
        if ($this->object->getSelfAssessmentEditingMode()) {
            $terms->setHideImages(true);
        }
        $terms->setRequired(true);
        $terms->setQuestionObject($this->object);
        $terms->setTextName($this->lng->txt('term_text'));
        $terms->setImageName($this->lng->txt('term_image'));

        if (0 === count($this->object->getTerms())) {
            // @PHP8-CR: If you look above, how $this->object->addDefinition does in fact take an object, I take this
            // issue as an indicator for a bigger issue and won't suppress / "quickfix" this but postpone further
            // analysis, eventually involving T&A TechSquad
            $this->object->addTerm(new assAnswerMatchingTerm());
        }
        $termvalues = array_map($stripHtmlEntitesFromValues, $this->object->getTerms());
        $terms->setValues($termvalues);
        if ($this->isTermImgUploadCommand()) {
            $terms->checkInput();
        }
        $form->addItem($terms);

        $pairs = new ilMatchingPairWizardInputGUI($this->lng->txt('matching_pairs'), 'pairs');
        $pairs->setRequired(true);
        $pairs->setTerms($this->object->getTerms());
        $pairs->setDefinitions($this->object->getDefinitions());
        if (count($this->object->getMatchingPairs()) == 0) {
            $this->object->addMatchingPair($termvalues[0], $definitionvalues[0], 0);
            //$this->object->addMatchingPair(new assAnswerMatchingPair($termvalues[0], $definitionvalues[0], 0));
        }
        $pairs->setPairs($this->object->getMatchingPairs());
        $form->addItem($pairs);

        return $form;
    }

    public function populateQuestionSpecificFormPart(\ilPropertyFormGUI $form): ilPropertyFormGUI
    {
        // Edit mode
        $hidden = new ilHiddenInputGUI("matching_type");
        $hidden->setValue('');
        $form->addItem($hidden);

        if (!$this->object->getSelfAssessmentEditingMode()) {
            // shuffle
            $shuffle = new ilSelectInputGUI($this->lng->txt("shuffle_answers"), "shuffle");
            $shuffle_options = array(
                0 => $this->lng->txt("no"),
                1 => $this->lng->txt("matching_shuffle_terms_definitions"),
                2 => $this->lng->txt("matching_shuffle_terms"),
                3 => $this->lng->txt("matching_shuffle_definitions")
            );
            $shuffle->setOptions($shuffle_options);
            $shuffle->setValue($this->object->getShuffleMode());
            $shuffle->setRequired(false);
            $form->addItem($shuffle);

            $geometry = new ilNumberInputGUI($this->lng->txt('thumb_size'), 'thumb_geometry');
            $geometry->setValue($this->object->getThumbGeometry());
            $geometry->setRequired(true);
            $geometry->setMaxLength(6);
            $geometry->setMinValue(20);
            $geometry->setSize(6);
            $geometry->setInfo($this->lng->txt('thumb_size_info'));
            $form->addItem($geometry);
        }

        // Matching Mode
        $mode = new ilRadioGroupInputGUI($this->lng->txt('qpl_qst_inp_matching_mode'), 'matching_mode');
        $mode->setRequired(true);

        $modeONEonONE = new ilRadioOption(
            $this->lng->txt('qpl_qst_inp_matching_mode_one_on_one'),
            assMatchingQuestion::MATCHING_MODE_1_ON_1
        );
        $mode->addOption($modeONEonONE);

        $modeALLonALL = new ilRadioOption(
            $this->lng->txt('qpl_qst_inp_matching_mode_all_on_all'),
            assMatchingQuestion::MATCHING_MODE_N_ON_N
        );
        $mode->addOption($modeALLonALL);

        $mode->setValue($this->object->getMatchingMode());

        $form->addItem($mode);
        return $form;
    }

    /**
    * Get the question solution output
    * @param integer $active_id             The active user id
    * @param integer $pass                  The test pass
    * @param boolean $graphicalOutput       Show visual feedback for right/wrong answers
    * @param boolean $result_output         Show the reached points for parts of the question
    * @param boolean $show_question_only    Show the question without the ILIAS content around
    * @param boolean $show_feedback         Show the question feedback
    * @param boolean $show_correct_solution Show the correct solution instead of the user solution
    * @param boolean $show_manual_scoring   Show specific information for the manual scoring output
    * @return string The solution output of the question as HTML code
    */
    public function getSolutionOutput(
        $active_id,
        $pass = null,
        $graphicalOutput = false,
        $result_output = false,
        $show_question_only = true,
        $show_feedback = false,
        $show_correct_solution = false,
        $show_manual_scoring = false,
        $show_question_text = true
    ): string {
        $template = new ilTemplate("tpl.il_as_qpl_matching_output_solution.html", true, true, "components/ILIAS/TestQuestionPool");
        $solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html", true, true, "components/ILIAS/TestQuestionPool");

        $solutions = array();
        if (($active_id > 0) && (!$show_correct_solution)) {
            $solutions = $this->object->getSolutionValues($active_id, $pass);
        } else {
            foreach ($this->object->getMaximumScoringMatchingPairs() as $pair) {
                $solutions[] = array(
                    "value1" => $pair->getTerm()->getIdentifier(),
                    "value2" => $pair->getDefinition()->getIdentifier(),
                    'points' => $pair->getPoints()
                );
            }
        }

        $i = 0;

        foreach ($solutions as $solution) {
            $definition = $this->object->getDefinitionWithIdentifier($solution['value2']);
            $term = $this->object->getTermWithIdentifier($solution['value1']);
            $points = $solution['points'];

            if (is_object($definition)) {
                if (strlen($definition->getPicture())) {
                    if (strlen($definition->getText())) {
                        $template->setCurrentBlock('definition_image_text');
                        $template->setVariable(
                            "TEXT_DEFINITION",
                            ilLegacyFormElementsUtil::prepareFormOutput($definition->getText())
                        );
                        $template->parseCurrentBlock();
                    }

                    $answerImageSrc = ilWACSignedPath::signFile(
                        $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $definition->getPicture()
                    );

                    $template->setCurrentBlock('definition_image');
                    $template->setVariable('ANSWER_IMAGE_URL', $answerImageSrc);
                    $template->setVariable(
                        'ANSWER_IMAGE_ALT',
                        (strlen($definition->getText())) ? ilLegacyFormElementsUtil::prepareFormOutput(
                            $definition->getText()
                        ) : ilLegacyFormElementsUtil::prepareFormOutput($definition->getPicture())
                    );
                    $template->setVariable(
                        'ANSWER_IMAGE_TITLE',
                        (strlen($definition->getText())) ? ilLegacyFormElementsUtil::prepareFormOutput(
                            $definition->getText()
                        ) : ilLegacyFormElementsUtil::prepareFormOutput($definition->getPicture())
                    );
                    $template->setVariable('URL_PREVIEW', $this->object->getImagePathWeb() . $definition->getPicture());
                    $template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
                    $template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('media/enlarge.svg'));
                    $template->parseCurrentBlock();
                } else {
                    $template->setCurrentBlock('definition_text');
                    $template->setVariable("DEFINITION", ilLegacyFormElementsUtil::prepareTextareaOutput($definition->getText(), true));
                    $template->parseCurrentBlock();
                }
            }
            if (is_object($term)) {
                if (strlen($term->getPicture())) {
                    if (strlen($term->getText())) {
                        $template->setCurrentBlock('term_image_text');
                        $template->setVariable("TEXT_TERM", ilLegacyFormElementsUtil::prepareFormOutput($term->getText()));
                        $template->parseCurrentBlock();
                    }

                    $answerImageSrc = ilWACSignedPath::signFile(
                        $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $term->getPicture()
                    );

                    $template->setCurrentBlock('term_image');
                    $template->setVariable('ANSWER_IMAGE_URL', $answerImageSrc);
                    $template->setVariable(
                        'ANSWER_IMAGE_ALT',
                        (strlen($term->getText())) ? ilLegacyFormElementsUtil::prepareFormOutput(
                            $term->getText()
                        ) : ilLegacyFormElementsUtil::prepareFormOutput($term->getPicture())
                    );
                    $template->setVariable(
                        'ANSWER_IMAGE_TITLE',
                        (strlen($term->getText())) ? ilLegacyFormElementsUtil::prepareFormOutput(
                            $term->getText()
                        ) : ilLegacyFormElementsUtil::prepareFormOutput($term->getPicture())
                    );
                    $template->setVariable('URL_PREVIEW', $this->object->getImagePathWeb() . $term->getPicture());
                    $template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
                    $template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('media/enlarge.svg'));
                    $template->parseCurrentBlock();
                } else {
                    $template->setCurrentBlock('term_text');
                    $template->setVariable("TERM", ilLegacyFormElementsUtil::prepareTextareaOutput($term->getText(), true));
                    $template->parseCurrentBlock();
                }
                $i++;
            }
            if (($active_id > 0) && (!$show_correct_solution)) {
                if ($graphicalOutput) {
                    // output of ok/not ok icons for user entered solutions
                    $ok = false;
                    foreach ($this->object->getMatchingPairs() as $pair) {
                        if ($this->isCorrectMatching($pair, $definition, $term)) {
                            $ok = true;
                        }
                    }

                    $correctness_icon = $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_NOT_OK);
                    if ($ok) {
                        $correctness_icon = $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_OK);
                    }
                    $template->setCurrentBlock("icon_ok");
                    $template->setVariable("ICON_OK", $correctness_icon);
                    $template->parseCurrentBlock();
                }
            }

            if ($result_output) {
                $resulttext = ($points == 1) ? "(%s " . $this->lng->txt("point") . ")" : "(%s " . $this->lng->txt("points") . ")";
                $template->setCurrentBlock("result_output");
                $template->setVariable("RESULT_OUTPUT", sprintf($resulttext, $points));
                $template->parseCurrentBlock();
            }

            $template->setCurrentBlock("row");
            $template->setVariable("TEXT_MATCHES", $this->lng->txt("matches"));
            $template->parseCurrentBlock();
        }

        if ($show_question_text == true) {
            $template->setVariable("QUESTIONTEXT", $this->object->getQuestionForHTMLOutput());
        }

        $questionoutput = $template->get();

        $feedback = '';
        if ($show_feedback) {
            if (!$this->isTestPresentationContext()) {
                $fb = $this->getGenericFeedbackOutput((int) $active_id, $pass);
                $feedback .= strlen($fb) ? $fb : '';
            }

            $fb = $this->getSpecificFeedbackOutput(array());
            $feedback .= strlen($fb) ? $fb : '';
        }
        if (strlen($feedback)) {
            $cssClass = (
                $this->hasCorrectSolution($active_id, $pass) ?
                ilAssQuestionFeedback::CSS_CLASS_FEEDBACK_CORRECT : ilAssQuestionFeedback::CSS_CLASS_FEEDBACK_WRONG
            );

            $solutiontemplate->setVariable("ILC_FB_CSS_CLASS", $cssClass);
            $solutiontemplate->setVariable("FEEDBACK", ilLegacyFormElementsUtil::prepareTextareaOutput($feedback, true));
        }

        $solutiontemplate->setVariable("SOLUTION_OUTPUT", $questionoutput);

        $solutionoutput = $solutiontemplate->get();
        if (!$show_question_only) {
            // get page object output
            $solutionoutput = $this->getILIASPage($solutionoutput);
        }
        return $solutionoutput;
    }

    public function getPreview($show_question_only = false, $showInlineFeedback = false): string
    {
        $solutions = is_object($this->getPreviewSession()) ? (array) $this->getPreviewSession()->getParticipantsSolution() : array();

        global $DIC; /* @var ILIAS\DI\Container $DIC */
        if ($DIC->http()->agent()->isMobile() || $DIC->http()->agent()->isIpad()) {
            iljQueryUtil::initjQuery();
            iljQueryUtil::initjQueryUI();
            $this->tpl->addJavaScript('assets/js/jquery.ui.touch-punch.js');
        }
        $this->tpl->addJavaScript('assets/js/ilMatchingQuestion.js');
        $this->tpl->addOnLoadCode('ilMatchingQuestionInit();');
        $this->tpl->addCss(ilUtil::getStyleSheetLocation('output', 'test_javascript.css'));

        $template = new ilTemplate("tpl.il_as_qpl_matching_output.html", true, true, "components/ILIAS/TestQuestionPool");

        foreach ($solutions as $defId => $terms) {
            foreach ($terms as $termId) {
                $template->setCurrentBlock("matching_data");
                $template->setVariable("DEFINITION_ID", $defId);
                $template->setVariable("TERM_ID", $termId);
                $template->parseCurrentBlock();
            }
        }

        // shuffle output
        $terms = $this->object->getTerms();
        $definitions = $this->object->getDefinitions();
        switch ($this->object->getShuffleMode()) {
            case 1:
                $terms = $this->object->getShuffler()->transform($terms);
                $definitions = $this->object->getShuffler()->transform(
                    $this->object->getShuffler()->transform($definitions)
                );
                break;
            case 2:
                $terms = $this->object->getShuffler()->transform($terms);
                break;
            case 3:
                $definitions = $this->object->getShuffler()->transform($definitions);
                break;
        }

        // create definitions
        $counter = 0;
        foreach ($definitions as $definition) {
            if (strlen($definition->getPicture())) {
                $template->setCurrentBlock("definition_picture");
                $template->setVariable("DEFINITION_ID", $definition->getIdentifier());
                $template->setVariable("IMAGE_HREF", $this->object->getImagePathWeb() . $definition->getPicture());
                $thumbweb = $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $definition->getPicture();
                $thumb = $this->object->getImagePath() . $this->object->getThumbPrefix() . $definition->getPicture();
                if (!@file_exists($thumb)) {
                    $this->object->rebuildThumbnails();
                }
                $template->setVariable("THUMBNAIL_HREF", $thumbweb);
                $template->setVariable("THUMB_ALT", $this->lng->txt("image"));
                $template->setVariable("THUMB_TITLE", $this->lng->txt("image"));
                $template->setVariable("TEXT_DEFINITION", (strlen($definition->getText())) ? ilLegacyFormElementsUtil::prepareTextareaOutput($definition->getText(), true, true) : '');
                $template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
                $template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('media/enlarge.svg'));
                $template->parseCurrentBlock();
            } else {
                $template->setCurrentBlock("definition_text");
                $template->setVariable("DEFINITION", ilLegacyFormElementsUtil::prepareTextareaOutput($definition->getText(), true, true));
                $template->parseCurrentBlock();
            }

            $template->setCurrentBlock("droparea");
            $template->setVariable("ID_DROPAREA", $definition->getIdentifier());
            $template->setVariable("QUESTION_ID", $this->object->getId());
            $template->parseCurrentBlock();

            $template->setCurrentBlock("definition_data");
            $template->setVariable("DEFINITION_ID", $definition->getIdentifier());
            $template->parseCurrentBlock();
        }

        // create terms
        $counter = 0;
        foreach ($terms as $term) {
            if (strlen($term->getPicture())) {
                $template->setCurrentBlock("term_picture");
                $template->setVariable("TERM_ID", $term->getIdentifier());
                $template->setVariable("IMAGE_HREF", $this->object->getImagePathWeb() . $term->getPicture());
                $thumbweb = $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $term->getPicture();
                $thumb = $this->object->getImagePath() . $this->object->getThumbPrefix() . $term->getPicture();
                if (!@file_exists($thumb)) {
                    $this->object->rebuildThumbnails();
                }
                $template->setVariable("THUMBNAIL_HREF", $thumbweb);
                $template->setVariable("THUMB_ALT", $this->lng->txt("image"));
                $template->setVariable("THUMB_TITLE", $this->lng->txt("image"));
                $template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
                $template->setVariable("TEXT_TERM", (strlen($term->getText())) ? ilLegacyFormElementsUtil::prepareTextareaOutput($term->getText(), true, true) : '');
                $template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('media/enlarge.svg'));
                $template->parseCurrentBlock();
            } else {
                $template->setCurrentBlock("term_text");
                $template->setVariable("TERM_TEXT", ilLegacyFormElementsUtil::prepareTextareaOutput($term->getText(), true, true));
                $template->parseCurrentBlock();
            }
            $template->setCurrentBlock("draggable");
            $template->setVariable("ID_DRAGGABLE", $term->getIdentifier());
            $template->parseCurrentBlock();

            $template->setCurrentBlock("term_data");
            $template->setVariable("TERM_ID", $term->getIdentifier());
            $template->parseCurrentBlock();
        }

        $template->setVariable('MATCHING_MODE', $this->object->getMatchingMode());

        $template->setVariable("RESET_BUTTON", $this->lng->txt("reset_terms"));

        $template->setVariable("QUESTIONTEXT", $this->object->getQuestionForHTMLOutput());

        $questionoutput = $template->get();

        if (!$show_question_only) {
            // get page object output
            $questionoutput = $this->getILIASPage($questionoutput);
        }

        return $questionoutput;
    }

    /**
     * @param array $solution
     * @param assAnswerMatchingDefinition[] $definitions
     * @return array
     */
    protected function sortDefinitionsBySolution(array $solution, array $definitions): array
    {
        $neworder = array();
        $handled_defintions = array();
        foreach ($solution as $solution_values) {
            $id = $solution_values['value2'];
            if (!isset($handled_defintions[$id])) {
                $neworder[] = $this->object->getDefinitionWithIdentifier($id);
                $handled_defintions[$id] = $id;
            }
        }

        foreach ($definitions as $definition) {
            /**
             * @var $definition assAnswerMatchingDefinition
             */
            if (!isset($handled_defintions[$definition->getIdentifier()])) {
                $neworder[] = $definition;
            }
        }

        return $neworder;
    }

    public function getPresentationJavascripts(): array
    {
        global $DIC; /* @var ILIAS\DI\Container $DIC */

        $files = array();

        if ($DIC->http()->agent()->isMobile() || $DIC->http()->agent()->isIpad()) {
            $files[] = './node_modules/@andxor/jquery-ui-touch-punch-fix/jquery.ui.touch-punch.js';
        }

        $files[] = 'components/ILIAS/TestQuestionPool/js/ilMatchingQuestion.js';

        return $files;
    }

    // hey: prevPassSolutions - pass will be always available from now on
    public function getTestOutput($active_id, $pass, $is_postponed = false, $user_post_solution = false, $inlineFeedback = false): string
    // hey.
    {
        global $DIC; /* @var ILIAS\DI\Container $DIC */
        if ($DIC->http()->agent()->isMobile() || $DIC->http()->agent()->isIpad()) {
            iljQueryUtil::initjQuery();
            iljQueryUtil::initjQueryUI();
            $this->tpl->addJavaScript('assets/js/jquery.ui.touch-punch.js');
        }
        $this->tpl->addJavaScript('assets/js/ilMatchingQuestion.js');
        $this->tpl->addOnLoadCode('ilMatchingQuestionInit();');
        $this->tpl->addCss(ilUtil::getStyleSheetLocation('output', 'test_javascript.css'));

        $template = new ilTemplate("tpl.il_as_qpl_matching_output.html", true, true, "components/ILIAS/TestQuestionPool");

        $solutions = array();
        if ($active_id) {
            if (is_array($user_post_solution)) {
                foreach ($user_post_solution['matching'][$this->object->getId()] as $definition => $term) {
                    array_push($solutions, array("value1" => $term, "value2" => $definition));
                }
            } else {
                // hey: prevPassSolutions - obsolete due to central check
                $solutions = $this->object->getTestOutputSolutions($active_id, $pass);
                // hey.
            }

            $counter = 0;
            foreach ($solutions as $idx => $solution_value) {
                if (($solution_value["value2"] > -1) && ($solution_value["value1"] > -1)) {
                    $template->setCurrentBlock("matching_data");
                    $template->setVariable("TERM_ID", $solution_value["value1"]);
                    $template->setVariable("DEFINITION_ID", $solution_value["value2"]);
                    $template->parseCurrentBlock();
                }

                $counter++;
            }
        }

        $terms = $this->object->getTerms();
        $definitions = $this->object->getDefinitions();
        switch ($this->object->getShuffleMode()) {
            case 1:
                $terms = $this->object->getShuffler()->transform($terms);
                if (count($solutions)) {
                    $definitions = $this->sortDefinitionsBySolution($solutions, $definitions);
                } else {
                    $definitions = $this->object->getShuffler()->transform(
                        $this->object->getShuffler()->transform($definitions)
                    );
                }
                break;
            case 2:
                $terms = $this->object->getShuffler()->transform($terms);
                break;
            case 3:
                if (count($solutions)) {
                    $definitions = $this->sortDefinitionsBySolution($solutions, $definitions);
                } else {
                    $definitions = $this->object->getShuffler()->transform($definitions);
                }
                break;
        }

        // create definitions
        $counter = 0;
        foreach ($definitions as $definition) {
            if (strlen($definition->getPicture())) {
                $template->setCurrentBlock("definition_picture");
                $template->setVariable("DEFINITION_ID", $definition->getIdentifier());
                $template->setVariable("IMAGE_HREF", $this->object->getImagePathWeb() . $definition->getPicture());
                $thumbweb = $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $definition->getPicture();
                $thumb = $this->object->getImagePath() . $this->object->getThumbPrefix() . $definition->getPicture();
                if (!@file_exists($thumb)) {
                    $this->object->rebuildThumbnails();
                }
                $template->setVariable("THUMBNAIL_HREF", $thumbweb);
                $template->setVariable("THUMB_ALT", $this->lng->txt("image"));
                $template->setVariable("THUMB_TITLE", $this->lng->txt("image"));
                $template->setVariable("TEXT_DEFINITION", (strlen($definition->getText())) ? ilLegacyFormElementsUtil::prepareTextareaOutput($definition->getText(), true, true) : '');
                $template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
                $template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('media/enlarge.svg'));
                $template->parseCurrentBlock();
            } else {
                $template->setCurrentBlock("definition_text");
                $template->setVariable("DEFINITION", ilLegacyFormElementsUtil::prepareTextareaOutput($definition->getText(), true, true));
                $template->parseCurrentBlock();
            }

            $template->setCurrentBlock("droparea");
            $template->setVariable("ID_DROPAREA", $definition->getIdentifier());
            $template->setVariable("QUESTION_ID", $this->object->getId());
            $template->parseCurrentBlock();

            $template->setCurrentBlock("definition_data");
            $template->setVariable("DEFINITION_ID", $definition->getIdentifier());
            $template->parseCurrentBlock();
        }

        // create terms
        $counter = 0;
        foreach ($terms as $term) {
            if (strlen($term->getPicture())) {
                $template->setCurrentBlock("term_picture");
                $template->setVariable("TERM_ID", $term->getIdentifier());
                $template->setVariable("IMAGE_HREF", $this->object->getImagePathWeb() . $term->getPicture());
                $thumbweb = $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $term->getPicture();
                $thumb = $this->object->getImagePath() . $this->object->getThumbPrefix() . $term->getPicture();
                if (!@file_exists($thumb)) {
                    $this->object->rebuildThumbnails();
                }
                $template->setVariable("THUMBNAIL_HREF", $thumbweb);
                $template->setVariable("THUMB_ALT", $this->lng->txt("image"));
                $template->setVariable("THUMB_TITLE", $this->lng->txt("image"));
                $template->setVariable("TEXT_PREVIEW", $this->lng->txt('preview'));
                $template->setVariable("TEXT_TERM", (strlen($term->getText())) ? ilLegacyFormElementsUtil::prepareTextareaOutput($term->getText(), true, true) : '');
                $template->setVariable("IMG_PREVIEW", ilUtil::getImagePath('media/enlarge.svg'));
                $template->parseCurrentBlock();
            } else {
                $template->setCurrentBlock("term_text");
                $template->setVariable("TERM_TEXT", ilLegacyFormElementsUtil::prepareTextareaOutput($term->getText(), true, true));
                $template->parseCurrentBlock();
            }
            $template->setCurrentBlock("draggable");
            $template->setVariable("ID_DRAGGABLE", $term->getIdentifier());
            $template->parseCurrentBlock();

            $template->setCurrentBlock('term_data');
            $template->setVariable('TERM_ID', $term->getIdentifier());
            $template->parseCurrentBlock();
        }

        $template->setVariable('MATCHING_MODE', $this->object->getMatchingMode());

        $template->setVariable("RESET_BUTTON", $this->lng->txt("reset_terms"));

        $template->setVariable("QUESTIONTEXT", $this->object->getQuestionForHTMLOutput());

        return $this->outQuestionPage("", $is_postponed, $active_id, $template->get());
    }

    /**
    * check input fields
    */
    public function checkInput(): bool
    {
        if ((!$_POST["title"]) or (!$_POST["author"]) or (!$_POST["question"])) {
            return false;
        }
        return true;
    }

    public function getSpecificFeedbackOutput(array $userSolution): string
    {
        $matches = array_values($this->object->matchingpairs);

        if (!$this->object->feedbackOBJ->specificAnswerFeedbackExists()) {
            return '';
        }

        $feedback = '<table class="test_specific_feedback"><tbody>';

        foreach ($matches as $idx => $ans) {
            if (!isset($userSolution[$ans->getDefinition()->getIdentifier()])) {
                continue;
            }

            if (!is_array($userSolution[$ans->getDefinition()->getIdentifier()])) {
                continue;
            }

            if (!in_array($ans->getTerm()->getIdentifier(), $userSolution[$ans->getDefinition()->getIdentifier()])) {
                continue;
            }

            $fb = $this->object->feedbackOBJ->getSpecificAnswerFeedbackTestPresentation(
                $this->object->getId(),
                0,
                $idx
            );
            $feedback .= '<tr><td>"' . $ans->getDefinition()->getText() . '"&nbsp;' . $this->lng->txt("matches") . '&nbsp;"';
            $feedback .= $ans->getTerm()->getText() . '"</td><td>';
            $feedback .= $fb . '</td> </tr>';
        }

        $feedback .= '</tbody></table>';
        return ilLegacyFormElementsUtil::prepareTextareaOutput($feedback, true);
    }

    /**
     * Returns a list of postvars which will be suppressed in the form output when used in scoring adjustment.
     * The form elements will be shown disabled, so the users see the usual form but can only edit the settings, which
     * make sense in the given context.
     *
     * E.g. array('cloze_type', 'image_filename')
     *
     * @return string[]
     */
    public function getAfterParticipationSuppressionAnswerPostVars(): array
    {
        return array();
    }

    /**
     * Returns a list of postvars which will be suppressed in the form output when used in scoring adjustment.
     * The form elements will be shown disabled, so the users see the usual form but can only edit the settings, which
     * make sense in the given context.
     *
     * E.g. array('cloze_type', 'image_filename')
     *
     * @return string[]
     */
    public function getAfterParticipationSuppressionQuestionPostVars(): array
    {
        return array();
    }

    /**
     * Returns an html string containing a question specific representation of the answers so far
     * given in the test for use in the right column in the scoring adjustment user interface.
     * @param array $relevant_answers
     * @return string
     */
    public function getAggregatedAnswersView(array $relevant_answers): string
    {
        return ''; //print_r($relevant_answers,true);
    }

    private function isCorrectMatching($pair, $definition, $term): bool
    {
        if (!($pair->getPoints() > 0)) {
            return false;
        }

        if (!is_object($term)) {
            return false;
        }

        if ($pair->getDefinition()->getIdentifier() != $definition->getIdentifier()) {
            return false;
        }

        if ($pair->getTerm()->getIdentifier() != $term->getIdentifier()) {
            return false;
        }

        return true;
    }

    protected function getAnswerStatisticImageHtml($picture): string
    {
        $thumbweb = $this->object->getImagePathWeb() . $this->object->getThumbPrefix() . $picture;
        return '<img src="' . $thumbweb . '" alt="' . $picture . '" title="' . $picture . '"/>';
    }

    protected function getAnswerStatisticMatchingElemHtml($elem): string
    {
        $html = '';

        if (strlen($elem->getText())) {
            $html .= $elem->getText();
        }

        if (strlen($elem->getPicture())) {
            $html .= $this->getAnswerStatisticImageHtml($elem->getPicture());
        }

        return $html;
    }

    public function getAnswersFrequency($relevantAnswers, $questionIndex): array
    {
        $answersByActiveAndPass = array();

        foreach ($relevantAnswers as $row) {
            $key = $row['active_fi'] . ':' . $row['pass'];

            if (!isset($answersByActiveAndPass[$key])) {
                $answersByActiveAndPass[$key] = array();
            }

            $answersByActiveAndPass[$key][$row['value1']] = $row['value2'];
        }

        $answers = array();

        foreach ($answersByActiveAndPass as $key => $matchingPairs) {
            foreach ($matchingPairs as $termId => $defId) {
                $hash = md5($termId . ':' . $defId);

                if (!isset($answers[$hash])) {
                    $termHtml = $this->getAnswerStatisticMatchingElemHtml(
                        $this->object->getTermWithIdentifier($termId)
                    );

                    $defHtml = $this->getAnswerStatisticMatchingElemHtml(
                        $this->object->getDefinitionWithIdentifier($defId)
                    );

                    $answers[$hash] = array(
                        'answer' => $termHtml . $defHtml,
                        'term' => $termHtml,
                        'definition' => $defHtml,
                        'frequency' => 0
                    );
                }

                $answers[$hash]['frequency']++;
            }
        }

        return $answers;
    }

    /**
     * @param $parentGui
     * @param $parentCmd
     * @param $relevantAnswers
     * @param $questionIndex
     * @return ilMatchingQuestionAnswerFreqStatTableGUI
     */
    public function getAnswerFrequencyTableGUI($parentGui, $parentCmd, $relevantAnswers, $questionIndex): ilAnswerFrequencyStatisticTableGUI
    {
        $table = new ilMatchingQuestionAnswerFreqStatTableGUI($parentGui, $parentCmd, $this->object);
        $table->setQuestionIndex($questionIndex);
        $table->setData($this->getAnswersFrequency($relevantAnswers, $questionIndex));
        $table->initColumns();

        return $table;
    }

    public function populateCorrectionsFormProperties(ilPropertyFormGUI $form): void
    {
        $pairs = new ilAssMatchingPairCorrectionsInputGUI($this->lng->txt('matching_pairs'), 'pairs');
        $pairs->setRequired(true);
        $pairs->setTerms($this->object->getTerms());
        $pairs->setDefinitions($this->object->getDefinitions());
        $pairs->setPairs($this->object->getMatchingPairs());
        $pairs->setThumbsWebPathWithPrefix($this->object->getImagePathWeb() . $this->object->getThumbPrefix());
        $form->addItem($pairs);
    }

    /**
     * @param ilPropertyFormGUI $form
     */
    public function saveCorrectionsFormProperties(ilPropertyFormGUI $form): void
    {
        $pairs = $this->object->getMatchingPairs();
        $nu_pairs = [];

        if ($this->request->isset('pairs')) {
            $points_of_pairs = $this->request->raw('pairs')['points'];
            $pair_terms = explode(',', $this->request->raw('pairs')['term_id']);
            $pair_definitions = explode(',', $this->request->raw('pairs')['definition_id']);
            $values = [];
            foreach ($points_of_pairs as $idx => $points) {
                $k = implode('.', [$pair_terms[$idx],$pair_definitions[$idx]]);
                $values[$k] = (float) str_replace(',', '.', $points);
            }

            foreach ($pairs as $idx => $pair) {
                $id = implode('.', [
                    $pair->getTerm()->getIdentifier(),
                    $pair->getDefinition()->getIdentifier()
                ]);
                $nu_pairs[$id] = $pair->withPoints($values[$id]);
            }

            $this->object = $this->object->withMatchingPairs($nu_pairs);
        }
    }
}
