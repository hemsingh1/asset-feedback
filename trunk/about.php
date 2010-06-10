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
// $Id: about.php 50 2008-07-09 15:54:07Z frey $
//

//
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
include "preferences.php";

include_once(NSSDROPBOX_LIB_DIR."NSSDropbox.php");

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  $theDropbox->HTMLWriteHeader();
  $theDropbox->HTMLStartBody();
?>

<div style="text-align:justify;"><a href="images/dropbox-icon.pdf"><img src="images/dropbox-icon.png" align="left" border="0" alt="[dropbox]"/></a>
<h4>About the Dropbox...</h4>

Email messages with large attachments can wreak havoc on email servers and end-users' computers.  Downloading such email message can take hours on a slow Internet connection and block any sending or receiving of messages through the duration.  In some cases, the download will fail repeatedly, breaking the recipient's ability to receive mail at all.  Also, Internet email clients add considerably to the size of the file being sent. For example, saving an Outlook Express message with an attachment adds up to 40% to the file's size. To share files larger than 1MB, use the <?=$theDropbox->dropboxName()?> to temporarily make a file (or files) available to another user across the Internet, in a secure and efficient manner.<br/>
<br/>
There are two distinct kinds of users that will be accessing the Dropbox system:  <i>inside</i> users, who are associated with the organization running the service, and <i>outside</i> users, which encompasses the rest of the Internet.<br/>
<br/>
An <i>inside</i> user is allowed to create a drop-off that is to be delivered to anyone, whether he or she be an <i>inside</i> or <i>outside</i> user.  An <i>outside</i> user is only allowed to create a drop-off that is to be delivered to an <i>inside</i> user.  That prompts the question:  what is a drop-off?

<div style="border:1px solid #C0C0C0;background:#E0E0E0;margin:12px;padding:4px;">
  <b><i>drop-off</i></b>:  one or more files uploaded to the Dropbox as a single entity for delivery to a user
</div>

There are two ways in which a user can dropoff multiple files at once:

<ul>
  <li>Attach each file individually on the dropoff page</li>
  <li>Archive and compress the files into a single package and attach the resulting archive file on the dropoff page.  There are many ways to archive and compress files:
    <ul>
      <li>Mac users can select the files in the Finder and <i>Create an archive</i> (see the <i>File</i> menu)</li>
      <li>Windows users can use WinZip</li>
      <li>Linux/Mac/Unix users, give the <tt>tar</tt> utility a try</li>
    </ul>
  </li>
</ul>

<b>Creating a Drop-off</b><br/>
<blockquote style="text-align:justify;border-bottom:2px dotted #C0C0C0;">
When a user creates a drop-off, he or she enters some identifying information about himself or herself (name, organization, and email address); identifying information about the recipient (name and email address); and chooses what files should be uploaded to the Dropbox.  If the files are successfully uploaded, an email is sent to the recipient explaining that a drop-off has been made.  This email also provides a link to access the drop-off, as well as the 16-character passcode that the user must enter to gain access.  Other information (the Internet address and/or hostname from which the drop-off was created, for example) is retained, so that the recipient can verify the identity of the sender.<br/>
<br/>
</blockquote>

<b>Making a Pick-up</b><br/>
<blockquote style="text-align:justify;border-bottom:2px dotted #C0C0C0;">
There are two ways to pick-up files that have been dropped-off:
<ul>
  <li>All users can use the claim ID and passcode provided in the notification email message to access a specific drop-off.</li>
  <li>An inside user, once logged-in to the system, can display a list of all drop-offs waiting for him or her in the Dropbox.  Once logged-in, an inside user is able to access drop-offs without the need for the passcode.</li>
</ul>
When viewing a drop-off, the user will see quite a few things:
<ul>
  <li>The sender and recipient information that the sender entered when the drop-off was created</li>
  <li>The Internet hostname and/or address from which the drop-off was created</li>
  <li>The list of files that were uploaded</li>
  <li>A list of pick-ups that have been made</li>
</ul>
The recipient has 21 days to pick-up the files.  Each night, drop-offs that are older than 21 days are purged from the system.<br/>
<br/>
</blockquote>

Please note that the uploaded files are not scanned for viruses, so the recipient should exercise as much caution in downloading and opening them as is appropriate.  This can be as easy as verifying with the sender mentioned in the notification email that he or she indeed made the drop-off.  One can also check the Internet hostname/address that was logged when the drop-off was created, to be sure that it is appropriate to the sender's Internet domain; IP addresses <i>can</i> be faked, though, so the former identity verification is really the most failsafe.<br/>
<br/>

</div>

<hr/>

<h4>Resumable Downloading of Files</h4>

Some web browsers support <i>resumable downloads</i>.  Imagine this scenario:  you're sitting at your local coffee shop, downloading a 50MB PDF that a student uploaded to Dropbox for you.  Suddenly, someone a few tables away starts watching the latest HD movie trailer (well, attempting to, anyway) and your wireless connection drops &mdash; you were 45MB into the download, and now you have to start over!  Not so, if your browser supports <i>resumable downloads</i>; in which case, the browser requests only the remaining 5MB of the file.<br/>
<br/>
Dropbox 2 features support for the server-side components of <i>resumable download</i> technology under the HTTP 1.1 standard.  If you're a Safari, Opera, or OmniWeb user then rest assured, you can resume interrupted downloads, we've tested it!<br/>
<br/>

<hr/>

<h4>Size Limitations on Uploads</h4>

Being able to upload files larger than 2 GB depends on the browser being used.  The following major browsers have been tested:<br>
<br>

<center>
<table border="1" cellpadding="4" cellspacing="1">
  <tr style="background-color:#2F2F4F;color:white;"><th>&nbsp;</th><th>Browser</th><th>Uploads &gt; 2 GB?</th></tr>
  <tr>
    <td rowspan="6" style="background-color:#2F2F4F;color:white;text-align:center;">M<br/>A<br/>C</td>
    <td><a href="http://www.apple.com/safari">Safari</a> 2.x</td>
    <td style="text-align:center;font-weight:bold;color:#00A000;">YES</td>
  </tr>
  <tr>
    <td><a href="http://www.apple.com/safari">Safari</a> 3.x</td>
    <td style="text-align:center;font-weight:bold;color:#00A000;">YES</td>
  </tr>
  <tr>
    <td><a href="http://www.omnigroup.com/applications/omniweb/">OmniWeb</a> 5.5</td>
    <td style="text-align:center;font-weight:bold;color:#00A000;">YES</td>
  </tr>
  <tr>
    <td><a href="http://www.mozilla.com/en-US/firefox/">Firefox</a> 1.5</td>
    <td style="text-align:center;font-weight:bold;color:#A00000;">NO</td>
  </tr>
  <tr>
    <td><a href="http://www.mozilla.com/en-US/firefox/">Firefox</a> 2.0</td>
    <td style="text-align:center;font-weight:bold;color:#A00000;">NO</td>
  </tr>
  <tr>
    <td><a href="http://www.mozilla.com/en-US/firefox/">Opera</a> 9.x</td>
    <td style="text-align:center;font-weight:bold;color:#A00000;">NO</td>
  </tr>
  <tr>
    <td rowspan="4" style="background-color:#2F2F4F;color:white;text-align:center;">P<br/>C</td>
    <td><a href="http://www.microsoft.com/ie">Internet Explorer</a> 7</td>
    <td style="text-align:center;font-weight:bold;color:#A00000;">NO</td>
  </tr>
  <tr>
    <td><a href="http://www.mozilla.com/en-US/firefox/">Firefox</a> 2.0</td>
    <td style="text-align:center;font-weight:bold;color:#A00000;">NO</td>
  </tr>
  <tr>
    <td><a href="http://www.opera.com/">Opera</a> 9</td>
    <td style="text-align:center;font-weight:bold;color:#00A000;">YES</td>
  </tr>
</table>
</center>
<br/>

The Dropbox software itself has limits on the amount of data that can be uploaded in a single dropoff.  Even for browsers that support uploads larger than 2 GB, dropoffs may not exceed <?=NSSFormattedMemSize($theDropbox->maxBytesForFile())?> per file, or <?=NSSFormattedMemSize($theDropbox->maxBytesForDropoff())?> total for the entire dropoff.<br/>
<br/>
If you are having the following issues when dropping-off or picking-up a large file:
<ul>
  <li>Your browser reports a bad or broken connection after downloading a significant portion of the file</li>
  <li>An error page is displayed that indicates you dropped-off no files</li>
</ul>
then you are most likely connected to the Internet via a connection too slow to move the amount of data in a timely fashion.  Your computer has approximately 10 minutes to fully send or receive a dropoff.

<hr/>

<h4>Using the Dropbox as a Web-form-processing Agent</h4>

<i>Dropobox Referral Service</i> (or DRS) allows you to point a web form containing file upload controls to Dropbox, rather than to the usual email-based delivery CGIs  you may be using. The uploaded files are augmented by one more file (XML, text, or HTML content) containing the additional form data from your page. The usual notification mechanisms in Dropbox allow the recipient(s) of the form to be notified by email with pickup information and the sender to be emailed when the dropoff is picked-up.

<a href="https://turin.nss.udel.edu/wiki/dropbox/doku.php?id=documentation:referral">See this link more information on using this service on your UD web page.</a>

<hr/>


<p style="font-size:10px;" align="left"><a href="http://www.php.net/"><img src="images/PHP5.png" align="right" border="0" alt="[php5]"/></a>
Based upon the original Perl UD Dropbox software written by Doke Scott.  Version 2.0 has been rewritten in PHP5 with an extended feature set by Jeff Frey.
</p>

<?PHP
  $theDropbox->HTMLWriteFooter();
}

?>
