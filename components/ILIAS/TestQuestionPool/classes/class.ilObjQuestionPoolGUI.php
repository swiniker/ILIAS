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

use ILIAS\TestQuestionPool\QuestionPoolDIC;
use ILIAS\TestQuestionPool\RequestDataCollector;
use ILIAS\TestQuestionPool\Presentation\QuestionTable;
use ILIAS\TestQuestionPool\Questions\GeneralQuestionPropertiesRepository;
use ILIAS\Test\QuestionIdentifiers;

use ILIAS\DI\RBACServices;
use ILIAS\Taxonomy\Service;
use Psr\Http\Message\ServerRequestInterface as HttpRequest;
use ILIAS\UI\Component\Input\Container\Form\Form;
use ILIAS\UI\Component\Input\Field\Select;
use ILIAS\UI\Component\Input\Input;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\GlobalScreen\Services as GlobalScreen;

/**
 * Class ilObjQuestionPoolGUI
 *
 * @author         Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author         Björn Heyser <bheyser@databay.de>
 *
 * @version        $Id$
 *
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: ilAssQuestionPageGUI, ilQuestionBrowserTableGUI, ilToolbarGUI, ilObjTestGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: assMultipleChoiceGUI, assClozeTestGUI, assMatchingQuestionGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: assOrderingQuestionGUI, assImagemapQuestionGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: assNumericGUI, assTextSubsetGUI, assSingleChoiceGUI, ilPropertyFormGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: assTextQuestionGUI, ilObjectMetaDataGUI, ilPermissionGUI, ilObjectCopyGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: ilQuestionPoolExportGUI, ilInfoScreenGUI, ilTaxonomySettingsGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: ilAssQuestionHintsGUI, ilAssQuestionFeedbackEditingGUI, ilLocalUnitConfigurationGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: ilObjQuestionPoolSettingsGeneralGUI, assFormulaQuestionGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: ilAssQuestionPreviewGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: assKprimChoiceGUI, assLongMenuGUI
 * @ilCtrl_Calls   ilObjQuestionPoolGUI: ilQuestionPoolSkillAdministrationGUI
 *
 * @ingroup components\ILIASTestQuestionPool
 *
 */
class ilObjQuestionPoolGUI extends ilObjectGUI implements ilCtrlBaseClassInterface
{
    public const DEFAULT_CMD = 'questions';

    private HttpRequest $http_request;
    private \ILIAS\Filesystem\Util\Archive\LegacyArchives $archives;
    protected Service $taxonomy;
    public ?ilObject $object;
    protected ilDBInterface $db;
    protected RBACServices $rbac;
    protected ilComponentLogger $log;
    protected ilHelpGUI $help;
    protected GlobalScreen $global_screen;
    protected ilComponentFactory $component_factory;
    protected ilComponentRepository $component_repository;
    protected ilNavigationHistory $navigation_history;
    protected ilUIService $ui_service;
    protected DataFactory $data_factory;
    protected URLBuilder $url_builder;
    protected URLBuilderToken $action_parameter_token;
    protected URLBuilderToken $row_id_token;

    protected RequestDataCollector $qplrequest;
    protected GeneralQuestionPropertiesRepository $questionrepository;

    public function __construct()
    {
        /** @var ILIAS\DI\Container $DIC */
        global $DIC;
        $this->type = 'qpl';

        $this->db = $DIC['ilDB'];
        $this->rbac = $DIC->rbac();
        $this->log = $DIC['ilLog'];
        $this->help = $DIC['ilHelp'];
        $this->global_screen = $DIC['global_screen'];
        $this->component_factory = $DIC['component.factory'];
        $this->component_repository = $DIC['component.repository'];
        $this->navigation_history = $DIC['ilNavigationHistory'];
        $this->ui_service = $DIC->uiService();
        $this->taxonomy = $DIC->taxonomy();
        $this->http_request = $DIC->http()->request();
        $this->archives = $DIC->legacyArchives();

        $this->data_factory = new DataFactory();

        $local_dic = QuestionPoolDIC::dic();
        $this->qplrequest = $local_dic['request_data_collector'];
        $this->questionrepository = $local_dic['general_question_properties_repository'];

        parent::__construct('', $this->qplrequest->getRefId(), true, false);

        $this->ctrl->saveParameter($this, [
            'ref_id',
            'test_ref_id',
            'calling_test',
            'test_express_mode',
            'q_id',
            'tax_node',
            'consumer_context'
        ]);
        $this->ctrl->saveParameterByClass('ilAssQuestionPageGUI', 'consumer_context');
        $this->ctrl->saveParameterByClass('ilobjquestionpoolgui', 'consumer_context');

        $this->lng->loadLanguageModule('assessment');

        $here_uri = $this->data_factory->uri($this->request->getUri()->__toString());
        $url_builder = new URLBuilder($here_uri);
        $query_params_namespace = ['qpool', 'table'];
        list($url_builder, $action_parameter_token, $row_id_token) = $url_builder->acquireParameters(
            $query_params_namespace,
            "action", //this is the actions's parameter name
            "qids"   //this is the parameter name to be used for row-ids
        );
        $this->url_builder = $url_builder;
        $this->action_parameter_token = $action_parameter_token;
        $this->row_id_token = $row_id_token;

        $this->notes_service->gui()->initJavascript();
    }

    protected function getQueryParamString(string $param): ?string
    {
        if (!$this->request_wrapper->has($param)) {
            return null;
        }
        $trafo = $this->refinery->byTrying([
            $this->refinery->kindlyTo()->null(),
            $this->refinery->kindlyTo()->string()
        ]);
        return $this->request_wrapper->retrieve($param, $trafo);
    }

    protected function getQueryParamInt(string $param): ?int
    {
        if (!$this->request_wrapper->has($param)) {
            return null;
        }
        $trafo = $this->refinery->byTrying([
            $this->refinery->kindlyTo()->null(),
            $this->refinery->kindlyTo()->int()
        ]);
        return $this->request_wrapper->retrieve($param, $trafo);
    }

    public function executeCommand(): void
    {
        $ilUser = $this->user;
        $ilAccess = $this->access;
        $ilNavigationHistory = $this->navigation_history;
        $tpl = $this->tpl;
        $ilCtrl = $this->ctrl;
        $ilErr = $this->error;
        $ilTabs = $this->tabs_gui;
        $lng = $this->lng;
        $ilDB = $this->db;
        $component_repository = $this->component_repository;
        $ilias = $this->ilias;
        $randomGroup = $this->refinery->random();

        $writeAccess = $ilAccess->checkAccess('write', '', $this->qplrequest->getRefId());

        if ((!$ilAccess->checkAccess('read', '', $this->qplrequest->getRefId()))
            && (!$ilAccess->checkAccess('visible', '', $this->qplrequest->getRefId()))) {
            $ilias->raiseError($this->lng->txt('permission_denied'), $this->ilias->error_obj->MESSAGE);
        }

        if (!$this->getCreationMode() &&
            $ilAccess->checkAccess('read', '', $this->qplrequest->getRefId())) {
            if ('qpl' === $this->object->getType()) {
                $ilNavigationHistory->addItem(
                    $this->qplrequest->getRefId(),
                    ilLink::_getLink($this->qplrequest->getRefId(), "qpl"),
                    'qpl',
                );
            }
        }

        $cmd = $this->ctrl->getCmd(self::DEFAULT_CMD);
        $next_class = $this->ctrl->getNextClass($this);
        $q_id = $this->getQueryParamInt('q_id');

        if (in_array($next_class, ['', 'ilobjquestionpoolgui']) && $cmd == self::DEFAULT_CMD) {
            $q_id = -1;
        }

        $this->prepareOutput();

        $this->tpl->addCss(ilUtil::getStyleSheetLocation('output', 'test_print.css'), 'print');

        $q_type = '';
        if (!(in_array($next_class, ['', 'ilobjquestionpoolgui']) && $cmd == self::DEFAULT_CMD) && $q_id < 1) {
            $q_type = $this->qplrequest->raw('sel_question_types');
        }
        if ($cmd !== 'createQuestion' && $cmd !== 'createQuestionForTest'
            && $next_class != 'ilassquestionpagegui') {
            if (($this->qplrequest->raw('test_ref_id') != '') || ($this->qplrequest->raw('calling_test'))) {
                $ref_id = $this->qplrequest->raw('test_ref_id');
                if (!$ref_id) {
                    $ref_id = $this->qplrequest->raw('calling_test');
                }
            }
        }
        switch ($next_class) {
            case 'ilcommonactiondispatchergui':
                $gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
                $this->ctrl->forwardCommand($gui);
                break;

            case 'ilobjectmetadatagui':
                if (!$ilAccess->checkAccess('write', '', $this->object->getRefId())) {
                    $ilErr->raiseError($this->lng->txt('permission_denied'), $ilErr->WARNING);
                }
                $md_gui = new ilObjectMetaDataGUI($this->object);
                $this->ctrl->forwardCommand($md_gui);
                break;

            case 'ilassquestionpreviewgui':
                if (!$ilAccess->checkAccess('read', '', $this->object->getRefId())) {
                    $this->redirectAfterMissingWrite();
                }

                $this->ctrl->saveParameter($this, 'q_id');
                $gui = new ilAssQuestionPreviewGUI(
                    $this->ctrl,
                    $this->rbac_system,
                    $this->tabs_gui,
                    $this->tpl,
                    $this->lng,
                    $ilDB,
                    $randomGroup,
                    $this->global_screen
                );

                $gui->initQuestion((int) $this->qplrequest->raw('q_id'), $this->object->getId());
                $gui->initPreviewSettings($this->object->getRefId());
                $gui->initPreviewSession($ilUser->getId(), $this->fetchAuthoringQuestionIdParamater());
                $gui->initHintTracking();
                $this->tabs_gui->setBackTarget(
                    $this->lng->txt('backtocallingpool'),
                    $this->ctrl->getLinkTargetByClass(self::class, self::DEFAULT_CMD)
                );

                $ilHelp = $this->help;
                $ilHelp->setScreenIdComponent('qpl');

                $this->ctrl->forwardCommand($gui);
                break;

            case 'ilassquestionpagegui':
                if ($cmd == 'finishEditing') {
                    $this->ctrl->redirectByClass('ilassquestionpreviewgui', 'show');
                    break;
                }
                if ($cmd === 'edit' && !$ilAccess->checkAccess('write', '', $this->object->getRefId())) {
                    $this->redirectAfterMissingWrite();
                }
                $this->tpl->setCurrentBlock('ContentStyle');
                $this->tpl->setVariable('LOCATION_CONTENT_STYLESHEET', ilObjStyleSheet::getContentStylePath(0));
                $this->tpl->parseCurrentBlock();

                $this->tpl->setCurrentBlock('SyntaxStyle');
                $this->tpl->setVariable('LOCATION_SYNTAX_STYLESHEET', ilObjStyleSheet::getSyntaxStylePath());
                $this->tpl->parseCurrentBlock();
                $q_gui = assQuestionGUI::_getQuestionGUI('', $this->fetchAuthoringQuestionIdParamater());
                $q_gui->setRenderPurpose(assQuestionGUI::RENDER_PURPOSE_PREVIEW);
                $q_gui->setQuestionTabs();
                $q_gui->outAdditionalOutput();
                $q_gui->object->setObjId($this->object->getId());

                $q_gui->setTargetGuiClass(null);
                $q_gui->setQuestionActionCmd('');

                if ($this->object->getType() == 'qpl') {
                    $q_gui->addHeaderAction();
                }

                $question = $q_gui->object;

                if ($this->questionrepository->isInActiveTest($question->getObjId())) {
                    $this->tpl->setOnScreenMessage(
                        'failure',
                        $this->lng->txt('question_is_part_of_running_test'),
                        true
                    );
                    $this->ctrl->redirectByClass('ilAssQuestionPreviewGUI', ilAssQuestionPreviewGUI::CMD_SHOW);
                }

                $this->ctrl->saveParameter($this, 'q_id');
                $this->lng->loadLanguageModule('content');
                $this->ctrl->setReturnByClass('ilAssQuestionPageGUI', 'view');
                $this->ctrl->setReturn($this, self::DEFAULT_CMD);
                $page_gui = new ilAssQuestionPageGUI($this->qplrequest->getQuestionId());
                $page_gui->obj->addUpdateListener(
                    $question,
                    'updateTimestamp'
                );
                $page_gui->setEditPreview(true);
                $page_gui->setEnabledTabs(false);
                if (strlen(
                    $this->ctrl->getCmd()
                ) == 0 && !isset($_POST['editImagemapForward_x'])) { // workaround for page edit imagemaps, keep in mind
                    // @todo: removed deprecated ilCtrl methods, this needs inspection by a maintainer.
                    // $this->ctrl->setCmdClass(get_class($page_gui));
                    // $this->ctrl->setCmd('preview');
                }
                $page_gui->setQuestionHTML([$q_gui->object->getId() => $q_gui->getPreview(true)]);
                $page_gui->setTemplateTargetVar('ADM_CONTENT');
                $page_gui->setOutputMode('edit');
                $page_gui->setHeader($question->getTitle());
                $page_gui->setPresentationTitle($question->getTitle());
                $ret = $this->ctrl->forwardCommand($page_gui);
                if ($ret != '') {
                    $tpl->setContent($ret);
                }
                break;

            case 'ilpermissiongui':
                $perm_gui = new ilPermissionGUI($this);
                $this->ctrl->forwardCommand($perm_gui);
                break;

            case 'ilobjectcopygui':
                $cp = new ilObjectCopyGUI($this);
                $cp->setType('qpl');
                $this->ctrl->forwardCommand($cp);
                break;

            case 'ilquestionpoolexportgui':
                $exp_gui = new ilQuestionPoolExportGUI($this);
                $exp_gui->addFormat('xml', $this->lng->txt('qpl_export_xml'));
                $exp_gui->addFormat('xlsx', $this->lng->txt('qpl_export_excel'), $this, 'createExportExcel');
                $ret = $this->ctrl->forwardCommand($exp_gui);
                break;

            case 'ilinfoscreengui':
                $this->infoScreenForward();
                break;

            case 'ilassquestionhintsgui':
                if (!$ilAccess->checkAccess('write', '', $this->object->getRefId())) {
                    $this->redirectAfterMissingWrite();
                }

                $this->ctrl->setReturn($this, self::DEFAULT_CMD);
                $questionGUI = assQuestionGUI::_getQuestionGUI(
                    $q_type ?? '',
                    $this->fetchAuthoringQuestionIdParamater()
                );
                $questionGUI->object->setObjId($this->object->getId());
                $questionGUI->setQuestionTabs();

                if ($this->questionrepository->isInActiveTest($questionGUI->object->getObjId())) {
                    $this->tpl->setOnScreenMessage(
                        'failure',
                        $this->lng->txt('question_is_part_of_running_test'),
                        true
                    );
                    $this->ctrl->redirectByClass('ilAssQuestionPreviewGUI', ilAssQuestionPreviewGUI::CMD_SHOW);
                }

                $ilHelp = $this->help;
                $ilHelp->setScreenIdComponent('qpl');

                if ($this->object->getType() == 'qpl' && $writeAccess) {
                    $questionGUI->addHeaderAction();
                }
                $gui = new ilAssQuestionHintsGUI($questionGUI);

                $gui->setEditingEnabled(
                    $this->access->checkAccess('write', '', $this->object->getRefId())
                );

                $ilCtrl->forwardCommand($gui);

                break;

            case 'illocalunitconfigurationgui':
                if (!$ilAccess->checkAccess('write', '', $this->object->getRefId())) {
                    $ilErr->raiseError($this->lng->txt('permission_denied'), $ilErr->WARNING);
                }
                $questionGUI = assQuestionGUI::_getQuestionGUI($q_type, $this->fetchAuthoringQuestionIdParamater());
                $questionGUI->object->setObjId($this->object->getId());
                $questionGUI->setQuestionTabs();

                $this->ctrl->setReturn($this, self::DEFAULT_CMD);
                $gui = new ilLocalUnitConfigurationGUI(
                    new ilUnitConfigurationRepository($this->qplrequest->getQuestionId())
                );
                $ilCtrl->forwardCommand($gui);
                break;

            case 'ilassquestionfeedbackeditinggui':
                if (!$ilAccess->checkAccess('write', '', $this->object->getRefId())) {
                    $this->redirectAfterMissingWrite();
                }

                $this->ctrl->setReturn($this, self::DEFAULT_CMD);
                $questionGUI = assQuestionGUI::_getQuestionGUI($q_type, $this->fetchAuthoringQuestionIdParamater());
                $questionGUI->object->setObjId($this->object->getId());
                $questionGUI->setQuestionTabs();

                if ($this->questionrepository->isInActiveTest($questionGUI->object->getObjId())) {
                    $this->tpl->setOnScreenMessage(
                        'failure',
                        $this->lng->txt('question_is_part_of_running_test'),
                        true
                    );
                    $this->ctrl->redirectByClass('ilAssQuestionPreviewGUI', ilAssQuestionPreviewGUI::CMD_SHOW);
                }

                $ilHelp = $this->help;
                $ilHelp->setScreenIdComponent('qpl');

                if ($this->object->getType() == 'qpl' && $writeAccess) {
                    $questionGUI->addHeaderAction();
                }
                $gui = new ilAssQuestionFeedbackEditingGUI(
                    $questionGUI,
                    $ilCtrl,
                    $ilAccess,
                    $tpl,
                    $ilTabs,
                    $lng,
                    $this->help,
                    $this->qplrequest,
                    $this->questionrepository
                );
                $ilCtrl->forwardCommand($gui);

                break;

            case 'ilobjquestionpoolsettingsgeneralgui':
                $gui = new ilObjQuestionPoolSettingsGeneralGUI(
                    $ilCtrl,
                    $ilAccess,
                    $lng,
                    $tpl,
                    $ilTabs,
                    $this,
                    $this->refinery,
                    $this->ui_factory,
                    $this->ui_renderer,
                    $this->http_request,
                );
                $this->ctrl->forwardCommand($gui);
                break;

            case strtolower(ilTaxonomySettingsGUI::class):
                if (!$ilAccess->checkAccess('write', '', $this->object->getRefId())) {
                    $this->redirectAfterMissingWrite();
                }

                /** @var ilObjQuestionPool $obj */
                $obj = $this->object;
                $forwarder = new ilObjQuestionPoolTaxonomyEditingCommandForwarder(
                    $this->object,
                    $ilDB,
                    $this->refinery,
                    $component_repository,
                    $ilCtrl,
                    $ilTabs,
                    $lng,
                    $this->taxonomy
                );

                $forwarder->forward();

                break;

            case 'ilquestionpoolskilladministrationgui':
                $obj = $this->object;
                $gui = new ilQuestionPoolSkillAdministrationGUI(
                    $ilias,
                    $ilCtrl,
                    $this->refinery,
                    $ilAccess,
                    $ilTabs,
                    $tpl,
                    $lng,
                    $ilDB,
                    $component_repository,
                    $obj,
                    $this->ref_id
                );

                $this->ctrl->forwardCommand($gui);

                break;


            case 'ilobjquestionpoolgui':
            case '':

                //table actions.
                if ($action = $this->getQueryParamString($this->action_parameter_token->getName())) {
                    $ids = $this->request_wrapper->retrieve(
                        $this->row_id_token->getName(),
                        $this->refinery->custom()->transformation(fn($v) => $v)
                    );

                    if (is_null($ids)) {
                        $this->tpl->setOnScreenMessage('failure', $this->lng->txt('msg_no_questions_selected'), true);
                        $this->ctrl->redirect($this, self::DEFAULT_CMD);
                    }
                    if (! is_array($ids)) {
                        $ids = explode(',', $ids);
                    }
                    $ids = array_map('intval', $ids);

                    $class = strtolower(assQuestionGUI::_getGUIClassNameForId(current($ids)));
                    $this->ctrl->setParameterByClass("ilAssQuestionPageGUI", "q_id", current($ids));
                    $this->ctrl->setParameterByClass("ilAssQuestionPreviewGUI", "q_id", current($ids));
                    $this->ctrl->setParameterByClass('ilAssQuestionFeedbackEditingGUI', 'q_id', current($ids));
                    $this->ctrl->setParameterByClass('ilAssQuestionHintsGUI', 'q_id', current($ids));
                    $this->ctrl->setParameterByClass($class, "q_id", current($ids));

                    switch ($action) {
                        case 'preview':
                            $url = $this->ctrl->getLinkTargetByClass('ilAssQuestionPreviewGUI', ilAssQuestionPreviewGUI::CMD_SHOW);
                            $this->ctrl->redirectToURL($url);
                            break;
                        case 'statistics':
                            $url = $this->ctrl->getLinkTargetByClass('ilAssQuestionPreviewGUI', ilAssQuestionPreviewGUI::CMD_STATISTICS);
                            $this->ctrl->redirectToURL($url);
                            break;
                        case 'edit_question':
                            $url = $this->ctrl->getLinkTargetByClass($class, 'editQuestion');
                            $this->ctrl->redirectToURL($url);
                            break;
                        case 'edit_page':
                            $url = $this->ctrl->getLinkTargetByClass('ilAssQuestionPageGUI', 'edit');
                            $this->ctrl->redirectToURL($url);
                            break;
                        case 'feedback':
                            $url = $this->ctrl->getLinkTargetByClass('ilAssQuestionFeedbackEditingGUI', ilAssQuestionFeedbackEditingGUI::CMD_SHOW);
                            $this->ctrl->redirectToURL($url);
                            break;
                        case 'hints':
                            $url = $this->ctrl->getLinkTargetByClass('ilAssQuestionHintsGUI', ilAssQuestionHintsGUI::CMD_SHOW_LIST);
                            $this->ctrl->redirectToURL($url);
                            break;
                        case 'move':
                            $ret = $this->moveQuestions($ids);
                            $this->ctrl->redirect($this, self::DEFAULT_CMD);
                            break;
                        case 'copy':
                            $this->copyQuestions($ids);
                            $this->ctrl->redirect($this, self::DEFAULT_CMD);
                            break;
                        case 'delete':
                            $this->confirmDeleteQuestions($ids);
                            break;
                        case 'export':
                            $this->exportQuestions($ids);
                            $this->ctrl->redirect($this, self::DEFAULT_CMD);
                            break;
                        case 'comments':
                            $ajax_hash = ilCommonActionDispatcherGUI::buildAjaxHash(
                                ilCommonActionDispatcherGUI::TYPE_REPOSITORY,
                                $this->object->getRefId(),
                                'quest',
                                $this->object->getId(),
                                'quest',
                                current($ids)
                            );
                            echo ''
                                . '<script>'
                                . ' event = new Event("click");'
                                . ilCommentGUI::getListCommentsJSCall($ajax_hash)
                                . '</script>'
                            ;
                            exit();

                        default:
                            throw new \Exception("'$action'" . " not implemented");
                    }
                    break;
                }


                if ($cmd == self::DEFAULT_CMD) {
                    $this->ctrl->setParameter($this, 'q_id', '');
                }
                $cmd .= 'Object';
                $ret = $this->$cmd();
                break;

            default:
                if (in_array($cmd, ['editQuestion', 'save', 'suggestedsolution']) && !$ilAccess->checkAccess(
                    'write',
                    '',
                    $this->object->getRefId()
                )) {
                    $this->redirectAfterMissingWrite();
                }

                if ($cmd === 'assessment' &&
                    $this->object->getType() === 'tst' &&
                    !$ilAccess->checkAccess('write', '', $this->object->getRefId())) {
                    $this->redirectAfterMissingWrite();
                }

                $this->ctrl->setReturn($this, self::DEFAULT_CMD);

                $questionGUI = assQuestionGUI::_getQuestionGUI($q_type, $this->fetchAuthoringQuestionIdParamater());
                $questionGUI->setEditContext(assQuestionGUI::EDIT_CONTEXT_AUTHORING);
                $questionGUI->object->setObjId($this->object->getId());

                if (in_array(
                    $cmd,
                    ['editQuestion', 'save', 'suggestedsolution']
                ) && $this->questionrepository->isInActiveTest($questionGUI->object->getObjId())
                ) {
                    $this->tpl->setOnScreenMessage(
                        'failure',
                        $this->lng->txt('question_is_part_of_running_test'),
                        true
                    );
                    $this->ctrl->redirectByClass('ilAssQuestionPreviewGUI', ilAssQuestionPreviewGUI::CMD_SHOW);
                }

                if ($this->object->getType() == 'qpl') {
                    $questionGUI->setTaxonomyIds($this->object->getTaxonomyIds());

                    if ($writeAccess) {
                        $questionGUI->addHeaderAction();
                    }
                }
                $questionGUI->setQuestionTabs();

                $ilHelp = $this->help;
                $ilHelp->setScreenIdComponent('qpl');
                $ret = $this->ctrl->forwardCommand($questionGUI);
                break;
        }

        if (!(strtolower($this->qplrequest->raw('baseClass')) == 'iladministrationgui'
                || strtolower($this->qplrequest->raw('baseClass')) == 'ilrepositorygui')
            && $this->getCreationMode() != true) {
            $this->tpl->printToStdout();
        }
    }

    protected function redirectAfterMissingWrite()
    {
        $this->tpl->setOnScreenMessage('failure', $this->lng->txt('no_permission'), true);
        $target_class = get_class($this->object) . 'GUI';
        $this->ctrl->setParameterByClass($target_class, 'ref_id', $this->ref_id);
        $this->ctrl->redirectByClass($target_class);
    }

    /**
     * Gateway for exports initiated from workspace, as there is a generic
     * forward to {objTypeMainGUI}::export()
     */
    protected function exportObject(): void
    {
        $this->ctrl->redirectByClass('ilQuestionPoolExportGUI');
    }

    public function downloadFileObject(): void
    {
        $file = explode('_', $this->qplrequest->raw('file_id'));
        $fileObj = new ilObjFile($file[count($file) - 1], false);
        $fileObj->sendFile();
        exit;
    }

    /**
     * show fullscreen view
     */
    public function fullscreenObject(): void
    {
        $page_gui = new ilAssQuestionPageGUI($this->qplrequest->raw('pg_id'));
        $page_gui->showMediaFullscreen();
    }

    /**
     * download source code paragraph
     */
    public function download_paragraphObject(): void
    {
        $pg_obj = new ilAssQuestionPage($this->qplrequest->raw('pg_id'));
        $pg_obj->sendParagraph($this->qplrequest->raw('par_id'), $this->qplrequest->raw('downloadtitle'));
        exit;
    }

    /**
     * imports question(s) into the questionpool
     */
    public function uploadQplObject($questions_only = false)
    {
        $this->ctrl->setParameter($this, 'new_type', $this->qplrequest->raw('new_type'));
        if (!isset($_FILES['xmldoc']) || !isset($_FILES['xmldoc']['error']) || $_FILES['xmldoc']['error'] > UPLOAD_ERR_OK) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('error_upload'), true);
            if (!$questions_only) {
                $this->ctrl->redirect($this, 'create');
            }
            return false;
        }

        $basedir = $this->createImportDirectory();

        $xml_file = '';
        $qti_file = '';
        $subdir = '';

        $file = pathinfo($_FILES['xmldoc']['name']);
        $full_path = $basedir . '/' . $_FILES['xmldoc']['name'];

        if (strpos($file['filename'], 'qpl') === false
            && strpos($file['filename'], 'qti') === false) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('import_file_not_valid'), true);
            $cmd = $this->ctrl->getCmd() === 'upload' ? 'importQuestions' : 'create';
            $this->ctrl->redirect($this, $cmd);
            return;
        }

        $this->log->write(__METHOD__ . ': full path ' . $full_path);
        try {
            ilFileUtils::moveUploadedFile($_FILES['xmldoc']['tmp_name'], $_FILES['xmldoc']['name'], $full_path);
        } catch (Error $e) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('import_file_not_valid'), true);
            $cmd = $this->ctrl->getCmd() === 'upload' ? 'importQuestions' : 'create';
            $this->ctrl->redirect($this, $cmd);
            return;
        }
        $this->log->write(__METHOD__ . ': full path ' . $full_path);
        if (strcmp($_FILES['xmldoc']['type'], 'text/xml') == 0) {
            $qti_file = $full_path;
            ilObjTest::_setImportDirectory($basedir);
        } else {
            $this->archives->unzip($full_path);

            $subdir = basename($file['basename'], '.' . $file['extension']);
            ilObjQuestionPool::_setImportDirectory($basedir);
            $xml_file = ilObjQuestionPool::_getImportDirectory() . '/' . $subdir . '/' . $subdir . '.xml';
            $qti_file = ilObjQuestionPool::_getImportDirectory() . '/' . $subdir . '/' . str_replace(
                'qpl',
                'qti',
                $subdir
            ) . '.xml';
        }
        if (!file_exists($qti_file)) {
            ilFileUtils::delDir($basedir);
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('cannot_find_xml'), true);
            $cmd = $this->ctrl->getCmd() === 'upload' ? 'importQuestions' : 'create';
            $this->ctrl->redirect($this, $cmd);
            return false;
        }
        $qtiParser = new ilQTIParser($qti_file, ilQTIParser::IL_MO_VERIFY_QTI, 0, []);
        $qtiParser->startParsing();
        $founditems = &$qtiParser->getFoundItems();
        if (count($founditems) == 0) {
            ilFileUtils::delDir($basedir);

            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('qpl_import_no_items'), true);
            if (!$questions_only) {
                $this->ctrl->redirect($this, 'create');
            }
            return false;
        }

        $complete = 0;
        $incomplete = 0;
        foreach ($founditems as $item) {
            if (strlen($item['type'])) {
                $complete++;
            } else {
                $incomplete++;
            }
        }

        if ($complete == 0) {
            ilFileUtils::delDir($basedir);

            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('qpl_import_non_ilias_files'), true);
            if (!$questions_only) {
                $this->ctrl->redirect($this, 'create');
            }
            return false;
        }

        ilSession::set('qpl_import_xml_file', $xml_file);
        ilSession::set('qpl_import_qti_file', $qti_file);
        ilSession::set('qpl_import_subdir', $subdir);

        $this->tpl->addBlockFile(
            'ADM_CONTENT',
            'adm_content',
            'tpl.qpl_import_verification.html',
            'components/ILIAS/TestQuestionPool'
        );
        $table = new ilQuestionPoolImportVerificationTableGUI($this, 'uploadQpl');
        $rows = [];

        foreach ($founditems as $item) {
            $row = [
                'title' => $item['title'],
                'ident' => $item['ident'],
            ];
            switch ($item['type']) {
                case QuestionIdentifiers::CLOZE_TEST_IDENTIFIER:
                    $type = $this->lng->txt('assClozeTest');
                    break;
                case QuestionIdentifiers::IMAGEMAP_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assImagemapQuestion');
                    break;
                case QuestionIdentifiers::MATCHING_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assMatchingQuestion');
                    break;
                case QuestionIdentifiers::MULTIPLE_CHOICE_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assMultipleChoice');
                    break;
                case QuestionIdentifiers::KPRIM_CHOICE_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assKprimChoice');
                    break;
                case QuestionIdentifiers::LONG_MENU_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assLongMenu');
                    break;
                case QuestionIdentifiers::SINGLE_CHOICE_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assSingleChoice');
                    break;
                case QuestionIdentifiers::ORDERING_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assOrderingQuestion');
                    break;
                case QuestionIdentifiers::TEXT_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assTextQuestion');
                    break;
                case QuestionIdentifiers::NUMERIC_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assNumeric');
                    break;
                case QuestionIdentifiers::TEXTSUBSET_QUESTION_IDENTIFIER:
                    $type = $this->lng->txt('assTextSubset');
                    break;
                default:
                    $type = $this->lng->txt($item['type']);
                    break;
            }

            if (strcmp($type, '-' . $item['type'] . '-') == 0) {
                $component_factory = $this->component_factory;
                $component_repository = $this->component_repository;
                foreach ($component_factory->getActivePluginsInSlot('qst') as $pl) {
                    if (strcmp($pl->getQuestionType(), $item['type']) == 0) {
                        $type = $pl->getQuestionTypeTranslation();
                    }
                }
            }

            $row['type'] = $type;

            $rows[] = $row;
        }
        $table->setData($rows);

        $this->tpl->setCurrentBlock('import_qpl');
        if (is_file($xml_file)) {
            try {
                $fh = fopen($xml_file, 'r');
                $xml = fread($fh, filesize($xml_file));
                fclose($fh);
            } catch (Exception $e) {
                return false;
            }
            if (preg_match('/<ContentObject.*?MetaData.*?General.*?Title[^>]*?>([^<]*?)</', $xml, $matches)) {
                $this->tpl->setVariable('VALUE_NEW_QUESTIONPOOL', $matches[1]);
            }
        }
        $this->tpl->setVariable('TEXT_CREATE_NEW_QUESTIONPOOL', $this->lng->txt('qpl_import_create_new_qpl'));
        $this->tpl->parseCurrentBlock();

        $this->tpl->setCurrentBlock('adm_content');
        $this->tpl->setVariable('FOUND_QUESTIONS_INTRODUCTION', $this->lng->txt('qpl_import_verify_found_questions'));
        if ($questions_only) {
            $this->tpl->setVariable('VERIFICATION_HEADING', $this->lng->txt('import_questions_into_qpl'));
            $this->tpl->setVariable('FORMACTION', $this->ctrl->getFormAction($this));
        } else {
            $this->tpl->setVariable('VERIFICATION_HEADING', $this->lng->txt('import_qpl'));

            $this->ctrl->setParameter($this, 'new_type', $this->type);
            $this->tpl->setVariable('FORMACTION', $this->ctrl->getFormAction($this));
        }

        $value_questions_only = 0;
        if ($questions_only) {
            $value_questions_only = 1;
        }
        $this->tpl->setVariable('VALUE_QUESTIONS_ONLY', $value_questions_only);
        $this->tpl->setVariable('VERIFICATION_TABLE', $table->getHtml());
        $this->tpl->setVariable('VERIFICATION_FORM_NAME', $table->getFormName());

        $this->tpl->parseCurrentBlock();

        return true;
    }

    private function createImportDirectory(): string
    {
        $qpl_data_dir = ilFileUtils::getDataDir() . '/qpl_data';
        ilFileUtils::makeDir($qpl_data_dir);

        if (!is_writable($qpl_data_dir)) {
            $this->error->raiseError(
                'Questionpool Data Directory (' . $qpl_data_dir
                . ') not writeable.',
                $this->error->FATAL
            );
        }

        $qpl_dir = $qpl_data_dir . '/qpl_import';
        ilFileUtils::makeDir($qpl_dir);
        if (!@is_dir($qpl_dir)) {
            $this->error->raiseError('Creation of Questionpool Directory failed.', $this->error->FATAL);
        }
        return $qpl_dir;
    }

    public function importVerifiedFileObject(): void
    {
        if ($_POST['questions_only'] == 1) {
            $newObj = &$this->object;
        } else {
            $newObj = new ilObjQuestionPool(0, true);
            $newObj->setType($this->qplrequest->raw('new_type'));
            $newObj->setTitle('dummy');
            $newObj->setDescription('questionpool import');
            $newObj->create(true);
            $newObj->createReference();
            $newObj->putInTree($this->qplrequest->getRefId());
            $newObj->setPermissions($this->qplrequest->getRefId());
        }

        if (is_string(ilSession::get("qpl_import_dir")) && is_string(ilSession::get("qpl_import_subdir")) && is_file(
            ilSession::get("qpl_import_dir") . '/' . ilSession::get("qpl_import_subdir") . "/manifest.xml"
        )) {
            ilSession::set("qpl_import_idents", $this->qplrequest->raw("ident"));

            $fileName = ilSession::get('qpl_import_subdir') . '.zip';
            $fullPath = ilSession::get('qpl_import_dir') . '/' . $fileName;
            $imp = new ilImport($this->qplrequest->getRefId());
            $map = $imp->getMapping();
            $map->addMapping('components/ILIAS/TestQuestionPool', 'qpl', 'new_id', $newObj->getId());
            $imp->importObject($newObj, $fullPath, $fileName, 'qpl', 'components/ILIAS/TestQuestionPool', true);
        } else {
            $idents = $this->qplrequest->raw('ident') ?? [];
            $qtiParser = new ilQTIParser(
                ilSession::get('qpl_import_qti_file'),
                ilQTIParser::IL_MO_PARSE_QTI,
                $newObj->getId(),
                $idents
            );
            $qtiParser->startParsing();
            // import page data
            if (strlen(ilSession::get('qpl_import_xml_file'))) {
                $contParser = new ilQuestionPageParser(
                    $newObj,
                    ilSession::get('qpl_import_xml_file'),
                    ilSession::get('qpl_import_subdir')
                );
                $contParser->setQuestionMapping($qtiParser->getImportMapping());
                $contParser->startParsing();
                // #20494
                $newObj->fromXML(ilSession::get('qpl_import_xml_file'));
            }

            // set another question pool name (if possible)
            if (isset($_POST['qpl_new']) && strlen($_POST['qpl_new'])) {
                $newObj->setTitle($_POST['qpl_new']);
            }

            $newObj->update();
            $newObj->saveToDb();
        }
        ilFileUtils::delDir(dirname(ilObjQuestionPool::_getImportDirectory()));

        if ($_POST['questions_only'] == 1) {
            $this->ctrl->redirect($this, self::DEFAULT_CMD);
        } else {
            $this->tpl->setOnScreenMessage('success', $this->lng->txt('object_imported'), true);
            ilUtil::redirect(
                'ilias.php?ref_id=' . $newObj->getRefId() .
                '&baseClass=ilObjQuestionPoolGUI'
            );
        }
    }

    public function cancelImportObject(): void
    {
        if ($_POST['questions_only'] == 1) {
            $this->ctrl->redirect($this, self::DEFAULT_CMD);
        } else {
            $this->ctrl->redirect($this, 'cancel');
        }
    }

    /**
     * imports question(s) into the questionpool
     */
    public function uploadObject(): void
    {
        $upload_valid = true;
        $form = $this->getImportQuestionsForm();
        if ($form->checkInput()) {
            if (!$this->uploadQplObject(true)) {
                $form->setValuesByPost();
                $this->importQuestionsObject($form);
            }
        } else {
            $form->setValuesByPost();
            $this->importQuestionsObject($form);
        }
    }

    /**
     * display the import form to import questions into the questionpool
     */
    public function importQuestionsObject(ilPropertyFormGUI $form = null): void
    {
        if (!$form instanceof ilPropertyFormGUI) {
            $form = $this->getImportQuestionsForm();
        }

        $this->tpl->setContent($form->getHtml());
    }

    protected function getImportQuestionsForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->lng->txt('import_question'));
        $form->setFormAction($this->ctrl->getFormAction($this, 'upload'));

        $file = new ilFileInputGUI($this->lng->txt('select_file'), 'xmldoc');
        $file->setRequired(true);
        $form->addItem($file);

        $form->addCommandButton('upload', $this->lng->txt('upload'));
        $form->addCommandButton(self::DEFAULT_CMD, $this->lng->txt('cancel'));

        return $form;
    }

    public function createQuestionObject(): void
    {
        $form = $this->buildQuestionCreationForm()->withRequest($this->request);
        $data_with_section = $form->getData();
        if ($data_with_section === null) {
            $this->createQuestionFormObject($form);
            return;
        }
        $data = $data_with_section[0];

        /** @var assQuestionGUI $question_gui */
        $question_gui = assQuestionGUI::_getQuestionGUI(
            ilObjQuestionPool::getQuestionTypeByTypeId($data['question_type'])
        );
        $question_gui->object->setObjId($this->object->getId());
        $question_gui->object->setAdditionalContentEditingMode($data['editing_type']);
        $question_gui->setQuestionTabs();
        $question_gui->editQuestion();
    }

    public function createQuestionForTestObject(): void
    {
        if (!$this->qplrequest->raw('q_id')) {
            if (ilObjTestFolder::isAdditionalQuestionContentEditingModePageObjectEnabled()) {
                $add_cont_edit_mode = $this->qplrequest->raw('add_quest_cont_edit_mode');
            } else {
                $add_cont_edit_mode = assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_RTE;
            }
            $q_gui = assQuestionGUI::_getQuestionGUI($this->qplrequest->raw('sel_question_types'));
            $q_gui->object->setObjId($this->object->getId());
            $q_gui->object->setAdditionalContentEditingMode($add_cont_edit_mode);
            $q_gui->object->createNewQuestion();

            $class = get_class($q_gui);
            $q_id = $q_gui->object->getId();
        } else {
            $class = $this->qplrequest->raw('sel_question_types') . 'gui';
            $q_id = $this->qplrequest->raw('q_id');
        }

        $this->ctrl->setParameterByClass($class, 'q_id', $q_id);
        $this->ctrl->setParameterByClass($class, 'sel_question_types', $this->qplrequest->raw('sel_question_types'));
        $this->ctrl->setParameterByClass($class, 'prev_qid', $this->qplrequest->raw('prev_qid'));

        $this->ctrl->redirectByClass($class, 'editQuestion');
    }

    public function afterSave(ilObject $new_object): void
    {
        // always send a message
        $this->tpl->setOnScreenMessage('success', $this->lng->txt('object_added'), true);

        ilUtil::redirect(
            'ilias.php?ref_id=' . $new_object->getRefId() .
            '&baseClass=ilObjQuestionPoolGUI'
        );
    }

    public function questionObject(): void
    {
        // @PHP8-CR: With this probably never working and no detectable usages, it would be a candidate for removal...
        // but it is one of the magic command-methods ($cmd.'Object' - pattern) so I live to leave this in here for now
        // until it can be further investigated.
        $type = $this->qplrequest->raw('sel_question_types');
        $this->editQuestionForm($type);
    }

    public function confirmDeleteQuestions(array $ids): void
    {
        $rbacsystem = $this->rbac_system;

        $questionIdsToDelete = array_filter(array_map('intval', $ids));
        if (0 === count($questionIdsToDelete)) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('qpl_delete_select_none'), true);
            $this->ctrl->redirect($this, self::DEFAULT_CMD);
        }

        $this->tpl->setOnScreenMessage('question', $this->lng->txt('qpl_confirm_delete_questions'));
        $deleteable_questions = $this->object->getDeleteableQuestionDetails($questionIdsToDelete);
        $table_gui = new ilQuestionBrowserTableGUI($this, self::DEFAULT_CMD, (($rbacsystem->checkAccess('write', $this->qplrequest->getRefId()) ? true : false)), true);
        $table_gui->setShowRowsSelector(false);
        $table_gui->setLimit(PHP_INT_MAX);
        $table_gui->setEditable($rbacsystem->checkAccess('write', $this->qplrequest->getRefId()));
        $table_gui->setData($deleteable_questions);
        $this->tpl->setVariable('ADM_CONTENT', $table_gui->getHTML());
    }

    /**
     * delete questions confirmation screen
     */
    public function deleteQuestionsObject(): void
    {
        $rbacsystem = $this->rbac_system;

        $questionIdsToDelete = $this->qplrequest->isset('q_id') ? (array) $this->qplrequest->raw('q_id') : [];
        if ($questionIdsToDelete === [] && $this->qplrequest->isset('q_id')) {
            $questionIdsToDelete = [$this->qplrequest->getQuestionId()];
        }

        $questionIdsToDelete = array_filter(array_map('intval', $questionIdsToDelete));
        if ($questionIdsToDelete === []) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('qpl_delete_select_none'), true);
            $this->ctrl->redirect($this, self::DEFAULT_CMD);
        }

        $this->tpl->setOnScreenMessage('question', $this->lng->txt('qpl_confirm_delete_questions'));
        $deleteable_questions = &$this->object->getDeleteableQuestionDetails($questionIdsToDelete);
        $table_gui = new ilQuestionBrowserTableGUI(
            $this,
            self::DEFAULT_CMD,
            (($rbacsystem->checkAccess('write', $this->qplrequest->getRefId()) ? true : false)),
            true
        );
        $table_gui->setShowRowsSelector(false);
        $table_gui->setLimit(PHP_INT_MAX);
        $table_gui->setEditable($rbacsystem->checkAccess('write', $this->qplrequest->getRefId()));
        $table_gui->setData($deleteable_questions);
        $this->tpl->setVariable('ADM_CONTENT', $table_gui->getHTML());
    }

    /**
     * delete questions after confirmation
     */
    public function confirmDeleteQuestionsObject(): void
    {
        foreach ($this->qplrequest->raw('q_id') as $value) {
            $this->object->deleteQuestion((int) $value);
            $this->object->cleanupClipboard((int) $value);
        }
        if (count($_POST['q_id'])) {
            $this->tpl->setOnScreenMessage('success', $this->lng->txt('qpl_questions_deleted'), true);
        }

        $this->ctrl->setParameter($this, 'q_id', '');
        $this->ctrl->redirect($this, self::DEFAULT_CMD);
    }

    public function cancelDeleteQuestionsObject(): void
    {
        $this->ctrl->redirect($this, self::DEFAULT_CMD);
    }

    public function exportQuestions(array $ids): void
    {
        if ($ids !== []) {
            $qpl_exp = new ilQuestionpoolExport($this->object, 'xml', $ids);
            // @PHP8-CR: This seems to be a pointer to an issue with exports. I like to leave this open for now and
            // schedule a thorough examination / analysis for later, eventually involved T&A TechSquad
            $export_file = $qpl_exp->buildExportFile();
            $filename = $export_file;
            $filename = preg_replace('/.*\//', '', $filename);
            if ($export_file === '') {
                $export_file = 'StandIn';
            }
            ilFileDelivery::deliverFileLegacy($export_file, $filename);
            exit();
        } else {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('qpl_export_select_none'), true);
        }
    }

    protected function renoveImportFailsObject(): void
    {
        $qsaImportFails = new ilAssQuestionSkillAssignmentImportFails($this->object->getId());
        $qsaImportFails->deleteRegisteredImportFails();

        $this->ctrl->redirect($this, 'infoScreen');
    }

    /**
     * list questions of question pool
     */
    public function questionsObject(): void
    {
        if (!$this->access->checkAccess("read", "", $this->qplrequest->getRefId())) {
            $this->infoScreenForward();
            return;
        }

        if (get_class($this->object) == 'ilObjTest') {
            if ($this->qplrequest->raw('calling_test') > 0) {
                $ref_id = $this->qplrequest->raw('calling_test');
                $q_id = $this->qplrequest->raw('q_id');

                if ($this->qplrequest->raw('test_express_mode')) {
                    if ($q_id) {
                        ilUtil::redirect(
                            'ilias.php?ref_id=' . $ref_id . '&q_id=' . $q_id . '&test_express_mode=1&cmd=showPage&cmdClass=iltestexpresspageobjectgui&baseClass=ilObjTestGUI'
                        );
                    } else {
                        ilUtil::redirect(
                            'ilias.php?ref_id=' . $ref_id . '&test_express_mode=1&cmd=showPage&cmdClass=iltestexpresspageobjectgui&baseClass=ilObjTestGUI'
                        );
                    }
                } else {
                    ilUtil::redirect('ilias.php?baseClass=ilObjTestGUI&ref_id=' . $ref_id . '&cmd=questions');
                }
            }
        }

        if ($this->qplrequest->isset('calling_consumer') && (int) $this->qplrequest->raw('calling_consumer')) {
            $ref_id = (int) $this->qplrequest->raw('calling_consumer');
            $consumer = ilObjectFactory::getInstanceByRefId($ref_id);
            if ($consumer instanceof ilQuestionEditingFormConsumer) {
                ilUtil::redirect(
                    $consumer->getQuestionEditingFormBackTarget($this->qplrequest->raw('consumer_context'))
                );
            }
            ilUtil::redirect(ilLink::_getLink($ref_id));
        }

        $this->object->purgeQuestions();
        // reset test_id SESSION variable
        ilSession::set('test_id', '');
        $qsa_import_fails = new ilAssQuestionSkillAssignmentImportFails($this->object->getId());
        if ($qsa_import_fails->failedImportsRegistered()) {
            $button = $this->ui_factory->button()->standard(
                $this->lng->txt('ass_skl_import_fails_remove_btn'),
                $this->ctrl->getLinkTarget($this, 'renoveImportFails')
            );
            $this->tpl->setOnScreenMessage(
                'failure',
                $qsa_import_fails->getFailedImportsMessage($this->lng) . '<br />' . $this->ui_renderer->render(
                    $button
                )
            );
        }

        $out = [];

        if ($this->rbac_system->checkAccess('write', $this->qplrequest->getRefId())) {
            $toolbar = new ilToolbarGUI();
            $btn = $this->ui_factory->button()->primary(
                $this->lng->txt('ass_create_question'),
                $this->ctrl->getLinkTarget($this, 'createQuestionForm')
            );
            $toolbar->addComponent($btn);

            $btn_import = $this->ui_factory->button()->standard(
                $this->lng->txt('import'),
                $this->ctrl->getLinkTarget($this, 'importQuestions')
            );
            $toolbar->addComponent($btn_import);

            if (ilSession::get('qpl_clipboard') != null && count(ilSession::get('qpl_clipboard'))) {
                $btn_paste = $this->ui_factory->button()->standard(
                    $this->lng->txt('paste'),
                    $this->ctrl->getLinkTarget($this, 'paste')
                );
                $toolbar->addComponent($btn_paste);
            }

            $this->tpl->setContent(
                $out[] = $this->ctrl->getHTML($toolbar)
            );
        }

        $this->tpl->setPermanentLink($this->object->getType(), $this->object->getRefId());
        $out[] = $this->getTable();
        $this->tpl->setContent(implode('', $out));
    }

    protected function fetchAuthoringQuestionIdParamater(): int
    {
        $q_id = $this->qplrequest->getQuestionId();

        if ($this->object->checkQuestionParent($q_id)) {
            return $q_id;
        }

        throw new ilTestQuestionPoolException('question id does not relate to parent object!');
    }

    private function createQuestionFormObject(Form $form = null): void
    {
        $this->help->setScreenId('assQuestions');
        if (ilObjTestFolder::isAdditionalQuestionContentEditingModePageObjectEnabled()) {
            $this->help->setSubScreenId('createQuestion_editMode');
        } else {
            $this->help->setSubScreenId('createQuestion');
        }

        $this->tpl->setContent(
            $this->ui_renderer->render(
                $form ?? $this->buildQuestionCreationForm()
            )
        );
    }

    private function buildQuestionCreationForm(): Form
    {
        $inputs['question_type'] = $this->buildInputQuestionType();
        $inputs['editing_type'] = $this->buildInputEditingType();

        $section = [
            $this->ui_factory->input()->field()->section($inputs, $this->lng->txt('ass_create_question'))
        ];

        $form = $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, 'createQuestion'),
            $section
        )->withSubmitLabel('create');

        return $form;
    }

    private function buildInputQuestionType(): Select
    {
        $question_types = (new ilObjQuestionPool())->getQuestionTypes(false, true, false);
        $options = [];
        foreach ($question_types as $label => $data) {
            $options[$data['question_type_id']] = $label;
        }

        return $this->ui_factory->input()->field()->select(
            $this->lng->txt('question_type'),
            $options
        )->withRequired(true);
    }

    private function buildInputEditingType(): Input
    {
        if (!ilObjTestFolder::isAdditionalQuestionContentEditingModePageObjectEnabled()) {
            return $this->ui_factory->input()->field()->hidden()->withValue(
                assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_RTE
            );
        }

        return $this->ui_factory->input()->field()->radio('tst_add_quest_cont_edit_mode')
            ->withOption(
                assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_IPE,
                $this->lng->txt('tst_add_quest_cont_edit_mode_IPE'),
                $this->lng->txt('tst_add_quest_cont_edit_mode_IPE_info')
            )->withOption(
                assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_RTE,
                $this->lng->txt('tst_add_quest_cont_edit_mode_RTE'),
                $this->lng->txt('tst_add_quest_cont_edit_mode_RTE_info')
            )
            ->withValue(assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_IPE);
    }

    public function printObject(): void
    {
        $this->ctrl->setParameter($this, 'output', 'overview');
        $output_link = $this->ctrl->getLinkTarget($this, 'print');
        $this->ctrl->setParameter($this, 'output', 'detailed_output_solutions');
        $output_link_detailed = $this->ctrl->getLinkTarget($this, 'print');
        $this->ctrl->setParameter($this, 'output', 'detailed_output_printview');
        $output_link_printview = $this->ctrl->getLinkTarget($this, 'print');

        $mode = $this->ui_factory->dropdown()->standard([
            $this->ui_factory->button()->shy($this->lng->txt('overview'), $output_link),
            $this->ui_factory->button()->shy($this->lng->txt('detailed_output_solutions'), $output_link_detailed),
            $this->ui_factory->button()->shy($this->lng->txt('detailed_output_printview'), $output_link_printview)
        ])->withLabel($this->lng->txt('output_mode'));

        $output = $this->qplrequest->raw('output') ?? '';

        $table_gui = new ilQuestionPoolPrintViewTableGUI($this, 'print', $output);
        $data = $this->object->getPrintviewQuestions();
        $totalPoints = 0;
        foreach ($data as $d) {
            $totalPoints += $d['points'];
        }
        $table_gui->setTotalPoints($totalPoints);
        $table_gui->initColumns();
        $table_gui->setData($data);
        $this->tpl->setContent($this->ui_renderer->render($mode) . $table_gui->getHTML());
    }

    public function updateObject(): void
    {
        $this->object->update();
        $this->tpl->setOnScreenMessage('success', $this->lng->txt('msg_obj_modified'), true);
    }

    public function pasteObject(): void
    {
        if (ilSession::get('qpl_clipboard') != null) {
            if ($this->object->pasteFromClipboard()) {
                $this->tpl->setOnScreenMessage('success', $this->lng->txt('qpl_paste_success'), true);
            } else {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('qpl_paste_error'), true);
            }
        } else {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('qpl_paste_no_objects'), true);
        }
        $this->ctrl->redirect($this, self::DEFAULT_CMD);
    }

    public function copyQuestions(array $ids): void
    {
        if ($ids) {
            foreach ($ids as $id) {
                $this->object->copyToClipboard($id);
            }
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('qpl_copy_insert_clipboard'), true);
        } else {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('qpl_copy_select_none'), true);
        }
    }

    public function moveQuestions(array $ids): void
    {
        if ($ids) {
            foreach ($ids as $id) {
                $this->object->moveToClipboard($id);
            }
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('qpl_move_insert_clipboard'), true);
        } else {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('qpl_move_select_none'), true);
        }
    }

    public function createExportExcel(): void
    {
        $rbacsystem = $this->rbac_system;
        if ($rbacsystem->checkAccess('write', $this->qplrequest->getRefId())) {
            $question_ids = &$this->object->getAllQuestionIds();
            $qpl_exp = new ilQuestionpoolExport($this->object, 'xlsx', $question_ids);
            $qpl_exp->buildExportFile();
            $this->ctrl->redirectByClass('ilquestionpoolexportgui', '');
        }
    }

    public function editQuestionForTestObject(): void
    {
        $this->ctrl->redirectByClass(ilAssQuestionPreviewGUI::class, 'show');
    }

    protected function initImportForm(string $new_type): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTarget('_top');
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->lng->txt('import_qpl'));

        $fi = new ilFileInputGUI($this->lng->txt('import_file'), 'xmldoc');
        $fi->setSuffixes(['zip']);
        $fi->setRequired(true);
        $form->addItem($fi);

        $form->addCommandButton('importFile', $this->lng->txt('import'));
        $form->addCommandButton('cancel', $this->lng->txt('cancel'));

        return $form;
    }

    /**
     * form for new questionpool object import
     */
    protected function importFileObject(int $parent_id = null): void
    {
        if ($_REQUEST['new_type'] === null) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('import_file_not_valid'), true);
            $this->ctrl->redirect($this, 'create');
            return;
        }
        if (!$this->checkPermissionBool('create', '', $_REQUEST['new_type'])) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('no_create_permission'), true);
            $this->ctrl->redirect($this, 'create');
            return;
        }

        $form = $this->initImportForm($this->qplrequest->raw('new_type'));
        if ($form->checkInput()) {
            $this->uploadQplObject();
        }

        // display form to correct errors
        $this->tpl->setContent($form->getHTML());
    }

    public function addLocatorItems(): void
    {
        $ilLocator = $this->locator;

        switch ($this->ctrl->getCmd()) {
            case 'create':
            case 'importFile':
            case 'cancel':
                break;
            default:
                $this->ctrl->clearParameterByClass(self::class, 'q_id');
                $ilLocator->addItem(
                    $this->object->getTitle(),
                    $this->ctrl->getLinkTarget($this, ''),
                    '',
                    $this->qplrequest->getRefId()
                );
                $this->ctrl->setParameter($this, 'q_id', $this->qplrequest->getQuestionId());
                break;
        }

        if (!is_array($this->qplrequest->raw('q_id')) && $this->qplrequest->raw('q_id') > 0 && $this->qplrequest->raw(
            'cmd'
        ) !== self::DEFAULT_CMD) {
            $q_gui = assQuestionGUI::_getQuestionGUI('', $this->qplrequest->getQuestionId());
            if ($q_gui !== null && $q_gui->object instanceof assQuestion) {
                $q_gui->object->setObjId($this->object->getId());
                $title = $q_gui->object->getTitle();
                if (!$title) {
                    $title = $this->lng->txt('new') . ': ' . $this->questionrepository->getForQuestionId(
                        $q_gui->object->getId()
                    )->getTypeName();
                }
                $ilLocator->addItem($title, $this->ctrl->getLinkTargetByClass(get_class($q_gui), 'editQuestion'));
            } else {
                // Workaround for context issues: If no object was found, redirect without q_id parameter
                $this->ctrl->setParameter($this, 'q_id', '');
                $this->ctrl->redirect($this);
            }
        }
    }

    /**
     * called by prepare output
     */
    public function setTitleAndDescription(): void
    {
        parent::setTitleAndDescription();

        if (!is_array($this->qplrequest->raw('q_id')) && $this->qplrequest->raw('q_id') > 0 && $this->qplrequest->raw(
            'cmd'
        ) !== self::DEFAULT_CMD) {
            $q_gui = assQuestionGUI::_getQuestionGUI('', $this->qplrequest->getQuestionId());
            if ($q_gui->object instanceof assQuestion) {
                $q_gui->object->setObjId($this->object->getId());
                $title = $q_gui->object->getTitle();
                if (!$title) {
                    $title = $this->lng->txt('new') . ': ' . $this->questionrepository->getForQuestionId(
                        $q_gui->object->getId()
                    )->getTypeName($this->lng);
                }
                $this->tpl->setTitle(
                    strip_tags(
                        $title,
                        self::ALLOWED_TAGS_IN_TITLE_AND_DESCRIPTION
                    )
                );
                $this->tpl->setDescription(
                    $q_gui->object->getDescriptionForHTMLOutput()
                );
                $this->tpl->setTitleIcon(ilObject2::_getIcon($this->object->getId(), 'big', $this->object->getType()));
            } else {
                // Workaround for context issues: If no object was found, redirect without q_id parameter
                $this->ctrl->setParameter($this, 'q_id', '');
                $this->ctrl->redirect($this);
            }
        } else {
            $this->tpl->setTitle(
                strip_tags(
                    $this->object->getTitle(),
                    self::ALLOWED_TAGS_IN_TITLE_AND_DESCRIPTION
                )
            );
            $this->tpl->setDescription(
                strip_tags(
                    $this->object->getLongDescription(),
                    self::ALLOWED_TAGS_IN_TITLE_AND_DESCRIPTION
                )
            );
            $this->tpl->setTitleIcon(ilObject2::_getIcon($this->object->getId(), 'big', $this->object->getType()));
        }
    }

    /**
     * adds tabs to tab gui object
     *
     * @param object $tabs_gui ilTabsGUI object
     */
    public function getTabs(): void
    {
        $ilHelp = $this->help;

        $currentUserHasWriteAccess = $this->access->checkAccess('write', '', $this->object->getRefId());
        $currentUserHasReadAccess = $this->access->checkAccess('read', '', $this->object->getRefId());

        $ilHelp->setScreenIdComponent('qpl');

        $next_class = strtolower($this->ctrl->getNextClass());
        switch ($next_class) {
            case '':
            case 'ilpermissiongui':
            case 'ilobjectmetadatagui':
            case 'ilquestionpoolexportgui':
            case 'ilquestionpoolskilladministrationgui':
                break;

            case strtolower(ilTaxonomySettingsGUI::class):
            case 'ilobjquestionpoolsettingsgeneralgui':

                if ($currentUserHasWriteAccess) {
                    $this->addSettingsSubTabs($this->tabs_gui);
                }

                break;

            default:
                return;
                break;
        }
        // questions
        $force_active = false;
        $commands = $this->getQueryParamString('cmd');
        if (is_array($commands)) {
            foreach ($commands as $key => $value) {
                if (preg_match('/^delete_.*/', $key, $matches) ||
                    preg_match('/^addSelectGap_.*/', $key, $matches) ||
                    preg_match('/^addTextGap_.*/', $key, $matches) ||
                    preg_match('/^deleteImage_.*/', $key, $matches) ||
                    preg_match('/^upload_.*/', $key, $matches) ||
                    preg_match('/^addSuggestedSolution_.*/', $key, $matches)
                ) {
                    $force_active = true;
                }
            }
        }
        if (isset($_POST['imagemap_x'])) {
            $force_active = true;
        }
        if (!$force_active) {
            $force_active = ((strtolower($this->ctrl->getCmdClass()) == strtolower(get_class($this)) || strlen(
                $this->ctrl->getCmdClass()
            ) == 0) &&
                $this->ctrl->getCmd() == '')
                ? true
                : false;
        }
        if ($currentUserHasReadAccess) {
            $this->tabs_gui->addTarget(
                'assQuestions',
                $this->ctrl->getLinkTarget($this, self::DEFAULT_CMD),
                [
                    self::DEFAULT_CMD,
                    'filter',
                    'resetFilter',
                    'createQuestion',
                    'importQuestions',
                    'deleteQuestions',
                    'filterQuestionBrowser',
                    'view',
                    'preview',
                    'editQuestion',
                    'exec_pg',
                    'addItem',
                    'upload',
                    'save',
                    'cancel',
                    'addSuggestedSolution',
                    'cancelExplorer',
                    'linkChilds',
                    'removeSuggestedSolution',
                    'add',
                    'addYesNo',
                    'addTrueFalse',
                    'createGaps',
                    'setMediaMode',
                    'uploadingImage',
                    'uploadingImagemap',
                    'addArea',
                    'deletearea',
                    'saveShape',
                    'back',
                    'addPair',
                    'uploadingJavaapplet',
                    'addParameter',
                    'assessment',
                    'addGIT',
                    'addST',
                    'addPG',
                    'delete',
                    'toggleGraphicalAnswers',
                    'deleteAnswer',
                    'deleteImage',
                    'removeJavaapplet'
                ],
                '',
                '',
                $force_active
            );
        }
        if ($currentUserHasReadAccess) {
            $this->tabs_gui->addTarget(
                'info_short',
                $this->ctrl->getLinkTarget($this, 'infoScreen'),
                ['infoScreen', 'showSummary']
            );
        }

        if ($currentUserHasWriteAccess) {
            // properties
            $this->tabs_gui->addTarget(
                'settings',
                $this->ctrl->getLinkTargetByClass('ilObjQuestionPoolSettingsGeneralGUI'),
                [],
                ['ilObjQuestionPoolSettingsGeneralGUI', 'ilObjTaxonomyGUI']
            );

            // skill service
            if ($this->isSkillsTabRequired()) {
                $link = $this->ctrl->getLinkTargetByClass(
                    ['ilQuestionPoolSkillAdministrationGUI', 'ilAssQuestionSkillAssignmentsGUI'],
                    ilAssQuestionSkillAssignmentsGUI::CMD_SHOW_SKILL_QUEST_ASSIGNS
                );

                $this->tabs_gui->addTarget('qpl_tab_competences', $link, [], []);
            }
        }

        if ($currentUserHasReadAccess) {
            // print view
            $this->tabs_gui->addTarget(
                'print_view',
                $this->ctrl->getLinkTarget($this, 'print'),
                ['print'],
                '',
                ''
            );
        }

        if ($currentUserHasWriteAccess) {
            $mdgui = new ilObjectMetaDataGUI($this->object);
            $mdtab = $mdgui->getTab();
            if ($mdtab) {
                $this->tabs_gui->addTarget(
                    'meta_data',
                    $mdtab,
                    '',
                    'ilmdeditorgui'
                );
            }
        }

        if ($currentUserHasWriteAccess) {
            $this->tabs_gui->addTarget(
                'export',
                $this->ctrl->getLinkTargetByClass('ilquestionpoolexportgui', ''),
                '',
                'ilquestionpoolexportgui'
            );
        }

        if ($this->access->checkAccess('edit_permission', '', $this->object->getRefId())) {
            $this->tabs_gui->addTarget(
                'perm_settings',
                $this->ctrl->getLinkTargetByClass([get_class($this), 'ilpermissiongui'], 'perm'),
                ['perm', 'info', 'owner'],
                'ilpermissiongui'
            );
        }
    }

    private function isSkillsTabRequired(): bool
    {
        if (!($this->object instanceof ilObjQuestionPool)) {
            return false;
        }

        if (!$this->object->isSkillServiceEnabled()) {
            return false;
        }

        if (!ilObjQuestionPool::isSkillManagementGloballyActivated()) {
            return false;
        }

        return true;
    }

    private function addSettingsSubTabs(ilTabsGUI $tabs): void
    {
        $tabs->addSubTab(
            ilObjQuestionPoolSettingsGeneralGUI::TAB_COMMON_SETTINGS,
            $this->lng->txt('qpl_settings_subtab_general'),
            $this->ctrl->getLinkTargetByClass('ilObjQuestionPoolSettingsGeneralGUI'),
        );
        if ($this->object->getShowTaxonomies()) {
            $tabs->addSubTab(
                'tax_settings',
                $this->lng->txt('qpl_settings_subtab_taxonomies'),
                $this->ctrl->getLinkTargetByClass('ilTaxonomySettingsGUI', ''),
            );
        }
    }

    /**
     * this one is called from the info button in the repository
     * not very nice to set cmdClass/Cmd manually, if everything
     * works through ilCtrl in the future this may be changed
     */
    public function infoScreenObject(): void
    {
        // @todo: removed deprecated ilCtrl methods, this needs inspection by a maintainer.
        // $this->ctrl->setCmd('showSummary');
        // $this->ctrl->setCmdClass('ilinfoscreengui');
        $this->infoScreenForward();
    }

    public function infoScreenForward(): void
    {
        if (!$this->access->checkAccess('visible', '', $this->ref_id)) {
            $this->error->raiseError($this->lng->txt('msg_no_perm_read'));
        }
        $info = new ilInfoScreenGUI($this);
        $info->enablePrivateNotes();

        // standard meta data
        $info->addMetaDataSections($this->object->getId(), 0, $this->object->getType());

        $this->ctrl->forwardCommand($info);
    }

    public static function _goto($a_target): void
    {
        global $DIC;
        $main_tpl = $DIC->ui()->mainTemplate();
        $ilAccess = $DIC['ilAccess'];
        $ilErr = $DIC['ilErr'];
        $lng = $DIC['lng'];
        $ctrl = $DIC['ilCtrl'];

        if ($ilAccess->checkAccess('write', '', (int) $a_target)
            || $ilAccess->checkAccess('read', '', (int) $a_target)
        ) {
            $target_class = ilObjQuestionPoolGUI::class;
            $target_cmd = self::DEFAULT_CMD;
            $ctrl->setParameterByClass($target_class, 'ref_id', $a_target);
            $ctrl->redirectByClass([ilRepositoryGUI::class, $target_class], $target_cmd);
            return;
        }
        if ($ilAccess->checkAccess('visible', "", $a_target)) {
            ilObjectGUI::_gotoRepositoryNode($a_target, 'infoScreen');
            return;
        }
        if ($ilAccess->checkAccess("read", "", ROOT_FOLDER_ID)) {
            $main_tpl->setOnScreenMessage(
                'info',
                sprintf(
                    $lng->txt("msg_no_perm_read_item"),
                    ilObject::_lookupTitle(ilObject::_lookupObjId($a_target))
                ),
                true
            );
            ilObjectGUI::_gotoRepositoryRoot();
            return;
        }
        $ilErr->raiseError($lng->txt('msg_no_perm_read_lm'), $ilErr->FATAL);
    }

    protected function getTable(): string
    {
        $f = $this->ui_factory;
        $r = $this->ui_renderer;

        $table = new QuestionTable(
            $f,
            $r,
            $this->data_factory,
            $this->refinery,
            $this->url_builder,
            $this->action_parameter_token,
            $this->row_id_token,
            $this->db,
            $this->lng,
            $this->component_repository,
            $this->rbac_system,
            $this->taxonomy->domain(),
            $this->notes_service,
            $this->object->getId(),
            (int) $this->qplrequest->getRefId()
        );

        /**
         * Filters should be part of the Table; for now, since they are not fully
         * integrated, they are rendered and applied seperately
         */
        $filter_action = $this->ctrl->getLinkTarget($this, self::DEFAULT_CMD);
        $filter = $table->getFilter($this->ui_service, $filter_action);

        $filter_params = $this->ui_service->filter()->getData($filter);
        if ($filter_params) {
            foreach (array_filter($filter_params) as $item => $value) {

                switch ($item) {
                    case 'taxonomies':
                        if($value === 'null') {
                            $table->addTaxonomyFilterNoTaxonomySet(true);
                        } else {
                            $tax_nodes = explode('-', $value);
                            $tax_id = array_shift($tax_nodes);
                            $table->addTaxonomyFilter(
                                $tax_id,
                                $tax_nodes,
                                $this->object->getId(),
                                $this->object->getType()
                            );
                        }
                        break;
                    case 'commented':
                        $table->setCommentFilter($value);
                        break;
                    default:
                        $table->addFieldFilter($item, $value);
                }
            }
        }

        return $r->render([
            $filter,
            $table->getTable()
            ->withRequest($this->request)
        ]);
    }
}
