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
// $Id: dropoff_list.php 50 2008-07-09 15:54:07Z frey $
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
  // This page handles the listing of dropoffs made by an
  // authenticated user.  If the user is NOT authenticated,
  // then an error is presented.
  //
  if ( $theDropbox->authorizedUser() ) {
    //
    // Returns an array of all NSSDropoff instances belonging to
    // this user.
    //
    $allDropoffs = NSSDropoff::dropoffsFromCurrentUser($theDropbox);
    //
    // Start the web page and add some Javascript to automatically
    // fill-in and submit a pickup form when a dropoff on the page
    // is clicked.
    //
    $theDropbox->HTMLWriteHeader();
?>
<script type="text/javascript">
<!--

function doPickup(theID)
{
  document.pickup.claimID.value = theID;
  return document.pickup.submit();
}

//-->
</script>
<?PHP
    $theDropbox->HTMLStartBody();
    
    if ( $allDropoffs && ($iMax = count($allDropoffs)) ) {
      //
      // Label for the footer of the table -- dropoff count:
      //
      $tableLabel = sprintf("%d drop-off%s",$iMax,( $iMax != 1 ? "s" : "" ));
?>
<h5>Click on a drop-off claim ID to view the information and files for that drop-off.</h5>
<table class="UD_form" cellpadding="4">
  <tr class="UD_form_header"><td>Claim ID</td><td>Sender</td><td>Recipient</td><td>Created</td></tr>
<?PHP
      $i = 0;
      while ( $i < $iMax ) {
        //
        // For each drop off we add a row to the table.  The
        // row contains the claimID, sender info, and the
        // date the dropoff was made.
        //
        $claimID = $allDropoffs[$i]->claimID();
        ?><tr valign="middle" class="UD_form_lined"><?PHP
        printf("<td class=\"UD_form_lined\"><a onmouseover=\"document.body.style.cursor = 'pointer';\" onmouseout=\"document.body.style.cursor = 'auto';\" onclick=\"doPickup('%s');\"><tt>%s</tt></a></td>",
                  $claimID,
                  $claimID
          );
        printf("<td class=\"UD_form_lined\">%s%s (%s)</td>",
                  $allDropoffs[$i]->senderName(),
                  ( ($value = $allDropoffs[$i]->senderOrganization()) ? ", $value" : "" ),
                  $allDropoffs[$i]->senderEmail()
          );
        ?><td class="UD_form_lined"><?PHP
        $recipients = $allDropoffs[$i]->recipients();
        foreach ( $recipients as $recipient ) {
          printf("%s &lt;%s&gt;<br/>",
                  $recipient[0],
                  $recipient[1]
            );
        }
        ?></td><?PHP
        $dropoffDate = $allDropoffs[$i]->created();
        printf("<td><tt>%02d:%02d %s %d, %d</tt></td>",
                  $dropoffDate['hours'],
                  $dropoffDate['minutes'],
                  $dropoffDate['month'],
                  $dropoffDate['mday'],
                  $dropoffDate['year']
          );
        ?></tr><?PHP
        $i++;
      }
?>
  <tr class="UD_form_footer"><td colspan="4" align="center"><?=$tableLabel?></td></tr>
</table>
<br/>
<form name="pickup" method="post" action="<?=$NSSDROPBOX_URL?>pickup.php">
  <input type="hidden" id="claimID" name="claimID" value=""/>
</form>
<?PHP
    
    } else {
      ?><h5>There are no drop-offs made by you on record at this time.</h5><?PHP
    }
  } else {
    $theDropbox->HTMLWriteHeader();
    $theDropbox->HTMLStartBody();
    ?><br/><br/><?PHP
    NSSError("This feature is only available to users who have logged-in to the system.","Access Denied");
    ?><br/><br/><?PHP
  }
  $theDropbox->HTMLWriteFooter();
}

?>