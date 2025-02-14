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
 * @author Alexander Killing <killing@leifos.de>
 */
class ilLMContentRendererGUI
{
    public const STATUS_ACCESS = 0;
    public const STATUS_NO_ACCESS = 1;
    public const STATUS_NO_PUBLIC_ACCESS = 2;
    public const STATUS_FAILED_PRECONDITIONS = 3;
    public const STATUS_CORRECT_ANSWER_MISSING = 4;
    public const STATUS_NO_PAGE_IN_CHAPTER = 5;
    public const STATUS_DEACTIVATED_PAGE = 6;
    public const STATUS_NO_PAGE_FOUND = 7;
    protected ilLMNavigationStatus $navigation_status;

    protected ?int $requested_focus_return;
    protected int $requested_obj_id = 0;
    protected ?string $search_string;
    protected ?int $focus_id;
    protected bool $deactivated_page;
    protected bool $chapter_has_no_active_page;
    protected ilAccessHandler $access;
    protected ilObjUser $user;
    protected ilHelpGUI $help;
    protected ?int $current_page = null;
    protected ilObjLearningModule $lm;
    protected ilLanguage $lng;
    protected bool $offline;
    protected ilLMTracker $tracker;
    protected ilCtrl $ctrl;
    protected ilLMTree $lm_tree;
    protected ilLMPresentationGUI $parent_gui;
    protected ilSetting $lm_set;
    protected string $lang;
    protected ilLMPresentationLinker $linker;
    protected string $requested_frame;
    protected ilObjectTranslation $ot;
    protected string $concrete_lang = "";

    public function __construct(
        ilLMPresentationService $service,
        ilLMPresentationGUI $parent_gui,
        ilLanguage $lng,
        ilCtrl $ctrl,
        ilAccessHandler $access,
        ilObjUser $user,
        ilHelpGUI $help,
        int $requested_obj_id
    ) {
        $this->access = $access;
        $this->user = $user;
        $this->help = $help;
        $this->ctrl = $ctrl;
        $this->lm_tree = $service->getLMTree();
        $this->lang = $service->getPresentationStatus()->getLang();
        $this->current_page = $service->getNavigationStatus()->getCurrentPage();
        $this->lm = $service->getLearningModule();
        $this->lm_set = $service->getSettings();
        $this->lng = $lng;
        $this->offline = $service->getPresentationStatus()->offline;
        $this->tracker = $service->getTracker();
        $this->linker = $service->getLinker();
        $this->parent_gui = $parent_gui;
        $this->chapter_has_no_active_page = $service->getNavigationStatus()->isChapterWithoutActivePage();
        $this->deactivated_page = $service->getNavigationStatus()->isDeactivatedPage();
        $this->focus_id = $service->getPresentationStatus()->getFocusId();
        $this->concrete_lang = $service->getPresentationStatus()->getConcreteLang();
        $this->search_string = $service->getPresentationStatus()->getSearchString();
        $this->requested_obj_id = $requested_obj_id;
        $this->requested_focus_return = $service->getPresentationStatus()->getFocusReturn();
        $this->requested_frame = $service->getRequest()->getFrame();
        $this->navigation_status = $service->getNavigationStatus();
        $this->ot = ilObjectTranslation::getInstance($this->lm->getId());
    }

    protected function initHelp(): void
    {
        $ilHelp = $this->help;
        $ilHelp->setScreenIdComponent("lm");
        $ilHelp->setScreenId("content");
        $ilHelp->setSubScreenId("content");
    }

    protected function determineStatus(): int
    {
        $user = $this->user;

        $status = self::STATUS_ACCESS;
        // check page id
        $requested_page_lm = ilLMPage::lookupParentId($this->current_page, "lm");
        if ($requested_page_lm != $this->lm->getId()) {
            $status = self::STATUS_NO_ACCESS;
        }


        // check if page is (not) visible in public area
        if ($user->getId() == ANONYMOUS_USER_ID &&
            $this->lm->getPublicAccessMode() == 'selected') {
            if (!ilLMObject::_isPagePublic($this->current_page)) {
                $status = self::STATUS_NO_PUBLIC_ACCESS;
            }
        }

        // preconditions
        if (!ilObjContentObject::_checkPreconditionsOfPage($this->lm->getRefId(), $this->lm->getId(), $this->current_page)) {
            $status = self::STATUS_FAILED_PRECONDITIONS;
        }

        // if navigation is restricted based on correct answered questions
        // check if we have preceeding pages including unsanswered/incorrect answered questions
        if (!$this->offline) {
            if ($this->lm->getRestrictForwardNavigation()) {
                if ($this->tracker->hasPredIncorrectAnswers($this->current_page)) {
                    $status = self::STATUS_CORRECT_ANSWER_MISSING;
                }
            }
        }

        // no active page found in chapter
        if ($this->chapter_has_no_active_page &&
            ilLMObject::_lookupType($this->requested_obj_id) == "st") {
            $status = self::STATUS_NO_PAGE_IN_CHAPTER;
        }

        if ($this->deactivated_page) {
            $status = self::STATUS_DEACTIVATED_PAGE;
        }

        if ($this->current_page == 0) {
            $status = self::STATUS_NO_PAGE_FOUND;
        }

        return $status;
    }

    protected function initSearchHighlighting(): void
    {
        $user = $this->user;

        if ($this->search_string != "" && !$this->offline) {
            $cache = ilUserSearchCache::_getInstance($user->getId());
            $cache->switchSearchType(ilUserSearchCache::LAST_QUERY);
            $search_string = $cache->getQuery();

            // advanced search?
            if (is_array($search_string)) {
                $search_string = $search_string["lom_content"];
            }

            $p = new ilQueryParser($search_string);
            $p->parse();

            $words = $p->getQuotedWords();
            if (is_array($words)) {
                foreach ($words as $w) {
                    ilTextHighlighterGUI::highlight("ilLMPageContent", $w);
                }
            }
        }
    }

    public function render(
        int $a_head_foot_page_id = 0
    ): string {
        $ilUser = $this->user;

        $head = $foot = "";

        $this->initHelp();

        switch ($this->determineStatus()) {
            case self::STATUS_NO_ACCESS:
                return $this->renderNoPageAccess();

            case self::STATUS_NO_PUBLIC_ACCESS:
                return $this->renderNoPublicAccess();

            case self::STATUS_FAILED_PRECONDITIONS:
                return $this->renderPreconditionsOfPage();

            case self::STATUS_CORRECT_ANSWER_MISSING:
                return $this->renderNavRestrictionDueToQuestions();

            case self::STATUS_NO_PAGE_IN_CHAPTER:
                return $this->renderNoPageInChapterMessage();

            case self::STATUS_DEACTIVATED_PAGE:
                return $this->renderDeactivatedPageMessage();

            case self::STATUS_NO_PAGE_FOUND:
                return $this->renderNoPageFoundMessage();
        }

        // page id is e.g. > 0 when footer or header page is processed
        if ($a_head_foot_page_id == 0) {
            $page_id = $this->current_page;
            $this->initSearchHighlighting();
        } else {
            $page_id = $a_head_foot_page_id;
        }

        // check if page is out of focus
        $focus_mess = $this->renderFocusMessage();
        $page_object_gui = $this->getLMPageGUI($page_id);

        // @todo 6.0 (factor this out (maybe to ilLMPageGUI)
        $this->parent_gui->basicPageGuiInit($page_object_gui);

        $page_object = $page_object_gui->getPageObject();
        $page_object->buildDom();
        $page_object->registerOfflineHandler($this);

        $page_object_gui->setTemplateOutput(false);

        // Update course items
        ilCourseLMHistory::_updateLastAccess($ilUser->getId(), $this->lm->getRefId(), $page_id);

        // read link targets
        $page_object_gui->setPageLinker($this->linker);

        // get lm page object
        $lm_pg_obj = new ilLMPageObject($this->lm, $page_id);
        $lm_pg_obj->setLMId($this->lm->getId());

        // determine target frames for internal links
        $page_object_gui->setLinkFrame($this->requested_frame);

        // page title and tracking (not for header or footer page)
        if ($page_id == 0 || ($page_id != $this->lm->getHeaderPage() &&
                $page_id != $this->lm->getFooterPage())) {
            $page_object_gui->setPresentationTitle(
                ilLMPageObject::_getPresentationTitle(
                    $lm_pg_obj->getId(),
                    $this->lm->getPageHeader(),
                    $this->lm->isActiveNumbering(),
                    (bool) $this->lm_set->get("time_scheduled_page_activation"),
                    false,
                    0,
                    $this->lang
                )
            );

            // track access
            if ($page_id != 0 && !$this->offline) {
                $this->tracker->trackAccess($page_id, $ilUser->getId());
            }
        } else {
            $page_object_gui->setEnabledPageFocus(false);
            $page_object_gui->getPageConfig()->setEnableSelfAssessment(false);
        }

        // ADDED FOR CITATION
        $page_object_gui->setLinkParams("ref_id=" . $this->lm->getRefId());
        $page_object_gui->setTemplateTargetVar("PAGE_CONTENT");
        // @todo 6.0
        //		$page_object_gui->setSourcecodeDownloadScript($this->getSourcecodeDownloadLink());

        $ret = $page_object_gui->presentation($page_object_gui->getOutputMode());

        // process header
        if ($this->lm->getHeaderPage() > 0 &&
            $page_id != $this->lm->getHeaderPage() &&
            ($page_id == 0 || $page_id != $this->lm->getFooterPage())) {
            if (ilLMObject::_exists($this->lm->getHeaderPage())) {
                $head = $this->render($this->lm->getHeaderPage());
            }
        }

        // process footer
        if ($this->lm->getFooterPage() > 0 &&
            $page_id != $this->lm->getFooterPage() &&
            ($page_id == 0 || $page_id != $this->lm->getHeaderPage())) {
            if (ilLMObject::_exists($this->lm->getFooterPage())) {
                $foot = $this->render($this->lm->getFooterPage());
            }
        }
        return $head . $focus_mess . $ret . $foot;
    }

    public function getLMPageGUI(int $a_id): ilLMPageGUI
    {
        if ($this->lang != "-" && ilPageObject::_exists("lm", $a_id, $this->lang)) {
            $page_gui = new ilLMPageGUI($a_id, 0, false, $this->lang, $this->concrete_lang);
        } else {
            if ($this->lang != "-" && ilPageObject::_exists("lm", $a_id, $this->ot->getFallbackLanguage())) {
                $page_gui = new ilLMPageGUI($a_id, 0, false, $this->ot->getFallbackLanguage(), $this->concrete_lang);
            } else {
                $page_gui = new ilLMPageGUI($a_id, 0, false, "", $this->concrete_lang);
            }
        }
        if ($this->offline) {
            $page_gui->setOutputMode(ilPageObjectGUI::OFFLINE);
        }
        return $page_gui;
    }

    public function handleCodeParagraph(
        int $page_id,
        int $paragraph_id,
        string $title,
        string $text
    ): void {
        $directory = $this->parent_gui->getOfflineDirectory() . "/codefiles/" . $page_id . "/" . $paragraph_id;
        ilFileUtils::makeDirParents($directory);
        $file = $directory . "/" . $title;
        if (!($fp = fopen($file, "w+"))) {
            die("<b>Error</b>: Could not open \"" . $file . "\" for writing" .
                " in <b>" . __FILE__ . "</b> on line <b>" . __LINE__ . "</b><br />");
        }
        chmod($file, 0770);
        fwrite($fp, $text);
        fclose($fp);
    }

    protected function renderFocusMessage(): string
    {
        $focus_mess = "";
        if ($this->focus_id > 0) {
            $path = $this->lm_tree->getPathId($this->current_page);

            // out of focus
            if (!in_array($this->focus_id, $path)) {
                $mtpl = new ilTemplate(
                    "tpl.out_of_focus_message.html",
                    true,
                    true,
                    "Modules/LearningModule"
                );
                $mtpl->setVariable("MESSAGE", $this->lng->txt("cont_out_of_focus_message"));
                $mtpl->setVariable("TXT_SHOW_CONTENT", $this->lng->txt("cont_show_content_after_focus"));

                if ($this->requested_focus_return == 0 || ilObject::_lookupType((int) $this->requested_focus_return, true) != "crs") {
                    $mtpl->setVariable("TXT_BACK_BEGINNING", $this->lng->txt("cont_to_focus_beginning"));
                    $this->ctrl->setParameter($this->parent_gui, "obj_id", $this->focus_id);
                    $mtpl->setVariable("LINK_BACK_TO_BEGINNING", $this->ctrl->getLinkTarget($this->parent_gui, "layout"));
                    $this->ctrl->setParameter($this->parent_gui, "obj_id", $this->requested_obj_id);
                } else {
                    $mtpl->setVariable("TXT_BACK_BEGINNING", $this->lng->txt("cont_to_focus_return_crs"));
                    $mtpl->setVariable("LINK_BACK_TO_BEGINNING", ilLink::_getLink($this->requested_focus_return));
                }

                $this->ctrl->setParameter($this->parent_gui, "focus_id", null);
                $mtpl->setVariable("LINK_SHOW_CONTENT", $this->ctrl->getLinkTarget($this->parent_gui, "layout"));
                $this->ctrl->setParameter($this->parent_gui, "focus_id", $this->requested_obj_id);

                $focus_mess = $mtpl->get();
            } else {
                $succ_page_id = $this->navigation_status->getSuccessorPageId();
                $path2 = array();
                if ($succ_page_id > 0) {
                    $path2 = $this->lm_tree->getPathId($succ_page_id);
                }
                if ($succ_page_id == 0 || !in_array($this->focus_id, $path2)) {
                    $mtpl = new ilTemplate(
                        "tpl.out_of_focus_message.html",
                        true,
                        true,
                        "Modules/LearningModule"
                    );
                    $mtpl->setVariable("MESSAGE", $this->lng->txt("cont_out_of_focus_message_last_page"));
                    $mtpl->setVariable("TXT_SHOW_CONTENT", $this->lng->txt("cont_show_content_after_focus"));

                    if ($this->requested_focus_return == 0 || ilObject::_lookupType($this->requested_focus_return, true) != "crs") {
                        $mtpl->setVariable("TXT_BACK_BEGINNING", $this->lng->txt("cont_to_focus_beginning"));
                        $this->ctrl->setParameter($this->parent_gui, "obj_id", $this->focus_id);
                        $mtpl->setVariable("LINK_BACK_TO_BEGINNING", $this->ctrl->getLinkTarget($this->parent_gui, "layout"));
                        $this->ctrl->setParameter($this->parent_gui, "obj_id", $this->requested_obj_id);
                    } else {
                        $mtpl->setVariable("TXT_BACK_BEGINNING", $this->lng->txt("cont_to_focus_return_crs"));
                        $mtpl->setVariable("LINK_BACK_TO_BEGINNING", ilLink::_getLink($this->requested_focus_return));
                    }

                    $this->ctrl->setParameter($this->parent_gui, "focus_id", null);
                    $mtpl->setVariable("LINK_SHOW_CONTENT", $this->ctrl->getLinkTarget($this->parent_gui, "layout"));
                    $this->ctrl->setParameter($this->parent_gui, "focus_id", $this->requested_obj_id);

                    $focus_mess = $mtpl->get();
                }
            }
        }
        return $focus_mess;
    }


    /**
     * Render info message, if page is not accessible in public area
     */
    protected function renderNoPageAccess(): string
    {
        return $this->renderMessageScreen($this->lng->txt("msg_no_page_access"));
    }

    /**
     * Render message screen
     */
    protected function renderMessageScreen(string $a_content): string
    {
        // content style
        $tpl = new ilTemplate("tpl.page_message_screen.html", true, true, "Modules/LearningModule");
        $tpl->setVariable("TXT_PAGE_NO_PUBLIC_ACCESS", $a_content);

        return $tpl->get();
    }

    /**
     * Render info message, if page is not accessible in public area
     */
    protected function renderNoPublicAccess(): string
    {
        return $this->renderMessageScreen($this->lng->txt("msg_page_no_public_access"));
    }

    /**
     * Render message if navigation to page is not allowed due to unanswered
     * questions.
     */
    protected function renderNavRestrictionDueToQuestions(): string
    {
        return $this->renderMessageScreen($this->lng->txt("cont_no_page_access_unansw_q"));
    }

    /**
     * Render no page in chapter message
     */
    protected function renderNoPageInChapterMessage(): string
    {
        $mtpl = new ilTemplate(
            "tpl.no_content_message.html",
            true,
            true,
            "Modules/LearningModule"
        );
        $mtpl->setVariable("MESSAGE", $this->lng->txt("cont_no_page_in_chapter"));
        $mtpl->setVariable(
            "ITEM_TITLE",
            ilLMObject::_lookupTitle($this->requested_obj_id)
        );
        return $mtpl->get();
    }

    /**
     * Render no page found message
     */
    protected function renderNoPageFoundMessage(): string
    {
        return $this->renderMessageScreen($this->lng->txt("cont_no_page"));
    }

    protected function renderDeactivatedPageMessage(): string
    {
        $mtpl = new ilTemplate(
            "tpl.no_content_message.html",
            true,
            true,
            "Modules/LearningModule"
        );
        $m = $this->lng->txt("cont_page_currently_deactivated");
        $act_data = ilLMPage::_lookupActivationData($this->requested_obj_id, $this->lm->getType());
        if ($act_data["show_activation_info"] &&
            (ilUtil::now() < $act_data["activation_start"])) {
            $m .= "<p>" . sprintf(
                $this->lng->txt("cont_page_activation_on"),
                ilDatePresentation::formatDate(
                    new ilDateTime($act_data["activation_start"], IL_CAL_DATETIME)
                )
            ) .
                "</p>";
        }
        $mtpl->setVariable("MESSAGE", $m);
        $mtpl->setVariable(
            "ITEM_TITLE",
            ilLMObject::_lookupTitle($this->requested_obj_id)
        );
        return $mtpl->get();
    }


    /**
     * Render preconditions of the page
     */
    public function renderPreconditionsOfPage(): string
    {
        $succ_node = "";
        $conds = ilObjContentObject::_getMissingPreconditionsOfPage($this->lm->getRefId(), $this->lm->getId(), $this->current_page);
        $topchap = ilObjContentObject::_getMissingPreconditionsTopChapter($this->lm->getRefId(), $this->lm->getId(), $this->current_page);

        $ptpl = new ilTemplate("tpl.page_preconditions.html", true, true, "Modules/LearningModule");

        // list all missing preconditions
        foreach ($conds as $cond) {
            $obj_link = ilLink::_getLink($cond["trigger_ref_id"]);
            $ptpl->setCurrentBlock("condition");
            $ptpl->setVariable("VAL_ITEM", ilObject::_lookupTitle($cond["trigger_obj_id"]));
            $ptpl->setVariable("LINK_ITEM", $obj_link);
            if ($cond["operator"] == "passed") {
                $cond_str = $this->lng->txt("passed");
            } else {
                $cond_str = $this->lng->txt("condition_" . $cond["operator"]);
            }
            $ptpl->setVariable("VAL_CONDITION", $cond_str . " " . $cond["value"]);
            $ptpl->parseCurrentBlock();
        }

        $ptpl->setVariable(
            "TXT_MISSING_PRECONDITIONS",
            sprintf(
                $this->lng->txt("cont_missing_preconditions"),
                ilLMObject::_lookupTitle($topchap)
            )
        );
        $ptpl->setVariable("TXT_ITEM", $this->lng->txt("object"));
        $ptpl->setVariable("TXT_CONDITION", $this->lng->txt("condition"));

        // output skip chapter link
        $parent = $this->lm_tree->getParentId($topchap);
        $childs = $this->lm_tree->getChildsByType($parent, "st");
        $j = -2;
        $i = 1;
        foreach ($childs as $child) {
            if ($child["child"] == $topchap) {
                $j = $i;
            }
            if ($i++ == ($j + 1)) {
                $succ_node = $this->lm_tree->fetchSuccessorNode($child["child"], "pg");
            }
        }
        if ($succ_node != "") {
            $link = "<br /><a href=\"" .
                $this->linker->getLink("layout", $succ_node["obj_id"], $this->requested_frame) .
                "\">" . $this->lng->txt("cont_skip_chapter") . "</a>";
            $ptpl->setVariable("LINK_SKIP_CHAPTER", $link);
        }

        return $ptpl->get();
    }
}
