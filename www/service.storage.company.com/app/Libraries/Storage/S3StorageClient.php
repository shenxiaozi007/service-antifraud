<?php

namespace App\Libraries\Storage;

use Aws\S3\S3Client;
use InvalidArgumentException;

class S3StorageClient
{
    protected string $disk;

    protected array $config;

    protected S3Client $client;

    public function setDisk(?string $disk = ''): self
    {
        $this->disk = $disk ?: config('storage.default_disk');
        $this->config = config("storage.disks.{$this->disk}", []);

        if (! $this->config) {
            throw new InvalidArgumentException("Storage disk [{$this->disk}] is not configured.");
        }

        $clientConfig = [
            'version' => 'latest',
            'region' => $this->config['region'],
            'signature_version' => 'v4',
            'credentials' => [
                'key' => $this->config['key'],
                'secret' => $this->config['secret'],
            ],
        ];

        if (! empty($this->config['endpoint'])) {
            $clientConfig['endpoint'] = rtrim($this->config['endpoint'], '/');
            $clientConfig['use_path_style_endpoint'] = (bool) $this->config['use_path_style_endpoint'];
        }

        $this->client = new S3Client($clientConfig);

        return $this;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function getBucket(): string
    {
        return $this->config['bucket'];
    }

    public function upload(string $objectKey, string $filePath, string $mime): string
    {
        $this->client->putObject([
            'Bucket' => $this->getBucket(),
            'Key' => $objectKey,
            'Body' => fopen($filePath, 'r'),
            'ContentType' => $mime,
        ]);

        return $objectKey;
    }

    public function getObject(string $objectKey)
    {
        return $this->client->getObject([
            'Bucket' => $this->getBucket(),
            'Key' => $objectKey,
        ]);
    }

    public function getUrl(string $objectKey, int $expires = 0): string
    {
        if (! empty($this->config['cdn_host'])) {
            return rtrim($this->config['cdn_host'], '/') . '/' . ltrim($objectKey, '/');
        }

        if ($expires > 0) {
            $command = $this->client->getCommand('GetObject', [
                'Bucket' => $this->getBucket(),
                'Key' => $objectKey,
            ]);

            return (string) $this->client->createPresignedRequest($command, "+{$expires} seconds")->getUri();
        }

        return $this->client->getObjectUrl($this->getBucket(), $objectKey);
    }
}
