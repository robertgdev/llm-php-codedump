<?php

declare(strict_types=1);

use CodebaseDump\Core\AuditApiUploader;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function createMockClient(array $responses): Client
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    return new Client(['handler' => $handlerStack]);
}

describe('AuditApiUploader', function () {
    test('upload throws exception when audit text is empty', function () {
        $uploader = new AuditApiUploader(
            apiKey: 'test_key',
            apiUrl: 'http://custom.example.com/',
            apiSubmittedBy: 'codebase-dump.v1'
        );

        expect(fn () => $uploader->uploadAudit(''))
            ->toThrow(InvalidArgumentException::class, 'Repo content is required to upload');
    });

    test('uploadAuditSuccessful returns correct values', function () {
        $uploader = new AuditApiUploader(
            apiKey: 'test_key',
            apiUrl: 'http://custom.example.com/',
            apiSubmittedBy: 'codebase-dump.v1'
        );

        expect($uploader->getApiKey())->toBe('test_key');
        expect($uploader->getApiUrl())->toBe('http://custom.example.com/');
        expect($uploader->getApiSubmittedBy())->toBe('codebase-dump.v1');
    });

    test('uploadAuditWithoutApiKey returns null for api key', function () {
        $uploader = new AuditApiUploader(
            apiKey: null,
            apiUrl: 'https://codeaudits.ai/',
            apiSubmittedBy: 'codebase-dump'
        );

        expect($uploader->getApiKey())->toBeNull();
    });

    test('constructor sets default values', function () {
        $uploader = new AuditApiUploader();

        expect($uploader->getApiKey())->toBeNull();
        expect($uploader->getApiUrl())->toBe('https://codeaudits.ai/');
        expect($uploader->getApiSubmittedBy())->toBe('codebase-dump');
    });
});
