<?php

namespace Mtdowling\Supervisor;

/**
 * Handles communication between Supervisord events and an event callback
 *
 * @author Michael Dowling <mtdowling@gmail.com>
 */
class EventListener
{
    const ACKNOWLEDGED = 'ACKNOWLEDGED';
    const READY = 'READY';
    const BUSY = 'BUSY';
    const QUIT = 'quit';

    /**
     * @var resource Input stream used to retrieve text
     */
    protected $inputStream;

    /**
     * @var resource Output stream used to send text
     */
    protected $outputStream;

    /**
     * @var resource Error stream used to write log messages and errors
     */
    protected $errorStream;

    /** @var array|EventHandler[] */
    protected $eventHandlers;

    /**
     * Create a new EventListener
     *
     * @param array|EventHandler[] $eventHandlers
     */
    public function __construct(array $eventHandlers = [])
    {
        $this->inputStream = STDIN;
        $this->outputStream = STDOUT;
        $this->errorStream = STDERR;
        $this->eventHandlers = $eventHandlers;
    }

    /**
     * Set the error stream
     *
     * @param resource $stream Stream to send logs and errors
     *
     * @return EventListener
     */
    public function setErrorStream($stream)
    {
        $this->errorStream = $stream;

        return $this;
    }

    /**
     * Set the input stream
     *
     * @param resource $stream Stream to retrieve input from
     *
     * @return EventListener
     */
    public function setInputStream($stream)
    {
        $this->inputStream = $stream;

        return $this;
    }

    /**
     * Set the output stream
     *
     * @param resource $stream Stream to send output to
     *
     * @return EventListener
     */
    public function setOutputStream($stream)
    {
        $this->outputStream = $stream;

        return $this;
    }

    /**
     * @param EventHandler|Closure|callable $eventHandler
     *
     * @return $this
     */
    public function addEventHandler(EventHandler $eventHandler)
    {
        $this->eventHandlers[] = $eventHandler;

        return $this;
    }

    /**
     * Poll stdin for Supervisord notifications and dispatch notifications to
     * the callback function which should accept this object (EventListener) as
     * its first parameter and an EventNotification as its second.  The callback
     * should return TRUE if it was successful, FALSE on failure, or 'quit' to
     * break from the event listener loop.
     *
     * @param callable|Closure Closure callback
     */
    public function listen($callback = null)
    {
        if ($callback) {
            $this->addEventHandler(new CallableEventHandler($callback));
        }

        $this->sendReady();

        while (true) {
            if (!$input = trim($this->readLine())) {
                continue;
            }

            $notification = $this->parseNotification($input);

            $result = $this->notifyEventHandlers($notification);

            if (true === $result) {
                $this->sendComplete();
            }
            elseif (false === $result) {
                $this->sendFail();
            }
            elseif ($result == 'quit') {
                break;
            }

            $this->sendReady();
        }
    }

    /**
     * Log data to STDERR
     *
     * @param string $message Message to log to STDERR
     */
    public function log($message)
    {
        fwrite($this->errorStream, '[Supervisord Event] ' . date('Y-m-d H:i:s') . ': ' . $message . "\n");
    }

    /**
     * Read a line from the input stream
     *
     * @return string
     */
    public function readLine()
    {
        return fgets($this->inputStream);
    }

    /**
     * Send an ACKNOWLEDGED state to Supervisord. The event listener
     * has acknowledged (accepted or rejected) an event send.
     */
    public function sendAcknowledged()
    {
        fwrite($this->outputStream, self::ACKNOWLEDGED . "\n");
    }

    /**
     * Send a BUSY state to Supervisord. Event notifications
     * may not be sent to this event listener.
     */
    public function sendBusy()
    {
        fwrite($this->outputStream, self::BUSY . "\n");
    }

    /**
     * Send a completion result
     */
    public function sendComplete()
    {
        fwrite($this->outputStream, "RESULT 2\nOK");
    }

    /**
     * Send a fail result
     */
    public function sendFail()
    {
        fwrite($this->outputStream, "RESULT 4\nFAIL");
    }

    /**
     * Send a READY state to Supervisord. Event notificatons
     * may be sent to this event listener
     */
    public function sendReady()
    {
        fwrite($this->outputStream, self::READY . "\n");
    }

    /**
     * @param $input
     *
     * @return EventNotification
     */
    protected function parseNotification($input)
    {
        $headers = EventNotification::parseData($input);

        $payload = fread($this->inputStream, (int)$headers['len']);

        return new EventNotification($input, $payload, $headers);
    }

    /**
     * @param EventNotification $notification
     *
     * @return mixed
     */
    protected function notifyEventHandlers(EventNotification $notification)
    {
        $result = true;

        foreach ($this->eventHandlers as $handler) {
            if ( ! $handler->isHandlingEvent($notification)) {
                continue;
            }

            $result = $handler->handleEvent($notification, $this);

            if ($result !== true) {
                break;
            }
        }

        return $result;
    }
}
