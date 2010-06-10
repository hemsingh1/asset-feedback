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
// $Id: stats.php 50 2008-07-09 15:54:07Z frey $
//

//
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
include "preferences.php";

include_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  //
  // This page displays usage graphs for the system.
  //
  $theDropbox->HTMLWriteHeader();
  $theDropbox->HTMLStartBody();
  if ( $theDropbox->authorizedUser() && $theDropbox->authorizedUserData('grantAdminPriv') ) {
    
    switch ( $_GET['period'] ) {
    
      case 'month':
        $period = 30;
        break;
      case '90days':
        $period = 90;
        break;
      case 'year':
        $period = 365;
        break;
      case 'decade':
        $period = 3650;
        break;
      case 'week':
      default:
        $period = 7;
        break;
    }
    
    ?>
<blockquote>
  <form name="periodForm" method="get" action="stats.php">
  <table border="0">
    <tr>
      <td>View stats for the</td>
      <td>
        <select name="period" onchange="return document.periodForm.submit();">
          <option value="week"<?=($period == 7 ? " selected=\"selected\"" : "")?>>past week</option>
          <option value="month"<?=($period == 30 ? " selected=\"selected\"" : "")?>>past month</option>
          <option value="90days"<?=($period == 90 ? " selected=\"selected\"" : "")?>>past 90 days</option>
          <option value="year"<?=($period == 365 ? " selected=\"selected\"" : "")?>>past year</option>
          <option value="decade"<?=($period == 3650 ? " selected=\"selected\"" : "")?>>past 10 years</option>
        </select>
      </td>
    </tr>
  </table>
  </form>
  <hr/>
  <table border="0">
    
    <tr><td><b>Number of dropoffs made (checked daily)</b></td></tr>
    <tr><td><img src="graph.php?m=dropoff_count&p=<?=$period?>" alt="[dropoff counts]"/></td></tr>
    
    <tr><td><b>Total amount of data dropped off (checked daily)</b></td></tr>
    <tr><td><img src="graph.php?m=total_size&p=<?=$period?>" alt="[total dropoff bytes]"/></td></tr>
    
    <tr><td><b>Total files dropped off (checked daily)</b></td></tr>
    <tr><td><img src="graph.php?m=total_files&p=<?=$period?>" alt="[total dropoff files]"/></td></tr>
    
    <tr><td><b>File count per dropoff (checked daily)</b></td></tr>
    <tr><td><img src="graph.php?m=files_per_dropoff&p=<?=$period?>" alt="[files per dropoff]"/></td></tr>
    
  </table>
</blockquote>
    <?PHP
    
  } else {
    ?><br/><br/><?PHP
    NSSError("This feature is only available to administrators who have logged-in to the system.","Access Denied");
    ?><br/><br/><?PHP
  }
  $theDropbox->HTMLWriteFooter();
}



?>
