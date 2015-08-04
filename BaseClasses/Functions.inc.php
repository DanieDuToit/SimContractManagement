<?php
	include_once("BaseDB.class.php");
	include_once("Database.class.php");

	//connect to the db
	$dbBaseClass = new BaseDB();


	function dbGetErrorMsg()
	{
		$retVal = sqlsrv_errors();
		$retVal = $retVal[0]["message"];
		$retVal = preg_replace('/\[Microsoft]\[SQL Server Native Client [0-9]+.[0-9]+](\[SQL Server\])?/', '', $retVal);
		return $retVal;
	}//dbGetErrorMsg()
