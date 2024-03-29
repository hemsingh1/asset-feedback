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

	echo '<strong>This example shows you how to access the information about your ffmpeg installation.</strong><br /><br />';
	$ignore_demo_files = true;	
	
// 	load the examples configuration
	require_once 'example-config.php';
	
// 	require the library
	require_once '../phpvideotoolkit.php';
	
// 	temp directory
	$tmp_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'tmp/';
	
// 	start ffmpeg class
	$toolkit = new PHPVideoToolkit($tmp_dir);
	
// 	get the ffmpeg info
	$info = $toolkit->getFFmpegInfo();
	
// 	determine the type of support for ffmpeg-php
	echo '<strong>FFmpeg-PHP Support</strong><br />';
	
// 	determine if ffmpeg-php is supported
	$has_ffmpeg_php_support = $toolkit->hasFFmpegPHPSupport();
// 	you can also determine if it has ffmpeg php support with below
// 	$has_ffmpeg_php_support = $info['ffmpeg-php-support'];

	switch($has_ffmpeg_php_support)
	{
		case 'module' :
			echo 'Congratulations you have the FFmpeg-PHP module installed.';
			break;
			
		case 'emulated' :
			echo 'You haven\'t got the FFmpeg-PHP module installed, however you can use the PHPVideoToolkit\'s adapter\'s to emulate FFmpeg-PHP.<br /><strong>Note:</strong> It is recommended that if you heavily use FFmpeg-PHP that you install the module.';
			break;
			
		case false :
			echo 'You have no support at all for FFmpeg-PHP.';
			break;
	}
	
	echo '<br /><br /><strong>This is the information that is accessible about your install of FFmpeg.</strong><br />';
	echo '<pre>';
	print_r($info);
	echo '</pre>';