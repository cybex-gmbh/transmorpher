<?php

use FFMpeg\FFMpeg;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BinaryAvailabilityTest extends TestCase
{
    /**
     * @param $binaryName
     * @return void
     */
    protected function assertBinaryExists($binaryName): void
    {
        $exitCode = Process::run(['which', $binaryName])->exitCode();

        $this->assertEquals(0, $exitCode, sprintf('%s is not installed', $binaryName));
    }

    #[Test]
    public function ensureConfiguredImageOptimizersAreInstalled()
    {
        $optimizers = array_keys(config('image-optimizer.optimizers'));

        foreach ($optimizers as $optimizer) {
            $binaryName = app($optimizer)->binaryName();

            $this->assertBinaryExists($binaryName);
        }
    }

    #[Test]
    public function ensureImagemagickIsInstalled()
    {
        $this->assertBinaryExists('convert');
    }

    #[Test]
    public function ensureFfmpegIsInstalled()
    {
        $this->assertBinaryExists(FFMpeg::create()->getFFMpegDriver()->getName());
    }
}
