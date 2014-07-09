<?php

/**
 * Author: Matthew Ahrenstein
 * Project: Kaltura File Purger
 * File created on: 7/8/14 20:32
 * License: AGPL
 * Test on: Kaltura Hercules+
 */

require_once("kFilePurgerFunctions.class.php"); //Add the class containing the kFilePurge functions

$partnerID = "102"; //Replace with your partner ID
$adminSecret = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF"; //Test partner's admin secret
$userSecret = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF"; //Test partner's user secret
$serviceUrl = "http://my.kalturaServer.com"; //Test server's API url
$mysqlServer = "localhost"; //Replace with your MySQL server
$mysqlUser = "kaltura"; //Replace with your MySQL user
$mysqlPassword = "kaltura"; //Replace with your MySQL password

$purger = new kFilePurgeFunctions(); //Start a new instance of the kFilePurgeFunctions class
$Ks = $purger->getKs($partnerID, $adminSecret, $userSecret, $serviceUrl); //Start and store a valid KS for use with the file purging functions

$purger->purgeDeletedEntries($Ks, $partnerID, $mysqlServer, $mysqlUser, $mysqlPassword); //Provide a KS and MySQL credentials for the partner we want to cleanup deleted entries for
