<?php

namespace mindplay\stately;

/**
 * This interface defines a means of reading/writing/deleting session data.
 */
interface SessionStorage
{
    /**
     * Read data from underlying storage and lock the given session ID to prevent race conditions.
     *
     * The lock will be released by a later call to {@see write()}.
     *
     * @param string $session_id
     *
     * @return string|null data
     */
    public function read($session_id);

    /**
     * Write to underlying storage and unlock the given session ID.
     *
     * The key was previously locked by a call to {@see read()}.
     *
     * @param string      $session_id
     * @param string|null $data           data (or NULL to delete the session data)
     * @param int         $gc_maxlifetime time to live (in seconds; ignored if $data is NULL)
     *
     * @return void
     */
    public function write($session_id, $data, $gc_maxlifetime);

    /**
     * Garbage-collect session data with a timestamp older than a given number of seconds.
     *
     * @param int $gc_maxlifetime time to live (in seconds; ignored if $data is NULL)
     *
     * @return void
     */
    public function gc($gc_maxlifetime);
}
