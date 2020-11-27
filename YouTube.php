<?php

class YouTube
{
	public string $id = '';
	public string $title = '';
	public string $author = '';
	public string $category = '';
	public string $description = '';
	public string $upload_date = '';
	public string $publish_date = '';
	
	public int $views = 0;
	public int $rating = 0;
	public int $duration = 0;
	
	public array $videos = [];
	public array $keywords = [];
	public array $thumbnails = [];
	
	private array $actions = [];
	
	/**
	 * Set all object properties
	 *
	 * @param string $id YouTube video id
	 */
	
	public function __construct(string $id)
	{
		if($contents = file_get_contents('https://www.youtube.com/watch?v='.$id))
		{
			if(preg_match('#ytInitialPlayerResponse = (\{.+\});#U', $contents, $match))
			{
				if(!is_null($config = json_decode($match[1])))
				{
					if(preg_match('#"jsUrl":"([^"]+)"#', $contents, $match))
					{
						$this->setActions('https://www.youtube.com'.$match[1]);
					}
					
					$this->id = $this->get($config->videoDetails->videoId);
					$this->title = $this->get($config->videoDetails->title);
					$this->author = $this->get($config->videoDetails->author);
					$this->category = $this->get($config->microformat->playerMicroformatRenderer->category);
					$this->description = $this->get($config->videoDetails->shortDescription);
					
					$this->upload_date = $this->get($config->microformat->playerMicroformatRenderer->uploadDate);
					$this->publish_date = $this->get($config->microformat->playerMicroformatRenderer->publishDate);
					
					$this->keywords = $this->get($config->videoDetails->keywords, []);
					
					$this->views = $this->get($config->videoDetails->viewCount);
					$this->rating = $this->get($config->videoDetails->averageRating);
					$this->duration = $this->get($config->videoDetails->lengthSeconds);
					
					$this->thumbnails = $this->get($config->videoDetails->thumbnail->thumbnails, []);
					
					$this->setVideos($this->get($config->streamingData->formats, []));
					$this->setVideos($this->get($config->streamingData->adaptiveFormats, []));
				}
			}
		}
	}
	
	/**
	 * Get value if is set else default
	 *
	 * @param $value
	 * @param $default
	 */
	
	private function get(&$value, $default = null)
	{
		return isset($value) ? $value : $default;
	}
	
	/**
	 * Defines necessary actions to cipher the signature
	 *
	 * @param string $url YouTube javascript url
	 */
	
	private function setActions(string $url): void
	{
		if($contents = file_get_contents($url))
		{
			$function = new stdClass;
			
			if(preg_match('#([A-Za-z0-9]+):function\(a\)\{a\.reverse\(\)\}#', $contents, $match))
			{
				$function->{$match[1]} = 'reverse';
			}
			
			if(preg_match('#([A-Za-z0-9]+):function\(a,b\)\{a\.splice\(0,b\)\}#', $contents, $match))
			{
				$function->{$match[1]} = 'slice';
			}
			
			if(preg_match('#([A-Za-z0-9]+):function\(a,b\)\{var c=a\[0\];a\[0\]=a\[b%a\.length\];a\[b%a\.length\]=c\}#', $contents, $match)) 
			{
				$function->{$match[1]} = 'swap';
			}
			
			if(preg_match('#=([A-Za-z]+)\(decodeURIComponent#', $contents, $match))
			{
				if(preg_match('#'.$match[1].'=function\(a\)\{a=a\.split\(""\);([^\}]+)return a\.join\(""\)}#', $contents, $match))
				{
					if(preg_match_all('#[A-Za-z0-9]+\.([A-Za-z0-9]+)\(a,([0-9]+)\)#', $match[1], $match))
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
			$video = new stdClass;
			
			if(!empty($format->signatureCipher))
			{
				parse_str($format->signatureCipher, $data);
				
				if(!empty($data['url']) && !empty($data['sp']) && !empty($data['s']))
				{
					$video->url = $data['url'].'&'.$data['sp'].'='.$this->cipher($data['s']);
				}
				else $video->url = null;
			}
			else
			{
				$video->url = $this->get($format->url);
			}
			
			$video->itag = $this->get($format->itag);
			$video->lenght = $this->get($format->contentLength);
			$video->mime_type = $this->get($format->mimeType);
			
			if(isset($format->qualityLabel))
			{
				$video->type = isset($format->audioQuality) ? 'video' : 'mute-video';
				$video->width = $this->get($format->width);
				$video->height = $this->get($format->height);
				$video->quality = $this->get($format->qualityLabel);
			}
			else
			{
				$video->type = 'audio';
				$video->width = null;
				$video->height = null;
				$video->quality = null;
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
