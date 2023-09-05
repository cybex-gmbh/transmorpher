<?php


use App\Console\Commands\DeleteFfmpegTempFolders;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteFfmpegFoldersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected const PATH_TO_TEST_FOLDER = 'tests/ffmpeg-folders';

    /**
     * @test
     */
    public function ensureCorrectFoldersAreDeleted()
    {
        $basePath = base_path(self::PATH_TO_TEST_FOLDER);

        $this->createFolders([
            'ffmpeg-passes_olderThanADay' => Carbon::now()->subDay(),
            'ffmpeg-passes_youngerThanADay' => Carbon::now()
        ], $basePath);

        App::shouldReceive('basePath')->andReturn(sprintf('%s/*', $basePath))->once();
        Artisan::call(DeleteFfmpegTempFolders::class);

        $this->assertEquals([sprintf('%s/ffmpeg-passes_youngerThanADay', $basePath)], File::directories($basePath), 'Folders were not deleted correctly.');

        // Cleanup
        File::deleteDirectory($basePath);
    }

    protected function createFolders(array $folders, string $basePath): void
    {
        mkdir($basePath);

        foreach ($folders as $folderName => $creationDate) {
            $pathToFolder = sprintf('%s/%s', $basePath, $folderName);
            mkdir($pathToFolder);
            touch($pathToFolder, $creationDate->timestamp);
        }
    }
}
