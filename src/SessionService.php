<?php

namespace mindplay\stately;

use Closure;
use OutOfRangeException;
use Psr\Http\Message\ResponseInterface;
use ReflectionFunction;
use Psr\Http\Message\ServerRequestInterface;

/**
 * This class implements a type-safe session model container.
 *
 * Your session models must have an empty constructor, and will be constructed for you.
 *
 * Only one instance of each type of session object can be kept in the container - if you
 * need multiple instances of a nested model object (such as items in a shopping cart),
 * simply put those instances in an array in the session model itself.
 *
 * Session object types must be able to {@link serialize()} and {@link unserialize()}.
 *
 * Keep the smallest possible unit of information in a session model - for example, keep
 * an active user ID, as opposed to keeping the entire User model object.
 *
 * Remember, your session models are strictly models, not services - do not be tempted
 * to add, for example, a getUser() method for a $user_id property, as this implies both
 * interaction with a service (which actually makes your model a service) as well as a
 * dependency on that service. The correct approach in this case, is to provide a
 * service elsewhere (e.g. in a module) and have that service interact with the session
 * model, as opposed to expecting consumers to interact directly with the model.
 */
class SessionService implements SessionContainer
{
    /**
     * @var string cookie name
     */
    protected $cookie_name;

    /**
     * @var string|null session ID (or NULL, if this is a new session)
     */
    protected $session_id;

    /**
     * @var (object|null)[] map where full-qualified class-name => object (or NULL, if the object has been removed)
     */
    protected $cache = array();

    /**
     * @var SessionStorage
     */
    protected $storage;

    /**
     * @var int
     */
    protected $gc_maxlifetime;

    /**
     * @var int probability of garbage collection (relative to divisor)
     */
    protected $gc_probability;

    /**
     * @var int
     */
    protected $gc_divisor;

    /**
     * @param ServerRequestInterface $request        incoming server request
     * @param SessionStorage         $storage        session storage implementation
     * @param string                 $cookie_name    cookie name (or NULL to use INI setting)
     * @param int|null               $gc_maxlifetime time to live (or NULL to use INI setting)
     * @param int|null               $gc_probability garbage collection probability (or NULL to use INI setting)
     * @param int|null               $gc_divisor     garbage collection divisor (or NULL to use INI setting)
     *
     * @throws OutOfRangeException if any of the given $gc_* settings are out of range
     */
    public function __construct(
        ServerRequestInterface $request,
        SessionStorage $storage,
        $cookie_name,
        $gc_maxlifetime = null,
        $gc_probability = null,
        $gc_divisor = null
    ) {
        $this->cookie_name = $cookie_name ?: ini_get('session.name');
        $this->storage = $storage;

        $cookies = $request->getCookieParams();

        if (isset($cookies[$this->cookie_name])) {
            $session_id = $cookies[$this->cookie_name];

            $data = $this->storage->read($session_id);

            if ($data !== null) {
                $this->session_id = $session_id;
                $this->cache = $this->unserialize($data);
            }
        }

        $this->gc_maxlifetime = $gc_maxlifetime ?: (int)ini_get('session.gc_maxlifetime');
        $this->gc_probability = (int)ini_get('session.gc_probability');
        $this->gc_divisor = (int)ini_get('session.gc_divisor');

        if ($this->gc_probability > $this->gc_divisor) {
            throw new OutOfRangeException("\$gc_probability ({$this->gc_probability}) must be less than or equal to \$gc_divisor ({$this->gc_divisor})");
        }

        if ($this->gc_probability < 1) {
            throw new OutOfRangeException("\$gc_probability ({$this->gc_probability}) must be greater than or equal to 1");
        }

        if ($this->gc_divisor < 1) {
            throw new OutOfRangeException("\$gc_divisor ({$this->gc_divisor}) must be greater than or equal to 1");
        }
    }

    /**
     * @inheritdoc
     */
    public function update(Closure $func)
    {
        $reflection = new ReflectionFunction($func);

        $params = $reflection->getParameters();

        $args = array();

        foreach ($params as $param) {
            $type = $param->getClass()->getName();

            if (!isset($this->cache[$type])) {
                // auto-construct the requested session model:

                $this->cache[$type] = new $type();
            }

            $args[] = $this->cache[$type];
        }

        call_user_func_array($func, $args);
    }

    /**
     * @inheritdoc
     */
    public function remove($object)
    {
        $key = is_object($object)
            ? get_class($object)
            : (string)$object;

        $this->cache[$key] = null;
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->cache = array();
    }

    /**
     * Commit any changes made to objects in this session container to the underlying
     * storage implementation, and add the session ID cookie to the given response.
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function commit(ResponseInterface $response)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            trigger_error('PHP standard session management is in use', E_USER_WARNING);
        }

        $data = $this->serialize($this->cache);

        if ($this->session_id || $data) {
            if ($this->session_id === null) {
                $this->session_id = $this->createSessionID();

                $response = $response->withHeader('Set-Cookie', "{$this->cookie_name}={$this->session_id}; Path=/; HttpOnly");
            }

            $this->storage->write($this->session_id, $data, $this->gc_maxlifetime);
        }

        if (mt_rand(1, $this->gc_divisor) <= $this->gc_probability) {
            $this->storage->gc($this->gc_maxlifetime);
        }

        return $response;
    }

    /**
     * @param array $data
     *
     * @return string|null serialized data (or NULL, if the data array is empty)
     */
    protected function serialize(array $data)
    {
        return count($data) > 0
            ? serialize($data)
            : null;
    }

    /**
     * @param string|null $str serialized data (or NULL, if there is no data)
     *
     * @return array data
     */
    protected function unserialize($str)
    {
        if ($str === null || $str === '') {
            return array();
        }

        // note the error-tolerance here: if you provide garbage, you get an empty array.

        $data = @unserialize($str);

        return is_array($data)
            ? $data
            : array();
    }

    /**
     * @return string new session ID
     */
    protected function createSessionID()
    {
        return UUID::create();
    }
}
