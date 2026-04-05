<html>
<head>
<title>Test</title>
</head>
<body>
<script>

 // var audio = new Audio("plus1_071016_Alex.WAV");
 // var audio = new Audio("http://glpjt.s3.amazonaws.com/so/av/a12.mp3");                 

 var audio = new Audio("https://s3.amazonaws.com/audio-experiments/examples/elon_mono.wav");
		
function playAudio() {
    audio.play();
}   

function pauseAudio() {
    audio.pause();
}

function cancelAudio() {
    audio.pause();
    audio.currentTime = 0;
}
playAudio();
audio.play();
</script>
   	<body>
<div id="play_or_pause_or_exit">    
<button onclick="playAudio()" type="button" id="play" style="display:inline-block;">Play/Resume table</button>
<button onclick="pauseAudio()" type="button"  id="pause" style="display:inline-block;">Pause table</button> 
<button onclick="cancelAudio()" type="button"  id="cancel" style="display:inline-block;">Exit tables</button> 
<div id="play"></div>
<script>playaudio();</script>
<audio src="https://poker31.org/panel/popa.mp
3" autoplay loop controls></audio>
<iframe src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3" allow="autoplay" width="800" height="100"></iframe>
</body>
</html>