<?php
	/**
	* Library for the PageCDN API
	*
	* @version 0.1.0
	*/
	
	class PageCDN {
		
		private $ver		= '0.1.0';
		
		private $api_base	= 'https://pagecdn.com/api/v2';
		
		private $options;
		
		private $known_urls;
		
		static function init( $options = array( ) )
		{
			Static $connection = null;
			
			if( $connection === null )
			{
				#	Id for current option-set
				
				if( !isset( $options['id'] ) )
				{
					$options['id']	= md5( json_encode( $options ) );
				}
				
				if( !isset( $options['public_cdn'] ) )
				{
					$options['public_cdn']	= true;
				}
				
				if( !isset( $options['optimize_images'] ) )
				{
					$options['optimize_images']	= false;
				}
				
				if( !isset( $options['minify_css'] ) )
				{
					$options['minify_css']	= false;
				}
				
				if( !isset( $options['minify_js'] ) )
				{
					$options['minify_js']	= false;
				}
				
				if( !isset( $options['optimize_fonts'] ) )
				{
					$options['optimize_fonts']	= false;
				}
				
				if( !isset( $options['remove_querystring'] ) )
				{
					$options['remove_querystring']	= false;
				}
				
				if( !( isset( $options['origin_url'] ) && strlen( trim( $options['origin_url'] ) ) ) )
				{
					self::error( 'Origin URL is required.' );
				}
				
				if( $options['origin_url'] )
				{
					if( strpos( $options['origin_url'] , '?' ) !== false )
					{
						$options['origin_url']	= substr( $options['origin_url'] , 0 , strpos( $options['origin_url'] , '?' ) );
					}
					
					$options['origin_url']	= rtrim( $options['origin_url'] , '/' );
					
					if( strpos( $options['origin_url'] , '//' ) === 0 )
					{
						$options['origin_url']	= 'https:' . $options['origin_url'];
					}
					
					if( !parse_url( $options['origin_url'] , PHP_URL_SCHEME ) )
					{
						$options['origin_url']	= 'http://' . $options['origin_url'];
					}
				}
				
				$options['origin_host']	= parse_url( $options['origin_url'] , PHP_URL_HOST );
				
				$options['origin_protocol']	= parse_url( $options['origin_url'] , PHP_URL_SCHEME );
				
				$options['origin_path']	= parse_url( $options['origin_url'] , PHP_URL_PATH );
				
				if( in_array( strtolower( $options['origin_host'] ) , array( 'www.github.com' , 'github.com' ) ) )
				{
					if( !isset( $options['origin'] ) )
					{
						$options['origin']	= 'github';
					}
				}
				
				if( !isset( $options['origin'] ) )
				{
					$options['origin']	= 'website';
				}
				
				$_host	= $options['origin_host'];
				
				if( stripos( $_host , 'www.' ) === 0 )
				{
					$_host	= substr( $_host , 4 );
				}
				
				$options['origin_equivalents']	= array(	"https://www.{$_host}"	, "https://{$_host}"	,
															"http://www.{$_host}"	, "http://{$_host}"		,
															"//www.{$_host}"		, "//{$_host}"			);
				
				if( !isset( $options['private_cdn'] ) )
				{
					$options['private_cdn']	= false;
				}
				
				if( $options['private_cdn'] )
				{
					if( !( isset( $options['cdn_name'] ) && strlen( trim( $options['cdn_name'] ) ) ) )
					{
						$options['cdn_name']		= '';
						
						if( $options['origin_url'] )
						{
							$options['cdn_name']	= trim( parse_url( $options['origin_url'] , PHP_URL_HOST ) , '/' );
							
							$_path	= trim( parse_url( $options['origin_url'] , PHP_URL_PATH ) , '/' );
							
							if( strlen( $_path ) )
							{
								$options['cdn_name']	.= '/' . $_path;
							}
						}
					}
					
					if( !( isset( $options['apikey'] ) && strlen( trim( $options['apikey'] ) ) ) )
					{
						$options['apikey']		= '';
					}
					
					if( !( isset( $options['cdn_url'] ) && $options['cdn_url'] ) )
					{
						$options['cdn_url']		= '';
					}
					
					if( !( $options['cdn_url'] || $options['apikey'] ) )
					{
						self::error( 'API Key or CDN URL is required to enable private CDN features.' );
					}
				}
				
				
				#	It is possible to:
				#	- provide just cdn_url and omit apikey, or
				#	- provide just apikey and omit cdn_url.
				#	
				#	Omitting API key will disable operations that require API Key.
				
				
				if( !isset( $options['cache_dir'] ) || ( $options['cache_dir'] == '' ) )
				{
					$options['cache_dir']	= __DIR__ . DIRECTORY_SEPARATOR . '.pagecdn-cache';
				}
				
				if( !file_exists( $options['cache_dir'] ) )
				{
					if( !self::create_fs_hierarchy( $options['cache_dir'] ) )
					{
						self::error( 'Cache directory ' . $options['cache_dir'] . ' does not exist.' );
					}
				}
				else if( !is_writeable( $options['cache_dir'] ) )
				{
					self::error( 'Cache directory ' . $options['cache_dir'] . ' is not writeable.' );
				}
				
				$options['cache_dir']	= rtrim( $options['cache_dir'] , '/\\' ) . DIRECTORY_SEPARATOR;
				
				if( !file_exists( "{$options['cache_dir']}url-cache-{$options['id']}.json" ) )
				{
					file_put_contents( "{$options['cache_dir']}url-cache-{$options['id']}.json" , json_encode( array( ) ) );
				}
				
				$options['webp_support']	= isset( $_SERVER['HTTP_ACCEPT'] ) && ( strpos( $_SERVER['HTTP_ACCEPT'] , 'image/webp' ) !== false );
				
				$connection	= new PageCDN( $options );
			}
			
			return $connection;
		}
		
		function __construct( $options )
		{
			if( $this->known_urls = file_get_contents( "{$options['cache_dir']}url-cache-{$options['id']}.json" ) )
			{
				$this->known_urls	= json_decode( $this->known_urls , true );
			}
			else
			{
				$this->known_urls	= array( );
			}
			
			$this->options	= $options;
			
			if( !$this->options['cdn_url'] )
			{
				$this->check_repo( );
			}
		}
		
		private static function create_fs_hierarchy( $dir )
		{
			if( file_exists( $dir ) )
			{
				return true;
			}
			
			return ( bool ) mkdir( $dir , 0755 , $recursive = true );
		}
		
		private function remove_querystring( $url )
		{
			if( strpos( $url , '?' ) !== false )
			{
				$url	= substr( $url , 0 , strpos( $url , '?' ) );
			}
			
			return $url;
		}
		
		private function cache_get( $url )
		{
			$cache_identifier	= '';
			
			if( $this->options['optimize_images'] )
			{
				if( $this->options['webp_support'] )
				{
					$cache_identifier	= 'webp:';
					
					if( isset( $this->known_urls[$cache_identifier.$url] ) )
					{
						$this->counter_get( 'cache-webp-url' );
						
						return $this->known_urls[$cache_identifier.$url];
					}
				}
				else
				{
					$cache_identifier	= 'img:';
					
					if( isset( $this->known_urls[$cache_identifier.$url] ) )
					{
						$this->counter_get( 'cache-img-url' );
						
						return $this->known_urls[$cache_identifier.$url];
					}
				}
			}
			
			if( $this->options['minify_css'] || $this->options['minify_js'] )
			{
				$cache_identifier	= 'min:';
				
				if( isset( $this->known_urls[$cache_identifier.$url] ) )
				{
					$this->counter_get( 'cache-url' );
					
					return $this->known_urls[$cache_identifier.$url];
				}
			}
			
			if( isset( $this->known_urls[$url] ) )
			{
				$this->counter_get( 'cache-url' );
				
				return $this->known_urls[$url];
			}
			
			return false;
		}
		
		private function cache_set( $original , $optimized )
		{
			$this->known_urls[$original] = $optimized;
			
			file_put_contents( "{$this->options['cache_dir']}url-cache-{$this->options['id']}.json" , json_encode( $this->known_urls ) );
			
			$this->counter_set( 'cache-url' );
			
			return $optimized;
		}
		
		function url( $url )
		{
			if( ( $return_url = $this->cache_get( $url ) ) !== false )
			{
				return $return_url;
			}
			
			$original_url	= $url;
			
			$url			= $this->make_absolute_url( $url );
			
			if( ( strpos( $url , 'http://' ) === 0 ) || ( strpos( $url , 'https://' ) === 0 ) )
			{
				if( $this->is_public_url( $url ) || $this->is_origin_url( $url ) )
				{
					if( $this->options['public_cdn'] )
					{
						$return_url	= $this->lookup_public_file( $url );
						
						if( $return_url !== $url )
						{
							return $this->cache_set( $original_url , $return_url );
						}
					}
					
					if( !$this->is_full_origin_url( $original_url ) )
					{
						//Cache this so that URLs outside the origin path are no longer retried for public cdn.
						//Still allow first attempt on public cdn, and then cache and return.
						
						return $this->cache_set( $original_url , $original_url );
					}
					
					if( $this->is_origin_url( $url ) )
					{
						if( $this->options['remove_querystring'] )
						{
							$url	= $this->remove_querystring( $url );
						}
						
						if( $this->options['private_cdn'] )
						{
							$this->check_repo( );
							
							$try_url	= $this->normalize_origin( $url );
							
							$return_url	= $this->private_cdn_url( $original_url , $try_url );
							
							if( $return_url !== $try_url )
							{
								return $return_url;
							}
						}
					}
				}
			}
			
			return $url;
		}
		
		private function lookup_public_file( $url )
		{
			//Check if its a CSS or JS file
			
			if( strpos( $url , '?' ) !== false )
			{
				$test	= substr( $url , 0 , strpos( $url , '?' ) );
			}
			else
			{
				$test	= $url;
			}
			
			if( ( strtolower( substr( $test , -4 ) ) !== '.css' ) && ( strtolower( substr( $test , -3 ) ) !== '.js' ) )
			{
				return $url;
			}
			
			$contents	= $this->request( $url , 'get' , array( ) , $cacheable = true );
			
			if( $contents !== false )
			{
				$contents_hash	= hash( 'sha256' , $contents );
				
				$response	= $this->request( "https://pagecdn.io/lookup/{$contents_hash}" , 'get' , array( ) , $cacheable = true );
				
				if( $response !== false )
				{
					$response	= json_decode( $response , true );
					
					if( isset( $response['status'] ) && ( $response['status'] == '200' ) )
					{
						$response	= $response['response'];
						
						if( isset( $response['file_url'] ) && strlen( $response['file_url'] ) )
						{
							$cdn_file	= $response['file_url'];
							
							if( $this->options['minify_css'] )
							{
								if( ( substr( $cdn_file , -4 ) === '.css' ) && ( substr( $cdn_file , -7 ) !== 'min.css' ) )
								{
									$cdn_file	= substr_replace( $cdn_file , '.min.css' , strlen( $cdn_file ) - 4 , 4 );
								}
							}
							
							if( $this->options['minify_js'] )
							{
								if( ( substr( $cdn_file , -3 ) === '.js' ) && ( substr( $cdn_file , -6 ) !== 'min.js' ) )
								{
									$cdn_file	= substr_replace( $cdn_file , '.min.js' , strlen( $cdn_file ) - 3 , 3 );
								}
							}
							
							return $cdn_file;
						}
					}
				}
			}
			
			return $url;
		}
		
		private function private_cdn_url( $original_url , $url )
		{
			$start_with	= $url;
			
			if( $this->is_full_origin_url( $url ) )
			{
				$querystring		= '';
				
				$cache_identifier	= '';
				
				if( strpos( $url , '?' ) !== false )
				{
					$querystring	= substr( $url , strpos( $url , '?' ) + 1 );
					
					$url			= substr( $url , 0 , strpos( $url , '?' ) );
				}
				
				if( $this->options['optimize_images'] )
				{
					$flag	= '._o';
					
					if( $this->options['webp_support'] )
					{
						$flag	= '._o_webp';
					}
					
					if( in_array( strtolower( substr( $url , -4 ) ) , array( '.png' , '.jpg' ) ) )
					{
						$url	= substr( $url , 0 , -4 ) . $flag . substr( $url , -4 );
						
						if( $this->options['webp_support'] )
						{
							$cache_identifier	= 'webp:';
						}
						else
						{
							$cache_identifier	= 'img:';
						}
					}
					else if( in_array( strtolower( substr( $url , -5 ) ) , array( '.jpeg' ) ) )
					{
						$url	= substr( $url , 0 , -5 ) . $flag . substr( $url , -5 );
						
						if( $this->options['webp_support'] )
						{
							$cache_identifier	= 'webp:';
						}
						else
						{
							$cache_identifier	= 'img:';
						}
					}
				}
				
				if( $this->options['minify_css'] )
				{
					if( ( substr( $url , -7 ) !== 'min.css' ) && ( substr( $url , -4 ) === '.css' ) )
					{
						$url	= substr_replace( $url , '.min.css' , strlen( $url ) - 4 , 4 );
						
						$cache_identifier	= 'min:';
					}
				}
				
				if( $this->options['minify_js'] )
				{
					if( ( substr( $url , -6 ) !== 'min.js' ) && ( substr( $url , -3 ) === '.js' ) )
					{
						$url	= substr_replace( $url , '.min.js' , strlen( $url ) - 3 , 3 );
						
						$cache_identifier	= 'min:';
					}
				}
				
				$url	= str_replace( $this->options['origin_url'] , $this->options['cdn_url'] , $url );
				
				if( strlen( $querystring ) )
				{
					$url	.= '?'.$querystring;
				}
				
				return $this->cache_set( $cache_identifier . $original_url , $url );
			}
			
			return $start_with;
		}
		
		private function is_origin_url( $url )
		{
			$origin_host = parse_url( $this->options['origin_url'] , PHP_URL_HOST );
			
			$url_host = parse_url( $url , PHP_URL_HOST );
			
			return ( $origin_host == $url_host ) || ( 'www.'.$origin_host == $url_host ) || ( $origin_host == 'www.'.$url_host );
		}
		
		private function is_full_origin_url( $url )
		{
			if( $this->is_origin_url( $url ) )
			{
				$origin_path	= parse_url( $this->options['origin_url'] , PHP_URL_PATH );
				
				$url_path		= parse_url( $url , PHP_URL_PATH );
				
				if( strpos( $url_path , $origin_path . '/' ) === 0 )
				{
					return true;
				}
			}
			
			return false;
		}
		
		private function is_public_url( $url )
		{
			$host = parse_url( $url , PHP_URL_HOST );
			
			$public_hosts	= array(	'pagecdn.io'					,	
										'cdn.jsdelivr.net'				,	
										'cdnjs.cloudflare.com'			,	
										'ajax.aspnetcdn.com'			,	
										'ajax.googleapis.com'			,	
										'stackpath.bootstrapcdn.com'	,	
										'maxcdn.bootstrapcdn.com'		,	
										'code.jquery.com'				,	
										'cdn.bootcss.com'				,	
										'unpkg.com'						,	
										'use.fontawesome.com'			,	
										'cdn.rawgit.com'				,	
										'cdn.staticfile.org'			,	
										'apps.bdimg.com'				,	
										'yastatic.net'					,	
										'code.ionicframework.com'		,	
										'cdn.ckeditor.com'				,	
										'lib.arvancloud.com'			,	
										'netdna.bootstrapcdn.com'		,	
										'cdn.mathjax.org'				);
			
			return in_array( $host , $public_hosts );
		}
		
		private function normalize_origin( $url )
		{
			//Origin: http://example.com/
			
			$origin_host	= parse_url( $this->options['origin_url'] , PHP_URL_HOST );
			
			$url_host		= parse_url( $url , PHP_URL_HOST );
			
			$origin_scheme	= parse_url( $this->options['origin_url'] , PHP_URL_SCHEME );
			
			$url_scheme		= parse_url( $url , PHP_URL_SCHEME );
			
			if( ( $origin_host !== $url_host ) || ( $origin_scheme !== $url_scheme ) )
			{
				foreach( $this->options['origin_equivalents'] as $opt )
				{
					if( ( strpos( $this->options['origin_url'] , $opt ) === 0 ) && ( strpos( $url , $opt ) !== 0 ) )
					{
						$url	= substr_replace( $url , $opt , 0 , strpos( $url , $url_host ) + strlen( $url_host ) );
					}
				}
			}
			
			return $url;
		}
		
		private function make_absolute_url( $url )
		{
			if( strpos( $url , '//' ) === 0 )		//Protocol relative
			{
				$url = 'https:' . $url;
			}
			else if( strpos( $url , '/' ) === 0 )	//Origin relative
			{
				if( strpos( $url , '/../' ) !== 0 )	//	/../ at the start would mean a URL outside the scope of CDN.
				{
					$url	= $this->options['origin_url'] . $url;
				}
			}
			
			return $url;
		}
		
		function image( $url , $options = array( ) )
		{
			if( !isset( $options['optimize'] ) )
			{
				if( isset( $options['webp'] ) || isset( $options['width'] ) || isset( $options['height'] ) )
				{
					$options['optimize']	= true;
				}
				else
				{
					$options['optimize']	= $this->options['optimize_images'];
				}
			}
			else if( $options['optimize'] === false )
			{
				if( isset( $options['webp'] ) || isset( $options['width'] ) || isset( $options['height'] ) )
				{
					$options['optimize']	= true;
				}
			}
			
			
			if( !isset( $options['width'] ) )
			{
				$options['width']	= '';
			}
			
			if( !isset( $options['height'] ) )
			{
				$options['height']	= '';
			}
			
			if( !isset( $options['webp'] ) )
			{
				if( $this->options['webp_support'] )
				{
					$options['webp']	= true;
				}
				else
				{
					$options['webp']	= false;
				}
			}
			
			$options['id']	= md5( json_encode( $options ) . $this->options['id'] );
			
			if( ( $return_url = $this->cache_get( $options['id'].':'.$url ) ) !== false )
			{
				return $return_url;
			}
			
			
			
			
			$original_url	= $url;
			
			#	Make relative URL absolute.
			
			$url	= $this->make_absolute_url( $url );
			
			if( ( strpos( $url , 'http://' ) === 0 ) || ( strpos( $url , 'https://' ) === 0 ) )
			{
				if( $this->is_origin_url( $url ) )
				{
					if( $this->options['remove_querystring'] )
					{
						$url	= $this->remove_querystring( $url );
					}
					
					if( $this->options['private_cdn'] )
					{
						$this->check_repo( );
						
						$try_url	= $this->normalize_origin( $url );
						
						//$return_url	= $this->private_cdn_url( $original_url , $try_url );
						
						if( $options['optimize'] )
						{
							$querystring		= '';
							
							if( strpos( $try_url , '?' ) !== false )
							{
								$querystring	= substr( $try_url , strpos( $try_url , '?' ) + 1 );
								
								$try_url		= substr( $try_url , 0 , strpos( $try_url , '?' ) );
							}
							
							$flag	= '._o';
							
							
							if( $options['width'] )
							{
								$flag	.= '_' . $options['width'] . 'w';
								
								if( $options['height'] )
								{
									$flag	.= '_' . $options['height'] . 'h';
								}
							}
							else if( $options['height'] )
							{
								$flag	.= '_' . $options['height'] . 'h';
							}
							
							if( $options['webp'] )
							{
								$flag	.= '_webp';
							}
							
							if( in_array( strtolower( substr( $try_url , -4 ) ) , array( '.png' , '.jpg' ) ) )
							{
								$try_url	= substr( $try_url , 0 , -4 ) . $flag . substr( $try_url , -4 );
							}
							else if( in_array( strtolower( substr( $try_url , -5 ) ) , array( '.jpeg' ) ) )
							{
								$try_url	= substr( $try_url , 0 , -5 ) . $flag . substr( $try_url , -5 );
							}
							
							if( strlen( $querystring ) )
							{
								$try_url	.= '?'.$querystring;
							}
						}
						
						$try_url	= str_replace( $this->options['origin_url'] , $this->options['cdn_url'] , $try_url );
						
						return $this->cache_set(  $options['id'].':'.$original_url , $try_url );
					}
				}
			}
			
			return $original_url;
		}
		
		private function check_repo( )
		{
			if( !$this->options['cdn_url'] )
			{
				if( $this->options['apikey'] && $this->options['origin_url'] )
				{
					if( $ret = $this->create_repo( ) )
					{
						$this->options['cdn_url']	= $ret['cdn_base'];
					}
				}
			}
			
			if( !$this->options['cdn_url'] )
			{
				$this->error( 'PageCDN-Error: Private CDN could not be enabled as no cdn_url is specified.' );
			}
		}
		
		function create_repo( $options = null )
		{
			if( $options === null )
			{
				$options	= array( );
			}
			
			if( !isset( $options['apikey'] ) )
			{
				$options['apikey']	= $this->options['apikey'];
			}
			
			if( !isset( $options['origin'] ) )
			{
				$options['origin']		= 'website';
			}
			
			$use_existing_repo	= false;
			
			if( !isset( $options['origin_url'] ) )
			{
				$options['origin_url']	= $this->options['origin_url'];
				
				$options['cdn_name']	= $this->options['cdn_name'];
				
				//User has explicitly provided an origin_url. Lets create it even if there already is one.
				
				$use_existing_repo		= true;
			}
			
			if( !isset( $options['privacy'] ) )
			{
				$options['privacy']	= 'private';
			}
			
			
			$post					= array();
			
			$post['apikey']			= $options['apikey'];
			
			$post['origin']			= $options['origin'];
			
			$post['origin_url']		= rtrim( $options['origin_url'] , '/' );
			
			$post['privacy']		= $options['privacy'];
			
			
			if( isset( $options['cdn_name'] ) && strlen( $options['cdn_name'] ) )
			{
				$post['repo_name']		= $options['cdn_name'];
			}
			
			
			if( !isset( $options['cache_buster'] ) )
			{
				$options['cache_buster']	= '0';
			}
			
			$post['cache_buster']	= ( string ) ( int ) ( bool ) $options['cache_buster'];
			
			
			if( !isset( $options['update_css_paths'] ) )
			{
				$options['update_css_paths']	= '0';
			}
			
			$post['update_css_paths']	= ( string ) ( int ) ( bool ) $options['update_css_paths'];
			
			
			if( !isset( $options['import_dir'] ) )
			{
				$options['import_dir']	= '';
			}
			
			$post['import_dir']	= $options['import_dir'];
			
			
			if( isset( $options['keywords'] ) )
			{
				$post['keywords']	= $options['keywords'];
			}
			
			
			
			
			$repo_details	= array( );
			
			$key_options	= array(	$post['origin']				, 
										$post['origin_url']			, 
										$post['privacy']			, 
										$post['cache_buster']		, 
										$post['update_css_paths']	, 
										$post['import_dir'] );
			
			if( $use_existing_repo )
			{
				//1. Check a local cache based on options and load configuration from there.
				
				$repo_details	= $this->cache_get_repo( $key_options );
				
				if( !count( $repo_details ) )
				{
					//2. Match an existing repo based on origin and other details
					
					$response	= $this->api_request( '/private/account/repos' );
					
					if( $response['count'] )
					{
						foreach( $response['repos'] as $repo )
						{
							if(	( $repo['origin'] == $post['origin'] )			&& 
								( $repo['origin_url'] == $post['origin_url'] )	&&
								( $repo['privacy'] == $post['privacy'] )		)
							{
								$response	= $this->api_request( '/private/repo/info' , 'get' , array( 'repo' => $repo['repo'] ) );
								
								if( ( ( bool ) $response['cache_buster'] === ( bool ) $post['cache_buster'] ) && 
									( ( bool ) $response['update_css_paths'] === ( bool ) $post['update_css_paths'] ) )
								{
									if( ( $response['origin'] === 'github' ) )
									{
										if( $response['import_dir'] === $post['import_dir'] )
										{
											$repo_details	= $response;
										}
									}
									else
									{
										$repo_details	= $response;
									}
								}
							}
						}
					}
					
					if( count( $repo_details ) )
					{
						//Cache repo details
						
						$this->cache_set_repo( $key_options , $repo_details );
					}
				}
			}
			
			if( !count( $repo_details ) )
			{
				//3. Or create a new repo using $post data
				
				$response	= $this->api_request( '/private/repo/create' , 'post' , $post );
				
				$response	= $this->api_request( '/private/repo/info' , 'get' , array( 'repo' => $response['repo'] ) );
				
				$repo_details	= $response;
				
				if( count( $repo_details ) )
				{
					//Cache repo details
					
					$this->cache_set_repo( $key_options , $repo_details );
				}
			}
			
			return $repo_details;
		}
		
		private function cache_get_repo( $options )
		{
			$repo_details	= array( );
			
			$cache_file		= $this->options['cache_dir'] . 'cdn-cache-' . md5( json_encode( $options ) ) . '.json';
			
			if( file_exists( $cache_file ) )
			{
				$json	= file_get_contents( $cache_file );
				
				$repo_details	= json_decode( $json , true );
				
				$this->counter_get( 'repo-details' );
			}
			
			return $repo_details;
		}
		
		private function cache_set_repo( $options , $repo_details )
		{
			$cache_file		= $this->options['cache_dir'] . 'cdn-cache-' . md5( json_encode( $options ) ) . '.json';
			
			file_put_contents( $cache_file , json_encode( $repo_details ) );
			
			$this->counter_set( 'repo-details' );
			
			return $repo_details;
		}
		
		private function counter_set( $type )
		{
			if( !isset( $this->options['debug'][$type]['set'] ) )
				$this->options['debug'][$type]['set']	= 0;
			
			$this->options['debug'][$type]['set']++;
		}
		
		private function counter_get( $type )
		{
			if( !isset( $this->options['debug'][$type]['get'] ) )
				$this->options['debug'][$type]['get']	= 0;
			
			$this->options['debug'][$type]['get']++;
		}
		
		function reset_cache( )
		{
			foreach( glob( $this->options['cache_dir'] . '{request-cache,url-cache,cdn-cache}*' ,  GLOB_BRACE ) as $file )
			{
				if( !is_dir( $file ) )
				{
					unlink( $file );
				}
			}
		}
		
		function purge( $url = null )
		{
			if( !( strlen( $this->options['apikey'] ) && strlen( $this->options['cdn_url'] ) ) )
			{
				return false;
			}
			
			$original_url	= $url;
			
			if( strpos( $url , 'https://pagecdn.io' ) === 0 )
			{
				if( !( strpos( $url , $this->options['cdn_url'] ) === 0 ) )
				{
					//Public Resources, or 3rd party resources cannot be purged using this method.
					
					return false;
				}
			}
			else
			{
				$url	= $this->url( $url );
				
				if( !( strpos( $url , $this->options['cdn_url'] ) === 0 ) )
				{
					//Not a valid URL
					
					return false;
				}
			}
			
			if( isset( $this->known_urls[$original_url] ) )
			{
				unset( $this->known_urls[$original_url] );
			}
			
			$this->api_request( '/private/file/delete' , 'get' , array( 'file_url' => $url ) );
		}
		
		function purge_all( )
		{
			if( !( strlen( $this->options['apikey'] ) && strlen( $this->options['cdn_url'] ) ) )
			{
				return;
			}
			
			//$this->reset_cache( );
			
			$args			= array( );
			
			$args['repo']	= trim( parse_url( $this->options['cdn_url'] , PHP_URL_PATH ) , '/' );
			
			$this->api_request( '/private/repo/delete-files' , 'get' , $args );
		}
		
		private static function error( $string )
		{
			throw new Exception( $string );
		}
		
		private function integration_info( )
		{
			$tool['integration_name']			= 'php-sdk';
			$tool['integration_version']		= $this->ver;
			$tool['integration_cms_name']		= '';
			$tool['integration_cms_version']	= '';
			
			return $tool;
		}
		
		function api_request( $endpoint , $method = 'get' , $fields = array( ) , $cache = false )
		{
			if( !isset( $fields['apikey'] ) )
			{
				$fields['apikey']	= $this->options['apikey'];
			}
			
			if( strtolower( $method ) == 'get' )
			{
				$fields		= http_build_query( array_merge( $fields , $this->integration_info( ) ) );
				
				$response	= $this->request( "{$this->api_base}{$endpoint}?{$fields}" , $method , array( ) , $cache );
			}
			else
			{
				$response	= $this->request( "{$this->api_base}{$endpoint}" , $method , $fields , $cache );
			}
			
			if( $response === false )
			{
				$this->error( 'PageCDN-Error: Unable to connect to API.' );
				
				return false;
			}
			
			$response	= json_decode( $response , true );
			
			if( $response['status'] != 200 )
			{
				$this->error( 'PageCDN-Error: API returned [' . $response['status'] . ' ' . $response['message'] . '] [' . $response['details'] .']' );
			}
			
			return $response['response'];
		}
		
		private function request( $url , $method = 'get' , $fields = array( ) , $cache = false )
		{
			if( $cache )
			{
				$id	= md5( $url . $method . json_encode( $fields ) );
				
				$file_path	= $this->options['cache_dir'] . 'request-cache-' . $id . '.dat';
				
				if( file_exists( $file_path ) )
				{
					return file_get_contents( $file_path );
				}
			}
			
			$useragent	= 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:62.0) Gecko/20100101 Firefox/62.0';
			
			$curl		= curl_init();
			
			if( strtolower( $method ) == 'post' )
			{
				curl_setopt( $curl , CURLOPT_POST , true );
				
				if( $fields !== null )
				{
					curl_setopt( $curl , CURLOPT_POSTFIELDS , $fields ); 
				}
			}
			else
			{
				curl_setopt( $curl , CURLOPT_HTTPGET , true );
			}
			
			curl_setopt( $curl , CURLOPT_RETURNTRANSFER		, 1		);
			
			curl_setopt( $curl , CURLOPT_CONNECTTIMEOUT		, 60	);
			
			curl_setopt( $curl , CURLOPT_TIMEOUT			, 60	);
			
			curl_setopt( $curl , CURLOPT_FOLLOWLOCATION		, true	);
			
			curl_setopt( $curl , CURLOPT_MAXREDIRS			, 3 	);
			
			curl_setopt( $curl , CURLOPT_USERAGENT			, $useragent );
			
			curl_setopt( $curl , CURLOPT_URL , $url );
			
			$response	= curl_exec( $curl );
			
			$error		= curl_error( $curl );
			
			if( curl_errno( $curl ) )
			{
				$curl_error		= curl_error( $curl );
				
				$this->error( "PageCDN-Error: {$curl_error}" );
			}
			
			curl_close( $curl );
			
			if( $cache )
			{
				file_put_contents( $file_path , $response );
			}
			
			return $response;
		}
	}
	
	