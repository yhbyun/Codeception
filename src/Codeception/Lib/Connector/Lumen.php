<?php
namespace Codeception\Lib\Connector;

use Illuminate\Http\Request;
use Laravel\Lumen\Application;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class Lumen extends Client implements HttpKernelInterface
{

    /**
     * @var Application
     */
    private $app;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        parent::__construct($this);
    }

    /**
     * Handle a request.
     *
     * @param SymfonyRequest $request
     * @param int $type
     * @param bool $catch
     * @return Response
     */
    public function handle(SymfonyRequest $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        $this->app['request'] = $request = Request::createFromBase($request);

        if (class_exists('Dingo\Api\Provider\LumenServiceProvider')) {
            $reflection = new \ReflectionClass($this->app);
            $this->addRequestMiddlewareToBeginning($reflection);
        }

        $response = $this->app->handle($request);

        $method = new \ReflectionMethod(get_class($this->app), 'callTerminableMiddleware');
        $method->setAccessible(true);
        $method->invoke($this->app, $response);

        return $response;
    }

    /**
     * Add the request middleware to the beginning of the middleware stack on the
     * Lumen application instance.
     *  
     * @param \ReflectionClass $reflection
     *
     * @return void
     */
    protected function addRequestMiddlewareToBeginning(\ReflectionClass $reflection)
    {
        $property = $reflection->getProperty('middleware');
        $property->setAccessible(true);

        $middleware = $property->getValue($this->app);

        if ((count($middleware) && $middleware[0] !== 'Dingo\Api\Http\Middleware\Request') || count($middleware) === 0) {
            array_unshift($middleware, 'Dingo\Api\Http\Middleware\Request');
            $property->setValue($this->app, $middleware);
        }
        $property->setAccessible(false);
    }
}
