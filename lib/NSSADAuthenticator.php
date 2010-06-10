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
// $Id: NSSADAuthenticator.php 50 2008-07-09 15:54:07Z frey $
//

class NSSADAuthenticator extends NSSAuthenticator {
	private $_adServer= NULL;
	private $_adDomain= NULL;
	private $_mailDomain= NULL;

        public function __getemail( $uname )
        {
          define(PERSON_FLAT_FILE, "/var/www/person/person.txt");
          $_person_fh = fopen(PERSON_FLAT_FILE, "r");
          $_entry = NULL;
          $_entry_split = NULL;
          $_email = $uname . "@reading.ac.uk";
          if ($_person_fh) {
            while (!feof($_person_fh)) {
              $_entry = rtrim(fgets($_person_fh, 256),"\n");
              $_entry_split = explode(":", $_entry);
              if ($uname == $_entry_split[0]) {
                $_email = $_entry_split[5];
                if (substr($_email, -1) == ".") {
                  $_email = strtolower(substr($_email, 0, -1));
                } else if (strpos($_email, "@")) {
                  $_email = strtolower($_email) . ".reading.ac.uk";
                } else {
                  $_email = strtolower($_email) . "@reading.ac.uk";
                }
                break;
              }
            }
            fclose($_person_fh);
          } else {
            NSSError('Unable to open person database.','Person Error');
          }
          return $_email;
        }
        
	public function __construct( $prefs )
	{
    if ( $prefs['authADAdmins'] && (! $prefs['authAdmins']) ) {
      $prefs['authAdmins'] = $prefs['authADAdmins'];
    }

    parent::__construct($prefs);
    
		$this->_adServer= trim($prefs['authADServer']);
		$this->_adDomain=  trim($prefs['authADDomain']);
		$this->_mailDomain=  trim($prefs['dropboxDomain']);
	}

	public function description()
	{
    $desc = 'NSSADAuthenticator {
  domain:  '.$this->_adDomain.'
  server:  '.$this->_adServer.'
'.parent::description().'
}';
    return $desc;
	}

	public function validUsername ( $uname, &$response )
	{
		$result= FALSE;

//		if ( preg_match('/([a-z][^.]+\.[0-9]+)/', strtolower($uname),$pieces) )
		if ( preg_match('/([a-z]{2}[a-z0-9]{4,6})/', strtolower($uname),$pieces) )
		{
      $response = array(
          'uid'   => $pieces[1],
          'mail'  => $this->__getemail($pieces[1]),
          'cn'    => $pieces[1].'@'.$this->_mailDomain
      );
			$result= TRUE;
			
      //  Chain to the super class for any further properties to be added
      //  to the $response array:
      parent::validUsername($uname,$response);
		}
		return $result;
	}

	public function authenticate( $uname, $password, &$response )
	{
		$result= FALSE;
      if ( $ldapConn = @ldap_connect($this->_adServer) ) {
        //  Set the protocol to 3 only:
        ldap_set_option($ldapConn,LDAP_OPT_PROTOCOL_VERSION,3);
        if ( $password && ( $ldapBind = @ldap_bind($ldapConn, $uname . "@" . $this->_adDomain, $password) ) ) {
          $response = array(
              'uid'   => strtolower($uname),
              'mail'  => $this->__getemail($uname),
              'cn'    => strtolower($uname).'@'.$this->_mailDomain
          );
          ldap_unbind($ldapConn);
          $result= TRUE;
				
        //  Chain to the super class for any further properties to be added
        //  to the $response array:
        parent::authenticate($uname,$password,$response);
	}
      } else {
            NSSError('Unable to connect to the AD servers; could not authenticate user.','AD Error');
      }
		return $result;
	}

}

?>
