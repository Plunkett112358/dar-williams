<?php
namespace Craft;

class MailChimpVariable
{
    public function testApi()
    {

      $response = craft()->mailChimp->apiGet('/campaigns/list.json');


      var_dump($response);
      return $response;
    }


    public function subscribe($email)
    {


      $response = craft()->mailChimp->subscribe($email);

      var_dump($response);
      return $response;
    }



}
