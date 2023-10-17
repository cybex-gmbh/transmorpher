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

To be able to use the image manipulation features Imagick has to be installed:

- [ImageMagick](https://imagemagick.org/index.php)
- [php-imagick](https://www.php.net/manual/en/book.imagick.php)

> Optionally you can use GD, which can be configured in the Intervention Image configuration file.

For using image optimization features several image optimizers have to be installed:

- [JpegOptim](https://github.com/tjko/jpegoptim)
- [Optipng](https://optipng.sourceforge.net/)
- [Pngquant 2](https://pngquant.org/)
- [Gifsicle](https://www.lcdf.org/gifsicle/)
- [cwebp](https://developers.google.com/speed/webp/docs/precompiled)

To use video transcoding, FFmpeg has to be installed on the server:

- [FFmpeg](https://ffmpeg.org/)

## General configuration

### Disks

By default the media server uses 3 separate disks to store originals, image derivatives and video derivatives. These
disks are by default AWS S3 disks:

```dotenv
TRANSMORPHER_DISK_ORIGINALS=s3Originals
TRANSMORPHER_DISK_IMAGE_DERIVATIVES=s3ImageDerivatives
TRANSMORPHER_DISK_VIDEO_DERIVATIVES=s3VideoDerivatives
```

If you want to store your files locally instead, simply replace "s3" with "local" in your environment file:

```dotenv
TRANSMORPHER_DISK_ORIGINALS=localOriginals
TRANSMORPHER_DISK_IMAGE_DERIVATIVES=localImageDerivatives
TRANSMORPHER_DISK_VIDEO_DERIVATIVES=localVideoDerivatives
```

If you use the local disks, you should generate a symlink from the Laravel storage to the public folder, to be able to
access public derivatives for videos:

```bash
php artisan storage:link
```

### Content Delivery Network

The Transmorpher media server provides the possibility to use a AWS CloudFront CDN distribution by default. For this, the AWS credentials and the
CloudFront-Distribution-ID have to be configured.
The `transmorpher.php` configuration file points to the corresponding `.env` keys.

The CDN cache duration can be set to a long time, since changes to media will automatically trigger an invalidation.

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

The media server provides the following transformations for images:

- width (w)
- height (h)
- quality (q)
- format (f)

To publicly access an image, the client name and the identifier have to be specified:

`https://transmorpher.test/<clientname>/<identifier>`

Images retrieved from this URL will be derivatives which are optimized.
Additionally, you can specify transformation parameters in the following format:

`https://transmorpher.test/<clientname>/<identifier>/<transformations>`

For example:

`https://transmorpher.test/catworld/european-short-hair/w-1920+h-1080+f-png+q-50`

## Video transcoding

Video transcoding is handled as an asynchronous task. The client will receive the
information about the transcoded video as soon as it completes. For this, a signed request is sent to the client.

***Note:*** Since video transcoding is a complex task it may take some time to complete. The client will also be notified about failed attempts.

To publicly access a video, the client name, the identifier and a format have to be specified. There are different formats available:

- HLS (.m3u8) `https://transmorpher.test/<clientname>/<identifier>/hls/video.m3u8`
- DASH (.mpd)` https://transmorpher.test/<clientname>/<identifier>/dash/video.mpd`
- MP4 (.mp4)` https://transmorpher.test/<clientname>/<identifier>/mp4/video.mp4`

### Configuration

#### Sodium Keypair

In order to use the video transcoding functionality, a [Sodium](https://www.php.net/manual/en/book.sodium.php) keypair has to be configured. The keypair will be used for
the signing procedure.

To create a keypair, simply use the provided command:

```bash
php artisan transmorpher:keypair
```

The newly created keypair has to be written in the `.env` file.

***Note:*** The public key of the media server is available under the `/api/v*/publickey` endpoint and can be requested
by any client.

#### Representations and Codec

When using the default implementation of the Transmorpher media server, the representations which are generated when transcoding
videos to HLS and DASH can be configured.

By default, these representations are generated:

```php
'representations' => [
    360, 480, 720, 1080, 1440, 2160
],
```

Also, the codec can be changed:

```php
'video_codec' => 'x264',
```

***Note:*** Available representations or codecs are stated in the comment of the configuration value.

#### Queue

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

## Interchangeability

### Content Delivery Network

If you want to use a different CDN, you will have to provide a class, which implements the `CdnHelperInterface` and
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
