<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/**
 * Base class for any non-2xx response from the Mailcore API.
 *
 * The API reuses HTTP status codes across endpoints (a 404 may mean
 * "User not found" or "Domain name not found", a 409 may mean
 * "User already exists" or "Password has already been used", ...), and always
 * carries a human-readable reason in the JSON `statusmsg` field. We therefore
 * map the *status code* to a typed subclass (robust) and preserve `statusMsg`
 * verbatim so callers can branch further when they need to:
 *
 *     try {
 *         $client->users()->add($email, $password, $plan);
 *     } catch (ConflictException $e) {
 *         // $e->statusMsg === 'User already exists' | 'Password has already been used ...'
 *     }
 */
class ApiException extends \RuntimeException implements MailcoreException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $statusMsg,
        public readonly string $path,
        /**
         * The raw response body, verbatim. A few endpoints return meaningful data
         * alongside a non-2xx status (e.g. /mailfilter/bpllookup's 409 carries the
         * offending host's details), so it is preserved here. Never contains the
         * API key — that lives in the request URL, not the response.
         */
        public readonly ?string $body = null,
    ) {
        $reason = $statusMsg ?? 'Mailcore API request failed';
        parent::__construct(sprintf('[%d] %s (%s)', $statusCode, $reason, $path));
    }

    /**
     * Build the most specific exception available for the given status code.
     */
    public static function fromResponse(int $statusCode, ?string $statusMsg, string $path, ?string $body = null): self
    {
        $class = match (true) {
            $statusCode === 400 => BadRequestException::class,
            $statusCode === 404 => NotFoundException::class,
            $statusCode === 406 => NotAcceptableException::class,
            $statusCode === 409 => ConflictException::class,
            $statusCode === 410 => GoneException::class,
            $statusCode === 411 => MissingParameterException::class,
            $statusCode === 417 => ExpectationFailedException::class,
            $statusCode >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($statusCode, $statusMsg, $path, $body);
    }
}
