<?php

	/* SVN FILE: $Id$ */
	
	/**
	 * @author Oliver Lillie (aka buggedcom) <publicmail@buggedcom.co.uk>
	 * @package PHPVideoToolkit
	 * @license BSD
	 * @copyright Copyright (c) 2008 Oliver Lillie <http://www.buggedcom.co.uk>
	 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
	 * files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
	 * modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software
	 * is furnished to do so, subject to the following conditions:  The above copyright notice and this permission notice shall be
	 * included in all copies or substantial portions of the Software.
	 *
	 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
	 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
	 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
	 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
	 */

	echo '<strong>This example shows you how to watermark a video. Please note; that in order to watermark a video FFmpeg has to have been compiled with vhooks enabled.</strong><br />';
	
// 	load the examples configuration
	require_once 'example-config.php';
	
// 	require the library
	require_once '../phpvideotoolkit.php';
	
// 	please replace xxxxx with the full absolute path to the files and folders
// 	also please make the $thumbnail_output_dir read and writeable by the webserver
	
// 	temp directory
	$tmp_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'tmp/';
	
//	input movie files
	$video_to_process = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed/cat.mpeg';
	$watermark 		  = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'watermark.png';
// 	$watermark 		  = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'watermark.gif';

//	output files dirname has to exist
	$video_output_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/videos/';
	
//	output files dirname has to exist
	$thumbnail_output_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/thumbnails/';
	
//	log dir
	$log_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'logs/';
	
// 	start PHPVideoToolkit class
	$toolkit = new PHPVideoToolkit($tmp_dir);

	$use_vhook = !isset($_GET['gd']) || $_GET['gd'] == '0';
	if($use_vhook)
	{
// 		check to see if vhook support is enabled
		echo '<strong>Testing for vhook support...</strong><br />';
		if(!$toolkit->hasVHookSupport())
		{
			echo 'You FFmpeg binary has NOT been compiled with vhook support, you can not watermark video, you can however watermark image outputs.<br /><a href="?gd=1">Click here</a> to run the watermark demo on images only.<br />';
			exit;
//<-		exits 		
		}
		echo 'You FFmpeg binary has been compiled with vhook support.<br /><br />';
	}
	else
	{
		echo '<strong>GD watermarking only...</strong><br />';
		echo 'Your FFmpeg binary has NOT been compiled with vhook support and we are only testing automated watermarking of images via GD now.<br /><a href="?gd=0">Click here</a> to go back to the vhook watermarking demo.<br /><br />';
	}
	
// 	set ffmpeg class to run silently
	$toolkit->on_error_die = FALSE;
	
// 	get the filename parts
	$filename = basename($video_to_process);
	$filename_minus_ext = substr($filename, 0, strrpos($filename, '.'));
	
// 	set the input file
	$ok = $toolkit->setInputFile($video_to_process);
// 	check the return value in-case of error
	if(!$ok)
	{
// 		if there was an error then get it 
		echo '<b>'.$toolkit->getLastError()."</b><br />\r\n";
		exit;
	}
	
// 	set the output dimensions
	$toolkit->setVideoOutputDimensions(PHPVideoToolkit::SIZE_SAS);
	
// 	are we vhooking the videos?
	if($use_vhook)
	{
		$toolkit->addWatermark($watermark);
		$ok = $toolkit->setOutput($video_output_dir, $filename_minus_ext.'-watermarked.3gp', PHPVideoToolkit::OVERWRITE_EXISTING);
	}
// 	or just outputting images with watermarks?
	else
	{
		$toolkit->addGDWatermark($watermark, array('x-offset'=>-15, 'y-offset'=>-15, 'position'=>'center-middle'));
// 		extract a single frame
		$toolkit->extractFrame('00:00:03.5');
		$ok = $toolkit->setOutput($thumbnail_output_dir, $filename_minus_ext.'-watermarked.jpeg', PHPVideoToolkit::OVERWRITE_EXISTING);
	}
	
// 	set the output details
// 	check the return value in-case of error
	if(!$ok)
	{
// 		if there was an error then get it 
		echo '<b>'.$toolkit->getLastError()."</b><br />\r\n";
		exit;
	}
	
// 	execute the ffmpeg command
	$result = $toolkit->execute(false, true);
	
// 	get the last command given
// 	$command = $toolkit->getLastCommand();
// 	echo $command."<br />\r\n";
	
// 	check the return value in-case of error
	if($result !== PHPVideoToolkit::RESULT_OK)
	{
// 		move the log file to the log directory as something has gone wrong
		$toolkit->moveLog($log_dir.$filename_minus_ext.'.log');
// 		if there was an error then get it 
		echo '<b>'.$toolkit->getLastError()."</b><br />\r\n";
		$toolkit->reset();
		exit;
	}
	
	$file = array_shift($toolkit->getLastOutput());
	$filename = basename($file);
	if($use_vhook)
	{
		echo 'Video watermarked... <a href="process/videos/'.$filename.'"><b>'.$filename.'</b></a><br />'."\r\n";
	}
	else
	{
		echo 'Frame watermarked... <b>'.$filename.'</b><br />'."\r\n";
// 		$files = $toolkit->getLastOutput();
// 		foreach($files as $key=>$file)
// 		{
// 			echo '<img src="processed/thumbnails/'.$file.'" alt="" border="0" /> ';
// 		}
		echo '<img src="processed/thumbnails/'.$filename.'" alt="" border="0" /> ';
	}

// 	reset 
	$toolkit->reset();
		
	
