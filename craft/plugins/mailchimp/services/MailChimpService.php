<?php
namespace Craft;

class MailChimpService extends BaseApplicationComponent {

  public function apiGet($url) {

    $plugin = craft()->plugins->getPlugin('mailChimp');
    $settings = $plugin->getSettings();
    $apiKey = $settings['apiKey'];

    $dc = substr($apiKey, -3);

    //$cachedResponse = craft()->fileCache->get($url);
    $url = 'https://'.$dc.'.api.mailchimp.com/2.0'.$url.'?apikey='.$apiKey;
    $client = new \Guzzle\Http\Client();
    $request = $client->get($url);
    $response = $request->send();

    if (!$response->isSuccessful()) {
      return;
    }

    return $response->json();
  }

  public function subscribe($email) {

    $plugin = craft()->plugins->getPlugin('mailChimp');
    $settings = $plugin->getSettings();
    $apiKey = $settings['apiKey'];
    $listId = $settings['listId'];

    $dc = substr($apiKey, -3);

    $params = array('apikey'=>$apiKey, 'id'=>$listId, 'email' => array('email'=>$email) );

    //echo json_encode($params);

    //$cachedResponse = craft()->fileCache->get($url);
    $url = 'https://'.$dc.'.api.mailchimp.com/2.0/lists/subscribe.json';
    $client = new \Guzzle\Http\Client();
    $request = $client->post($url, array(),$params );
    $response = $request->send();

    if (!$response->isSuccessful()) {
      return;
    }

    return $response->json();
  }


  public function apiPost($url,$request) {

    $plugin = craft()->plugins->getPlugin('mailChimp');
    $settings = $plugin->getSettings();
    $apiKey = $settings['apiKey'];

    $dc = substr($apiKey, -3);


    //$cachedResponse = craft()->fileCache->get($url);
    $url = 'https://'.$dc.'.api.mailchimp.com/2.0'.$url.'?apikey='.$apiKey;
    $client = new \Guzzle\Http\Client();
    $request = $client->post($url, array(),$request );
    $response = $request->send();

    if (!$response->isSuccessful()) {
      return;
    }

    return $response->json();
  }


}
