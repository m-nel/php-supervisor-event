<?php namespace Mtdowling\Supervisor;

class CallableEventHandler implements EventHandler
{
    /**
     * @var callable|Closure
     */
    private $callable;

    /**
     * @param callable|Closure $callable
     */
    public function __construct($callable)
    {
        $this->callable = $callable;
    }

    public function handleEvent(EventNotification $event, EventListener $eventListener)
    {
        return $this->callable($eventListener, $event);
    }
}
