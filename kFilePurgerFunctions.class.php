<?php

/**
 * Author: Matthew Ahrenstein
 * Project: Kaltura File Purger
 * File created on: 7/8/14 19:57
 * License: AGPL
 * Test on: Kaltura Hercules+
 */

require_once("/opt/kaltura/app/tests/lib/KalturaClient.php"); //Require the Kaltura library for the server. This assumes you installed Kaltura in the default location


class kFilePurgeFunctions
{
	/**
	 * Create a valid KS for the chosen partner
	 *
	 * @param $partnerId string Partner ID number
	 * @param $secret string Partner admin secret
	 * @param $userId string Partner user secret
	 * @param $serviceUrl string Full URL of API server
	 *
	 * @return KalturaClient
	 * Returns a valid KS for use with other Kaltura functions
	 */
	public function getKs($partnerId, $secret, $userId, $serviceUrl)
	{
		//Setup a config to connect to Kaltura client with
		$kConfig             = new KalturaConfiguration($partnerId);
		$kConfig->serviceUrl = $serviceUrl;
		//Make a Kaltura session
		$Ks        = new KalturaClient($kConfig);
		$type      = KalturaSessionType::ADMIN;
		$expiry    = 86400; //KS will last one day. Scripts that need to run longer than a single day will have issues
		$privilege = NULL;
		//Get the KS
		$kClient = $Ks->session->start($secret, $userId, $type, $partnerId, $expiry, $privilege);
		$Ks->SetKs($kClient);

		return $Ks;
	}

	/**
	 * Recursive version of glob
	 * Original function located here: https://gist.github.com/wooki/3215801
	 *
	 * @param $pattern string /path/to/root/*file*
	 * @param int $flags Variable is optional and not needed for our use case
	 *
	 * @return array Array of files matching pattern
	 */
	public function glob_recursive($pattern, $flags = 0)
	{

		$files = glob($pattern, $flags);

		foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir)
		{
			$files = array_merge($files, $this->glob_recursive($dir . '/' . basename($pattern), $flags));
		}

		return $files;
	}


	/**
	 * Physically delete the files related to deleted media entries from the disk and set the status in file_sync table to purged (sataus 4)
	 *
	 * @param $Ks KalturaClient KS of partner you want to delete files for
	 * @param $partnerId string Partner ID of the partner we are working with
	 * @param $mysqlServer string MySQL server that the Kaltura installation uses
	 * @param $mysqlUser string MySQL user that the Kaltura installation uses
	 * @param $mysqlPassword string MySQL password that the Kaltura installation uses
	 */
	public function purgeDeletedEntries($Ks, $partnerId, $mysqlServer, $mysqlUser, $mysqlPassword)
	{
		$filter              = new KalturaBaseEntryFilter(); //Set a Kaltura filter
		$filter->statusEqual = KalturaEntryStatus::DELETED; //Filter to only show deleted entries
		$entries             = $Ks->baseEntry->listAction($filter, NULL); //Store the entries from the filter in a Kaltura object

		//Unfortunately there is no way to change the file_sync status to PURGED using the API. We have to do it directly in MySQL
		$mysqlConnection = mysqli_connect($mysqlServer, $mysqlUser, $mysqlPassword); //Establish a MySQL connection to the MySQL server
		if (!$mysqlConnection)
		{
			die("Could not connect to MySQL" . mysql_error());
		}
		mysqli_select_db($mysqlConnection, "kaltura"); //Connect to the kaltura database as it contains the file_sync table
		foreach ($entries->objects as $entry) //Loop through the entries object for the entry arrays
		{
			$entryRelatedFiles = $this->glob_recursive("/opt/kaltura/web/content/entry/data/*" . $entry->id . "*"); //Recursive glob search of all files matching entry ID
			foreach ($entryRelatedFiles as $filename) //Iterate over results of recursive glob search and perform file operations on each file
			{
				echo "File to delete: " . $filename . "\n"; //Just tell us about the file to be deleted
				try
				{
					unlink($filename); //Physically delete the file from the server
					$fileSyncName = substr($filename, strlen("/opt/kaltura/web")); //Strip /opt/kaltura/web from path for use with MySQL query
					mysqli_query($mysqlConnection, "UPDATE file_sync SET status = 4 WHERE partner_id = " . $partnerId . " AND file_path = " . "\"$fileSyncName\""); //Run the query to delete the file
					echo "Deleted file " . $filename . " for entry ID " . $entry->id . "\n"; //Tell the user what operation was done
				}
				catch (exception $ex)
				{
					echo "An error has occurred during operations on file: " . $filename . "  ERROR: " . $ex . "\n"; //Print error if operation fails
				}
			}

		}
		mysqli_close($mysqlConnection); //Close the MySQL connection
	}
}
