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

use ILIAS\UI\Factory;
use ILIAS\UI\URLBuilder;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\UI\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\RequestInterface;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\URLBuilderToken;
use ILIAS\Data\URI;
use ILIAS\UI\Implementation\Component\Link\Standard;
use ILIAS\Badge\ilBadgeImage;
use ILIAS\Badge\PresentationHeader;
use ILIAS\Badge\Tile;

class ilBadgePersonalTableGUI
{
    private readonly Factory $factory;
    private readonly Renderer $renderer;
    private readonly \ILIAS\Refinery\Factory $refinery;
    private readonly ServerRequestInterface|RequestInterface $request;
    private readonly ilLanguage $lng;
    private readonly ilGlobalTemplateInterface $tpl;
    private readonly ILIAS\DI\Container $dic;
    private readonly ilObjUser $user;
    private readonly ilAccessHandler $access;

    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->refinery = $DIC->refinery();
        $this->request = $DIC->http()->request();
        $this->user = $DIC->user();
        $this->access = $DIC->access();
    }

    protected function buildDataRetrievalObject(
        Factory $f,
        Renderer $r,
        ilObjUser $user,
        ilAccessHandler $access
    ): DataRetrieval {
        return new class ($f, $r, $user, $access) implements DataRetrieval {
            private readonly Tile $tile;

            public function __construct(
                private readonly Factory $ui_factory,
                private readonly Renderer $ui_renderer,
                private readonly ilObjUser $user,
                private readonly ilAccess $access
            ) {
                global $DIC;

                $this->tile = new Tile($DIC);
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
                return count($this->getRecords());
            }

            /**
             * @return list<array{
             *     id: int,
             *     active: bool,
             *     image: string,
             *     awarded_by: string,
             *     awarded_by_sortable: string,
             *     badge_issued_on: DateTimeImmutable,
             *     title: string,
             *     title_sortable: string
             *  }>
             */
            private function getRecords(Range $range = null, Order $order = null): array
            {
                $rows = [];
                $a_user_id = $this->user->getId();

                foreach (ilBadgeAssignment::getInstancesByUserId($a_user_id) as $ass) {
                    $badge = new ilBadge($ass->getBadgeId());

                    $parent = null;
                    if ($badge->getParentId()) {
                        $parent = $badge->getParentMeta();
                        if ($parent['type'] === 'bdga') {
                            $parent = null;
                        }
                    }

                    $awarded_by = '';
                    $awarded_by_sortable = '';
                    if ($parent !== null) {
                        $ref_ids = ilObject::_getAllReferences($parent['id']);
                        $ref_id = current($ref_ids);

                        $awarded_by = $parent['title'];
                        $awarded_by_sortable = $parent['title'];
                        if ($ref_id && $this->access->checkAccess('read', '', $ref_id)) {
                            $awarded_by = $this->ui_renderer->render(
                                new Standard(
                                    $awarded_by,
                                    (string) new URI(ilLink::_getLink($ref_id))
                                )
                            );
                        }

                        $awarded_by = implode(' ', [
                            $this->ui_renderer->render($this->ui_factory->symbol()->icon()->standard(
                                $parent['type'],
                                $parent['title']
                            )),
                            $awarded_by
                        ]);
                    }

                    $rows[] = [
                        'id' => $badge->getId(),
                        'image' => $this->ui_renderer->render(
                            $this->tile->asImage(
                                $this->tile->modalContentWithAssignment($badge, $ass),
                                ilBadgeImage::IMAGE_SIZE_XS
                            )
                        ),
                        'title' => $this->ui_renderer->render(
                            $this->tile->asTitle(
                                $this->tile->modalContentWithAssignment($badge, $ass)
                            )
                        ),
                        'title_sortable' => $badge->getTitle(),
                        'badge_issued_on' => (new DateTimeImmutable())
                            ->setTimestamp($ass->getTimestamp())
                            ->setTimezone(new DateTimeZone($this->user->getTimeZone())),
                        'awarded_by' => $awarded_by,
                        'awarded_by_sortable' => $awarded_by_sortable,
                        'active' => (bool) $ass->getPosition()
                    ];
                }

                if ($order) {
                    [$order_field, $order_direction] = $order->join(
                        [],
                        fn($ret, $key, $value) => [$key, $value]
                    );
                    usort(
                        $rows,
                        static function (array $left, array $right) use ($order_field): int {
                            if (in_array($order_field, ['title', 'awarded_by'], true)) {
                                if (in_array($order_field, ['title', 'awarded_by'], true)) {
                                    $order_field .= '_sortable';
                                }

                                return ilStr::strCmp(
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

                return $rows;
            }
        };
    }

    /**
     * @return array<string,\ILIAS\UI\Component\Table\Action\Action>
     */
    protected function getActions(
        URLBuilder $url_builder,
        URLBuilderToken $action_parameter_token,
        URLBuilderToken $row_id_token
    ): array {
        $f = $this->factory;

        return [
            'obj_badge_activate' => $f->table()->action()->multi(
                $this->lng->txt('activate'),
                $url_builder->withParameter($action_parameter_token, 'obj_badge_activate'),
                $row_id_token
            ),
            'obj_badge_deactivate' =>
                $f->table()->action()->multi(
                    $this->lng->txt('deactivate'),
                    $url_builder->withParameter($action_parameter_token, 'obj_badge_deactivate'),
                    $row_id_token
                )
        ];
    }

    public function renderTable(): void
    {
        $f = $this->factory;
        $r = $this->renderer;
        $request = $this->request;

        $df = new \ILIAS\Data\Factory();
        if ((int) $this->user->getTimeFormat() === ilCalendarSettings::TIME_FORMAT_12) {
            $date_format = $df->dateFormat()->withTime12($this->user->getDateFormat());
        } else {
            $date_format = $df->dateFormat()->withTime24($this->user->getDateFormat());
        }

        $columns = [
            'image' => $f->table()->column()->text($this->lng->txt('image'))->withIsSortable(false),
            'title' => $f->table()->column()->text($this->lng->txt('title')),
            'awarded_by' => $f->table()->column()->text($this->lng->txt('awarded_by')),
            'badge_issued_on' => $f->table()->column()->date($this->lng->txt('badge_issued_on'), $date_format),
            'active' => $f->table()->column()->boolean(
                $this->lng->txt('badge_in_profile'),
                $this->lng->txt('yes'),
                $this->lng->txt('no')
            ),
        ];

        $table_uri = $df->uri($request->getUri()->__toString());
        $url_builder = new URLBuilder($table_uri);
        $query_params_namespace = ['badge'];

        [$url_builder, $action_parameter_token, $row_id_token] =
            $url_builder->acquireParameters(
                $query_params_namespace,
                'table_action',
                'id'
            );

        $data_retrieval = $this->buildDataRetrievalObject($f, $r, $this->user, $this->access);

        $actions = $this->getActions($url_builder, $action_parameter_token, $row_id_token);

        $table = $f->table()
                   ->data($this->lng->txt('badge_personal_badges'), $columns, $data_retrieval)
                   ->withId(self::class)
                   ->withOrder(new Order('title', Order::ASC))
                   ->withActions($actions)
                   ->withRequest($request);

        $pres = new PresentationHeader($this->dic, ilBadgeProfileGUI::class);
        $pres->show($this->lng->txt('table_view'));
        $out = [$table];
        $this->tpl->setContent($r->render($out));
    }
}
