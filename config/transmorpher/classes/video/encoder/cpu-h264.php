<?php

return [
    // -omit data streams (e.g. timecodes). Transcoding sometimes failed when data streams were not omitted.
    '-dn',
    //omit attachments (e.g. metadata files). Metadata should not be publicly available.
    '-map', '-0:t?',
    // omit subtitles. Subtitles would need an encoder configuration for DASH, and possibly HLS.
    '-sn',
];
