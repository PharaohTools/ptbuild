<?php

Namespace Controller ;

class PublishHTMLreports extends Base {

     public function execute($pageVars) {

         $thisModel = $this->getModelAndCheckDependencies(substr(get_class($this), 11), $pageVars) ;
         if (is_array($thisModel)) { return $this->failDependencies($pageVars, $this->content, $thisModel) ; }

         if ($thisModel->userIsAllowedAccess() !== true) {
             $override = $this->getIndexControllerForOverride() ;
             return $override->execute() ; }

         $action = $pageVars["route"]["action"];

         if (in_array($pageVars["route"]["action"], array("report"))) {
             $this->content["data"] = $thisModel->getReportData() ;
             return array ("type"=>"view", "view"=>"publishHTMLreports", "pageVars"=>$this->content) ; }

//         if (in_array($pageVars["route"]["action"], array("report-list"))) {
//             $this->content["data"] = $thisModel->getReportListData() ;
//             return array ("type"=>"view", "view"=>"publishHTMLreportsList", "pageVars"=>$this->content) ; }

         if ($action === 'help') {
             $helpModel = new \Model\Help();
             $this->content["helpData"] = $helpModel->getHelpData($pageVars['route']['control']);
             return array ("type"=>"view", "view"=>"help", "pageVars"=>$this->content); }

         $this->content["messages"][] = "Invalid HTML reports Action";
         return array ("type"=>"control", "control"=>"index", "pageVars"=>$this->content);

     }

    protected function getIndexControllerForOverride() {
        return \Core\AutoLoader::getController("Signup")  ;
    }


}
