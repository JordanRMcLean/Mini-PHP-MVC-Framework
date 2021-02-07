<?php

class InstallSQL {

	public static $sessions_table_install = ["
	CREATE TABLE `" . CONFIG::SESSIONS_TABLE . "` (
		  `session_id` varchar(255) NOT NULL,
		  `session_user_id` int(7) NOT NULL,
		  `session_login_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	  ) ENGINE=InnoDB DEFAULT CHARSET=latin1;","
	 ALTER TABLE `" . CONFIG::SESSIONS_TABLE . "`
	  ADD UNIQUE KEY `session_id` (`session_id`);"];

	public static $users_table_install = ["
	CREATE TABLE `" . CONFIG::USERS_TABLE . "` (
	  `user_id` int(11) NOT NULL,
	  `user_email` varchar(255) NOT NULL,
	  `user_password` varchar(255) NOT NULL,
	  `user_permission_role` int(2) NOT NULL DEFAULT '2',
	  `user_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	  `user_last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;","
	ALTER TABLE `" . CONFIG::USERS_TABLE . "`
	  ADD PRIMARY KEY (`user_id`);","
	ALTER TABLE `". CONFIG::USERS_TABLE . "`
  	  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;"];

	public static $actions_table_install = ["
	CREATE TABLE `". CONFIG::PERMISSION_ACTIONS_TABLE . "` (
	  `action_id` int(11) NOT NULL,
	  `action_key` varchar(60) NOT NULL,
	  `action_desc` varchar(250) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;","
	ALTER TABLE `". CONFIG::PERMISSION_ACTIONS_TABLE . "`
	  ADD PRIMARY KEY (`action_id`);","
	ALTER TABLE `". CONFIG::PERMISSION_ACTIONS_TABLE . "`
   	  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;"];

	public static $roles_table_install = ["
	CREATE TABLE `" . CONFIG::PERMISSION_ROLES_TABLE . "` (
	  `role_id` int(11) NOT NULL,
	  `role_name` varchar(255) NOT NULL,
	  `role_desc` varchar(255) NOT NULL,
	  `role_actions` varchar(255) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;","
	ALTER TABLE `" . CONFIG::PERMISSION_ROLES_TABLE . "`
	  ADD PRIMARY KEY (`role_id`);","
	ALTER TABLE `" . CONFIG::PERMISSION_ROLES_TABLE . "`
  	  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;"];

}
