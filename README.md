# Transmorpher Media Server

A media server for images, pdfs and videos.

> For a client implementation for Laravel
> see [Laravel Transmorpher Client](https://github.com/cybex-gmbh/laravel-transmorpher-client).

### Libraries used

#### Image transformation and optimization

- [Intervention Image](https://github.com/Intervention/image)
- [Laravel Image Optimizer](https://github.com/spatie/laravel-image-optimizer)

#### PDF metadata removal

- [PDF Merge](https://github.com/karriereat/pdf-merge)

#### Video transcoding

- [PHP-FFmpeg-video-streaming](https://github.com/hadronepoch/PHP-FFmpeg-video-streaming)
- [PHP-FFMpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg)

## Installation

### Using docker

See the [Docker Hub repository](https://hub.docker.com/r/cybexwebdev/transmorpher) for images.

To not accidentally upgrade to a new major version, attach the major version you want to use to the image name:

`cybexwebdev/transmorpher:0`

#### Configuration options

There needs to be at least 1 Laravel worker to transcode videos.
The following variable specifies how many workers should be running in the container:

```dotenv
VIDEO_TRANSCODING_WORKERS_AMOUNT=1
```

> [!CAUTION]
> Using the database queue connection does neither guarantee FIFO nor prevent duplicate runs.
> It is recommended to use a queue which can guarantee these aspects, such as AWS SQS FIFO.
> To prevent duplicate runs with database, use only one worker process.

This environment variable has to be passed to the app container in your docker-compose.yml:

```yaml
environment:
    VIDEO_TRANSCODING_WORKERS_AMOUNT: ${VIDEO_TRANSCODING_WORKERS_AMOUNT:-1}
```

### Cloning the repository

To clone the repository and get your media server running, use:

```bash
git clone --branch release/v0 --single-branch https://github.com/cybex-gmbh/transmorpher.git
```

Install composer dependencies:

```bash
composer install --no-dev
```

#### Required software

See the Dockerfiles for details.

Image manipulation:

- [ImageMagick](https://imagemagick.org/index.php)
- [php-imagick](https://www.php.net/manual/en/book.imagick.php)

> Optionally, you can use GD, which can be configured in the Intervention Image configuration file.

Image optimization:

- [JpegOptim](https://github.com/tjko/jpegoptim)
- [Optipng](https://optipng.sourceforge.net/)
- [Pngquant](https://pngquant.org/)
- [Gifsicle](https://www.lcdf.org/gifsicle/)
- [cwebp](https://developers.google.com/speed/webp/docs/precompiled)

To use video transcoding:

- [FFmpeg](https://ffmpeg.org/)

#### Generic workers

Client notifications will be pushed on the queue `client-notifications`. You will need to set up 1 worker for this queue.

#### Scheduling

There may be some cases (e.g. failed uploads) where chunk files are not deleted and stay on the local disk.
To keep the local disk clean, a command is scheduled hourly to delete chunk files older than 24 hours.

See the [`chunk-upload` configuration file](config/chunk-upload.php) for more information.

To run the scheduler, you will need to add a cron job that runs the `schedule:run` command on your server:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

For more information about scheduling, check the [Laravel Docs](https://laravel.com/docs/11.x/scheduling).

## General configuration

#### Basics

1. Create an app key:

```bash
php artisan key:generate
```

2. Configure the database in the `.env` file.

3. Migrate the database:

```bash
php artisan migrate
```

#### Disks

The media server must use 3 separate Laravel disks to store originals, image derivatives and video derivatives.
Use the provided `.env` keys to select the according disks in the `filesystems.php` config file.

> [!NOTE]
>
> 1. The root folder, like images/, of the configured derivatives disks has to always match the prefix provided by the `MediaType` enum.
> 1. If this prefix would be changed after initially launching your media server,
     > clients would no longer be able to retrieve their previously uploaded media.

#### Sodium Keypair

A signed request is used to notify clients about finished transcodings and when derivatives are purged.
For this, a [Sodium](https://www.php.net/manual/en/book.sodium.php) keypair has to be configured.

To create a keypair, use the provided command:

```bash
php artisan create:keypair
```

The newly created keypair has to be written in the `.env` file:

```dotenv
TRANSMORPHER_SIGNING_KEYPAIR=
```

The public key of the media server is available under the `/api/v*/publickey` endpoint and can be requested by any client.

### Cloud Setup

The Transmorpher media server is not dependent on a specific cloud service provider,
but only provides classes for AWS services out of the box.

#### Prerequisites for video functionality

- A file storage, for example, AWS S3
- A routing-capable service, for example, a Content Delivery Network, like AWS CloudFront

#### IAM

Create an IAM user with programmatic access. For more information, check the documentation for the corresponding service.

Permissions:

- read/write/delete access to media storage
- read/write/delete access to queue service
- creation of CDN invalidations

Put the credentials into the `.env`:

```dotenv
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-central-1
```

#### File Storage

To use AWS S3 disks set the according `.env` values:

```dotenv
TRANSMORPHER_DISK_ORIGINALS=s3Originals
TRANSMORPHER_DISK_IMAGE_DERIVATIVES=s3ImageDerivatives
TRANSMORPHER_DISK_DOCUMENT_DERIVATIVES=s3DocumentDerivatives
TRANSMORPHER_DISK_VIDEO_DERIVATIVES=s3VideoDerivatives
```

Define the AWS S3 bucket for each disk:

```dotenv
AWS_BUCKET_ORIGINALS=
AWS_BUCKET_IMAGE_DERIVATIVES=
AWS_BUCKET_DOCUMENT_DERIVATIVES=
AWS_BUCKET_VIDEO_DERIVATIVES=
```

It is technically possible to use the same bucket for all 3,
but it is recommended to split it up to help manage and secure the files.

Privacy settings:

- all file storages should be private
- the CDN needs to access the video derivatives storage

#### Content Delivery Network

Configure your CloudFront-Distribution-ID:

```dotenv
AWS_CLOUDFRONT_DISTRIBUTION_ID=
```

Changes to media will automatically trigger a cache invalidation. Therefore, the CDN cache duration can be set to a long time.

To forward incoming requests from the CDN to your media server, configure your Transmorpher media server as the main origin.
For more information on configuring origins in CloudFront see
the [documentation page](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/DownloadDistS3AndCustomOrigins.html).

To properly use the API, you need to either:

1. add a rule to not cache anything under `/api/*`
1. publish the Transmorpher media server under an additional domain that is not behind the CDN

#### Video specific configuration

*Content Delivery Network*

In the CDN routing create a new behavior which points requests starting with "/videos/*" to a new origin,
which is the video derivatives S3 bucket.

*Queue*

Transcoding jobs are dispatched onto the "video-transcoding" queue.
You can have these jobs processed on the main server or dedicated workers.
For more information, check the [Laravel Queue Documentation](https://laravel.com/docs/11.x/queues).

> [!NOTE]
> Since queues are not generally FIFO, it is recommended to use a queue which guarantees FIFO and also prevents
> duplicate runs.
> For this, a custom AWS SQS FIFO queue connection is available.

You can define your queue connection in the `.env` file:

```dotenv
QUEUE_CONNECTION=sqs-fifo
```

To configure an AWS SQS queue, see the according keys in the `.env`.

### Local disk setup

#### Prerequisites for video functionality

- File storage accessible at `storage/app/videos`
- File storage able to be symlinked to `public/videos`

#### File Storage

Select the following Laravel disks in the `.env`:

```dotenv
TRANSMORPHER_DISK_ORIGINALS=localOriginals
TRANSMORPHER_DISK_IMAGE_DERIVATIVES=localImageDerivatives
TRANSMORPHER_DISK_DOCUMENT_DERIVATIVES=localDocumentDerivatives
TRANSMORPHER_DISK_VIDEO_DERIVATIVES=localVideoDerivatives
```

#### Video specific configuration

*File Storage*

To access public derivatives for videos, generate a symlink from the Laravel storage folder to the public folder:

```bash
php artisan storage:link
```

*Queue*

Transcoding jobs are dispatched onto the "video-transcoding" queue.
You can have these jobs processed on the main server or dedicated workers.
For more information, check the [Laravel Queue Documentation](https://laravel.com/docs/11.x/queues).

You can define your queue connection in the `.env` file:

```dotenv
QUEUE_CONNECTION=database
```

> [!CAUTION]
>
> The database connection does neither guarantee FIFO nor prevent duplicate runs.
> It is recommended to use a queue which can guarantee these aspects, such as AWS SQS FIFO.
> To prevent duplicate runs with database, use only one worker process.

### Additional options

By default, the media server stores image derivatives on the image derivatives disk.
This can be turned off, so they will always be re-generated on demand instead:

```dotenv
TRANSMORPHER_STORE_DERIVATIVES=true
```

There are additional settings in the `transmorpher.php` config file.

## Managing Media

### Users

Media always belongs to a user. To easily create one, use the provided command:

```bash
php artisan create:user <name> <email> <api_url>
```

The server sends notifications to the api url, for example, video transcoding information.
For our standard laravel client implementation, this is: `https://example.com/transmorpher/notifications`.

This command will provide you with a [Laravel Sanctum](https://laravel.com/docs/11.x/sanctum) token, which has to be
written in the `.env` file of a client system.
> The token will be passed for all API requests for authorization and is connected to the corresponding user.

If you need to re-generate a token for a user, use the provided command:

```bash
php artisan create:token <userId>
```

### Media

Media is identified by a string which is passed when uploading media. This "identifier" is unique per user.

When media is uploaded on the same identifier by the same user, a new version for the same media will be created.

The media server provides the following features for media:

- upload
- get derivative
- get original*
- set version
- delete

> Marked with * does not apply to videos.

## Image transformation

Images will always be optimized and transformed on the Transmorpher media server.
The media server will also directly answer requests for derivatives.

The media server provides the following transformations for images:

- width (w)
- height (h)
- quality (q)
- format (f)

To publicly access an image, the client name and the identifier have to be specified:

`https://transmorpher.test/images/<clientname>/<identifier>`

Images retrieved from this URL will be derivatives which are optimized.
Additionally, you can specify transformation parameters in the following format:

`https://transmorpher.test/images/<clientname>/<identifier>/<transformations>`

For example:

`https://transmorpher.test/images/catworld/european-short-hair/w-1920+h-1080+f-png+q-50`

The [Laravel Transmorpher Client](https://github.com/cybex-gmbh/laravel-transmorpher-client) will receive this information and store it. It can also create URLs with
transformations.

## PDF handling

Requesting a PDF file will return the document.
Metadata can be removed optionally by setting the `.env` key:

```dotenv
TRANSMORPHER_DOCUMENT_REMOVE_METADATA=true
```

### Images

When an image format transformation is specified, an image of a page will be returned.

By using the `p` transformation, you can specify the page to be exported.
By default, the first page will be used.

All available image transformations can also be applied to PDF image derivatives.
Requesting a PDF also follows the same URL structure as images, just replace `images` with `documents`.

Additionally, the pixels per inch can be specified with the `ppi` transformation.
The ppi will be multiplied with the document dimensions, which results in the image resolution.
By default, 300 ppi is used.
Use the `.env` key to specify another default:

```dotenv
TRANSMORPHER_DOCUMENT_DEFAULT_PPI=600
```

Example:

Document: `https://transmorpher.test/documents/catworld/cat-essay`

Image of page 5: `https://transmorpher.test/documents/catworld/cat-essay/f-jpg+p-5+w-1920+h-1080`

## Video transcoding

Video transcoding is handled as an asynchronous task. The client will receive the
information about the transcoded video as soon as it completes. For this, a signed request is sent to the client.

Since video transcoding is a complex task, it may take some time to complete.
The client will also be notified about failed attempts.

To publicly access a video, the client name, the identifier and a format have to be specified.
There are different formats available:

- HLS (.m3u8) `https://transmorpher.test/videos/<clientname>/<identifier>/hls/video.m3u8`
- DASH (.mpd) `https://transmorpher.test/videos/<clientname>/<identifier>/dash/video.mpd`
- MP4 (.mp4) `https://transmorpher.test/videos/<clientname>/<identifier>/mp4/video.mp4`

The [Laravel Transmorpher Client](https://github.com/cybex-gmbh/laravel-transmorpher-client) will receive this information and store it.

### Bit rate

The bit rate for video transcoding can be set in the `.env` file in kilobits:

```dotenv
TRANSMORPHER_VIDEO_ENCODER_BITRATE=9000k
```

This setting will be ignored for the DASH/HLS streaming formats because they are calculated automatically.
For suitable bit rates, see: https://help.twitch.tv/s/article/broadcast-guidelines#recommended

### Streaming Codec

To encode the DASH and HLS formats with either HEVC or h264, set the following environment variable.

```dotenv
TRANSMORPHER_VIDEO_ENCODER=cpu-hevc
```

or

```dotenv
TRANSMORPHER_VIDEO_ENCODER=cpu-h264
```

For the MP4 fallback file, h264 is always used because

- FFmpeg doesn't support HEVC in MP4 files when encoding with a CPU.
- h264 is the most widely supported codec, and this file is to be used when a client does not support HLS or DASH.

### GPU Acceleration

Videos may be transcoded using a machine's NVIDIA GPU.
This requires the according hardware and driver setup on the host machine.

- https://trac.ffmpeg.org/wiki/HWAccelIntro#NVENC
- https://docs.nvidia.com/video-technologies/video-codec-sdk/pdf/Using_FFmpeg_with_NVIDIA_GPU_Hardware_Acceleration.pdf

The following steps are necessary on a docker host:

- Install NVIDIA drivers
- Install NVIDIA container toolkit
- Configure the docker NVIDIA runtime (note difference for rootless docker)
- Add gpu capabilities and NVIDIA runtime to according compose.yml files
- Restart docker and according containers

To use GPU encoding with HEVC or h264, set the following environment variable.
This controls the codec used when transcoding videos to HLS and DASH, as well as the device used.

```dotenv
TRANSMORPHER_VIDEO_ENCODER=nvidia-hevc
```

or

```dotenv
TRANSMORPHER_VIDEO_ENCODER=nvidia-h264
```

The NVIDIA encoders have different presets available.
Higher preset numbers are higher quality and slower.
For encoder specific lists of presets see:

```bash
ffmpeg -h encoder=h264_nvenc
ffmpeg -h encoder=hevc_nvenc
```

The default preset is `p4`. To set the high quality preset, use the following environment variable:

```dotenv
TRANSMORPHER_VIDEO_ENCODER_NVIDIA_PRESET=p6
```

Each encoder has its own configuration file in the `config/encoder` folder, containing FFmpeg parameters.

Note that the optional GPU video decoding setting is experimental and unstable.
By default, videos are decoded using the CPU.

## Interchangeability

### Content Delivery Network

If you want to use a CDN other than CloudFront, you will have to provide a class, which implements the `CdnHelperInterface` and
provides the functionality of invalidating the CDN's cache.
The `CloudFrontHelper` class provides an implementation for CloudFront and can be viewed as an example.

You will also have to adjust the `transmorpher.php` configuration value for the `cdn_helper`:

```php
'cdn_helper' => App\Helpers\YourCdnClass::class,
```

### Image Transformation

The class to transform images as well as the classes to convert images to different formats are interchangeable.
This provides the ability to add additional image manipulation libraries or logic in a modular way.

To add a class for image transformation, create a new class which implements the `TransformInterface`.
An example implementation can be found at `App\Classes\Intervention\Transform`.
Additionally, the newly created class has to be specified in the `transmorpher.php` configuration file:

```php
'transform_class' => App\Classes\YourTransformationClass::class,
```

If you want to interchange the classes which convert images to different formats, you can do so by creating classes
which implement the `ConvertInterface`. An example
implementation can be found at `App\Classes\Intervention\Convert`.
You will also have to adjust the configuration values:

```php
'convert_classes' => [
    'jpg' => App\Classes\YourClassJpg::class,
    'png' => App\Classes\YourClassPng::class,
    'gif' => App\Classes\YourClassGif::class,
    'webp' => App\Classes\YourClassWebp::class,
],
```

### Image Optimization

The `image-optimizer.php` configuration file specifies which optimizers should be used.
Here you can configure options for each optimizer and add new or remove optimizers.

For more information on adding custom optimizers, check the documentation of
the [Laravel Image Optimizer](https://github.com/spatie/laravel-image-optimizer#adding-your-own-optimizers) package.

### Video Transcoding

By default, the Transmorpher uses FFmpeg and Laravel jobs for transcoding videos. This can be changed similar to the
image transformation classes.

To interchange the class, which is responsible for initiating transcoding, create a new class which implements
the `TranscodeInterface`. An example implementation, which
dispatches a job, can be found at `App\Classes\Transcode.php`.
You will also have to adjust the configuration value:

```php
'transcode_class' => App\Classes\YourTranscodeClass::class,
```

## Purging derivatives

Adjusting the way derivatives are generated will not be reflected on already existing derivatives.
Therefore, you might want to delete all existing derivatives or re-generate them.

We provide a command which will additionally notify clients with a signed request about a new derivatives revision,
so they can react accordingly (e.g. update cache buster).

```bash
php artisan purge:derivatives
```

The command accepts the options `--image`, `--document`, `--video` and `--all` (or `-a`) for purging the respective derivatives.
Image and document derivatives will be deleted, for video derivatives we dispatch a new transcoding job for the current version.

The derivatives revision is available on the route `/api/v*/cacheInvalidator`.

## Recovery

To restore operation of the server, restore the following:

- database
- the `originals` disk
- `.env` file*
- the `image derivatives` disk*
- the `document derivatives` disk*
- the `video derivatives` disk*

> Marked with * are optional, but recommended.

If the `.env` file is lost follow the setup instructions above, including creating a new signing keypair.

If video derivatives are lost, use the [purge command](#purging-derivatives) to restore them.

Lost image and document derivatives will automatically be re-generated on demand.

## Development

### Docker image information

#### ImageMagick

Due to issues with `ImageMagick 6` in combination with `Intervention Image v3` it is necessary to install `ImageMagick 7`.
This needs to be compiled from source, as our currently used distribution versions (Ubuntu 24.04, Debian bookworm) do not provide it yet.

#### FFmpeg

The production base image comes with an old FFmpeg version, therefore we add the source manually and install it from there.

#### NVIDIA toolkit

The production base image comes with the NVIDIA container toolkit pre-installed to enable GPU acceleration for video transcoding.

The development base image does not include it by default.
There is an additional build stage which takes care of installing the toolkit.

There is a development compose file `compose-nvidia.yml`, which targets this build stage.

### [Pullpreview](https://github.com/pullpreview/action)

For more information, take a look at the PullPreview section of the [github-workflow repository](https://github.com/cybex-gmbh/github-workflows#pullpreview).

App-specific GitHub Secrets:

- PULLPREVIEW_APP_KEY
- PULLPREVIEW_SODIUM_KEYPAIR
- PULLPREVIEW_SANCTUM_AUTH_TOKEN
- PULLPREVIEW_SANCTUM_AUTH_TOKEN_HASH
- PULLPREVIEW_USER_NAME
- PULLPREVIEW_USER_EMAIL

#### Companion App

A demonstration app, which implements the [client package](https://github.com/cybex-gmbh/laravel-transmorpher-client),
is booted with PullPreview and available at the PullPreview root URL.
The Transmorpher media server runs under the `transmorpher.` subdomain.

#### Auth Token Hash

The environment is seeded with a user with an auth token. To get access, you will have to locally create a token and use this token and its hash.

```bash
php artisan create:user pullpreview pullpreview@example.com http://pullpreview.test/transmorpher/notifications
```

Take the hash of the token from the `personal_access_tokens` table and save it to GitHub secrets. The command also provides a `TRANSMORPHER_AUTH_TOKEN`, which should be stored
securely to use in client systems.

#### Using your custom PullPreview environment

In addition to the GitHub Secrets, you'll need to set the `CLIENT_CONTAINER_NAME` env variable for the Transmorpher server.

You may use the `CLIENT_NOTIFICATION_ROUTE` env variable if you have a custom notifications url, which differs from the default client implementation.

### Chunk a file in Artisan Tinker

To test video transcoding for chunked uploads, you need to cut a video file into at least two pieces.
There is no additional change to the files. It is important that both chunks
have the same filename, else they cannot be joined on the other side.
Place a file called `test.mp4` in the `storage/app/private` folder.

```php
$chunkSize = <chunkSize in bytes>;
$fh = fopen(Storage::disk('local')->path('test.mp4'), 'r');

Storage::disk('local')->put('chunk1/chunkedVideo.mp4', fread($fh, $chunkSize));
Storage::disk('local')->put('chunk2/chunkedVideo.mp4', fread($fh, $chunkSize));
```

## Upgrade Guide

### v0.7.0 to v0.8.0

- If not using the docker image:
    - PHP was upgraded from 8.2 to 8.4. Upgrade your server accordingly.
    - The `Intervention Image` package was upgraded from v2 to v3.
        - `ImageMagick` v6 may cause artifacts to appear in images when combined with `Intervention Image` v3.
          Therefore, you must upgrade `ImageMagick` to v7 and use a v7 compatible `Imagick` version.
- Laravel was upgraded from v11 to v12.
  It is recommended to replace your .env with the new .env.example.
  Most noteworthy changes:
    - `SESSION_DRIVER` is now set to `database` by default.
    - `CACHE_DRIVER` is now `CACHE_STORE` and set to `database` by default.
- Run database migrations.
- The temporary files folder moved to `storage/app/private` as per the new `local` disk default.
  The folders `chunks` and `ffmpeg-temp` in the `storage/app` folder are no longer used.
  You can delete them.

## License

The Transmorpher media server is licensed under the [MIT license](https://opensource.org/licenses/MIT).
