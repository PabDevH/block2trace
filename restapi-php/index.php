<?php
session_start();
require("api.php");
$api = New api();

$method=$api->GetMethod();
$resource=$api->GetResource();
$resource = explode("/",$resource);
$params = $resource;
$resource = $params[2];
if ($method=="GET" && $resource=="Help") {
        require("./help.php");
        exit;
}

if (!$_SERVER['HTTP_CREDENTIALS']) {
        $api->auth_error();
}else{
        $credentials = explode(":",$_SERVER['HTTP_CREDENTIALS']);
        $auth = $api->auth($credentials[0],$credentials[1]);
        if ($auth>0) {
                $_SESSION['username']=$credentials[0];
                $api->PreProcessQuery();
        }else{
                $api->auth_error();
        }
}

