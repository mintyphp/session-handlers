<?php

class sessionLogger extends \SessionHandler
{

    static public function debug($val, $color = 'red')
    {
        echo "<div style='margin-left:300px; color:$color'>" . $val . '</div>';
    }

    public function create_sid(): string
    {
        $sid = parent::create_sid();
        self::debug('create_sid(): ' . $sid);
        return $sid;
    }

    public function open($path, $session_name): bool
    {
        self::debug('Open(): ' . $session_name);
        return parent::open($path, $session_name);
    }

    public function read($session_id): string
    {
        self::debug('Read(): ' . $session_id);
        $data = parent::read($session_id);
        return $data;
    }

    public function write($session_id, $data): bool
    {
        self::debug('Write(): ' . $session_id);
        return parent::write($session_id, $data);
    }

    public function close(): bool
    {
        self::debug('Close(): ' . session_id());
        return parent::close();
    }

    public function destroy($session_id): bool
    {
        self::debug('Destroy(): ' . $session_id);
        return parent::destroy($session_id);
    }

    public function gc($max_life): int
    {
        self::debug('Garbage Collection()');
        return parent::gc($max_life);
    }
}

class customHandler extends sessionLogger implements SessionHandlerInterface
{

    static public function debug($val, $color = 'blue')
    {
        echo "<div style='margin-left:200px; color:$color'>" . $val . '</div>';
    }

    public function create_sid(): string
    {
        self::debug('creating_sid ....');
        $sid = parent::create_sid();
        return $sid;
    }

    public function validateId($id): bool
    {
        self::debug('validateID (' . $id . ')');
        return true;
    }
}



ini_set('session.use_strict_mode', '1');
$handler = new customHandler;
session_set_save_handler($handler, true);

echo '<p>session_start()</p>';
session_start();

echo '<p>session_regenerate_id(false)</p>';
session_regenerate_id(false);

echo '<p>end session</p>';
