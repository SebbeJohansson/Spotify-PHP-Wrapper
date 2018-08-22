<?php

    namespace SpotifyWrapper;

    /**
     * Class SpotifyWrapper
     * @package SpotifyWrapper
     */
    class SpotifyWrapper{

        // Response to be used when using for example ajax.
        public $response = array('successful' => false, 'errors' => array(), 'status_message' => "", 'variables' => array());

        public $clientId;
        public $encryptedSecret;
        public $encodedRedirect;

        public $code;
        public $accessToken;
        public $refreshToken;
        public $validUntil;

        public $currentPlayBack;
        public $recentlyPlayed;

        protected $lastHTTPCode;

        public function __construct($client_id, $client_secret, $redirect_uri, $code = NULL){
            $this->clientId = $client_id;
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
        function authUser($clientId, $redirect_uri, $scopes = array("user-read-private", "user-read-email", "streaming", "user-read-birthdate", "user-modify-playback-state", "user-read-playback-state")){
            $scopes = implode("%20", $scopes);

            $encodedRedirect = urlencode($redirect_uri);
            header("Location: https://accounts.spotify.com/authorize/?client_id=$clientId&response_type=code&redirect_uri=$encodedRedirect&scope=$scopes&state=34fFs29kd09");

        }

        /**
         *  Set token using "code" variable.
         */
        function setToken(){
            $array = $this->executeCURL("https://accounts.spotify.com/api/token", array("Authorization: Basic $this->encryptedSecret", "Content-Type: application/x-www-form-urlencoded"), "POST", "grant_type=authorization_code&code=$this->code&redirect_uri=$this->encodedRedirect"); //

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                if($this->lastHTTPCode == 400){
                    $this->authUser($this->clientId, $this->encodedRedirect);
                    exit();
                }
            }else{
                $this->accessToken = $array['access_token'];
                $this->refreshToken = $array['refresh_token'];
                $this->validUntil = time() + 3600;

                $this->response['successful'] = true;
                $this->response['status_message'] = "Token set.";
            }
        }

        /**
         *  Refresh the "access token" using the "refresh token".
         */
        function refreshToken(){
            $array = $this->executeCURL("https://accounts.spotify.com/api/token", array("Authorization: Basic $this->encryptedSecret", "Content-Type: application/x-www-form-urlencoded"), "POST", "grant_type=refresh_token&refresh_token=$this->refreshToken");

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                 (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
            }else{
                $this->accessToken = $array['access_token'];
                $this->validUntil = time() + 3600;

                $this->response['successful'] = true;
                $this->response['status_message'] = "Refreshed Token.";
            }
        }


        /**
         *
         * HELPER FUNCTIONS
         *
         **/

        /**
         * Execute a cURL call.
         * Prefered to only do one cURL call which might result in a prefered httpcode per request.
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
            $this->response['variables']['http_response'] = $this->lastHTTPCode;
            if(isset($array['error']['status']) && $array['error']['status'] == 401){
                $this->response['successful'] = false;
                $this->response['errors'][] = "Missing permissions.";
                $this->response['status_message'] = "You do not have permissions to do this. (Wrong scope?)";
            }

            switch($this->lastHTTPCode){
                case 405:
                    $this->response['errors'][] = "The endpoint $url does not support $mode.";
                    break;
                case 429:
                    $this->response['status_message'] = "Too many damn requests. STAAAHP.";
                    break;
                default:
                    break;
            }

            if($this->lastHTTPCode == 405){
                $this->response['errors'][] = "The endpoint $url does not support $mode.";
            }
            curl_close($curl);
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
                    $this->response['successful'] = true;
                    $this->response['status_message'] = "Token is still valid.";
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
         * Sets "currentPlayBack" variable to the current state of user playback.
         *
         * @return array - Returns currentPlayback Object.
         */
        function setCurrentPlayback(){
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player", array("Authorization: Bearer $this->accessToken"), "GET");
            $this->currentPlayBack = $array;
            return $array;
        }


        /**
         * Transfers the playback to a specific device based on its id.
         *
         * @param string $device_id - ONE device id which playback should be transfered to. NOTE: Current api doc asks for array.
         * @param bool $play_mode - Specifices if device should be playing or not. False = Keeps state of last playback.
         */
        function transferCurrentPlayback($device_id, $play_mode = null){
            $postfields = json_encode(array('device_ids' => array($device_id), 'play' => $play_mode));

            $array = $this->executeCURL("https://api.spotify.com/v1/me/player", array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "PUT", $postfields);

            switch ($this->lastHTTPCode){
                case 204:
                    $this->response['successful'] = true;
                    $this->response['status_message'] = "Playback changed to device = $device_id";

                    break;
                case 404:
                    $this->response['successful'] = false;
                    $this->response['status_message'] = "Device not found.";

                    break;
                default:
                    $this->response['successful'] = false;
                    $this->response['status_message'] = "Unspecified http-code. Result unsure.";

                    break;
            }
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
         * Get all the recentplayed tracks.
         *
         * @param int $limit - Limit the number of returned tracks.
         * @param int $before - Get all tracks played before a certain UNIX timestamp in microseconds. Can not be used with $after.
         * @param int $after - Get all tracks played after a certain UNIX timestamp in microseconds. Can not be used with $before.
         */
        function getRecentlyPlayedTracks($limit = 50, $before = null, $after = null){
            if($after != null){
                $after = "&after=".$after;
            }elseif($before != null){
                $before = "&before=".$before;
            }

            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/recently-played?limit=$limit".$after.$before, array("Authorization: Bearer $this->accessToken"), "GET");
            $this->recentlyPlayed = $array;
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
            var_dump($array);
            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['status_message'] = "An error occurred!";
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                    (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];

                (isset($array['error']['status']) && $array['error']['status'] == 404) ? $this->response['status_message'] = "Device not found." : $array['error'];
            }elseif ($array == NULL){
                $this->stopPlayback();
                $this->response['successful'] = false;
                $this->response['status_message'] = "Request was invalid.";
                $this->response['errors'][] = "Something went wrong. Was the context uri wrong? Stopping playback.";
            }else{
                $this->response['successful'] = true;
                $songs = implode(" & ", $spotify_uris);
                $this->response['status_message'] = "Songs added to queue: $songs";
            }
        }


        /**
         * Toggles the playback state.
         *
         * @param string $device_id - (Optional) The device id for the device to start the playback on. No deviceid = active device control.
         */
        function togglePlayback($device_id = null){
            if(isset($this->currentPlayBack['is_playing'])){
                if($this->currentPlayBack['is_playing']){
                    $this->stopPlayback($device_id);
                }else{
                    $this->startPlayback($device_id);
                }
            }else{
                $this->setCurrentPlayback();
                $this->togglePlayback($device_id);
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

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];

                (isset($array['error']['status']) && $array['error']['status'] == 404) ? $this->response['status_message'] = "Device not found." : $array['error'];
            }else{
                $this->response['successful'] = true;
                $this->response['status_message'] = "Started Playback.";
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
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];

                (isset($array['error']['status']) && $array['error']['status'] == 404) ? $this->response['status_message'] = "Device not found." : $array['error'];
            }else{
                $this->response['successful'] = true;
                $this->response['status_message'] = "Stopped Playback.";
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
                $this->response['successful'] = true;
                $this->response['status_message'] = "Skipped to next song.";
            }else{
                $this->response['successful'] = false;
                $this->response['status_message'] = "No result to request.";
                ($this->lastHTTPCode == 404) ? $this->response['status_message'] = "Device not found." : $this->lastHTTPCode;
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
                $this->response['successful'] = true;
                $this->response['status_message'] = "Skipped to previous song.";
            }else{
                $this->response['successful'] = false;
                $this->response['status_message'] = "No result to request.";
                ($this->lastHTTPCode == 404) ? $this->response['status_message'] = "Device not found." : $this->lastHTTPCode;
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
         * @param bool $shuffle_mode - On or off.
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function setShuffle($shuffle_mode, $device_id = null){
            if($device_id != null){
                $device_id = "&device_id=".$device_id;
            }
            $shuffle_mode = ($shuffle_mode) ? 'true' : 'false';
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/shuffle?state=$shuffle_mode".$device_id, array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "PUT");

            if($this->lastHTTPCode == 204){
                $this->response['successful'] = true;
                $this->response['status_message'] = "Changed shuffle to $shuffle_mode.";
            }else{
                $this->response['successful'] = false;
                $this->response['status_message'] = "No result to request.";
                ($this->lastHTTPCode == 404) ? $this->response['status_message'] = "Device not found." : $this->lastHTTPCode;
            }

        }

        /**
         * Change repeat mode to the next one. Similar to how the official spotify program does it.
         * Might be a tad unstable.
         *
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function toggleRepeat($device_id = null){
            $modes = array("off", "context", "track");

            if(isset($this->currentPlayBack['repeat_state'])){
                $current_mode = array_search($this->currentPlayBack['repeat_state'], $modes);

                if($current_mode >= (count($modes) - 1)){
                    $this->setRepeat($modes[0], $device_id);
                }else{
                    $this->setRepeat($modes[$current_mode+1], $device_id);
                }
            }else{
                $this->setCurrentPlayback();
                $this->toggleRepeat($device_id);
            }
        }

        /**
         *
         * Set repeat mode to a specific mode. "track", "context", or "off".
         *
         * @param string $repeat_mode
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function setRepeat($repeat_mode, $device_id = null){
            if($device_id != null){
                $device_id = "&device_id=".$device_id;
            }
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/repeat?state=$repeat_mode".$device_id, array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "PUT");

            if($this->lastHTTPCode == 204){
                $this->response['successful'] = true;
                $this->response['status_message'] = "Changed repeat mode to $repeat_mode.";
            }else{
                $this->response['successful'] = false;
                $this->response['status_message'] = "No result to request.";
                ($this->lastHTTPCode == 404) ? $this->response['status_message'] = "Device not found." : $this->lastHTTPCode;
            }
        }


        /**
         * Set the playback to a specific position. If position is greater than track length function skips to next song.
         *
         * @param int $position - Playback position in microseconds.
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function setPlaybackPosition($position, $device_id = null){
            if($device_id != null){
                $device_id = "&device_id=".$device_id;
            }
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/seek?position_ms=$position".$device_id, array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "PUT");

            if($this->lastHTTPCode == 204){
                $this->response['successful'] = true;
                $this->response['status_message'] = "Set playback to $position microseconds into the track.";
            }else{
                $this->response['successful'] = false;
                $this->response['status_message'] = "No result to request.";
                ($this->lastHTTPCode == 404) ? $this->response['status_message'] = "Device not found." : $this->lastHTTPCode;
            }
        }

        /**
         * Set the playback volume to a specfic percentage.
         *
         * @param int $volume - Requested volume in percentage (0-100) in int.
         * @param string $device_id - (Optional) The device id for the device to stop the playback on. No deviceid = active device control.
         */
        function setPlaybackVolume($volume, $device_id = null){
            if($device_id != null){
                $device_id = "&device_id=".$device_id;
            }
            $array = $this->executeCURL("https://api.spotify.com/v1/me/player/volume?volume_percent=$volume".$device_id, array("Authorization: Bearer $this->accessToken", "Content-Type: application/json"), "PUT");

            switch ($this->lastHTTPCode){
                case 204:
                    if($volume > 100){
                        $this->response['successful'] = false;
                        $this->response['status_message'] = "Changed volume to $volume%.";
                    }else{
                        $this->response['successful'] = true;
                        $this->response['status_message'] = "Changed volume to $volume%.";
                    }

                    break;
                case 404:
                    $this->response['successful'] = false;
                    $this->response['status_message'] = "Device not found.";

                    break;
                default:
                    $this->response['successful'] = false;
                    if($volume > 100){
                        $this->response['status_message'] = "Specified volume is too high (>100).";
                    }else{
                        $this->response['status_message'] = "Unspecified http-code. Result unsure.";
                    }

                    break;
            }
        }


        /* ALBUM FUNCTIONS */

        /**
         * Get a specific album array based on album id.
         *
         * @param string $album_id
         * @return array|null - Returns album array. If no album array was found NULL is returned.
         */
        function getAlbum($album_id, $market = null){
            if($market != null){
                $market = "?market=".$market;
            }
            $array = $this->executeCURL("https://api.spotify.com/v1/albums/$album_id".$market, array("Authorization: Bearer $this->accessToken"), "GET");

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned album array.";
                return $array;
            }
        }

        /**
         * Get all tracks from a specific album based on album id.
         *
         * @param string $album_id
         * @param string $market - Market iso. Decides what market to pull data from.
         * @return array|null - Returns album array. If no album tracks was found NULL is returned.
         */
        function getAlbumTracks($album_id, $market = null){
            if($market != null){
                $market = "?market=".$market;
            }
            $tracks = array();

            // Initial tracks (first 20).
            $array = $this->executeCURL("https://api.spotify.com/v1/albums/$album_id/tracks".$market, array("Authorization: Bearer $this->accessToken"), "GET");

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{

                $tracks = array_merge($tracks, $array['items']);
                if($array['total'] > 20){
                    $left = $array['total'] - 20;

                    while($left > 0){
                        $array = $this->executeCURL($array['next'], array("Authorization: Bearer $this->accessToken"), "GET");
                        $tracks = array_merge($tracks, $array['items']);
                        $left -= 20;
                    }

                }

                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned album tracks.";
                return $tracks;
            }
        }

        /**
         * Get album arrays from multiple albums.
         *
         * @param array $album_ids
         * @param string $market - Market iso. Decides what market to pull data from.
         * @return  array|null - Returns album arrays (one for each returned album). If one or more album ids are invalid their array is NULL in returned array.
         */
        function getMultipleAlbums($album_ids, $market = null){
            if($market != null){
                $market = "&market=".$market;
            }

            $album_ids = implode(",", $album_ids);

            $array = $this->executeCURL("https://api.spotify.com/v1/albums/?ids=$album_ids".$market, array("Authorization: Bearer $this->accessToken"), "GET");

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned album arrays.";
                foreach($array['albums'] as $album){
                    if($album == NULL){
                        $this->response['status_message'] = "One or more album ids returned NULL. Use data with caution.";
                        break;
                    }
                }
                return $array['albums'];
            }

        }


        /* ARTIST FUNCTIONS */

        /**
         * Get a specific artist array based on artist id.
         *
         * @param string $artist_id
         * @return array|null - Returns artist array. If no artist array was found NULL is returned.
         */
        function getArtist($artist_id){
            $array = $this->executeCURL("https://api.spotify.com/v1/artists/$artist_id", array("Authorization: Bearer $this->accessToken"), "GET");

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned album array.";
                return $array;
            }
        }

        /**
         * Get all albums for specific artist.
         *
         * @param string $artist_id
         * @param array $types - Array of album types to return.
         * @param string $market - Market iso. Decides what market to pull data from.
         * @return array|null - Returns artist albums. If no artist array was found NULL is returned. If no albums was found return is successfull but an empty array is returned.
         */
        function getArtistAlbums($artist_id, $types = null, $market = null){
            if($market != null){
                $market = "&market=".$market;
            }
            $albums = array();

            //var_dump($types);
            if($types != null){
                $types = "&include_groups=".implode(",", $types);
            }

            // Initial albums (first 20).
            $array = $this->executeCURL("https://api.spotify.com/v1/artists/$artist_id/albums?limit=20".$market.$types, array("Authorization: Bearer $this->accessToken"), "GET");

            if(isset($array['error']) || $this->lastHTTPCode == 404){
                $this->response['successful'] = false;
                $this->response['status_message'] = "No artist albums returned.";
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $albums = array_merge($albums, $array['items']);
                if($array['total'] > 20){
                    $left = $array['total'] - 20;

                    while($left > 0){
                        $array = $this->executeCURL($array['next'], array("Authorization: Bearer $this->accessToken"), "GET");
                        $albums = array_merge($albums, $array['items']);
                        $left -= 20;
                    }

                }

                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned artists albums.";
                if(count($albums) <= 0){
                    $this->response['status_message'] = "No albums was found for artist id: $artist_id with the specified groups.";
                }
                return $albums;
            }
        }

        /**
         * Get all top tracks from artist.
         *
         * @param string $artist_id
         * @param string $market - Market iso. Decides what market to pull data from.
         * @return array|null - Tracks array or NULL if no tracks was returned.
         */
        function getArtistTopTracks($artist_id, $market){
            $array = $this->executeCURL("https://api.spotify.com/v1/artists/$artist_id/top-tracks?market=".$market, array("Authorization: Bearer $this->accessToken"), "GET");

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned album array.";
                return $array['tracks'];
            }
        }

        /**
         * Get all related artists to specific artist.
         *
         * @param string $artist_id
         * @return array|null - Artists array or null if no artists was returned.
         */
        function getRelatedArtists($artist_id){
            $array = $this->executeCURL("https://api.spotify.com/v1/artists/$artist_id/related-artists", array("Authorization: Bearer $this->accessToken"), "GET");

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned album array.";
                return $array['artists'];
            }
        }

        /**
         * Get multiple artist arrays.
         *
         * @param array $album_ids
         * @return array|null - Artist array or null if no artists was returned.
         */
        function getMultipleArtists($album_ids){
            $album_ids = implode(",", $album_ids);
            $array = $this->executeCURL("https://api.spotify.com/v1/artists?ids=$album_ids", array("Authorization: Bearer $this->accessToken"), "GET");

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned album arrays.";
                foreach($array['artists'] as $artist){
                    if($artist == NULL){
                        $this->response['status_message'] = "One or more artist ids returned NULL. Use data with caution.";
                        break;
                    }
                }
                return $array['artists'];
            }
        }


        /* BROWSE FUNCTIONS */

        /**
         * Get array of category.
         *
         * @param string $category_id
         * @param string $country - Decides what market to pull data from.
         * @param string $locale - Consists of "languagecode_countrycode". For example "es_MX". Decides what language to pull data in.
         * @return array|null - Category array or null if no category was returned.
         */
        function getCategory($category_id, $country = null, $locale = null){
            $parameters = null;
            if($country != null){
                $parameters = "?country=".$country;
            }
            if($locale != null){
                if($parameters == null){
                    $parameters = "?locale=".$locale;
                }else{
                    $parameters .= "&locale=".$locale;
                }
            }

            $array = $this->executeCURL("https://api.spotify.com/v1/browse/categories/$category_id".$parameters, array("Authorization: Bearer $this->accessToken"), "GET");

            if(isset($array['error'])){
                $this->response['successful'] = false;
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned category array.";
                return $array;
            }
        }

        /**
         * Get all playlists from a certain category.
         *
         * @param string $category_id
         * @param string $country - Country iso. Decides what market to pull data from.
         * @return array|null - Playlists array or NULL if no playlists was returned.
         */
        function getCategoryPlaylists($category_id, $country = null){
            if($country != null){
                $country = "?country=".$country;
            }

            // Initial playlists (first 20).
            $array = $this->executeCURL("https://api.spotify.com/v1/browse/categories/$category_id/playlists".$country, array("Authorization: Bearer $this->accessToken"), "GET");

            $playlists = array();
            if(isset($array['error']) || $this->lastHTTPCode == 404){
                $this->response['successful'] = false;
                $this->response['status_message'] = "No playlists returned.";
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $playlists = array_merge($playlists, $array['playlists']['items']);
                if($array['playlists']['total'] > 20){
                    $left = $array['playlists']['total'] - 20;

                    while($left > 0){
                        $array = $this->executeCURL($array['playlists']['next'], array("Authorization: Bearer $this->accessToken"), "GET");
                        $playlists = array_merge($playlists, $array['playlists']['items']);
                        $left -= 20;
                    }
                }

                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned playlists based on category id $category_id.";
                if(count($playlists) <= 0){
                    $this->response['status_message'] = "No playlists was found for category id: $category_id.";
                }
                return $playlists;
            }
        }

        /**
         * Get all available categories.
         *
         * @param string $country - Country ISO. Decides what market to pull data from.
         * @param string $locale - Consists of "languagecode_countrycode". For example "es_MX". Decides what language to pull data in.
         * @return array|null - Categories array or null if no categories was found.
         */
        function getCategories($country = null, $locale = null){
            $parameters = null;
            if($country != null){
                $parameters = "?country=".$country;
            }
            if($locale != null){
                if($parameters == null){
                    $parameters = "?locale=".$locale;
                }else{
                    $parameters .= "&locale=".$locale;
                }
            }

            // Initial categories (first 20).
            $array = $this->executeCURL("https://api.spotify.com/v1/browse/categories/".$parameters, array("Authorization: Bearer $this->accessToken"), "GET");

            $categories = array();
            if(isset($array['error']) || $this->lastHTTPCode == 404){
                $this->response['successful'] = false;
                $this->response['status_message'] = "No categories returned.";
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $categories = array_merge($categories, $array['categories']['items']);
                if($array['categories']['total'] > 20){
                    $left = $array['categories']['total'] - 20;

                    while($left > 0){
                        $array = $this->executeCURL($array['categories']['next'], array("Authorization: Bearer $this->accessToken"), "GET");
                        $categories = array_merge($categories, $array['categories']['items']);
                        $left -= 20;
                    }
                }

                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned categories.";
                if(count($categories) <= 0){
                    $this->response['status_message'] = "No categories was found with the specified parameters.";
                }
                return $categories;
            }
        }

        /**
         * @param string $country - Country ISO. Decides what market to pull data from.
         * @param string $locale - Consists of "languagecode_countrycode". For example "es_MX". Decides what language to pull data in.
         * @param string $timestamp - Timestamp for what point in time to pull data on. Format: "yyyy-MM-ddTHH:mm:ss"
         * @return array|null - Playlists array or null if no playlists was found.
         */
        function getFeaturedPlaylists($country = null, $locale = null, $timestamp = null){
            $parameters = null;
            if($country != null){
                $parameters = "?country=".$country;
            }
            if($locale != null){
                if($parameters == null){
                    $parameters = "?locale=".$locale;
                }else{
                    $parameters .= "&locale=".$locale;
                }
            }
            if($timestamp != null){
                if($parameters == null){
                    $parameters = "?timestamp=".$timestamp;
                }else{
                    $parameters .= "&timestamp=".$timestamp;
                }
            }

            // Initial playlists (first 20).
            $array = $this->executeCURL("https://api.spotify.com/v1/browse/featured-playlists/".$parameters, array("Authorization: Bearer $this->accessToken"), "GET");

            $playlists = array();
            if(isset($array['error']) || $this->lastHTTPCode == 404){
                $this->response['successful'] = false;
                $this->response['status_message'] = "No playlists returned.";
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $playlists = array_merge($playlists, $array['playlists']['items']);
                if($array['playlists']['total'] > 20){
                    $left = $array['playlists']['total'] - 20;

                    while($left > 0){
                        $array = $this->executeCURL($array['playlists']['next'], array("Authorization: Bearer $this->accessToken"), "GET");
                        $playlists = array_merge($playlists, $array['playlists']['items']);
                        $left -= 20;
                    }
                }

                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned featured playlists.";
                if(count($playlists) <= 0){
                    $this->response['status_message'] = "No playlists was found with the specified parameters.";
                }
                return $playlists;
            }
        }


        function getNewReleases($country = null){
            $parameters = null;
            if($country != null){
                $parameters = "?country=".$country;
            }

            // Initial playlists (first 20).
            $array = $this->executeCURL("https://api.spotify.com/v1/browse/new-releases/".$parameters, array("Authorization: Bearer $this->accessToken"), "GET");

            $albums = array();
            if(isset($array['error']) || $this->lastHTTPCode == 404){
                $this->response['successful'] = false;
                $this->response['status_message'] = "No albums returned.";
                $this->response['errors'][] = (isset($array['error']['message'])) ? $array['error']['message'] : $array['error'];
                (isset($array['error_description'])) ? $this->response['errors'][] = $array['error_description'] : $array['error'];
                return null;
            }else{
                $albums = array_merge($albums, $array['albums']['items']);
                if($array['albums']['total'] > 20){
                    $left = $array['albums']['total'] - 20;

                    while($left > 0){
                        $array = $this->executeCURL($array['albums']['next'], array("Authorization: Bearer $this->accessToken"), "GET");
                        $albums = array_merge($albums, $array['albums']['items']);
                        $left -= 20;
                    }
                }

                $this->response['successful'] = true;
                $this->response['status_message'] = "Successfully returned new releases.";
                if(count($albums) <= 0){
                    $this->response['status_message'] = "No albums was found with the specified parameters.";
                }
                return $albums;
            }
        }

        // Add recommentation end point at some point.


        

    }

