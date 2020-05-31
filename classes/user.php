<?php
Class Users extends Utils{
  function __construct($data){
    $this->setBasicinfo($data);
    
    switch($data->method){
      case 'GET':
        $this->_get($data);
        break;
      case 'POST':
        $this->_post($data);
        break;
      case 'PUT':
        $this->_put($data);
        break;
      case 'DELETE':
        $this->_delete($data);
        break;
      default:
        $this->setMsgCode(405, "Method Not Allowed.");
    }
  }

  private function _get($data){
    $result = $this->getUsers($data->req);
    if($result !== false)
      $this->result = $result;
  }

  private function _post($data){
    $param = $data->req;
    if(isset($param[2]) && $param[2] === "drink"){
      $id = $this->validateIdUrlParam($data->req);
      if($id === false)
        return false;
      $result = $this->addDrinked($id, $data->body);
      if($result !== false)
        $this->result = $result;
    }else{
      $this->newUser($data->body);
    }
  }
  
  private function _put($data){
    $id = $this->validateIdUrlParam($data->req);
    if($id === false)
      return false;
    $this->updateUser($id, $data->body);
  }
  
  private function _delete($data){
    $id = $this->validateIdUrlParam($data->req);
    if($id === false)
      return false;
    $this->deleteMyUser($id);
  }
  
  /*    
	 * Private method that get user list or especific user
	 * @param $param = URL path array
	 * @return object of class User or false boolean
	 */
  private function getUsers($uri_path){
    if(!$this->userIsLogged())
      //return false;

    $query = "
      SELECT
        users.id_user AS iduser,
        name,
        email,
        (
          SELECT COUNT(drinked_list.id_drinked)
            FROM drinked_list
            WHERE drinked_list.id_user = users.id_user
        ) AS drink_counter,
        (
          SELECT SUM(drinked_list.quantity_ml)
            FROM drinked_list
            WHERE drinked_list.id_user = users.id_user
        ) AS drinked_total
      FROM users
      GROUP BY users.id_user
    ";

    $queryPage = $this->pagination('users');
    if($queryPage !== false){
      $query .= $queryPage;
    }else{
      $query .= "WHERE users.deleted = '0' ";
      //Get ID on URL to get a especific user
      $id =  $this->getUrlId($uri_path);
      $qry_where = "AND users.id_user = '$id' ";
      $query .= ($id !== false) ? $qry_where : null;
    }

    $query = $this->conn->query($query);
    $result = $query->fetchAll(PDO::FETCH_ASSOC);

    if(!$result){
      $this->result = [];
      return $result;
    }
    $this->result = $result;
    return $result;
  }

  /*    
	 * Private method that creates a new user
	 * @param $body = body with fields of requisition in JSON
	 * @return boolean informing the status operation
	 */
  private function newUser($body){
    $data = $this->validateBodyFields($body);
    if($data === false)
      return false;
    $name = $data->name;
    $mail = $data->mail;
    $pass = $data->pass;

    $result = $this->searchByMail($mail);
    if($result !== false){
      $this->setMsgCode(400, "This email is already used. If you deleted your account, contact the administrator.");
      return false;
    }

    $pass = Crypt::encryptPass($pass, $mail);

    $query = "INSERT INTO users(name, email, password) VALUES(:name, :mail, :pass)";
    $query = $this->conn->prepare($query);
    $query->bindParam(':name', $name);
    $query->bindParam(':mail', $mail);
    $query->bindParam(':pass', $pass);
    
    $result = $query->execute();

    if(!$result){
      $this->setMsgCode(400, $query->errorInfo());
      return false;
    }

    $this->setMsgCode(201, "Created.");
    return true;
  }

  /*    
	 * Private method that removes current logged user informing their ID
	 * @param $id = ID of logged user
	 * @param $token = valid session token of logged user
	 * @return boolean informing the status operation
	 */
  private function deleteMyUser($id){
    if(!$this->userIsLogged($id)){
      $this->setMsgCode(403, "You do not have permission to delete this user. Verify the token in your header request and user ID in URL.");
      return false;   
    }

    $data = new stdClass();
    $data->conn = $this->conn;
    $data->token = null;
    $session = new Session($data);
    $session->invalidateOldTokensDeletedUser($id);

    $query = "UPDATE users SET deleted = '1' WHERE id_user = :id AND deleted = '0'";
    $query = $this->conn->prepare($query);
    $query->bindParam(':id', $id);
    if(!$query->execute()){
      $this->setMsgCode(400, $query->errorInfo());
      return false;
    }

    $this->setMsgCode(200, "Your user was deleted.");
    return true;
  }
      
  /*    
	 * Private method that body is not empty
	 * @param $body = JSON
	 * @return false boolean informing the status operation or object body
	 */
  private function validateBody($body){
    return !empty($body) ? (object) $body : false;
  }

  /*    
	 * Private method that validade body request received
	 * @param $body = JSON with user fields
	 * @return false boolean informing the status operation or the user object
	 */
  private function validateBodyFields($body){
    $userObj = $this->validateBody($body);

    $name = isset($userObj->name) ? $userObj->name : null;
    $mail = isset($userObj->email) ? $userObj->email : null;
    $pass = isset($userObj->password) ? $userObj->password : null;

    if(!$this->validStr($name) ||
       !$this->validStr($mail) ||
       !$this->validStr($pass)){
      $this->setMsgCode(400, "The fields 'name', 'email' and/or 'password' were not filled in correctly.");
      return false;
    }

    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
      $this->setMsgCode(400, "Invalid email format.");
      return false;
    }

    if (!preg_match("/^\pL+(?>[- ']\pL+)*$/u", $name)) {
      $this->setMsgCode(400, "Invalid name.");
      return false;
    }

    $userObj->name = $name;
    $userObj->mail = $mail;
    $userObj->pass = $pass;
    return $userObj;
  }

  /*    
	 * Private method that search user ID by email fields
	 * @param $email = email value to search
	 * @param $id = optional user ID to match with email
	 * @return false boolean informing the status operation or user ID
	 */
  private function searchByMail($email, $id = false){
    $query = "SELECT id_user FROM users WHERE email = :email";
    if($id !== false)
      $query .= " AND id_user != :id";

    $query = $this->conn->prepare($query);
    $query->bindParam(':email', $email);
    if($id !== false)
      $query->bindParam(':id', $id);

    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    if($result === false)
      return false;
    return $result;
  }

  /*    
	 * Private method that removes current logged user informing their ID
	 * @param $reqUrl = array with URL params
	 * @return boolean informing the negative status operation or ID informed
	 */
  private function validateIdUrlParam($reqUrl){
    if(!isset($reqUrl[1]) || empty($reqUrl[1])){
      $this->setMsgCode(400, 'Inform your ID in URL path. Example: [address]/StautRH/users/[YOUR_ID]');
      return false;
    }
    return $reqUrl[1];
  }
 
  /*    
	 * Private method that user data
	 * @param $id = user ID
	 * @param $body = object with fields
	 * @return boolean informing the status operation
	 */
  private function updateUser($id, $body){
    if(!$this->userIsLogged($id)){
      $this->setMsgCode(403, "You do not have permission to edit this user. Verify the token in your header request and user ID in URL.");
      return false;   
    }
    
    $data = $this->validateBodyFields($body);
    if($data === false)
      return false;
    $name = $data->name;
    $mail = $data->mail;
    $pass = $data->pass;
    
    $result = $this->searchByMail($mail, $id);
    if($result !== false){
      $this->setMsgCode(409, "This email '$mail' is associated to another account. You need inform a different email address.");
      return false;
    }
    
    $pass = Crypt::encryptPass($pass, $mail);
    
    $query = "UPDATE users SET name = :name, email = :mail, password = :pass WHERE id_user = :id";
    $query = $this->conn->prepare($query);
    $query->bindParam(':name', $name);
    $query->bindParam(':mail', $mail);
    $query->bindParam(':pass', $pass);
    $query->bindParam(':id', $id);    
    if(!$query->execute()){
      $this->setMsgCode(304, $query->errorInfo());
      return false;
    }

    $this->setMsgCode(202, "Updated.");
    return true;
  }

  /*    
	 * Private method that adds drinked items
	 * @param $id = user ID
	 * @param $body = field to filled data
	 * @return boolean informing the status operation or user data
	 */
  private function addDrinked($id, $body){
    if(!$this->userIsLogged($id)){
      $this->setMsgCode(403, "You do not have permission to add drinks to this user.");
      return false;   
    }
    
    $bodyObj = $this->validateBody($body);
    $quantity = isset($bodyObj->drink_ml) ? intval($bodyObj->drink_ml) : null;
    if($quantity === null || $quantity <= 0){
      $this->setMsgCode(406, "You need to inform a value greater then 0.");
      return false;
    }

    $query = "INSERT INTO drinked_list (id_user, quantity_ml) VALUES (:iduser, :quantity)";
    $query = $this->conn->prepare($query);
    $query->bindParam(':iduser', $id);
    $query->bindParam(':quantity', $quantity);
    $result = $query->execute();
    if(!$result){
      $this->setMsgCode(400, $query->errorInfo());
      return false;
    }

    $query = "
      SELECT
        users.id_user AS iduser,
        email,
        name,
        (
          SELECT COUNT(drinked_list.id_drinked)
            FROM drinked_list
            WHERE drinked_list.id_user = users.id_user
        ) AS drink_counter
      FROM users
      WHERE users.deleted = '0'
        AND id_user = :id
    ";
    //$query = $this->conn->query($query);
    $query = $this->conn->prepare($query);
    $query->bindParam(':id', $id);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    if(!$result){
      $this->setMsgCode(400, $query->errorInfo());
      return false;
    }
    return $result;
  }
}