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
use Psr\Http\Message\RequestInterface;
use ILIAS\UI\Component\Table\DataRowBuilder;
use Generator;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\DI\Container;
use ilBadgeHandler;
use ilObject;
use ilBadge;
use ilBadgeAssignment;
use ilUserQuery;
use DateTimeImmutable;
use ILIAS\UI\URLBuilderToken;
use ilObjectDataDeletionLog;

class ilBadgeUserTableGUI
{
    private readonly Factory $factory;
    private readonly Renderer $renderer;
    private readonly ServerRequestInterface|RequestInterface $request;
    private readonly int $parent_ref_id;
    private readonly ilLanguage $lng;
    private readonly ilGlobalTemplateInterface $tpl;

    public function __construct(
        int $parent_ref_id,
        private readonly ?ilBadge $award_badge = null,
        private readonly int $restrict_badge_id = 0
    ) {
        global $DIC;

        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->request = $DIC->http()->request();
        $this->parent_ref_id = $parent_ref_id;
    }

    private function buildDataRetrievalObject(
        Factory $f,
        Renderer $r,
        int $parent_ref_id,
        ?ilBadge $award_badge = null
    ): DataRetrieval {
        return new class ($f, $r, $parent_ref_id, $award_badge) implements DataRetrieval {
            public function __construct(
                private Factory $ui_factory,
                private Renderer $ui_renderer,
                private int $parent_ref_id,
                private ?ilBadge $award_badge = null
            ) {
            }

            /**
             * @return list<array{
             *     user_id: string,
             *     name: string,
             *     login: string,
             *     type: string,
             *     title: string,
             *     issued: int,
             *     parent_id: string,
             *     parent_meta: array}>
             */
            private function getBadgeImageTemplates(Container $DIC): array
            {
                $assignments = null;
                $user_ids = null;
                $parent_ref_id = $this->parent_ref_id;
                $data = [];
                $badges = [];
                $tree = $DIC->repositoryTree();
                $restrict_badge_id = 0;

                $a_parent_obj_id = ilObject::_lookupObjId($parent_ref_id);

                if ($parent_ref_id) {
                    $user_ids = ilBadgeHandler::getInstance()->getUserIds($parent_ref_id, $a_parent_obj_id);
                }

                $obj_ids = [$a_parent_obj_id];

                foreach ($tree->getSubTree($tree->getNodeData($parent_ref_id)) as $node) {
                    $obj_ids[] = (int) $node['obj_id'];
                }

                foreach ($obj_ids as $obj_id) {
                    foreach (ilBadge::getInstancesByParentId($obj_id) as $badge) {
                        $badges[$badge->getId()] = $badge;
                    }
                    foreach (ilBadgeAssignment::getInstancesByParentId($obj_id) as $ass) {
                        if ($restrict_badge_id &&
                            $restrict_badge_id !== $ass->getBadgeId()) {
                            continue;
                        }
                        if ($this->award_badge &&
                            $ass->getBadgeId() !== $this->award_badge->getId()) {
                            continue;
                        }

                        $assignments[$ass->getUserId()][] = $ass;
                    }
                }
                if (!$user_ids && $assignments !== null) {
                    $user_ids = array_keys($assignments);
                }

                $tmp['set'] = [];
                if (\count($user_ids) > 0) {
                    $uquery = new ilUserQuery();
                    $uquery->setLimit(9999);
                    $uquery->setUserFilter($user_ids);
                    $tmp = $uquery->query();
                }
                foreach ($tmp['set'] as $user) {
                    if (\is_array($assignments) && \array_key_exists($user['usr_id'], $assignments)) {
                        foreach ($assignments[$user['usr_id']] as $user_ass) {
                            $idx = $user_ass->getBadgeId() . '-' . $user['usr_id'];
                            $badge = $badges[$user_ass->getBadgeId()];
                            $parent = $badge->getParentMeta();
                            $timestamp = $user_ass->getTimestamp();
                            $immutable = new DateTimeImmutable();
                            $user_id = $user['usr_id'];
                            $name = $user['lastname'] . ', ' . $user['firstname'];
                            $login = $user['login'];
                            $type = ilBadge::getExtendedTypeCaption($badge->getTypeInstance());
                            $title = $badge->getTitle();
                            $issued = $immutable->setTimestamp($timestamp);
                            $parent_id = $parent['id'];
                            $data[$idx] = [
                                'id' => $user_id,
                                'user_id' => $user_id,
                                'name' => $name,
                                'login' => $login,
                                'type' => $type,
                                'title' => $title,
                                'issued' => $issued,
                                'parent_id' => $parent_id,
                                'parent_meta' => $parent
                            ];
                        }
                    } elseif ($this->award_badge) {
                        $idx = '0-' . $user['usr_id'];
                        $user_id = $user['usr_id'];
                        $name = $user['lastname'] . ', ' . $user['firstname'];
                        $login = $user['login'];
                        $data[$idx] = [
                            'id' => $user_id,
                            'user_id' => $user_id,
                            'name' => $name,
                            'login' => $login,
                            'type' => '',
                            'title' => '',
                            'parent_id' => ''
                        ];
                    }
                }

                return $data;
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
                    if ($this->award_badge !== null) {
                        $row_id = $record['id'] . '_' . $this->award_badge->getId();
                    }

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
             * @return list<array{
             *     user_id: string,
             *     name: string,
             *     login: string,
             *     type: string,
             *     title: string,
             *     issued: int,
             *     parent_id: string,
             *     parent_meta: array}>
             */
            private function getRecords(Range $range = null, Order $order = null): array
            {
                global $DIC;

                $rows = $this->getBadgeImageTemplates($DIC);

                if ($order) {
                    [$order_field, $order_direction] = $order->join(
                        [],
                        fn($ret, $key, $value) => [$key, $value]
                    );
                    usort(
                        $rows,
                        static function (array $left, array $right) use ($order_field): int {
                            if ($order_field === 'title') {
                                return \ilStr::strCmp(
                                    $left[$order_field],
                                    $right[$order_field ]
                                );
                            }

                            return $left[$order_field] <=> $right[$order_field];
                        }
                    );
                    if ($order_direction === ORDER::DESC) {
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
        if ($this->award_badge) {
            return [
                'badge_award_badge' =>
                    $f->table()->action()->multi(
                        $this->lng->txt('badge_award_badge'),
                        $url_builder->withParameter($action_parameter_token, 'assignBadge'),
                        $row_id_token
                    ),
                'badge_revoke_badge' =>
                    $f->table()->action()->multi(
                        $this->lng->txt('badge_remove_badge'),
                        $url_builder->withParameter($action_parameter_token, 'revokeBadge'),
                        $row_id_token
                    ),
            ];
        }

        return [];
    }

    public function renderTable(): void
    {
        $f = $this->factory;
        $r = $this->renderer;
        $request = $this->request;
        $df = new \ILIAS\Data\Factory();

        $columns = [
            'name' => $f->table()->column()->text($this->lng->txt('name')),
            'login' => $f->table()->column()->text($this->lng->txt('login')),
            'type' => $f->table()->column()->text($this->lng->txt('type')),
            'title' => $f->table()->column()->text($this->lng->txt('title')),
            'issued' => $f->table()->column()->date(
                $this->lng->txt('badge_issued_on'),
                $df->dateFormat()->germanShort()
            )
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

        $data_retrieval = $this->buildDataRetrievalObject($f, $r, $this->parent_ref_id, $this->award_badge);
        $actions = $this->getActions($url_builder, $action_parameter_token, $row_id_token);

        if ($this->award_badge) {
            $title = $this->lng->txt('badge_award_badge') . ': ' . $this->award_badge->getTitle();
        } else {
            $parent = '';
            $parent_obj_id = ilObject::_lookupObjId($this->parent_ref_id);
            if ($parent_obj_id) {
                $title = ilObject::_lookupTitle($parent_obj_id);
                if (!$title) {
                    $title = ilObjectDataDeletionLog::get($parent_obj_id);
                    if ($title) {
                        $title = $title['title'];
                    }
                }

                if ($this->restrict_badge_id) {
                    $badge = new ilBadge($this->restrict_badge_id);
                    $title .= ' - ' . $badge->getTitle();
                }

                $parent = $title . ': ';
            }
            $title = $parent . $this->lng->txt('users');
        }
        $table = $f->table()
                   ->data($title, $columns, $data_retrieval)
                   ->withId(self::class)
                   ->withOrder(new Order('name', Order::ASC))
                   ->withActions($actions)
                   ->withRequest($request);

        $out = [$table];

        $this->tpl->setContent($r->render($out));
    }
}
