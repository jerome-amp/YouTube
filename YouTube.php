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
					
					$this->set('id', $config->videoDetails->videoId);
					$this->set('title', $config->videoDetails->title);
					$this->set('author', $config->videoDetails->author);
					$this->set('category', $config->microformat->playerMicroformatRenderer->category);
					$this->set('description', $config->videoDetails->shortDescription);
					
					$this->set('upload_date', $config->microformat->playerMicroformatRenderer->uploadDate);
					$this->set('publish_date', $config->microformat->playerMicroformatRenderer->publishDate);
					
					$this->set('keywords', $config->videoDetails->keywords);
					
					$this->set('views', $config->videoDetails->viewCount);
					$this->set('rating', $config->videoDetails->averageRating);
					$this->set('duration', $config->videoDetails->lengthSeconds);
					
					$this->set('thumbnails', $config->videoDetails->thumbnail->thumbnails);
					
					$this->setVideos($config->streamingData->formats ?? []);
					$this->setVideos($config->streamingData->adaptiveFormats ?? []);
				}
			}
		}
	}
	
	private function set(string $name, int|array|string|null &$value): void
	{
		if(isset($value))
		{
			$this->$name = $value;
		}
	}
	
	private function setActions(string $url): void
	{
		if($contents = file_get_contents($url))
		{
			if(preg_match('#\'use strict\';var ([A-Za-z0-9]+)="(.+)"\.split\("([^"]+)"\)#', $contents, $match))
			{
				$index = $match[1];
				$values = explode($match[3], $match[2]);
				$keys = array_flip($values);
				
				if(preg_match('#([A-Za-z0-9]+):function\(([^\)]+)\)\{\2\['.$index.'\['.$keys['reverse'].'\]\]\(\)\}#', $contents, $match))
				{
					$actions[$match[1]] = 'reverse';
				}
				
				if(preg_match('#([A-Za-z0-9]+):function\(([^,]+),([^\)]+)\)\{\2\['.$index.'\['.$keys['splice'].'\]\]\(0,\3\)\}#', $contents, $match))
				{
					$actions[$match[1]] = 'splice';
				}
				
				if(preg_match('#([A-Za-z0-9]+):function\(([^,]+),([^\)]+)\)\{var ([^=]+)=\2\[0\];\2\[0\]=\2\[\3%\2\['.$index.'\['.$keys['length'].'\]\]\];\2\[\3%\2\['.$index.'\['.$keys['length'].'\]\]\]=\4\}#', $contents, $match))
				{
					$actions[$match[1]] = 'swap';
				}
				
				if(preg_match('#\['.$index.'\['.$keys['split'].'\]\]\('.$index.'\['.$keys[''].'\]\);(.*)return#', $contents, $match))
				{
					if(preg_match_all('#\['.$index.'\[([0-9]+)\]\]\([^,]+,([0-9]+)\)#', $match[1], $match))
					{
						foreach($match[0] as $key => $temp)
						{
							$action = new stdClass;
							
							$action->name = $actions[$values[$match[1][$key]]];
							$action->value = $match[2][$key];
							
							$this->actions[] = $action;
						}
					}
				}
			}
		}
	}
	
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
					$video->url = $data['url'].'&'.$data['sp'].'='.urlencode($this->cipher($data['s']));
				}
				else $video->url = null;
			}
			else
			{
				$video->url = $format->url;
			}
			
			$video->itag = $format->itag;
			$video->lenght = $format->contentLength ?? 0;
			$video->mime_type = $format->mimeType;
			
			if(isset($format->qualityLabel))
			{
				$video->type = isset($format->audioQuality) ? 'video' : 'mute-video';
				$video->width = $format->width;
				$video->height = $format->height;
				$video->quality = $format->qualityLabel;
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
