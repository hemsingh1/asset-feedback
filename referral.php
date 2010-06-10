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
// $Id: referral.php 50 2008-07-09 15:54:07Z frey $
//

//
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
include "preferences.php";

include_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

//

function additionalFormContent(
  $keysAndValues,
  $keyPrefix,
  &$mimeType,
  &$extension,
  $format = null
)
{
  $content = '';
  $rawKey = $rawValue = true;
  switch ( strtolower($format) ) {
    
    case 'xml': {
      $mimeType = 'text/xml';
      $extension = 'xml';
      $printFormat = "  <%s>%s</%1\$s>\n";
      $content = "<?xml version=\"1.0\" ?>\n<form-data>\n";
      $rawValue = false;
      break;
    }
    
    case 'html': {
      $mimeType = 'text/html';
      $extension = 'html';
      $printFormat = "      <tr><td><b>%s</b></td><td>%s</td></tr>\n";
      $content = <<<EOT
<html>
  <body>
    <table border="1">

EOT
      ;
      $rawValue = $rawKey = false;
      break;
    }
    
    case 'text':
    default: {
      $mimeType = 'text/plain';
      $extension = 'txt';
      $printFormat = "%s = %s\n";
      break;
    }
    
  }
  foreach ( $keysAndValues as $key => $value ) {
    if ( strpos($key,$keyPrefix) === 0 ) {
      $key = substr($key,strlen($keyPrefix));
      $content .= sprintf(
                      $printFormat,
                      ( $rawKey ? $key : htmlentities($key) ),
                      ( $rawValue ? $value : htmlentities($value) )
                    );
    }
  }
  switch ( strtolower($format) ) {
    
    case 'xml': {
      $content .= "</form-data>\n";
      break;
    }
    
    case 'html': {
      $content .= <<<EOT
    </table>
  </body>
</html>

EOT
      ;
      break;
    }
    
  }
  return $content;
}

//

$success = false;

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) { 
  if ( $_POST['Action'] == "dropoff" ) {

    // UoR - verify recipient hash
    /*$recipIndex=1;
    $recipString=$theDropbox->secretForReferral();
    while ( array_key_exists('recipient_'.$recipIndex,$_POST) ) {
      $recipString = $recipString . ":" . $_POST['recipName_'.$recipIndex] . ":" . $_POST['recipEmail_'.$recipIndex];
      $recipIndex++;
    }
    if ( md5($recipString) == $_POST['recipientMD5'] ) {*/
	
	//The sender is the receiver here!!
	$_POST['recipient_1'] = "1";
	$_POST['recipName_1'] = "Video Dropbox";
	$_POST['recipEmail_1'] = $_POST['senderEmail'];

    //
    // Posted form data indicates that a dropoff form was filled-out
    // and submitted; if posted from data is around, creating a new
    // dropoff instance creates a new dropoff using said form data.
    //
    if ( $theDropoff = new NSSDropoff($theDropbox) ) {
      if ( ! $theDropoff->formInitError() ) {
        if ( $_POST['affPrefix'] ) {
          $extraContent = additionalFormContent(
                                $_POST,
                                $_POST['affPrefix'],
                                $mimeType,
                                $extension,
                                $_POST['affFormat']
                              );
          $success = $theDropoff->addFileWithContent(
                            $extraContent,
                            'AdditionalFormData.' . $extension,
                            'Non-dropbox related form data collected from this form submission.',
                            $mimeType
                          );
          if ( ! $success ) {
            $errorMessage = 'Additional form data could not be attached to the files you submitted; the person maintaining this form will still receive the files.';
          }
        } else {
          $success = true;
        }
      } else {
        $errorMessage = $theDropoff->formInitError();
      }
    }
   /*} else {
     $errorMessage = 'md5(' . $recipString . ') =' . md5($recipString) . '!= recipientMD5 = ' . $_POST['recipientMD5'];
   }*/
  }
}

if ( $success ) {
  $redirectURL = $_POST['affSuccessURL'];
} else if ( $_POST['affFailureURL'] ) {
  $redirectURL = $_POST['affFailureURL'] . '?message=' . urlencode($errorMessage);
}

if ( ! $redirectURL ) {
  $theDropbox->HTMLWriteHeader();
  $theDropbox->HTMLStartBody();
?>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <center>
      
<?PHP
  if ( $success ) {
    print '<h2>Your form was successfully dropped-off!</h2>';
  } else {
    print '<h2>Unable to drop-off your form; please try again or contact the help desk.</h2>';
  }
?>
    </center>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
<?PHP
  $theDropbox->HTMLWriteFooter();
} else {
?>

<html>
  <head>
    <meta http-equiv="refresh" content="0;URL=<?=$redirectURL?>">
  </head>
  <body>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <center>
      You should be redirected momentarily; if not, click <a href="<?=$redirectURL?>">this link</a>.
    </center>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
    <br/>
  </body>
</html>

<?PHP
}

?>
