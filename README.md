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

### Cloning the repository

To clone the repository and get your media server running use:

```bash
git clone --branch release/v1 --single-branch https://github.com/cybex-gmbh/transmorpher.git
```

### Required software

See the Dockerfiles for details.

To be able to use the image manipulation features:

- [ImageMagick](https://imagemagick.org/index.php)
- [php-imagick](https://www.php.net/manual/en/book.imagick.php)

> Optionally you can use GD, which can be configured in the Intervention Image configuration file.

For using image optimization features:

- [JpegOptim](https://github.com/tjko/jpegoptim)
- [Optipng](https://optipng.sourceforge.net/)
- [Pngquant 2](https://pngquant.org/)
- [Gifsicle](https://www.lcdf.org/gifsicle/)
- [cwebp](https://developers.google.com/speed/webp/docs/precompiled)

To use video transcoding:

- [FFmpeg](https://ffmpeg.org/)

## General configuration

#### Disks

By default the media server uses 3 separate disks to store originals, image derivatives and video derivatives.

The configured disks can be found in the `filesystems.php` config file. To change where your media is stored use the provided `.env` keys.

> **Warning**
>
> 1. The root of the configured disks has to always match the prefix provided by the `MediaType` enum.
> 1. If you change the prefix after initially launching your media server, clients will no longer be able to retrieve their previously uploaded media.

### Cloud Setup

***Note***: The Transmorpher media server is not dependent on a specific cloud service provider, but only supports AWS services out of the box.  

#### Prerequisites for video functionality

- A publicly available file storage, for example AWS S3
- A routing capable service, for example a Content Delivery Network, like AWS CloudFront

#### IAM

Create an IAM user with programmatic access. For more information check the documentation for the corresponding service.

IAM permissions:

- read/write/delete access to media storage
- read/write/delete access to queue service
- creation of CDN invalidations

To access your AWS services, enter your credentials:

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

You will also have to define your AWS S3 buckets for each disk:

```dotenv
AWS_BUCKET_ORIGINALS=
AWS_BUCKET_IMAGE_DERIVATIVES=
AWS_BUCKET_VIDEO_DERIVATIVES=
```

Privacy settings:

- video derivatives storage has to be publicly available, at least to the CDN
- originals storage and image derivatives storage should be private

#### Content Delivery Network

The Transmorpher media server provides the possibility to use an AWS CloudFront CDN distribution by default. For this, the CloudFront-Distribution-ID has to be configured:

```dotenv
AWS_CLOUDFRONT_DISTRIBUTION_ID=
```

***Note:*** Changes to media will automatically trigger a cache invalidation, therefore the CDN cache duration can be set to a long time.

To forward incoming requests from the CDN to your media server, configure your Transmorpher media server as an origin.
For more information on configuring origins in CloudFront see
the [documentation page](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/DownloadDistS3AndCustomOrigins.html).

#### Video specific configuration

*Content Delivery Network*

To forward incoming requests from the CDN to your cloud storage, configure the cloud storage as an origin.
For more information on configuring origins in CloudFront see
the [documentation page](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/DownloadDistS3AndCustomOrigins.html).

Additionally, if you are also using image transformation, the CDN has to differentiate between video and image requests.
In CloudFront, set up a behavior which points requests starting with "/videos/*" to your storage origin, whereas the default rule points to your media server
origin.  
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

***Note:*** The public key of the media server is available under the `/api/v*/publickey` endpoint and can be requested
by any client.  
\
*Queue*

Transcoding jobs are dispatched onto the "video-transcoding" queue. You can have these jobs processed on the main server or dedicated workers. For more information check
the [Laravel Queue Documentation](https://laravel.com/docs/10.x/queues).

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

To store your files on a local disk, you can specify the following disks in your environment file:

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

***Note:*** The public key of the media server is available under the `/api/v*/publickey` endpoint and can be requested
by any client.  
\
*Queue*

Transcoding jobs are dispatched onto the "video-transcoding" queue. You can have these jobs processed on the main server or dedicated workers. For more information check
the [Laravel Queue Documentation](https://laravel.com/docs/10.x/queues).

You can define your queue connection in the `.env` file:

```dotenv
QUEUE_CONNECTION=database
```

> **Warning**
>
> The database connection does neither guarantee FIFO nor prevents duplicate runs. It is recommended to use a queue which can guarantee these aspects, such as AWS SQS FIFO.

### Additional options

By default, the media server stores image derivatives on the image derivatives disk. This can be turned off, so they will always be re-generated on
demand instead:

```dotenv
TRANSMORPHER_STORE_DERIVATIVES=true
```

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

Media is uniquely identified by a string identifier which is passed when uploading media. This identifier is unique per user.

When media is uploaded on the same identifier by the same user, a new version will be created.

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

## Video transcoding

Video transcoding is handled as an asynchronous task. The client will receive the
information about the transcoded video as soon as it completes. For this, a signed request is sent to the client.

***Note:*** Since video transcoding is a complex task it may take some time to complete. The client will also be notified about failed attempts.

To publicly access a video, the client name, the identifier and a format have to be specified. There are different formats available:

- HLS (.m3u8) `https://transmorpher.test/videos/<clientname>/<identifier>/hls/video.m3u8`
- DASH (.mpd) `https://transmorpher.test/videos/<clientname>/<identifier>/dash/video.mpd`
- MP4 (.mp4) `https://transmorpher.test/videos/<clientname>/<identifier>/mp4/video.mp4`

#### Representations and Codec

By default, these representations are generated:

```php
'representations' => [
    360, 480, 720, 1080, 1440, 2160
],
```

The X.264 codec is used by default:

```php
'video_codec' => 'x264',
```

***Note:*** Available representations or codecs are stated in the comment of the configuration value.

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

## License

The Transmorpher media server is licensed under the [MIT license](https://opensource.org/licenses/MIT).
