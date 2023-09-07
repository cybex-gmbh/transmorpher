<?php

use App\Console\Commands\DeleteFfmpegTempFolders;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteFfmpegFoldersCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * @dataProvider folderDataProvider
     */
    public function ensureCorrectFoldersAreDeleted(string $directory, bool $expectation)
    {
        $this->travelTo(Carbon::create(2000, 01, 01));

        $this->assertEquals(
            $expectation,
            app(DeleteFfmpegTempFolders::class)->directoryShouldBeDeleted(
                base_path(sprintf('tests/data/ffmpeg-folders/%s', $directory))
            )
        );
    }

    protected function folderDataProvider(): array
    {
        return [
            'directoryOlderThanADay' => [
                'directory' => 'ffmpeg-passes_directoryOlderThanADay',
                'expectation' => true
            ],
            'directoryYoungerThanADay' => [
                'directory' => 'ffmpeg-passes_directoryyoungerThanADay',
                'expectation' => false
            ],
            'fileOlderThanADay' => [
                'directory' => 'ffmpeg-passes_fileOlderThanADay',
                'expectation' => false
            ],
            'fileYoungerThanADay' => [
                'directory' => 'ffmpeg-passes_fileYoungerThanADay',
                'expectation' => false
            ],
        ];
    }
}
