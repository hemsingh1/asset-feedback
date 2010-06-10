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
// $Id: index.php 50 2008-07-09 15:54:07Z frey $
//

//
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
include "preferences.php";

include_once(NSSDROPBOX_LIB_DIR."NSSDropbox.php");

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  //
  // This page handles several actions.  By default, it simply
  // presents whatever "main menu" is appropriate.  This page also
  // handles the presentation of the login "dialog" and subsequently
  // the logout facility.
  //
  switch ( $_GET['action'] ) {
  
    case 'login': {
      $theDropbox->HTMLWriteHeader();
      
      if ( $_SERVER['HTTPS'] ) {
        $theDropbox->HTMLStartBody("login.uname");
?>
<br>
<br>
<br>
<center>
  <form name="login" method="post" action="<?=$NSSDROPBOX_URL?>">
  <table class="UD_form" cellpadding="4">
    <tr class="UD_form_header"><td colspan="2">
      <?=LookupInDict('Authentication')?>
    </td></tr>
    <tr>
      <td align="right"><b>Your <?=LookupInDict('Username')?>:</b></td>
      <td><input type="text" id="uname" name="uname" size="8" value="<?=$theDropbox->authorizedUser()?>"/></td>
    </tr>
    <tr>
      <td align="right"><b>Your Password:</b></td>
      <td><input type="password" name="password" size="8" value="" autocomplete="off"/></td>
    </tr>
    <tr class="footer"><td colspan="2" align="center">
      <input type="submit" name="login" value="Login"/>
    </td></tr>
  </table>
  </form>
</center>
<br>
<br>
<?PHP
      } else {
        $theDropbox->HTMLStartBody();
        $altURL = preg_replace('/^http[s]?/','https',$NSSDROPBOX_URL);
        ?><BR><BR><?PHP
        NSSError("The login feature is only available using encrypted web access.  If your system administrator has configured secure HTTP for this web server, <A HREF=\"$altURL\">this link should work</A>.","Secure HTTP Session Required");
        ?><BR><BR><?PHP
      }
      break;
    }
    
    case 'logout': {
      $theDropbox->logout();
      $theDropbox->HTMLWriteHeader();
      ?><meta http-equiv="refresh" content="5;URL=<?=$NSSDROPBOX_URL?>"><?PHP
      $theDropbox->HTMLStartBody();
      ?>
<h4>You have been logged out.</h4>
<h5>
For better security, you should also exit this browser, or at least close
this browser window.  You will be automatically redirected to the main menu momentarily.</h5>
      <?PHP
      break;
    }
    default: {
      $theDropbox->HTMLWriteHeader();
      $theDropbox->HTMLStartBody();
      $theDropbox->HTMLMenu();
      break;
    }
  }
  $theDropbox->HTMLWriteFooter();
}

?>
