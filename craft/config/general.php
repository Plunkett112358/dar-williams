<?php

/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here.
 * You can see a list of the default settings in craft/app/etc/config/defaults/general.php
 */

return array(

	'*' => array(
		 'omitScriptNameInUrls' => true,
		 'resourceTrigger' => 'files',
		 'useEmailAsUsername' => true,
		 'autoLoginAfterAccountActivation' => true,
		 'postLoginRedirect' => 'final-details'
    
    
      
	),

  '.dev' => array(
    'devMode' => true,
    'generateTransformsAfterPageLoad' => false,
    'backupDbOnUpdate' => false,
    'siteUrl' => 'http://dar-williams.craft.dev/',
    'overridePhpSessionLocation' => true
   
   

  ),

  '.org' => array(
  	'usePathInfo' => true,
  	'generateTransformsAfterPageLoad' => false,
  	'devMode' => false
  )


);
