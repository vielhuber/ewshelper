<?php
declare(strict_types=1);
namespace vielhuber\ewshelper;

/**
 * Thrown when exchange contacts cannot be read completely (connection, soap or ews response error).
 */
final class EwsHelperException extends \RuntimeException
{
    /**
     * Wrap a connection, soap or response parsing error.
     */
    public static function requestFailed(\Throwable $previous): self
    {
        return new self('EWS request failed: ' . $previous->getMessage(), 0, $previous);
    }

    /**
     * Describe an ews response message whose ResponseClass is not Success.
     */
    public static function responseFailed(string $operation, object $responseMessage): self
    {
        $code = $responseMessage->ResponseCode ?? '';
        $text = $responseMessage->MessageText ?? '';
        return new self(trim('EWS ' . $operation . ' failed: ' . $code . ' ' . $text));
    }
}
