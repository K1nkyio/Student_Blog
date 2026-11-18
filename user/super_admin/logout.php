<?php
require_once dirname(__DIR__) . '/shared/session.php';
    start_project_session();
destroy_project_session();
header("Location: login.php");
exit();

