<?php
	include_once("BaseClasses/BaseDB.class.php");
	include_once("BaseClasses/Database.class.php");

	$bytes                 = 0;
	$callDurationInSeconds = 0;
	$dateTimeOfCall        = new DateTime();
	$isData                = false;
	$costOfCall            = 0.00;
	$callDestination       = '';
	$VASName               = '';
	$VASProvider           = '';
	$billMonth             = 0;
	$billYear              = 0;
	$simContractID         = 0;
	$serviceDescription    = '';
	$file_handle           = fopen("itm-C0009442-8.csv", "r");
	$line_of_text          = new ArrayObject();
	$cellphoneNumber       = '';
	$resultMessage         = '';
	$categoryId            = 0;

	$db = new BaseDB();
	$db->dbTransactionBegin();

	$startTime = date_create();
	//		$i         = 10000;

	if ($file_handle != false) {
		//				while (!feof($file_handle) && $i > 0 && strlen($resultMessage) === 0) {
		while (!feof($file_handle) && strlen($resultMessage) === 0) {
			//		while (!feof($file_handle)) {
			global $line_of_text;
			set_time_limit(10);
			$line_of_text = fgetcsv($file_handle, 1024, ',');

			switch (true) {
				case startsWith($line_of_text[0], 'New Client - Cell Number : '):
					SetSimContractID(trim($line_of_text[0]));
					break;
				case startsWith($line_of_text[0], 'Voicemail - Deposits'):
				case startsWith($line_of_text[0], 'Vodacom to'):
				case startsWith($line_of_text[0], 'General Services'):
				case startsWith($line_of_text[0], 'Video Calls'):
				case startsWith($line_of_text[0], 'Top Up -'):
				case startsWith($line_of_text[0], 'International Calls'):
				case startsWith($line_of_text[0], 'Short Voice Service'):
				case startsWith($line_of_text[0], 'Roaming'):
					HandleCallsForCategory(1);; // CategoryId = 1
					break;
				case startsWith($line_of_text[0], 'SMS -'):
					HandleSMSs('SMS', 2); // CategoryId = 2
					break;
				case startsWith($line_of_text[0], 'MMS -'):
					HandleSMSs('MMS', 2); // CategoryId = 2
					break;
				case startsWith($line_of_text[0], 'Data Usage'):
					HandleDataUsage(3); // CategoryId = 3
					break;
				case startsWith($line_of_text[0], 'Content Services'):
				case startsWith($line_of_text[0], 'Vodafone Live'):
				case startsWith($line_of_text[0], 'USSD'):
					HandleContentServiceUsage(4); // CategoryId = 3
					break;
				default:
					;
			}
			//						$i--;
		}
		$endTime = date_create();
		$runTime = $startTime->diff($endTime);
		if ($resultMessage === '') {
			print 'SUCCESS: The time it took to run the job: ' . $runTime->format('%H:%I:%S');
			$db->dbTransactionCommit();
		} else {
			echo $resultMessage;
			$db->dbTransactionRollback();
		}
		fclose($file_handle);
		$db->close();
		return true;
	}

	function stripNumerics($str)
	{
		$strlen = strlen($str);
		$id     = "";
		for ($i = 0; $i <= $strlen; $i++) {
			$char = substr($str, $i, 1);
			if (is_numeric($char)) {
				break;
			}
			$id .= $char;
		}
		return $id;
	}

	function SetSimContractID($str)
	{
		global $simContractID;
		global $cellphoneNumber;
		global $resultMessage;
		global $db;
		$subStr          = 'New Client - Cell Number : ';
		$cellphoneNumber = trim(substr($str, strlen($subStr), strlen($str) - strlen($subStr) + 1));

		$sql = "
			SELECT TOP(1) SimContractID
			FROM SimContractManagement.dbo.SimContract
			WHERE CellphoneNumber LIKE '%$cellphoneNumber%'
      ";

		$records = $db->dbQuery($sql);
		if (!$records) {
			$resultMessage = 'ERROR: ' . dbGetErrorMsg();
			$db->dbTransactionRollback();
			return;
		}
		if (sqlsrv_has_rows($records)) {
			$row           = sqlsrv_fetch_array($records, SQLSRV_FETCH_ASSOC);
			$simContractID = $row['SimContractID'];
		} else {
			//			$resultMessage = "Contract for SIM: " . $cellphoneNumber . " does not exist <br>";
			// No contract for this cellphone - Insert a record
			$sql     = "
				INSERT INTO SimContractManagement.dbo.SimContract (CellphoneNumber) VALUES ('$cellphoneNumber')";
			$records = $db->dbQuery($sql);
			if (!$records) {
				$resultMessage = 'ERROR: ' . dbGetErrorMsg();
				$db->dbTransactionRollback();
				return;
			}
			$sql    = "
				SELECT IDENT_CURRENT('SimContractManagement.dbo.SimContract') AS SimContractID;
			";
			$record = $db->dbQuery($sql);
			if (!$record) {
				$resultMessage = 'ERROR: ' . dbGetErrorMsg();
				$db->dbTransactionRollback();
				return;
			}
			$row           = sqlsrv_fetch_array($record, SQLSRV_FETCH_ASSOC);
			$simContractID = $row['SimContractID'];
		}
		echo 'SimContractID: ' . $simContractID . ' Cellphone: ' . $cellphoneNumber . '<br>';
	}

	function HandleCallsForCategory($cId)
	{
		global $line_of_text;
		global $file_handle;
		global $bytes;
		global $callDurationInSeconds;
		global $dateTimeOfCall;
		global $isData;
		global $costOfCall;
		global $callDestination;
		global $VASName;
		global $VASProvider;
		global $billMonth;
		global $billYear;
		global $serviceDescription;
		global $categoryId;

		$line_of_text = fgetcsv($file_handle, 1024, ',');
		while (!feof($file_handle) && substr($line_of_text[0], 0, 1) != '') {
			$line_of_text = str_replace("'", '', $line_of_text);
			set_time_limit(10);

			$categoryId = $cId;

			// Date and Time of call
			$strippedLineOfText = $line_of_text[0];
			$day                = substr($strippedLineOfText, 0, 2);
			$month              = substr($strippedLineOfText, 3, 2);
			$year               = substr($strippedLineOfText, 6, 4);
			$s                  = $year . '/' . $month . '/' . $day . ' ' . trim($line_of_text[1]);
			$dt                 = strtotime($s);
			$dateTimeOfCall     = date('d M Y H:i:s', $dt);


			// Duration of call in seconds
			$callLengthArray       = explode(':', $line_of_text[2]);
			$callHours             = $callLengthArray[0];
			$callMinutes           = $callLengthArray[1];
			$callSeconds           = $callLengthArray[2];
			$callDurationInSeconds = (intval($callHours) * 60 * 60) + (intval($callMinutes) * 60) + (intval($callSeconds));

			// Get recipient's phone number
			if (isset($line_of_text[3]))
				$callDestination = $line_of_text[3];
			else
				$callDestination = '';

			// Get cost of call
			if (isset($line_of_text[4]))
				$costOfCall = floatval($line_of_text[4]);
			else
				$costOfCall = 0.00;

			$billMonth = $month;
			$billYear  = $year;

			// Set unrelated variables
			$VASName            = '';
			$isData             = false;
			$VASProvider        = '';
			$bytes              = 0;
			$serviceDescription = '';

			insertRecord();
			$line_of_text = fgetcsv($file_handle, 1024, ',');
		}
	}

	function HandleSMSs($type, $cId)
	{
		global $line_of_text;
		global $file_handle;
		global $bytes;
		global $callDurationInSeconds;
		global $dateTimeOfCall;
		global $isData;
		global $costOfCall;
		global $callDestination;
		global $VASName;
		global $VASProvider;
		global $billMonth;
		global $billYear;
		global $serviceDescription;
		global $categoryId;
		global $simContractID;
		global $resultMessage;

		$line_of_text = fgetcsv($file_handle, 1024, ',');
		while (!feof($file_handle) && substr($line_of_text[0], 0, 1) != '') {
			$line_of_text = str_replace("'", '', $line_of_text);
			set_time_limit(10);

			$categoryId = $cId;

			// Date and Time of call
			$strippedLineOfText = $line_of_text[0];
			$day                = substr($strippedLineOfText, 0, 2);
			$month              = substr($strippedLineOfText, 3, 2);
			$year               = substr($strippedLineOfText, 6, 4);
			$s                  = $year . '/' . $month . '/' . $day . ' ' . trim($line_of_text[1]);
			$dt                 = strtotime($s);
			$dateTimeOfCall     = date('d M Y H:i:s', $dt);

			if (isset($line_of_text[2])) {
				if ($type == 'MMS') {
					$bytes = intval(str_replace(',', '', $line_of_text[2]));
				} else {
					$bytes = 0;
				}
			}

			// Get recipient's phone number
			if (isset($line_of_text[3]))
				$callDestination = $line_of_text[3];
			else
				$callDestination = '';

			//Get Service Description
			if (isset($line_of_text[4]))
				$serviceDescription = rtrim($line_of_text[4]);
			else
				$serviceDescription = '';

			// Get cost of call
			// Voice Mail do not have a Cost of call index
			if (isset($line_of_text[5]))
				$costOfCall = floatval(str_replace(',', '', $line_of_text[5]));
			else
				$costOfCall = 0.00;

			$billMonth = $month;
			$billYear  = $year;

			// Set unrelated variables
			$VASName               = '';
			$isData                = false;
			$VASProvider           = '';
			$callDurationInSeconds = 0;

			insertRecord();
			$line_of_text = fgetcsv($file_handle, 1024, ',');
		}
	}

	function HandleDataUsage($cId)
	{
		global $line_of_text;
		global $file_handle;
		global $bytes;
		global $callDurationInSeconds;
		global $dateTimeOfCall;
		global $isData;
		global $costOfCall;
		global $callDestination;
		global $VASName;
		global $VASProvider;
		global $billMonth;
		global $billYear;
		global $serviceDescription;
		global $categoryId;
		global $simContractID;
		global $resultMessage;

		$line_of_text = fgetcsv($file_handle, 1024, ',');
		while (!feof($file_handle) && substr($line_of_text[0], 0, 1) != '') {
			$line_of_text = str_replace("'", '', $line_of_text);
			set_time_limit(10);

			$categoryId = $cId;

			// Date and Time of call
			$strippedLineOfText = $line_of_text[0];
			$day                = substr($strippedLineOfText, 0, 2);
			$month              = substr($strippedLineOfText, 3, 2);
			$year               = substr($strippedLineOfText, 6, 4);
			$s                  = $year . '/' . $month . '/' . $day;
			$dt                 = strtotime($s);
			$dateTimeOfCall     = date('d M Y H:i:s', $dt);

			// Get bytes used
			if (isset($line_of_text[1]))
				$bytes = intval(str_replace(',', '', $line_of_text[1]));
			else
				$bytes = 0;

			//Get Service Description
			if (isset($line_of_text[2]))
				$serviceDescription = rtrim($line_of_text[2]);
			else
				$serviceDescription = '';

			// Get recipient's phone number
			if (isset($line_of_text[3]))
				$callDestination = $line_of_text[3];
			else
				$callDestination = '';

			// Get cost of call
			if (isset($line_of_text[5]))
				$costOfCall = floatval(str_replace(',', '', $line_of_text[5]));
			else
				$costOfCall = 0.00;

			$billMonth = $month;
			$billYear  = $year;
			$isData    = true;

			// Set unrelated variables
			$VASName               = '';
			$VASProvider           = '';
			$callDurationInSeconds = 0;

			insertRecord();
			$line_of_text = fgetcsv($file_handle, 1024, ',');
		}
	}

	function HandleContentServiceUsage($cId)
	{
		global $line_of_text;
		global $file_handle;
		global $bytes;
		global $callDurationInSeconds;
		global $dateTimeOfCall;
		global $isData;
		global $costOfCall;
		global $callDestination;
		global $VASName;
		global $VASProvider;
		global $billMonth;
		global $billYear;
		global $serviceDescription;
		global $categoryId;
		global $simContractID;
		global $resultMessage;

		$line_of_text = fgetcsv($file_handle, 1024, ',');
		while (!feof($file_handle) && substr($line_of_text[0], 0, 1) != '') {
			$line_of_text = str_replace("'", '', $line_of_text);
			set_time_limit(10);

			$categoryId = $cId;

			// Date and Time of call
			$strippedLineOfText = $line_of_text[0];
			$day                = substr($strippedLineOfText, 0, 2);
			$month              = substr($strippedLineOfText, 3, 2);
			$year               = substr($strippedLineOfText, 6, 4);
			$s                  = $year . '/' . $month . '/' . $day . ' ' . trim($line_of_text[1]);
			$dt                 = strtotime($s);
			$dateTimeOfCall     = date('d M Y H:i:s', $dt);

			// Get bytes used
			$bytes = 0;

			//Get Service Description
			if (isset($line_of_text[4]))
				$serviceDescription = rtrim($line_of_text[4]);
			else
				$serviceDescription = rtrim($line_of_text[4]);

			// VAS Provider
			if (isset($line_of_text[3]))
				$VASProvider = rtrim($line_of_text[3]);
			else
				$VASProvider = rtrim($line_of_text[3]);

			// VAS Name
			if (isset($line_of_text[4]))
				$VASName = rtrim($line_of_text[4]);
			else
				$VASName = rtrim($line_of_text[4]);

			// Get cost of call
			if (isset($line_of_text[5]))
				$costOfCall = floatval(str_replace(',', '', $line_of_text[5]));
			else
				$costOfCall = 0.00;

			$billMonth = $month;
			$billYear  = $year;
			$isData    = false;

			// Set unrelated variables
			$callDurationInSeconds = 0;
			$callDestination       = '';

			insertRecord();
			$line_of_text = fgetcsv($file_handle, 1024, ',');
		}
	}

	function insertRecord()
	{
		global $simContractID;
		global $callDurationInSeconds;
		global $dateTimeOfCall;
		global $isData;
		global $costOfCall;
		global $callDestination;
		global $VASName;
		global $VASProvider;
		global $billMonth;
		global $billYear;
		global $bytes;
		global $db;
		global $resultMessage;
		global $serviceDescription;
		global $categoryId;

		$isData             = ($isData == true) ? 1 : 0;
		$callDestination    = trim($callDestination);
		$VASName            = trim($VASName);
		$VASProvider        = trim($VASProvider);
		$serviceDescription = trim($serviceDescription);

		//		echo $cellphoneNumber . ' Dest: ' . $callDestination . '<br>';
		$sql = "
			INSERT INTO [dbo].[CallDetail]
						  ([SimContractID]
						  ,[DateTimeOfCall]
						  ,[CallDurationInSeconds]
						  ,[CallDestination]
						  ,[VASName]
						  ,[VASProvider]
						  ,[CostOfCall]
						  ,[BillYear]
						  ,[BillMonth]
						  ,[IsData]
						  ,[Bytes]
						  ,[ServiceDescription]
						  ,[CategoryId])
				  VALUES
						  ($simContractID
						  ,'$dateTimeOfCall'
						  ,$callDurationInSeconds
						  ,'$callDestination'
						  ,'$VASName'
						  ,'$VASProvider'
						  ,$costOfCall
						  ,$billYear
						  ,$billMonth
						  ,$isData
						  ,$bytes
						  ,'$serviceDescription'
						  ,$categoryId)
        ";
		try {
			$result = $db->dbQuery($sql);
			if ($result == false) {
				$resultMessage = 'ERROR: ' . dbGetErrorMsg();
				$db->dbTransactionRollback();
				return;
			}
		} catch (Exception $exc) {
			$resultMessage = 'EXCEPTION: ' . $exc;
		}
	}

	function startsWith($haystack, $needle)
	{
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
	}
