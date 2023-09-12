<?php

namespace Tests\Unit;

use App\Console\Commands\DeleteFfmpegTempFolders;
use Carbon\Carbon;
use Tests\TestCase;

class DeleteFfmpegFoldersCommandTest extends TestCase
{
    /**
     * @test
     * @dataProvider folderDataProvider
     */
    public function ensureCorrectFoldersAreDeleted(string $baseName, string $creationDate, bool $expectation)
    {
        $path = base_path(sprintf('tests/data/ffmpeg-folders/%s', $baseName));

        $this->travelTo(Carbon::create(2000, 01, 01));
        touch($path, Carbon::parse($creationDate)->timestamp);

        $this->assertEquals(
            $expectation,
            app(DeleteFfmpegTempFolders::class)->directoryShouldBeDeleted($path)
        );
    }

    /**
     * @test
     */
    public function ensureNonExistentPathDoesNotCauseProblems()
    {
        $this->assertEquals(
            false,
            app(DeleteFfmpegTempFolders::class)->directoryShouldBeDeleted(base_path('tests/data/ffmpeg-folders/ffmpeg-passes_nonExistent'))
        );
    }

    protected function folderDataProvider(): array
    {
        return [
            'directoryOlderThanADay' => [
                'directory' => 'ffmpeg-passes_directoryOlderThanADay',
                'creationDate' => '1999-12-31',
                'expectation' => true
            ],
            'directoryYoungerThanADay' => [
                'directory' => 'ffmpeg-passes_directoryYoungerThanADay',
                'creationDate' => '2000-01-01',
                'expectation' => false
            ],
            'fileOlderThanADay' => [
                'directory' => 'ffmpeg-passes_fileOlderThanADay',
                'creationDate' => '2000-01-01',
                'expectation' => false
            ],
            'fileYoungerThanADay' => [
                'directory' => 'ffmpeg-passes_fileYoungerThanADay',
                'creationDate' => '2000-01-01',
                'expectation' => false
            ],
        ];
    }
}
