<!DOCTYPE html>
<html>
<head>
	<title>Timer</title>

	<style type="text/css">
		body {
			margin: 0 auto;
			max-width: 40em;
			width: 88%;
		}

		#app {
			font-size: 2em;
			font-weight: bold;
		}
	</style>
</head>
<body>

	<h1>Timer</h1>

	<div id="app" aria-live="polite">5</div>

	<script>
		// Get the #app element
		let app = document.querySelector('#app');

		// Track the count
		let count = 5;

		/**
		 * Play the chime sound
		 */
		function playSound () {
			let ding = new Audio('/la-cucaracha-horn.mp3');
			ding.load();
            ding.play();
		}

		// Run a callback function once every second
		let cardevent = setInterval(function () {

			// Reduce count by 1
			count--;

			// Update the UI
			if (count > 0) {
				app.textContent = count;
			} else {
				app.textContent = '⏰';
				clearInterval(cardevent);
				playSound();
			}

		}, 1000);
	</script>

</body>
</html>