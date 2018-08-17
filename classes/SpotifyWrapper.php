<?php

    namespace SpotifyWrapper;

    /**
     * Class SpotifyWrapper
     * @package SpotifyWrapper
     */
    class SpotifyWrapper{


        public $ajaxResponse = array('successful' => false, 'errors' => array(), 'statusmessage' => "", 'variables' => array());

        public $encryptedSecret;
        public $encodedRedirect;

        public $code;
        public $accessToken;
        public $refreshToken;
        public $validUntil;

        public $currentPlayBack;

        protected $lastHTTPCode;

        public function __construct($client_id, $client_secret, $redirect_uri, $code = NULL){
            $this->encryptedSecret = base64_encode("$client_id:$client_secret");
            $this->code = $code;
            $this->encodedRedirect = urlencode($redirect_uri);

        }

        /* AUTHERIZATION FUNCTIONS */

        /**
         * Redirects user to autherize page. Returns back with "code".
         *
         * @param string $clientId - Spotify API clientID.
         * @param string $redirect_uri - Redirect URI. Has to be in the spotify dashboard.
         * @param array $scopes - Permission scopes for the API process.
         */
        static function authUser($clientId, $redirect_uri, $scopes = array()){
            $encodedRedirect = urlencode($redirect_uri);
            header("Location: https://accounts.spotify.com/authorize/?client_id=$clientId&response_type=code&redirect_uri=$encodedRedirect&scope=user-read-private%20user-read-email%20streaming%20user-read-birthdate%20user-modify-playback-state%20user-read-playback-state&state=34fFs29kd09");

        }

        /**
         *  Set token using "code" variable.
         */
        function setToken(){
            $array = $this->executeCURL("https://accounts.spotify.com/api/token", array("Authorization: Basic $this->encryptedSecret", "Content-Type: application/x-www-form-urlencoded"), "POST", "grant_type=authorization_code&code=$this->code&redirect_uri=$this->encodedRedirect"); //

            if(isset($array['error'])){
                $this->ajaxResponse['successful'] = false;
                $this->ajaxResponse['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->ajaxResponse['errors'][] = $array['error_description'] : $array['error'];
            }else{
                $this->accessToken = $array['access_token'];
                $this->refreshToken = $array['refresh_token'];
                $this->validUntil = time() + 3600;

                $this->ajaxResponse['successful'] = true;
                $this->ajaxResponse['statusmessage'] = "Token set.";
            }
        }

        /**
         *  Refresh the "access token" using the "refresh token".
         */
        function refreshToken(){
            $array = $this->executeCURL("https://accounts.spotify.com/api/token", array("Authorization: Basic $this->encryptedSecret", "Content-Type: application/x-www-form-urlencoded"), "POST", "grant_type=refresh_token&refresh_token=$this->refreshToken");

            if(isset($array['error'])){
                $this->ajaxResponse['successful'] = false;
                $this->ajaxResponse['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                 (isset($array['error_description'])) ? $this->ajaxResponse['errors'][] = $array['error_description'] : $array['error'];
            }else{
                $this->accessToken = $array['access_token'];
                $this->validUntil = time() + 3600;

                $this->ajaxResponse['successful'] = true;
                $this->ajaxResponse['statusmessage'] = "Refreshed Token.";
            }
        }


        /**
         *
         * HELPER FUNCTIONS
         *
         **/

        /**
         * Execute a cURL call.
         *
         * @param string $url
         * @param array $header An array of headers.
         * @param string $mode Mode for the execution (PUT/GET/POST).
         * @param string $variables Variables/body to send in execution. String or json.
         * @return mixed Returns decoded json of return from execution.
         */
        function executeCURL($url, $header = array(), $mode = "GET", $variables = ""){
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL,$url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $mode);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $variables);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $content = curl_exec($curl);
            $this->lastHTTPCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $array = json_decode($content, true);
            if(isset($array['error']['status']) && $array['error']['status'] == 401){
                $this->ajaxResponse['successful'] = false;
                $this->ajaxResponse['errors'][] = "Missing permissions.";
                $this->ajaxResponse['statusmessage'] = "You do not have permissions to do this. (Wrong scope?)";
            }

            if($this->lastHTTPCode == 405){
                $this->ajaxResponse['errors'][] = "The endpoint $url does not support $mode.";
            }
            return $array;
        }

        /**
         *  Check if token is valid. If not = refresh. If no token = set token.
         */
        function checkToken(){
            if(isset($this->validUntil)){
                if($this->isTokenExpired()){
                    $this->refreshToken();
                }else{
                    $this->ajaxResponse['successful'] = true;
                    $this->ajaxResponse['statusmessage'] = "Token is still valid.";
                }
            }else{
                $this->setToken();
            }
        }

        function isTokenExpired(){
            return (time() > $this->validUntil) ? true : false;
        }



        /* PLAYBACK FUNCTIONS */

        /**
         *  Sets "currentPlayBack" variable to the current state of user playback.
         */
        function setCurrentPlayback(){
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player", array("Authorization: Bearer $this->accessToken"), "GET");
            $this->currentPlayBack = $array;
        }

        /**
         * Get all the avaiable devices for connected user.
         *
         * @return array - Devices avaiable on user.
         */
        function getAvailableDevices(){
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/devices", array("Authorization: Bearer $this->accessToken"), "GET");
            return $array['devices'];
        }


        /**
         * Play songs based on spotify uris.
         *
         * @param string $device_id - Playback device id.
         * @param array $spotify_uris - Array with spotify song uris.
         * @param string $context_uri - Context URI for where to play. Album, playlist, or otherwise.
         * @param array $offset - Array where first item is "position" of where in context to start and "uri" of the context to start at.
         */
        function playSong($spotify_uris, $device_id = null, $context_uri = null, $offset = array("position" => null, "uri" => null)){
            $offsetobj = (object) array();
            if($offset['position'] != null){
                $offsetobj->position = $offset['position'];

            }
            if($offset['uri'] != null){
                $offsetobj->uri = $offset['uri'];
            }

            $postfields_array = array();
            if($device_id != null){
                $device_id = "?device_id=".$device_id;
            }
            if($context_uri){
                $postfields_array['context_uri'] = $context_uri;
            }
            if($spotify_uris){
                $postfields_array['spotify_uris'] = $spotify_uris;
            }
            if(isset($offsetobj->position) || isset($offsetobj->uri)){
                $postfields_array['offset'] = $offsetobj;
            }

            $postfields = json_encode($postfields_array);
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/play$device_id", array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "PUT", $postfields);

            if(isset($array['error'])){
                $this->ajaxResponse['successful'] = false;
                $this->ajaxResponse['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->ajaxResponse['errors'][] = $array['error_description'] : $array['error'];

                (isset($array['error']['status']) && $array['error']['status'] == 404) ? $this->ajaxResponse['statusmessage'][] = "Device not found." : $array['error'];
            }else{
                $this->ajaxResponse['successful'] = true;
                $songs = implode(" & ", $spotify_uris);
                $this->ajaxResponse['statusmessage'] = "Songs added to queue: $songs";
            }
        }

        /**
         * Resume playback on device.
         *
         * @param string $device_id - (Optional) The device id for the device to start the playback on. No deviceid = active device control.
         */
        function startPlayback($device_id = null){
            if($device_id != null){
                $device_id = "?device_id=".$device_id;
            }
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/play$device_id", array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "PUT");
            var_dump($array);

            if(isset($array['error'])){
                $this->ajaxResponse['successful'] = false;
                $this->ajaxResponse['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->ajaxResponse['errors'][] = $array['error_description'] : $array['error'];

                (isset($array['error']['status']) && $array['error']['status'] == 404) ? $this->ajaxResponse['statusmessage'][] = "Device not found." : $array['error'];
            }else{
                $this->ajaxResponse['successful'] = true;
                $this->ajaxResponse['statusmessage'] = "Started Playback.";
            }
        }

        /**
         * Stop playback on device.
         *
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function stopPlayback($device_id = null){
            if($device_id != null){
                $device_id = "?device_id=".$device_id;
            }
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/pause$device_id", array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "PUT");

            if(isset($array['error'])){
                $this->ajaxResponse['successful'] = false;
                $this->ajaxResponse['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->ajaxResponse['errors'][] = $array['error_description'] : $array['error'];

                (isset($array['error']['status']) && $array['error']['status'] == 404) ? $this->ajaxResponse['statusmessage'][] = "Device not found." : $array['error'];
            }else{
                $this->ajaxResponse['successful'] = true;
                $this->ajaxResponse['statusmessage'] = "Stopped Playback.";
            }
        }

        /**
         * Skip to the next track.
         *
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function nextTrack($device_id = null){
            if($device_id != null){
                $device_id = "?device_id=".$device_id;
            }
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/next$device_id", array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "POST");

            if($this->lastHTTPCode == 204){
                $this->ajaxResponse['successful'] = true;
                $this->ajaxResponse['statusmessage'] = "Skipped to next song.";
            }else{
                $this->ajaxResponse['successful'] = false;
                $this->ajaxResponse['statusmessage'] = "No result to request.";
                ($this->lastHTTPCode == 404) ? $this->ajaxResponse['statusmessage'][] = "Device not found." : $this->lastHTTPCode;
            }
        }

        /**
         * Skip to the next track.
         *
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function previousTrack($device_id = null){
            if($device_id != null){
                $device_id = "?device_id=".$device_id;
            }
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/previous$device_id", array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "POST");

            if($this->lastHTTPCode == 204){
                $this->ajaxResponse['successful'] = true;
                $this->ajaxResponse['statusmessage'] = "Skipped to previous song.";
            }else{
                $this->ajaxResponse['successful'] = false;
                $this->ajaxResponse['statusmessage'] = "No result to request.";
                ($this->lastHTTPCode == 404) ? $this->ajaxResponse['statusmessage'][] = "Device not found." : $this->lastHTTPCode;
            }
        }

        /**
         * Switch shuffle mode. Uses "currentPlayback" to see what mode is active.
         *
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function toggleShuffle($device_id = null){
            if(isset($this->currentPlayBack['shuffle_state'])){
                $this->setShuffle(!$this->currentPlayBack['shuffle_state'], $device_id);
            }else{
                $this->setCurrentPlayback();
                $this->toggleShuffle($device_id);
            }
        }

        /**
         * Set shuffle to a specific mode (yes/no).
         *
         * @param bool $shuffle_mode
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function setShuffle($shuffle_mode, $device_id = null){
            if($device_id != null){
                $device_id = "&device_id=".$device_id;
            }
            $shuffle_mode = ($shuffle_mode) ? 'true' : 'false';
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/shuffle?state=$shuffle_mode".$device_id, array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "PUT");

            echo $this->lastHTTPCode;
            if($this->lastHTTPCode == 204){
                $this->ajaxResponse['successful'] = true;
                $this->ajaxResponse['statusmessage'] = "Changed shuffle to $shuffle_mode.";
            }else{
                $this->ajaxResponse['successful'] = false;
                $this->ajaxResponse['statusmessage'] = "No result to request.";
                ($this->lastHTTPCode == 404) ? $this->ajaxResponse['statusmessage'][] = "Device not found." : $this->lastHTTPCode;
            }

        }


    }
