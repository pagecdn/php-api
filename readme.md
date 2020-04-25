PHP Library for PageCDN API
=======================

[PageCDN](https://pagecdn.com) is a Content Delivery Network and Content Optimization Service to accelerate your websites by upto 10x.

### Requirements
1. PHP 5.3+
2. PHP Curl Extension
3. PHP JSON Extension
4. Write access to cache directory

### Usage
i [Initial Setup](#initial-setup)
i [Using Public CDN](#using-public-cdn)
i [Using Private CDN](#using-private-cdn)
i [Using Public + Private CDN](#using-public--private-cdn)
i [Enabling Optimizations](#enabling-optimizations)

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
PageCDN offers both Public CDN and Private CDN. Using Public CDN is free and DOES NOT even require a free account. Here is how you can link to a resource automatically from Public CDN.
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


#Result:
#https://pagecdn.io/lib/bootstrap/4.3.1/css/bootstrap.min.css
#https://pagecdn.io/repo/wp-includes/5.4/js/jquery/jquery.js

```




### Using Private CDN
### Using Public + Private CDN
### Enabling Optimizations





