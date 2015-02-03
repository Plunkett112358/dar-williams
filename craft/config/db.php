<?php
/**
 * Database Configuration
 *
 * All of your system's database configuration settings go in here.
 * You can see a list of the default settings in craft/app/etc/config/defaults/db.php
 */



return array(
	'*' => array(
		'tablePrefix' => 'craft',
    'database' => 'dar_williams_craft'
  ),
  '.dev' => array(
    'server' => 'localhost',
    'user' => 'root',
    'password' => 'root',
  ),
  'default' => array(
    'server' => 'localhost',
    'user' => 'forge',
    'password' => 'UMk6qQYeWofjRb7p1vNs'
  )
);
