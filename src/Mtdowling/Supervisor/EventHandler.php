<?php namespace Mtdowling\Supervisor;

interface EventHandler
{
    /**
     * Should return TRUE if it was successful, FALSE on failure,
     * or 'quit' to break from the event listener loop.
     *
     * @param EventNotification $event
     * @param EventListener     $eventListener
     *
     * @return mixed
     */
    public function handleEvent(EventNotification $event, EventListener $eventListener);

    public function getEventSubscriptions();
}
