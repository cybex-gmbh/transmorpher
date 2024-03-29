# Transmorpher Media Server

A media server for images and videos.

> For a client implementation for Laravel
> see [Laravel Transmorpher Client](https://github.com/cybex-gmbh/laravel-transmorpher-client).

### Libraries used

#### Image transformation and optimization

- [Intervention Image](https://github.com/Intervention/image)
- [Laravel Image Optimizer](https://github.com/spatie/laravel-image-optimizer)

#### Video transcoding

- [PHP-FFmpeg-video-streaming](https://github.com/hadronepoch/PHP-FFmpeg-video-streaming)
- [PHP-FFMpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg)

## Installation

### Using docker

See the [Docker Hub repository](https://hub.docker.com/r/cybexwebdev/transmorpher) for images.

To not accidentally upgrade to a new major version, attach the major version you want to use to the image name:

`cybexwebdev/transmorpher:0`

#### Configuration options

There needs to be at least 1 Laravel worker to transcode videos. The following variable specifies how many workers should be running in the container:

```dotenv
VIDEO_TRANSCODING_WORKERS_AMOUNT=1
```

> [!CAUTION]
> Using the database queue connection does neither guarantee FIFO nor prevent duplicate runs. It is recommended to use a queue which can guarantee these aspects, such as AWS SQS
> FIFO.
> To prevent duplicate runs with database, use only one worker process.

This environment variable has to be passed to the app container in your docker-compose.yml:

```yaml
environment:
    VIDEO_TRANSCODING_WORKERS_AMOUNT: ${VIDEO_TRANSCODING_WORKERS_AMOUNT:-1}
```

### Cloning the repository

To clone the repository and get your media server running use:

```bash
git clone --branch release/v0 --single-branch https://github.com/cybex-gmbh/transmorpher.git
```

### Required software

See the Dockerfiles for details.

Image manipulation:

- [ImageMagick](https://imagemagick.org/index.php)
- [php-imagick](https://www.php.net/manual/en/book.imagick.php)

> Optionally you can use GD, which can be configured in the Intervention Image configuration file.

Image optimization:

- [JpegOptim](https://github.com/tjko/jpegoptim)
- [Optipng](https://optipng.sourceforge.net/)
- [Pngquant 2](https://pngquant.org/)
- [Gifsicle](https://www.lcdf.org/gifsicle/)
- [cwebp](https://developers.google.com/speed/webp/docs/precompiled)

To use video transcoding:

- [FFmpeg](https://ffmpeg.org/)

## General configuration

#### Disks

The media server uses 3 separate Laravel disks to store originals, image derivatives and video derivatives. Use the provided `.env` keys to select any of the disks in
the `filesystems.php` config file.

> [!NOTE]
>
> 1. The root folder, like images/, of the configured derivatives disks has to always match the prefix provided by the `MediaType` enum.
> 1. If this prefix would be changed after initially launching your media server, clients would no longer be able to retrieve their previously uploaded media.

### Cloud Setup

The Transmorpher media server is not dependent on a specific cloud service provider, but only provides classes for AWS services out of the box.

#### Prerequisites for video functionality

- A file storage, for example AWS S3
- A routing capable service, for example a Content Delivery Network, like AWS CloudFront

#### IAM

Create an IAM user with programmatic access. For more information check the documentation for the corresponding service.

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

By default, AWS S3 disks are configured in the `.env`:

```dotenv
TRANSMORPHER_DISK_ORIGINALS=s3Originals
TRANSMORPHER_DISK_IMAGE_DERIVATIVES=s3ImageDerivatives
TRANSMORPHER_DISK_VIDEO_DERIVATIVES=s3VideoDerivatives
```

Define the AWS S3 bucket for each disk:

```dotenv
AWS_BUCKET_ORIGINALS=
AWS_BUCKET_IMAGE_DERIVATIVES=
AWS_BUCKET_VIDEO_DERIVATIVES=
```

It is technically possible to use the same bucket for all 3, but it is recommended to split it up to help manage and secure the files.

Privacy settings:

- all file storages should be private
- the CDN needs to access the video derivatives storage

#### Content Delivery Network

Configure your CloudFront-Distribution-ID:

```dotenv
AWS_CLOUDFRONT_DISTRIBUTION_ID=
```

Changes to media will automatically trigger a cache invalidation, therefore the CDN cache duration can be set to a long time.

To forward incoming requests from the CDN to your media server, configure your Transmorpher media server as the main origin.
For more information on configuring origins in CloudFront see
the [documentation page](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/DownloadDistS3AndCustomOrigins.html).

In order to properly use the API you need to either:

1. add a rule to not cache anything under `/api/*`
1. publish the Transmorpher media server under an additional domain that is not behind the CDN

#### Video specific configuration

*Content Delivery Network*

In the CDN routing create a new behavior which points requests starting with "/videos/*" to a new origin, which is the video derivatives S3 bucket.  
\
*Sodium Keypair*

A signed request is used to notify clients about finished transcodings. For this, a [Sodium](https://www.php.net/manual/en/book.sodium.php) keypair has to be configured.

To create a keypair, simply use the provided command:

```bash
php artisan transmorpher:keypair
```

The newly created keypair has to be written in the `.env` file:

```dotenv
TRANSMORPHER_SIGNING_KEYPAIR=
```

The public key of the media server is available under the `/api/v*/publickey` endpoint and can be requested
by any client.  
\
*Queue*

Transcoding jobs are dispatched onto the "video-transcoding" queue. You can have these jobs processed on the main server or dedicated workers. For more information check
the [Laravel Queue Documentation](https://laravel.com/docs/10.x/queues).

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
TRANSMORPHER_DISK_VIDEO_DERIVATIVES=localVideoDerivatives
```

#### Video specific configuration

*File Storage*

To access public derivatives for videos, generate a symlink from the Laravel storage folder to the public folder:

```bash
php artisan storage:link
```

*Sodium Keypair*

A signed request is used to notify clients about finished transcodings. For this, a [Sodium](https://www.php.net/manual/en/book.sodium.php) keypair has to be configured.

To create a keypair, simply use the provided command:

```bash
php artisan transmorpher:keypair
```

The newly created keypair has to be written in the `.env` file:

```dotenv
TRANSMORPHER_SIGNING_KEYPAIR=
```

The public key of the media server is available under the `/api/v*/publickey` endpoint and can be requested
by any client.  
\
*Queue*

Transcoding jobs are dispatched onto the "video-transcoding" queue. You can have these jobs processed on the main server or dedicated workers. For more information check
the [Laravel Queue Documentation](https://laravel.com/docs/10.x/queues).

You can define your queue connection in the `.env` file:

```dotenv
QUEUE_CONNECTION=database
```

> [!CAUTION]
>
> The database connection does neither guarantee FIFO nor prevent duplicate runs. It is recommended to use a queue which can guarantee these aspects, such as AWS SQS FIFO.
> To prevent duplicate runs with database, use only one worker process.

### Additional options

By default, the media server stores image derivatives on the image derivatives disk. This can be turned off, so they will always be re-generated on
demand instead:

```dotenv
TRANSMORPHER_STORE_DERIVATIVES=true
```

There are additional settings in the `transmorpher.php` config file.

## Managing Media

### Users

Media always belongs to a user. To easily create one, use the provided command:

```bash
php artisan create:user <name> <email>
```

This command will provide you with a [Laravel Sanctum](https://laravel.com/docs/10.x/sanctum) token, which has to be
written in the `.env` file of a client system.
> The token will be passed for all API requests for authorization and is connected to the corresponding user.

If you need to re-generate a token for a user, use the provided command:

```bash
php artisan create:token <userId>
```

### Media

Media is identified by a string which is passed when uploading media. This "identifier" is unique per user.

When media is uploaded on the same identifier by the same user, a new version for the same media will be created.

The media server provides following features for media:

- upload
- get derivative
- get original*
- set version
- delete

> Marked with * only applies to images.

## Image transformation

Images will always be optimized and transformed on the Transmorpher media server. Requests for derivatives will also be directly answered by the media server.

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

## Video transcoding

Video transcoding is handled as an asynchronous task. The client will receive the
information about the transcoded video as soon as it completes. For this, a signed request is sent to the client.

Since video transcoding is a complex task it may take some time to complete. The client will also be notified about failed attempts.

To publicly access a video, the client name, the identifier and a format have to be specified. There are different formats available:

- HLS (.m3u8) `https://transmorpher.test/videos/<clientname>/<identifier>/hls/video.m3u8`
- DASH (.mpd) `https://transmorpher.test/videos/<clientname>/<identifier>/dash/video.mpd`
- MP4 (.mp4) `https://transmorpher.test/videos/<clientname>/<identifier>/mp4/video.mp4`

The [Laravel Transmorpher Client](https://github.com/cybex-gmbh/laravel-transmorpher-client) will receive this information and store it.

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

To add a class for image transformation, simply create a new class which implements the `TransformInterface`. An example
implementation can be found
at `App\Classes\Intervention\Transform`.
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

The `image-optimizer.php` configuration file specifies which optimizers should be used. Here you can configure options for each optimizer and add new or remove optimizers.

For more information on adding custom optimizers check the documentation of
the [Laravel Image Optimizer](https://github.com/spatie/laravel-image-optimizer#adding-your-own-optimizers) package.

### Video Transcoding

By default, the Transmorpher uses FFmpeg and Laravel jobs for transcoding videos. This can be changed similar to the
image transformation classes.

To interchange the class, which is responsible for initiating transcoding, simply create a new class which implements
the `TranscodeInterface`. An example implementation, which
dispatches a job, can be found at `App\Classes\Transcode.php`.
You will also have to adjust the configuration value:

```php
'transcode_class' => App\Classes\YourTranscodeClass::class,
```

## Development

### [Pullpreview](https://github.com/pullpreview/action)

When labeling a pull request with the "pullpreview" label, a staging environment is booted. To make this functional, some environment variables have to be stored in GitHub secrets:

- APP_KEY
- TRANSMORPHER_SIGNING_KEYPAIR
- PULLPREVIEW_TRANSMORPHER_AUTH_TOKEN_HASH
- PULLPREVIEW_AWS_ACCESS_KEY_ID
- PULLPREVIEW_AWS_SECRET_ACCESS_KEY

#### Auth Token Hash

The environment is seeded with a user with an auth token. To get access, you will have to locally create a token and use this token and its hash.

```bash
php artisan create:user pullpreview pullpreview@example.com
```

Take the hash of the token from the `personal_access_tokens` table and save it to GitHub secrets. The command also provides a `TRANSMORPHER_AUTH_TOKEN`, which should be stored
securely to use in client systems.

#### AWS Credentials

You need credentials of an IAM user that can manage AWS Lightsail. For a recommended configuration take a look at
the [Pullpreview wiki](https://github.com/pullpreview/action/wiki/Recommended-AWS-Configuration).

## License

The Transmorpher media server is licensed under the [MIT license](https://opensource.org/licenses/MIT).
