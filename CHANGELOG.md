# Release Notes

## [v0.8.0](https://github.com/cybex-gmbh/transmorpher/compare/v0.7.0...v0.8.0)

### Features

- now uses Laravel 12 and PHP 8.4, this implies:
  - local disk is now pointing to `storage/app/private` by default. The local disk is used for storing temporary files.
  - session driver is now set to `database` by default in the .env.example
  - cache store is now set to `database` by default in the .env.example
  - migrations for the session and cache database tables have been published
- a worker for emails has been added to the docker image. Available notifications have been documented, see ["Email notifications README"](README.md#email-notifications)

### Bug Fixes

- fix an issue for the local disk setup where videos could not be delivered due to CORS

### Development

- the `Intervention Image` package has been updated to v3, since v2 is EOL. 
- `ImageMagick` v6 caused artifacts to appear in images when combined with `Intervention Image` v3, therefore we needed to update `ImageMagick` to v7. 

## [v0.7.0](https://github.com/cybex-gmbh/transmorpher/compare/v0.6.0...v0.7.0)

### Features

- PDFs are now supported, see ["PDF handling README"](README.md#pdf-handling)
  - upload
  - download
  - images of specific pages, supporting image formats and transformations
  - derivative purging
- the version history is now traceable, this means that restoring a version will now create a new version using the same original file as source

## [v0.6.0](https://github.com/cybex-gmbh/transmorpher/compare/v0.5.3...v0.6.0)

### Features

- add gpu video encoding support, including an optional docker compose file with nvidia support. For more information, see ["GPU Acceleration README"](README.md#gpu-acceleration)
- make video decoder and encoder configurable
- update ffmpeg version from 4 to 6

## [v0.5.3](https://github.com/cybex-gmbh/transmorpher/compare/v0.5.2...v0.5.3)

### Bug Fixes

- fix an issue where trying to upload large transcoded videos to the cloud would fail

## [v0.5.2](https://github.com/cybex-gmbh/transmorpher/compare/v0.5.1...v0.5.2)

### Bug Fixes

- fix an issue where trying to load large videos would exceed the memory limit
- fix an issue where HLS and DASH files were named incorrectly

## [v0.5.1](https://github.com/cybex-gmbh/transmorpher/compare/v0.5.0...v0.5.1)

### Bug Fixes

- restore the custom error message for "ModelNotFoundExceptions"

## [v0.5.0](https://github.com/cybex-gmbh/transmorpher/compare/v0.4.0...v0.5.0)

### Development

- the seeded PullPreview user is now configurable

## [v0.4.0](https://github.com/cybex-gmbh/transmorpher/compare/v0.3.0...v0.4.0)

### Features

- now uses Laravel 11
- temporary ffmpeg files are now stored on the local disk and deleted after video transcoding
- stray chunk files can now be deleted using the laravel scheduler, see ["Scheduling README"](README.md#scheduling)
- derivatives can now be deleted, see ["Purging Derivatives README"](README.md#purging-derivatives)

### Bug Fixes

- fix crashes when transformations are not passed in expected format
- docker workers now work as expected
- fix an issue where user creation sometimes failed
- media delivery routes no longer use sessions. This prevents cookie conflicts when server and client run on the same domain

### Development

- GitHub workflows now use reusable workflows from https://github.com/cybex-gmbh/github-workflows
- the PullPreview staging environment now comes with a companion app for easier testing

## [v0.3.0](https://github.com/cybex-gmbh/transmorpher/compare/v0.2.0...v0.3.0)

### Features

- provide the possibility to configure a subdirectory for the laravel.log file 

### Development

- rename aws github secrets to clarify usage

## [v0.2.0](https://github.com/cybex-gmbh/transmorpher/compare/v0.1.1...v0.2.0)

### Features

- provide a production-ready docker image
- add [laravel-protector](https://github.com/cybex-gmbh/laravel-protector) for database dumps and backups

### Development

- add [pullpreview](https://pullpreview.com/) workflow for on-demand staging environments
- add a GitHub workflow for automatic Docker image releases on tag creation

## [v0.1.1](https://github.com/cybex-gmbh/transmorpher/compare/v0.1.0...v0.1.1)

### Bug Fixes

- The mimetype for files without extension was always returned as application/octet-stream,
  which caused file downloads in the browser instead of displaying it

## [Initial release](https://github.com/cybex-gmbh/transmorpher/releases/tag/v0.1.0)

### Features

- upload and download of media
- media version management
- image transformation and optimization:
    - jpg
    - png
    - webp
    - gif
- video transcoding:
    - mp4
    - dash
    - hls
