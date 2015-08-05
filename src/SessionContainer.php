<?php

namespace mindplay\stately;

use Closure;

/**
 * Consumer-facing (public) interface of {@see SessionService}.
 */
interface SessionContainer
{
    /**
     * Access one or more objects in this container.
     *
     * @param Closure $func function(MyType $object...) : mixed
     *
     * @return mixed any value returned by the given function
     */
    public function update(Closure $func);

    /**
     * Remove and object from this session container.
     *
     * Note that the change is not effective until you call commit()
     *
     * @param string|object $object session object, or model class-name (e.g. ShoppingCart::class)
     *
     * @return void
     */
    public function remove($object);

    /**
     * Removes all the objects in this session container.
     *
     * Note that this change isn't effective until you {@see commit()} the changes.
     *
     * @return void
     */
    public function clear();
}
