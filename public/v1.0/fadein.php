<!DOCTYPE html>
<html>
<head>
    <title>Notification Example</title>
    <style>
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            display: none;
            z-index: 1000;
        }
    </style>
</head>
<body>

<div id="notification" class="notification-container">
    Thông báo của bạn ở đây!
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
$(document).ready(function(){
    $("#notification").fadeIn("slow").delay(3000).fadeOut("slow");
});
</script>

</body>
</html>
