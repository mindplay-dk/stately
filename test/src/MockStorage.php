<?php

use mindplay\stately\SessionStorage;

class MockStorage implements SessionStorage
{
    /**
     * @var string[]
     */
    private $sessions = array();

    /**
     * @var float[]
     */
    private $timestamps = array();

    /**
     * @inheritdoc
     */
    public function read($session_id)
    {
        return @$this->sessions[$session_id];
    }

    /**
     * @inheritdoc
     */
    public function write($session_id, $data, $gc_maxlifetime)
    {
        $this->sessions[$session_id] = $data;
        $this->timestamps[$session_id] = microtime(true);
    }

    /**
     * @inheritdoc
     */
    public function gc($gc_maxlifetime)
    {
        $now = microtime(true);

        foreach ($this->timestamps as $session_id => $timestamp) {
            $age = $now - $timestamp;

            if ($age > $gc_maxlifetime) {
                unset($this->sessions[$session_id]);
                unset($this->timestamps[$session_id]);
            }
        }
    }
}
