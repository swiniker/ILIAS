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

namespace ILIAS\ResourceStorage\Events;

/**
 * @author       Fabian Schmid <fabian@sr.solutions>
 * @internal
 */
class Subject
{
    /**
     * @var array<Event, Observer[]>
     */
    protected array $observer_groups = [];

    public function __construct()
    {
        $this->initObserverGroup((Event::ALL)->value);
    }

    public function attach(Observer $observer, Event $event): void
    {
        $this->initObserverGroup($event->value);

        $this->observer_groups[$event->value][] = $observer;
    }

    public function detach(Observer $observer, Event $event = Event::ALL): void
    {
        $this->initObserverGroup($event->value);

        foreach ($this->observer_groups[$event->value] as $index => $attached_observer) {
            if ($attached_observer->getId() === $observer->getId()) {
                unset($this->observer_groups[$event->value][$index]);
            }
        }
    }

    public function notify(Event $event, ?Data $data): void
    {
        $this->initObserverGroup($event->value);
        /** @var Observer[] $observers */
        $observers = array_merge(
            $this->observer_groups[(Event::ALL)->value],
            $this->observer_groups[$event->value],
        );

        foreach ($observers as $interested_observer) {
            try {
                $interested_observer->update($event, $data);
            } catch (Throwable $e) {
                // we must catch all exceptions to ensure that all observers are notified.
                // we pass the exception to the observer so that it can handle it.
                // to make sure it doesn't result in another throwable, we catch it here agian.
                try {
                    $interested_observer->updateFailed($e, $event, $data);
                } catch (Throwable $e) {
                    // we can't do anything here.
                }
            }
        }
    }

    protected function initObserverGroup(string $group): void
    {
        if (!isset($this->observer_groups[$group])) {
            $this->observer_groups[$group] = [];
        }
    }
}
