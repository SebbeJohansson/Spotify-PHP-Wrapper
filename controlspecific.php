<?php

    session_start();
    error_reporting(E_ALL);
    ini_set('display_errors','On');

    require_once("classes/SpotifyWrapper.php");

    use SpotifyWrapper\SpotifyWrapper as Spotify;


    $refreshtoken = "";
    $clientId = "";
    $clientSecret = "";
    $redirect_uri = "";

    if(isset($_GET['code'])){
        $spotify = new Spotify($clientId, $clientSecret, $redirect_uri, $_GET['code']);
        if(isset($_SESSION['token'])){
            $spotify->accessToken = $_SESSION['token'];
            $spotify->refreshToken = $_SESSION['refresh'];
            $spotify->validUntil = $_SESSION['validuntil'];
            $spotify->checkToken();
            var_dump($spotify->getMultipleArtists(array("2NqeFgy0ual6Abk5hd0xxi")));
            var_dump($spotify->setCurrentPlayback());

            //var_dump($spotify->getNewReleases());
            //$spotify->setPlaybackVolume(101);
            //echo "devices: "; var_dump($spotify->getAvailableDevices());
            //$spotify->playSong($spotify->getAvailableDevices()[0]['id'], array('spotify:track:44y7WtFqzaCW3j8MTA2pfy'), "spotify:album:3ck3Tj8c6B7TA6ugtaQsyj");
            //$spotify->startPlayBack($spotify->getAvailableDevices()[0]['id']);
            //$spotify->setPlaybackPosition(500000);
        }else{
            $spotify->setToken();
            if($spotify->response['successful']){
                header("Location: $redirect_uri");
            }
        }
        var_dump($spotify->response);

        $_SESSION['token'] = $spotify->accessToken;
        $_SESSION['refresh'] = $spotify->refreshToken;
        $_SESSION['validuntil'] = $spotify->validUntil;
    }else{
        $spotify = new Spotify($clientId, $clientSecret, $redirect_uri);
        $spotify->accessToken = $_SESSION['token'];
        $spotify->refreshToken = $_SESSION['refresh'];
        $spotify->validUntil = $_SESSION['validuntil'];
        if (isset($_POST['uri'])){
            echo "uri";
            $spotify->checkToken();
            echo $_POST['uri'];
            $spotify->setCurrentPlayback();
            var_dump($spotify->getAvailableDevices()[0]['id']);
            var_dump($spotify->currentPlayBack['context']['uri']);
            $spotify->playSong(array($_POST['uri']));
            //var_dump($spotify->response);
        }else{
            $spotify->authUser($clientId, $redirect_uri);
        }

        $_SESSION['token'] = $spotify->accessToken;
        $_SESSION['refresh'] = $spotify->refreshToken;
        $_SESSION['validuntil'] = $spotify->validUntil;
    }

?>

<form action="controlspecific.php" method="post">
    <input type="text" name="uri"/>
    <input type="submit" value="Submit"/>
</form>
