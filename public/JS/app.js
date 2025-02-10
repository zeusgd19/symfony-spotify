import {updateMediaSession} from './mediaSession.js'

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

let player;
let isConnected = false;

window.onSpotifyWebPlaybackSDKReady = async () => {
    const token = await getSpotifyToken()
    player = new Spotify.Player({
        name: 'Spotify Clone Web',
        getOAuthToken: cb => {cb(token);},
        enableMediaSession: true
    });

    player.addListener("ready", ({ device_id }) => {
        console.log("Dispositivo listo:", device_id);
        transferPlaybackHere(device_id, token);
    });
};

$(document).ready(async function () {

    const $playlistUl = $('#playlists');
    const $hamburguer = $('#hamburguer');
    const $library = $('#library');
    const $menu = $('#menu');
    const $app = $('#app');
    const $aside = $('aside');
    let $songsUl = $('#songs');
    const $player = $('#player');
    const $playSvg = $('#play-svg');
    const $slider = $('#slider');
    const dialog = document.querySelector('.dialog-overview');
    const closeButton = dialog.querySelector('sl-button[slot="footer"]');
    closeButton.addEventListener('click', () => dialog.hide());

    const main = await $.ajax({
        type: 'GET',
        url: '/'
    })
    let position = 0;
    let duration = 0;
    let interval;

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

    async function playTrack(trackUri, token) {
        try {
          const response =  await fetch("https://api.spotify.com/v1/me/player/play", {
                method: "PUT",
                headers: {
                    "Authorization": `Bearer ${token}`,
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({uris: [trackUri]})
            });

            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }
        }catch (e) {
        	await fetch(`https://spotifyclone.com:3000/preview/${trackUri.substring(trackUri.indexOf('track:') + 6,trackUri.length)}`)
        }
    }

    let $tiempoTotal = $('#tiempoTotal');
    let isMarqueed = false;
    let isPlaying = false;

    let playLists = [];
    let songs = [];

    adjustItems();
    $(window).on("resize", adjustItems);

    $(document).on('click','.artist, .album', async function (){
        const logged = await checkLoginStatus();

        if(!logged){
            dialog.show();
            return;
        } else{

        }
    })

    async function checkLoginStatus() {
            try {
                const response = await $.ajax({
                    type: 'GET',
                    url: '/api/user'
                });

                let userData = await response;
                return !!userData;
            } catch (e){
                return false;
            }
    }

    $(document).on('click',".songSearched",async function(){
        if(!isConnected){
            player.connect().then(success => {
                if (success) {
                    console.log('Connected to Spotify Player!');
                    isConnected = true;
                }
            });
        }
        const $audio = $('#song');
        $audio.pause();
        $audio.remove();
        const token = await getSpotifyToken();
        console.log($(this).data('id'))
        /*
        const duracion = $(this).data('duration');
        const minutos = Math.floor(duracion / 60000);
        const segundos = Math.floor(duracion % 60000).toString().padStart(2, '0');
        const segundosSubstring = segundos.substring(0,2);
        $slider.attr("max",duracion / 1000);
        console.log($slider.val())
        intervalSpotifyTime = new resumableInterval(function (){
            $slider.val(tiempo++);
            segundosRecorridos++;
            if(segundosRecorridos >= 60){
                segundosRecorridos = 0;
                minutosRecorridos++;
            }
            let minutosRecorridosString = minutosRecorridos.toString().padStart(2,'0')
            let segundosRecorridosString = segundosRecorridos.toString().padStart(2,'0');
            $('#tiempoRecorrido').text(`${minutosRecorridosString}:${segundosRecorridosString}`);
        },1000);
        $tiempoTotal.text(`${minutos}:${segundosSubstring}`);
        */
        const response = playTrack(`spotify:track:${$(this).data('id')}`,token);

        response.then(data => {
            if(data.toString().indexOf('https://p.scdn.co/')){
                const $audio = $('<audio>', {
                    id: 'song',
                    src: data,
                    preload: 'metadata'
                });

                $player.append($audio);

                $audio.on('loadedmetadata', function () {
                    const duracion = $audio[0].duration;
                    const minutos = Math.floor(duracion / 60);
                    const segundos = Math.floor(duracion % 60).toString().padStart(2, '0');

                    $tiempoTotal.text(`${minutos}:${segundos}`);
                });

                $audio.on('canplay', function () {
                    $audio[0].play();
                    isPlaying = true;
                    $playSvg.attr('d', 'M6 5h4v14H6zm8 0h4v14h-4z');

                    if(window.innerWidth > 480) {
                        $('#equalizer').audioWave({
                            audioElement: '#song',
                            waveColor: '#08ff00',
                            barWidth: 3,
                            barSpacing: 7,
                        });

                        if(!isMarqueed) {
                            $('#artists-card-song').marquee({
                                speed: 50,
                                allowCss3Support: true,
                                css3easing: 'linear',
                                delayBeforStart: -1,
                                easing: 'linear',
                                direction: 'left',
                                gap: 100
                            });

                            isMarqueed = true;
                        }
                    }
                });

            }
        })

        player.addListener('player_state_changed', (state) => {
            if (!state) return;
            console.log(state);
            if(isConnected) {
                isPlaying = !state.paused;
                position = state.position / 1000; // Convertir a segundos
                duration = state.duration / 1000;

                // Actualizar icono de play/pausa
                if (state.paused) {
                    $playSvg.attr('d', 'M8 5.14v14l11-7-11-7z');
                    $('.marquee').marquee('pause');
                } else {
                    $playSvg.attr('d', 'M6 5h4v14H6zm8 0h4v14h-4z');
                    const minutos = Math.floor(duration / 60);
                    const segundos = Math.floor(duration % 60).toString().padStart(2, '0');
                    $tiempoTotal.text(`${minutos}:${segundos}`);

                    const $img = $(`<img id="image-song-card" src="${state.track_window.current_track.album.images[0].url}" alt="Imagen Portada"/>`);
                    const $title = $(`<p id="title-card-song">${state.track_window.current_track.album.name}</p>`)
                    const $artist = $(`<p id="artists-card-song" class="marquee">${state.track_window.current_track.artists[0].name}</p>`)
                    const $div = $('<div></div>')
                    isMarqueed = false;
                    $('#song-card').empty();
                    $('#song-card').append($div)
                    $('#song-card').prepend($img).find('div').append($title).append($artist);

                    if (!isMarqueed) {
                        $('#artists-card-song').marquee({
                            speed: 50,
                            allowCss3Support: true,
                            css3easing: 'linear',
                            delayBeforStart: -1,
                            easing: 'linear',
                            direction: 'left',
                            gap: 100
                        });

                        isMarqueed = true;
                    }
                }

                updateProgressBar();

                if (isPlaying) {
                    if (interval) clearInterval(interval);
                    interval = setInterval(() => {
                        position += 1;
                        updateProgressBar();
                        console.log("hola")
                    }, 1000);
                } else {
                    clearInterval(interval);
                }
            }
        });
    })

    function updateProgressBar() {
        const minutos = Math.floor(position / 60);
        const segundos = Math.floor(position % 60).toString().padStart(2, '0');

        $('#tiempoRecorrido').text(`${minutos}:${segundos}`);
        $slider.attr("max", duration);
        $slider.val(position);


    }

    $('#back-home').on('click',function(){
        if(window.location.pathname !== "/"){
            $('#search').val('');
            $('main').empty();
            $('main').append(main);
            $songsUl = $('main').find('#songs');
            window.history.pushState({}, '', '/');
        }
    })

    $('[data-dropdown]').on('click', function(e) {
        e.preventDefault();
        $($(this).data('dropdown')).toggle();
        if($($(this).data('dropdown')).css('opacity') == 0){
            $($(this).data('dropdown')).css({opacity: 100})
        }

    });

    function adjustItems() {
        let $albums = $(".album");
        let $artists = $(".artist-popular");

        $artists.each(function(index) {
            let hidden = false;
            let rect = this.getBoundingClientRect();

            if (rect.right >= window.innerWidth - 40) {
                $artists.slice(index + 1).hide();
                hidden = true;
            } else {
                $(this).show();
            }

            if (hidden) return false;
        });

        $albums.each(function(index) {
            let hidden = false;
            let rect = this.getBoundingClientRect();

            if (rect.right >= window.innerWidth - 40) {
                $albums.slice(index + 1).hide();
                hidden = true;
            } else {
                $(this).show();
            }

            if (hidden) return false;
        });
    }


    function debounce(func, delay) {
        let timer;
        return function () {
            let context = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(() => func.apply(context, args), delay);
        };
    }

    function setArtistsAndSongSearched(artists,tracks){
        $('#artists-list').empty();
        $('#artists-list-populars').empty();
        $('#artistsUser').empty();
        $('#songs-list').empty();
        if(artists){
            artists = artists.items;
            artists.forEach(artist => {
                const image = artist.images.length !== 0 ? artist.images[0].url : "/img/defectImg.svg";
                const $li = $(`<li class="artist" data-id="${artist.id}">
                        <div class="artist-img-back">
                            <img data-src="${image}" alt="${artist.name}" class="artist-img lazy-load">
                        </div>
                        <p>${artist.name}</p>
                        <p>Artista</p>`
                );

                $('#artists-list').append($li);
            })
        }

        if(tracks){
            tracks = tracks.items
            tracks.forEach(track => {
                const image = track.album.images.length !== 0 ? track.album.images[0].url : "/img/defectImg.svg";
                const artist = track.artists.length !== 0 ? track.artists[0].name: "";
                const $li = $(`<li class="songSearched" data-id="${track.id}" data-duration="${track.duration_ms}">
                            <div class="song-img-back">
                                <img data-src="${image}" alt="${track.name}" class="song-img lazy-load">
                            </div>
                            <div>
                                <p>${track.name}</p>
                                <p>${artist}</p>
                            </div>`
                );
                $('#songs-list').append($li);
            })
        }
    }

    $('#search').on('input', debounce(function() {
        let query = $(this).val().trim();
        window.history.pushState({}, '', '/search/' + query);
        if (query.length > 2) {
            $.ajax({
                url: '/query',
                type: 'POST',
                data: { q: query },
                success: function(response) {
                    const { artists, tracks } = response;
                    console.log(tracks)
                    $.ajax({
                        url: '/search',
                        type: 'GET',
                        success: function (data) {
                            $('main').empty();
                            $('main').append(data);
                            $(document).on('click','.options a',function() {
                                let clicked = $(this)
                                $('.options a').each(function (){
                                console.log($(this))
                                    if($(this) != clicked){
                                        $(this).removeClass('active');
                                    }
                                })
                                $(this).addClass('active');
                                let limit = $(this).data('type') == "all" ? null : 50
                                getSearchedOption($(this).data('type'),query,limit);
                            })
                            setArtistsAndSongSearched(artists, tracks);
                        }
                    })
                },
                error: function(xhr) {
                    console.error('Error en la búsqueda:', xhr.responseText);
                }
            });
        }
    }, 500));


    function getSearchedOption(option,query,limit = null){
        let type = option == "all" ? null : option == "artists" ? 'artist' : 'track';
        console.log(option)
        let artists;
        let tracks;
        $.ajax({
            type: 'POST',
            url: '/query',
            data: { q: query, type: type, limit: limit},
            success: function(data){
                if(option == "all"){
                      artists = data.artists;
                      tracks = data.tracks;
                } else if(option == "artists"){
                      artists = data.artists;
                } else {
                     tracks = data.tracks;
                }
                $.ajax({
                    url: '/search',
                    type: 'GET',
                    success: function (data) {
                        $('main').empty();
                        $('main').append(data);
                        setArtistsAndSongSearched(artists, tracks);
                    }
                })
            }
        })
    }

    function renderPlayLists() {
        adjustItems();
        if (!$menu.hasClass('hidden')) {
            $playlistUl.empty();
            playLists.forEach(playList => {
                const $li = $(`<li data-album-id="${playList.albumId}" data-id="${playList.id}">
                        <img src="${playList.cover}" alt="Cover playlist">
                        <div class="playlistInfo">
                            <p>${playList.title}</p>
                            <p>Artists: ${playList.artists.join(', ')}</p>
                        </div>
                    </li>`);
                $playlistUl.append($li);
            });
        }
        /*else if(window.innerWidth <= 480) {
            $songsUl.empty();
            playLists.forEach(playList => {
                const $li = $(`<li data-album-id="${playList.albumId}" data-id="${playList.id}">
                        <img src="${playList.cover}" alt="Cover playlist">
                        <div class="playlistInfo">
                            <p>${playList.title}</p>
                            <p>Artists: ${playList.artists.join(', ')}</p>
                        </div>
                    </li>`);
                $songsUl.append($li);
            });
        }

         */
    }

    $.ajax({
        type: 'GET',
        url: '/api/playlists',
        success: function (data) {
            playLists = data;
            renderPlayLists()
        },
        error: function (error) {
            console.log(error)
        }
    })

    $('.library-mobile').on('click',function (ev){
        ev.preventDefault();
        $('#artists-popular').empty();
        $('#albums-popular').empty();
        $('#artistsUser').empty();
        $songsUl.empty();
        playLists.forEach(playList => {
            const $li = $(`<li data-album-id="${playList.albumId}" data-id="${playList.id}">
                        <img src="${playList.cover}" alt="Cover playlist">
                        <div class="playlistInfo">
                            <p>${playList.title}</p>
                            <p>Artists: ${playList.artists.join(', ')}</p>
                        </div>
                    </li>`);
            $songsUl.append($li);
        });
    })

    // Función para actualizar el DOM del menú de la aplicación
    function updateHamburguerMenu(ev) {
        $library.toggleClass('hidden');
        $menu.toggleClass('hidden');

        if ($menu.hasClass('hidden')) {
            $app.css('grid-template-columns', '50px 1fr');
            $aside.css('width', '50px');
            $hamburguer.css('position', 'absolute');
            $('main').css({
                overflow: ''
            })
            adjustItems();
        } else {
            $app.css('grid-template-columns', '350px 1fr');
            $aside.css('width', '350px');
            $library.css('margin-left', '50px');
            $menu.css('margin-left', '50px');
            renderPlayLists();
        }
    }

    // Manejar el clic en el botón hamburguesa
    $hamburguer.on('click', updateHamburguerMenu);

    function renderSongs() {
        $('#artists-popular').empty();
        $('#albums-popular').empty();
        $('#artistsUser').empty();
        $songsUl.empty();
        songs.forEach(song => {
                const $li = $(`<li data-album-id="${song.albumId}" data-song-id="${song.id}" data-title="${song.title}" data-album="${song.album}">
                        <img src="${song.image}" alt="Imagen cancion">
                        <div class="songInfo">
                            <p>${song.title}</p>
                            <p id="artists">Artists: ${song.artists.join(', ')}</p>
                        </div>
                    </li>`);
                $songsUl.append($li);
        });

        $playSvg.attr('d', 'M8 5.14v14l11-7-11-7z');
    }

    // Función para actualizar el DOM de la lista de canciones
     function setSongsForPlayList(ev) {
        if(!$(ev.target).closest("li").data('title')){
            const $liElement = $(ev.target).closest('li');

            $.ajax({
                type: 'POST',
                url: '/api/songs',
                data: JSON.stringify({PlayListId: $liElement.data('id')}),
                success: function (data){
                    songs = data;
                    renderSongs();
                },
                error: function(error){
                    console.log(error);
                }
            })

            if ($liElement.length) {
                $songsUl.empty();
                renderSongs();
            }
        } else {
            setAudioPlayerForSong(ev);
        }
    }

    // Manejar el click en cada playList
    if(window.innerWidth > 480){
        $playlistUl.on('click', 'li', setSongsForPlayList);
        $(document).on('click', '#songs li', setAudioPlayerForSong);
    } else {
        $songsUl.on('click', 'li', setSongsForPlayList);
    }

    // Función para actualizar el DOM del reproductor de audio
    function setAudioPlayerForSong(ev) {
        if(isConnected){
            player.disconnect();
            isConnected = false;
            console.log("hola")
            clearInterval(interval);
            updateProgressBar();
        }
        const $audioAnterior = $('#song');
        const $liElement = $(ev.target).closest('li');
        const $player = $('#player');
        const artists = $liElement.find('#artists').text().substring($liElement.find('#artists').text().indexOf(":") + 1,$liElement.find('#artists').text().length)
        const $img = $(`<img id="image-song-card" src="${$liElement.find('img').attr('src')}" alt="Imagen Portada"/>`);
        const $title = $(`<p id="title-card-song">${$liElement.data('title')}</p>`)
        const $artist = $(`<p id="artists-card-song" class="marquee">${artists}</p>`)
        const $div = $('<div></div>')
        isMarqueed = false;
        $('#song-card').empty();
        $('#song-card').append($div)
        $('#song-card').prepend($img).find('div').append($title).append($artist);

        const $audio = $('<audio>', {
            id: 'song',
            src: `music/${$liElement.data('album-id')}/${$liElement.data('title')}.mp3`,
            preload: 'metadata'
        });

        if ($audioAnterior.length) {
            $audioAnterior[0].pause();
            $audioAnterior[0].currentTime = 0;
            $audioAnterior.remove();
        }

        if ($liElement.length) {
            $player.append($audio);
        }

        $audio.on('loadedmetadata', function () {
            const duracion = $audio[0].duration;
            const minutos = Math.floor(duracion / 60);
            const segundos = Math.floor(duracion % 60).toString().padStart(2, '0');

            $tiempoTotal.text(`${minutos}:${segundos}`);
        });

        $audio.on('canplay', function () {
            $audio[0].play();
            isPlaying = true;
            $playSvg.attr('d', 'M6 5h4v14H6zm8 0h4v14h-4z');

            if(window.innerWidth > 480) {
                $('#equalizer').audioWave({
                    audioElement: '#song',
                    waveColor: '#08ff00',
                    barWidth: 3,
                    barSpacing: 7,
                });

                if(!isMarqueed) {
                    $('#artists-card-song').marquee({
                        speed: 50,
                        allowCss3Support: true,
                        css3easing: 'linear',
                        delayBeforStart: -1,
                        easing: 'linear',
                        direction: 'left',
                        gap: 100
                    });

                    isMarqueed = true;
                }
            }
        });

        updateMediaSession($liElement.find('img').attr('src'),$liElement.data('title'),artists,$liElement.data('album'));

        $audio.on('timeupdate', updateTime);
    }

    // Función para manejar el botón de reproducción
    function getPlayer() {
        const $audio = $('#song');
        if($audio.length > 0) {
            if (isPlaying) {
                isPlaying = false;
                $playSvg.attr('d', 'M8 5.14v14l11-7-11-7z');
                $audio[0].pause();
                $('.marquee').marquee('pause');
            } else {
                isPlaying = true;
                $audio[0].play();
                $playSvg.attr('d', 'M6 5h4v14H6zm8 0h4v14h-4z');
                $('.marquee').marquee('resume');
                $audio.on('timeupdate', updateTime);
            }
        } else {
            if(isPlaying){
                $playSvg.attr('d', 'M8 5.14v14l11-7-11-7z');
                player.pause().then(() => {
                    console.log('Paused!');
                });
            } else {
                $playSvg.attr('d', 'M6 5h4v14H6zm8 0h4v14h-4z');
                player.resume().then(() => {
                    console.log('Resumed!');
                });
            }
        }
    }

    // Manejar el click en el botón de reproducción
    $player.on('click', getPlayer);

    function updateTime(ev) {
        const tiempoActual = ev.target.currentTime;
        const minutos = Math.floor(tiempoActual / 60);
        const segundos = Math.floor(tiempoActual % 60).toString().padStart(2, '0');

        if(ev.target.paused){
            if(ev.target.currentTime >= ev.target.duration){
                ev.target.currentTime = 0;
            } else {
                $playSvg.attr('d', 'M8 5.14v14l11-7-11-7z');
            }
        } else {
            $playSvg.attr('d', 'M6 5h4v14H6zm8 0h4v14h-4z');
        }

        $('#tiempoRecorrido').text(`${minutos}:${segundos}`);
        $slider.attr('max', ev.target.duration);
        $slider.val(tiempoActual);
    }

    $slider.on('input', function (ev) {
        const $audio = $('#song');
        if($audio.length > 0) {
            $audio[0].currentTime = ev.target.value;
        } else {
            player.seek($(this).val() * 1000).then(()=> {
                console.log("Change Position")
            })
        }
    });


    $('#volume').on('input',function(){
        player.setVolume($(this).val())
        .then(() => {
            console.log('Volume Changed');
        })
    })
});
