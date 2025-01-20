$(document).ready(function () {

    const $playlistUl = $('#playlists');
    const $hamburguer = $('#hamburguer');
    const $library = $('#library');
    const $menu = $('#menu');
    const $app = $('#app');
    const $aside = $('aside');
    const $songsUl = $('#songs');
    const $player = $('#player');
    const $playSvg = $('#play-svg');
    const $slider = $('#slider');
    let $tiempoTotal = $('#tiempoTotal');

    let isPlaying = false;

    let playLists = [];
    let songs = [];
    $('#dropdown1').toggle();

    $('[data-dropdown]').on('click', function(e) {
        e.preventDefault();
        $($(this).data('dropdown')).toggle();
    });

    $.ajax({
        type: 'GET',
        url: '/api/playlists',
        success: function (data) {
            playLists = data;
            renderPlayLists()
            console.log(playLists)
        },
        error: function (error) {
            console.log(error)
        }
    })

    function renderPlayLists() {
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
        } else if(window.innerWidth <= 480) {
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
    }

    // Función para actualizar el DOM del menú de la aplicación
    function updateHamburguerMenu(ev) {
        $library.toggleClass('hidden');
        $menu.toggleClass('hidden');

        if ($menu.hasClass('hidden')) {
            $app.css('grid-template-columns', '50px 1fr');
            $aside.css('width', '50px');
            $hamburguer.css('position', 'absolute');
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
        $songsUl.empty();
        songs.forEach(song => {
                const $li = $(`<li data-album-id="${song.albumId}" data-song-id="${song.id}" data-title="${song.title}">
                        <img src="${song.image}" alt="Imagen cancion">
                        <div class="songInfo">
                            <p>${song.title}</p>
                            <p id="artists">Artists: ${song.artists.join(', ')}</p>
                        </div>
                    </li>`);
                $songsUl.append($li);
        });
        isPlaying = false;

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
                beforeSend: function(){
                    $songsUl.append('<img src="/img/loading.gif"/>')
                },
                success: function (data){
                    songs = data;
                    console.log(songs)
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
        $songsUl.on('click', 'li', setAudioPlayerForSong);
    } else {
        $songsUl.on('click', 'li', setSongsForPlayList);
    }
    // Función para actualizar el DOM del reproductor de audio
    function setAudioPlayerForSong(ev) {
        const $audioAnterior = $('#song');
        const $liElement = $(ev.target).closest('li');
        const $img = $(`<img id="image-song-card" src="${$liElement.find('img').attr('src')}" alt="Imagen Portada"/>`);
        const $title = $(`<p id="title-card-song">${$liElement.data('title')}</p>`)
        const $artist = $(`<p id="artists-card-song" class="marquee">${$liElement.find('#artists').text().substring($liElement.find('#artists').text().indexOf(":") + 1,$liElement.find('#artists').text().length)}</p>`)
        const $div = $('<div></div>')
        let marqueed = false;

        $('#song-card').empty();
        $('#song-card').append($div)
        $('#song-card').prepend($img).find('div').append($title).append($artist);

        const $audio = $('<audio>', {
            id: 'song',
            src: `music/${$liElement.data('album-id')}/${$liElement.data('title')}.mp3`,
            preload: 'metadata'
        });

        console.log($audio)
        console.log($audioAnterior)

        if ($audioAnterior.length) {
            $audioAnterior[0].pause();
            $audioAnterior[0].currentTime = 0;
            $audioAnterior.remove();
        }

        if ($liElement.length) {
            $liElement.append($audio);
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

                if(!marqueed) {
                    $('#artists-card-song').marquee({
                        speed: 50,
                        allowCss3Support: true,
                        css3easing: 'linear',
                        delayBeforStart: -1,
                        easing: 'linear',
                        direction: 'left',
                        gap: 100
                    });
                }
                marqueed = true;
            }
        });

        $audio.on('timeupdate', updateTime);
    }

    // Función para manejar el botón de reproducción
    function getPlayer(ev) {
        const $audio = $('#song');
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
    }

    // Manejar el click en el botón de reproducción
    $player.on('click', getPlayer);

    function updateTime(ev) {
        const tiempoActual = ev.target.currentTime;
        const minutos = Math.floor(tiempoActual / 60);
        const segundos = Math.floor(tiempoActual % 60).toString().padStart(2, '0');

        $('#tiempoRecorrido').text(`${minutos}:${segundos}`);
        $slider.attr('max', ev.target.duration);
        $slider.val(tiempoActual);

        $slider.on('input', function (ev) {
            const $audio = $('#song');
            $audio[0].currentTime = ev.target.value;
        });
    }
});
