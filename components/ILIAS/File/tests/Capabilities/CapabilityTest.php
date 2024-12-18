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

use ILIAS\File\Capabilities\CapabilityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use ILIAS\components\WOPI\Discovery\ActionRepository;
use ILIAS\HTTP\Services;
use ILIAS\StaticURL\Builder\URIBuilder;
use PHPUnit\Framework\TestCase;
use ILIAS\File\Capabilities\Permissions;
use ILIAS\File\Capabilities\Capabilities;
use ILIAS\File\Capabilities\TypeResolver;

class CapabilityTest extends TestCase
{
    public \PHPUnit\Framework\MockObject\MockObject|TypeResolver $type_resolver;
    private ilObjFileInfoRepository|MockObject $file_info_repository;
    private ilAccessHandler|MockObject $access;
    private ilCtrlInterface|MockObject $ctrl;
    private ActionRepository|MockObject $action_repository;
    private Services|MockObject $http;
    private URIBuilder|MockObject $static_url;
    private CapabilityBuilder $capability_builder;

    protected function setUp(): void
    {
        if (!defined('ILIAS_HTTP_PATH')) {
            define('ILIAS_HTTP_PATH', 'https://ilias.unit.test');
        }

        $this->file_info_repository = $this->createMock(\ilObjFileInfoRepository::class);
        $this->access = $this->createMock(\ilAccessHandler::class);
        $this->ctrl = $this->createMock(\ilCtrlInterface::class);
        $this->action_repository = $this->createMock(ActionRepository::class);
        $this->http = $this->createMock(Services::class);
        $this->static_url = $this->createMock(URIBuilder::class);
        $this->type_resolver = $this->createMock(TypeResolver::class);

        $this->type_resolver->method('resolveType')
                            ->withAnyParameters()
                            ->willReturn('file');

        $this->capability_builder = new CapabilityBuilder(
            $this->file_info_repository,
            $this->access,
            $this->ctrl,
            $this->action_repository,
            $this->http,
            $this->type_resolver,
            $this->static_url
        );
    }

    protected function tearDown(): void
    {
    }

    public static function environmentProvider(): array
    {
        return [
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => true,
                'user_permissions' => [
                    Permissions::READ,
                    Permissions::WRITE,
                    Permissions::VISIBLE,
                    Permissions::EDIT_CONTENT,
                    Permissions::VIEW_CONTENT
                ],
                'expected_best' => Capabilities::FORCED_INFO_PAGE
            ],
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => false,
                'user_permissions' => [
                    Permissions::READ,
                    Permissions::WRITE,
                    Permissions::VISIBLE,
                    Permissions::EDIT_CONTENT,
                    Permissions::VIEW_CONTENT
                ],
                'expected_best' => Capabilities::VIEW_EXTERNAL
            ],
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => false,
                'user_permissions' => [
                    Permissions::EDIT_CONTENT,
                    Permissions::VIEW_CONTENT
                ],
                'expected_best' => Capabilities::VIEW_EXTERNAL
            ],
            [
                'wopi_view' => false,
                'wopi_edit' => false,
                'infopage_first' => true,
                'user_permissions' => [
                    Permissions::READ,
                    Permissions::VISIBLE
                ],
                'expected_best' => Capabilities::FORCED_INFO_PAGE
            ],
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => false,
                'user_permissions' => [
                    Permissions::EDIT_CONTENT,
                ],
                'expected_best' => Capabilities::EDIT_EXTERNAL
            ],
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => false,
                'user_permissions' => [
                    Permissions::READ,
                ],
                'expected_best' => Capabilities::DOWNLOAD
            ],
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => false,
                'user_permissions' => [
                    Permissions::WRITE,
                    Permissions::READ,
                ],
                'expected_best' => Capabilities::DOWNLOAD
            ],
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => false,
                'user_permissions' => [
                    Permissions::WRITE,
                ],
                'expected_best' => Capabilities::MANAGE_VERSIONS
            ],
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => false,
                'user_permissions' => [
                    Permissions::VISIBLE,
                ],
                'expected_best' => Capabilities::INFO_PAGE
            ],
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => true,
                'user_permissions' => [
                    Permissions::WRITE,
                    Permissions::READ,
                ],
                'expected_best' => Capabilities::FORCED_INFO_PAGE
            ],
            [
                'wopi_view' => true,
                'wopi_edit' => true,
                'infopage_first' => false,
                'user_permissions' => [
                    Permissions::NONE,
                ],
                'expected_best' => Capabilities::NONE
            ],
        ];
    }

    #[DataProvider('environmentProvider')]
    public function testCapabilityPriority(
        bool $wopi_view,
        bool $wopi_edit,
        bool $infopage_first,
        array $permissions,
        Capabilities $expected_best
    ): void {
        $ref_id = 42;

        $this->access->method('checkAccess')
                     ->willReturnCallback(
                         function (string $permission) use ($permissions): bool {
                             $checked_permissions = explode(',', $permission);
                             $common_permissions = array_intersect(
                                 array_map(static fn(Permissions $p): string => $p->value, $permissions),
                                 $checked_permissions
                             );
                             return $common_permissions !== [];
                         }
                     );

        $file_info = $this->createMock(\ilObjFileInfo::class);
        $file_info->method('shouldDownloadDirectly')
                  ->willReturn(!$infopage_first);

        $this->file_info_repository->method('getByRefId')
                                   ->with($ref_id)
                                   ->willReturn($file_info);

        $this->action_repository->method('hasEditActionForSuffix')
                                ->willReturn($wopi_edit);

        $this->action_repository->method('hasViewActionForSuffix')
                                ->willReturn($wopi_view);

        $capabilities = $this->capability_builder->get($ref_id);
        $best = $capabilities->getBest();

        $this->assertEquals($expected_best, $best->getCapability());
    }

}
