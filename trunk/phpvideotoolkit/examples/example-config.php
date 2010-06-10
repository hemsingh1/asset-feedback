<?php

	/* SVN FILE: $Id$ */

// 	define the paths to the required binaries
// 	define('PHPVIDEOTOOLKIT_FFMPEG_BINARY', '/usr/local/bin/ffmpeg');
// 	define('PHPVIDEOTOOLKIT_FLVTOOLS_BINARY', '/usr/bin/flvtool2');
// 	define('PHPVIDEOTOOLKIT_MENCODER_BINARY', '/usr/local/bin/mencoder'); // only required for video joining
// 	define('PHPVIDEOTOOLKIT_FFMPEG_WATERMARK_VHOOK', '/usr/local/lib/vhook/watermark.dylib'); // only required for video wartermarking
	define('PHPVIDEOTOOLKIT_FFMPEG_BINARY', 'xxxx');
	define('PHPVIDEOTOOLKIT_FLVTOOLS_BINARY', 'xxxx');
	define('PHPVIDEOTOOLKIT_MENCODER_BINARY', 'xxxx'); // only required for video joining
	define('PHPVIDEOTOOLKIT_WATERMARK_VHOOK', 'xxxx'); // only required for video wartermarking
	
// 	define the absolute path of the example folder so that the examples only have to be edited once
// 	REMEMBER the trailing slash
	define('PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH', dirname(__FILE__).DIRECTORY_SEPARATOR);

	if(PHPVIDEOTOOLKIT_FFMPEG_BINARY == 'xxxx' || PHPVIDEOTOOLKIT_FLVTOOLS_BINARY == 'xxxx' || PHPVIDEOTOOLKIT_MENCODER_BINARY == 'xxxx' || PHPVIDEOTOOLKIT_FFMPEG_WATERMARK_VHOOK == 'xxxx' || PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH == 'xxxx')
	{
		die('Please open examples/example-config.php to set your servers values.');
//<-	exits 		
	}
	
	if(!is_file(PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed'.DIRECTORY_SEPARATOR.'cat.mpeg'))
	{
		echo 'Please note that this example requires demo files. If you have not got these demo files you can download them from <a href="http://www.buggedcom.co.uk/ffmpeg">here</a>.<br /><br />';
	}
	else
	{
		echo '<br />';
	}

	
	