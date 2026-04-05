<head>
        <meta charset="UTF-8">
    </head>
    <body>
       
        <script>
            let utter = new SpeechSynthesisUtterance();
utter.lang = 'en-US';
utter.text = 'Hello World';
utter.volume = 0.5;

// event after text has been spoken
utter.onend = function() {
	alert('Speech has finished');
}

// speak
window.speechSynthesis.speak(utter);
</script>
    </body>
</html>```