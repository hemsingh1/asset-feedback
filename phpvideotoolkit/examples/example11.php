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

	echo '<strong>This example shows you how to use the ffmpeg-php adaptor scripts. NOTE: Please note whenever possible you should use ffmpeg-php as it is much more efficient than this pure PHP emulation.</strong><br />';
	
// 	load the examples configuration
	require_once 'example-config.php';
	
	echo '<strong>NOTE; The FFmpeg-PHP adapter makes use of <a href="http://getid3.sourceforge.net/">getID3</a>. For convenience, v1.7.7 it has been distributed along with this package, however it is subject to a GPL license where as the PHPVideoToolkit class is distributed with a BSD license. This <u>WILL</u> have implications if you are intending to redistribute the PHPVideoToolkit ffmpeg-php adapter classes commercially.</strong><br /><br />';
	
// 	show windows getID3 helpers alert
	if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
	{
		echo '<strong>NOTE 2; because you are on a Windows based system you may need to download and install some getID3 helper files. You can find these files <a href="http://sourceforge.net/project/showfiles.php?group_id=55859&package_id=105260">here</a>. getID3 is mainly only used to provide mp3 id3 information in the ffmpeg_movie class, however it also provides some minor audio information. Thus if you are using the adapter class for video only, getID3 is not used and you will not need to download these helper files.</strong><br /><br />';
	}

// 	get the ffmpeg adaptors
	if(!class_exists('ffmpeg_movie')) 
	{
		require_once '../adapters/ffmpeg-php/ffmpeg_movie.php';
		require_once '../adapters/ffmpeg-php/ffmpeg_frame.php';
		require_once '../adapters/ffmpeg-php/ffmpeg_animated_gif.php';
	}
	else 
	{
		echo '<strong>You currently have ffmpeg-php installed on your server, and the module is loaded, therefore this example will not use the PHPVideoToolkit ffmpeg-php adapter. It will use the actual ffmpeg-php module.</strong><br /><br />';
	}
	
// 	temp directory
	$tmp_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'tmp/';
	
// 	the frame number to retrieve
	$frame_number = 15;
	
//	input movie files
// 	$file = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed/mp3.mp3';
	$file = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'to-be-processed/MOV00007.3gp';
	$file_info = pathinfo($file);

//	output files dirname has to exist
	$thumbnail_output_dir = PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/thumbnails/';
	
// 	load the movie
	$ffmpeg_movie 	= new ffmpeg_movie($file, false, $tmp_dir);
// 	get the 20th frame from the movie
	$frame 			= $ffmpeg_movie->getFrame($frame_number);
// 	check the frame has been returned
	if($frame === false)
	{
		die('The frame, '.$frame_number.' does not exist.');
	}
// 	print_R($frame);
// 	create the same size thumbnail
	$gd_resource 	= $frame->toGDImage();
	$orig_width 	= $frame->getWidth();
	$orig_height 	= $frame->getHeight();
// 	echo $gd_resource;
	imagejpeg($gd_resource, PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/thumbnails/'.$file_info['filename'].'-samesize.jpg', 80);
	
	echo '<strong>Plain Frame Grab of Movie</strong>.<br />';
	echo 'This is a frame grab at the same resolution as the video.<br />';
	echo '<img src="processed/thumbnails/'.$file_info['filename'].'-samesize.jpg" alt="" width="'.$frame->getWidth().'" height="'.$frame->getHeight().'" border="0" /><br /><br />';
	
// 	crop the thumbnail
	$frame->crop(20, 20, 20, 20);
	$gd_resource 	= $frame->toGDImage();
	imagejpeg($gd_resource, PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/thumbnails/'.$file_info['filename'].'-cropped.jpg', 80);
	
	echo '<strong>Cropped Frame Grab of Movie</strong>.<br />';
	echo 'This is a frame grab that has been cropped.<br />';
	echo '<img src="processed/thumbnails/'.$file_info['filename'].'-cropped.jpg" alt="" width="'.$frame->getWidth().'" height="'.$frame->getHeight().'" border="0" /><br /><br />';
	
// 	resize the thumbnail
	$frame->resize(50, 50);
	$gd_resource 	= $frame->toGDImage();
	imagejpeg($gd_resource, PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/thumbnails/'.$file_info['filename'].'-resized.jpg', 80);
	$small_width = $frame->getWidth();
	$small_height = $frame->getHeight();
	
	echo '<strong>Cropped and Resized Frame Grab of Movie</strong>.<br />';
	echo 'This is a frame grab that has been cropped then resized.<br />';
	echo '<img src="processed/thumbnails/'.$file_info['filename'].'-resized.jpg" alt="" width="'.$small_width.'" height="'.$small_height.'" border="0" /><br /><br />';
	
// 	create 2 animated gifs, one normal size, one small
// 	create the normal one
	$ffmpeg_gif = new ffmpeg_animated_gif(PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/thumbnails/'.$file_info['filename'].'-animated.gif', $orig_width, $orig_height, 5, 0);
// 	create the small one
	$ffmpeg_gif_small = new ffmpeg_animated_gif(PHPVIDEOTOOLKIT_EXAMPLE_ABSOLUTE_BATH.'processed/thumbnails/'.$file_info['filename'].'-animated-small.gif', $small_width, $small_height, 5, 0);
	for ($i = 1, $a = $ffmpeg_movie->getFrameCount(), $inc = $ffmpeg_movie->getFrameRate()/2; $i < $a; $i += $inc)
	{
// 		get the required frame
	    $ffmpeg_frame = $ffmpeg_movie->getFrame($i);
		if($ffmpeg_frame !== false)
		{
// 			add the frame to the gif
	    	$result = $ffmpeg_gif->addFrame($ffmpeg_frame);
			if(!$result)
			{
				'There was an error adding frame '.$i.' to the gif.<br />';
			}
		}
// 		get the required frame
	    $ffmpeg_frame_small = $ffmpeg_movie->getFrame($i);
		if($ffmpeg_frame_small !== false)
		{
// 			crop and resize the frame
			$ffmpeg_frame_small->resize(50, 50, 20, 20, 20, 20);
// 			then add it to the small one
			$result = $ffmpeg_gif_small->addFrame($ffmpeg_frame_small);
			if(!$result)
			{
				'There was an error adding frame '.$i.' to the gif.<br />';
			}
		}
	}
	echo '<strong>Animated Gif of Movie</strong>.<br />';
	$result = $ffmpeg_gif->saveNow($tmp_dir);
	if(!$result)
	{
		echo 'It was not possible to save the animated gif for some reason.<br />';
	}
	else
	{
		echo 'This is an animated gif extracted from the movie.<br />';
		echo '<img src="processed/thumbnails/'.$file_info['filename'].'-animated.gif" alt="" width="'.$orig_width.'" height="'.$orig_height.'" border="0" /><br /><br />';
	}
	
	echo '<strong>Cropped and Resized Animated Gif of Movie</strong>.<br />';
	$result = $ffmpeg_gif_small->saveNow($tmp_dir);
	if(!$result)
	{
		echo 'It was not possible to save the animated gif for some reason.<br />';
	}
	else
	{
		echo 'This is an animated gif extracted from the movie but <i>cropped and then resized</i>.<br />';
		echo '<img src="processed/thumbnails/'.$file_info['filename'].'-animated-small.gif" alt="" width="'.$small_width.'" height="'.$small_height.'" border="0" /><br /><br />';
	}
	
	
	
	
