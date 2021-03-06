<?php

Namespace Model;

class PublishReleasesAllOS extends Base {

    // Compatibility
    public $os = array("any") ;
    public $linuxType = array("any") ;
    public $distros = array("any") ;
    public $versions = array("any") ;
    public $architectures = array("any") ;

    // Model Group
    public $modelGroup = array("Default") ;

    public function getSettingTypes() {
        return array_keys($this->getSettingFormFields());
    }

    public function getSettingFormFields() {
        $ff = array(
            "enabled" =>
            	array(
                	"type" => "boolean",
                	"optional" => true,
                	"name" => "Publish Releases on Build Completion?"
            ),
            "allow_public" =>
                array(
                    "type" => "boolean",
                    "name" => "Allow public Release access for this Pipeline?",
                    "slug" => "allow_public"
            ),
            "fieldsets" => array(
                "custom_release" => array(
                    "release_title" =>
                        array(
                            "type" => "text",
                            "name" => "Release Title",
                            "slug" => "release_title"),
                    "release_file" =>
                        array(
                            "type" => "text",
                            "name" => "Relative or absolute path to file",
                            "slug" => "release_file"),
                    "new_file_name" =>
                        array(
                            "type" => "text",
                            "name" => "New File Name",
                            "slug" => "new_file_name"),
                    "remote_file_url" =>
                        array(
                            "type" => "text",
                            "name" => "Remote File URL",
                            "slug" => "remote_file_url"),
                    "image" =>
                        array(
                            "type" => "text",
                            "name" => "Image for Release Package",
                            "slug" => "image"),
                    "allow_public" =>
                        array(
                            "type" => "boolean",
                            "name" => "Allow Public Release Access for this release?",
                            "slug" => "allow_public"),
                    "publish_as_remote_url" =>
                        array(
                            "type" => "boolean",
                            "name" => "Publish Remote URL?",
                            "slug" => "publish_as_remote_url")
                ),
            ),
        ) ;
        return $ff ;
    }
   
    public function getEventNames() {
        return array_keys($this->getEvents());
    }

	public function getEvents() {
		$ff = array("afterBuildComplete" => array("publishReleases"));
		return $ff ;
    }

    public function getPipeline() {
        $pipelineFactory = new \Model\Pipeline() ;
        $pipeline = $pipelineFactory->getModel($this->params);
        $r = $pipeline->getPipeline($this->params["item"]);
        return $r ;
    }

    public function getReleasesList() {
		$pipeFactory = new \Model\Pipeline();
		$pipeline = $pipeFactory->getModel($this->params);
		$thisPipe = $pipeline->getPipeline($this->params["item"]);
		$mn = $this->getModuleName() ;
        $ff["releases_list"] = $thisPipe["settings"][$mn] ;
        $ff["releases_available"] = $this->getReleasesAvailable($thisPipe) ;
        $ff["pipeline"] = $thisPipe;
        $ff["current_user"] = $this->getCurrentUser() ;
        $ff["current_user_role"] = $this->getCurrentUserRole($ff["current_user"]);
		return $ff ;
    }

	public function getReleasesData() {
        $ff["pipeline"] = $this->getPipeline() ;
        $ff["current_user"] = $this->getCurrentUser() ;
        $ff["current_user_role"] = $this->getCurrentUserRole($ff["current_user"]);
        return $ff ;
    }

	public function getReleasesAvailable($thisPipe) {

        $releaseRef =
            PIPEDIR.DS.$this->params["item"].DS.'ReleasePackages'.
            DS ;

        $hashes = scandir($releaseRef) ;
        $hashes = array_diff($hashes, array('.', '..')) ;
        $releases = array() ;
        foreach ($hashes as $hash) {
            $is_remote = ($thisPipe['settings']['PublishReleases']['custom_release'][$hash]['publish_as_remote_url'] == 'on') ? true : false ;
            if ($is_remote == true) {
                $hi = $this->getHistoryIndex() ;
                foreach ($hi as $run_id => $hi_detail) {
                    $remote_url = $hi[$run_id]["meta"]['PublishedReleases'][$hash]['remote_url'] ;
                    if ($remote_url!= "") {
                        $releases[$hash][$run_id][] = $remote_url;
                    }
                }
            } else {
                $run_ids = scandir($releaseRef . $hash);
                $run_ids = array_diff($run_ids, array('.', '..'));
                sort($run_ids, SORT_NUMERIC);
                foreach ($run_ids as $run_id) {
                    $release_files = scandir($releaseRef . $hash . DS . $run_id);
                    $release_files = array_diff($release_files, array('.', '..'));
                    foreach ($release_files as $release_file) {
                        $releases[$hash][$run_id][] = $release_file;
                    }
                }
            }
        }
        return $releases ;
    }

    protected function getCurrentUser() {
        $signupFactory = new \Model\Signup() ;
        $signup = $signupFactory->getModel($this->params);
        $user = $signup->getLoggedInUserData();
        return $user ;
    }

    public function getCurrentUserRole($user = null) {
        if ($user === null) {
            $user = $this->getCurrentUser(); }
        if ($user === false) {
            return false ; }
        return $user['role'] ;
    }

    public function isLoginEnabled() {
        $settings = $this->getSettings();
        if ( (isset($settings["Signup"]["signup_enabled"]) && $settings["Signup"]["signup_enabled"] !== "on")
            || !isset($settings["Signup"]["signup_enabled"])) {
            return false ; }
        return true ;
    }

    public function userIsAllowedAccess() {
        $user = $this->getCurrentUser() ;
        $pipeline = $this->getPipeline() ;
        $settings = $this->getSettings() ;
        if (!isset($settings["PublicScope"]["enable_public"]) ||
            ( isset($settings["PublicScope"]["enable_public"]) && $settings["PublicScope"]["enable_public"] !== "on" )) {
            // if enable public is set to off
            if ($user == false) {
                // and the user is not logged in
                return false ; }
            // if they are logged in continue on
            return true ; }
        else {
            // if enable public is set to on
            if ($user == false) {
                // and the user is not logged in
                if ($pipeline["settings"]["PublicScope"]["enabled"] === "on" &&
                    $pipeline["settings"]["PublicScope"]["build_public_releases"] === "on") {
                    // if public releases and general are on
                    return true ; }
                else {
                    // if no public pages are on
                    return false ; } }
            else {
                // and the user is logged in
                // @todo this is where repo specific perms go when ready
                return true ;
            }
        }
    }

    protected function getSettings() {
        $settings = \Model\AppConfig::getAppVariable("mod_config");
        return $settings ;
    }

    public function publishReleases() {
        $loggingFactory = new \Model\Logging();
        $this->params["echo-log"] = true ;
        $logging = $loggingFactory->getModel($this->params);
        $pipe = $this->getPipeline() ;
        $pipe_settings = $pipe['settings'];
        $mn = $this->getModuleName() ;
        if (isset($pipe_settings[$mn]["enabled"]) && $pipe_settings[$mn]["enabled"] === "on") {
            $logging->log("Release publishing is enabled, executing", $this->getModuleName());
            $results = array() ;
            foreach ($pipe_settings[$mn]['custom_release'] as $release_hash => $release_details) {
                $results = $this->publishOneRelease($release_hash, $release_details) ;
            }
            return (in_array(false, $results)) ? true : false ;
        }
        else {
            $logging->log ("Release Publishing disabled...", $this->getModuleName() ) ;
            return true ;
        }
    }

    protected function publishOneRelease($one_release_hash, $one_release_details) {

        $loggingFactory = new \Model\Logging();
        $this->params["echo-log"] = true ;
        $logging = $loggingFactory->getModel($this->params);

        $file = $one_release_details["release_file"];

        if (isset($one_release_details['publish_as_remote_url']) &&
            $one_release_details['publish_as_remote_url'] == 'on') {

            $publish_url = $this->parseEnvVarString($one_release_details['remote_file_url']) ;
            $logging->log ("Publishing Remote URL {$publish_url} to build metadata...", $this->getModuleName() ) ;
            $this->saveRemoteURLToMetadata($publish_url, $one_release_hash);

        } else {

            if (substr($file, 0, 1) !== DS) {
                $source_file = PIPEDIR.DS.$this->params["item"].DS.'workspace'.DS.$file ;
                $source_dir = dirname($source_file) ;
            } else {
                $source_file = $file ;
                $source_dir = dirname($file) ;
            }

            if (!is_dir($source_dir)) {
                $log_msg = "Unable to locate Release Directory {$source_dir} " ;
                $log_msg .= "from release {$one_release_details["release_title"]}" ;
                $logging->log($log_msg, $this->getModuleName());
                return false ;
            }

            $releaseRef =
                PIPEDIR.DS.$this->params["item"].DS.'ReleasePackages'.
                DS.$one_release_hash.DS.$this->params["run-id"].DS ;
            $logging->log ("Publishing to release directory {$releaseRef}", $this->getModuleName() ) ;
            if (!is_dir($releaseRef)) {
                $logging->log ("Attempting to create release directory {$releaseRef}", $this->getModuleName() ) ;
                mkdir($releaseRef, 0777, true);
            }

            if (isset($one_release_details['new_file_name'])) {
                $new_file = $one_release_details['new_file_name'] ;
            } else {
                $new_file = basename($file) ;
            }
            $tf = $releaseRef.$new_file ;
            $tf = $this->parseEnvVarString($tf) ;

            $copy_command = "cp -r {$source_file} {$tf}" ;
            $rc = $this->executeAndGetReturnCode($copy_command, false, true) ;

            if ($rc["rc"] !== 0) {
                $last = count($rc["output"]) - 1 ;
                $logging->log("Copy unsuccessful, Error: {$rc["output"][$last]}", $this->getModuleName());
            }

            if ($rc["rc"] == 0) {
                $logging->log ("Release {$one_release_details["release_title"]} published to file {$tf}...", $this->getModuleName() ) ;
                return true;
            }
            else {
                $logging->log ("Unable to publish generated release {$one_release_details['release_title']} to file {$tf}...", $this->getModuleName() ) ;
                return false;
            }
        }



    }

    protected function parseEnvVarString($tf, $run_id = null) {

        $loggingFactory = new \Model\Logging();
        $this->params["echo-log"] = true ;
        $logging = $loggingFactory->getModel($this->params);

        $env_var_string = "" ;
        if (isset($this->params["env-vars"]) && is_array($this->params["env-vars"]) && (count($this->params["env-vars"])>0)) {
            $logging->log("Release Publishing Extracting Environment Variables...", $this->getModuleName()) ;
            $ext_vars = implode(", ", array_keys($this->params["env-vars"])) ;
            $count = 0 ;
            foreach ($this->params["env-vars"] as $env_var_key) {
                $var_swap_option = '$$'.$env_var_key ;
                if (strpos($tf, $var_swap_option)) {
                    $logging->log("Swapping Env Variable \${$env_var_key} for value {$this->params["env-vars"][$env_var_key]}", $this->getModuleName()) ;
                    $tf = str_replace($var_swap_option, $this->params["env-vars"][$env_var_key], $tf) ; } }
            $logging->log("Successfully Extracted {$count} Environment Variables into Release Publishing Variables {$ext_vars}...", $this->getModuleName()) ;
        }

        foreach ($this->params["env-vars"] as $env_var_key => $env_var_val) {
            $env_var_string .= "$env_var_key=".'"'.$env_var_val.'"'."\n" ;
        }

        if ($run_id === null) {
            $run_id = $this->params['run-id'] ;
        }
        $swap_options = array('item', 'run-id', 'run_id') ;
        foreach ($swap_options as $swap_option) {
            $pso = $this->params[$swap_option];
            if (in_array($swap_option, array('run-id', 'run_id'))) {
                $pso = $run_id ;
            }
            $var_swap_option = '$$'.$swap_option ;
            if (strpos($tf, $var_swap_option) !== false) {
                $logging->log("Swapping variable \${$swap_option} for value {$pso}", $this->getModuleName()) ;
                $tf = str_replace($var_swap_option, $pso, $tf) ;
            }
        }

        return $tf ;

    }

    public function saveRemoteURLToMetadata($remote_url, $one_release_hash) {
        $hi = $this->getHistoryIndex() ;
        $hi[$this -> params["run-id"]]["meta"]['PublishedReleases'][$one_release_hash]['remote_url'] = $remote_url ;
        $this->saveHistoryIndex($hi) ;
    }

    public function getHistoryIndex() {
        $file = PIPEDIR . DS . $this -> params["item"] . DS . 'historyIndex';
        if ($historyIndex = file_get_contents($file)) {
            $historyIndex = json_decode($historyIndex, true);
        }
        return $historyIndex ;
    }

    public function saveHistoryIndex($hi) {
        $file = PIPEDIR . DS . $this -> params["item"] . DS . 'historyIndex';
        $hi_string = json_encode($hi, JSON_PRETTY_PRINT) ;
        file_put_contents($file, $hi_string);
    }

}