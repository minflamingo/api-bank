<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>2FA Authenticator</title>
    <script>
    var interval;
    var currentKey = '';
    var lastTimeSlice = -1;

    function generateCode() {
        currentKey = document.getElementById('key').value;
        if (currentKey.trim() === '') {
            alert('Please enter a setup key.');
            return;
        }

        document.getElementById('codeResult').innerText = '';
        clearInterval(interval);
        fetchCode(currentKey);
        interval = setInterval(checkForNewCode, 1000);
    }

    function fetchCode(key) {
        var timeSlice = getCurrentTimeSlice();
        if (timeSlice !== lastTimeSlice) {
            lastTimeSlice = timeSlice;
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    document.getElementById('codeResult').innerText = 'Your code: ' + this.responseText;
                }
            };
            xhttp.open('GET', 'authenticator.php?key=' + encodeURIComponent(key), true);
            xhttp.send();
        }
    }

    function getCurrentTimeSlice() {
        var epoch = Math.floor(new Date().getTime() / 1000.0);
        return Math.floor(epoch / 30);
    }

    function checkForNewCode() {
    var currentTime = new Date();
    var secondsSinceEpoch = Math.floor(currentTime.getTime() / 1000);
    var remainingSeconds = 30 - (secondsSinceEpoch % 30);
    document.getElementById('timer').innerText = 'Time remaining: ' + remainingSeconds + 's';

    if (remainingSeconds == 30 || remainingSeconds == 0) {
        fetchCode(currentKey); // Fetch a new code at the start of a new 30-second cycle
    }
}

</script>

	
	
	
	
	


</head>
<body>

    <label for="key">Enter your setup key:</label>
    <input type="text" id="key" name="key" required>
    <button onclick="generateCode()">Generate Code</button>
    <p id="codeResult"></p>
    <p id="timer"></p>


</body>
</html>
