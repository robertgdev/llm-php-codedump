<?php

declare(strict_types=1);

namespace CodebaseDump\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Uploads audit data to the Code Audits API.
 */
class AuditApiUploader
{
    private ?string $apiKey;
    private string $apiUrl;
    private string $apiSubmittedBy;
    private Client $httpClient;

    public function __construct(
        ?string $apiKey = null,
        string $apiUrl = 'https://codeaudits.ai/',
        string $apiSubmittedBy = 'codebase-dump'
    ) {
        $this->apiKey = $apiKey;
        $this->apiUrl = rtrim($apiUrl, '/') . '/';
        $this->apiSubmittedBy = $apiSubmittedBy;
        $this->httpClient = new Client();
    }

    /**
     * Uploads the audit data to the API.
     *
     * @throws \InvalidArgumentException if the audit content is empty
     * @throws \RuntimeException if the upload fails
     */
    public function uploadAudit(string $audit): void
    {
        if (empty($audit)) {
            throw new \InvalidArgumentException('Repo content is required to upload');
        }

        echo "Uploading to audits API...\n";

        $headers = [
            'x-submitted-by' => $this->apiSubmittedBy,
        ];

        if ($this->apiKey !== null) {
            $headers['x-api-key'] = $this->apiKey;
        }

        $payload = [
            'text' => $audit,
        ];

        $url = $this->apiUrl . 'api/repo/add';

        try {
            $response = $this->httpClient->post($url, [
                'json' => $payload,
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $responseBody = (string) $response->getBody();

                if ($statusCode === 413) {
                    throw new \RuntimeException(
                        'Parsed codebase is too big. Please reduce the size. ' .
                        'You can use --ignore-top-large-files param to ignore the largest files or use ignore patterns.'
                    );
                }

                throw new \RuntimeException('Failed to upload audit: ' . $responseBody);
            }

            echo "Audit uploaded successfully\n";
            echo "Audit info:\n";

            $responseData = json_decode((string) $response->getBody(), true);
            print_r($responseData);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to upload audit: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Gets the API key.
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Gets the API URL.
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * Gets the submitted by string.
     */
    public function getApiSubmittedBy(): string
    {
        return $this->apiSubmittedBy;
    }
}
