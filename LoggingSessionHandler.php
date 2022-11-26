<?php

namespace MintyPHP;

use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

class LoggingSessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    private $sessionHandler;

    public function __construct($sessionHandler)
    {
        $this->sessionHandler = $sessionHandler;
    }

    public function __call($name, $arguments)
    {
        $parameters = json_encode($arguments);
        echo "$name $parameters = ";
        $result = $this->sessionHandler->$name(...$arguments);
        echo json_encode($result) . "\n";
        return $result;
    }

    public function close(): bool
    {
        return $this->__call('close', func_get_args());
    }

    public function destroy($id): bool
    {
        return $this->__call('destroy', func_get_args());
    }

    public function gc($maxlifetime): bool
    {
        return $this->__call('gc', func_get_args());
    }

    public function open($save_path, $session_name): bool
    {
        return $this->__call('open', func_get_args());
    }

    public function read($id): string
    {
        return $this->__call('read', func_get_args());
    }

    public function write($id, $session_data): bool
    {
        return $this->__call('write', func_get_args());
    }

    public function create_sid(): string
    {
        return $this->__call('create_sid', func_get_args());
    }

    public function validateId($id): bool
    {
        return $this->__call('validateId', func_get_args());
    }

    public function updateTimestamp($id, $session_data): bool
    {
        return $this->__call('updateTimestamp', func_get_args());
    }
}
