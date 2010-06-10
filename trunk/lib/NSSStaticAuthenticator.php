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
// $Id: NSSStaticAuthenticator.php 50 2008-07-09 15:54:07Z frey $
//

/*!
  @class NSSStaticAuthenticator
  
  Used for testing purposes.  This authenticator has a single pseudo-user associated with it.
  There are no attributes necessary.
  
    Username:       test
    Password:       changeme
    Canonical Name: Test User
    Email:          test@nowhere.org
  
  I've added no comments to the class because there's not much to say!
*/

define('NSS_STATIC_UID','test');

class NSSStaticAuthenticator extends NSSAuthenticator {

  public function __construct(
    $prefs
  )
  {
    $adjPrefs = $prefs;
    $adjPrefs['authAdmins'] = array( NSS_STATIC_UID );
    
    parent::__construct($adjPrefs);
  }


  
  public function description()
  {
    return 'NSSStaticAuthenticator {
'.parent::description().'
}';
  }
  
  

  public function validUsername(
    $uname,
    &$response
  )
  {
    if ( $uname == NSS_STATIC_UID ) {
      $response = array( 'uid' => NSS_STATIC_UID , 'mail' => NSS_STATIC_UID.'@nowhere.org' , 'cn' => 'Test User' );
      
      //  Chain to the super class for any further properties to be added
      //  to the $response array:
      parent::validUsername($uname,$response);
      
      return TRUE;
    }
    return FALSE;
  }
  
  
  
  public function authenticate(
    $uname,
    $password,
    &$response
  )
  {
    if ( ($uname == NSS_STATIC_UID) && ($password == 'changeme') ) {
      $response = array( 'uid' => NSS_STATIC_UID , 'mail' => NSS_STATIC_UID.'@nowhere.org' , 'cn' => 'Test User' );
      
      //  Chain to the super class for any further properties to be added
      //  to the $response array:
      parent::authenticate($uname,$password,$response);
      
      return TRUE;
    }
    return FALSE;
  }

}

?>