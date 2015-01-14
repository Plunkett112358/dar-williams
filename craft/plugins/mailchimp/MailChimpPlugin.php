<?php
namespace Craft;

class MailChimpPlugin extends BasePlugin
{

  /* --------------------------------------------------------------
   * PLUGIN INFO
   * ------------------------------------------------------------ */

  public function getName()
  {
    return Craft::t('MailChimp');
  }

  public function getVersion()
  {
    return '0.1';
  }

  public function getDeveloper()
  {
    return 'Familiar';
  }

  public function getDeveloperUrl()
  {
    return 'http://familiar.is';
  }


  protected function defineSettings()
  {
      return array(
          'apiKey' => array(AttributeType::String),
          'listId' => array(AttributeType::String),
      );
  }

  public function getSettingsHtml()
  {
       return craft()->templates->render('mailchimp/settings', array(
           'settings' => $this->getSettings()
       ));
  }

  /* --------------------------------------------------------------
   * HOOKS
   * ------------------------------------------------------------ */


  public function init() {

    craft()->on('users.onBeforeSaveUser', function(Event $event) {

      if ($event->params['isNewUser']) {

        //subscribe new user
      } else {
        //update existing user

      }
    // ...
    });

  }

}
