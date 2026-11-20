<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model\Provider;

use Angeo\AiAssist\Api\ProviderInterface;
use Laminas\Http\Request;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\LaminasClientFactory;
use Magento\Framework\Phrase;

/**
 * POST JSON to a fixed provider URL. TLS verified, no redirects.
 *
 * The URL is a constant of each provider class; nothing from the request or
 * the store decides where the call goes. The API key travels in a header,
 * never in the URL, so it does not end up in proxy or server logs.
 */
abstract class AbstractHttpProvider implements ProviderInterface
{
    public function __construct(
        private readonly LaminasClientFactory $clientFactory,
        protected readonly ResponseParser $parser
    ) {
    }

    abstract protected function name(): string;

    /**
     * @param array<string,mixed>  $payload
     * @param array<string,string> $headers
     * @return array<string,mixed>
     * @throws LocalizedException
     */
    protected function post(string $url, array $payload, array $headers, int $timeout): array
    {
        $client = $this->clientFactory->create();
        $client->setUri($url);
        $client->setMethod(Request::METHOD_POST);
        $client->setOptions([
            'timeout'            => $timeout,
            'maxredirects'       => 0,
            'sslverifypeer'      => true,
            'sslallowselfsigned' => false,
        ]);
        $client->setHeaders(array_merge(['Content-Type' => 'application/json'], $headers));
        $client->setRawBody((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        try {
            $response = $client->send();
        } catch (\Throwable $e) {
            // The message of a transport error can contain the URL, never the
            // key: the key is in a header.
            throw new LocalizedException(
                new Phrase('%1 could not be reached: %2', [$this->name(), mb_substr($e->getMessage(), 0, 200)])
            );
        }

        $status  = (int) $response->getStatusCode();
        $raw     = (string) $response->getBody();
        $decoded = json_decode($raw, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status < 200 || $status >= 300) {
            $hint = match ($status) {
                401, 403 => ' Check the API key in the AI Assistant settings.',
                404      => ' Check the model name in the AI Assistant settings.',
                429      => ' The provider rate limit or budget was reached. Try again later.',
                default  => '',
            };
            throw new LocalizedException(new Phrase(
                '%1 returned an error (HTTP %2): %3%4',
                [$this->name(), $status, $this->parser->error($decoded, $raw), $hint]
            ));
        }

        return $decoded;
    }

    /**
     * @param array{text:string,input_tokens:?int,output_tokens:?int,stop:?string} $result
     * @return array{text:string,input_tokens:?int,output_tokens:?int,stop:?string}
     * @throws LocalizedException
     */
    protected function requireText(array $result): array
    {
        if (trim($result['text']) === '') {
            throw new LocalizedException(new Phrase(
                '%1 returned no text (stop reason: %2).',
                [$this->name(), $result['stop'] ?? 'unknown']
            ));
        }

        return $result;
    }
}
