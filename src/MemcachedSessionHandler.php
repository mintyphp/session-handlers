<?php

namespace MintyPHP;

use SessionHandlerInterface;
use SessionIdInterface;
use Memcached;

class MemcachedSessionHandler implements SessionHandlerInterface, SessionIdInterface
{
    private string $sessionName = '';
    private string $sessionId = '';
    private $memcached = null;
    private bool $isLocked = false;

    /* Open session data database */
    public function open($save_path, $session_name): bool
    {
        // string $save_path - Directory path, connection strings, etc. Default: session.save_path
        // string $session_name - Session ID cookie name. Default: session.name

        $url = parse_url($save_path);
        $memcached = new Memcached();
        $memcached->addServer($url['host'], $url['port']);

        $this->sessionName = $session_name;
        $this->memcached = $memcached;

        // MUST return bool. Return true for success.
        return true;
    }

    /* Close session data database */
    public function close(): bool
    {
        // void parameter
        // NOTE: This function should unlock session data, if write() does not unlock it.

        $prefix = $this->sessionName;
        $id = $this->sessionId;
        $session_lock_key_name = "sess-$prefix-$id-lock";

        if ($this->isLocked) {
            $this->memcached->delete($session_lock_key_name);
        }
        $this->sessionId = '';

        // MUST return bool. Return true for success.
        return $this->memcached->quit();
    }

    /* Read session data */
    public function read($id): string|false
    {
        if (!ctype_xdigit($id)) return '';

        // string $id - Session ID string
        // NOTE: All production session save handler MUST implement "exclusive" lock.
        //       e.g. Use "serializable transaction isolation level" with RDBMS.
        //       read() would be the best place for locking for most save handlers.

        $this->sessionId = $id;
        $prefix = $this->sessionName;

        // We are creating lock entries in memcache
        // to support locks on distributed systems.
        $session_key_name = "sess-$prefix-$id";
        $session_lock_key_name = "sess-$prefix-$id-lock";

        // Try to aquire lock for 30 seconds (max execution time).
        $success = false;
        $max_time = ini_get("max_execution_time") ?: 30;
        for ($i = 0; $i < $max_time * 50; $i++) {
            $success = $this->memcached->add($session_lock_key_name, '1', 0);
            if ($success) {
                break;
            }
            usleep(20 * 1000); // wait for 20 ms
        }
        // return false if we could not aquire the lock
        if ($success === false) {
            return false;
        }
        $this->isLocked = true;
        // read MUST create file. Otherwise, strict mode will not work
        $session_timeout = ini_get('session.gc_maxlifetime');
        $session_data = $this->memcached->get($session_key_name) ?: '';
        $this->memcached->touch($session_key_name, $session_timeout);

        // MUST return STRING for successful read().
        // Return false only when there is error. i.e. Do not return false
        // for non-existing session data for the $id.
        return $session_data;
    }

    /* Write session data */
    public function write($id, $session_data): bool
    {
        if (!ctype_xdigit($id)) return false;
        if (!$id || $this->sessionId != $id) return false;

        // string $id - Session ID string
        // string $session_data - Session data string serialized by session serializer.
        // NOTE: This function may unlock session data locked by read(). If write() is
        //       is not suitable place your handler to unlock. Unlock data at close().

        $prefix = $this->sessionName;
        $session_key_name = "sess-$prefix-$id";
        $session_lock_key_name = "sess-$prefix-$id-lock";
        $session_timeout = ini_get('session.gc_maxlifetime');
        $return = $this->memcached->set($session_key_name, $session_data, $session_timeout);
        $this->memcached->delete($session_lock_key_name);
        $this->isLocked = false;
        // MUST return bool. Return true for success.
        return $return;
    }

    /* Remove specified session */
    public function destroy($id): bool
    {
        if (!ctype_xdigit($id)) return false;
        if (!$id || $this->sessionId != $id) return false;

        // string $id - Session ID string

        $this->sessionId = '';
        $prefix = $this->sessionName;
        $session_key_name = "sess-$prefix-$id";
        $session_lock_key_name = "sess-$prefix-$id-lock";
        $this->memcached->delete($session_key_name);
        $this->memcached->delete($session_lock_key_name);
        $this->isLocked = false;
        // MUST return bool. Return true for success.
        // Return false only when there is error. i.e. Do not return false
        // for non-existing session data for the $id.
        return true;
    }

    /* Perform garbage collection */
    public function gc($maxlifetime): int
    {
        // memcache evicts data that exceeds TTL automatically
        return true;
    }

    /* Create new secure session ID */
    public function create_sid(): string
    {
        // void parameter
        // NOTE: Defining create_sid() is mandatory because validate_sid() is mandatory for
        //       security reasons for production save handler.
        //       PHP 7.1 has session_create_id() for secure session ID generation. Older PHPs
        //       must generate secure session ID by yourself.
        //       e.g. hash('sha2', random_bytes(64)) or use /dev/urandom

        $prefix = $this->sessionName;
        do {
            $id = bin2hex(random_bytes(16)); // 128 bit is recommended
            $session_key_name = "sess-$prefix-$id";
        } while ($this->memcached->get($session_key_name));

        // MUST return session ID string.
        // Return false for error.
        return $id;
    }

    /* Check session ID collision */
    public function validateId($id): bool
    {
        if (!ctype_xdigit($id)) return false;

        // string $id - Session ID string

        $prefix = $this->sessionName;
        $session_key_name = "sess-$prefix-$id";
        $ret = $this->memcached->get($session_key_name) ? true : false;

        // MUST return bool. Return true for collision.
        // NOTE: This handler is mandatory for session security.
        //       All save handlers MUST implement this handler.
        //       Check session ID collision, return true when it collides.
        //       Otherwise, return false.
        return $ret;
    }

    /* Update session data access time stamp WITHOUT writing $session_data */
    public function updateTimestamp($id, $session_data): bool
    {
        if (!ctype_xdigit($id)) return false;
        if (!$id || $this->sessionId != $id) return false;

        // string $id - Session ID string
        // string $session_data - Session data serialized by session serializer
        // NOTE: This handler is optional. If your session database cannot
        //       support time stamp updating, you must not define this.

        $prefix = $this->sessionName;
        $session_key_name = "sess-$prefix-$id";
        $session_lock_key_name = "sess-$prefix-$id-lock";
        // We shouldn't update the timestamp if we don't hold the lock.
        if (!$this->memcached->get($session_lock_key_name)) {
            return false;
        }
        $session_timeout = ini_get('session.gc_maxlifetime');
        $return = $this->memcached->touch($session_key_name, $session_timeout);
        $this->memcached->delete($session_lock_key_name);
        $this->isLocked = false;
        // MUST return bool. Return true for success.
        return $return;
    }
}
