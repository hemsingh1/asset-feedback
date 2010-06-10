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
// $Id: dropoff.php 50 2008-07-09 15:54:07Z frey $
//

//
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
include "preferences.php";

include_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

function generateEmailTable(
  $aDropbox,
  $label = 1
)
{
?>
            <table border="0">
              <tr>
                <td align="right">Name:</td>
                <td><input type="text" id="recipName_<?=$label?>" name="recipName_<?=$label?>" size="30" value=""/></td>
              </tr>
              <tr>
<?PHP
  if ( $aDropbox->authorizedUser() ) {
?>
                <td align="right">Email or Username:</td>
                <td>
                  <input type="text" id="recipEmail_<?=$label?>" name="recipEmail_<?=$label?>" size="30" value=""/>
                  <input type="hidden" name="recipient_<?=$label?>" value="<?=$label?>"/>
                </td>
<?PHP
  } else {
?>
                <td align="right">Email:</td>
                <td>
                  <input type="text" id="recipEmail_<?=$label?>" name="recipEmail_<?=$label?>" size="16" value=""/>@<?=$aDropbox->dropboxDomain();?>
                  <input type="hidden" name="recipient_<?=$label?>" value="<?=$label?>"/>
                </td>
<?PHP
  }
?>
              </tr>
            </table>
<?PHP
}

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  $theDropbox->HTMLWriteHeader();
  
  if ( $_POST['Action'] == "dropoff" ) {
    //
    // Posted form data indicates that a dropoff form was filled-out
    // and submitted; if posted from data is around, creating a new
    // dropoff instance creates a new dropoff using said form data.
    //
    if ( $theDropoff = new NSSDropoff($theDropbox) ) {
      $theDropbox->HTMLStartBody();
      $theDropoff->HTMLWrite();
    }
  
  } else {
    //
    // We need to present the dropoff form.  This page will include some
    // JavaScript that does basic checking of the form prior to submission
    // as well as the code to handle the attachment of multiple files.
    // After all that, we start the page body and write-out the HTML for
    // the form.
    //
    // If the user is authenticated then some of the fields will be
    // already-filled-in (sender name and email).
    //
    ?>

<script type="text/javascript">
<!--
var   file_id = 1;
var   recipient_id = 1;

function makeVisible() 
{
	document.images['loaderImage'].style.visibility = 'visible';
}

function addFile()
{
  if ( document.getElementById("file_" + file_id).value ) {
    var   uploadRow   = document.getElementById("file_matrix").insertRow(3 + 2 * (file_id - 1));
    var   descRow   = document.getElementById("file_matrix").insertRow(4 + 2 * (file_id - 1));
    
    if ( uploadRow && descRow ) {
      var label = uploadRow.insertCell(0);
      var upload = uploadRow.insertCell(1);
      var blank = descRow.insertCell(0);
      var desc = descRow.insertCell(1);
      
      file_id++;
      
      label.innerHTML = '<b>File ' + file_id + ':</b>';
      upload.innerHTML = '<input type="file" name="file_' + file_id + '" id="file_' + file_id + '" size="50" onChange="addFile();"/>';
      
      blank.innerHTML = '&nbsp;';
      desc.innerHTML = 'Description:&nbsp;<input type="text" name="desc_' + file_id + '" id="desc_' + file_id + '" size="30"/>&nbsp;Video:&nbsp;<input type="checkbox" name="video_' + file_id + 
						'" id="video_'  + file_id + '" checked="false"/>';
    }
  }
  return 1;
}

function addRecipient()
{
  if ( document.getElementById('recipEmail_' + recipient_id).value ) {
    var   newRow = document.getElementById("recipient_matrix").insertRow(1 + recipient_id);
    
    if ( newRow ) {
      var label = newRow.insertCell(0);
      var name = newRow.insertCell(1);
      
      recipient_id++;
      
      label.innerHTML = '<b>Recipient ' + recipient_id + ':</b>';
      name.innerHTML = '<table border="0">' +
'  <tr>' +
'    <td align="right">Name:</td>' +
'    <td><input type="text" id="recipName_' + recipient_id + '" name="recipName_' + recipient_id + '" size="30" value=""/></td>' +
'  </tr>' +
'  <tr>' +
<?PHP
if ( $theDropbox->authorizedUser() ) {
?>
'    <td align="right">Email or Username:</td>' +
'    <td>' +
'      <input type="text" id="recipEmail_' + recipient_id + '" name="recipEmail_' + recipient_id + '" size="30" value=""/>' +
'      <input type="hidden" name="recipient_' + recipient_id + '" value="' + recipient_id + '"/>' +
'    </td>' +
<?PHP
} else {
?>
'    <td align="right">Email:</td>' +
'    <td>' +
'      <input type="text" id="recipEmail_' + recipient_id + '" name="recipEmail_' + recipient_id + '" size="16" value=""/>@<?=$theDropbox->dropboxDomain();?>' +
'      <input type="hidden" name="recipient_' + recipient_id + '" value="' + recipient_id + '"/>' +
'    </td>' +
<?PHP
}
?>
'  </tr>' +
'</table>';
    }
  }
  return 1;
}

function validateForm()
{
  if ( document.getElementById("file_1").value == "" ) {
    alert("Please select at least one file in Section 3 before submitting.");
    return false;
  }
  if ( document.dropoff.senderName.value == "" ) {
    alert("Please enter your name in Section 1 before submitting.");
    return false;
  }
  if ( document.dropoff.senderEmail.value == "" ) {
    alert("Please enter your email address in Section 1 before submitting.");
    return false;
  }
  if ( document.dropoff.recipEmail_1.value == "" ) {
    alert("Please enter the recipient's email address in Section 2 before submitting.");
    return false;
  }
  makeVisible();
  return true;
}
//-->
</script>
<?PHP
    $theDropbox->HTMLStartBody("dropoff.senderName");
    
    if ( $theDropbox->authorizedUser() ) { 
      ?><h5>This web page will allow you to drop-off (upload) one or more files for anyone (either
  <?=$theDropbox->authUserShortDesc()?> or others).  The recipient will receive an email containing the
  information you enter below and instructions for downloading the file.</h5><?PHP
    } else {
      ?><h5>You must be logged in to use this feature! Please follow <a href="<?=$NSSDROPBOX_URL?>"><?=$NSSDROPBOX_URL?></a> and log in<h5>
	  <!--<h5>This web page will allow you to drop-off (upload) one or more files for a <?=$theDropbox->authUserFormalDesc()?> user. The
  recipient will receive an automated email containing the information you enter below and
  instructions for downloading the file.  Your IP address will be logged and
  sent to the recipient, as well, for identity confirmation purposes.</h5>--><?PHP 
      exit();
    }
?>
<table border="0"><tr valign="top">
  <td>
    <form name="dropoff" id="dropoff" method="post" action="<?=$NSSDROPBOX_URL?>dropoff.php" enctype="multipart/form-data" onsubmit="return validateForm();">
      <input type="hidden" name="Action" value="dropoff"/>
      <!-- input type="hidden" name="MAX_FILE_SIZE" value="<?=intval($theDropbox->maxBytesForFile());?>"/-->
      <table border="0" cellpadding="4">
      
        <tr><td width="100%">
          <table class="UD_form" width="100%" cellpadding="4">
            <tr class="UD_form_header"><td colspan="2">
              1. Information about the Sender
            </td></tr>
            <tr>
              <td align="right"><b>Your name:</b></td>
              <td width="60%">
<?PHP
    if ( $theDropbox->authorizedUser() && ($theFullName = $theDropbox->authorizedUserData("cn")) ) {
      echo "<input type=\"hidden\" id=\"senderName\" name=\"senderName\" value=\"".$theFullName."\"/>".$theFullName;
    } else {
      ?><input type="text" id="senderName" name="senderName" size="30" value=""/><font style="font-size:9px">(required)</font><?PHP
    }
?>
              </td>
            </tr>
            <tr>
              <td align="right"><b>Your organization:</b></td>
              <td width="60%"><input type="text" name="senderOrganization" size="30" value=""/></td>
            </tr>
            <tr>
              <td align="right"><b>Your email address:</b></td>
              <td width="60%">
<?PHP
    if ( $theDropbox->authorizedUser() && ($theEmail = strtolower($theDropbox->authorizedUserData("mail"))) ) {
      echo "<input type=\"hidden\" name=\"senderEmail\" value=\"".$theEmail."\"/>".$theEmail;
    } else {
      ?><input type="text" name="senderEmail" size="30" value=""/><font style="font-size:9px">(required)</font><?PHP
    }
?>
              </td>
            </tr>
            <tr>
              <td colspan="2" align="right"><input type="checkbox" name="confirmDelivery" checked="checked"/>Send an email to me when the recipient picks-up the file(s).</td>
            </tr>
            
          </table>
        </td></tr>
      
        <tr><td width="100%">
          <table id="recipient_matrix" class="UD_form" width="100%" cellpadding="4">
            <tr class="UD_form_header"><td colspan="2">
              2.  Information about the Recipient
            </td></tr>
            <tr>
              <td><b>Recipient 1:</b></td>
              <td>
                <?PHP generateEmailTable($theDropbox); ?>
              </td>
            </tr>
            <tr><td colspan="2" align="right"><img src="images/<?=( defined('NSSTHEME') ? NSSTHEME : "default" )?>/add-button.png" onclick="addRecipient();" onmouseover="document.body.style.cursor = 'pointer';" onmouseout="document.body.style.cursor = 'auto';" alt="[add recipient]"/></td></tr>
            <tr><td colspan="2" align="center"><hr></td></tr>
            <tr><td colspan="2" align="left"><b>Upload a CSV or text file containing addresses:</b></td></tr>
            <tr>
              <td>&nbsp;</td>
              <td align="left"><input type="file" name="recipient_csv" size="50"/></td>
            </tr>
            </tr>
          </table>
        </td></tr>
      
        <tr><td width="100%">
          <table id="file_matrix" class="UD_form" width="100%" cellpadding="4">
            <tr class="UD_form_header"><td colspan="2">
              3. Choose the File(s) you would like to Upload
            </td></tr>
            <tr>
              <td><b>File 1:</b></td>
              <td><input type="file" name="file_1" id="file_1" size="50" onchange="addFile();"/></td>
            </tr>
            <tr>
              <td>&nbsp;</td>
              <td>Description:&nbsp;<input type="text" name="desc_1" id="desc_1" size="30"/>&nbsp;Video:&nbsp;<input type="checkbox" name="video_1" id="video_1" checked="true"/></td>
            </tr>
            <tr class="footer"><td colspan="2" align="center">
				<input type="submit" value="Drop-off the File(s)"/><br/><img name="loaderImage" style="visibility:hidden" src="images/loader.gif" alt="loading"/>
            </td></tr>
          </table>
        </td></tr>
      </table>
    </form>
  </td>
  <td align="center">
    <br>
    <br>
    <div style="width:300px;padding:4px;border:2px solid #C01010;background:#FFF0F0;color:#C01010;text-align:justify;">
      <b>PLEASE NOTE</b><br>
      <br>
      Files uploaded to the Dropbox are not scanned for viruses.  Exercise the same degree of caution as you would with any other file you download.  Users are also <b>strongly encouraged</b> to encrypt any files containing sensitive information (e.g. personal non-public information, PNPI) before sending them via the Dropbox!  See <a href="http://www.udel.edu/pnpi/tools/" style="color:#C01010;">this page</a> for information on encryption.<br>
      <br>
      If you are attaching a file containing the dropoff recipients' addresses, the file should be:
      <ul>
        <li>A plain text file with a single email address per line</li>
        <li>A spreadsheet in CSV format (e.g. exported by Excel)</li>
      </ul>
    </div>
  </td>
</tr></table>
<?PHP
  }
  
  $theDropbox->HTMLWriteFooter();
}

?>
