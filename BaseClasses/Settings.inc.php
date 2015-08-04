<?php

	//collate SQL_Latin1_General_CP1_CI_AS
	class ApplicationSettings
	{
		static $versionNumber = "1.0.0.0 ";
		static $applicationPrefix = "ANDROID";
		static $applicationTitle = "Android Asset Management";
	}

	class DBSettings
	{
		static $extension = "sqlsrv";
		static $database = "SimContractManagement";
		static $dbUser = "SimContractManagement";
		static $dbPass = "SimContractManagement";
		static $conn = null;
		// At work
		static $Server = "localhost";

		// At home
		//			static $Server = "DANIE-HP";
	}
