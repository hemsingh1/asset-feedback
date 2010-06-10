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
// $Id: NSSAuthenticator.php 50 2008-07-09 15:54:07Z frey $
//

/*!
  @class NSSAuthenticator
  
  Abstract class interface to support basic user authentication.
  Authentication methods are implemented as concrete classes that
  conform to this interface.
*/
abstract class NSSAuthenticator {

  private $_adminList = NULL;

  /*!
    @function __construct
    
    Constructor function for the concrete classes.  The $prefs
    array should contain any of the attributes necessary to the
    individual authenticators.
  */
  public function __construct(
    $prefs
  )
  {
    if ( $prefs['authAdmins'] ) {
      $this->_adminList = $prefs['authAdmins'];
    }
  }
  
  /*!
    @function description
    
    Returns a textual description of the authenticator instance,
    suitable for debugging purposes.
  */
  public function description()
  {
    $desc = 'NSSAuthenticator {
  admins:  (
';
    if ( $this->_adminList && count($this->_adminList) ) {
      foreach ( $this->_adminList as $uname ) {
        $desc .= '          '.$uname."\n";
      }
    }
    return $desc.'  )
}';
  }
  
  /*!
    @function validUsername
    
    Returns TRUE if $uname is a valid username within the context of
    the authenticator.  On return, the variable referenced by
    $response will then contain an array, keyed by LDAP-style
    attribute names, with the various attributes for the user.
    
    Returns FALSE in case of any error.
  */
  public function validUsername(
    $uname,
    &$response
  )
  {
    if ( $response && $this->isAdmin($uname) ) {
      $response['grantAdminPriv'] = TRUE;
    }
    return TRUE;
  }
  
  /*!
    @function authenticate
    
    Returns TRUE if $uname is a valid username within the context of
    the authenticator and $password is the correct password for that
    user.  On return, the variable referenced by $response will then
    contain an array, keyed by LDAP-style attribute names, with the
    various attributes for the user.
    
    Returns FALSE in case of any error.
  */
  public function authenticate(
    $uname,
    $password,
    &$response
  )
  {
    if ( $response && $this->isAdmin($uname) ) {
      $response['grantAdminPriv'] = TRUE;
    }
    return TRUE;
  }
  
  /*!
    @function isAdmin
    
  */
  private function isAdmin(
    $uname
  )
  {
    if ( $this->_adminList ) {
      return in_array($uname,$this->_adminList);
    }
    return NULL;
  }
  
}



/*!
  @function NSSAuthenticator
  
  Create an authenticator based upon the contents of the $prefs
  array.  The array should contain (at least) a value keyed by
  the string "authenticator" and having the value:
  
    "LDAP"          NSSLDAPAuthenticator class
    "Static"        NSSStaticAuthenticator class
    "IMAP"          NSSIMAPAuthenticator class
    "AD"            NSSADAuthenticator class
    
  The array should also contain any attributes needed by the target
  class' constructor function.
  
  Returns NULL if an authenticator could not be created.
*/
function NSSAuthenticator(
  $prefs
)
{
	$authenticator= 'NSS' . $prefs['authenticator'] . 'Authenticator';
	if ( file_exists( NSSDROPBOX_LIB_DIR . $authenticator . '.php' ) )
	{
		include_once( NSSDROPBOX_LIB_DIR . $authenticator . '.php');
		return new $authenticator ( $prefs );
	}
	return NULL;
}

?>
