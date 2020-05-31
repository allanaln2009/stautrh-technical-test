<?php
class Crypt{
  //Internal key to encript data
  private static $key = "StrautRH";

  /*    
	 * Public method that encript a password in a hash
   * @param $pass = String
   * @param $mail = optional String
	 * @return string hash
	 */
  public static function encryptPass($pass, $mail = ''){
    $base = base64_encode(Crypt::$key.$mail);
    $hash = hash('sha512', base64_encode($base.$pass.md5($base)));
    for ($i = 0; $i < strlen($pass)+3/3; $i++) {
      $hash = hash('sha512', $hash.sha1($base));
    }
    return $hash;
  }

  /*    
	 * Public method that generate a token
   * @param $key1 = String
   * @param $key1 = String
	 * @return string token
	 */
  public function genToken($key1, $key2){
    $key = date("D d/m/Y H:i:s");
    $base1 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($key1));
    $base2 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($key2));
    $sign = hash('sha512', $base1.$base2.base64_encode('Staut_RH'.$key), true);
    $base = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($key.$sign.$key));
    $token = hash('sha512', $key.$base.$key1.$base1).hash('sha512', $base.$key2.$key.$base2);
    return $token;
  }
}