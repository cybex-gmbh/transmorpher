<?php

namespace Tests\v2\Unit;

use FFMpeg\FFMpeg;
use Imagick;
use PHPUnit\Framework\Attributes\Test;
use Process;
use Tests\TestCase;

class BinaryAvailabilityTest extends TestCase
{
    /**
     * @param $binaryName
     * @return void
     */
    protected function assertBinaryExists($binaryName): void
    {
        $exitCode = Process::run(sprintf('command -v %s', $binaryName))->exitCode();

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
    public function ensureImagickIsInstalled()
    {
        $this->assertIsArray(Imagick::getVersion());
    }

    #[Test]
    public function ensureFfmpegIsInstalled()
    {
        $this->assertBinaryExists(FFMpeg::create()->getFFMpegDriver()->getName());
    }
}
