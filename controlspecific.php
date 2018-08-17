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
            //echo "devices: "; var_dump($spotify->getAvailableDevices());
            //$spotify->playSong($spotify->getAvailableDevices()[0]['id'], array('spotify:track:44y7WtFqzaCW3j8MTA2pfy'), "spotify:album:3ck3Tj8c6B7TA6ugtaQsyj");
            //$spotify->startPlayBack($spotify->getAvailableDevices()[0]['id']);
            $spotify->toggleShuffle();
        }else{
            $spotify->setToken();
            if($spotify->ajaxResponse['successful']){
                header("Location: $redirect_uri");
            }
        }
        var_dump($spotify->ajaxResponse);

        $_SESSION['token'] = $spotify->accessToken;
        $_SESSION['refresh'] = $spotify->refreshToken;
        $_SESSION['validuntil'] = $spotify->validUntil;
    }else{
        Spotify::authUser($clientId, $redirect_uri);
    }
