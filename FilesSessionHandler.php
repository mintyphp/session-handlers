<?php

namespace MintyPHP;

use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

class FilesSessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    private string $sessionSavePath = '';
    private string $sessionId = '';

    /*
     * == General Return Value Rule ==
     *
     * Returning false indicates FATAL error.
     * Exceptions are: gc(), validate_sid()
     *
     * == Session Data Lock ==
     *
     * Session data lock is mandatory. Lock must be exclusive. i.e. Block read also.
     *
     * == Collision Detection ==
     *
     * Collision detection is mandatory to reject attacker initialized session ID.
     * Coolision detection is absolute requirement for secure session.
     */

    /* Open session data database */
    public function open($save_path, $session_name): bool
    {
        // string $save_path - Directory path, connection strings, etc. Default: session.save_path
        // string $session_name - Session ID cookie name. Default: session.name
        $this->sessionSavePath = $save_path;
        //echo "Open [{$save_path},{$session_name}]\n";
        // MUST return bool. Return true for success.
        return true;
    }

    /* Close session data database */
    public function close(): bool
    {
        // void parameter
        // NOTE: This function should unlock session data, if write() does not unlock it.

        $session_save_path = $this->sessionSavePath;
        $id = $this->sessionId;
        $session_lock_file_name = "$session_save_path/sess_$id.lock";
        if (file_exists($session_lock_file_name)) {
            unlink($session_lock_file_name);
        }
        $this->sessionId = '';

        //$session_save_path = $this->sessionSavePath;
        //$session_name = $this->sessionName;
        //echo "Close [{$session_save_path},{$session_name}]\n";

        // MUST return bool. Return true for success.
        return true;
    }

    /* Read session data */
    public function read($id): string
    {
        if (!ctype_xdigit($id)) return '';
        if ($this->sessionId && $this->sessionId != $id) return '';

        // string $id - Session ID string
        // NOTE: All production session save handler MUST implement "exclusive" lock.
        //       e.g. Use "serializable transaction isolation level" with RDBMS.
        //       read() would be the best place for locking for most save handlers.

        $this->sessionId = $id;
        $session_save_path = $this->sessionSavePath;
        //echo "Read [{$session_save_path},{$id}]\n";
        $session_file_name = "$session_save_path/sess_$id";
        $session_lock_file_name = "$session_save_path/sess_$id.lock";

        // try to aquire lock for 30 seconds (max execution time)
        $handle = false;
        $max_time = ini_get("max_execution_time");
        for ($i = 0; $i < $max_time * 10; $i++) {
            $handle = @fopen($session_lock_file_name, 'x');
            if ($handle !== false) {
                break;
            }
            usleep(100 * 1000); // wait for 100 ms
        }
        // return false if we could not aquire the lock
        if ($handle === false) {
            return false;
        }
        fclose($handle);
        // read MUST create file. Otherwise, strict mode will not work
        touch($session_file_name);

        // MUST return STRING for successful read().
        // Return false only when there is error. i.e. Do not return false
        // for non-existing session data for the $id.
        return (string) @file_get_contents($session_file_name);
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

        $session_save_path = $this->sessionSavePath;
        //echo "Write [{$session_save_path},{$id},{$session_data}]\n";
        $session_file_name = "$session_save_path/sess_$id";
        $session_lock_file_name = "$session_save_path/sess_$id.lock";
        if (!file_exists($session_lock_file_name)) {
            return false;
        }
        $return = file_put_contents($session_file_name, $session_data, LOCK_EX);
        unlink($session_lock_file_name);
        // MUST return bool. Return true for success.
        return $return === false ? false : true;
    }

    /* Remove specified session */
    public function destroy($id): bool
    {
        if (!ctype_xdigit($id)) return false;
        if (!$id || $this->sessionId != $id) return false;

        // string $id - Session ID string

        $this->sessionId = '';
        $session_save_path = $this->sessionSavePath;
        //echo "Destroy [{$session_save_path},{$id}]\n";
        $session_file_name = "$session_save_path/sess_$id";
        $session_lock_file_name = "$session_save_path/sess_$id.lock";
        unlink($session_file_name);
        unlink($session_lock_file_name);

        // MUST return bool. Return true for success.
        // Return false only when there is error. i.e. Do not return false
        // for non-existing session data for the $id.
        return true;
    }

    /* Perform garbage collection */
    public function gc($maxlifetime): int
    {
        // long $maxlifetime - GC TTL in seconds. Default: session.gc_maxlifetime

        $session_save_path = $this->sessionSavePath;
        $gc_cnt = 0;
        $directory = opendir("$session_save_path/");
        while (($file = readdir($directory)) !== false) {
            $qualified = "$session_save_path/$file";
            if (is_file($qualified) === true && substr($file, 0, 5) == 'sess_') {
                if (filemtime($qualified) + $maxlifetime <= time()) {
                    unlink($qualified);
                    $gc_cnt++;
                }
            }
        }
        closedir($directory);

        // SHOULD return long (number of deleted sessions).
        // Returning true works also, but it will not report correct number of deleted sessions.
        // Return negative value for error. false does not work because it's the same as 0.
        return $gc_cnt;
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

        $session_save_path = $this->sessionSavePath;
        do {
            $id = bin2hex(random_bytes(16)); // 128 bit is recommended
            $session_file_name = "$session_save_path/sess_$id";
        } while (file_exists($session_file_name));
        //echo "CreateID [{$id}]\n";

        // MUST return session ID string.
        // Return false for error.
        return $id;
    }

    /* Check session ID collision */
    public function validateId($id): bool
    {
        if (!ctype_xdigit($id)) return false;

        // string $id - Session ID string

        $session_save_path = $this->sessionSavePath;
        //echo "ValidateID [{$session_save_path},{$id}]\n";
        $session_file_name = "$session_save_path/sess_$id";
        $ret = file_exists($session_file_name);

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

        $session_save_path = $this->sessionSavePath;
        //echo "UpdateTimestamp [{$session_save_path},{$id}]\n";
        $session_file_name = "$session_save_path/sess_$id";
        $session_lock_file_name = "$session_save_path/sess_$id.lock";
        if (!file_exists($session_lock_file_name)) {
            return false;
        }
        $ret = touch($session_file_name);
        unlink($session_lock_file_name);

        // MUST return bool. Return true for success.
        return $ret;
    }
}
