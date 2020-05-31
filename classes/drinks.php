<?php
Class Drinks extends Utils{
  function __construct($data){
    $this->setBasicinfo($data);

    switch($data->method){
      case 'GET':
        $this->_get($data);
        break;
      default:
        $this->setMsgCode(405, "Method Not Allowed.");
    }
  }

  private function _get($data){
    $id = $this->getUrlId($data->req);
    if(!$id){
      $this->ranking();
      return;
    }
    //$this->setMsgCode(400, "Inform an user ID.");
    $this->getHistory($id);
  }
            
  private function ranking(){
    $query = "
    SELECT
      name,
      (
        SELECT SUM(drinked_list.quantity_ml)
        FROM drinked_list
        WHERE drinked_list.id_user = users.id_user
      ) AS drinked_total
    FROM users
    ORDER BY drinked_total DESC
    ";
    
    $queryPage = $this->pagination('users');
    $query .= $queryPage !== false ? $queryPage : null;

    $query = $this->conn->query($query);
    $this->getData($query);
  }
  
  /*    
	 * Private method that gets a history drinks of a user ID
	 * @param $id = user ID
	 */
  private function getHistory($id){
    $query = "
    SELECT quantity_ml, create_time
      FROM drinked_list
     WHERE id_user = :id
    ";

    $query = $this->conn->prepare($query);
    $query->bindParam(':id', $id);
    $query->execute();

    $this->getData($query);
  }

  /*    
	 * Private method to generic query of this class
   * Set the result in result fields of the object
	 * @param $query = PDO query mounted
	 */
  private function getData($query){
    $result = $query->fetchAll(PDO::FETCH_ASSOC);

    if(!$result){
      $this->result = [];
    }
    $this->result = $result;
  }

}