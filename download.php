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
// $Id: download.php 50 2008-07-09 15:54:07Z frey $
//

//
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
include "preferences.php";

include_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");


//
// download.php
//
// Handles download of a file associated with a drop-off.  Posted
// form data is necessary, containing a claimID and a file
// identifier (fid).  The "fid" is used instead of an actual filename
// for the sake of simplicity.
//
// The necessary authentication is all handled by:
//
//   (1) When the dropbox instance is created, the page's cookie is
//       consulted and authentication may be pulled from that.
//   (2) When the pickup instance is created, the authenticated
//       username itself may imply authorization; otherwise, posted
//       form data (claimID and claimPasscode) will provide the
//       authorization.
//
if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  $thePickup = new NSSDropoff($theDropbox);
  
  $theDropbox->writeToLog("into download");
  
  if ( $thePickup->dropoffID() > 0 ) {
    ($fid = $_POST['fid']) || ($fid = $_GET['fid']);
    $downloaded = $thePickup->downloadFile($fid);
  }
  if ( ! $downloaded ) {
    $theDropbox->HTMLWriteHeader();
    $theDropbox->HTMLStartBody();
    ?><h5>No file was chosen for download.</h5><?PHP
    $theDropbox->HTMLWriteFooter();
  }
}

?>
