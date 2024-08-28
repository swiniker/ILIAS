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

namespace ILIAS\Export\ExportHandler\Info\Export\Container\ObjectId;

use ILIAS\Export\ExportHandler\I\Info\Export\Container\ObjectId\CollectionInterface as ilExportHandlerContainerExportInfoObjectIdCollectionInterface;
use ILIAS\Export\ExportHandler\I\Info\Export\Container\ObjectId\HandlerInterface as ilExportHandlerContainerExportInfoObjectIdInterface;

class Collection implements ilExportHandlerContainerExportInfoObjectIdCollectionInterface
{
    /**
     * @var ilExportHandlerContainerExportInfoObjectIdInterface[]
     */
    protected array $elements;
    protected int $index;

    public function __construct()
    {
        $this->elements = [];
        $this->index = 0;
    }

    public function withElement(
        ilExportHandlerContainerExportInfoObjectIdInterface $object_id
    ): ilExportHandlerContainerExportInfoObjectIdCollectionInterface {
        $clone = clone $this;
        $clone->elements[] = $object_id;
        return $clone;
    }

    public function head(): ilExportHandlerContainerExportInfoObjectIdInterface
    {
        return $this->elements[0];
    }

    public function withoutHead(): ilExportHandlerContainerExportInfoObjectIdCollectionInterface
    {
        $clone = clone $this;
        array_shift($clone->elements);
        return $clone;
    }

    public function current(): ilExportHandlerContainerExportInfoObjectIdInterface
    {
        return $this->elements[$this->index];
    }

    public function key(): int
    {
        return $this->index;
    }

    public function next(): void
    {
        $this->index++;
    }

    public function rewind(): void
    {
        $this->index = 0;
    }

    public function valid(): bool
    {
        return isset($this->elements[$this->index]);
    }

    public function count(): int
    {
        return count($this->elements);
    }
}
