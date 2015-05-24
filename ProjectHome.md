Asset (http://www.reading.ac.uk/asset/) is a JISC funded project which investigates video usage in feedback to students in higher education.

Here you can find the open source code of the project. It has been developed "on-top" of the dropbox2 project which can be found at: http://turin.nss.udel.edu/programming/dropbox2/

This is not intended as a fork of that project, but was initiated at University of Reading to enable easy handling of video and transcoding of videos to flash with resulting embed scripts. This is not a core functionality of the dropbox, hence this site rather than patches.

To install this please follow the guides at http://turin.nss.udel.edu/programming/dropbox2/

Furthermore ffmpeg needs to be installed, because the code uses the phpvideotoolkit from http://sourceforge.net/projects/ffmpeg-phpclass/

The OSplaver is also integrated into the package for convenience, but can easily be exchanged if needed (http://www.osflv.com/).

To enable the embed scripts there need to be a symbolic link from within the directory of the dropbox to the directory where the dropoff data is stored. Please be aware that the embed scripts won't be counted within the statistics of the dropbox as their access had to circumvent the internal streaming of the dropbox. This allow direct access to the files, if the guid of the video is known by users, which introduces a minimal security issue. This can be tolerated as a id/password embedded into a script would be just as open as a guid. As long as the directory of the videos aren't searchable by general users.

The server has been working as a production environment running entirely on an Ubuntu 9.04 server.