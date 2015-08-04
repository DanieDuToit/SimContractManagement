<?php
	include_once("BaseClasses/BaseDB.class.php");
	include_once("BaseClasses/Database.class.php");

	$file_handle = fopen("itm-C0002316-1.csv", "r");
	if ($file_handle == false) {
		print "Unable to open file!!!";
		return false;
	}
	$line_of_text  = '';
	$resultMessage = '';

	$db = new BaseDB();
	$db->dbTransactionBegin();

	if (true) {
		while (!feof($file_handle) && strlen($resultMessage) === 0) {
			global $line_of_text;
			set_time_limit(10);
			$line_of_text = fgetcsv($file_handle, 1024, ',');

			switch (true) {
				case startsWith($line_of_text[0], 'New Client - Cell Number : '):
				case startsWith($line_of_text[0], 'Voicemail - Deposits'):
				case startsWith($line_of_text[0], 'Vodacom to'):
				case startsWith($line_of_text[0], 'General Services'):
				case startsWith($line_of_text[0], 'Video Calls'):
				case startsWith($line_of_text[0], 'Top Up -'):
				case startsWith($line_of_text[0], 'International Calls'):
				case startsWith($line_of_text[0], 'Short Voice Service'):
				case startsWith($line_of_text[0], 'Roaming'):
				case startsWith($line_of_text[0], 'SMS -'):
				case startsWith($line_of_text[0], 'MMS -'):
				case startsWith($line_of_text[0], 'Data Usage'):
				case startsWith($line_of_text[0], 'Content Services'):
				case startsWith($line_of_text[0], 'Vodafone Live'):
				case startsWith($line_of_text[0], 'USSD'):
				case startsWith($line_of_text[0], 'Calls - Category'):
				case startsWith($line_of_text[0], 'Sub Total'):
				case startsWith($line_of_text[0], 'Total'):
				case startsWith($line_of_text[0], 'Calls - '):
				case startsWith($line_of_text[0], 'GPRS CHARGES'):
				case startsWith($line_of_text[0], 'TELEPHONY CHARGES'):
				case startsWith($line_of_text[0], 'Date'):
					break;
					break;
				case
				(is_numeric(substr($line_of_text[0], 0, 1)) || strlen(trim($line_of_text[0])) == 0):
					break;
				default:
					insertRecord(trim($line_of_text[0]));
			}
		}
		if ($resultMessage === '') {
			print 'SUCCESS';
			$db->dbTransactionCommit();
		} else {
			echo $resultMessage;
			$db->dbTransactionRollback();
		}
		fclose($file_handle);
		$db->close();
		return true;
	}

	function insertRecord($text)
	{
		global $resultMessage;
		global $db;

		$sql     = "
			SELECT count(*) AS counter FROM HeaderNames WHERE HeaderName = '$text'
		";
		$records = $db->dbQuery($sql);

		if ($records) {
			$row = sqlsrv_fetch_array($records, SQLSRV_FETCH_ASSOC);
			if ($row['counter'] == '0') {
				// Headername does not exist yet
				// Insert record
				echo 'NEW HEADER: ' . $text . '<br>';
				$sql    = "
				INSERT INTO HeaderNames (HeaderName) VALUES ('$text');
			";
				$result = $db->dbQuery($sql);
				if ($result == false) {
					$resultMessage = 'ERROR: ' . dbGetErrorMsg();
					$db->dbTransactionRollback();
				}
			}
		} else {
			$resultMessage = 'ERROR: ' . dbGetErrorMsg();
			$db->dbTransactionRollback();
		}
	}

	function startsWith($haystack, $needle)
	{
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
	}
