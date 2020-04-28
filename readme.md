PHP Library for PageCDN API
=======================

[PageCDN](https://pagecdn.com) is a Content Delivery Network and Content Optimization Service to accelerate your websites by upto 10x.

### Requirements
1. PHP 5.3+
2. PHP Curl Extension
3. PHP JSON Extension
4. Write access to cache directory

### Usage
1. [Initial Setup](#initial-setup)
2. [Using Public CDN](#using-public-cdn)
3. [Using Private CDN](#using-private-cdn)
4. [Using Public + Private CDN](#using-public--private-cdn)
5. [Enabling Optimizations](#enabling-optimizations)
6. [Resizing, Converting and Optimizing Images](#resizing-converting-and-optimizing-images)
7. [Purge and Cache cleanup](#purge-and-cache-cleanup)

### Initial Setup

To use this library, simply include the [`pagecdn.php`](/src/pagecdn.php) file as below:
```php
<?php
require 'pagecdn.php';
```

This library relies heavily on local cache to speed up several CDN operations like conversion of your website URLs to CDN URLs, fetching Public CDN resources, caching API requests, etc.
For such purpose and to ease the conversion of URLs, this libraries requires that you specify your Origin URL and provide write access to a cache directory.
```php
<?php
require 'pagecdn.php';

$options = [ 'origin_url' => 'https://your-website.com/blog' , //Always Required
             'cache_dir'  => './sdk-cache/'                    //Always required
             ];

$pagecdn = PageCDN::init( $options );
```

### Using Public CDN
PageCDN offers both Public CDN and Private CDN. Using Public CDN is **FREE** and DOES NOT even require an account. Here is how you can link to a resource automatically from Public CDN.
```php
<?php
require 'pagecdn.php';

$options = [ 'public_cdn' => true ,
             'origin_url' => 'https://your-website.com/blog' , //Always Required
             'cache_dir'  => './sdk-cache/'                    //Always required
             ];

$pagecdn = PageCDN::init( $options );

echo $pagecdn->url('https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.css?ver=4.3.1');
echo $pagecdn->url('https://your-website.com/blog/wp-includes/js/jquery/jquery.js');

# Result:
#   https://pagecdn.io/lib/bootstrap/4.3.1/css/bootstrap.min.css
#   https://pagecdn.io/repo/wp-includes/5.4/js/jquery/jquery.js
```

### Using Private CDN
Using Private CDN requires API Key and optionally CDN URL in addition to the basic requirements set forth in the Initial Setup section above. This library can automatically create a private repo (Zone) for you to ease the CDN connection. Here is example of a basic Private CDN setup.
```php
<?php
require 'pagecdn.php';

$options = [ 'private_cdn'=> true ,
             'origin_url' => 'https://your-website.com/blog' , //Always Required
             'apikey'     => '3e692034d5b4d31326f8dc637229ee6f95a50e1242394420f07a8597934c0cc0' , //Required for Private CDN
             'cdn_url'    => 'https://pagecdn.io/site/abcxyz' , //Optional. Library can automatically find or create a CDN_URL for you
             'cache_dir'  => './sdk-cache/'                    //Always required
             ];

$pagecdn = PageCDN::init( $options );

echo $pagecdn->url('https://your-website.com/blog/assets/hello.css');

# Result:
#   https://pagecdn.io/site/abcxyz/assets/hello.css
```

### Using Public + Private CDN
It is possible to use Public and Private CDN together. In such a case, this library will automatically find the most optimal way to link to a resource.
```php
<?php
require 'pagecdn.php';

$options = [ 'private_cdn'=> true ,
             'public_cdn' => true ,
             'origin_url' => 'https://your-website.com/blog' , //Always Required
             'apikey'     => '3e692034d5b4d31326f8dc637229ee6f95a50e1242394420f07a8597934c0cc0' , //Required for Private CDN
             'cdn_url'    => 'https://pagecdn.io/site/abcxyz' , //Optional. Library can automatically find or create a CDN_URL for you
             'cache_dir'  => './sdk-cache/'                    //Always required
             ];

$pagecdn = PageCDN::init( $options );

echo $pagecdn->url('https://your-website.com/blog/assets/hello.css');
echo $pagecdn->url('https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.css?ver=4.3.1');
echo $pagecdn->url('https://your-website.com/blog/wp-includes/js/jquery/jquery.js');

# Result:
#   https://pagecdn.io/site/abcxyz/assets/hello.css               [Private CDN]
#   https://pagecdn.io/lib/bootstrap/4.3.1/css/bootstrap.min.css  [Public CDN]
#   https://pagecdn.io/repo/wp-includes/5.4/js/jquery/jquery.js   [Public CDN]
```
Linking to resources from Public CDN is recommended as it increases cache hit ratio in browser and on edge. Using Public CDN is free, and results in bandwidth cost saving.

### Enabling Optimizations
All the above examples help with loading resources over CDN. But this is not all what PageCDN can do for you. PageCDN allows you to optimize your website resources aggressively without worrying about maintaining complex optimization tools, configurations and multiple copies of optimized files.
Optimizing resources with PageCDN is as simple as just linking to a resource.
```php
<?php
require 'pagecdn.php';

$options = [ 'private_cdn'=> true ,
             'public_cdn' => true ,
             'origin_url' => 'https://your-website.com/blog' , //Always Required
             'apikey'     => '3e692034d5b4d31326f8dc637229ee6f95a50e1242394420f07a8597934c0cc0' , //Required for Private CDN
             'cdn_url'    => 'https://pagecdn.io/site/abcxyz' , //Optional. Library can automatically find or create a CDN_URL for you
             'cache_dir'  => './sdk-cache/'                   , //Always required
             
             //Optimizations
             'remove_querystring'	=> true ,
             'optimize_images'    => true ,
             'minify_css'         => true ,
             'minify_js'          => true ,
             ];

$pagecdn = PageCDN::init( $options );

echo $pagecdn->url('https://your-website.com/blog/assets/hello.js?ver=4.2.1');
echo $pagecdn->url('https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.css?ver=4.3.1');
echo $pagecdn->url('https://your-website.com/blog/assets/company-logo.png');

# Result:
#   https://pagecdn.io/site/abcxyz/assets/hello.min.js              [Minified JS on Private CDN, Removed Querystring]
#   https://pagecdn.io/lib/bootstrap/4.3.1/css/bootstrap.min.css    [Minified CSS on Public CDN]
#   https://pagecdn.io/site/abcxyz/assets/company-logo._o_webp.png  [Optimized and Converted to WebP if browser supports for WebP is available]
```
All these optimizations are handled by PageCDN on the fly.

### Resizing, Converting and Optimizing Images
Enabling above image optimization in itself can speedup website by a big margin. However, you may need to resize images to smaller dimensions to save unnecessary bandwidth spent on delivering large images. Here is how to achieve that.
```php
<?php
require 'pagecdn.php';

$options = [ 'private_cdn'=> true ,
             'public_cdn' => true ,
             'origin_url' => 'https://your-website.com/blog' , //Always Required
             'apikey'     => '3e692034d5b4d31326f8dc637229ee6f95a50e1242394420f07a8597934c0cc0' , //Required for Private CDN
             'cdn_url'    => 'https://pagecdn.io/site/abcxyz' , //Optional. Library can automatically find or create a CDN_URL for you
             'cache_dir'  => './sdk-cache/'                   , //Always required
             
             //Optimizations
             'remove_querystring'	=> true ,
             'optimize_images'    => true ,
             'minify_css'         => true ,
             'minify_js'          => true ,
             ];

$pagecdn = PageCDN::init( $options );

echo $pagecdn->image('https://your-website.com/blog/assets/company-logo.png',['width'=>300,'height'=>100]);

# Result:
#   https://pagecdn.io/site/abcxyz/assets/company-logo._o_300w_100h_webp.png
```
### Purge and Cache cleanup
#### Purge a single URL from PageCDN edge cache
```php
$pagecdn->purge('https://your-website.com/blog/assets/company-logo.png');
```
#### Purge the entire repo
```php
$pagecdn->purge_all( );
```
#### Reset local directory cache
```php
$pagecdn->reset_cache( );
```

### Missing Features
Some of the PageCDN features are not yet implemented in this library. Please wait for the future releases to avail those features, or roll your own implementation or library with the help of [API Docs](https://pagecdn.com/docs).
1. In-URL Cache Busters
2. Storage Buckets / Push CDN
3. Pull CDN for Github Repos
4. Autmatic conversion of Google Fonts or other Font URLs to [Easyfonts](https://pagecdn.com/lib/easyfonts).
