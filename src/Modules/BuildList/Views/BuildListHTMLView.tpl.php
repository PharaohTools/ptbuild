<div class="container">
    <div class="row">
        <div class="col-sm-4 col-md-3 sidebar">
            <div class="mini-submenu">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </div>
            <div class="list-group sidebar-list">
                        <span href="#" class="list-group-item active">
                            Menu
                            <span class="pull-right" id="slide-submenu">
                                <i class="fa fa-times"></i>
                            </span>
                        </span>
                <a href="/index.php?control=Index&action=show" class="list-group-item">
                    <i class="fa fa-comment-o"></i> Dashboard
                </a>
                <a href="#" class="list-group-item">
                    <i class="fa fa-search">Configure Phrankinsense</i>
                </a>
                <a href="#" class="list-group-item">
                    <i class="fa fa-user">List Pipelines</i>
                </a>
                <a href="#" class="list-group-item">
                    <i class="fa fa-folder-open-o"></i> Lorem ipsum <span class="badge">14</span>
                </a>
                <a href="#" class="list-group-item">
                    <i class="fa fa-bar-chart-o"></i> Lorem ipsumr <span class="badge">14</span>
                </a>
                <a href="#" class="list-group-item">
                    <i class="fa fa-envelope"></i> Lorem ipsum
                </a>
            </div>
        </div>

        <div class="col-sm-8 col-md-9 clearfix main-container">
            <h2 class="text-uppercase text-light"><a href="/"> Phrankinsense - Pharaoh Tools</a></h2>

            <div class="row clearfix no-margin">
                <h4 class="text-uppercase text-light">A list of builds in a page</h4>
                <h3><a class="lg-anchor text-light" href="/index.php?control=BuildList&action=show">Build List <i style="font-size: 18px;" class="fa fa-chevron-right"></i></a></h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-custom">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Pipeline Name</th>
                            <th>Status</th>
                            <th>Success</th>
                            <th>Failure</th>
                            <th>Duration</th>
                            <th>Parent</th>
                            <th>Child</th>
                            <th>Run Now</th>
                        </tr>
                        </thead>
                        <tbody class="table-hover">

                        <?php
                            $i = 1;
                            foreach ($pageVars["data"]["pipelines"] as $pipelineSlug => $pipelineDetails) { ?>
                            <tr>
                                <th scope="row"><?php echo $i ; ?></th>
                                <td><a href="/index.php?control=BuildHome&action=show&item=<?php echo $pipelineSlug; ?>">Pipeline <?php echo $pipelineDetails["project_title"]["value"] ; ?></a></td>
                                <td>
                                    <?php
                                        if ($pipelineDetails["last_status"] === true) {
                                            echo '<img class="listImage listImageWide" src="/Assets/BuildList/images/green-ball.png" />' ; }
                                        else {
                                            echo '<img class="listImage listImageWide" src="/Assets/BuildList/images/red-ball.png" />' ; }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($pipelineDetails["last_success"] != false) {
                                        echo '20:13:55 01/02/2015' ; }
                                    else {
                                        echo 'N/A' ; }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($pipelineDetails["last_fail"] != false) {
                                        echo '14:13:55 21/01/2015' ; }
                                    else {
                                        echo 'N/A' ; }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($pipelineDetails["duration"] != false) {
                                        echo '134 seconds' ; }
                                    else {
                                        echo 'N/A' ; }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($pipelineDetails["has_parents"] === true) {
                                        echo '<img class="listImage" src="/Assets/BuildList/images/tick.png" />' ; }
                                    else {
                                        echo '<img class="listImage" src="/Assets/BuildList/images/cross.png" />' ; }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($pipelineDetails["has_children"] === true) {
                                        echo '<img class="listImage" src="/Assets/BuildList/images/tick.png" />' ; }
                                    else {
                                        echo '<img class="listImage" src="/Assets/BuildList/images/cross.png" />' ; }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        echo '<a href="PipeRunner"><img class="listImage" src="/Assets/BuildList/images/run.png" /></a>' ;
                                    ?>
                                </td>
                            </tr>
                        <?php
                            $i++;
                            } ?>

                        </tbody>
                    </table>
                </div>
            </div>

            <p>
                ---------------------------------------<br/>
                Visit www.pharaohtools.com for more
            </p>
        </div>


    </div>

</div><!-- /.container -->
