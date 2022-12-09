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
        $parameters = json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (method_exists($this->sessionHandler, $name)) {
            $result = $this->sessionHandler->$name(...$arguments);
        } else {
            switch ($name) {
                case 'create_sid': // from SessionIdInterface
                    $result = bin2hex(random_bytes(16));
                    break;
                case 'updateTimestamp': // from SessionUpdateTimestampHandlerInterface
                    $result = $this->sessionHandler->write(...$arguments);
                    break;
                default:
                    $className = get_class($this->sessionHandler);
                    throw new \Exception("The class '$className' is missing method '$name'");
            }
        }
        $return = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "$name $parameters = $return\n";
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
