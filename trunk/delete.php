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
// $Id: delete.php 50 2008-07-09 15:54:07Z frey $
//

//
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
include "preferences.php";

include_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

//
// This is pretty straightforward; depending upon the form data coming
// into this PHP session, creating a new dropoff object will either
// display the claimID-and-claimPasscode "dialog" (no form data or
// missing/invalid passcode); display the selected dropoff if the
// claimID and claimPasscode are valid OR the recipient matches the
// authenticate user -- it's all built-into the NSSDropoff class.
//
if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  if ( $thePickup = new NSSDropoff($theDropbox) ) {
    $success = $thePickup->removeDropoff();
    
    //  Start the page and add a refresh after 5 seconds if
    //  the removal was successful.
    $theDropbox->HTMLWriteHeader();
    if ( $success ) {
      ?><meta http-equiv="refresh" content="5;URL=<?=$NSSDROPBOX_URL?>"><?PHP
    }
    $theDropbox->HTMLStartBody();
    
    if ( $success ) {
      ?>
<h3>The dropoff with claim ID <?=$thePickup->claimID()?> was successfully removed.</h3>
      <?PHP
    } else {
      NSSError("Unabled to remove the dropoff.  Please contact the system administrator.","Unable to remove ".$thePickup->claimID());
    }
    
    $theDropbox->HTMLWriteFooter();
  }
}

?>