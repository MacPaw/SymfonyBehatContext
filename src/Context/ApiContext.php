<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Context;

use SymfonyBehatContext\Context\Traits\ArraySimilarTrait;
use SymfonyBehatContext\Context\Traits\ResponseTrait;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Exception;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

class ApiContext implements Context, ResettableInterface
{
    use ArraySimilarTrait;
    use ResponseTrait;

    private RouterInterface $router;
    private RequestStack $requestStack;
    private KernelInterface $kernel;

    private ?string $token = null;
    private array $headers = [];

    public function __construct(
        RouterInterface $router,
        RequestStack $requestStack,
        KernelInterface $kernel
    ) {
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->kernel = $kernel;
    }

    /**
     * Before Scenario.
     *
     * @BeforeScenario
     */
    public function beforeScenario(): void
    {
        $this->reset();
    }

    /**
     * Send request to route.
     *
     * @param string $method
     * @param string $route
     * @param array  $headers
     * @param string $queryString
     * @param array  $postFields
     * @param array  $routeParams
     * @param array  $cookies
     *
     * @Given /^I send "([^"]*)" request to "([^"]*)" route$/
     *
     * @throws Exception
     */
    public function iSendRequestToRoute(
        string $method,
        string $route,
        array $headers = [],
        string $queryString = '',
        array $postFields = [],
        array $routeParams = [],
        array $cookies = []
    ): void {
        $url = $this->router->generate($route, $routeParams);
        $url = preg_replace('|^/app[^\.]*\.php|', '', $url);

        $request = Request::create($url . '?' . $queryString, $method, $postFields ?? [], $cookies);
        $request->headers->add(array_merge($this->headers, $headers));

        $response = $this->kernel->handle($request);

        $this->requestStack->pop();
        $this->kernel->terminate($request, $response);

        if (strtoupper($method) !== Request::METHOD_GET) {
            $this->kernel->resetBundles();
        }

        $this->response = new Response($response->getContent(), $response->getStatusCode(), $response->headers->all());
    }

    /**
     * @Given /^the "([^"]*)" request header contains "([^"]*)"$/
     */
    public function theRequestHeaderContains($header, $value): void
    {
        $this->headers[$header] = $value;
    }

    /**
     * Send request with last given token and params.
     *
     * @param string       $method
     * @param string       $route
     * @param PyStringNode $params
     *
     * @Then /^I send "([^"]*)" request to "([^"]*)" route with last token and params:$/
     */
    public function iSendRequestToRouteWithLastTokenAndParams(string $method, string $route, PyStringNode $params): void
    {
        $this->addParamsAndSendRequestToRoute($method, $route, $params, ['Authorization' => 'Bearer ' . $this->token]);
    }

    /**
     * Send request with last given token and params.
     *
     * @param string $method
     * @param string $route
     *
     * @Then /^I send "([^"]*)" request to "([^"]*)" route with last token$/
     */
    public function iSendRequestToRouteWithLastToken(string $method, string $route): void
    {
        $this->iSendRequestToRoute(
            $method,
            $route,
            ['Authorization' => 'Bearer ' . $this->token]
        );
    }

    /**
     * Send request to route with given token and params.
     *
     * @param string       $method
     * @param string       $route
     * @param string       $token
     * @param PyStringNode $params
     *
     * @Given /^I send "([^"]*)" request to "([^"]*)" route with token "([^"]*)" and params:$/
     */
    public function iSendRequestToRouteWithTokenAndParams(
        string $method,
        string $route,
        string $token,
        PyStringNode $params
    ): void {
        $this->addParamsAndSendRequestToRoute($method, $route, $params, ['Authorization' => 'Bearer ' . $token]);
    }

    /**
     * Send request to route with params.
     *
     * @param string       $method
     * @param string       $route
     * @param PyStringNode $params
     *
     * @throws \RuntimeException
     *
     * @Given /^I send "([^"]*)" request to "([^"]*)" route with params:$/
     */
    public function iSendRequestToRouteWithParams(string $method, string $route, PyStringNode $params): void
    {
        $this->addParamsAndSendRequestToRoute($method, $route, $params);
    }

    /**
     * I send request to route with token.
     *
     * @param string $method
     * @param string $route
     * @param string $token
     *
     * @Given /^I send "([^"]*)" request to "([^"]*)" route with token "([^"]*)"$/
     */
    public function iSendRequestToRouteWithToken(string $method, string $route, string $token): void
    {
        $this->iSendRequestToRoute(
            $method,
            $route,
            ['Authorization' => 'Bearer ' . $token]
        );
    }

    private function addParamsAndSendRequestToRoute(
        string $method,
        string $route,
        PyStringNode $params,
        array $headers = []
    ): void {
        $queryString = '';
        $postFields = [];
        $requestParams = json_decode(trim($params->getRaw()), true, 512, JSON_THROW_ON_ERROR);

        if (Request::METHOD_GET === $method) {
            $queryString = http_build_query($requestParams);
        } elseif (Request::METHOD_POST === $method || Request::METHOD_PATCH === $method) {
            $postFields = $requestParams;
        }

        $routeParams = $this->popRouteAttributesFromRequestParams($route, $requestParams);

        $this->iSendRequestToRoute($method, $route, $headers, $queryString, $postFields, $routeParams);
    }

    /**
     * Retrieves route attributes from given array, cleans this array from route attributes
     *
     * @param string $route
     * @param array  $requestParams
     *
     * @return array
     */
    private function popRouteAttributesFromRequestParams(string $route, &$requestParams): array
    {
        $routeParams = [];

        if (is_array($requestParams) && ($routeDecl = $this->router->getRouteCollection()->get($route))) {
            $requirements = $routeDecl->getRequirements();

            foreach ($requirements as $attribute => $requirement) {
                if (isset($requestParams[$attribute]) && strpos($attribute, '_') !== 0) {
                    $routeParams[$attribute] = $requestParams[$attribute];
                    unset($requestParams[$attribute]);
                }
            }
        }

        return $routeParams;
    }

    public function reset(): void
    {
        $this->response = null;
        $this->token = null;
        $this->headers = [];
    }
}
