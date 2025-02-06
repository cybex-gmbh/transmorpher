<?php

namespace App\Http\Controllers\V1;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\User;
use App\Models\Version;
use Delivery;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PdfController extends Controller
{
    /**
     * Handles incoming image derivative requests.
     *
     * @param User $user
     * @param Media $media
     * @param string $transformations
     *
     * @return Application|ResponseFactory|Response
     */
    public function get(User $user, Media $media, string $transformations = ''): Response|Application|ResponseFactory
    {
        return $this->getDerivative($transformations, $media->currentVersion);
    }

    /**
     * Retrieve an original image for a version.
     *
     * @param Request $request
     * @param Media $media
     * @param Version $version
     * @return Application|Response|ResponseFactory
     */
    public function getOriginal(Request $request, Media $media, Version $version): Response|Application|ResponseFactory
    {
        return Delivery::getOriginal($version);
    }

    /**
     * Retrieve a derivative for a version.
     *
     * @param Request $request
     * @param Media $media
     * @param Version $version
     * @param string $transformations
     * @return Response|Application|ResponseFactory
     */
    public function getDerivativeForVersion(Request $request, Media $media, Version $version, string $transformations = ''): Response|Application|ResponseFactory
    {
        return $this->getDerivative($transformations, $version);
    }

    /**
     * @param string $transformations
     * @param Version $version
     * @return Application|ResponseFactory|Response
     */
    protected function getDerivative(string $transformations, Version $version): ResponseFactory|Application|Response
    {
        return Delivery::getDerivative($transformations, $version, MediaType::PDF);
    }
}
