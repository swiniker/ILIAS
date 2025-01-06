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
use ilBadgeImageTemplate;
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
use ILIAS\Filesystem\Stream\Streams;

class ilBadgeImageTemplateTableGUI
{
    private readonly Factory $factory;
    private readonly Renderer $renderer;
    private readonly \ILIAS\Refinery\Factory $refinery;
    private readonly ServerRequestInterface|RequestInterface $request;
    private readonly Services $http;
    private readonly ilLanguage $lng;
    private readonly ilGlobalTemplateInterface $tpl;

    public function __construct(protected bool $has_write = false)
    {
        global $DIC;
        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->refinery = $DIC->refinery();
        $this->request = $DIC->http()->request();
        $this->http = $DIC->http();
    }

    private function buildDataRetrievalObject(Factory $f, Renderer $r): DataRetrieval
    {
        return new class ($f, $r) implements DataRetrieval {
            public function __construct(
                private readonly Factory $ui_factory,
                private readonly Renderer $ui_renderer
            ) {
            }

            /**
             * @return list<array{id: int, image: string, title: string, title_sortable: string, image_sortable: string}>
             */
            private function getBadgeImageTemplates(): array
            {
                $modal_container = new ModalBuilder();
                $rows = [];

                foreach (ilBadgeImageTemplate::getInstances() as $template) {
                    $image = '';
                    $title = $template->getTitle();

                    $image_src = $template->getImageFromResourceId($template->getImageRid());
                    if ($image_src !== '') {
                        $image_component = $this->ui_factory->image()->responsive(
                            $image_src,
                            $template->getTitle()
                        );
                        $image_html = $this->ui_renderer->render($image_component);

                        $image_src_large = $template->getImageFromResourceId(
                            $template->getImageRid(),
                            null,
                            ilBadgeImage::IMAGE_SIZE_XL
                        );
                        $large_image_component = $this->ui_factory->image()->responsive(
                            $image_src_large,
                            $template->getTitle()
                        );

                        $modal = $modal_container->constructModal($large_image_component, $template->getTitle());

                        $image = implode('', [
                            $modal_container->renderShyButton($image_html, $modal),
                            $modal_container->renderModal($modal)
                        ]);
                        $title = $modal_container->renderShyButton($template->getTitle(), $modal);
                    }

                    $rows[] = [
                        'id' => $template->getId(),
                        'image' => $image,
                        // Just an boolean-like indicator for sorting
                        'image_sortable' => $image ? 'A' . $template->getId() : 'Z' . $template->getId(),
                        'title' => $title,
                        'title_sortable' => $template->getTitle(),
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
                    yield $row_builder->buildDataRow($row_id, $record);
                }
            }

            public function getTotalRowCount(
                ?array $filter_data,
                ?array $additional_parameters
            ): ?int {
                return \count($this->getRecords());
            }

            /**
             * @return list<array{id: int, image: string, title: string, title_sortable: string, image_sortable: string}>
             */
            private function getRecords(Range $range = null, Order $order = null): array
            {
                $rows = $this->getBadgeImageTemplates();

                if ($order) {
                    [$order_field, $order_direction] = $order->join(
                        [],
                        fn($ret, $key, $value) => [$key, $value]
                    );
                    usort(
                        $rows,
                        static function (array $left, array $right) use ($order_field): int {
                            if (\in_array($order_field, ['image', 'title'], true)) {
                                return \ilStr::strCmp(
                                    $left[$order_field . '_sortable'],
                                    $right[$order_field . '_sortable']
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
        URLBuilderToken $row_id_token
    ): array {
        $f = $this->factory;
        if ($this->has_write) {
            return [
                'badge_image_template_edit' => $f->table()->action()->single(
                    $this->lng->txt('edit'),
                    $url_builder->withParameter($action_parameter_token, 'badge_image_template_editImageTemplate'),
                    $row_id_token
                ),
                'badge_image_template_delete' =>
                    $f->table()->action()->standard(
                        $this->lng->txt('delete'),
                        $url_builder->withParameter($action_parameter_token, 'badge_image_template_delete'),
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
        $request = $this->request;
        $df = new \ILIAS\Data\Factory();

        $columns = [
            'image' => $f->table()->column()->text($this->lng->txt('image')),
            'title' => $f->table()->column()->text($this->lng->txt('title')),
        ];

        $table_uri = $df->uri($request->getUri()->__toString());
        $url_builder = new URLBuilder($table_uri);
        $query_params_namespace = ['tid'];

        [$url_builder, $action_parameter_token, $row_id_token] =
            $url_builder->acquireParameters(
                $query_params_namespace,
                'table_action',
                'id'
            );

        $actions = $this->getActions($url_builder, $action_parameter_token, $row_id_token);

        $data_retrieval = $this->buildDataRetrievalObject($f, $r);

        $table = $f->table()
                   ->data($this->lng->txt('badge_image_templates'), $columns, $data_retrieval)
                   ->withId(self::class)
                   ->withOrder(new Order('title', Order::ASC))
                   ->withActions($actions)
                   ->withRequest($request);

        $out = [$table];
        $query = $this->http->wrapper()->query();
        if ($query->has('tid')) {
            $query_values = $query->retrieve(
                'tid',
                $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->string())
            );

            $items = [];
            if ($query_values === ['ALL_OBJECTS']) {
                foreach (ilBadgeImageTemplate::getInstances() as $template) {
                    if ($template->getId() !== null) {
                        $items[] = $f->modal()->interruptiveItem()->keyValue(
                            (string) $template->getId(),
                            (string) $template->getId(),
                            $template->getTitle()
                        );
                    }
                }
            } elseif (\is_array($query_values)) {
                foreach ($query_values as $id) {
                    $badge = new ilBadgeImageTemplate((int) $id);
                    $items[] = $f->modal()->interruptiveItem()->keyValue(
                        (string) $id,
                        (string) $badge->getId(),
                        $badge->getTitle()
                    );
                }
            } else {
                $badge = new ilBadgeImageTemplate($query_values);
                $items[] = $f->modal()->interruptiveItem()->keyValue(
                    (string) $badge->getId(),
                    (string) $badge->getId(),
                    $badge->getTitle()
                );
            }
            if ($query->has($action_parameter_token->getName())) {
                $action = $query->retrieve($action_parameter_token->getName(), $this->refinery->kindlyTo()->string());
                if ($action === 'badge_image_template_delete') {
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
        }

        $this->tpl->setContent($r->render($out));
    }
}
