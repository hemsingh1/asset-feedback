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
// $Id: config.php 64 2008-07-21 13:09:38Z frey $
//

$webPath = preg_replace('/\/[^\/]*$/','',$_SERVER['SCRIPT_FILENAME']);
$writeableWebPath = is_writeable($webPath);

if ( $_POST['NSSDROPBOX_BASE_DIR'] ) {
  $basePath = htmlentities($_POST['NSSDROPBOX_BASE_DIR']);
} else {
  $basePath = preg_replace('/\/[^\/]*\/[^\/]*$/','',$_SERVER['SCRIPT_FILENAME']);
}
$validBasePath = is_dir($basePath);

if ( $_POST['NSSDROPBOX_LIB_DIR'] ) {
  $libPath = htmlentities($_POST['NSSDROPBOX_LIB_DIR']);
} else {
  $libPath = $basePath.'/lib';
}
$validLibPath = ( is_dir($libPath) && file_exists($libPath.'/NSSDropbox.php') );

if ( $_POST['NSSDROPBOX_DATA_DIR'] ) {
  $dataPath = htmlentities($_POST['NSSDROPBOX_DATA_DIR']);
} else {
  $dataPath = $basePath.'/data';
}
$validDataPath = ( is_dir($dataPath) && is_writeable($dataPath) );

if ( $_POST['dropboxDomain'] ) {
  $dropboxDomain = $_POST['dropboxDomain'];
} else {
  if ( preg_match('/.*\.([^\.]+\..+)$/',$dropboxDomain = exec("hostname"),$parts) ) {
    $dropboxDomain = $parts[1];
  }
}

if ( file_exists($libPath.'/NSSUtils.php') ) {
  include_once($libPath.'/NSSUtils.php');
  $libIsValid = TRUE;
}

$allValid = ( $writeableWebPath && $validBasePath && $validLibPath && $validDataPath );

//
// This script facilitates the creation of an initial preferences
// file via a sweet web-based form.
//

$theme =  ( $_POST['theme'] ? $_POST['theme'] : 'default' );

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/html4/loose.dtd">
<HTML>
  <HEAD>
    <TITLE>Dropbox - Initial Configuration</TITLE>
    <LINK REL="stylesheet" TYPE="text/css" HREF="css/<?=$theme?>.css">
  </HEAD>
  <BODY>
    <TABLE CLASS="UD_header" WIDTH="100%">
      <TR VALIGN="TOP">
        <TD ID="UD_header_left" ROWSPAN="2">&nbsp;</TD>
        <TD ID="UD_header_top" ALIGN="RIGHT">&nbsp;</TD>
      </TR>
      <TR>
        <TD ID="UD_header_title">Dropbox Configuration&nbsp;<IMG SRC="images/unlocked.png"></TD>
      </TR>
    </TABLE>
    
<!-- Begin page content -->
    <DIV CLASS="content">
    
<?PHP
$prefs = array(
            'dropboxDomain'         => array( TRUE , $dropboxDomain ),
            'dropboxDirectory'      => array( $validDataPath , 'NSSDROPBOX_DATA_DIR."dropoffs"' ),
            'dropboxDatabase'       => array( $validDataPath , 'NSSDROPBOX_DATA_DIR."dropbox.sqlite"' ),
            'logFilePath'           => array( $validDataPath , 'NSSDROPBOX_DATA_DIR."dropbox.log"' ),
            'numberOfDaysToRetain'  => array( TRUE , 14 ),
            'authenticator'         => array( TRUE , 'Static' ),
            'showRecipsOnPickup'    => array( TRUE , FALSE )
          );

if ( $_POST['dropboxName'] ) {
  $prefs['dropboxName'] = array( TRUE , $_POST['dropboxName'] );
} else {
  $prefs['dropboxName'] = array( FALSE , 'Dropbox Service' );
  $allValid = FALSE;
}

if ( $_POST['authUserFormalDesc'] ) {
  $prefs['authUserFormalDesc'] = array( TRUE , $_POST['authUserFormalDesc'] );
} else {
  $prefs['authUserFormalDesc'] = array( FALSE , 'Authenticated User' );
  $allValid = FALSE;
}

if ( $_POST['authUserShortDesc'] ) {
  $prefs['authUserShortDesc'] = array( TRUE , $_POST['authUserShortDesc'] );
} else {
  $prefs['authUserShortDesc'] = array( FALSE , 'AuthUser' );
  $allValid = FALSE;
}

if ( $_POST['contactInfo'] ) {
  $prefs['contactInfo'] = array( TRUE , $_POST['contactInfo'] );
} else {
  $prefs['contactInfo'] = array( FALSE , 'Dropbox Service (c) 2007' );
  $allValid = FALSE;
}

if ( ($bytesPerDropoff = floatval($_POST['maxBytesForDropoff'])) > 2048.0 ) {
  $prefs['maxBytesForDropoff'] = array( TRUE , $bytesPerDropoff );
} else {
  $prefs['maxBytesForDropoff'] = array( FALSE , 20.0 * 1024.0 * 1024.0 );
  $allValid = FALSE;
}

if ( (($bytesPerFile = floatval($_POST['maxBytesForFile'])) > 1024.0) && ($bytesPerFile <= $bytesPerDropoff) ) {
  $prefs['maxBytesForFile'] = array( TRUE , $bytesPerFile );
} else {
  $prefs['maxBytesForFile'] = array( FALSE , $bytesPerDropoff / 2.0 );
  $allValid = FALSE;
}

if ( $_POST['cookieName'] ) {
  $prefs['cookieName'] = array( TRUE , $_POST['cookieName'] );
} else {
  $prefs['cookieName'] = array( FALSE , 'dropbox-session' );
  $allValid = FALSE;
}

if ( $cookieTTL = intval($_POST['cookieTTL']) ) {
  $prefs['cookieTTL'] = array( TRUE , $cookieTTL );
} else {
  $prefs['cookieTTL'] = array( FALSE , 900 );
  $allValid = FALSE;
}

if ( $allValid && ($_POST['action'] == 'confirm') ) {
  //
  //  Get the directory constants defined now rather than later,
  //  so that we don't create funky files like ``NSSDROPBOX_DATA_DIR."dropbox.log"''
  //  in the www/ directory!
  //
  //  Bug reported (officially) 2008-07-15
  //
  define('NSSDROPBOX_BASE_DIR',$basePath);
  define('NSSDROPBOX_LIB_DIR',$libPath);
  define('NSSDROPBOX_DATA_DIR',$dataPath);

  //
  //  Try to open the preference file for writing:
  //
  if ( $prefFile = fopen($webPath."/preferences.php","w") ) {
    //
    // Now attempt to create any non-existent data directories and files:
    //
    $keepGoing = TRUE;
    // Bug 0001
    eval('$tmpPath = ' . $prefs['dropboxDirectory'][1] . ';');
    if ( ! file_exists($tmpPath) ) {
      if ( ! mkdir($tmpPath) ) {
        NSSError("Unable to create directory: ".$tmpPath,"Configuration Failed");
        $keepGoing = FALSE;
      }
    }
    if ( $keepGoing ) {
      // Bug 0001
      eval('$tmpPath = ' . $prefs['logFilePath'] . ';');
      if ( ! file_exists($tmpPath) ) {
        if ( ! ($fh = fopen($tmpPath,"w")) ) {
          NSSError("Unable to create log file: ".$tmpPath,"Configuration Failed");
          $keepGoing = FALSE;
        } else {
          fwrite($fh,sprintf("%s [%s]: A dropbox is born!\n",strftime("%Y-%m-%d %T"),$prefs['dropboxName'][1]));
          fclose($fh);
        }
      }
      
      if ( $keepGoing ) {
        //
        //  Now let's write the preference file contents:
        //
        fwrite($prefFile,sprintf('
<?PHP
//
// Dropbox 2.1
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

//
// This file was generated by the config.php script
//

define(\'NSSDROPBOX_BASE_DIR\',\'%s/\');
define(\'NSSDROPBOX_LIB_DIR\',\'%s/\');
define(\'NSSDROPBOX_DATA_DIR\',\'%s/\');

//
// Buttons can be skinned by adding a new directory in image/ and
// using its name as the value for NSSTHEME here.  We
// include several button themes in the package:
//
define(\'NSSTHEME\',\'default\');
//define(\'NSSTHEME\',\'flat\');
//define(\'NSSTHEME\',\'flat2\');
//define(\'NSSTHEME\',\'blue\');
//define(\'NSSTHEME\',\'algae\');
//define(\'NSSTHEME\',\'ud\');
//define(\'NSSTHEME\',\'duracell\');

//
// Preferences are stored as a hashed array.  Inline comments
// indicate what everything is for.
//
$NSSDROPBOX_PREFS = array(
',
            $basePath,
            $libPath,
            $dataPath
           )
         );
        
        fwrite($prefFile,"  'dropboxName'          => '".$prefs['dropboxName'][1]."',\n"); unset($prefs['dropboxName']);
        fwrite($prefFile,"  'dropboxDomain'        => '".$prefs['dropboxDomain'][1]."',\n"); unset($prefs['dropboxDomain']);
        fwrite($prefFile,"  'dropboxDirectory'     => ".$prefs['dropboxDirectory'][1].",\n"); unset($prefs['dropboxDirectory']);
        fwrite($prefFile,"  'dropboxDatabase'      => ".$prefs['dropboxDatabase'][1].",\n"); unset($prefs['dropboxDatabase']);
        fwrite($prefFile,"  'logFilePath'          => ".$prefs['logFilePath'][1].",\n"); unset($prefs['logFilePath']);
        fwrite($prefFile,"  'numberOfDaysToRetain' => ".$prefs['numberOfDaysToRetain'][1].",\n"); unset($prefs['numberOfDaysToRetain']);
        fwrite($prefFile,"  'showRecipsOnPickup'   => ".($prefs['showRecipsOnPickup'][1] ? 'TRUE' : 'FALSE').",\n"); unset($prefs['showRecipsOnPickup']);
        fwrite($prefFile,"  'maxBytesForDropoff'   => ".$prefs['maxBytesForDropoff'][1].",\n"); unset($prefs['maxBytesForDropoff']);
        fwrite($prefFile,"  'maxBytesForFile'      => ".$prefs['maxBytesForFile'][1].",\n"); unset($prefs['maxBytesForFile']);
        
        $iMax = count(array_keys($prefs));
        $i = 1;
        foreach ( $prefs as $key => $value ) {
          fwrite($prefFile,sprintf("  '%s' => '%s'%s\n",$key,$value[1],(($i++ == $iMax) ? '' : ',')));
        }
         
        fwrite($prefFile,');

//
// This global array contains terms that will be replaced automatically
// throughout the interface:
//
$DROPBOX_DICTIONARY = array(
  \'Authentication\'        => \'Authentication\',
  \'Username\'              => \'Username\',
  \'username\'              => \'username\',
  \'username-regex\'        => \'/^([a-z0-9][a-z0-9\_\.]*)$/\'
);
?>'
          );
        fclose($prefFile);
        
        //
        // Finally, attempt to instantiate a NSSDropbox using these prefs,
        // so that the database gets setup:
        //
        include_once('preferences.php');
        include_once($libPath.'/NSSDropbox.php');

        if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
?>
<BR>
<BR>
<BR>
<H2>Congratulations!</H2>
The configuration script was able to successfully configure the Dropbox for you.  You may need to fine-tune the resulting preference file<BR>
<BR>
<CENTER>
  <TT><?=$webPath."/preferences.php"?></TT>
</CENTER>
<BR>
to configure LDAP authentication, for example.<BR>
<BR>
<DIV STYLE="width=90%;padding:4px;border:2px #8F6060 solid;background-color:#FFD0D0;color:#8F6060;">
  <B>S&nbsp;T&nbsp;O&nbsp;P&nbsp;!</B><BR>
  <BR>
  BEFORE YOU PROCEED:<BR>
  <BR>
  At this point <TT><?=$webPath?></TT> is setup with write permissions for your web server user!  It would probably be best to set the directory to be read-only.<BR>
  <BR>
  Also, you would do well to either move or delete this configuration script (<TT><?=$webPath."/config.php"?></TT>) so no one could possibly reconfigure your Dropbox system!
</DIV>
<BR>
<?PHP
          NSSTextButton('Go to the new dropbox!',$NSSDROPBOX_URL);
        } else {
          NSSError("Unable to create a dropbox object; not sure what's wrong!","Configuration Failed");
        }
      }
      
    }
  } else {
    NSSError("Unable to open for writing: $webPath/preferences.php","Configuration Failed");
  }

} else {

?>
<FORM NAME="config" METHOD="POST" ACTION="config.php">
<INPUT TYPE="hidden" NAME="action" VALUE=""/>
<TABLE BORDER="0" CELLPADDING="4">

<?PHP

    if ( ! $writeableWebPath ) {
?>
  <TR STYLE="background-color:#EFE0E0"><TD COLSPAN="3">The directory from which you're serving this page must be writeable by the web server user.  Please fix the permissions on <TT><?=$webPath?></TT> before proceeding.</TD></TR>
<?PHP
    } else {
?>
  <TR STYLE="background-color:#E0EFE0"><TD COLSPAN="3">The directory from which you're serving this page (<TT><?=$webPath?></TT>) is writeable by the web server user.  Good job &mdash; for now!  After generating a preference file you should probably change the permissions to make <TT><?=$webPath?></TT> <I>NOT</I> writeable.</TD></TR>
<?PHP
    }

?>

  <TR><TD COLSPAN="3"><I>The following paths are my best guesses based upon the location of the config.php script itself.  They are most likely correct &mdash; if not, the offending directory will be displayed in red text.</I></TD></TR>
  
  <TR>
    <TD ALIGN="RIGHT"><B>Base Path:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="NSSDROPBOX_BASE_DIR" SIZE="64" VALUE="<?=$basePath?>"<?PHP 
    
    if ( ! $validBasePath ) {
      echo ' STYLE="color:red"';
    }
    
    ?>/></TD>
  </TR>

  <TR>
    <TD ALIGN="RIGHT"><B>Library Path:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="NSSDROPBOX_LIB_DIR" SIZE="64" VALUE="<?=$libPath?>"<?PHP 
    
    if ( ! $validLibPath ) {
      echo ' STYLE="color:red"';
    }
    
    ?>/></TD>
  </TR>

  <TR>
    <TD ALIGN="RIGHT"><B>Data Path:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="NSSDROPBOX_DATA_DIR" SIZE="64" VALUE="<?=$dataPath?>"<?PHP 
    
    if ( ! $validDataPath ) {
      echo ' STYLE="color:red"';
    }
    
    ?>/></TD>
  </TR>
  
  <TR><TD>&nbsp;</TD><TD COLSPAN="2"><INPUT TYPE="SUBMIT" VALUE="Test my paths"/></TD></TR>

  <TR><TD COLSPAN="3"><I>Select a name for your Dropbox service:</I></TD></TR>
  
  <TR>
    <TD ALIGN="RIGHT"><B>Dropbox Name:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="dropboxName" SIZE="64" VALUE="<?=$prefs['dropboxName'][1]?>"/></TD>
  </TR>
  
  <TR><TD COLSPAN="3"><I>Describe the inside users of your Dropbox service:</I></TD></TR>
  
  <TR>
    <TD ALIGN="RIGHT"><B>Formal User Description:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="authUserFormalDesc" SIZE="64" VALUE="<?=$prefs['authUserFormalDesc'][1]?>"/></TD>
  </TR>
  <TR>
    <TD ALIGN="RIGHT"><B>Shortened Form:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="authUserShortDesc" SIZE="64" VALUE="<?=$prefs['authUserShortDesc'][1]?>"/></TD>
  </TR>

  <TR><TD COLSPAN="3"><I>Enter contact information that should be displayed in each page's footer:</I></TD></TR>
  
  <TR>
    <TD ALIGN="RIGHT"><B>Contact Info:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="contactInfo" SIZE="64" VALUE="<?=$prefs['contactInfo'][1]?>"/></TD>
  </TR>

  <TR><TD COLSPAN="3"><I>This is my best guess at the root-level domain for your organization:</I></TD></TR>
  
  <TR>
    <TD ALIGN="RIGHT"><B>Dropbox Domain:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="dropboxDomain" SIZE="64" VALUE="<?=$prefs['dropboxDomain'][1]?>"/></TD>
  </TR>

  <TR><TD COLSPAN="3"><I>Dropbox has many different page-display themes available (you can also create your own):</I></TD></TR>

  <TR>
    <TD ALIGN="RIGHT"><B>Site Theme:</B></TD>
    <TD><SELECT NAME="theme"><?PHP
    
  $themes = glob('css/*.css');
  foreach ( $themes as $themeName ) {
    if ( preg_match('/css\/(.*)\.css/',$themeName,$parts) ) {
      printf('<OPTION%s>%s</OPTION>',($parts[1] == $theme ? " SELECTED" : ""),$parts[1]);
    }
  }
    
    ?></SELECT><INPUT TYPE="submit" VALUE="Test this theme"/></TD>
    <TD><?PHP 
    
  if ( $libIsValid ) {
    NSSTextButton("Sample Button",NULL,"200");
    NSSTextButton("Sample Admin Button",NULL,"200",TRUE);
  } else {
    echo '<FONT COLOR="red"><I>Your library path must be valid before you can preview this theme\'s buttons.</I></FONT>';
  }
  
  ?></TD>
  </TR>

  <TR><TD COLSPAN="3"><I>You should also specify the maximum allowed size (in bytes) of dropoffs and the files in a dropoff:</I></TD></TR>
  
  <TR>
    <TD ALIGN="RIGHT"><B>Maximum Bytes per Dropoff:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="maxBytesForDropoff" SIZE="24" VALUE="<?=$prefs['maxBytesForDropoff'][1]?>"/></TD>
  </TR>
  <TR>
    <TD ALIGN="RIGHT"><B>Maximum Bytes per File:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="maxBytesForFile" SIZE="24" VALUE="<?=$prefs['maxBytesForFile'][1]?>"/></TD>
  </TR>

  <TR><TD COLSPAN="3"><I>The default cookie parameters will probably suffice, but modify them if you wish:</I></TD></TR>
  
  <TR>
    <TD ALIGN="RIGHT"><B>Cookie Name:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="cookieName" SIZE="64" VALUE="<?=$prefs['cookieName'][1]?>"/></TD>
  </TR>
  <TR>
    <TD ALIGN="RIGHT"><B>Cookie Time-To-Live:</B></TD>
    <TD COLSPAN="2"><INPUT TYPE="TEXT" NAME="cookieTTL" SIZE="6" VALUE="<?=$prefs['cookieTTL'][1]?>"/>&nbsp;seconds</TD>
  </TR>

<?PHP

    if ( $allValid ) {
      ?><TR STYLE="background-color:#E0EFE0"><TD COLSPAN="3">All directories appear to be valid.  Click the "Create Preferences" button below to generate a preference file in <TT><?=$webPath?></TT>.
    <TR><TD COLSPAN="3" ALIGN="CENTER"><INPUT TYPE="SUBMIT" VALUE="Create Preferences" onClick="document.config.action.value = 'confirm';document.config.submit();"/></TD></TR><?PHP
    }

?>

</TABLE>
</FORM>

<?PHP

}

?>
    </DIV>
    <TABLE WIDTH="100%" CLASS="UD_footer">
      <TR VALIGN="BOTTOM">
        <TD ID="UD_footer_text">Version 2.1&nbsp;|&nbsp;Copyright &copy 2006</TD><TD ID="UD_footer_right" ROWSPAN="2">&nbsp;</TD>
      </TR>
      <TR>
        <TD ID="UD_footer_bottom">&nbsp;</TD>
      </TR>
    </TABLE>

  </BODY>
</HTML>
