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

namespace ILIAS\FileDelivery\Token;

use ILIAS\FileDelivery\Token\Compression\GZipCompression;
use ILIAS\FileDelivery\Delivery\Disposition;
use ILIAS\Filesystem\Stream\FileStream;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 */
final class Request
{
    public function __construct(
        private FileStream $stream,
        private Disposition $disposition,
        private string $file_name,
        private int $valid_for_at_least_hours,
    ) {
    }

    public static function fromStreamAttached(
        FileStream $stream,
        string $file_name,
        int $valid_for_at_least_hours,
    ): self {
        return new self(
            $stream,
            Disposition::ATTACHMENT,
            $file_name,
            $valid_for_at_least_hours
        );
    }

    public static function fromStreamInline(
        FileStream $stream,
        string $file_name,
        int $valid_for_at_least_hours,
    ): self {
        return new self(
            $stream,
            Disposition::INLINE,
            $file_name,
            $valid_for_at_least_hours
        );
    }
}
