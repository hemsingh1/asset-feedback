<?PHP
//
// Dropbox 2.2
// Copyright (C) 2006 Jeffrey Frey, frey at udel dot edu
//
// Based on the original PERL dropbox written by Doke Scott.
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//
// $Id: NSSDropbox.php 50 2008-07-09 15:54:07Z frey $
//

include_once(NSSDROPBOX_LIB_DIR."NSSAuthenticator.php");
include_once(NSSDROPBOX_LIB_DIR."NSSUtils.php");

//
// There are sooo many places where it would be nice to have the base URL
// for this site, so we can just tack-on a page or GET directives.  We
// form it quite simply by concatenating a couple of the SERVER fields.
// There may be other ways (other SERVER fields) that would work better
// for this, but the code-fu below is adequate:
//
$NSSDROPBOX_URL = "http".($_SERVER['HTTPS'] ? "s" : "")."://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
if ( !preg_match('/\/$/',$NSSDROPBOX_URL) ) {
  $NSSDROPBOX_URL = preg_replace('/\/[^\/]+$/',"/",$NSSDROPBOX_URL);
}

/*!

  @class NSSDropbox
  
  An instance of NSSDropbox serves as the parent for all dropped-off "stuff".
  The instance also acts as a container for the site's preferences; the
  connection to the SQLite database backing the dropbox; and the authenticator
  used to validate and authenticate users.  Accessors are provided for all of
  the instance data (some are read-only, but many of the preference fields
  are read-write), and some methods are implemented to handle common actions
  on the instance data.
*/
class NSSDropbox {

  //  Instance data:
  private $_dropboxName;
  private $_dropboxDomain;
  private $_dropboxDirectory;
  private $_dropboxLog = '/var/log/dropbox.log';
  private $_retainDays = 14;
  private $_authUserFormalDesc;
  private $_authUserShortDesc;
  private $_secretForCookies;
  private $_cookieName = "dropbox_session";
  private $_cookieTTL = 900;
  
  private $_secretForReferral;

  private $_maxBytesForFile = 1048576000.0;  //  1000 MB
  private $_maxBytesForDropoff = 2097152000.0; // 2000 MB
  
  private $_authenticator = NULL;
  private $_authorizedUser = NULL;
  private $_authorizationFailed = FALSE;
  private $_authorizedUserData = NULL;
  private $_emailSenderAddr = NULL;
  
  private $_contactInfo;
  private $_showRecipsOnPickup = TRUE;
  
  private $_newDatabase = FALSE;
  private $_database = NULL;

  /*!
    @function __construct
    
    Class constructor.  Takes a hash array of preference fields as its
    only parameter and initializes the instance using those values.
    
    Also gets our backing-database open and creates the appropriate
    authenticator according to the preferences.
  */
  public function __construct(
    $prefs
  )
  {
    global $NSSDROPBOX_URL;

    if ( $prefs ) {
      if ( ! $this->checkPrefs($prefs) ) {
        NSSError("The preferences are not configured properly!","Invalid Configuration");
        exit(1);
      }
    
      //  Get the database open:
      if ( $prefs['dropboxDatabase'] ) {
        if ( ! file_exists($prefs['dropboxDatabase']) ) {
          if ( ! ($this->_database = new SQLiteDatabase($prefs['dropboxDatabase'],0666)) ) {
            NSSError("Could not create the new database.","Database Error");
            return;
          }
          //  It was a new file, so we need to create tables in the database
          //  right now, too!
          if ( ! $this->setupDatabase() ) {
            NSSError("Could not create the tables in the new database.","Database Error");
            return;
          }
          //  This was a new database:
          $this->_newDatabase = TRUE;
        } else {
          if ( ! ($this->_database = new SQLiteDatabase($prefs['dropboxDatabase'])) ) {
          //if ( ! ($this->_database = new sqlite_open($prefs['dropboxDatabase'])) ) {
            NSSError("Could not open the database.","Database Error");
            return;
          }
        }
        
        //  Instance copies of the preference data:
        $this->_dropboxName           = $prefs['dropboxName'];
        $this->_dropboxDomain         = $prefs['dropboxDomain'];
        $this->_dropboxDirectory      = $prefs['dropboxDirectory'];
        $this->_authUserFormalDesc    = $prefs['authUserFormalDesc'];
        $this->_authUserShortDesc     = $prefs['authUserShortDesc'];
        $this->_dropboxLog            = $prefs['logFilePath'];
        $this->_cookieName            = $prefs['cookieName'];
        
        if ( ! ($this->_emailSenderAddr = $prefs['emailSenderAddr']) ) {
          $execAsUser = posix_getpwuid(posix_geteuid());
          $this->_emailSenderAddr = sprintf("%s <%s@%s>",
                                        $this->_dropboxName,
                                        $execAsUser['name'],
                                        $_SERVER['SERVER_NAME']
                                       );
        }
        
        if ( $intValue = intval( $prefs['numberOfDaysToRetain'] ) ) {
          $this->_retainDays          = $intValue;
        }
        if ( $prefs['cookieSecret'] ) {
          $this->_secretForCookies    = $prefs['cookieSecret'];
        }
        if ( $intValue = intval($prefs['cookieTTL']) ) {
          $this->_cookieTTL           = $intValue;
        }
        if ( $prefs['referralSecret'] ) {
          $this->_secretForReferral    = $prefs['referralSecret'];
        }
        if ( $intValue = intval($prefs['maxBytesForFile']) ) {
          $this->_maxBytesForFile     = $intValue;
        }
        if ( $intValue = intval($prefs['maxBytesForDropoff']) ) {
          $this->_maxBytesForDropoff  = $intValue;
        }
        
        $this->_contactInfo           = $prefs['contactInfo'];
        if ( $prefs['showRecipsOnPickup'] === FALSE ) {
          $this->_showRecipsOnPickup  = FALSE;
        }
        
        //  Create an authenticator based on our prefs:
        $this->_authenticator         = NSSAuthenticator($prefs);
        
        if ( ! $this->_authenticator ) {
          NSSError("The dropbox preferences have no authentication method selected.","Authentication Error");
          exit(1);
        }
        
        //  First try an authentication, since it _could_ override a cookie
        //  that was already set.  If that doesn't work, then try the cookie:
        if ( $this->userFromAuthentication() ) {
          
          $this->writeToLog("authenticated as '".$this->_authorizedUser."'");
          
          //  Set the cookie now:
          setcookie(
              $this->_cookieName,
              $this->cookieForSession(),
              time() + $this->_cookieTTL,
              "/",
              "",
              TRUE
            );
        } else {
          if ( $this->userFromCookie() ) {
            //  Update the cookie's time-to-live:
            setcookie(
                $this->_cookieName,
                $this->cookieForSession(),
                time() + $this->_cookieTTL,
                "/",
                "",
                TRUE
              );
          }
        }
      } else {
        NSSError("There was no dropbox database file path in the preferences.","Dropbox Creation Error");
        $this->writeToLog("no database file path in preferences");
      }
    } else {
      NSSError("The preferences are not configured properly (they're empty)!","Invalid Configuration");
      exit(1);
    }
  }
  
  /*!
    @function description
    
    Debugging too, for the most part.  Give a description of the
    instance.
  */
  public function description()
  {
    return sprintf("NSSDropbox {
  name:               %s
  domain:             %s
  directory:          %s
  log:                %s
  retainDays:         %d
  authUserFormalDesc: %s
  authUserShortDesc:  %s
  secretForCookies:   %s
  secretForReferral:   %s
  authorizedUser:     %s
  authenticator:      %s
}",
                $this->_dropboxName,
                $this->_dropboxDomain,
                $this->_dropboxDirectory,
                $this->_dropboxLog,
                $this->_retainDays,
                $this->_authUserFormalDesc,
                $this->_authUserShortDesc,
                $this->_secretForCookies,
                $this->_secretForReferral,
                $this->_authorizedUser,
                ( $this->_authenticator ? $this->_authenticator->description() : "<no authenticator>" )
          );
  
  }

  /*!
    @function logout
    
    Logout the current user.  This amounts to nulling our cookie and giving it a zero
    second time-to-live, which should force the browser to drop the cookie.
  */
  public function logout()
  {
    $this->writeToLog("logged-out user '".$this->_authorizedUser."'");
    setcookie(
        $this->_cookieName,
        "",
        0,
        "/",
        "",
        TRUE
      );
    $this->_authorizedUser = NULL;
    $this->_authorizedUserData = NULL;
  }

  /*!
    @function dropboxName
    
    Accessor pair for getting/setting the name of this dropbox.
  */
  public function dropboxName() { return $this->_dropboxName; }
  public function setDropBoxName(
    $dropboxName
  )
  {
    if ( $dropboxName && $dropboxName != $this->_dropboxName ) {
      $this->_dropboxName = $dropboxName;
    }
  }
  
  /*!
    @function dropboxDomain
    
    Accessor pair for getting/setting the domain portion of this dropbox's
    network.  Used to check email addresses for being "inside" or
    "outside" -- email addresses matching "@[dropboxDomain]" are considered
    inside.
  */
  public function dropboxDomain() { return $this->_dropboxDomain; }
  public function setDropBoxDomain(
    $dropboxDomain
  )
  {
    if ( $dropboxDomain && $dropboxDomain != $this->_dropboxDomain ) {
      $this->_dropboxDomain = $dropboxDomain;
    }
  }
  
  /*!
    @function dropboxDirectory
    
    Accessor pair for getting/setting the directory where dropoffs are
    stored.  Always use a canonical path -- and, of course, be sure your
    web server is allowed to write to it!!
  */
  public function dropboxDirectory() { return $this->_dropboxDirectory; }
  public function setDropBoxDirectory(
    $dropboxDirectory
  )
  {
    if ( $dropboxDirectory && $dropboxDirectory != $this->_dropboxDirectory && is_dir($dropboxDirectory) ) {
      $this->_dropboxDirectory = $dropboxDirectory;
    }
  }
  
  /*!
    @function dropboxLog
    
    Accessor pair for getting/setting the path to the log file for this
    dropbox.  Make sure your web server has access privs on the file
    (or the enclosing directory, in which case the file will get created
    automatically the first time we log to it).
  */
  public function dropboxLog() { return $this->_dropboxLog; }
  public function setDropBoxLog(
    $dropboxLog
  )
  {
    if ( $dropboxLog && $dropboxLog != $this->_dropboxLog ) {
      $this->_dropboxLog = $dropboxLog;
    }
  }
  
  /*!
    @function retainDays
    
    Accessor pair for getting/setting the number of days that a dropoff
    is allowed to reside in the dropbox.  The "cleanup.php" admin script
    actually removes them, we don't do it from the web interface.
  */
  public function retainDays() { return $this->_retainDays; }
  public function setRetainDays(
    $retainDays
  )
  {
    if ( intval($retainDays) > 0 && intval($retainDays) != $this->_retainDays ) {
      $this->_retainDays = intval($retainDays);
    }
  }

  /*!
    @function maxBytesForFile
    
    Accessor pair for getting/setting the maximum size (in bytes) of a single
    file that is part of a dropoff.  Note that there is a PHP system parameter
    that you must be sure is set high-enough to accomodate what you select
    herein!
  */
  public function maxBytesForFile() { return $this->_maxBytesForFile; }
  public function setMaxBytesForFile(
    $maxBytesForFile
  )
  {
    if ( ($intValue = intval($maxBytesForFile)) > 0 ) {
      $this->_maxBytesForFile = $intValue;
    }
  }

  /*!
    @function maxBytesForDropoff
    
    Accessor pair for getting/setting the maximum size (in bytes) of a dropoff
    (all files summed).  Note that there is a PHP system parameter that you must
    be sure is set high-enough to accomodate what you select herein!
  */
  public function maxBytesForDropoff() { return $this->_maxBytesForDropoff; }
  public function setMaxBytesForDropoff(
    $maxBytesForDropoff
  )
  {
    if ( ($intValue = intval($maxBytesForDropoff)) > 0 ) {
      $this->_maxBytesForDropoff = $intValue;
    }
  }

  /*!
    @function authUserFormalDesc
    
    Accessor pair for getting/setting the formal (long) description of users
    that are properly authenticated and thus "inside" users.
  */
  public function authUserFormalDesc() { return $this->_authUserFormalDesc; }
  public function setAuthUserFormalDesc(
    $authUserFormalDesc
  )
  {
    if ( $authUserFormalDesc && $authUserFormalDesc != $this->_authUserFormalDesc ) {
      $this->_authUserFormalDesc = $authUserFormalDesc;
    }
  }

  /*!
    @function authUserShortDesc
    
    Accessor pair for getting/setting the informal (short) description of users
    that are properly authenticated and thus "inside" users.
  */
  public function authUserShortDesc() { return $this->_authUserShortDesc; }
  public function setAuthUserShortDesc(
    $authUserShortDesc
  )
  {
    if ( $authUserShortDesc && $authUserShortDesc != $this->_authUserShortDesc ) {
      $this->_authUserShortDesc = $authUserShortDesc;
    }
  }

  /*!
    @function secretForCookies
    
    Accessor pair for getting/setting the secret string that we include in the
    MD5 sum that gets sent off as our cookie values.
  */
  public function secretForCookies() { return $this->_secretForCookies; }
  public function setSecretForCookies(
    $secretForCookies
  )
  {
    if ( $secretForCookies && $secretForCookies != $this->_secretForCookies ) {
      $this->_secretForCookies = $secretForCookies;
    }
  }

  /*!
    @function secretForReferral
    
    Accessor pair for getting/setting the secret string that we include in the
    MD5 sum that gets sent off as our referral values.
  */
  public function secretForReferral() { return $this->_secretForReferral; }
  public function setSecretForReferral(
    $secretForReferral
  )
  {
    if ( $secretForReferral && $secretForReferral != $this->_secretForReferral ) {
      $this->_secretForReferral = $secretForReferral;
    }
  }

  /*!
    @function isNewDatabase
    
    Returns TRUE if the backing-database was newly-created by this instance.
  */
  public function isNewDatabase() { return $this->_newDatabase; }
  
  /*!
    @function database
    
    Returns a reference to the database object (class is SQLiteDatabase)
    backing this dropbox.
  */
  public function &database() { return $this->_database; }
  
  /*!
    @function authorizedUser
    
    If the instance was created and was able to associate with a valid user
    (either via cookie or explicit authentication) the username in question
    is returned.
  */
  public function authorizedUser() { return $this->_authorizedUser; }

  /*!
    @function authorizedUserData
    
    If the instance was created and was able to associate with a valid user
    (either via cookie or explicit authentication) then this function returns
    either the entire hash of user information (if $field is NULL) or a
    particular value from the hash of user information.  For example, you
    could grab the user's email address using:
    
      $userEmail = $aDropbox->authorizedUserData('mail');
      
    If the field you request does not exist, NULL is returned.  Note that
    as the origin of this data is probably an LDAP lookup, there _may_ be
    arrays involved if a given field has multiple values.
  */
  public function authorizedUserData(
    $field = NULL
  )
  {
    if ( $field ) {
      return $this->_authorizedUserData[$field];
    }
    return $this->_authorizedUserData;
  }
  
  public function showRecipsOnPickup() { return $this->_showRecipsOnPickup; }
  public function setShowRecipsOnPickup(
    $showIt
  )
  {
    $this->_showRecipsOnPickup = $showIt;
  }

  /*!
    @function directoryForDropoff
    
    If $claimID enters with a value already assigned, then this function attempts
    to find the on-disk directory which contains that dropoff's files; the directory
    is returned in the $claimDir variable-reference.
    
    If $claimID is NULL, then we're being requested to setup a new dropoff.  So we
    pick a new claim ID, make sure it doesn't exist, and then create the directory.
    The new claim ID goes back in $claimID and the directory goes back to the caller
    in $claimDir.
    
    Returns TRUE on success, FALSE on failure.
  */
  public function directoryForDropoff(
    &$claimID = NULL,
    &$claimDir = NULL
  )
  {
    if ( $claimID ) {
      if ( is_dir($this->_dropboxDirectory."/$claimID") ) {
        $claimDir = $this->_dropboxDirectory."/$claimID";
        return TRUE;
      }
    } else {
      while ( 1 ) {
        $claimID = NSSGenerateCode();
        //  Is it already in the database?
        $extant = $this->_database->arrayQuery("SELECT * FROM dropoff WHERE claimID = '".$claimID."'");
        if ( !$extant || (count($extant) == 0) ) {
          //  Make sure there's no directory hanging around:
          if ( ! file_exists($this->_dropboxDirectory."/$claimID") ) {
            if ( mkdir($this->_dropboxDirectory."/$claimID",0700) ) {
              $claimDir = $this->_dropboxDirectory."/$claimID";
              return TRUE;
            }
            $this->writeToLog("unable to create ".$this->_dropboxDirectory."/$claimID");
            break;
          }
        }
      }
    }
    return FALSE;
  }
  
  /*!
    @function authenticator
    
    Returns the authenticator object (subclass of NSSAuthenticator) that was created
    when we were initialized.
  */
  public function authenticator() { return $this->_authenticator; }
  
  /*!
    @function deliverEmail
    
    Send the $content of an email message to (one or more) address(es) in
    $toAddr.
  */
  public function deliverEmail(
    $toAddr,
    $subject,
    $content
  )
  {
    return mail(
              $toAddr,
              $subject,
              $content,
              sprintf("From: %s\r\nReply-to: %1\$s\r\n",
                $this->_emailSenderAddr
              )
            );
  }

  /*!
    @function writeToLog
    
    Write the $logText to the log file.  Each line is formatted to have a date
    and time, as well as the name of this dropbox.
  */
  public function writeToLog(
    $logText
  )
  {
    $logText = sprintf("%s [%s]: %s\n",strftime("%Y-%m-%d %T"),$this->_dropboxName,$logText);
    file_put_contents($this->_dropboxLog,$logText,FILE_APPEND | LOCK_EX);
  }
  
  /*!
    @function HTMLWriteHeader
    
    Start HTML content by writing out the document type and <HEAD> section.
    Does NOT terminate the <HEAD> block, so the caller can add <META>
    tags, etc.  The <HEAD> block is terminated by the HTMLStartBody()
    function.
  */
  public function HTMLWriteHeader()
  {
    global $NSSDROPBOX_URL;
?>
<!DOCTYPE html
	PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US" xml:lang="en-US">
  <head>
    <title><?=$this->_dropboxName?></title>
    <link rel="stylesheet" type="text/css" href="css/<?=( defined('NSSTHEME') ? NSSTHEME : 'default' )?>.css"/>
	
	<script src='AC_RunActiveContent.js' language='javascript'></script>
	<script charset='ISO-8859-1' src='rac.js' language='javascript'></script>
<?PHP

    if ( ! $_SERVER['HTTPS'] ) {
      printf("    <meta http-equiv=\"refresh\" content=\"0;URL=%s\">\n",
          preg_replace('/^http:/','https:',$NSSDROPBOX_URL)
        );
?>
	
  </head>
</html>
<?PHP
      exit(0);
    }
  }
  
  /*!
    @function HTMLStartBody
    
    End the <HEAD> section; write-out the standard stuff for the <BODY> --
    the page header with title, etc.  Upon exit, the caller should begin
    writing HTML content for the page.
    
    We also get a chance here to spit-out an error if the authentication
    of a user failed.
    
    The single argument gives the text field that we should throw focus
    to when the page loads.  You should pass the text field as
    "[form name].[field name]", which the function turns into
    "document.[form name].[field name].focus()".
  */
  public function HTMLStartBody(
    $focusTarget = NULL
  )
  {  
?>
  </head>
<?PHP
    if ( $focusTarget ) {
      ?><body onload="document.<?=$focusTarget?>.focus();"><?PHP
    } else {
      ?><body><?PHP
    }
?>
<table class="UD_header" width="100%">
  <tr valign="top">
    <td id="UD_header_left" rowspan="2">&nbsp;</td>
    <td id="UD_header_top" align="right">&nbsp;</td>
  </tr>
  <tr>
    <td id="UD_header_title"><?=$this->_dropboxName?>&nbsp;<img src="images/<?=( $this->_authorizedUser ? "locked" : "unlocked" )?>.png" alt="<?=( $this->_authorizedUser ? "locked" : "unlocked" )?>"/></td>
  </tr>
</table>

<!-- Begin page content -->
<div class="content">

<?PHP
    if ( $this->_authorizationFailed ) {
      NSSError("The username or password was incorrect.","Authentication Error");
    }
  }
  
  /*!
    @function HTMLWriteFooter
    
    Finishes-off the content area of the page, writes the footer HTML (including
    copyright notice, logged-in user name, etc), and ends the <BODY> of the page.
    This should be (more or less) the last thing you call when generating page
    content.
  */
  public function HTMLWriteFooter()
  {
    global $NSSDROPBOX_URL;
    
?>
</div>
<?PHP
    if ( ! preg_match('/^index\.php.*/',basename($_SERVER['PHP_SELF'])) ) {
      ?><table border="0" cellpadding="4"><tr><td><?PHP
      NSSTextButton("Return to the ".$this->_dropboxName." main menu.",$NSSDROPBOX_URL);
      ?></td><td><?PHP
      if ( $this->_authorizedUser ) {
        NSSTextButton("Logout",$NSSDROPBOX_URL."?action=logout");
      } else {
        echo "&nbsp;";
      }
      ?></td></tr></table><?PHP
    }
?>
<table width="100%" class="UD_footer">
  <tr valign="bottom">
    <td id="UD_footer_text">Version 2.1&nbsp;|&nbsp;<?PHP
    echo ( $this->_contactInfo ? $this->_contactInfo : "Copyright &copy 2006" );
    if ( $whoAmI = $this->authorizedUserData("cn") ) {
      printf("&nbsp;|&nbsp;you are currently logged in as <i>%s</i>",$whoAmI);
    }
    ?></td>
<?PHP
    if ( $this->_authorizedUserData['grantAdminPriv'] ) {
      ?><td id="UD_footer_right_admin" rowspan="2">&nbsp;</td><?PHP
    } else {
      ?><td id="UD_footer_right" rowspan="2">&nbsp;</td><?PHP
    }
?>
  </tr>
  <tr>
    <td id="UD_footer_bottom">&nbsp;</td>
  </tr>
</table>

  </body>
</html>
<?PHP
  }
  
  /*!
    @function HTMLMenu
    
    Writes the menu of user actions.  Internally, this function looks at whether or
    not there's an authenticated user associated with this instance and displays the
    appropriate menu accordingly.  Un-authenticated users get a very basic set of
    choices -- About, Dropoff, Pickup, Login.  Authenticated users get some buttons
    to see what they have available to Pickup, what they have Dropped-off.  Admin
    users get a button to view all of the resident dropoffs.
  */
  public function HTMLMenu()
  {
    if ( $this->_authorizedUser ) {
?>
<table border="0">
  <tr><td colspan="2"><h4>You may perform the following activities:</h4></td></tr>
  <tr>
    <td><?PHP NSSTextButton("About the Dropbox",$NSSDROPBOX_URL."about.php","100%"); ?></td>
    <td class="UD_nav_label">What <i>is</i> the dropbox?</td>
  </tr>
  <tr><td colspan="2">&nbsp;</td></tr>
  <tr>
    <td><?PHP NSSTextButton("Drop-off",$NSSDROPBOX_URL."dropoff.php","100%"); ?></td>
    <td class="UD_nav_label">Drop-off (<i>upload</i>) a file for a <?=$this->_authUserFormalDesc?> or other user.</td>
  </tr>
  <tr>
    <td><?PHP NSSTextButton("Pick-up",$NSSDROPBOX_URL."pickup.php","100%"); ?></td>
    <td class="UD_nav_label">Pick-up (<i>download</i>) a file that was dropped-off for you.</td>
  </tr>
  <tr><td colspan="2">&nbsp;</td></tr>
  <tr>
    <td><?PHP NSSTextButton("Drop-offs for Me",$NSSDROPBOX_URL."pickup_list.php","100%"); ?></td>
    <td class="UD_nav_label">Display a list of drop-offs that were sent to you.</td>
  </tr>
  <tr>
    <td><?PHP NSSTextButton("Drop-offs by Me",$NSSDROPBOX_URL."dropoff_list.php","100%"); ?></td>
    <td class="UD_nav_label">Display a list of drop-offs that you have created.</td>
  </tr>
<?PHP
      if ( $this->_authorizedUserData['grantAdminPriv'] ) {
?>
  <tr>
    <td><?PHP NSSTextButton("Show All Drop-offs",$NSSDROPBOX_URL."pickup_list_all.php","100%",TRUE); ?></td>
    <td class="UD_nav_label">View all drop-offs in the database (<i>Administrators only.</i>)</td>
  </tr>
  <tr>
    <td><?PHP NSSTextButton("System Statistics",$NSSDROPBOX_URL."stats.php","100%",TRUE); ?></td>
    <td class="UD_nav_label">View daily statistics for the dropbox (<i>Administrators only.</i>)</td>
  </tr>
<?PHP
      }
?>
  <tr><td colspan="2">&nbsp;</td></tr>
  <tr>
    <td><?PHP NSSTextButton("Logout",$NSSDROPBOX_URL."?action=logout","100%"); ?></td>
    <td class="UD_nav_label">Logout from the dropbox site.</td>
  </tr>
</table>
<br/>
<br/>
<?PHP
    } else {
?>
<table border="0">
  <tr><td colspan="2"><h4>You may perform the following activities:</h4></td></tr>
  <tr>
    <td><?PHP NSSTextButton("About the Dropbox",$NSSDROPBOX_URL."about.php","100%"); ?></td>
    <td class="UD_nav_label">What <i>is</i> the dropbox?</td>
  </tr>
  <tr><td colspan="2">&nbsp;</td></tr>
  <tr>
  <!-- Drop functionality only available for UoR people
    <td><?PHP NSSTextButton("Drop-off",$NSSDROPBOX_URL."dropoff.php","100%"); ?></td>
    <td class="UD_nav_label">Drop-off (<i>upload</i>) a file for a <?=$this->_authUserFormalDesc?> user.</td>
  </tr>
  -->
  <tr>
    <td><?PHP NSSTextButton("Pick-up",$NSSDROPBOX_URL."pickup.php","100%"); ?></td>
    <td class="UD_nav_label">Pick-up (<i>download</i>) a file dropped-off for you by a <?=$this->_authUserFormalDesc?> user.</td>
  </tr>
  <tr><td colspan="2"><h4>If you are a <?=$this->_authUserFormalDesc?> user, you may also perform the following activities:</h4></td></tr>
  <tr>
    <td><?PHP NSSTextButton("Login",$NSSDROPBOX_URL."?action=login","100%"); ?></td>
    <td class="UD_nav_label">Use your <?=LookupInDict('username')?> to log in and access features not available to the public.</td>
  </tr>
</table>
<br/>
<br/>
<?PHP
    }
  }

  /*!
    @function setupDatabase
    
    SQL statements to create the tables we'll be needing.
  */
  private function setupDatabase()
  {
    if ( $this->_database ) {
    
      if ( ! $this->_database->queryExec(
"CREATE TABLE dropoff (
  claimID             character varying(16) not null,
  claimPasscode       character varying(16),
  
  authorizedUser      character varying(16),
  
  senderName          character varying(32) not null,
  senderOrganization  character varying(32),
  senderEmail         text not null,
  senderIP            character varying(15) not null,
  confirmDelivery     boolean default FALSE,
  created             timestamp with time zone not null
);",$errorMsg) ) {
        NSSError($errorMsg,"Database Error");
        return FALSE;
      }
    
      if ( ! $this->_database->queryExec(
"CREATE TABLE recipient (
  dID                 integer not null,
  
  recipName           character varying(32) not null,
  recipEmail          text not null
);",$errorMsg) ) {
        NSSError($errorMsg,"Database Error");
        return FALSE;
      }
    
      if ( ! $this->_database->queryExec(
"CREATE TABLE file (
  dID                 integer not null,
  
  tmpname             text not null,
  basename            text not null,
  lengthInBytes       bigint not null,
  mimeType            character varying(32) not null,
  description         text
);",$errorMsg) ) {
        NSSError($errorMsg,"Database Error");
        return FALSE;
      }
    
      if ( ! $this->_database->queryExec(
"CREATE TABLE pickup (
  dID                 integer not null,
  
  authorizedUser      character varying(16),
  emailAddr           text,
  recipientIP         character varying(15) not null,
  pickupTimestamp     timestamp with time zone not null
);",$errorMsg) ) {
        NSSError($errorMsg,"Database Error");
        return FALSE;
      }
      
      //  Do the indexes now:
    
      if ( ! $this->_database->queryExec(
"CREATE INDEX dropoff_claimID_index ON dropoff(claimID);",$errorMsg) ) {
        NSSError($errorMsg,"Database Error");
        return FALSE;
      }
    
      if ( ! $this->_database->queryExec(
"CREATE INDEX recipient_dID_index ON recipient(dID);",$errorMsg) ) {
        NSSError($errorMsg,"Database Error");
        return FALSE;
      }
    
      if ( ! $this->_database->queryExec(
"CREATE INDEX file_dID_index ON file(dID);",$errorMsg) ) {
        NSSError($errorMsg,"Database Error");
        return FALSE;
      }
    
      if ( ! $this->_database->queryExec(
"CREATE INDEX pickup_dID_index ON pickup(dID);",$errorMsg) ) {
        NSSError($errorMsg,"Database Error");
        return FALSE;
      }
      
      $this->writeToLog("initial setup of database complete");
      
      return TRUE;
    }
    return FALSE;
  }
  
  /*!
    @function cookieForSession
    
    Returns an appropriate cookie for the current session.  An initial key is
    constructed using the username, remote IP, current time, a random value,
    the user's browser agent tag, and our special cookie secret.  This key is
    hashed, and included as part of the actual cookie.  The cookie contains
    more or less all but the secret value, so that the initial key and its
    hash can later be reconstructed for authenticity's sake.
  */
  private function cookieForSession()
  {
    $now = time();
    $nonce = mt_rand();
    $digestString = sprintf("%s %s %d %d %s %s %s",
                        $this->_authorizedUser,
                        $_SERVER['REMOTE_ADDR'],
                        $now,
                        $nonce,
                        $_SERVER['HTTP_USER_AGENT'],
                        $this->_cookieName,
                        $this->_secretForCookies
                      );
    return sprintf("%s,%s,%d,%d,%s",
                        $this->_authorizedUser,
                        $_SERVER['REMOTE_ADDR'],
                        $now,
                        $nonce,
                        md5($digestString)
                      );
  }
  
  /*!
    @function userFromCookie
    
    Attempt to parse our cookie (if it exists) and establish the current user's
    username.
  */
  private function userFromCookie()
  {
    if ( $cookieVal = $_COOKIE[$this->_cookieName] ) {
      if ( preg_match('/^(.+)\,([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+),([0-9]+),([0-9]+),([A-Fa-f0-9]+)$/',$cookieVal,$cookiePieces) ) {
        //  Coming from the same remote IP?
        if ( $cookiePieces[2] != $_SERVER['REMOTE_ADDR'] ) {
          return FALSE;
        }
        
        //  How old is the internal timestamp?
        if ( time() - $cookiePieces[3] > $this->_cookieTTL ) {
          return FALSE;
        }
        
        //  Verify the MD5 checksum.  This implies that everything
        //  (including the HTTP agent) is unchanged.
        $digestString = sprintf("%s %s %d %d %s %s %s",
                            $cookiePieces[1],
                            $cookiePieces[2],
                            $cookiePieces[3],
                            $cookiePieces[4],
                            $_SERVER['HTTP_USER_AGENT'],
                            $this->_cookieName,
                            $this->_secretForCookies
                          );
        if ( md5($digestString) != $cookiePieces[5] ) {
          return FALSE;
        }
        
        //  Success!  Verify the username as valid:
        if ( $this->_authenticator->validUsername($cookiePieces[1],$this->_authorizedUserData) ) {
          $this->_authorizedUser = $cookiePieces[1];
          return TRUE;
        }
      }
    }
    return FALSE;
  }
  
  /*!
    @function userFromAuthentication
    
    Presumes that a username and password have come in POST'ed form
    data.  We need to do an LDAP bind to verify the user's identity.
  */
  private function userFromAuthentication()
  {
    $result = FALSE;
    
    if ( ($usernameRegex = LookupInDict('username-regex')) == NULL ) {
      $usernameRegex = '/^([a-z0-9][a-z0-9\_\.]*)$/';
    }
    
    if ( $this->_authenticator && preg_match($usernameRegex,$_POST['uname'],$detaintUser) && $_POST['password'] ) {
      $password = stripslashes($_POST['password']);
      if ( $result = $this->_authenticator->authenticate($detaintUser[1],$password,$this->_authorizedUserData) ) {
        $this->_authorizedUser = $detaintUser[1];
      } else {
        $this->_authorizationFailed = TRUE;
        $this->writeToLog("authorization failed for ".$detaintUser[1]);
      }
    }
    return $result;
  }
  
  /*!
    @function checkPrefs
    
    Examines a preference hash to be sure that all of the required parameters
    are extant.
  */
  private function checkPrefs(
    $prefs
  )
  {
    static $requiredKeys = array(
              'dropboxName',
              'dropboxDirectory',
              'authUserFormalDesc',
              'authUserShortDesc',
              'logFilePath',
              'cookieName',
              'authenticator'
            );
    foreach ( $requiredKeys as $key ) {
      if ( !$prefs[$key] || ($prefs[$key] == "") ) {
        NSSError("You must provide a value for the following preference key: '$key'","Undefined Preference Key");
        return FALSE;
      }
    }
    return TRUE;
  }
  
}

?>
