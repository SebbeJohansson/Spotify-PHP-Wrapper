<?php
    session_start();

    $files = array_filter(glob('*.php'));

    foreach($files as $file){
        echo "<li><a href='$file'>$file</a></li>";
    }


    $code = (isset($_GET['code'])) ? $_GET['code'] : $_SESSION['code'];
    var_dump($code);
    $_SESSION['code'] = $code;
    var_dump($_SESSION['code']);

    ?>

<html>

<head>
    <!-- jQuery library -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

    <script>
        function doSomething(something){
            $.ajax({
                type: "GET",
                url: "includes/doSomething.php",
                data: "action="+something,
                success: function (response) {
                    if(something === "auth"){
                        window.location = response;
                    }
                    console.log(response);
                    //response = JSON.parse(response);
                }
            });
        }
    </script>

    <style>
        a{
            color: hotpink;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <a onclick="doSomething('auth')"><p>Auth</p></a>
    <a onclick="doSomething('checktoken')"><p>Check token</p></a>
    <a onclick="doSomething('user')"><p>Check User</p></a>
</body>

</html>
