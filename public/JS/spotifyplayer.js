async function transferPlaybackHere(deviceId, token) {
    await fetch("https://api.spotify.com/v1/me/player", {
        method: "PUT",
        headers: {
            "Authorization": `Bearer ${token}`,
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ device_ids: [deviceId], play: true })
    });
}

async function getSpotifyToken() {
    const response = await fetch('/spotifyToken');
    const data = await response.json();

    if (data.token) {
        return data.token;
    } else {
        console.error('Error getting token:', data.error);
        return null;
    }
}


window.onSpotifyWebPlaybackSDKReady = async () => {
    const token = await getSpotifyToken()
    const player = new Spotify.Player({
        name: 'My Web Player',
        getOAuthToken: cb => {cb(token);}
    });

    player.addListener("ready", ({ device_id }) => {
        console.log("Dispositivo listo:", device_id);
        transferPlaybackHere(device_id, token);
    });

    player.connect().then(success => {
        if (success) {
            console.log('Connected to Spotify Player!');
        }
    });
};