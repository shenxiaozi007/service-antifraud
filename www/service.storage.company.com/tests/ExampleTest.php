<?php

namespace Tests;

class ExampleTest extends TestCase
{
    public function test_file_disks_endpoint_returns_supported_disks(): void
    {
        $this->get('/service/api/v1/file/disks');

        $this->seeStatusCode(200);
        $this->seeJsonContains([
            'code' => 0,
            'module' => 'object-storage-service',
        ]);
    }
}
