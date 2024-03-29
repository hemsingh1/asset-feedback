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
// $Id: graph.php 50 2008-07-09 15:54:07Z frey $
//

//
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
include "preferences.php";

include_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

define('RRD_DATA_PATH','/opt/DropboxData/rrd/');

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');              // Date in the past
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');     // always modified
header('Cache-Control: no-cache, must-revalidate');            // HTTP/1.1
header('Pragma: no-cache');   
header('Content-type: image/png');

switch ( $_GET['p'] ) {

  case 7:
  case 30:
  case 90:
  case 365:
  case 3650:
    $period = $_GET['p'];
    break;
    
}

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  //
  // This page displays usage graphs for the system.
  //
  if ( $theDropbox->authorizedUser() && $theDropbox->authorizedUserData('grantAdminPriv') ) {
    
    if ( $period && ($metric = $_GET['m']) || ($metric = $_POST['metric']) ) {
      if ( is_readable($path = RRD_DATA_PATH.$metric.$period.'.png') ) {
        readfile($path);
        exit(0);
      }
    }
    
  }
}
readfile(RRD_DATA_PATH.'notfound.png');

?>
