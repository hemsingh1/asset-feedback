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

	echo '<strong>This example shows you how to extract a specific frame from a movie</strong>.<br />';
	
// 	load the examples configuration
	require_once 'example-config.php';
	
// 	require the library
	require_once '../phpvideotoolkit.php';
	
// 	please replace xxxxx with the full absolute path to the files and folders
// 	also please make the $thumbnail_output_dir read and writeable by the webserver
	
// 	temp directory
	$tmp_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'tmp/';
	
//	input movie files
	$files_to_process = array(
		PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed/MOV00007.3gp',
		PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed/Video000.3gp',
		PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed/cat.mpeg'
	);

//	output files dirname has to exist
	$thumbnail_output_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/thumbnails/';
	
//	log dir
	$log_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'logs/';
	
// 	start phpvideotoolkit class
	$toolkit = new PHPVideoToolkit($tmp_dir);
	
// 	set phpvideotoolkit class to run silently
	$toolkit->on_error_die = FALSE;
	
// 	start the timer collection
	$total_process_time = 0;
	
// 	loop through the files to process
	foreach($files_to_process as $key=>$file)
	{
// 		get the filename parts
		$filename = basename($file);
		$filename_minus_ext = substr($filename, 0, strrpos($filename, '.'));
		
// 		set the input file
		$ok = $toolkit->setInputFile($file);
// 		check the return value in-case of error
		if(!$ok)
		{
// 			if there was an error then get it 
			echo '<b>'.$toolkit->getLastError()."</b><br />\r\n";
			$toolkit->reset();
			continue;
		}
		
// 		set the output dimensions
		$toolkit->setVideoOutputDimensions(PHPVideoToolkit::SIZE_SQCIF);
		
// 		extract a thumbnail from the fifth frame two seconds into the video
		$toolkit->extractFrame('00:00:02.5');
		
// 		set the output details
		$ok = $toolkit->setOutput($thumbnail_output_dir, $filename_minus_ext.'.jpg', PHPVideoToolkit::OVERWRITE_EXISTING);
// 		check the return value in-case of error
		if(!$ok)
		{
// 			if there was an error then get it 
			echo '<b>'.$toolkit->getLastError()."</b><br />\r\n";
			$toolkit->reset();
			continue;
		}
		
// 		execute the ffmpeg command
		$result = $toolkit->execute(false, true);
		
// 		get the last command given
// 		$command = $toolkit->getLastCommand();
// 		echo $command."<br />\r\n";
// 		check the return value in-case of error
		if($result !== PHPVideoToolkit::RESULT_OK)
		{
// 			move the log file to the log directory as something has gone wrong
			$toolkit->moveLog($log_dir.$filename_minus_ext.'.log');
// 			if there was an error then get it 
			echo '<b>'.$toolkit->getLastError()."</b><br />\r\n";
			$toolkit->reset();
			continue;
		}
		
// 		get the process time of the file
		$process_time = $toolkit->getLastProcessTime();
		$total_process_time += $process_time;
		
		$file = array_shift($toolkit->getLastOutput());
		
		echo 'Frame grabbed in '.$process_time.' seconds... <b>'.$thumbnail_output_dir.$file.'</b><br />'."\r\n";
		echo '<img src="processed/thumbnails/'.$file.'" alt="" border="0" /><br /><br />';
	
// 		reset 
		$toolkit->reset();
		
	}
	
	echo '<br />'."\r\n".'The total time taken to process all '.($key+1).' file(s) is : <b>'.$total_process_time.'</b>';
	echo '<br />'."\r\n".'The average time taken to process each file is : <b>'.($total_process_time/($key+1)).'</b><br /><br />';
