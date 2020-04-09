<?php

class YouTube
{
	public string $id = '';
	public string $title = '';
	public string $author = '';
	public string $category = '';
	public string $description = '';
	public string $date_upload = '';
	public string $date_publish = '';
	
	public int $views = 0;
	public int $rating = 0;
	public int $duration = 0;
	
	public array $videos = array();
	public array $keywords = array();
	public array $thumbnails = array();
	
	private array $actions = array();
	
	public function __construct(string $v)
	{
		if(($contents = file_get_contents('https://www.youtube.com/watch?v='.$v)) !== false)
		{
			if(preg_match('#ytplayer\.config = (\{.+\});#U', $contents, $match) !== false)
			{
				if(($config = json_decode($match[1])) !== false)
				{
					$this->setActions('https://www.youtube.com'.$config->assets->js);
					
					if(($config = json_decode($config->args->player_response)) !== false)
					{
						$this->id = $config->videoDetails->videoId;
						$this->title = $config->videoDetails->title;
						$this->author = $config->videoDetails->author;
						$this->category = $config->microformat->playerMicroformatRenderer->category;
						$this->description = $config->videoDetails->shortDescription;
						
						$this->date_upload = $config->microformat->playerMicroformatRenderer->uploadDate;
						$this->date_publish = $config->microformat->playerMicroformatRenderer->publishDate;
						
						$this->keywords = $config->videoDetails->keywords;
						
						$this->views = $config->videoDetails->viewCount;
						$this->rating = $config->videoDetails->averageRating;
						$this->duration = $config->videoDetails->lengthSeconds;
						
						$this->thumbnails = $config->videoDetails->thumbnail->thumbnails;
						
						$this->setVideos($config->streamingData->formats);
						$this->setVideos($config->streamingData->adaptiveFormats);
					}
				}
			}
		}
	}
	
	/**
	 * Defines necessary actions to cipher the signature
	 *
	 * @param string $url YouTube JavaScript URL
	 */
	
	private function setActions(string $url): void
	{
		if(($contents = file_get_contents($url)) !== false)
		{
			$function = new stdClass;
			
			if(preg_match('#([A-Za-z0-9]+):function\(a\)\{a\.reverse\(\)\}#', $contents, $match) !== false)
			{
				$function->{$match[1]} = 'reverse';
			}
			
			if(preg_match('#([A-Za-z0-9]+):function\(a,b\)\{a\.splice\(0,b\)\}#', $contents, $match) !== false)
			{
				$function->{$match[1]} = 'slice';
			}
			
			if(preg_match('#([A-Za-z0-9]+):function\(a,b\)\{var c=a\[0\];a\[0\]=a\[b%a\.length\];a\[b%a\.length\]=c\}#', $contents, $match) !== false) 
			{
				$function->{$match[1]} = 'swap';
			}
			
			if(preg_match('#=([A-Za-z]+)\(decodeURIComponent#', $contents, $match) !== false)
			{
				if(preg_match('#'.$match[1].'=function\(a\)\{a=a\.split\(""\);([^\}]+)return a\.join\(""\)}#', $contents, $match) !== false)
				{
					if(preg_match_all('#[A-Za-z0-9]+\.([A-Za-z0-9]+)\(a,([0-9]+)\)#', $match[1], $match) !== false)
					{
						foreach($match[0] as $key => $temp)
						{
							$action = new stdClass;
							
							$action->name = $function->{$match[1][$key]};
							$action->value = $match[2][$key];
							
							$this->actions[] = $action;
						}
					}
				}
			}
		}
	}
	
	/**
	 * Defines videos property from different YouTube video formats
	 *
	 * @param array $formats YouTube video formats
	 */
	
	private function setVideos(array $formats): void
	{
		foreach($formats as $format)
		{
			parse_str($format->cipher, $data);
			
			$video = new stdClass;
			
			$video->url = $data['url'].'&'.$data['sp'].'='.$this->cipher($data['s']);
			$video->itag = $format->itag;
			$video->lenght = $format->contentLength;
			$video->mime_type = $format->mimeType;
			
			if(!isset($format->qualityLabel))
			{
				$video->type = 'audio';
			}
			else
			{
				$video->type = isset($format->audioQuality) ? 'video' : 'mute-video';
				$video->width = $format->width;
				$video->height = $format->height;
				$video->quality = $format->qualityLabel;
			}
			
			$this->videos[] = $video;
		}
	}
	
	/**
	 * Ciphers the YouTube video signature by applying different actions
	 *
	 * @param string $signature YouTube video signature
	 */
	
	private function cipher(string $signature): string
	{
		foreach($this->actions as $action)
		{
			if($action->name == 'swap')
			{
				$temp = $signature[0];
				
				$signature[0] = $signature[$action->value%strlen($signature)];
				
				$signature[$action->value] = $temp;
			}
			else if($action->name == 'slice')
			{
				$signature = substr($signature, $action->value);
			}
			else if($action->name == 'reverse')
			{
				$signature = strrev($signature);
			}
		}
		
		return $signature;
	}
}
