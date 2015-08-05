<?php

namespace mindplay\stately;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RuntimeException;

class SessionFileStorage implements SessionStorage
{
    /**
     * @var string file extension of session files
     */
    const SESSION_FILE_EXT = 'session.txt';

    /**
     * @var string absolute root path
     */
    private $root_path;

    /**
     * @var int number of path levels
     */
    private $path_levels;

    /**
     * @var string[] cached session data for open sessions (used to check for modifications on write)
     */
    private $sessions = array();

    /**
     * @var resource[] open file handles (exclusively locked on read, released on write)
     */
    private $files = array();

    /**
     * @var int permission mask for created files
     */
    private $file_mode;

    /**
     * @param string|null $root_path       absolute root path (or NULL to use INI setting)
     * @param int         $file_mode permission mask for created session files
     * @param int|null    $path_levels     number of path levels (or NULL to use INI setting)
     */
    public function __construct($root_path = null, $file_mode = 0666, $path_levels = null)
    {
        if ($root_path === null || $path_levels === null) {
            $parts = explode(';', ini_get('session.save_path'), 2);

            if ($root_path === null) {
                $root_path = count($parts) === 2
                    ? $parts[1]
                    : $parts[0];
            }

            if ($path_levels === null) {
                $path_levels = count($parts) === 2
                    ? (int)$parts[0]
                    : 0;
            }
        }

        $this->root_path = $root_path;
        $this->path_levels = $path_levels;
        $this->file_mode = $file_mode;
    }

    /**
     * @inheritdoc
     */
    public function read($session_id)
    {
        if (isset($this->files[$session_id])) {
            throw new RuntimeException("attempted duplicate read() with same \$session_id: {$session_id})");
        }

        $path = $this->getPath($session_id);
        $dir = dirname($path);

        if (!is_dir($dir) || !is_writable($dir)) {
            throw new RuntimeException("file permission issue - unable to write to dir: {$path}");
        }

        $file = @fopen($path, 'c+');

        if ($file === false) {
            throw new RuntimeException("unable to open session file: {$path}");
        }

        $mode_set = @chmod($path, $this->file_mode & ~umask()) !== false;

        if (!$mode_set) {
            throw new RuntimeException("unable to change mode of session file: {$path}");
        }

        if (!flock($file, LOCK_EX)) {
            trigger_error("unable to lock session file: {$path}", E_USER_WARNING);
        }

        $data = @stream_get_contents($file);

        if ($data === false) {
            throw new RuntimeException("unable to read session file: {$path}");
        }

        if ($data === '') {
            $data = null;
        }

        $this->sessions[$session_id] = $data;
        $this->files[$session_id] = $file;

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function write($session_id, $data, $gc_maxlifetime)
    {
        if (!isset($this->files[$session_id])) {
            throw new RuntimeException("attempted write() without prior read() with \$session_id: {$session_id}");
        }

        $file = $this->files[$session_id];

        if ($this->sessions[$session_id] !== $data) {
            ftruncate($file, 0);
            fwrite($file, $data);
            fflush($file);
        }

        if ($data === null || $data === '') {
            unlink($this->getPath($session_id));
        }

        flock($file, LOCK_UN);
        fclose($file);

        unset($this->sessions[$session_id]);
        unset($this->files[$session_id]);
    }

    /**
     * @inheritdoc
     */
    public function gc($gc_maxlifetime)
    {
        $paths = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $this->root_path,
                    FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
                )
            ),
            '#.*\.' . preg_quote(self::SESSION_FILE_EXT) . '$#'
        );

        $now = time();

        foreach ($paths as $path) {
            $age = $now - filemtime($path);

            if ($age >= $gc_maxlifetime) {
                @unlink($path);
            }
        }
    }

    /**
     * @param $session_id
     *
     * @return string absolute session file path
     */
    protected function getPath($session_id)
    {
        $path = $this->root_path;

        if ($this->path_levels > 0) {
            for ($i = 0; $i < $this->path_levels; $i++) {
                $path .= '/' . substr($session_id, $i, 1);
            }
        }

        return $path . '/' . preg_replace('#[^\w_-]#', '_', $session_id) . '.' . self::SESSION_FILE_EXT;
    }
}
