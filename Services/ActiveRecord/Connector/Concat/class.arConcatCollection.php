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
 * Class arConcatCollection
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 2.0.7
 */
class arConcatCollection extends arStatementCollection
{
    public function asSQLStatement(): string
    {
        $return = '';
        if ($this->hasStatements()) {
            $return = ', ';
            $concats = $this->getConcats();
            foreach ($concats as $concat) {
                $return .= $concat->asSQLStatement($this->getAr());
                if ($concat !== end($concats)) {
                    $return .= ', ';
                }
            }
        }

        return $return;
    }

    /**
     * @return arConcat[]
     */
    public function getConcats(): array
    {
        return $this->statements;
    }
}
