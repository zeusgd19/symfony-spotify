const $player = $('#player');
const $playSvg = $('#play-svg');
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


