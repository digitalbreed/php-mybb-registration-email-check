<?php

/*
 * Registration eMail Check (RMC) for MyBB
 * by Matthias Gall
 *
 * http://www.digitalbreed.com/2008/mybb-registration-email-check
 *
 * Version: 1.0
 */

// Install a hook into the registration process. This hook is called when the
// users registration form data is submitted.
$plugins->add_hook( 'member_do_register_start', 'rmc_do_register' );

/**
 * Provides information on the plugin. Required by the MyBB plugin interface.
 *
 * @return An array with plugin information as required by the MyBB plugin
 *   interface.
 */
function rmc_info()
{
	return array(
		'name'			=> 'Registration eMail Check for MyBB',
		'description'	=> 'Provides means to disallow email addresses from certain host names (e.g. so-called anti-spam email providers like mailinator.com) and verify an email by contacting the server.',
		'website'		=> 'http://www.digitalbreed.com/2008/mybb-registration-email-check',
		'author'		=> 'Matthias Gall',
		'authorsite'	=> 'http://www.digitalbreed.com/',
		'version'		=> '1.0',
	);
}

/**
 * Called when the RMC plugin is activated from the MyBB Plugin Manager.
 * Inserts a new settings group "Registration eMail Check" to the Board
 * Settings and adds three settings for RMC.
 */
function rmc_activate()
{
	global $db, $mybb;

	// Create a new Board Settings group.
	$rmc_group = array(
		"name"			=> "rmc_group",
		"title"			=> "Registration eMail Check",
		"description"	=> "Validates registration email addresses",
		"disporder"		=> "25",
		"isdefault"		=> "no",
	);
	$db->insert_query( TABLE_PREFIX . "settinggroups", $rmc_group );
	$gid = $db->insert_id();

	// Setting to toggle RMC on/off.
	$new_setting = array(
		'name'			=> 'rmc_on',
		'title'			=> 'Enable eMail Check',
		'description'	=> 'If enabled, a users email address is verified before registration proceeds.',
		'optionscode'	=> 'yesno',
		'value'			=> 'yes',
		'disporder'		=> '1',
		'gid'			=> intval( $gid )
	);
	$db->insert_query( TABLE_PREFIX . 'settings', $new_setting );

	// Setting which contains all disallowed hosts.
	$new_setting = array(
		'name'			=> 'rmc_disallowed_hosts',
		'title'			=> 'Disallowed Hosts',
		'description'	=> 'E-Mail host names which are disallowed for registration, separated by a line break.<br/>Example:<br/><strong>hotmail.com<br/>mailinator.com</strong>',
		'optionscode'	=> 'textarea',
		'value'			=> 'mailinator.com
mailinator2.com
sogetthis.com
mailin8r.com
spamherelots.com
thisisnotmyrealemail.com
tempmail.info
spamavert.com
pookmail.com
dumpmail.net
sofort-mail.de
spambog.com
eintagsmail.de
dontsendmespam.de
temporaryinbox.com
mx0.wwwnew.eu
bodhi.lawlita.com
anonbox.net
spamgourmet.com
mintemail.com
mintemails.info.tm
oneoffmail.com
aravensoft.com
dodgit.com
maileater.com
mailnull.com
trashymail.com
nospamfor.us
nospam4.us
shortmail.net
skeefmail.net
spam.la
spam.su
spambox.us
spamfree24.org
spamfree24.com
spamfree24.eu
spamfree24.org
spamfree24.net
spamfree24.info
spamfree24.de
spaml.com
tempemail.net
disposeamail.com',
		'disporder'		=> '2',
		'gid'			=> intval( $gid )
	);
	$db->insert_query( TABLE_PREFIX . 'settings', $new_setting );

	// Setting to enable "live" checking of an email address.
	$new_setting = array(
		'name'			=> 'rmc_live_check',
		'title'			=> 'Live Check',
		'description'	=> 'If enabled, RMC connects to the email host and verifies the email address.',
		'optionscode'	=> 'yesno',
		'value'			=> 'yes',
		'disporder'		=> '3',
		'gid'			=> intval( $gid )
	);
	$db->insert_query( TABLE_PREFIX . 'settings', $new_setting );

	rebuildsettings();
}

/**
 * Called when the RMC plugin is deactivated from the MyBB Plugin Manager.
 * Removes all RMC settings from database.
 */
function rmc_deactivate()
{
	global $db;

	$db->query( "DELETE FROM " . TABLE_PREFIX . "settings WHERE name = 'rmc_on'");
	$db->query( "DELETE FROM " . TABLE_PREFIX . "settings WHERE name = 'rmc_disallowed_hosts'");
	$db->query( "DELETE FROM " . TABLE_PREFIX . "settings WHERE name = 'rmc_live_check'");
	$db->delete_query( TABLE_PREFIX . "settinggroups", "name = 'rmc_group'");

	rebuildsettings();
}

/**
 * Called from a MyBB plugin hook in member.php when the users registration
 * data is submitted.
 *
 * If RMC is enabled, a number of checks are performed:
 *   1. The email address format is checked.
 *   2. The host part of the email address is compared against a list of
 *      disallowed hosts.
 *   3. If enabled, the host is contacted to verify whether the email actually
 *      exists.
 *
 * If any of these checks fails, an error message is displayed and the
 * registration process is aborted.
 */
function rmc_do_register()
{
	global $mybb;

	if( $mybb->settings[ 'rmc_on' ] == "yes" )
	{
		$email = $mybb->input[ 'email' ];

		// Check email format.
		if( !eregi( "^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $email ) )
		{
			rmc_fail( "\"$email\" is not a valid email address!" );
		}

		// Check whether the email host is in the list of disallowed hosts.
		list( $user, $host ) = split( "@", $email );
		$host = strtolower( trim( $host ) );
		$disallowedHosts = explode( "\n", $mybb->settings[ 'rmc_disallowed_hosts' ] );
		for( $i = 0; $i < count( $disallowedHosts ); $i++ )
		{
			if( strcasecmp( trim( $disallowedHosts[ $i ] ), $host ) == 0 )
			{
				rmc_fail("\"$host\" email addresses are not allowed!");
			}
		}

		// Finally check whether the email address really exists.
		if( $mybb->settings[ 'rmc_live_check' ] == "yes" )
		{
			$result = rmc_check_mail( $email );
			if( !$result[ 0 ] )
			{
				rmc_fail( $result[ 1 ] );
			}
		}
	}
}

/**
 * Checks whether a given email actually exists by contacting the host (or an
 * alternative host resolved by getmxrr) on port 25 (SMTP) and attempting to
 * send an email from the given address to the given address. If this works,
 * the server responds with SMTP return code 250, which indicates that the
 * server accepted the given address.
 *
 * @param email The email address to check, e.g. "foobar@mailinator.com".
 *
 * @return An array (boolean, string) where the first element indicates
 *   whether the check was successful (true, i.e. the email address seems
 *   to be valid) or not (false), and an additional message as the second
 *   element.
 */
function rmc_check_mail( $email )
{
	global $HTTP_HOST;

	$result = array();

	list( $username, $domain ) = split( "@", $email );

	if( getmxrr( $domain, $mxhost ) )
	{
		$connectAddress = $mxhost[ 0 ];
	}
	else
	{
		$connectAddress = $domain;
	}

	$connect = fsockopen( $connectAddress, 25 );

	if( $connect )
	{
		if( ereg( "^220", $out = fgets( $connect, 1024 ) ) )
		{
			fputs( $connect, "HELO $HTTP_HOST\r\n" );
			$out = fgets( $connect, 1024 );
			fputs( $connect, "MAIL FROM: <{$email}>\r\n" );
			$from = fgets( $connect, 1024 );
			fputs( $connect, "RCPT TO: <{$email}>\r\n" );
			$to = fgets( $connect, 1024 );
			fputs( $connect, "QUIT\r\n" );
			fclose( $connect );

			if( !( ereg( "^250", $from ) > 0 && ereg( "^250", $to ) > 0 ) )
			{
				$result[ 0 ] = false;
				$result[ 1 ] = "Server \"" . $connectAddress . "\" rejected address \"" . $email . "\"!";
				return $result;
			}
		}
		else
		{
			$result[ 0 ] = false;
			$result[ 1 ] = "No response from server \"" . $connectAddress . "\"!";
			return $result;
		}
	}
	else
	{
		$result[ 0 ] = false;
		$result[ 1 ] = "Cannot connect to the server \"" . $connectAddress . "\"!";
		return $result;
	}

	$result[ 0 ] = true;
	$result[ 1 ] = "\"$email\" appears to be a valid email address";

	return $result;
}

/**
 * Wrapper for MyBB's error method which enhances the given message with some
 * additional text.
 *
 * @param message The message to display as the error reason.
 */
function rmc_fail( $message )
{
	$message = "<p><strong>Registration Failed</strong></p><p>"
				. $message
				. "</p><p>Please use the \"Back\" button of your browser to return to the previous page and try a different email address.</p>";

	error( $message );
}

?>
