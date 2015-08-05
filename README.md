mindplay/stately
================

This library implements a simple session abstraction that integrates with a
[PSR-7](http://www.php-fig.org/psr/psr-7/) HTTP abstraction, to replace PHP's
built-in session management.

This means you will not be using `$_SESSION` or the `session_*()` functions.

The API approach relies on type-hinted closures as a means of creating/updating
session state. In practice, this means every session variable will be an object,
which means your session state will really be exposed to you as a model, not as
an array or a key/value store; which is great for IDE support and for code
comprehension in general - for example:

```PHP
$product_id = 123;
$amount = 4;

$session->update(function (ShoppingCart $cart) use ($product_id, $amount) {
    $cart->items[] = new CartItem($product_id, $amount);
});
```

Note that the `ShoppingCart` model is automatically created, if it isn't already
present in the session data. As such, every session model is a "singleton", as
far as session state goes, though not in the sense that you can't create more
than one instance in a test-suite.

This is analogous to e.g. `$_SESSION['ShoppingCart'] = new ShoppingCart()`, but
eliminates the unsafe use of arrays and strings, the need to test if the session
variable is already set, the need to type-hint when reading from `$_SESSION`, and
so on.


#### Implementation

You should create `SessionService` centrally (usually in a DI container) and
expose it to consumers (usually controllers) as `SessionContainer`, which is
the public portion of the interface. The internal portion of the interface
includes the `commit()` method, which should be called centrally, as part of
your request/response pipeline (middleware, framework or front controller),
e.g. immediately after the controller has been dispatched, before you emit
the response.


#### Example

Note that auto-creation implies that your session models must always have an
empty constructor - which implies it must have no hard dependencies. Try to view
this as "a good thing" - it means you won't be tempted to do things like adding
a `getUser()` method for a `$user_id` property, which would imply a dependency
on a `UserRepository` of some sort.

This is called "feature envy", and it's an anti-pattern - you're trying to
provide a more convenient way to interact with the model, which would be great,
except your model is now really a service and not just a model, even if it is
internally using a repository to provide that service.

The clean approach to this problem, is a separate user service, which internally
uses the session container to interact with the session model - the intention is
is not for a consumer to reach into `$_SESSION` for your component's session data,
just as the expectation is not that a consumer directly accesses your session
model; but rather to encapsulate that exchange privately inside a service.

For example:

```PHP
class ActiveUser
{
    /** @var int|null active user ID (or NULL, if no User is logged in) */
    public $user_id;
}

class UserService
{
    private $container;
    private $repo;

    public function __construct(SessionContainer $container, UserRepository $repo)
    {
        $this->container = $container;
        $this->repo = $repo;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        $user_id = $this->container->update(function (ActiveUser $user) {
            return $user->user_id;
        });

        return $user_id
            ? $this->repo->getUserById($user_id)
            : null;
    }
}
```

The `User` model is now conveniently exposed by the `UserService`, which internally
uses the `ActiveUser` model for session persistence.
