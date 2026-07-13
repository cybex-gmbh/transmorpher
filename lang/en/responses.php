<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Response Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the responses which are returned to a
    | client.
    |
    */
    'cdn' => [
        'invalidation' => [
            'failed' => 'CDN invalidation failed.',
        ]
    ],

    'validation' => [
        'file' => [
            'name' => [
                'invalid' => 'File name may not contain the following characters: :disallowedCharacters.',
                'invalid_only_spaces' => 'File name may not only consist of spaces.',
            ]
        ],
        'identifier' => [
            'non_matching' => 'The provided identifier does not match the identifier of the reserved upload slot.',
        ],
    ],

    'upload' => [
        'aborted' => 'Upload aborted.',
        'slot' => [
            'created' => 'Successfully created upload slot.',
            'failed' => 'Upload slot creation failed.',
        ],
        'image' => [
            'success' => 'Successfully uploaded new image version.'
        ],
        'video' => [
            'success' => 'Successfully uploaded new video version, transcoding job has been dispatched.'
        ],
        'document' => [
            'success' => 'Successfully uploaded new document version.'
        ],
    ],

    'image' => [
        'version' => [
            'set' => [
                'success' => 'Successfully set image version.',
            ]
        ]
    ],

    'video' => [
        'transcoding' => [
            'success' => 'Successfully transcoded video.',
            'aborted' => 'Transcoding process aborted due to a new version or upload.',
            'failed' => 'Video transcoding failed, version has been removed.',
            'job_dispatch_failed' => 'There was an error when trying to dispatch the transcoding job.',
        ],
        'version' => [
            'set' => [
                'success' => 'Successfully set video version, transcoding job has been dispatched.',
            ]
        ]
    ],

    'document' => [
        'version' => [
            'set' => [
                'success' => 'Successfully set document version.',
            ]
        ]
    ],

    'media' => [
        'versions' => [
            'retrieved' => 'Successfully retrieved version numbers.',
        ],
        'deletion' => [
            'success' => 'Successfully deleted media.'
        ]
    ],

    'disk' => [
        'write' => [
            'failed' => 'Could not write media to disk.'
        ]
    ],
];
