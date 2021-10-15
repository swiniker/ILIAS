<?php

use ILIAS\Refinery\Factory as Refinery;
use ILIAS\HTTP\Wrapper\RequestWrapper;

/**
 * Class ilCtrlContext is responsible for holding the
 * current context information.
 *
 * @author Thibeau Fuhrer <thf@studer-raimann.ch>
 */
final class ilCtrlContext implements ilCtrlContextInterface
{
    /**
     * @var ilCtrlPathFactory
     */
    private ilCtrlPathFactory $path_factory;

    /**
     * @var RequestWrapper
     */
    private RequestWrapper $request;

    /**
     * @var Refinery
     */
    private Refinery $refinery;

    /**
     * @var ilCtrlPathInterface
     */
    private ilCtrlPathInterface $path;

    /**
     * @var bool
     */
    private bool $is_async = false;

    /**
     * @var string|null
     */
    private ?string $cmd_mode = null;

    /**
     * @var string|null
     */
    private ?string $redirect = null;

    /**
     * @var string
     */
    private string $target_script = 'ilias.php';

    /**
     * @var string|null
     */
    private ?string $base_class = null;

    /**
     * @var string|null
     */
    private ?string $cmd_class = null;

    /**
     * @var string|null
     */
    private ?string $cmd = null;

    /**
     * @var string|null
     */
    private ?string $obj_type = null;

    /**
     * @var int|null
     */
    private ?int $obj_id = null;

    /**
     * ilCtrlContext Constructor
     *
     * @param ilCtrlPathFactory $path_factory
     * @param RequestWrapper    $request
     * @param Refinery          $refinery
     */
    public function __construct(ilCtrlPathFactory $path_factory, RequestWrapper $request, Refinery $refinery)
    {
        $this->path_factory = $path_factory;
        $this->request      = $request;
        $this->refinery     = $refinery;

        // initialize null-path per default.
        $this->path = $path_factory->null();
    }

    /**
     * @inheritDoc
     */
    public function adoptRequestParameters() : void
    {
        $this->is_async   = (ilCtrlInterface::CMD_MODE_ASYNC === $this->getQueryParam(ilCtrlInterface::PARAM_CMD_MODE));

        // if the query parameter is not provided, the currently
        // set value must be used instead, otherwise the values
        // are overwritten by null.
        $this->redirect   = $this->getQueryParam(ilCtrlInterface::PARAM_REDIRECT) ?? $this->redirect;
        $this->base_class = $this->getQueryParam(ilCtrlInterface::PARAM_BASE_CLASS) ?? $this->base_class;
        $this->cmd_class  = $this->getQueryParam(ilCtrlInterface::PARAM_CMD_CLASS) ?? $this->cmd_class;
        $this->cmd_mode   = $this->getQueryParam(ilCtrlInterface::PARAM_CMD_MODE) ?? $this->cmd_mode;
        $this->cmd        = $this->getQueryParam(ilCtrlInterface::PARAM_CMD) ?? $this->cmd;

        $existing_path = $this->getQueryParam(ilCtrlInterface::PARAM_CID_PATH);
        if (null !== $existing_path) {
            $this->path = $this->path_factory->existing($existing_path);
        } elseif (null !== $this->base_class) {
            $this->path = $this->path_factory->find($this, $this->base_class);
        } else {
            $this->path = $this->path_factory->null();
        }
    }

    /**
     * @inheritDoc
     */
    public function isAsync() : bool
    {
        return $this->is_async;
    }

    /**
     * @inheritDoc
     */
    public function getRedirectSource() : ?string
    {
        return $this->redirect;
    }

    /**
     * @inheritDoc
     */
    public function getPath() : ilCtrlPathInterface
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function setCmdMode(string $mode) : ilCtrlContextInterface
    {
        $this->cmd_mode = $mode;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCmdMode() : ?string
    {
        return $this->cmd_mode;
    }

    /**
     * @inheritDoc
     */
    public function setBaseClass(string $base_class) : ilCtrlContextInterface
    {
        $this->base_class = $base_class;
        $this->path = $this->path_factory->find($this, $base_class);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getBaseClass() : ?string
    {
        return $this->base_class;
    }

    /**
     * @inheritDoc
     */
    public function setTargetScript(string $target_script) : ilCtrlContextInterface
    {
        $this->target_script = $target_script;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getTargetScript() : string
    {
        return $this->target_script;
    }

    /**
     * @inheritDoc
     */
    public function setCmdClass(string $cmd_class) : ilCtrlContextInterface
    {
        $this->cmd_class = $cmd_class;
        $this->path = $this->path_factory->find($this, $cmd_class);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCmdClass() : ?string
    {
        // if no cmd_class is set yet, the baseclass
        // value can be returned.
        return $this->cmd_class ?? $this->base_class;
    }

    /**
     * @inheritDoc
     */
    public function setCmd(string $cmd) : ilCtrlContextInterface
    {
        $this->cmd = $cmd;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCmd() : ?string
    {
        return $this->cmd;
    }

    /**
     * @inheritDoc
     */
    public function setObjType(string $obj_type) : ilCtrlContextInterface
    {
        $this->obj_type = $obj_type;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getObjType() : ?string
    {
        return $this->obj_type;
    }

    /**
     * @inheritDoc
     */
    public function setObjId(int $obj_id) : ilCtrlContextInterface
    {
        $this->obj_id = $obj_id;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getObjId() : ?int
    {
        return $this->obj_id;
    }

    /**
     * Helper function to retrieve request parameter values by name.
     *
     * @param string $parameter_name
     * @return string|null
     */
    private function getQueryParam(string $parameter_name) : ?string
    {
        if ($this->request->has($parameter_name)) {
            return $this->request->retrieve(
                $parameter_name,
                $this->refinery->to()->string()
            );
        }

        return null;
    }
}