export function updateMediaSession(image,title,artists,album) {
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: title,
                artist: artists,
                album: album,
                artwork: [
                    {src: image, sizes: '96x96', type: 'image/png'},
                    {src: image, sizes: '128x128', type: 'image/png'},
                    {src: image, sizes: '192x192', type: 'image/png'},
                    {src: image, sizes: '256x256', type: 'image/png'},
                    {src: image, sizes: '384x384', type: 'image/png'},
                    {src: image, sizes: '512x512', type: 'image/png'}
                ]
            });
        }
    }