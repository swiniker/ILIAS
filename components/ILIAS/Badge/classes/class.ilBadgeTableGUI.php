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
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Badge;

use ILIAS\UI\Factory;
use ILIAS\UI\URLBuilder;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ilLanguage;
use ilGlobalTemplateInterface;
use ILIAS\UI\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\HTTP\Services;
use Psr\Http\Message\RequestInterface;
use ILIAS\UI\Component\Table\DataRowBuilder;
use Generator;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\URLBuilderToken;
use ilBadge;
use ilBadgeAuto;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\UI\Component\Table\Column\Column;

class ilBadgeTableGUI
{
    private readonly Factory $factory;
    private readonly Renderer $renderer;
    private readonly \ILIAS\Refinery\Factory $refinery;
    private readonly ServerRequestInterface|RequestInterface $request;
    private readonly Services $http;
    private readonly int $parent_id;
    private readonly string $parent_type;
    private readonly ilLanguage $lng;
    private readonly ilGlobalTemplateInterface $tpl;

    public function __construct(int $parent_obj_id, string $parent_obj_type, protected bool $has_write = false)
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->refinery = $DIC->refinery();
        $this->request = $DIC->http()->request();
        $this->http = $DIC->http();

        $this->parent_id = $parent_obj_id;
        $this->parent_type = $parent_obj_type;
    }

    /**
     * @return array<string, Column>
     */
    private function buildColumns(): array
    {
        $column = $this->factory->table()->column();
        $lng = $this->lng;

        return [
            'image' => $column->text($lng->txt('image')),
            'title' => $column->text($lng->txt('title')),
            'type' => $column->text($lng->txt('type')),
            'active' => $column->boolean($lng->txt('active'), $lng->txt('yes'), $lng->txt('no')),
        ];
    }

    private function buildDataRetrievalObject(Factory $f, Renderer $r, int $p, string $type): DataRetrieval
    {
        return new class ($f, $r, $p, $type) implements DataRetrieval {
            private ilBadgeImage $badge_image_service;

            public function __construct(
                private readonly Factory $ui_factory,
                private readonly Renderer $ui_renderer,
                private readonly int $parent_id,
                private readonly string $parent_type
            ) {
                global $DIC;
                $this->badge_image_service = new ilBadgeImage(
                    $DIC->resourceStorage(),
                    $DIC->upload(),
                    $DIC->ui()->mainTemplate()
                );
            }

            /**
             * @return list<array{
             *     id: int,
             *     badge: ilBadge,
             *     active: bool,
             *     type: string,
             *     manual: bool,
             *     image: string,
             *     image_sortable: string,
             *     title: string,
             *     title_sortable: string
             * }>
             */
            private function getBadges(): array
            {
                $rows = [];
                $modal_container = new ModalBuilder();

                foreach (ilBadge::getInstancesByParentId($this->parent_id) as $badge) {
                    $images = [
                        'rendered' => null,
                        'large' => null,
                    ];
                    $image_src = $this->badge_image_service->getImageFromBadge($badge);
                    if ($image_src !== '') {
                        $images['rendered'] = $this->ui_renderer->render(
                            $this->ui_factory->image()->responsive(
                                $image_src,
                                $badge->getTitle()
                            )
                        );

                        $image_src_large = $this->badge_image_service->getImageFromBadge(
                            $badge,
                            ilBadgeImage::IMAGE_SIZE_XL
                        );
                        if ($image_src_large !== '') {
                            $images['large'] = $this->ui_factory->image()->responsive(
                                $image_src_large,
                                $badge->getTitle()
                            );
                        }
                    }

                    $modal = $modal_container->constructModal(
                        $images['large'],
                        $badge->getTitle(),
                        [
                            'description' => $badge->getDescription(),
                            'badge_criteria' => $badge->getCriteria(),
                        ]
                    );

                    $rows[] = [
                        'id' => $badge->getId(),
                        'badge' => $badge,
                        'active' => $badge->isActive(),
                        'type' => $this->parent_type !== 'bdga'
                            ? ilBadge::getExtendedTypeCaption($badge->getTypeInstance())
                            : $badge->getTypeInstance()->getCaption(),
                        'manual' => !$badge->getTypeInstance() instanceof ilBadgeAuto,
                        'image' => $images['rendered'] ? ($modal_container->renderShyButton(
                            $images['rendered'],
                            $modal
                        ) . ' ') : '',
                        // Just an boolean-like indicator for sorting
                        'image_sortable' => $images['rendered'] ? 'A' . $badge->getId() : 'Z' . $badge->getId(),
                        'title' => implode('', [
                            $modal_container->renderShyButton($badge->getTitle(), $modal),
                            $modal_container->renderModal($modal)
                        ]),
                        'title_sortable' => $badge->getTitle()
                    ];
                }

                return $rows;
            }

            public function getRows(
                DataRowBuilder $row_builder,
                array $visible_column_ids,
                Range $range,
                Order $order,
                ?array $filter_data,
                ?array $additional_parameters
            ): Generator {
                $records = $this->getRecords($range, $order);
                foreach ($records as $record) {
                    $row_id = (string) $record['id'];
                    yield $row_builder->buildDataRow($row_id, $record)
                                      ->withDisabledAction(
                                          'award_revoke_badge',
                                          !$record['manual'] || !$record['active']
                                      );
                }
            }

            public function getTotalRowCount(
                ?array $filter_data,
                ?array $additional_parameters
            ): ?int {
                return \count($this->getRecords());
            }

            /**
             * @return list<array{
             *     id: int,
             *     badge: ilBadge,
             *     active: bool,
             *     type: string,
             *     manual: bool,
             *     image: string,
             *     image_sortable: string,
             *     title: string,
             *     title_sortable: string}>
             */
            private function getRecords(Range $range = null, Order $order = null): array
            {
                $rows = $this->getBadges();

                if ($order) {
                    [$order_field, $order_direction] = $order->join(
                        [],
                        fn($ret, $key, $value) => [$key, $value]
                    );
                    usort(
                        $rows,
                        static function (array $left, array $right) use ($order_field): int {
                            if (\in_array($order_field, ['title', 'type', 'image'], true)) {
                                if (\in_array($order_field, ['title', 'image'], true)) {
                                    $order_field .= '_sortable';
                                }

                                return \ilStr::strCmp(
                                    $left[$order_field],
                                    $right[$order_field]
                                );
                            }

                            return $left[$order_field] <=> $right[$order_field];
                        }
                    );
                    if ($order_direction === Order::DESC) {
                        $rows = array_reverse($rows);
                    }
                }

                if ($range) {
                    $rows = \array_slice($rows, $range->getStart(), $range->getLength());
                }

                return $rows;
            }
        };
    }

    /**
     * @return array<string, \ILIAS\UI\Component\Table\Action\Action>
     */
    private function getActions(
        URLBuilder $url_builder,
        URLBuilderToken $action_parameter_token,
        URLBuilderToken $row_id_token,
    ): array {
        $f = $this->factory;

        if ($this->has_write) {
            return [
                'badge_table_activate' =>
                    $f->table()->action()->multi(
                        $this->lng->txt('activate'),
                        $url_builder->withParameter($action_parameter_token, 'badge_table_activate'),
                        $row_id_token
                    ),
                'badge_table_deactivate' =>
                    $f->table()->action()->multi(
                        $this->lng->txt('deactivate'),
                        $url_builder->withParameter($action_parameter_token, 'badge_table_deactivate'),
                        $row_id_token
                    ),
                'badge_table_edit' => $f->table()->action()->single(
                    $this->lng->txt('edit'),
                    $url_builder->withParameter($action_parameter_token, 'badge_table_edit'),
                    $row_id_token
                ),
                'badge_table_delete' =>
                    $f->table()->action()->standard(
                        $this->lng->txt('delete'),
                        $url_builder->withParameter($action_parameter_token, 'badge_table_delete'),
                        $row_id_token
                    ),
                'award_revoke_badge' =>
                    $f->table()->action()->single(
                        $this->lng->txt('badge_award_revoke'),
                        $url_builder->withParameter($action_parameter_token, 'award_revoke_badge'),
                        $row_id_token
                    )
            ];
        } else {
            return [];
        }

    }

    public function renderTable(): void
    {
        $f = $this->factory;
        $r = $this->renderer;
        $df = new \ILIAS\Data\Factory();

        $refinery = $this->refinery;
        $request = $this->request;

        $columns = $this->buildColumns();
        $table_uri = $df->uri($request->getUri()->__toString());
        $url_builder = new URLBuilder($table_uri);
        $query_params_namespace = ['tid'];

        [
            $url_builder,
            $action_parameter_token,
            $row_id_token
        ] =
            $url_builder->acquireParameters(
                $query_params_namespace,
                'table_action',
                'id'
            );

        $data_retrieval = $this->buildDataRetrievalObject($f, $r, $this->parent_id, $this->parent_type);
        $actions = $this->getActions($url_builder, $action_parameter_token, $row_id_token);
        $table = $f->table()
                   ->data($this->lng->txt('obj_bdga'), $columns, $data_retrieval)
                   ->withId(self::class . '_' . $this->parent_id)
                   ->withOrder(new Order('title', Order::ASC))
                   ->withActions($actions)
                   ->withRequest($request);
        $out = [$table];

        $query = $this->http->wrapper()->query();

        if ($query->has($action_parameter_token->getName())) {
            $action = $query->retrieve($action_parameter_token->getName(), $refinery->to()->string());
            $ids = $query->retrieve($row_id_token->getName(), $refinery->custom()->transformation(fn($v) => $v));

            if ($action === 'delete') {
                $items = [];
                foreach ($ids as $id) {
                    $items[] = $f->modal()->interruptiveItem()->keyValue($id, $row_id_token->getName(), $id);
                }

                $this->http->saveResponse(
                    $this->http
                        ->response()
                        ->withBody(
                            Streams::ofString($r->renderAsync([
                                $f->modal()->interruptive(
                                    $this->lng->txt('badge_deletion'),
                                    $this->lng->txt('badge_deletion_confirmation'),
                                    '#'
                                )->withAffectedItems($items)
                            ]))
                        )
                );
                $this->http->sendResponse();
                $this->http->close();
            }
        }

        $this->tpl->setContent($r->render($out));
    }
}
