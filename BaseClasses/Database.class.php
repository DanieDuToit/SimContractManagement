<?php

	include_once('functions.inc.php');

	/**
	 * Created by PhpStorm.
	 * User: Danie
	 * Date: 2014/10/26
	 * Time: 01:53 PM
	 */
	class Database
	{
		private static $_instance;
		// Store the single instance.
		private $_connection;

		/**
		 * Constructor
		 */
		public function __construct()
		{
			$connectionInfo    = array("UID"                  => DBSettings::$dbUser,
												"PWD"                  => DBSettings::$dbPass,
												"Database"             => DBSettings::$database,
												"ReturnDatesAsStrings" => true);
			$this->_connection = sqlsrv_connect(DBSettings::$Server, $connectionInfo); // Creates and opens a connection.
			if ($this->_connection == null) {
				trigger_error('Failed to connect to SQL: ' . dbGetErrorMsg());
			}
		}

		/**
		 * Get an instance of the Database.
		 * @return Database
		 */
		public static function getInstance()
		{
			if (!self::$_instance) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Get the mysqli connection.
		 */
		public function getConnection()
		{
			return $this->_connection;
		}

		/**
		 * Empty clone magic method to prevent duplication.
		 */
		private function __clone()
		{
		}
	}
