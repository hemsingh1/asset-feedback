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
// $Id: NSSIMAPAuthenticator.php 50 2008-07-09 15:54:07Z frey $
//

class NSSIMAPAuthenticator extends NSSAuthenticator {
	private $_imapServer= NULL;
	private $_imapDomain= NULL;

	public function __construct( $prefs )
	{
    if ( $prefs['authIMAPAdmins'] && (! $prefs['authAdmins']) ) {
      $prefs['authAdmins'] = $prefs['authIMAPAdmins'];
    }
    parent::__construct($prefs);
    
		$this->_imapServer= trim($prefs['authIMAPServer']);
		$this->_imapDomain=  trim($prefs['dropboxDomain']);
	}

	public function description()
	{
    $desc = 'NSSIMAPAuthenticator {
  domain:  '.$this->_imapDomain.'
  server:  '.$this->_imapServer.'
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
          'mail'  => $pieces[1].'@'.$this->_imapDomain,
          'cn'    => $pieces[1].'@'.$this->_imapDomain
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

		$mbox = @imap_open($this->_imapServer, $uname, $password);
		if ($mbox)
		{
			$minfo = @imap_status($mbox, $this->_imapServer, SA_MESSAGES);
			if ($minfo)
			{
        $response = array(
            'uid'   => strtolower($uname),
            'mail'  => strtolower($uname).'@'.$this->_imapDomain,
            'cn'    => strtolower($uname).'@'.$this->_imapDomain
        );
				$result= TRUE;
				
        //  Chain to the super class for any further properties to be added
        //  to the $response array:
        parent::authenticate($uname,$password,$response);
			}
		}
		@imap_close($mbox);
		return $result;
	}

}

?>
