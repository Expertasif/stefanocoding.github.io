<?php
if(isset($_SERVER['HTTP_REFERER'])) {

  $referrer = $_SERVER['HTTP_REFERER'];
  preg_match("/&code=(.*)/", $referrer, $code);

  // Get access_token
  $auth_data_url = 'https://longboard.heroku.com/login/token';
  $curl = curl_init();
  curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL => $auth_data_url,
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => array(
          grant_type => 'password',
          password => $code[1],
          username => ''
      )
  ));
  $auth_data_response = curl_exec($curl);
  curl_close($curl);
  $auth_data = json_decode($auth_data_response);


  // Get account information
  $account_data_url = 'https://api.heroku.com/account';
  $curl = curl_init();
  curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL => $account_data_url,
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $auth_data->{'access_token'},
        'Accept: application/vnd.heroku+json; version=3.federated-identity'
        )
  ));
  $account_data_response = curl_exec($curl);
  curl_close($curl);
  $account_data = json_decode($account_data_response);

  // Send mail to user
  mail($account_data->{'email'}, 'Hi ' . $account_data->{'name'}, 'Greetings from somewhere!');

}
?>
