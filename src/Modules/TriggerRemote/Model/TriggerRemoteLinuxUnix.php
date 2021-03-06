<?php

Namespace Model;

class TriggerRemoteLinuxUnix extends Base {

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
            "trigger-remote-http" => array(
                "type" => "boolean",
                "name" => "Allow Triggering this job by Remote HTTP?",
                "optional" => true ),
            "trigger-remote-cli" => array(
                "type" => "boolean",
                "name" => "Allow Triggering this job by CLI?",
                "optional" => true ),
            "trigger-web" => array(
                "type" => "boolean",
                "name" => "Allow Triggering this job by Web Interface?",
                "optional" => true ),
        );
        return $ff ;
    }

	public function getStepTypes() {
        return array_keys($this->getFormFields());
    }
	
	public function getFormFields() {
        $pipeline_options = $this->getPipelineOptions() ;
        $ff = array(
            "trigger_remote_data" => array(
                array(
                    "type" => "text",
                    "name" => "Step Label",
                    "slug" => "step_label"),
                array(
                    "type" => "dropdown",
                    "name" => "Build to Trigger Remotely",
                    "slug" => "trigger_job",
                    "data" => $pipeline_options),
                array(
                    "type" => "boolean",
                    "name" => "Allow Unstable Triggered Build?",
                    "slug" => "allow_unstable" ),
                array(
                    "type" => "boolean",
                    "name" => "Allow Failed Triggered Build?",
                    "slug" => "allow_failed" ),
                array(
                    "type" => "textarea",
                    "name" => "Parameter Set",
                    "slug" => "parameter_raw" ),
                array(
                    "type" => "text",
                    "name" => "Custom timeout in seconds - defaults to 1 week",
                    "slug" => "timeout"),
                array(
                    "type" => "boolean",
                    "name" => "Quiet Progress?",
                    "slug" => "quiet_progress"),
            )
        );
        return $ff ;
    }

    protected function getPipelineOptions() {
        $pipelineFactory = new \Model\Pipeline() ;
        $pipeline = $pipelineFactory->getModel($this->params);
        $pipelines =  $pipeline->getPipelines();
        $pipe_options = array() ;
        foreach ($pipelines as $pipeline) {
            $pipe_options[$pipeline['project-slug']] = $pipeline['project-name'] ;
        }
//        file_put_contents('/tmp/opts', var_export($pipe_options, true)) ;
        return $pipe_options ;
    }

    public function executeStep($step) {
        $loggingFactory = new \Model\Logging();
        $logging = $loggingFactory->getModel($this->params);
        // For a normal remote trigger job
        if ( $step["steptype"] == "trigger_remote_data") {
            $logging->log("Running Trigger of Build Job...", $this->getModuleName()) ;
            $prFactory = new \Model\PipeRunner() ;
            $params = $this->params ;
            $params["item"] = $step["trigger_job"] ;

            //
            if (isset($this->params["env-vars"]) && is_array($this->params["env-vars"])) {
                $logging->log("Remote Trigger Extracting Environment Variables...", $this->getModuleName()) ;
                $ext_vars = implode(", ", array_keys($this->params["env-vars"])) ;
                $count = 0 ;
                foreach ($this->params["env-vars"] as $env_var_key => $env_var_val) {
                    $step["parameter_raw"] = str_replace('$'.$env_var_key, $env_var_val, $step["parameter_raw"]) ;
                    $count++ ; }
                $logging->log("Successfully Extracted {$count} Environment Variables into Parameters {$ext_vars}...", $this->getModuleName()) ; }

            $params["build-parameters"] = $this->params_from_raw($step["parameter_raw"]) ;
            $pr = $prFactory->getModel($params) ;
            $started_run = $res = $pr->runPipe() ;

            if ($res !== false) {
                $logging->log("Build Job {$params["item"]} started successfully, run id {$started_run}...", $this->getModuleName()) ;
            } else {
                $logging->log("Build Job {$params["item"]} failed...", $this->getModuleName()) ;
            }

            $default_timeout = 604800 ; // one week
            $timeout = (isset($step["timeout"]) && strlen($step["timeout"]) > 0) ? $step["timeout"] : $default_timeout ; // one week

            $loop_max = $timeout / 5 ;

            $finder = $prFactory->getModel($params, 'FindRunning') ;
            for ($i=0; $i<=$loop_max; $i++) {
                $current_running = $finder->getData();
                $is_running = $this->findInRunning($current_running['running_builds'], $params["item"], $started_run) ;
                if ($is_running) {
                    if (!isset($step['quiet_progress']) || $step['quiet_progress'] !== 'on') {
                        $logging->log("Build Job {$params["item"]} is running...", $this->getModuleName()) ;
                    }
                } else {
                    $logging->log("Build Job {$params["item"]} is no longer running...", $this->getModuleName()) ;
                    break ;
                }
                if ($i == $loop_max) {
                    $timeout = true ;
                    $logging->log("Build Job {$params["item"]} has exceeded timeout of {$timeout} seconds...", $this->getModuleName()) ;
                }
                sleep(5);
            }
            $logging->log("Build Job {$params["item"]} monitoring complete, finding execution status...", $this->getModuleName()) ;


            $pipelineParentFactory = new \Model\Pipeline() ;
            $pipelineParent = $pipelineParentFactory->getModel($params) ;
            $pipeline = $pipelineParent->getPipeline($params["item"]) ;
            $status = $pipeline["history_index"][$started_run]["status"] ;


            $status_prefix = "Build Job {$params['item']}, run id {$started_run}" ;

            if ($status === 'SUCCESS') {
                $logging->log("$status_prefix was successful...", $this->getModuleName()) ;
                $res = true ;
            } else if ($status === 'FAIL') {
                $logging->log("$status_prefix has failed...", $this->getModuleName()) ;
                if ($step["allow_failed"] == 'on') {
                    $logging->log("Always allowing a failed Triggered Build, marking Step as success", $this->getModuleName()) ;
                    $res = true ;
                } else {
                    $res = false ;
                }
            } else {
                $logging->log("$status_prefix is unstable...", $this->getModuleName()) ;
                if ($step["allow_unstable"] == 'on') {
                    $logging->log("Always allowing an unstable Triggered Build, marking Step as success", $this->getModuleName()) ;
                    $res = true ;
                } else {
                    $res = false ;
                }
            }

            return $res ; }
        else if ( $step["steptype"] == "trigger_remote_script") {
            $logging->log("Running TriggerRemote from Script...") ;
            $this->executeAsTriggerRemote($step["script"]) ;
            return true ; }
        else {
            $logging->log("Unrecognised Build Step Type {$step["type"]} specified in TriggerRemote Module") ;
            return false ; }
    }

    protected function params_from_raw($raw) {
        $lines = explode("\n", $raw) ;
        $params = array() ;
        foreach ($lines as $line) {
            $parts = explode('=', $line) ;
            $parts[0] = rtrim($parts[0]) ;
            $parts[1] = ltrim($parts[1]) ;
            $params[$parts[0]] = $parts[1] ;
        }
        return $params ;
    }

    protected function findInRunning($current_running, $item, $run) {
        foreach ($current_running as $one_running) {
            if ($one_running['item'] == $item &&
                $one_running['runid'] == $run) {
                return true ;
            }
        }
        return false ;
    }

}
