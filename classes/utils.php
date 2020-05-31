<?php
Class Utils{
  // Vars to use in childs classes
  protected $conn, $result, $token;

  /*    
	 * Public method that validate a string is filled
   * @param $str = String
	 * @return boolean
	 */
  public function validStr($str){
    if(empty($str) || $str === null || strlen($str) == 0)
      return false;
    return true;
  }

  /*    
	 * Public method that returns JSON body request
	 * @return JSON body
	 */
  public function getJsonStr(){
    return json_encode($this->result);
  }

  /*    
	 * Public method that returns HTTP code set in result->code
	 * @return HTTP code
	 */
  public function getStatusCode(){
    return isset($this->result->code) ? $this->result->code : 200;
  }
  
  /*    
	 * Public method that sets message and code HTTP
   * @param $str = String
	 */
  public function setMsgCode($code, $msg){
    $this->result = (object) array(
      'code' => $code,
      'message' => $msg
    );
  }
  
  /*    
	 * Public method that verify if a token was sent
   * @param $id_user = optional param to inform user ID is valid with token
	 * @return boolean
	 */
  public function userIsLogged($id_user = false){
    if(!isset($this->token) || empty($this->token)){
      $this->setMsgCode(401, "You do not inform a valid token in your header request.");
      return false;
    }

    if(!Session::validateToken($this, $id_user)){
      $this->setMsgCode(401, "Invalid token.");
      return false;
    }
    return true;
  }
  
  /*    
	 * Protected method that set class attributes
   * @param $data = basic data (DB connection, access token, pagination query)
	 * @return boolean
	 */
  protected function setBasicInfo($data){
    $this->conn = $data->conn;
    $this->token = $data->token;
  }

  /*    
	 * Private method that verify and get the user ID informed in URL
	 * @param $param = URL path array
	 * @return int ID ou bool false
	 */
  protected function getUrlId($param){
    if(!isset($param[1]))
      return false;

    //No SQL Injection
    $replace = preg_replace('/[^0-9]/', '', $param[1]);
    return $replace !== "" ? $replace : false;
  }
  
  /*    
	 * Public method that generate a simple pagination query
   * NEEDS IMPROVEMENT to ignore user IDs deleted or diferent filters
	 * @return String with limit query
	 */
  protected function pagination($table){
    $perPage = filter_input(INPUT_GET, 'per_page', FILTER_SANITIZE_URL);
    $pageIndex = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_URL);

    if($perPage === null || intval($perPage) <= 0 || empty($perPage))
      return false;
    
    if($pageIndex === null || intval($pageIndex) <= 0)
      $pageIndex = 1;

    $sql = "SELECT COUNT(*) FROM $table";
    $query = $this->conn->query($sql);
    $result = $query->fetch();

    $totalRows = $result[0];
    $totalPages = ceil($totalRows/$perPage);
    
    $curPage = $pageIndex;
    if($curPage > $totalPages){
        $curPage = $totalPages;
    }
    if($curPage < 1){
        $curPage = 1;
    }
    $init = ($curPage - 1) * $perPage;

    return " LIMIT $init, $perPage ";
  }
}