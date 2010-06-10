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
// $Id: NSSDropoff.php 54 2008-07-09 16:08:50Z frey $
//

include_once(NSSDROPBOX_LIB_DIR."NSSDropbox.php");
include_once(NSSDROPBOX_LIB_DIR."NSSUtils.php");
include_once(NSSDROPBOX_LIB_DIR."Timestamp.php");

/*!
  @class NSSDropoff
  
  Wraps an item that's been dropped-off.  There are two methods of
  allocation available.  The primary is using the dropoff ID,
  for which the database will be queried and used to initialize
  the instance.  The second includes no ID, and in this instance
  the $_FILES array will be examined -- if any files were uploaded
  then the dropoff is initialized using $_FILES and $_POST data.
  
  Dropoffs have evolved a bit since the previous version of this
  service.  Each dropoff can now have multiple files associated
  with it, eliminating the need for the end-user to archive
  multiple files for dropoff.  Dropoffs are now created as a
  one-to-many relationship, where the previous version was setup to
  be a one-to-one deal only.
  
  Of course, we're also leveraging the power of SQL to maintain
  the behind-the-scenes data for each dropoff.
*/
class NSSDropoff {

  //  Instance data:
  private $_dropbox = NULL;
  
  private $_dropoffID = -1;
  private $_claimID;
  private $_claimPasscode;
  private $_claimDir;
  
  private $_authorizedUser;
  private $_emailAddr;
  
  private $_senderName;
  private $_senderOrganization;
  private $_senderEmail;
  private $_senderIP;
  private $_confirmDelivery;
  private $_created;
  
  private $_recipients;
  
  private $_showPasscodeHTML = TRUE;
  private $_cameFromEmail = FALSE;
  private $_invalidClaimID = FALSE;
  private $_invalidClaimPasscode = FALSE;
  private $_isNewDropoff = FALSE;
  private $_formInitError = NULL;
  private $_okayForDownloads = FALSE;
  
  /*!
    @function dropoffsForCurrentUser
    
    Static function that returns an array of all dropoffs (as
    NSSDropoff instances) that include the currently-authenticated
    user in their recipient list.  
  */
  public static function dropoffsForCurrentUser(
    $aDropbox
  )
  {
    $allDropoffs = NULL;
    
    if ( $targetEmail = strtolower($aDropbox->authorizedUserData('mail')) ) {
      $qResult = $aDropbox->database()->arrayQuery(
                    "SELECT d.rowID,d.* FROM dropoff d,recipient r WHERE d.rowID = r.dID AND r.recipEmail = '$targetEmail' ORDER BY d.created",
                    SQLITE_ASSOC
                  );
      if ( $qResult && ($iMax = count($qResult)) ) {
        //  Allocate all of the wrappers:
        $i = 0;
        while ( $i < $iMax ) {
          $params = $qResult[$i];
          $altParams = array();
          foreach ( $params as $key => $value ) {
            $altParams[preg_replace('/^d\./','',$key)] = $value;
          }
          if ( $nextDropoff = new NSSDropoff($aDropbox,$altParams) ) {
            $allDropoffs[] = $nextDropoff;
          }
          $i++;
        }
      }
    }
    return $allDropoffs;
  }
  
  /*!
    @function dropoffsFromCurrentUser
    
    Static function that returns an array of all dropoffs (as
    NSSDropoff instances) that were created by the currently-
    authenticated user.  Matches are made based on the
    user's username OR the user's email address -- that catches
    authenticated as well as anonymouse dropoffs by the user.
  */
  public static function dropoffsFromCurrentUser(
    $aDropbox
  )
  {
    $allDropoffs = NULL;
    
    if ( $authSender = $aDropbox->authorizedUser() ) {
      $targetEmail = strtolower($aDropbox->authorizedUserData('mail'));
      
      $qResult = $aDropbox->database()->arrayQuery(
                    sprintf("SELECT rowID,* FROM dropoff WHERE authorizedUser = '$authSender' %s ORDER BY created",
                        ( $targetEmail ? "OR senderEmail = '$targetEmail'" : "")
                    ),
                    SQLITE_ASSOC
                  );
      if ( $qResult && ($iMax = count($qResult)) ) {
        //  Allocate all of the wrappers:
        $i = 0;
        while ( $i < $iMax ) {
          if ( $nextDropoff = new NSSDropoff($aDropbox,$qResult[$i]) ) {
            $allDropoffs[] = $nextDropoff;
          }
          $i++;
        }
      }
    }
    return $allDropoffs;
  }

  /*!
    @function dropoffsOutsideRetentionTime
    
    Static function that returns an array of all dropoffs (as
    NSSDropoff instances) that are older than the dropbox's
    retention time.  Subsequently, they should be removed --
    see the "cleanup.php" admin script.
  */
  public static function dropoffsOutsideRetentionTime(
    $aDropbox
  )
  {
    $allDropoffs = NULL;
    
    $targetDate = timestampForTime( time() - $aDropbox->retainDays() * 24 * 60 * 60 );
    
    $qResult = $aDropbox->database()->arrayQuery(
                    "SELECT rowID,* FROM dropoff WHERE created < '$targetDate' ORDER BY created",
                    SQLITE_ASSOC
                  );
    if ( $qResult && ($iMax = count($qResult)) ) {
      //  Allocate all of the wrappers:
      $i = 0;
      while ( $i < $iMax ) {
        if ( $nextDropoff = new NSSDropoff($aDropbox,$qResult[$i]) ) {
          $allDropoffs[] = $nextDropoff;
        }
        $i++;
      }
    }
    return $allDropoffs;
  }

  /*!
    @function dropoffsCreatedToday
    
    Static function that returns an array of all dropoffs (as
    NSSDropoff instances) that were made in the last 24 hours.
  */
  public static function dropoffsCreatedToday(
    $aDropbox
  )
  {
    $allDropoffs = NULL;
    
    $targetDate = timestampForTime( time() - 24 * 60 * 60 );
    
    $qResult = $aDropbox->database()->arrayQuery(
                    "SELECT rowID,* FROM dropoff WHERE created >= '$targetDate' ORDER BY created",
                    SQLITE_ASSOC
                  );
    if ( $qResult && ($iMax = count($qResult)) ) {
      //  Allocate all of the wrappers:
      $i = 0;
      while ( $i < $iMax ) {
        if ( $nextDropoff = new NSSDropoff($aDropbox,$qResult[$i]) ) {
          $allDropoffs[] = $nextDropoff;
        }
        $i++;
      }
    }
    return $allDropoffs;
  }

  /*!
    @function allDropoffs
    
    Static function that returns an array of every single dropoff (as
    NSSDropoff instances) that exist in the database.
  */
  public static function allDropoffs(
    $aDropbox
  )
  {
    $allDropoffs = NULL;
    
    $qResult = $aDropbox->database()->arrayQuery(
                    "SELECT rowID,* FROM dropoff ORDER BY created",
                    SQLITE_ASSOC
                  );
    if ( $qResult && ($iMax = count($qResult)) ) {
      //  Allocate all of the wrappers:
      $i = 0;
      while ( $i < $iMax ) {
        if ( $nextDropoff = new NSSDropoff($aDropbox,$qResult[$i]) ) {
          $allDropoffs[] = $nextDropoff;
        }
        $i++;
      }
    }
    return $allDropoffs;
  }

  /*!
    @function cleanupOrphans
    
    Static function that looks for orphans:  directories in the dropoff
    directory that have no matching record in the database AND records in
    the database that have no on-disk directory anymore.  Scrubs both
    types of orphans.  This function gets called from the "cleanup.php"
    script after purging "old" dropoffs.
  */
  public static function cleanupOrphans(
    $aDropbox
  )
  {
    $qResult = $aDropbox->database()->arrayQuery(
                      "SELECT rowID,* FROM dropoff",
                      SQLITE_ASSOC
                    );
    $scrubCount = 0;
    if ( $qResult && ($iMax = count($qResult)) ) {
      //
      //  Build a list of claim IDs and walk the dropoff directory
      //  to remove any directories that aren't in the database:
      //
      $dropoffDir = $aDropbox->dropboxDirectory();
      if ( $dirRes = opendir($dropoffDir) ) {
        $i = 0;
        $validClaimIDs = array();
        while ( $i < $iMax ) {
          $nextClaim = $qResult[$i]['claimID'];
          
          //  If there's no directory, then we should scrub this entry
          //  from the database:
          if ( !is_dir($dropoffDir."/".$nextClaim) ) {
            if ( $aDropoff = new NSSDropoff($aDropbox,$qResult[$i]) ) {
              $aDropoff->removeDropoff(FALSE);
              echo "- Removed orphaned record:             $nextClaim\n";
            } else {
              echo "- Unable to remove orphaned record:    $nextClaim\n";
            }
            $scrubCount++;
          } else {
            $validClaimIDs[] = $nextClaim;
          }
          $i++;
        }
        while ( $nextDir = readdir($dirRes) ) {
          //  Each item is a NAME, not a PATH.  Test whether it's a directory
          //  and no longer in the database:
          if ( ( $nextDir != '.' && $nextDir != '..' ) && is_dir($dropoffDir."/".$nextDir) && !in_array($nextDir,$validClaimIDs) ) {
            if ( rmdir_r($dropoffDir."/".$nextDir) ) {
              echo "- Removed orphaned directory:          $nextDir\n";
            } else {
              echo "- Unable to remove orphaned directory: $nextDir\n";
            }
            $scrubCount++;
          }
        }
        closedir($dirRes);
      }
    }
    if ( $scrubCount ) {
      printf("%d orphan%s removed.\n\n",$scrubCount,($scrubCount == 1 ? "" : "s"));
    } else {
      echo "No orphans found.\n\n";
    }
  }

  /*!
    @function __construct
    
    Object constructor.  First of all, if we were passed a query result hash
    in $qResult, then initialize the instance using data from the SQL query.
    Otherwise, we need to look at the disposition of the incoming form data:
    
    * The only GET-type form we do comes from the email notifications we
      send to notify recipients.  So the presence of claimID (and possibly
      claimPasscode) in $_GET means we can init as though the user were
      making a pickup.
    
    * If there a POST-type form and a claimID exists in $_POST, then
      try to initialize using that claimID.
    
    * Otherwise, we need to see if the POST-type form data has an action
      of "dropoff" -- if it does, then attempt to create a ~new~ dropoff
      with $_FILES and $_POST.
    
    A _lot_ of state stuff going on in here; might be ripe for simplification
    in the future.
  */
  public function __construct(
    $aDropbox,
    $qResult = FALSE
  )
  {
    $this->_dropbox = $aDropbox;
    
    if ( ! $qResult ) {
      if ( $_POST['claimID'] ) {
        //  Coming from a web form:
        if ( ! $this->initWithClaimID($_POST['claimID']) ) {
          $this->_invalidClaimID = TRUE;
        } else {
          $this->_showPasscodeHTML = FALSE;
        }
      } else if ( $_GET['claimID'] ) {
        //  Coming from an email:
        $this->_cameFromEmail = TRUE;
        if ( ! $this->initWithClaimID($_GET['claimID']) ) {
          $this->_invalidClaimID = TRUE;
        }
      } else if ( $_POST['Action'] == "dropoff" ) {
        $this->_isNewDropoff = TRUE;
        $this->_showPasscodeHTML = FALSE;
        //  Try to create a new one from form data:
        $this->_formInitError = $this->initWithFormData();
      }
      
      //  If we got a dropoff ID, check the passcode now:
      if ( ! $this->_isNewDropoff && $this->_dropoffID > 0 ) {
        //  Several ways to "authorize" this:
        //
        //    1) if the target user is the currently-logged-in user
        //    2) if the sender is the currently-logged-in user
        //    3) if the incoming form data has the valid passcode
        //
        $curUser = $this->_dropbox->authorizedUser();
        $curUserEmail = $this->_dropbox->authorizedUserData("mail");
        if ( $this->validRecipientEmail($curUserEmail) || ($curUser && ($curUser == $this->_authorizedUser)) || ($curUserEmail && ($curUserEmail == $this->_senderEmail)) ) {
          $this->_showPasscodeHTML = FALSE;
          $this->_okayForDownloads = TRUE;
        } else if ( $this->_cameFromEmail ) {
          if ( $_GET['claimPasscode'] != $this->_claimPasscode ) {
            $this->_showPasscodeHTML = TRUE;
          } else {
            $this->_showPasscodeHTML = FALSE;
            $this->_okayForDownloads = TRUE;
          }
        } else {
          if ( !$this->_dropbox->authorizedUserData('grantAdminPriv') && ($_POST['claimPasscode'] != $this->_claimPasscode) ) {
            $this->_invalidClaimPasscode = TRUE;
            $this->_showPasscodeHTML = TRUE;
          } else {
            $this->_okayForDownloads = TRUE;
          }
        }
      }
    } else {
      $this->initWithQueryResult($qResult);
    }
  }

  /*
    These are all accessors to get the value of all of the dropoff
    parameters.  Note that there are no functions to set these
    parameters' values:  an instance is immutable once it's created!
    
    I won't document each one of them because the names are
    strategically descriptive *grin*
  */
  public function dropbox() { return $this->_dropbox; }
  public function dropoffID() { return $this->_dropoffID; }
  public function claimID() { return $this->_claimID; }
  public function claimPasscode() { return $this->_claimPasscode; }
  public function claimDir() { return $this->_claimDir; }
  public function authorizedUser() { return $this->_authorizedUser; }
  public function senderName() { return $this->_senderName; }
  public function senderOrganization() { return $this->_senderOrganization; }
  public function senderEmail() { return $this->_senderEmail; }
  public function senderIP() { return $this->_senderIP; }
  public function confirmDelivery() { return $this->_confirmDelivery; }
  public function created() { return $this->_created; }
  public function recipients() { return $this->_recipients; }
  public function formInitError() { return $this->_formInitError; }
  
  /*!
    @function validRecipientEmail
    
    Returns TRUE is the incoming $recipEmail address is a member of the
    recipient list for this dropoff.  Returns FALSE otherwise.
  */
  public function validRecipientEmail(
    $recipEmail
  )
  {
    foreach ( $this->_recipients as $recipient ) {
      if ( strcasecmp($recipient[1],$recipEmail) == 0 ) {
        return TRUE;
      }
    }
    return FALSE;
  }
  
  /*!
    @function files
    
    Returns a hash array containing info for all of the files in
    the dropoff.
  */
  public function files()
  {
    $query = sprintf("SELECT rowID,* FROM file WHERE dID = %d ORDER by basename",$this->_dropoffID);
    if ( ($dropoffFiles = $this->_dropbox->database()->arrayQuery($query,SQLITE_ASSOC)) && (($iMax = count($dropoffFiles)) > 0) ) {
      $fileInfo = array();
      
      $totalBytes = 0.0;
      $i = 0;
      
      while ( $i < $iMax ) {
        $totalBytes += floatval($dropoffFiles[$i++]['lengthInBytes']);
      }
      $dropoffFiles['totalFiles'] = $iMax;
      $dropoffFiles['totalBytes'] = $totalBytes;
      return $dropoffFiles;
    }
    return NULL;
  }

  /*!
    @function addFileWithContent
    
    Add another file to this dropoff's payload, using the provided content,
    filename, and MIME type.
  */
  public function addFileWithContent(
    $content,
    $filename,
    $description,
    $mimeType = 'application/octet-stream'
  )
  {
    if ( ($contentLen = strlen($content)) && strlen($filename) && $this->_dropoffID ) {
      if ( strlen($mimeType) < 1 ) {
        $mimeType = 'application/octet-stream';
      }
      if ( $this->_claimDir ) {
        $tmpname = tempnam($this->_claimDir,'aff_');
        if ( $fptr = fopen($tmpname,'w') ) {
          fwrite($fptr,$content,$contentLen);
          fclose($fptr);
          
          //  Add to database:
          if ( ! $this->_dropbox->database()->queryExec("BEGIN") ) {
            $this->_dropbox->writeToLog("failed to BEGIN transaction block while adding $filename to dropoff $claimID");
            return false;
          }
          $query = sprintf("INSERT INTO file
  (dID,tmpname,basename,lengthInBytes,mimeType,description)
  VALUES
  (%d,'%s','%s',%.0f,'%s','%s')",
                  $this->_dropoffID,
                  sqlite_escape_string(basename($tmpname)),
                  sqlite_escape_string(stripslashes($filename)),
                  $contentLen,
                  sqlite_escape_string($mimeType),
                  sqlite_escape_string(stripslashes($description))
                );
          if ( ! $this->_dropbox->database()->queryExec($query) ) {
            //  Exit gracefully -- dump database changes and remove the dropoff
            //  directory:
            $this->_dropbox->writeToLog("error while adding $filename to dropoff $claimID");
            if ( ! $this->_dropbox->database()->queryExec("ROLLBACK") ) {
              $this->_dropbox->writeToLog("failed to ROLLBACK after botched addition of $filename to dropoff $claimID");
            }
            unlink($tmpname);
            return false;
          }
          return $this->_dropbox->database()->queryExec('COMMIT');
        }
      }
    }
    return false;
  }

  /*!
    @function downloadFile
    
    Given a fileID -- which is simply a rowID from the "file" table in
    the database -- attempt to download that file.  Download requires that
    NO HTTP headers have been transmitted yet, so we have to be very
    careful to call this function BEFORE the PHP has generated ANY output.
    
    We do quite a bit of logging here:
    
    * Log the pickup to the database; this gives the authorized sender
      the ability to examine who made a pick-up, from when and where.
    
    * Log the pickup to the log file -- UID for auth users, 'emailAddr'
      possibly coming in from a form, or anonymously; claim ID; file
      name.
    
    If all goes well, then the user gets a file and we return TRUE.
    Otherwise, and FALSE is returned.
  */
  public function downloadFile(
    $fileID
  )
  {$this->_dropbox->writeToLog("into downloadFile");
    //  First, make sure we've been properly authenticated:
    if ( $this->_okayForDownloads ) {
      //  Do we have such a file on-record?
      $fileList = $this->_dropbox->database()->arrayQuery(
                      sprintf("SELECT * FROM file WHERE dID = %d AND rowID = %d",
                          $this->_dropoffID,
                          $fileID
                        ),
                      SQLITE_ASSOC
                    );
      if ( $fileList && count($fileList) ) {
        @ob_end_clean(); //turn off output buffering to decrease cpu usage
        header("Content-type: ".$fileList[0]['mimeType']);
        header(sprintf('Content-Disposition: attachment; filename="%s"',
            $fileList[0]['basename'])
          );
        header('Content-Transfer-Encoding: binary');

        //  Range-based support stuff:
        header('Last-Modified: ' . substr(gmdate('r', filemtime($this->_claimDir."/".$fileList[0]['tmpname'])), 0, -5) . 'GMT');
        header('ETag: ' . $this->_dropoffID . $fileList[0]['tmpname']);
        header('Accept-Ranges: bytes');
        
        //  No caching, please:
        header('Cache-control: private');
        header('Pragma: private');
        header('Expires: 0');

        $fullSize = $fileList[0]['lengthInBytes'];

        //  Multi-thread and resumed downloading should be supported by this next
        //  block:
        if ( isset($_SERVER['HTTP_RANGE']) ) {
          if ( preg_match('/^[Bb][Yy][Tt][Ee][Ss]=([0-9]*)-([0-9]*)$/',$_SERVER['HTTP_RANGE'],$rangePieces) ) {
            if ( is_numeric($rangePieces[1]) && ($offset = intval($rangePieces[1])) ) {
              if ( ($offset >= 0) && ($offset < $fullSize) ) {
                //  Are we doing an honest-to-god range, or a start-to-end range:
                if ( is_numeric($rangePieces[2]) && ($endOfRange = intval($rangePieces[2])) ) {
                  if ( $endOfRange >= 0 ) {
                    if ( $endOfRange >= $fullSize ) {
                      $endOfRange = $fullSize - 1;
                    }
                    if ( $endOfRange >= $offset ) {
                      $length = $endOfRange - $offset + 1;
                    } else {
                      $offset = 0; $length = $fullSize;
                    }
                  } else {
                    $offset = 0; $length = $fullSize;
                  }
                } else {
                  //  start-to-end range:
                  $length = $fullSize - $offset;
                }
              } else {
                $offset = 0; $length = $fullSize;
              }
            } else if ( is_numeric($rangePieces[2]) && ($length = intval($rangePieces[2])) ) {
              //  The last $rangePieces[2] bytes of the file:
              $offset = $fullSize - $length;
              if ( $offset < 0 ) {
                $offset = 0; $length = $fullSize;
              }
            } else {
              $offset = 0;
              $length = $fullSize;
            }
          } else {
            $offset = 0;
            $length = $fullSize;
          }
        } else {
          $offset = 0;
          $length = $fullSize;
        }
        if ( ($offset > 0) && ($length < $fullSize) ) {
          header("HTTP/1.1 206 Partial Content");
          $this->_dropbox->writeToLog(sprintf('Partial download of %d bytes, range %d - %d / %d (%s)',
              $length,
              $offset,
              $offset + $length - 1,
              $fullSize,
              $_SERVER['HTTP_RANGE']
            )
          );
        }
        header(sprintf('Content-Range: bytes %d-%d/%d',$offset,$offset + $length - 1,$fullSize));
        header('Content-Length: '.$length);

        //  Open the file:
        $fptr = fopen($this->_claimDir."/".$fileList[0]['tmpname'],'rb');
        fseek($fptr,$offset);
        while ( ! feof($fptr) && ! connection_aborted() ) {
          set_time_limit(0);
          print( fread($fptr,8 * 1024) );
          flush();
          ob_flush();
        }
        fclose($fptr);

        //  Who made the pick-up?
        $whoWasIt = $this->_dropbox->authorizedUser();
        if ( ! $whoWasIt ) {
          $whoWasIt = ( $_POST['emailAddr'] ? $_POST['emailAddr'] : "One of the recipients");
        } else {
          $whoWasItUID = $whoWasIt;
          $whoWasIt = $this->_dropbox->authorizedUserData('cn');
        }

        //  Only send emails, etc, if the transfer didn't end with an aborted
        //  connection:
        if ( connection_aborted() ) {
          $this->_dropbox->writeToLog(sprintf('%s :: %s | %s [ABORTED]',
                ( $whoWasItUID ? $whoWasItUID : $whoWasIt ),
                $this->_claimID,
                $fileList[0]['basename']
              )
            );
        } else {
          //  Have any pick-ups been made already?
          $extantPickups = $this->_dropbox->database()->arrayQuery(sprintf("SELECT count(*) FROM pickup WHERE dID = %d",$this->_dropoffID));
           
          if ( $this->_confirmDelivery && (! $extantPickups || ($extantPickups[0][0] == 0)) ) {
            $this->_dropbox->writeToLog("sending confirmation email to ".$this->_senderEmail." for claim ".$this->_claimID);
            $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
            $emailContent = sprintf("
This is an automated message sent to you by the %s.

The drop-off you made (claim ID: %s) has been picked-up.  %s made the pick-up from %s.
      
      ",
                $this->_dropbox->dropboxName(),
                $this->_claimID,
                $whoWasIt,
                ( $hostname == $_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : "$hostname (".$_SERVER['REMOTE_ADDR'].")" )
              );
            if ( ! $this->_dropbox->deliverEmail(
                    $this->_senderEmail,
                    sprintf("[%s] %s has picked-up your drop-off!",
                          $this->_dropbox->dropboxName(),
                          $whoWasIt
                        ),
                    $emailContent
                 )
            ) {
              $this->_dropbox->writeToLog("error while sending confirmation email for claim ".$this-_claimID);
            }
          } else {
            $this->_dropbox->writeToLog("no need to send confirmation email for claim ".$this->_claimID);
          }
          $this->_dropbox->writeToLog(sprintf("%s :: %s | %s",
                  ( $whoWasItUID ? $whoWasItUID : $whoWasIt ),
                  $this->_claimID,
                  $fileList[0]['basename'])
            );
          
          //  Add to the pickup log:
          $query = sprintf("INSERT INTO pickup (dID,authorizedUser,emailAddr,recipientIP,pickupTimestamp) VALUES (%d,'%s','%s','%s','%s')",
                      $this->_dropoffID,
                      sqlite_escape_string($this->_dropbox->authorizedUser()),
                      sqlite_escape_string($_POST['emailAddr']),
                      sqlite_escape_string($_SERVER['REMOTE_ADDR']),
                      sqlite_escape_string(timestampForTime(time()))
                    );
          if ( ! $this->_dropbox->database()->queryExec($query) ) {
            $this->_dropbox->writeToLog("unabled to add pickup record for claimID ".$this->_claimID);
          }
        }
        return TRUE;
      }
    }
    return FALSE;
  }

  /*!
    @function removeDropoff
    
    Scrub the database and on-disk directory for this dropoff, effectively
    removing it.  We do some writing to the log file to make sure we know
    when this happens.
  */
  public function removeDropoff(
    $doLogEntries = TRUE
  )
  {
    if ( is_dir($this->_claimDir) ) {
      //  Remove the contents of the directory:
      if ( ! rmdir_r($this->_claimDir) ) {
        if ( $doLogEntries ) {
          $this->_dropbox->writeToLog("could not remove drop-off directory ".$this->_claimDir);
        }
        return FALSE;
      }
      
      //  Remove any stuff from the database:
      if ( $this->_dropbox->database()->queryExec("BEGIN") ) {
        $query = sprintf("DELETE FROM pickup WHERE dID = %d",$this->_dropoffID);
        if ( ! $this->_dropbox->database()->queryExec($query) ) {
          if ( $doLogEntries ) {
            $this->_dropbox->writeToLog("error in '$query'");
          }
          $this->_dropbox->database()->queryExec("ROLLBACK");
          return FALSE;
        }
        
        $query = sprintf("DELETE FROM file WHERE dID = %d",$this->_dropoffID);
        if ( ! $this->_dropbox->database()->queryExec($query) ) {
          if ( $doLogEntries ) {
            $this->_dropbox->writeToLog("error in '$query'");
          }
          $this->_dropbox->database()->queryExec("ROLLBACK");
          return FALSE;
        }
        
        $query = sprintf("DELETE FROM recipient WHERE dID = %d",$this->_dropoffID);
        if ( ! $this->_dropbox->database()->queryExec($query) ) {
          if ( $doLogEntries ) {
            $this->_dropbox->writeToLog("error in '$query'");
          }
          $this->_dropbox->database()->queryExec("ROLLBACK");
          return FALSE;
        }
        
        $query = sprintf("DELETE FROM dropoff WHERE claimID = '%s'",$this->_claimID);
        if ( ! $this->_dropbox->database()->queryExec($query) ) {
          if ( $doLogEntries ) {
            $this->_dropbox->writeToLog("error in '$query'");
          }
          $this->_dropbox->database()->queryExec("ROLLBACK");
          return FALSE;
        }
        
        if ( ! $this->_dropbox->database()->queryExec("COMMIT") ) {
          if ( $doLogEntries ) {
            $this->_dropbox->writeToLog("error trying to COMMIT removal of claimID ".$this->_claimID);
          }
          $this->_dropbox->database()->queryExec("ROLLBACK");
          return FALSE;
        }
        
        if ( $doLogEntries ) {
          $this->_dropbox->writeToLog("drop-off with claimID ".$this->_claimID." removed");
        }
        return TRUE;
      }
    }
    return FALSE;
  }

  /*!
    @function HTMLOnLoadJavascript
    
    Returns the "[form name].[field name]" string that's most appropriate for the page
    that's going to display this object.  Basically allows us to give focus to the
    claim ID or passcode field according to what data we have so far.
  */
  public function HTMLOnLoadJavascript()
  {
    if ( $this->_showPasscodeHTML ) {
      if ( !$this->_invalidClaimID && ($_GET['claimID'] && !$_GET['claimPasscode']) || ($_POST['claimID'] && !$_POST['claimPasscode']) ) {
        return "pickup.claimPasscode";
      }
      return "pickup.claimID";
    }
    return NULL;
  }

  /*!
    @function HTMLWrite
    
    Composes and writes the HTML that should be output for this
    instance.  If the instance is a fully-initialized, existing
    dropoff, then we'll wind up calling HTMLSummary().  Otherwise,
    we output one of several possible errors (wrong claim passcode,
    e.g.) and possibly show the claim ID and passcode "dialog".
  */
  public function HTMLWrite()
  {
    global $NSSDROPBOX_URL;
    
    $claimID = $this->_claimID;
    
    if ( $this->_invalidClaimID ) {
      NSSError("A drop-off with the claim identifier you entered could not be found; please verify that you entered the identifier correctly.","Invalid Claim ID");
      $claimID = ( $this->_cameFromEmail ? $_GET['claimID'] : $_POST['claimID'] );
    }
    if ( $this->_invalidClaimPasscode ) {
      NSSError("The password you entered for that drop-off was incorrect; please verify that you entered the passcode correctly.","Invalid Claim Passcode");
      $claimID = ( $this->_cameFromEmail ? $_GET['claimID'] : $_POST['claimID'] );
    }
    if ( $this->_isNewDropoff ) {
      if ( $this->_formInitError ) {
        NSSError($this->_formInitError,"Upload Error");
      } else {
        $this->HTMLSummary(FALSE,TRUE);
      }
    }
    else if ( $this->_showPasscodeHTML ) {
      if ( $this->_cameFromEmail ) {
        ?><h4>Please enter the claim id and claim passcode that were emailed to you.  <?PHP
      } else {
        ?><h4>Please enter the claim id and claim passcode.  <?PHP
      }
      if ( $this->_dropbox->authorizedUser() ) {
        ?>If the sender gave you a passcode for the claim, please enter it.  Otherwise, leave that field blank (it is possible that no passcode is necessary, since you are logged in).<?PHP
      }
      ?></h4>
<center>
  <form id="pickup" method="post" action="<?=$NSSDROPBOX_URL."pickup.php"?>">
  <table class="UD_form" cellpadding="4">
    <tr class="UD_form_header"><td colspan="2">
      File Pick-Up
    </td></tr>
    <tr>
      <td align="right"><b>Claim ID:</b></td>
      <td><input type="text" id="claimID" name="claimID" size="12" value=""/></td>
    </tr>
    <tr>
      <td align="right"><b>Claim Passcode:</b></td>
      <td><input type="text" name="claimPasscode" size="12" value=""/></td>
    </tr>
    <tr class="UD_form_footer"><td colspan="2" align="center">
      <input type="submit" name="pickup" value="Pick-up the File(s)"/>
    </td></tr>
  </table>
  </form>
</center>
<?PHP
    } else {
      $this->HTMLSummary(TRUE);
    }
  }

  /*!
    @function HTMLSummary
    
    Compose and write the HTML that shows all of the info for a dropoff.
    This includes:
    
    * A table of claim ID and passcode; sender info; and list of recipients
    
    * A list of the files included in the dropoff.  The icons and names in
      this list will be hyperlinked as download triggers if the $clickable
      argument is TRUE.
    
    * A table of the pickup history for this dropoff.
    
  */
  public function HTMLSummary(
    $clickable = FALSE,
    $overrideShowRecips = FALSE
  )
  {
    $curUser = $this->_dropbox->authorizedUser();
    $curUserEmail = $this->_dropbox->authorizedUserData("mail");
    if ( $curUser ) {
      if ( $curUserEmail && (strcasecmp($curUserEmail,$this->_senderEmail) == 0) ) {
        $isSender = TRUE;
      }
      if ( ($curUser == $this->_authorizedUser) || $isSender ) {
        $overrideShowRecips = TRUE;
      }
    }
    if ( $this->_senderIP ) {
      //  Try to get a hostname for the IP, too:
      $remoteHostName = gethostbyaddr($this->_senderIP);
    }
    if ( count($this->_recipients) == 1 ) {
      $isSingleRecip = TRUE;
    }
    if ( $clickable ) {
      ?><h5>Click on a filename or icon to download that component of the dropoff.</h5><?PHP
    }
?>
<table border="0" cellpadding="5"><tr valign="top">
  <td>
    <table class="UD_form" cellpadding="4">
      <tr class="UD_form_header" valign="middle"><td colspan="2">Drop-Off Summary</td><td align="right"><?PHP
    if ( $clickable && ( $isSender || $isSingleRecip ) ) {
?><img src="images/<?=( defined('NSSTHEME') ? NSSTHEME : "default" )?>/button-delete.png" onclick="doDelete();" onmouseover="document.body.style.cursor = 'pointer';" onmouseout="document.body.style.cursor = 'auto';" alt="[delete]"/><?PHP
    } else {
      echo "&nbsp;";
    }
?></td></tr>
      <tr>
        <td class="UD_form_lined" colspan="2" align="right"><b>Claim ID:</b></td>
        <td><tt><?=$this->_claimID?></tt></td>
      </tr>
      <tr class="UD_form_lined">
        <td class="UD_form_lined" colspan="2" align="right"><b>Claim Passcode:</b></td>
        <td><tt><?=$this->_claimPasscode?></tt></td>
      </tr>
      <tr>
        <td class="UD_form_lined" rowspan="6" align="center"><b>F<br/>R<br/>O<br/>M</b></td>
        <td class="UD_form_lined" align="right"><b>Name:</b></td>
        <td><tt><?=$this->_senderName?></tt></td>
      </tr>
      <tr>
        <td class="UD_form_lined" align="right"><b>Organization:</b></td>
        <td><tt><?=( $this->_senderOrganization ? $this->_senderOrganization : "&nbsp;")?></tt></td>
      </tr>
      <tr>
        <td class="UD_form_lined" align="right"><b>Email:</b></td>
        <td><tt><?=$this->_senderEmail?></tt></td>
      </tr>
      <tr>
        <td class="UD_form_lined" align="right"><b>Sent From:</b></td>
        <td><tt><?=$remoteHostName?></tt></td>
      </tr>
      <tr>
        <td class="UD_form_lined" align="right">&nbsp;</td>
        <td><tt><?=strftime("%d %b %Y&nbsp;&nbsp;%r",timeForDate($this->created()))?></tt></td>
      </tr>
      <tr class="UD_form_lined">
        <td class="UD_form_lined" align="right"><b>Confirm Delivery:</b></td>
        <td><tt><?=( $this->_confirmDelivery ? "yes" : "no" )?></tt></td>
      </tr>
<?PHP
    if ( $this->_dropbox->showRecipsOnPickup() || $overrideShowRecips || ($this->_dropbox->authorizedUser() && $this->_dropbox->authorizedUserData('grantAdminPriv')) ) {
?>
      <tr>
        <td class="UD_form_lined" align="center"><b>T<br/>O</b></td>
        <td class="UD_form_lined" align="right"><b>Name & Email:</b></td>
        <td><tt>
<?PHP
      foreach ( $this->_recipients as $recipient ) {
        printf("%s (%s)<br/>",$recipient[0],$recipient[1]);
      }
?>
        </tt></td>
      </tr>
<?PHP
    }
?>
    </table>
  </td>
  <td>
<?PHP
    $query = sprintf("SELECT rowID,* FROM file WHERE dID = %d ORDER by basename",$this->_dropoffID);
    if ( ($dropoffFiles = $this->_dropbox->database()->arrayQuery($query,SQLITE_ASSOC)) && (count($dropoffFiles) > 0) ) {
?>
    <table class="UD_form" cellpadding="4">
      <tr class="UD_form_header"><td colspan="2">Filename</td><td align="center">Type</td><td align="right">Size</td><td>Description</td></tr>
<?PHP
      $fileCount = count( $dropoffFiles );
      $filesLabel = sprintf("%d file%s",$fileCount,($fileCount != 1 ? "s" : ""));
      $i = 0;
      $downloadURLFormat = 'download.php?claimID=' . $this->_claimID . '&claimPasscode=' . $this->_claimPasscode . '&fid=%s';
      if ( $_GET['emailAddr'] ) {
        $downloadURLFormat .= '&emailAddr=' . $_GET['emailAddr'];
      }
      $downloadArray = '[';
      while ( $i < $fileCount ) {
        ?><tr class="UD_form_lined" valign="middle"><td width="20" align="center"><?PHP
        if ( $clickable ) {
          $downloadArray .= sprintf('\''.$downloadURLFormat.'\',', $dropoffFiles[$i]['rowID']);
          printf('<a href="' . $downloadURLFormat . '"><img src="images/generic.png" border="0" alt="[file]"/></a></td><td class="UD_form_lined"><a href="' . $downloadURLFormat . '"><tt>%s</tt></a></td>',
              $dropoffFiles[$i]['rowID'],
              $dropoffFiles[$i]['rowID'],
              htmlentities($dropoffFiles[$i]['basename'],ENT_NOQUOTES)
            );
        } else {
          ?><img src="images/generic.png" alt="[file]"/></td><?PHP
          printf("<td class=\"UD_form_lined\"><tt>%s</tt></td>",htmlentities($dropoffFiles[$i]['basename'],ENT_NOQUOTES));
        }
        $description = preg_replace('/\\\"/','"',$dropoffFiles[$i]['description']);
        printf("<td class=\"UD_form_lined\" align=\"center\">%s</td><td class=\"UD_form_lined\" align=\"right\">%s</td><td>%s</td>",
            $dropoffFiles[$i]['mimeType'],
            NSSFormattedMemSize($dropoffFiles[$i]['lengthInBytes']),
            ( $description ? htmlentities($description,ENT_NOQUOTES) : "&nbsp;" )
          );
        ?></tr><?PHP
		
		if($dropoffFiles[$i]['mimeType'] == "video/x-flv")
		{
			echo '<tr class="UD_form_lined" valign="middle"><td>';
			require_once 'flash/flash.php';
			
			$fileList = $this->_dropbox->database()->arrayQuery(
                      sprintf("SELECT * FROM file WHERE dID = %d AND rowID = %d",
                          $this->_dropoffID,
                          $dropoffFiles[$i]['rowID']
                        ),
                      SQLITE_ASSOC
                    );
					
			
			
			$dwnld = 'http://asset-live.reading.ac.uk/dropbox/dropoffs/' . $this->_claimID."/".$dropoffFiles[$i]['basename'];
			/*if(!file_exists($dwnld))
			{
				echo 'Somethings wrong here<br/>';
				echo exec('whoami');
			}*/
			
			flv($dwnld);
			
			echo '</td></tr>';
			
			echo '<tr class="UD_form_lined" valign="middle"><td>';
			echo '<textarea cols="40" row="1">'.flvembedstring($dwnld).'</textarea>';
			echo '</td></tr>';
		}
		
        $i++;
      }
      $downloadArray = rtrim($downloadArray,',') . ']';
?>
      <tr class="UD_form_footer"><td colspan="5" align="center"><?=$filesLabel?></td></tr>
    </table>
    <form name="deleteDropoff" method="post" action="<?=$NSSDROPBOX_URL?>delete.php">
      <input type="hidden" name="claimID" value="<?=$this->_claimID?>"/>
      <input type="hidden" name="claimPasscode" value="<?=$this->_claimPasscode?>"/>
<?PHP
      if ( $_GET['emailAddr'] ) {
        ?><input type="hidden" name="emailAddr" value="<?=$_GET['emailAddr']?>"/><?PHP
      }
?>
    </form>
<?PHP
      if ( $_GET['emailAddr'] ) {
        ?><input type="hidden" name="emailAddr" value="<?=$_GET['emailAddr']?>"/><?PHP
      }
    } else {
      echo "No files in the dropoff...something is amiss!";
    }
?>
  </td>
</tr>
<?PHP
    if ( $this->_dropbox->showRecipsOnPickup() || $overrideShowRecips || ($this->_dropbox->authorizedUser() && $this->_dropbox->authorizedUserData('grantAdminPriv')) ) {
?>
<tr>
  <td colspan="2">
<?PHP
      $query = sprintf("SELECT * FROM pickup WHERE dID = %d ORDER by pickupTimestamp",$this->_dropoffID);
      if ( ($pickups = $this->_dropbox->database()->arrayQuery($query,SQLITE_ASSOC)) && (($pickupCount = count($pickups)) > 0) ) {
?>
    <table width="100%" class="UD_form" cellpadding="4">
      <tr class="UD_form_header"><td>Picked-up on date...</td><td>...from remote address...</td><td>...by recipient.</td></tr>
<?PHP
        $pickupLabel = sprintf("%d pickup%s",$pickupCount,($pickupCount != 1 ? "s" : ""));
        $i = 0;
        while ( $i < $pickupCount ) {
        ?><tr class="UD_form_lined" valign="middle"><?PHP
          $hostname = gethostbyaddr($pickups[$i]['recipientIP']);
          if ( $hostname != $pickups[$i]['recipientIP'] ) {
            $hostname = "$hostname (".$pickups[$i]['recipientIP'].")";
          }
          $pickupDate = strftime("%d %b %Y&nbsp;&nbsp;%r",timeForTimestamp($pickups[$i]['pickupTimestamp']));
          printf("<td class=\"UD_form_lined\"><tt>%s</tt></td><td class=\"UD_form_lined\">%s</td><td>%s</td></tr>\n",
              $pickupDate,
              htmlentities($hostname,ENT_NOQUOTES),
              ( $pickups[$i]['authorizedUser'] ? htmlentities($pickups[$i]['authorizedUser'],ENT_NOQUOTES) : 
                ( $pickups[$i]['emailAddr'] ? $pickups[$i]['emailAddr'] : "&lt;Unknown&gt;" ) )
            );
          $i++;
        }
?>
      <tr class="UD_form_footer"><td colspan="3" align="center"><?=$pickupLabel?></td></tr>
    </table>

<?PHP
      } else {
        echo "None of the files have been picked-up yet.";
      }
?>
  </td>
</tr>
<?PHP
    }
?>
</table>
<br/>
<?PHP
  }

  /*!
    @function initWithClaimID
    
    Completes the initialization (begun by the __construct function)
    by looking-up a dropoff by the $claimID.
    
    Returns TRUE on success, FALSE otherwise.
  */
  private function initWithClaimID(
    $claimID
  )
  {
    if (!preg_match("/^[0-9A-Za-z]{16}$/",$claimID))
      return FALSE;

    if ( $this->_dropbox ) {
      $qResult = $this->_dropbox->database()->arrayQuery(
                    "SELECT rowID,* FROM dropoff WHERE claimID = '$claimID'",
                    SQLITE_ASSOC
                  );
      if ( $qResult && ($iMax = count($qResult)) ) {
        //  Set the fields:
        if ( $iMax == 1 ) {
          return $this->initWithQueryResult($qResult[0]);
        } else {
          NSSError("There appear to be multiple drop-offs with that claim identifier, please notify the administrator.","Invalid Claim ID");
        }
      }
    }
    return FALSE;
  }
  
  /*!
    @function initWithQueryResult
    
    Completes the initialization (begun by the __construct function)
    by pulling instance data from a hash of results from an SQL query.
    
    Also builds an in-memory recipient list by doing a query on the
    recipient table.  The list is a 2D array, each outer element being
    a hash containing values keyed by 'recipName' and 'recipEmail'.
    
    Returns TRUE on success, FALSE otherwise.
  */
  private function initWithQueryResult(
    $qResult
  )
  {
    if ( ! $this->_dropbox->directoryForDropoff($qResult['claimID'],$this->_claimDir) ) {
      NSSError("The directory containing this drop-off's file has gone missing, please notify the administrator.","Drop-Off Directory Not Found");
    } else {
      $this->_dropoffID           = $qResult['rowID'];
      
      $this->_claimID             = $qResult['claimID'];
      $this->_claimPasscode       = $qResult['claimPasscode'];
      
      $this->_authorizedUser      = $qResult['authorizedUser'];
      $this->_emailAddr           = $qResult['emailAddr'];
      
      $this->_senderName          = $qResult['senderName'];
      $this->_senderOrganization  = $qResult['senderOrganization'];
      $this->_senderEmail         = $qResult['senderEmail'];
      $this->_senderIP            = $qResult['senderIP'];
      $this->_confirmDelivery     = ( $qResult['confirmDelivery'] == 't' ? TRUE : FALSE );
      $this->_created             = dateForTimestamp($qResult['created']);
      
      $this->_recipients          = $this->_dropbox->database()->arrayQuery(
                                      sprintf("SELECT recipName,recipEmail FROM recipient WHERE dID = %d",
                                          $qResult['rowID']),
                                      SQLITE_NUM
                                     );
      
      return TRUE;
    }
    return FALSE;
  }
  
  /*!
    @function initWithFormData
    
    This monster routine examines POST-type form data coming from our dropoff
    form, validates all of it, and actually creates a new dropoff.
    
    The validation is done primarily on the email addresses that are involved,
    and all of that is documented inline below.  We also have to be sure that
    the user didn't leave any crucial fields blank.
    
    We examine the incoming files to be sure that individually they are all
    below our parent dropbox's filesize limit; in the process, we sum the
    sizes so that we can confirm that the whole dropoff is below the parent's
    dropoff size limit.
    
    Barring any problems with all of that, we get a new claimID and claim
    directory for this dropoff and move the uploaded files into it.  We add
    a record to the "dropoff" table in the database.
    
    We also have to craft and email and send it to all of the recipients.  A
    template string is created with the content and then filled-in individually
    (think form letter) for each recipient (we embed the recipient's email address
    in the URL so that it _might_ be possible to identify the picker-upper even
    when the user isn't logged in).
    
    If any errors occur, this function will return an error string.  But
    if all goes according to plan, then we return NULL!
  */
  private function initWithFormData()
  {
    global $NSSDROPBOX_URL;
    
    $senderName = stripslashes($_POST['senderName']);
    $senderOrganization = stripslashes($_POST['senderOrganization']);
    $senderEmail = stripslashes(strtolower($_POST['senderEmail']));
    $confirmDelivery = ( $_POST['confirmDelivery'] ? TRUE : FALSE );
    
    $recipients = array();
    $recipIndex = 1;
	
    while ( array_key_exists('recipient_'.$recipIndex,$_POST) ) {
      $recipName = stripslashes($_POST['recipName_'.$recipIndex]);
      $recipEmail = stripslashes($_POST['recipEmail_'.$recipIndex]);
      if ( $recipName || $recipEmail ) {
        //  Take the email to purely lowercase for simplicity:
        $recipEmail = strtolower($recipEmail);
         
        //  Just a username?  We add an implicit "@domain.com" for these and validate them!
        if ( preg_match('/^([a-z0-9][a-z0-9\.\_\-]*)$/',$recipEmail,$emailParts) ) {
          $emailParts[2] = $this->dropbox()->dropboxDomain();
        }
        else if ( ! preg_match('/^([a-z0-9][a-z0-9\.\_\-]*)\@([a-z0-9][a-z0-9\_\-\.]+)$/',$recipEmail,$emailParts) ) {
          return "The recipient email address '" . htmlentities($recipEmail) . "' is invalid.  Use the back button in your browser to go back and fix this address before trying again.";
        }
        $recipEmailDomain = $emailParts[2];
        $recipEmail = $emailParts[1]."@".$emailParts[2];
    
        //  Look at the recipient's email domain; un-authenticated users can only deliver
        //  to the dropbox's domain:
        if ( ! $this->_dropbox->authorizedUser() && ($recipEmailDomain != $this->_dropbox->dropboxDomain()) ) {
          return "You must be logged-in as a ".$this->_dropbox->authUserFormalDesc()." user in order to drop-off a file for a non-".$this->_dropbox->authUserShortDesc()." user.";
        }
        $recipients[] = array(( $recipName ? $recipName : "" ),$recipEmail);
      } else if ( $recipName && !$recipEmail ) {
        return "You must specify all recipient's email addresses in the form.  Use the back button in your browser to go back and fix this omission before trying again.";
      }
	  
      $recipIndex++;
    }
    
    //
    //  Check for an uploaded CSV/TXT file containing addresses:
    //
    if ( $_FILES['recipient_csv']['tmp_name'] ) {
      if ( $_FILES['recipient_csv']['error'] != UPLOAD_ERR_OK ) {
        $error = sprintf("There was an error while uploading '%s'.  ",$_FILES['recipient_csv']['name']);
        switch ( $_FILES['recipient_csv']['error'] ) {
          case UPLOAD_ERR_INI_SIZE:
            $error .= "The recipient file's size exceeds the limit imposed by PHP on the server; please contact the administrator regarding this problem.";
            break;
          case UPLOAD_ERR_FORM_SIZE:
            $error .= "The recipient file's size exceeds the limit for ".$this->_dropbox->dropboxName()." (the maximum is ".$this->_dropbox->maxBytesForFile().").";
            break;
          case UPLOAD_ERR_PARTIAL:
            $error .= "The recipient file was only partially uploaded; your network connection may have timed-out while attempting to upload.";
            break;
          case UPLOAD_ERR_NO_FILE:
            $error .= "No recipient file was actually uploaded.";
            break;
          case UPLOAD_ERR_NO_TMP_DIR:
            $error .= "The server was not configured with a temporary folder for uploads.";
            break;
          case UPLOAD_ERR_CANT_WRITE:
            $error .= "The server's temporary folder is misconfigured.";
            break;
        }
        return $error;
      }
      
      //  Parse the CSV/TXT file:
      if ( $csv = fopen($_FILES['recipient_csv']['tmp_name'],'r') ) {
        while ( $fields = fgetcsv($csv) ) {
          if ( $fields[0] !== NULL ) {
            //  Got one; figure out which field is an email address:
            foreach ( $fields as $recipEmail ) {
              //  Take the email to purely lowercase for simplicity:
              $recipEmail = strtolower($recipEmail);
               
              //  Just a username?  We add an implicit "@domain.com" for these and validate them!
              if ( preg_match('/^([a-z0-9][a-z0-9\.\_\-]*)$/',$recipEmail,$emailParts) ) {
                $emailParts[2] = $this->dropbox()->dropboxDomain();
              }
              else if ( ! preg_match('/^([a-z0-9][a-z0-9\.\_\-]*)\@([a-z0-9][a-z0-9\_\-\.]+)$/',$recipEmail,$emailParts) ) {
                continue;
              }
              $recipEmailDomain = $emailParts[2];
              $recipEmail = $emailParts[1]."@".$emailParts[2];
          
              //  Look at the recipient's email domain; un-authenticated users can only deliver
              //  to the dropbox's domain:
              if ( ! $this->_dropbox->authorizedUser() && ($recipEmailDomain != $this->_dropbox->dropboxDomain()) ) {
                return "You must be logged-in as a ".$this->_dropbox->authUserFormalDesc()." user in order to drop-off a file for a non-".$this->_dropbox->authUserShortDesc()." user.";
              }
              $recipients[] = array(( $recipName ? $recipName : "" ),$recipEmail);
            }
          }
        }
        fclose($csv);
      } else {
        return "Could not read the uploaded recipients file.";
      }
      
      $fileCount = count( array_keys($_FILES) ) - 1;
    } else {
      $fileCount = count( array_keys($_FILES) );
    }
	
	$video = array();
	$videoIndex = 1;
	
	while ( array_key_exists('video_'.$videoIndex,$_POST) ) {
	  $video[$videoIndex] = $_POST['video_'.$videoIndex];
	
	  $videoIndex++;
	}
    
    //  Confirm that all fields are present and accounted for:
    if ( $fileCount == 0 ) {
      return "You must choose at least one file to drop-off.  Use the back button in your browser to go back and fix this omission before trying again.";
    }
    
    //  Now make sure each file was uploaded successfully, isn't too large,
    //  and that the total size of the upload isn't over capacity:
    $i = 1;
    $totalBytes = 0.0;
    while ( $i <= $fileCount ) {
      $key = "file_".$i;
      if ( $_FILES[$key]['name'] ) {
        if ( $_FILES[$key]['error'] != UPLOAD_ERR_OK ) {
          $error = sprintf("There was an error while uploading '%s'.  ",$_FILES[$key]['name']);
          switch ( $_FILES[$key]['error'] ) {
            case UPLOAD_ERR_INI_SIZE:
              $error .= "The file's size exceeds the limit imposed by PHP on the server; please contact the administrator regarding this problem.";
              break;
            case UPLOAD_ERR_FORM_SIZE:
              $error .= "The file's size exceeds the limit for ".$this->_dropbox->dropboxName()." (the maximum is ".$this->_dropbox->maxBytesForFile().").";
              break;
            case UPLOAD_ERR_PARTIAL:
              $error .= "The file was only partially uploaded; your network connection may have timed-out while attempting to upload.";
              break;
            case UPLOAD_ERR_NO_FILE:
              $error .= "No file was actually uploaded.";
              break;
            case UPLOAD_ERR_NO_TMP_DIR:
              $error .= "The server was not configured with a temporary folder for uploads.";
              break;
            case UPLOAD_ERR_CANT_WRITE:
              $error .= "The server's temporary folder is misconfigured.";
              break;
          }
          return $error;
        }
        if ( ($bytes = $_FILES[$key]['size']) < 0 ) {
          //  Grrr...stupid 32-bit nonsense.  Convert to the positive
          //  value float-wise:
          $bytes = ($bytes & 0x7FFFFFFF) + 2147483648.0;
        }
        if ( $bytes > $this->_dropbox->maxBytesForFile() ) {
          return sprintf("The file '%s' was too large.  Each uploaded file may be (at most) %s.",
                      $_FILES[$key]['name'],
                      NSSFormattedMemSize($this->_dropbox->maxBytesForFile())
                    );
        }
        if ( ($totalBytes += $bytes) > $this->_dropbox->maxBytesForDropoff() ) {
          return sprintf("The total size of the uploaded files exceeds the maximum for a single drop-off.  Altogether, a single upload can be (at most) %s.",
                      $_FILES[$key]['name'],
                      NSSFormattedMemSize($this->_dropbox->maxBytesForDropoff())
                    );
        }
      }
      $i++;
    }
    if ( $totalBytes == 0 ) {
      return "No files were uploaded.";
    }
    
    if ( ! $senderName ) {
      return "You must specify your name in the form.  Use the back button in your browser to go back and fix this omission before trying again.";
    }
    if ( ! $senderEmail ) {
      return "You must specify your own email address in the form.  Use the back button in your browser to go back and fix this omission before trying again.";
    }
    if ( ! preg_match('/^([a-z0-9][a-z0-9\.\_\-]*)\@([a-z0-9][a-z0-9\_\-\.]+)$/',$senderEmail,$emailParts) ) {
      return "The sender email address you entered was invalid.  Use the back button in your browser to go back and fix this omission before trying again.";
    }
    $senderEmail = $emailParts[1]."@".$emailParts[2];
    
    //  Invent a passcode and claim ID:
    $claimPasscode = NSSGenerateCode();
    $claimID = NULL; $claimDir = NULL;
    if ( ! $this->_dropbox->directoryForDropoff($claimID,$claimDir) ) {
      return "A unique directory to contain your dropped-off files could not be created; please contact the administrator.";
    }
    
    //  Insert into database:
    if ( $this->_dropbox->database()->queryExec('BEGIN') ) {
      
      $query = sprintf("INSERT INTO dropoff
(claimID,claimPasscode,authorizedUser,senderName,
 senderOrganization,senderEmail,senderIP,
 confirmDelivery,created)
VALUES
('%s','%s','%s','%s',
 '%s','%s','%s',
 '%s','%s')",
                  sqlite_escape_string($claimID),
                  sqlite_escape_string($claimPasscode),
                  sqlite_escape_string($this->_dropbox->authorizedUser()),
                  sqlite_escape_string($senderName),
                  sqlite_escape_string($senderOrganization),
                  sqlite_escape_string($senderEmail),
                  sqlite_escape_string($_SERVER['REMOTE_ADDR']),
                  ( $confirmDelivery ? 't' : 'f' ),
                  timestampForTime(time())
                );
      if ( $this->_dropbox->database()->queryExec($query) ) {
      
        $dropoffID = $this->_dropbox->database()->lastInsertRowid();
        
        //  Add recipients:
        foreach ( $recipients as $recipient ) {
          $query = sprintf("INSERT INTO recipient (dID,recipName,recipEmail) VALUES (%d,'%s','%s')",
                    $dropoffID,
                    sqlite_escape_string($recipient[0]),
                    sqlite_escape_string($recipient[1])
                   );
          if ( ! $this->_dropbox->database()->queryExec($query) ) {
            $this->_dropbox->database()->queryExec("ROLLBACK");
            return "Could not add recipients to the database.";
          }
        }
        
        //  Process the files:
        $i = 1;
        $realFileCount = 0;
        while ( $i <= $fileCount ) {
          $key = "file_".$i;
          if ( $_FILES[$key]['name'] ) {
            $tmpname = basename($_FILES[$key]['tmp_name']);
            if ( ! move_uploaded_file($_FILES[$key]['tmp_name'],$claimDir."/".$tmpname) ) {
              //  Exit gracefully -- dump database changes and remove the dropoff
              //  directory:
              $this->_dropbox->writeToLog("error while storing dropoff files for $claimID");
              if ( ! rmdir_r($claimDir) ) {
                $this->_dropbox->writeToLog("unable to remove $claimDir -- orphaned!!");
              }
              if ( ! $this->_dropbox->database()->queryExec("ROLLBACK") ) {
                $this->_dropbox->writeToLog("failed to ROLLBACK after botched dropoff:  $claimID");
                $this->_dropbox->writeToLog("there may be orphans");
              }
              return "Trouble while attempting to drop '".$_FILES[$key]['name']."' into its dropoff directory, please notify the administrator.";
            }
			
            if ( ($bytes = $_FILES[$key]['size']) < 0 ) {
              //  Grrr...stupid 32-bit nonsense.  Convert to the positive
              //  value float-wise:
              $bytes = ($bytes & 0x7FFFFFFF) + 2147483648.0;
            }
            //  Add to database:
            $query = sprintf("INSERT INTO file
  (dID,tmpname,basename,lengthInBytes,mimeType,description)
  VALUES
  (%d,'%s','%s',%.0f,'%s','%s')",
                    $dropoffID,
                    sqlite_escape_string($tmpname),
                    sqlite_escape_string(stripslashes($_FILES[$key]['name'])),
                    $bytes,
                    sqlite_escape_string(( $_FILES[$key]['type'] ? $_FILES[$key]['type'] : "application/octet-stream" )),
                    sqlite_escape_string(stripslashes($_POST["desc_".$i]))
                  );
            if ( ! $this->_dropbox->database()->queryExec($query) ) {
              //  Exit gracefully -- dump database changes and remove the dropoff
              //  directory:
              $this->_dropbox->writeToLog("error while adding dropoff file to database for $claimID");
              if ( ! rmdir_r($claimDir) ) {
                $this->_dropbox->writeToLog("unable to remove $claimDir -- orphaned!!");
              }
              if ( ! $this->_dropbox->database()->queryExec("ROLLBACK") ) {
                $this->_dropbox->writeToLog("failed to ROLLBACK after botched dropoff:  $claimID");
                $this->_dropbox->writeToLog("there may be orphans");
              }
              return "Trouble while attempting to save the information for '".$_FILES[$key]['name']."', please notify the administrator.";
            }
            
            //  That's right, one more file!
            $realFileCount++;
            
            $emailFileList .= sprintf("
      Name:            %s
      Content Type:    %s
      Size:            %s
      Description:     %s
",
                                stripslashes($_FILES[$key]['name']),
                                $_FILES[$key]['type'],
                                NSSFormattedMemSize($_FILES[$key]['size']),
                                stripslashes($_POST["desc_".$i])
                              );
						
						
			if($video[$i]) 
			{
			  define('PHPVIDEOTOOLKIT_FFMPEG_BINARY', 'ffmpeg');
			  define('PHPVIDEOTOOLKIT_FLVTOOLS_BINARY', 'flvtool2');
			  define('PHPVIDEOTOOLKIT_MENCODER_BINARY', 'mencoder');
			
			  require_once NSSDROPBOX_BASE_DIR.'/phpvideotoolkit/phpvideotoolkit.php';
	          require_once NSSDROPBOX_BASE_DIR.'/phpvideotoolkit/adapters/videoto.php';
			
			  $fileToConvert = $claimDir."/".$tmpname.'.'.$this->findexts(stripslashes($_FILES[$key]['name']));
			  copy($claimDir."/".$tmpname, $fileToConvert); 
			  
			  set_time_limit(360);
			  
			  //$this->_dropbox->writeToLog('Begin convert: '.$fileToConvert);
			  $result = VideoTo::FLV($fileToConvert, array(
				'temp_dir'					=> $claimDir.'/', 
				'output_dir'				=> $claimDir.'/', 
				'die_on_error'				=> false,
				'overwrite_mode'			=> PHPVideoToolkit::OVERWRITE_EXISTING,
				'width'						=> 480,
				'height'					=> 295
			    ));
			  //$this->_dropbox->writeToLog('Stopped convert: '.$fileToConvert);
				
			  // 		check for an error
			  if($result !== PHPVideoToolkit::RESULT_OK)
			  {
				  $this->_dropbox->writeToLog(VideoTo::getError());
				  return 'Trouble while converting '.$_FILES[$key]['name'].' into FLV format.';
			  }
			  else
			  {
				$output = VideoTo::getOutput();
				$filename = basename($output[0]);
				$filename_hash = md5($filename);
				
				$this->_dropbox->writeToLog($filename.' has finished convertion');
			
				$bytes = filesize($claimDir.'/'.$filename);
			
				//  Add to database:
				$query = sprintf("INSERT INTO file
								(dID,tmpname,basename,lengthInBytes,mimeType,description)
								VALUES
								(%d,'%s','%s',%.0f,'%s','%s')",
								$dropoffID,
								sqlite_escape_string($filename),
								sqlite_escape_string($filename),
								$bytes,
								sqlite_escape_string("video/x-flv" ),
								sqlite_escape_string(stripslashes($_POST["desc_".$i]))
								);
				if ( ! $this->_dropbox->database()->queryExec($query) ) 
				{
				  //  Exit gracefully -- dump database changes and remove the dropoff
				  //  directory:
				  $this->_dropbox->writeToLog("error while adding dropoff file to database for $claimID");
				  if ( ! rmdir_r($claimDir) ) {
                    $this->_dropbox->writeToLog("unable to remove $claimDir -- orphaned!!");
				  }
				  if ( ! $this->_dropbox->database()->queryExec("ROLLBACK") ) {
                    $this->_dropbox->writeToLog("failed to ROLLBACK after botched dropoff:  $claimID");
					$this->_dropbox->writeToLog("there may be orphans");
				  }
				  return "Trouble while attempting to save the information for '".$_FILES[$key]['name']."' converted into flash format, please notify the administrator.";
				}			
			  }
			  /*
			  //AVI anyone?
			  $filename_minus_ext = substr($filename, 0, strrpos($filename, '.'));
			  
			  $toolkit = new PHPVideoToolkit($claimDir.'/');
	
			  // 	set PHPVideoToolkit class to run silently
			  $toolkit->on_error_die = FALSE;
			  
			  $toolkit->setInputFile($fileToConvert);
			  $toolkit->setVideoOutputDimensions(320, 240);
			  
			  $toolkit->setFormat(PHPVideoToolkit::FORMAT_AVI);
			  $toolkit->setOutput($claimDir.'/', $filename_minus_ext.'.avi', PHPVideoToolkit::OVERWRITE_EXISTING);
			  $result = $toolkit->execute(true, true);
			  
			  if($result !== PHPVideoToolkit::RESULT_OK)
			  {
				$this->_dropbox->writeToLog(VideoTo::getError());
				return 'Trouble while converting '.$_FILES[$key]['name'].' into AVI format.';
			  }
			  else
			  {				
				//$this->_dropbox->writeToLog($filename.' has finished convertion');
			
				$bytes = filesize($claimDir.'/'.$filename);
			
				//  Add to database:
				$query = sprintf("INSERT INTO file
								(dID,tmpname,basename,lengthInBytes,mimeType,description)
								VALUES
								(%d,'%s','%s',%.0f,'%s','%s')",
								$dropoffID,
								sqlite_escape_string($filename_minus_ext.'.avi'),
								sqlite_escape_string($filename_minus_ext.'.avi'),
								$bytes,
								sqlite_escape_string("video/x-msvideo" ),
								sqlite_escape_string(stripslashes($_POST["desc_".$i]))
								);
				if ( ! $this->_dropbox->database()->queryExec($query) ) 
				{
				  //  Exit gracefully -- dump database changes and remove the dropoff
				  //  directory:
				  $this->_dropbox->writeToLog("error while adding dropoff file to database for $claimID");
				  if ( ! rmdir_r($claimDir) ) {
                    $this->_dropbox->writeToLog("unable to remove $claimDir -- orphaned!!");
				  }
				  if ( ! $this->_dropbox->database()->queryExec("ROLLBACK") ) {
                    $this->_dropbox->writeToLog("failed to ROLLBACK after botched dropoff:  $claimID");
					$this->_dropbox->writeToLog("there may be orphans");
				  }
				  return "Trouble while attempting to save the information for '".$_FILES[$key]['name']."' converted into flash format, please notify the administrator.";
				}
			  }*/
			  
            } else {
			  $this->_dropbox->writeToLog('No convertion needed');
			}
		  }
          $i++;
        }
        
        //  Once we get here, it's time to commit the stuff to the database:
        $this->_dropbox->database()->queryExec("COMMIT");

        $this->_dropoffID             = $dropoffID;
          
        //  At long last, fill-in the fields:
        $this->_claimID               = $claimID;
        $this->_claimPasscode         = $claimPasscode;
        $this->_claimDir              = $claimDir;
        
        $this->_authorizedUser        = $this->_dropbox->authorizedUser();
        
        $this->_senderName            = $senderName;
        $this->_senderOrganization    = $senderOrganization;
        $this->_senderEmail           = $senderEmail;
        $this->_senderIP              = $_SERVER['REMOTE_ADDR'];
        $this->_confirmDelivery       = $confirmDelivery;
        $this->_created               = getdate();
        
        $this->_recipients            = $recipients;
        
        //  Construct the email notification and deliver:
        $emailContent = sprintf("
This is an automated message sent to you by the %s.
    
$senderName ($senderEmail) has dropped-off %s for you.

You can retrieve the drop-off by clicking the following link (or copying and pasting it into your web browser):

  \"%s\"

Full information for the drop-off:

    Claim ID:          $claimID
    Claim Passcode:    $claimPasscode
    Date of Drop-Off:  %s

    -- Sender --
      Name:            $senderName
      Organization:    $senderOrganization
      Email Address:   $senderEmail
      IP Address:      $senderIP
      
    -- Uploaded File%s --
%s
      
      ",
          $this->_dropbox->dropboxName(),
          ( $realFileCount == 1 ? "a file" : "$realFileCount files" ),
          $NSSDROPBOX_URL."pickup.php?claimID=".$claimID."&claimPasscode=".$claimPasscode."&emailAddr=%s",
          timestampForTime(time()),
          ( $realFileCount == 1 ? "" : "s" ),
          str_replace('%','%%',$emailFileList)
        );
        
        foreach ( $recipients as $recipient ) {
          $success = $this->_dropbox->deliverEmail(
              $recipient[1],
              sprintf("[%s] $senderName has dropped-off %s for you!",
                    $this->_dropbox->dropboxName(),
                    ( $realFileCount == 1 ? "a file" : "$realFileCount files" )
                  ),
              sprintf($emailContent,urlencode($recipient[1]))
           );
          if ( ! $success ) {
            $this->_dropbox->writeToLog(sprintf("notification email not delivered successfully to %s for claimID $claimID",$recipient[1]));
          } else {
            $this->_dropbox->writeToLog(sprintf("notification email delivered successfully to %s for claimID $claimID",$recipient[1]));
          }
        }
        
        //  Log our success:
        $this->_dropbox->writeToLog(sprintf("$senderName <$senderEmail> => $claimID [%s]",
                                     ( $realFileCount == 1 ? "1 file" : "$realFileCount files" )));
      } else {
        return "Unable to add a dropoff record to the database, please notify the administrator.";
      }
    } else {
      return "Unable to begin database transaction, please notify the administrator.";
    }
    return NULL;
  }
  
  function findexts ($filename) 
 { 
 $filename = strtolower($filename) ; 
 $exts = split("[/\\.]", $filename) ; 
 $n = count($exts)-1; 
 $exts = $exts[$n]; 
 return $exts; 
 }

}

?>
