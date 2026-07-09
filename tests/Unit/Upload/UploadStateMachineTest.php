<?php

namespace Tests\Unit\Upload;

use App\Exceptions\InvalidUploadStateException;
use App\Services\Admin\Upload\UploadStateMachine;
use PHPUnit\Framework\TestCase;

class UploadStateMachineTest extends TestCase
{
    private UploadStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new UploadStateMachine();
    }

    public function test_complete_is_terminal(): void
    {
        $this->expectException(InvalidUploadStateException::class);

        $meta = ['status' => 'complete'];
        $this->machine->transition($meta, 'uploading');
    }

    public function test_uploaded_to_finalizing_is_allowed(): void
    {
        $meta = ['status' => 'uploaded'];
        $this->machine->transition($meta, 'finalizing');

        $this->assertSame('finalizing', $meta['status']);
    }

    public function test_failed_to_uploading_is_allowed(): void
    {
        $meta = ['status' => 'failed'];
        $this->machine->transition($meta, 'uploading');

        $this->assertSame('uploading', $meta['status']);
    }
}
