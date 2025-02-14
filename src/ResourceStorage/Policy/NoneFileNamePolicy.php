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

namespace ILIAS\ResourceStorage\Policy;

/**
 * Class NoneFileNamePolicy
 * @author Fabian Schmid <fabian@sr.solutions.ch>
 * @internal
 */
class NoneFileNamePolicy implements FileNamePolicy
{
    public function check(string $extension): bool
    {
        return true;
    }

    public function isValidExtension(string $extension): bool
    {
        return true;
    }

    public function isBlockedExtension(string $extension): bool
    {
        return true;
    }

    public function prepareFileNameForConsumer(string $filename_with_extension): string
    {
        return $filename_with_extension;
    }
}
