<?php

namespace App\Console\Commands;

use App;
use File;
use Illuminate\Console\Command;

class DeleteFfmpegTempFolders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ffmpeg:delete-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes all temporary Ffmpeg folders which are older than 1 day.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $directoriesDeleted = 0;

        foreach (File::glob(App::basePath('ffmpeg-passes*')) as $directory) {
            if (File::isDirectory($directory) && File::lastModified($directory) <= now()->subDay()->timestamp) {
                File::deleteDirectory($directory) ? $directoriesDeleted++ : $this->error(sprintf('Could not delete directory "%s".', $directory));
            }
        }

        $this->info(sprintf('Deleted %d directories.', $directoriesDeleted));

        return Command::SUCCESS;
    }
}
