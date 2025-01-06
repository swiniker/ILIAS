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
use ilBadgeHandler;
use ilBadgeAuto;

class ilBadgeTypesTableGUI
{
    private readonly Factory $factory;
    private readonly Renderer $renderer;
    private readonly \ILIAS\Refinery\Factory $refinery;
    private readonly ServerRequestInterface|RequestInterface $request;
    private readonly Services $http;
    private readonly ilLanguage $lng;
    private readonly ilGlobalTemplateInterface $tpl;

    public function __construct(protected bool $a_has_write = false)
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->lng->loadLanguageModule('cmps');
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
             * @return list<array{"id": string, "comp": string, "name": string, "manual": bool, "active": bool, "activity": bool}>
             */
            private function getBadgeImageTemplates(): array
            {
                $rows = [];
                $handler = ilBadgeHandler::getInstance();
                $inactive = $handler->getInactiveTypes();

                foreach ($handler->getComponents() as $component) {
                    $provider = $handler->getProviderInstance($component);
                    if ($provider) {
                        foreach ($provider->getBadgeTypes() as $badge_obj) {
                            $id = $handler->getUniqueTypeId($component, $badge_obj);

                            $rows[] = [
                                'id' => $id,
                                'comp' => $handler->getComponentCaption($component),
                                'name' => $badge_obj->getCaption(),
                                'manual' => !$badge_obj instanceof ilBadgeAuto,
                                'active' => !\in_array($id, $inactive, true),
                                'activity' => \in_array('bdga', $badge_obj->getValidObjectTypes(), true)
                            ];
                        }
                    }
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
             * @return list<array{"id": string, "comp": string, "name": string, "manual": bool, "active": bool, "activity": bool}>
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
                            if (\in_array($order_field, ['name', 'comp'], true)) {
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
        URLBuilderToken $row_id_token
    ): array {
        $f = $this->factory;
        if ($this->a_has_write) {
            return [
                'badge_type_activate' => $f->table()->action()->multi(
                    $this->lng->txt('activate'),
                    $url_builder->withParameter($action_parameter_token, 'badge_type_activate'),
                    $row_id_token
                ),
                'badge_type_deactivate' =>
                    $f->table()->action()->multi(
                        $this->lng->txt('deactivate'),
                        $url_builder->withParameter($action_parameter_token, 'badge_type_deactivate'),
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
        $refinery = $this->refinery;
        $request = $this->request;
        $df = new \ILIAS\Data\Factory();

        $badge_manual_txt = $this->lng->txt('badge_manual') . ': ';
        $badge_activity_txt = $this->lng->txt('badge_activity_badges') . ': ';
        $active_txt = $this->lng->txt('active') . ': ';
        $columns = [
            'name' => $f->table()->column()->text($this->lng->txt('name')),
            'comp' => $f->table()->column()->text($this->lng->txt('cmps_component')),
            'manual' => $f->table()->column()->boolean(
                $this->lng->txt('badge_manual'),
                $this->lng->txt('yes'),
                $this->lng->txt('no')
            )->withOrderingLabels(
                $badge_manual_txt . $this->lng->txt('no'),
                $badge_manual_txt . $this->lng->txt('yes')
            ),
            'activity' => $f->table()->column()->boolean(
                $this->lng->txt('badge_activity_badges'),
                $this->lng->txt('yes'),
                $this->lng->txt('no')
            )->withOrderingLabels(
                $badge_activity_txt . $this->lng->txt('no'),
                $badge_activity_txt . $this->lng->txt('yes')
            ),
            'active' => $f->table()->column()->boolean(
                $this->lng->txt('active'),
                $this->lng->txt('yes'),
                $this->lng->txt('no')
            )->withOrderingLabels(
                $active_txt . $this->lng->txt('no'),
                $active_txt . $this->lng->txt('yes')
            ),
        ];

        $table_uri = $df->uri($request->getUri()->__toString());
        $url_builder = new URLBuilder($table_uri);
        $query_params_namespace = ['tid'];

        [$url_builder, $action_parameter_token, $row_id_token] =
            $url_builder->acquireParameters(
                $query_params_namespace,
                'table_action',
                'id',
            );

        $actions = $this->getActions($url_builder, $action_parameter_token, $row_id_token);
        $data_retrieval = $this->buildDataRetrievalObject($f, $r);

        $table = $f->table()
                   ->data($this->lng->txt('badge_types'), $columns, $data_retrieval)
                   ->withId(self::class)
                   ->withOrder(new Order('name', Order::ASC))
                   ->withActions($actions)
                   ->withRequest($request);

        $out = [$table];

        $query = $this->http->wrapper()->query();
        if ($query->has($action_parameter_token->getName())) {
            $action = $query->retrieve($action_parameter_token->getName(), $refinery->to()->string());
            $ids = $query->retrieve($row_id_token->getName(), $refinery->custom()->transformation(fn($v) => $v));
            $listing = $f->listing()->characteristicValue()->text([
                'table_action' => $action,
                'id' => print_r($ids, true),
            ]);

            $out[] = $f->divider()->horizontal();
            $out[] = $listing;
        }

        $this->tpl->setContent($r->render($out));
    }
}
