<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Context\Traits;

use Behat\Gherkin\Node\PyStringNode;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

trait ResponseTrait
{
    protected ?Response $response;

    /**
     * Verifies response status code.
     *
     * @param string $httpStatus
     *
     * @Then /^response status code should be (\d+)$/
     *
     * @throws RuntimeException
     */
    public function responseStatusCodeShouldBe(string $httpStatus): void
    {
        if ((string) $this->response->getStatusCode() !== $httpStatus) {
            $message = sprintf(
                'HTTP code does not match %s (actual: %s). Response: %s',
                $httpStatus,
                $this->response->getStatusCode(),
                $this->response->sendContent()
            );

            throw new RuntimeException($message);
        }
    }

    /**
     * Checks whether response is JSON or not.
     *
     * @Then /^response is JSON$/
     *
     * @throws RuntimeException|JsonException
     */
    public function responseIsJson(): void
    {
        $data = json_decode($this->response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (empty($data)) {
            throw new RuntimeException("Response was not JSON\n" . $this->response->getContent());
        }
    }

    /**
     * @Then response should be empty
     *
     * @throws RuntimeException
     */
    public function responseEmpty(): void
    {
        if (!empty($this->response->getContent())) {
            throw new RuntimeException('Content not empty');
        }
    }

    /**
     * Verifies that response should be exactly the same JSON as we expected.
     *
     * @param PyStringNode $string
     *
     * @Then /^response should be JSON:$/
     *
     * @throws RuntimeException|JsonException
     */
    public function responseShouldBeJson(PyStringNode $string): void
    {
        $expectedResponse = json_decode(trim($string->getRaw()), true, 512, JSON_THROW_ON_ERROR);
        $actualResponse = json_decode($this->response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if ($expectedResponse !== $actualResponse) {
            $prettyJSON = json_encode($actualResponse, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT, 512);
            $message = sprintf("Expected JSON does not match actual JSON:\n%s\n", $prettyJSON);

            throw new RuntimeException($message);
        }
    }

    /**
     * Checks whether response has given param or not.
     *
     * @param string $param
     *
     * @throws RuntimeException|JsonException
     *
     * @When /^I get "([^"]*)" param from json response$/
     */
    public function iGetParamFromJsonResponse(string $param): void
    {
        $actualResponse = json_decode($this->response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($actualResponse[$param])) {
            throw new RuntimeException(sprintf('Response does not contain param "%s"', $param));
        }

        if ($param === 'token') {
            $this->token = $actualResponse[$param];
        }
    }

    /**
     * Verifies that response should be JSON with variable fields that we expected.
     *
     * @param string       $variableFields
     * @param PyStringNode $string
     *
     * @Then /^response should be JSON with variable fields "([^"]*)":$/
     */
    public function responseShouldBeJsonWithVariableFields(string $variableFields, PyStringNode $string): void
    {
        $this->compareStructureResponse($variableFields, $string, $this->response->getContent());
    }
}
