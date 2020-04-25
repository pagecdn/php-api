PHP Library for PageCDN API
=======================

[PageCDN](https://pagecdn.com) is a Content Delivery Network and Content Optimization Service to accelerate your websites by upto 10x.

### Requirements
1. PHP 5.3+
2. PHP Curl Extension
3. PHP JSON Extension
4. Write access to cache directory

### Usage
* [Initial Setup](#initial-setup)
* Using Public CDN
* Using Private CDN
* Using Public + Private CDN
* Enabling Optimizations

### Initial Setup

To use this library, simply include the `pagecdn.php` file as below:
```php
<?php
require 'pagecdn.php';
```

This library relies heavily on local cache to speed up several CDN operations like conversion of your website URLs to CDN URLs, fetching Public CDN resources, caching API requests, etc.
For such purpose and to ease the conversion of URLs, this libraries requires Origin URL and write access to a cache directory.
```php
<?php
require 'pagecdn.php';

$options = [ 'origin_url' => 'https://your-website.com/blog' , //Always Required
             'cache_dir'  => './sdk-cache/'                    //Always required
             ];

$pagecdn = PageCDN::init( $options );
```


