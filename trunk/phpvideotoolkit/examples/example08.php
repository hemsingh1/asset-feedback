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

	echo '<strong>This example shows you how to convert videos to common formats simply by using the simple adapters.</strong><br />';
	
// 	load the examples configuration
	require_once 'example-config.php';
	
// 	require the library
	require_once '../phpvideotoolkit.php';
	require_once '../adapters/videoto.php';
	
// 	temp directory
	$tmp_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'tmp/';
	
// 	processed file output directory
	$output_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/videos/';
	
//	input movie files
	$files_to_process = array(
		PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed/MOV00007.3gp',
		PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed/Video000.3gp',
		PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed/cat.mpeg'
	);

// 	loop through the files to process
	foreach($files_to_process as $file)
	{
		echo '<strong>Processing '.basename($file).'</strong><br />';
		
// 		convert the video to a gif format
		$result = VideoTo::gif($file, array(
				'temp_dir'					=> $tmp_dir, 
				'output_dir'				=> $output_dir, 
				'output_file'				=> '#filename-gif.#ext', 
				'die_on_error'				=> false,
				'overwrite_mode'			=> PHPVideoToolkit::OVERWRITE_EXISTING
			));
// 		check for an error
		if($result !== PHPVideoToolkit::RESULT_OK)
		{
			echo VideoTo::getError().'<br />'."\r\n";
			echo 'Please check the log file generated as additional debug info may be contained.<br />'."\r\n";
		}
		else
		{
			$output = VideoTo::getOutput();
			echo 'Coverted to <a href="processed/videos/'.basename($output[0]).'">Animated Gif</a>.<br />'."\r\n";
		}
		
// 		convert the video to a psp mp4
		$result = VideoTo::PSP($file, array(
				'temp_dir'					=> $tmp_dir, 
				'output_dir'				=> $output_dir, 
				'output_file'				=> '#filename-psp.#ext', 
				'die_on_error'				=> false,
				'overwrite_mode'			=> PHPVideoToolkit::OVERWRITE_EXISTING
			));
// 		check for an error
		if($result !== PHPVideoToolkit::RESULT_OK)
		{
			echo VideoTo::getError().'<br />'."\r\n";
			echo 'Please check the log file generated as additional debug info may be contained.<br />'."\r\n";
		}
		else
		{
			$output = VideoTo::getOutput();
			echo 'Coverted to <a href="processed/videos/'.basename($output[0]).'">PSP mp4</a>.<br />'."\r\n";
		}
		
// 		convert the video to flv
		$result = VideoTo::FLV($file, array(
				'temp_dir'					=> $tmp_dir, 
				'output_dir'				=> $output_dir, 
				'die_on_error'				=> false,
				'overwrite_mode'			=> PHPVideoToolkit::OVERWRITE_EXISTING
			));
// 		check for an error
		if($result !== PHPVideoToolkit::RESULT_OK)
		{
			echo VideoTo::getError().'<br />'."\r\n";
			echo 'Please check the log file generated as additional debug info may be contained.<br />'."\r\n";
		}
		else
		{
			$output = VideoTo::getOutput();
			echo 'Coverted to <a href="processed/videos/'.basename($output[0]).'">Flash Video (flv)</a>.<br />'."\r\n";
		}
		
// 		convert the video to an ipod mp4
		$result = VideoTo::iPod($file, array(
				'temp_dir'					=> $tmp_dir, 
				'output_dir'				=> $output_dir, 
				'die_on_error'				=> false,
				'output_file'				=> '#filename-ipod.#ext',
				'overwrite_mode'			=> PHPVideoToolkit::OVERWRITE_EXISTING
			));
// 		check for an error
		if($result !== PHPVideoToolkit::RESULT_OK)
		{
			echo VideoTo::getError().'<br />'."\r\n";
			echo 'Please check the log file generated as additional debug info may be contained.<br /><br />'."\r\n";
		}
		else
		{
			$output = VideoTo::getOutput();
			echo 'Coverted to <a href="processed/videos/'.basename($output[0]).'">iPod mp4</a>.<br /><br />'."\r\n";
		}
		
	}
	
