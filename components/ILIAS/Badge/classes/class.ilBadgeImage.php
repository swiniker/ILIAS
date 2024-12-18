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

namespace ILIAS\Badge;

use ilBadge;
use ILIAS\ResourceStorage\Services;
use ilBadgeFileStakeholder;
use ILIAS\FileUpload\FileUpload;
use ILIAS\FileUpload\Exception\IllegalStateException;
use ilGlobalTemplateInterface;

class ilBadgeImage
{
    public const IMAGE_SIZE_XS = 4;
    public const IMAGE_SIZE_S = 3;
    public const IMAGE_SIZE_M = 2;
    public const IMAGE_SIZE_L = 1;
    public const IMAGE_SIZE_XL = 0;
    public const IMAGE_URL_COUNT = 5;

    private ?Services $resource_storage;
    private ?FileUpload $upload_service;
    private ?ilGlobalTemplateInterface $main_template;

    public function __construct(
        Services $resourceStorage,
        FileUpload $uploadService,
        ilGlobalTemplateInterface $main_template
    ) {
        $this->resource_storage = $resourceStorage;
        $this->upload_service = $uploadService;
        $this->main_template = $main_template;
    }

    public function getImageFromBadge(ilBadge $badge, int $size = self::IMAGE_SIZE_XS): string
    {
        return $this->getImageFromResourceId($badge, (string) $badge->getImageRid(), $size);
    }

    public function getImageFromResourceId(
        ilBadge|array $badge,
        ?string $image_rid,
        int $size = self::IMAGE_SIZE_XS
    ): string {
        $image_src = '';

        if ($image_rid !== null) {
            $identification = $this->resource_storage->manage()->find($image_rid);
            if ($identification !== null) {
                $flavour = $this->resource_storage->flavours()->get($identification, new \ilBadgePictureDefinition());
                $urls = $this->resource_storage->consume()->flavourUrls($flavour)->getURLsAsArray(false);
                if (\count($urls) === self::IMAGE_URL_COUNT && isset($urls[$size])) {
                    $image_src = $urls[$size];
                }
            }
        } elseif (\is_array($badge) && isset($badge['image'])) {
            $image_src = $badge['image'];
        } elseif ($badge instanceof ilBadge) {
            $image_src = $badge->getImage();
        }

        return $image_src;
    }

    public function processImageUpload(ilBadge $badge): void
    {
        try {
            $array_result = $this->upload_service->getResults();
            $array_result = array_pop($array_result);
            $stakeholder = new ilBadgeFileStakeholder();
            $identification = $this->resource_storage->manage()->upload($array_result, $stakeholder);
            $this->resource_storage->flavours()->ensure($identification, new \ilBadgePictureDefinition());
            $badge->setImageRid($identification->serialize());
            $badge->update();
        } catch (IllegalStateException $e) {
            $this->main_template->setOnScreenMessage('failure', $e->getMessage(), true);
        }
    }
}
