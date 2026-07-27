# Transmorpher Media Server

A media server for images, pdfs and videos.

> For a client implementation for Laravel
> see [Laravel Transmorpher Client](https://github.com/cybex-gmbh/laravel-transmorpher-client).

> We offer a [Postman collection](postman.json) which features example calls for all API endpoints.
> Based on that you can implement your own client in any language you like.

> [!WARNING]
> The API version 1 is deprecated and will be removed in a future release. Please use API version 2.
>
> See the [Changelog](CHANGELOG.md) and [Upgrade Guide](#upgrade-guide) for more information.

### Table of Contents

- [Libraries used](#libraries-used)
    - [Image transformation and optimization](#image-transformation-and-optimization)
    - [PDF metadata removal](#pdf-metadata-removal)
    - [Video transcoding](#video-transcoding)
- [Concepts](#concepts)
    - [Identifiers](#identifiers)
    - [Versions](#versions)
    - [Originals and derivatives](#originals-and-derivatives)
    - [Media types](#media-types)
    - [Derivatives revision](#derivatives-revision)
- [Installation](#installation)
    - [Using docker](#using-docker)
        - [Configuration options](#configuration-options)
    - [Cloning the repository](#cloning-the-repository)
        - [Required software](#required-software)
        - [Generic workers](#generic-workers)
        - [Scheduling](#scheduling)
- [General configuration](#general-configuration)
    - [Basics](#basics)
    - [Disks](#disks)
    - [Sodium Keypair](#sodium-keypair)
    - [Email notifications](#email-notifications)
    - [Cloud Setup](#cloud-setup)
        - [Prerequisites for video functionality](#prerequisites-for-video-functionality)
        - [IAM](#iam)
        - [File Storage](#file-storage)
        - [Content Delivery Network](#content-delivery-network)
        - [Video specific configuration](#video-specific-configuration)
    - [Local disk setup](#local-disk-setup)
        - [Prerequisites for video functionality](#prerequisites-for-video-functionality-1)
        - [File Storage](#file-storage-1)
        - [Video specific configuration](#video-specific-configuration-1)
    - [Upload Handler](#upload-handler)
        - [Default Upload Handler](#default-upload-handler)
        - [S3 Multipart Upload Handler](#s3-multipart-upload-handler)
    - [Video transcoding](#video-transcoding-1)
        - [Bit rate](#bit-rate)
        - [Streaming Codec](#streaming-codec)
        - [GPU Acceleration](#gpu-acceleration)
    - [PDF configuration](#pdf-configuration)
        - [Metadata](#metadata)
        - [PPI](#ppi)
    - [Additional options](#additional-options)
- [Managing users](#managing-users)
- [Implementing a client](#implementing-a-client)
    - [Uploading media](#uploading-media)
        - [Upload Handler](#upload-handler-1)
        - [1) Reserve an upload slot](#1-reserve-an-upload-slot)
        - [2) Get a chunk upload URL](#2-get-a-chunk-upload-url)
        - [3) Upload chunks](#3-upload-chunks)
        - [4) Complete the upload](#4-complete-the-upload)
        - [5) Abort an upload](#5-abort-an-upload)
    - [Image transformation](#image-transformation)
    - [PDF handling](#pdf-handling)
        - [Images](#images)
    - [Video transcoding](#video-transcoding-2)
    - [Derivatives Revision](#derivatives-revision-1)
    - [Browser cache busting](#browser-cache-busting)
    - [Receiving signed notifications from the server](#receiving-signed-notifications-from-the-server)
- [Interchangeability](#interchangeability)
    - [Content Delivery Network](#content-delivery-network-1)
    - [Image Transformation](#image-transformation-1)
    - [Image Optimization](#image-optimization)
    - [Video Transcoding](#video-transcoding-3)
    - [Upload handler](#upload-handler-2)
- [Purging derivatives](#purging-derivatives)
- [Recovery](#recovery)
- [Development](#development)
    - [Testing](#testing)
    - [Docker image information](#docker-image-information)
        - [ImageMagick](#imagemagick)
        - [NVIDIA toolkit](#nvidia-toolkit)
    - [Pullpreview](#pullpreview)
        - [Companion App](#companion-app)
        - [Auth Token Hash](#auth-token-hash)
        - [Using your custom PullPreview environment](#using-your-custom-pullpreview-environment)
    - [Chunk a file in Artisan Tinker](#chunk-a-file-in-artisan-tinker)
- [Upgrade Guide](#upgrade-guide)
    - [v0.8.0 to v0.9.0](#v080-to-v090)
        - [For Docker image users](#for-docker-image-users)
    - [v0.7.0 to v0.8.0](#v070-to-v080)
- [License](#license)

### Libraries used

#### Image transformation and optimization

- [Intervention Image](https://github.com/Intervention/image)
- [Laravel Image Optimizer](https://github.com/spatie/laravel-image-optimizer)

#### PDF metadata removal

- [PDF Merge](https://github.com/karriereat/pdf-merge)

#### Video transcoding

- [PHP-FFmpeg-video-streaming](https://github.com/hadronepoch/PHP-FFmpeg-video-streaming)
- [PHP-FFMpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg)

## Concepts

### Identifiers

Each medium is identified by a unique string identifier, scoped per user.
You choose the identifier when uploading media for the first time and use it in all subsequent requests for that medium.

### Versions

Every time media is uploaded for an existing identifier, a new version is created.
Previous versions are retained and can be restored.
The latest processed version is always the one that is served to the public.

### Originals and derivatives

When media is uploaded, the original file is stored and never modified.
All files served to the public are derivatives, i.e. transformed or transcoded copies of the original.
For images and documents, derivatives are generated on demand and by default stored for subsequent requests.
For videos, derivatives are produced asynchronously by a transcoding worker.

### Media types

The media server supports three media types:

- **Image**: derivatives are generated and served synchronously on request.
- **Document**: PDF files; derivatives are generated and served synchronously on request.
- **Video**: derivatives are transcoded asynchronously, the client is notified when transcoding completes.

### Derivatives revision

The derivatives revision is defined as a counter that increments whenever derivatives are purged.
Clients can combine the revision with the media hash to build browser cache-busting URLs.
See [Purging derivatives](#purging-derivatives) for details.

## Installation

### Using docker

See the [Docker Hub repository](https://hub.docker.com/r/cybexwebdev/transmorpher) for images.

The Transmorpher Media Server comes with two images:

- `app`: The main application, which handles image and document processing.
- `transcoder`: The transcoding worker, which handles video processing.

Please check the [compose.prod.example.yml](compose.prod.example.yml) file for an example of a production configuration.

To not accidentally upgrade to a new breaking version, attach the version (replace "0.x" with a valid version in this example) you want to use to the image name:

`cybexwebdev/transmorpher:0.x-app`
`cybexwebdev/transmorpher:0.x-transcoder`

> [!IMPORTANT]
>
> The app and transcoder image need to match in version.

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

This environment variable has to be passed to the transcoding worker container in your compose.yml:

```yaml
environment:
    SERVICE_INSTANCES: ${VIDEO_TRANSCODING_WORKERS_AMOUNT:-1}
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
> This has not been tested.

Image optimization:

- [JpegOptim](https://github.com/tjko/jpegoptim)
- [Optipng](https://optipng.sourceforge.net/)
- [Pngquant](https://pngquant.org/)
- [Gifsicle](https://www.lcdf.org/gifsicle/)
- [cwebp](https://developers.google.com/speed/webp/docs/precompiled)

PDF handling:

- [Ghostscript](https://ghostscript.com/)

To use video transcoding:

- [FFmpeg](https://ffmpeg.org/)

#### Generic workers

Client notifications will be pushed onto the queue `client-notifications`.
You must set up 1 worker for this queue.

Email notifications will be pushed onto the queue `email`.
You may set up 1 worker for this queue, if you want to send emails.
See [Email notifications](#email-notifications) for more information.

#### Scheduling

There may be some cases (e.g. failed uploads) where chunk files are not deleted and stay on the local disk.
To keep the local disk clean, a command is scheduled hourly to delete chunk files older than 24 hours.

See the [`chunk-upload` configuration file](config/chunk-upload.php) for more information.

To run the scheduler, you will need to add a cron job that runs the `schedule:run` command on your server:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

For more information about scheduling, check the [Laravel Docs](https://laravel.com/docs/12.x/scheduling).

## General configuration

### Basics

1. Create an app key:

```bash
php artisan key:generate
```

2. Configure the database in the `.env` file.

3. Migrate the database:

```bash
php artisan migrate
```

### Disks

The media server must use 3 separate Laravel disks to store originals, image derivatives and video derivatives.
Use the provided `.env` keys to select the according disks in the `filesystems.php` config file.

> [!NOTE]
>
> 1. The root folder, like images/, of the configured derivatives disks has to always match the prefix provided by the `MediaType` enum.
> 2. If this prefix would be changed after initially launching your media server,
     clients would no longer be able to retrieve their previously uploaded media.

### Sodium Keypair

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

The public key of the media server is available under the `/api/v*/meta/publicKey` endpoint and can be requested by any client.

### Email notifications

If you want to send emails, you will need to configure a mail provider via the `MAIL_` `.env` keys.
For more information, check the [Laravel Mail documentation](https://laravel.com/docs/12.x/mail).

Available email notifications:

- New Api Version Notice: `php artisan mail:new-api-version-notice <newApiVersion>`
- Api Version Deprecation Notice `php artisan mail:deprecation-notice <deprecatedApiVersion>`

These will be sent to all users.

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
2. publish the Transmorpher media server under an additional domain that is not behind the CDN

#### Video specific configuration

*Content Delivery Network*

In the CDN routing create a new behavior which points requests starting with "/videos/*" to a new origin,
which is the video derivatives S3 bucket.

*Queue*

Transcoding jobs are dispatched onto the "video-transcoding" queue.
You can have these jobs processed on the main server or dedicated workers.
For more information, check the [Laravel Queue Documentation](https://laravel.com/docs/12.x/queues).

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

> [!WARNING]
> For the docker setup, to be able to deliver videos, the `Access-Control-Allow-Origin` header is currently set to '*'.
> This means every website can embed your videos.
>
> This applies to files in your /public/videos folder ending with .m3u8, .ts, .mpd, .m4s and .mp4.
> If you want to restrict this, you can mount your own configuration at `/etc/nginx/conf.d/default.conf.template`.
> The current config can be found at `docker/common/nginx.default.conf`.

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
For more information, check the [Laravel Queue Documentation](https://laravel.com/docs/12.x/queues).

You can define your queue connection in the `.env` file:

```dotenv
QUEUE_CONNECTION=database
```

> [!CAUTION]
>
> The database connection does neither guarantee FIFO nor prevent duplicate runs.
> It is recommended to use a queue which can guarantee these aspects, such as AWS SQS FIFO.
> To prevent duplicate runs with database, use only one worker process.

### Upload Handler

You can specify the upload handler via the `.env` key:

```dotenv
TRANSMORPHER_UPLOAD_HANDLER=s3-multi-part
```

Dependent on the upload handler, the upload flow and required payload can differ.
See the [Upload chunks section](#3-upload-chunks) for more information.

The Transmorpher offers two upload handlers out of the box:

#### Default Upload Handler

- can work with any Laravel disk
- handles chunked uploads for various request structures, see the Postman collection for an example
- will first receive the file on the server, and then move it to the configured disk

#### S3 Multipart Upload Handler

- the originals disk needs to be an S3 disk
- initiates a multipart upload on S3 and returns pre-signed URLs for each chunk
- the client can upload the chunks directly to S3, which is faster and saves bandwidth on the server
- uncompleted uploads will leave chunks on S3, which need to be cleaned up with an S3 lifecycle rule
- chunk size needs to be at least 5MiB (excluding the last chunk)

> [!IMPORTANT]
> When using S3 multipart uploads, configure an S3 lifecycle rule
> on the originals bucket to automatically abort incomplete multipart uploads after
> 24 hours.
>
> This mirrors the upload slot expiry and prevents abandoned multipart
> uploads from accumulating storage costs.

### Video transcoding

#### Bit rate

The bit rate for video transcoding can be set in the `.env` file in kilobits:

```dotenv
TRANSMORPHER_VIDEO_ENCODER_BITRATE=9000k
```

This setting will be ignored for the DASH/HLS streaming formats because they are calculated automatically.
For suitable bit rates, see: https://help.twitch.tv/s/article/broadcast-guidelines#recommended

#### Streaming Codec

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

#### GPU Acceleration

Videos may be transcoded using a machine's NVIDIA GPU.
This requires the according hardware and driver setup on the host machine.

- https://trac.ffmpeg.org/wiki/HWAccelIntro#NVENC
- https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/latest/install-guide.html

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

### PDF configuration

#### Metadata

Metadata can be removed optionally by setting the `.env` key:

```dotenv
TRANSMORPHER_DOCUMENT_REMOVE_METADATA=true
```

#### PPI

When an image format transformation is specified, an image of a page will be returned.
The ppi will be multiplied with the document dimensions, which results in the image resolution.
By default, 300 ppi is used.

Use the `.env` key to specify another default:

```dotenv
TRANSMORPHER_DOCUMENT_DEFAULT_PPI=600
```

### Additional options

By default, the media server stores image derivatives on the image derivatives disk.
This can be turned off, so they will always be re-generated on demand instead:

```dotenv
TRANSMORPHER_STORE_DERIVATIVES=true
```

There are additional settings in the `transmorpher.php` config file.

## Managing users

Media always belongs to a user. To easily create one, use the provided command:

```bash
php artisan create:user <name> <email> <api_url>
```

The server sends notifications to the api url, for example, video transcoding information.
For our standard Laravel client implementation, this is: `https://example.com/transmorpher/notifications`.

This command will provide you with a [Laravel Sanctum](https://laravel.com/docs/12.x/sanctum) token, which has to be
written in the `.env` file of a client system.
> The token should be passed for all API requests for authorization and is connected to the corresponding user.

If you need to re-generate a token for a user, use the provided command:

```bash
php artisan create:token <userId>
```

## Implementing a client

The media server provides the following features from client perspective:

Media specific:

- upload
- get original*
- get derivative
- get derivative for specific version*
- list versions
- set version
- delete

> Marked with * does not apply to videos.

Informative:

- get public key for verifying signed requests
- get current derivatives revision
- get upload handler

### Uploading media

The following examples use the `image` media type.
The other media types work the same way.

#### Upload Handler

See the [upload handler section](#upload-handler) for more information about the available upload handlers.

Dependent on the server's configuration, the upload handler can either be `default` or `s3-multi-part`.
Based on that, the upload flow can differ.
See below for details.

Before uploading, your client can check the configured upload handler:

```bash
curl -sS 'https://transmorpher.test/api/v2/meta/uploadHandler'
```

#### 1) Reserve an upload slot

At any time only 1 upload can be active for a specific identifier.

Reserve an upload slot for a media type, pass the media identifier and final filename:

```bash
curl -sS -X POST 'https://transmorpher.test/api/v2/image/upload/reserve' \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <sanctum-token>" \
  -H "Content-Type: application/json" \
  --data-raw '{"identifier":"example-media","filename":"example.jpg"}'
```

Example response:

```json
{
    "state": "initializing",
    "message": "Successfully created upload slot.",
    "identifier": "example-media",
    "upload_token": "<upload-token>"
}
```

The "upload_token" is required for all subsequent upload requests and is valid for 24 hours.

#### 2) Get a chunk upload URL

Request an upload URL for each chunk:

```bash
curl -sS 'https://transmorpher.test/api/v2/upload/<upload-token>/chunkUrl/<chunk-number>' \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <sanctum-token>"
```

Example response:

```json
{
    "url": "<chunk-upload-url>"
}
```

#### 3) Upload chunks

Based on the upload handler configured on the server, see the appropriate section.

##### default

The file has to be sent as multipart form-data to the chunk upload URL.
Additionally, a few form fields have to be sent:

```bash
curl -sS -X PUT 'https://transmorpher.test/api/v2/upload/<upload-token>' \
  -H "Accept: application/json" \
  -F 'file=@/path/to/chunk-1.part' \
  -F 'identifier=example-media' \
  -F 'chunkNumber=1' \
  -F 'totalChunks=4'
```

> [!NOTE]
> `chunkNumber` and `totalChunks` are optional for single-chunk uploads.

> [!NOTE]
> The `default` handler also supports dropzone-style request fields instead of `chunkNumber` and `totalChunks`.

Repeat steps 2 and 3 for all chunks in order.

##### s3-multi-part

The file has to be sent as binary data to the pre-signed URL.

> [!IMPORTANT]
> Due to AWS S3 limitations, each chunk except the last one must be at least `5MiB`.

```bash
curl -sS -X PUT '<chunk-upload-url>' \
  -H "Content-Type: application/octet-stream" \
  --data-binary '@/path/to/chunk-1.part'
```

Repeat steps 2 and 3 for all chunks in order.

#### 4) Complete the upload

After all chunks are uploaded, complete the upload:

```bash
curl -sS -X POST 'https://transmorpher.test/api/v2/upload/<upload-token>/complete' \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <sanctum-token>"
```

Example success response for images (documents are similar):

```json
{
    "state": "success",
    "message": "Successfully uploaded new image version.",
    "identifier": "example-media",
    "version": 1,
    "public_path": "images/<clientname>/example-media",
    "upload_token": "<upload-token>",
    "hash": "<hash>"
}
```

Example success response for videos (transcoding is asynchronous and a finished transcoding will send a notification):

```json
{
    "state": "processing",
    "message": "Successfully uploaded new video version, transcoding job has been dispatched.",
    "identifier": "example-media",
    "version": 1,
    "public_path": null,
    "upload_token": "<upload-token>",
    "hash": null
}
```

#### 5) Abort an upload

If an upload fails or is abandoned you need to abort the upload:

```bash
curl -sS -X DELETE 'https://transmorpher.test/api/v2/upload/<upload-token>' \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <sanctum-token>"
```

Example success response:

```json
{
    "state": "aborted",
    "message": "Upload aborted",
    "identifier": "example-media"
}
```

### Image transformation

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

### PDF handling

Requesting a PDF file will return the full document.

#### Images

When an image format transformation is specified, an image of a page will be returned.

By using the `p` transformation, you can specify the page to be exported.
By default, the first page will be used.

All available image transformations can also be applied to PDF image derivatives.
Requesting a PDF also follows the same URL structure as images, just replace `images` with `documents`.

Additionally, the pixels per inch can be specified with the `ppi` transformation.
The ppi will be multiplied with the document dimensions, which results in the image resolution.
By default, 300 ppi is used.

Example:

Document: `https://transmorpher.test/documents/catworld/cat-essay`

Image of page 5: `https://transmorpher.test/documents/catworld/cat-essay/f-jpg+p-5+w-1920+h-1080`

### Video transcoding

Video transcoding is handled as an asynchronous task.
Since video transcoding is a complex task, it may take some time to complete.

You will receive the information about the transcoded video as soon as it completes.
You will also be notified about failed attempts.
See [Receiving signed notifications from the server](#receiving-signed-notifications-from-the-server) for more information.

To publicly access a video, the client name, the identifier and a format have to be specified.
There are different formats available:

- HLS (.m3u8) `https://transmorpher.test/videos/<clientname>/<identifier>/hls/video.m3u8`
- DASH (.mpd) `https://transmorpher.test/videos/<clientname>/<identifier>/dash/video.mpd`
- MP4 (.mp4) `https://transmorpher.test/videos/<clientname>/<identifier>/mp4/video.mp4`

> [!IMPORTANT]
> The path beyond the identifier is by convention and has to be exactly as specified above.

Example success notification:

```json
{
    "state": "success",
    "message": "Successfully transcoded video.",
    "identifier": "example-media",
    "version": 1,
    "upload_token": "<upload-token>",
    "public_path": "videos/<clientname>/example-media",
    "hash": "<hash>",
    "notification_type": "video_transcoding"
}
```

### Derivatives Revision

The media server keeps its own revision counter for derivatives.
When the counter increases, all old derivatives have been deleted from the server.

When this happens you will receive a notification with the new revision number.
See [Receiving signed notifications from the server](#receiving-signed-notifications-from-the-server) for more information.

Example notification:

```json
{
    "notification_type": "cache_invalidation",
    "cache_invalidator": 2
}
```

To query the current revision, you can use the following endpoint:

```bash
curl -sS 'https://transmorpher.test/api/v2/meta/cacheInvalidator'
```

### Browser cache busting

When a media version has been processed, the response or client notification will include a `hash` for this version.
Use this hash in combination with the `derivatives revision` (see [Derivatives revision](#derivatives-revision) and [Purging derivatives](#purging-derivatives)),
and add both to the public URL.

For example:

`https://transmorpher.test/images/<clientname>/<identifier>?v=<derivatives_revision>_<hash>`
`https://transmorpher.test/images/<clientname>/<identifier>/<transformations>?v=<derivatives_revision>_<hash>`

### Receiving signed notifications from the server

For certain cases, such as finished or failed video transcodings, the server will send a signed notification to the `api_url` you have given to a media server admin.

Dependent on the notification type you can react to the notification.

> [!IMPORTANT]
> You will need some implementation of `libsodium` to verify the signature of the notification.

For this, you can use the public key of the media server:

```bash
curl -sS 'https://transmorpher.test/api/v2/meta/publicKey'
```

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

### Upload handler

You can create your own upload handler by implementing the `UploadHandlerInterface`, for example for the Azure Blob Storage.

You will need to add a config file for your handler in the `config/handler/upload` directory and specify the class name of your handler.

```php
return [
    'class' => Your\Upload\Handler\Class::class,
]
```

You can then set the `TRANSMORPHER_UPLOAD_HANDLER` environment variable to the name of your config file (without the `.php` extension) to use your handler.

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

The derivatives revision is available on the route `/api/v*/meta/cacheInvalidator`.

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

To start the docker containers for development, use the following command:

```bash
docker compose up -d
```

To start the containers with a GPU worker, use the following command:

```bash
docker compose -f compose.yml -f compose.nvidia.yml up -d
```

To connect to the application container:

```bash
docker compose exec app shell
```

To connect to other containers, such as the transcoding worker container, replace `app` with the service name in the `compose.yml` file, e.g. `transcoding-worker`

### Testing

You need to use the `testing` container:

```bash
docker compose exec testing shell
```

To run the tests:

```bash
php artisan test
```

> [!NOTE]
>
> Your IDE may have some kind of docker integration which allows you to run the tests directly from the IDE.
> When configuring this, make sure to connect to the docker container as `www-data` user, to prevent mismatching file permissions.

### Docker image information

#### ImageMagick

Due to issues with `ImageMagick 6` in combination with `Intervention Image v3`, we need to install `ImageMagick 7`.
This is already included in the base image.

#### NVIDIA toolkit

The transcoder image comes with the NVIDIA container toolkit pre-installed to enable GPU acceleration for video transcoding.

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

### v0.8.0 to v0.9.0

> [!WARNING]
> Breaking changes!

#### For Docker image users

- The base images have changed and need a new compose.yml definition.
    - The main application image was split into separate images for the application and the transcoding worker.
    - The application image no longer automatically starts workers or creates a cron for the scheduler.
        - This will now need to be set up in the compose.yml file.
        - Please refer to the [compose.prod.example.yml](compose.prod.example.yml) file for an example production setup

#### Client implementations

- V1 will be deprecated in the near future, please use v2 routes
- The upload process has changed
    - please refer to the [Implementing a client](#implementing-a-client)'s [uploading media](#uploading-media) section for details
    - please see the [Postman collection](postman.json) for example calls for all v2 routes

Following v2 routes have different URLs than their v1 equivalents:

| v2 route                                                                        | v1 route                                                                       |
|---------------------------------------------------------------------------------|--------------------------------------------------------------------------------|
| `POST /api/v2/image/upload/reserve`                                             | `POST /api/v1/image/reserveUploadSlot`                                         |
| `POST /api/v2/document/upload/reserve`                                          | `POST /api/v1/document/reserveUploadSlot`                                      |
| `POST /api/v2/video/upload/reserve`                                             | `POST /api/v1/video/reserveUploadSlot`                                         |
| `PATCH /api/v2/media/{media}/versions/{version}`                                | `PATCH /api/v1/media/{media}/version/{version}`                                |
| `GET /api/v2/image/{media}/versions/{version}/original`                         | `GET /api/v1/image/{media}/version/{version}/original`                         |
| `GET /api/v2/image/{media}/versions/{version}/derivative/{transformations?}`    | `GET /api/v1/image/{media}/version/{version}/derivative/{transformations?}`    |
| `GET /api/v2/document/{media}/versions/{version}/original`                      | `GET /api/v1/document/{media}/version/{version}/original`                      |
| `GET /api/v2/document/{media}/versions/{version}/derivative/{transformations?}` | `GET /api/v1/document/{media}/version/{version}/derivative/{transformations?}` |
| `GET /api/v2/meta/publicKey`                                                    | `GET /api/v1/publickey`                                                        |
| `GET /api/v2/meta/cacheInvalidator`                                             | `GET /api/v1/cacheInvalidator`                                                 |

### v0.7.0 to v0.8.0

- If not using the docker image:
    - PHP was upgraded from 8.2 to 8.4. Upgrade your server accordingly.
    - The `Intervention Image` package was upgraded from v2 to v3.
        - `ImageMagick` v6 may cause artifacts to appear in images when combined with `Intervention Image` v3.
          Therefore, you must upgrade `ImageMagick` to v7 and use a v7 compatible `Imagick` version.
    - If you want to send emails, set up a worker for the `email` queue.
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
