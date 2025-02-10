(function ($) {
  $.fn.audioWave = function (options) {
    var settings = $.extend({
      audioElement: null,
      waveColor: '#00f',
      barWidth: 2,
      barSpacing: 1,
    }, options);

    if (!settings.audioElement || $(settings.audioElement).length === 0) {
      console.error('audioWave: No se encontró el elemento de audio.');
      return this;
    }

    var audio = $(settings.audioElement)[0];
    var canvas = this[0];
    var ctx = canvas.getContext('2d');
    var audioCtx = null
    var analyser = null
    var animationId = null;
    var source = null; // Variable para el nodo MediaElementSourceNode

    function initializeAudioContext() {
      if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioCtx.createAnalyser();
        analyser.fftSize = 256;
      }
    }

    function createMediaSource() {
      try {

        // Si el nodo ya existe, no lo volvemos a crear
	console.log(source)
        if (!source) {
          source = audioCtx.createMediaElementSource(audio);
          source.connect(analyser);
          analyser.connect(audioCtx.destination);
      	}
	} catch (e) {
        console.error('audioWave: No se pudo conectar el audio.', e);
      }
    }

    var bufferLength = 256;
    var dataArray = new Uint8Array(bufferLength);

    function drawWave() {
      if (!analyser) return;

      analyser.getByteFrequencyData(dataArray);

      ctx.clearRect(0, 0, canvas.width, canvas.height);
      var barWidth = settings.barWidth;
      var barSpacing = settings.barSpacing;
      var x = 0;

      dataArray.forEach(function (value) {
        var barHeight = (value / 255) * canvas.height;
        ctx.fillStyle = settings.waveColor;
        ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
        x += barWidth + barSpacing;
      });

      animationId = requestAnimationFrame(drawWave);
    }

    function stopDrawing() {
      if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
      }
    }

    audio.addEventListener('play', function () {
      initializeAudioContext();
      if (audioCtx.state === 'suspended') {
        audioCtx.resume();
      }
      createMediaSource();
      drawWave();
    });

    audio.addEventListener('pause', function () {
      stopDrawing();
      if (audioCtx && audioCtx.state === 'running') {
        audioCtx.suspend();
      }
    });

    audio.addEventListener('ended', function () {
      stopDrawing();
      if (audioCtx && audioCtx.state === 'running') {
        audioCtx.suspend();
      }
    });

    return this;
  };
}(jQuery));
