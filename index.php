<?php
include_once 'classes/utils.php';
include_once 'classes/db.php';
include_once 'classes/user.php';
include_once 'classes/crypt.php';
include_once 'classes/session.php';
include_once 'classes/drinks.php';

//Create a conection object to interate with DB
try {
    $dbObj = new DB();
    $conn = $dbObj->connect();
} catch (PDOException $ex) {
    echo $ex->getMessage();
    exit();
}

//Get URL and remove root
$uri_full = explode('&', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$uri_path = explode('/', $uri_full[0]);
array_shift($uri_path);
array_shift($uri_path);

//Set main object with configurations
$data = (object) array(
    'method' => $_SERVER['REQUEST_METHOD'],
    'req' => $uri_path,
    'body' => json_decode(file_get_contents('php://input'), true),
    'token' => isset(getallheaders()['token']) ? getallheaders()['token'] : null,
    'conn' => $conn
);

//Create and select services/entities of API
switch ($uri_path[0]) {
    case 'login':
        $req = new Session($data);
        break;
    case 'users':
        $req = new Users($data);
        break;
    case 'drinks':
        $req = new Drinks($data);
        break;
    default:
        $req = new Utils();
        $req->setMsgCode(404, "The '$uri_path[0]' is a valid parameter.");
}
$result = $req->getJsonStr();

http_response_code($req->getStatusCode());
header("Content-Type:application/json;charset=utf-8'");
if ($data->token !== null)
    header("token:" . $data->token);

echo ($result);
