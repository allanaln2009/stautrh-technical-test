<?php
Class Session extends Utils{

  function __construct($data){
    $this->setBasicinfo($data);
    if(!isset($data->method)){
      return;
    }

    switch($data->method){
      case 'POST':
        echo '';
        if($this->verify((object) $data->body)){
          $this->login($data->body['email'], $data->body['password']);
        }
        break;
      default:
        $this->setMsgCode(405, "Method Not Allowed.");
    }
  }

  /*    
	 * Private method that checks the fields to login
	 * @param $body = JSON login
	 * @return boolean informing the status operation
	 */
  private function verify($body){
    $mail = isset($body->email) ? $body->email : null;
    $pass = isset($body->password) ? $body->password : null;

    if(!$this->validStr($mail) ||
       !$this->validStr($pass)){
      $this->setMsgCode(400, "The fields 'email' and/or 'password' were not filled in correctly.");
      return false;
    }
    return true;
  }

  /*    
	 * Private method that log in a user generating a token too
   * This method inserts the token in DB
	 * @param $email = email to login
	 * @param $pass = password to login
	 * @return in success the user data or false in error to log in
	 */
  private function login($email, $pass){
    if(!$this->emailExists($email))
      return false;
    
    $hash = Crypt::encryptPass($pass, $email);
    $data = $this->validateAccess_GetData($email, $hash);
    if($data === false)
      return false;

    if(!$this->invalidateOldTokens($data['iduser']))
      return false;

    $data['token'] = $this->genAccessToken($data['iduser'], $data['email']);

    date_default_timezone_set("Brazil/East");
    $data['valid_until'] = date("Y-m-d H:i:s", strtotime("+1 hours"));

    //$query = 'INSERT INTO tokens (id_user, token, valid_until) VALUES ('.$data['iduser'].', '.$data['token'].', '.$data['valid_until'].')'; //TIMESTAMPADD(HOUR,1,NOW())
    $query = "INSERT INTO tokens (id_user, token, valid_until) VALUES (:iduser, :token, :valid_until)";
    $query = $this->conn->prepare($query);
    $query->bindParam(':iduser', $data['iduser']);
    $query->bindParam(':token', $data['token']);
    $query->bindParam(':valid_until', $data['valid_until']);

    $result_insert = $query->execute();

    if(!$result_insert){
      $this->setMsgCode(400, $query->errorInfo());
      return false;
    }

    $this->result = $data;
  }

  /*    
	 * Private method to check if email exists in database and
   * verify if its is a deleted account
	 * @param $email = email to check
	 * @return boolean informing the status operation
	 */
  private function emailExists($email){
    $sql = "
    SELECT id_user
      FROM users
     WHERE email = :email
    ";

    $query = $this->conn->prepare($sql);
    $query->bindParam(':email', $email);
    $query->execute();

    if($query->fetch() === false){
      $this->setMsgCode(400, 'This email is not registered yet.');
      return false;
    }
    $email_exists = true;
    
    $sql .= "AND users.deleted = '0'";
    $query = $this->conn->prepare($sql);
    $query->bindParam(':email', $email);
    $query->execute();
    $result = $query->fetch();
    if($result === false){
      $this->setMsgCode(403, 'This email was used and deleted, contact the administrator.');
      return false;
    }

    return true;
  }
  
  /*    
	 * Private method that check if user login (email) and password matches
	 * @param $email = user's email
	 * @param $hash = hash of password
	 * @return false boolean informing the status operation or user data
	 */
  private function validateAccess_GetData($email, $hash){
    $query = "
      SELECT
        users.id_user AS iduser,
        name,
        email,
        (
          SELECT COUNT(drinked_list.id_drinked)
            FROM drinked_list
            WHERE drinked_list.id_user = users.id_user
        ) AS drink_counter
      FROM users
      WHERE users.deleted = '0'
        AND email = :email
        AND password = :hash
    ";
    //$query = $this->conn->query($query);
    $query = $this->conn->prepare($query);
    $query->bindParam(':email', $email);
    $query->bindParam(':hash', $hash);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);

    if($result === false){
      $this->setMsgCode(401, 'The password is incorrect.');
      return false;
    }
    return $result;
  }

  /*    
	 * Public method to run private invalidateOldTokens() out of this class
	 * @param $id = user ID
	 */
  public function invalidateOldTokensDeletedUser($id){
    $this->invalidateOldTokens($id);
  }
  
  /*    
	 * Private method that invalidate the old token active of an user ID
	 * @param $id = user ID
	 * @return boolean informing the status operation
	 */
  private function invalidateOldTokens($id){
    $query = "
    UPDATE tokens
    SET invalid = '1'
     WHERE id_user = :iduser
     AND invalid = '0'
     AND valid_until > CURRENT_TIMESTAMP()
     ";
    $query = $this->conn->prepare($query);
    $query->bindParam(':iduser', $id);
    if(!$query->execute()){
      $this->setMsgCode(400, $query->errorInfo());
      return false;
    }
    return true;
  }
  
  /*    
	 * Private method that generate the token
	 * @param $value1 = value to generate token
	 * @param $value2 = value to generate token
	 * @return the token string
	 */
  private function genAccessToken($value1, $value2){
    $key1 = hash('sha512', random_int(0,999999).$value1.sha1(date("d/m/Y H:i:s")));
    $key2 = hash('sha512', random_int(0,999999).$value2.date("d/m/Y H:i:s"));
    return Crypt::genToken($key1, $key2);
  }

  /*    
	 * Public method that verify if token is valid
	 * @param $class = object with 'token' filled
	 * @param $user_id = optional user ID to match ID and token
	 * @return boolean informing the validity of the token
	 */
  public static function validateToken($class, $user_id = false){
    $query = "SELECT id_token FROM tokens WHERE token = :token AND valid_until > CURRENT_TIMESTAMP() AND invalid = '0'";

    if($user_id !== false)
      $query .= " AND id_user = :id";

    $query = $class->conn->prepare($query);
    $query->bindParam(':token', $class->token);
    
    if($user_id !== false)
      $query->bindParam(':id', $user_id);
    
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);

    if($result === false)
      return false;
    return true;
  }
}