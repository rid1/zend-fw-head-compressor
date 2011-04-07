### Overview

Dm library contains View Helpers: compressScript and compressStyle, which provide you possibility
to pack all head files to minifyed one for reducing number of HTTP requests. For using it you don't have
to change your application code, just change lines with adding head scripts and styles in
main layout, so it's rather easy to use this not only for new, but even for already worked projects.

### Introduction

Prototype for this helper comes from [here](http://habrahabr.ru/blogs/zend_framework/85324/)

But there were some great problems with given code:

* no testing facilities cause of using $_SERVER array
* hard to change rules for folders mapping when additional domains/CDN are used
* several bags with static variables using
* many code repeats
* etc...

So, I rewrite full code of this helper to make it more flexible and stable,
but great thanks previous author for idea and first steps!


### Using helpers in you application

For reusing this library in your application do next:

Copy directories library/Dm/*, library/Tools/* to you libraries directory

Add following lines to application.ini file (or do the same configuration via Bootstrap):

    ;; Set autoloading for DM library
    autoloaderNamespaces[] = "Dm"

    ;; Add new path for finding view helpers
    resources.view.helperPath.Dm_View_Helper = APPLICATION_PATH "/../library/Dm/View/Helper"

Create folder for handling cached JS and CSS files. This folder should be available for webserver,
follow directories will be used as default: 

* public/cache/js for scripts
* public/cache/css for styles

Give this directories 0777 permissions.

### Javascript processing

If you already use headScript() view helper for adding JS files on HTML page, pass this step. 
In other case, start to do this. Any way you have to get something like this:

    <?php $this->headScript()->appendFile($this->baseUrl('js/jquery.js')); ?>
    <?php $this->headScript()->appendFile($this->baseUrl('js/jquery.prettyPhoto.js')); ?>


No matter where you have done this: in controller, view script or in layout.
You can find more information on headScript() helper [here](http://framework.zend.com/manual/en/zend.view.helpers.html#zend.view.helpers.initial.headscript)

To append link to compressed JS files to head section, add follow line between `<head></head>` tags:

    <?php echo $this->compressScript() ?>


### CSS styles processing

The same with css files appending:

    <?php $this->headLink()->appendStylesheet($this->baseUrl('styles/style.css')); ?>
    <?php $this->headLink()->appendStylesheet($this->baseUrl('styles/jquery.prettyPhoto.css')); ?>


And than

    <?php echo $this->compressStyle() ?>


### Testing

In package you can find test classes for helper's unit testing. You can add new test cases if necessary.
Also, if you will launch full copy of this repository as web project, you have to see on web page test string:

"If you see this words on red background, than both of compressors work well!"

If it's true - everything is alright =) (helpers merge 3 js- and 3 css- files).

### Additional notes

For making your site faster, you can compress this files using gzip and set server headers for client caching.
This helpers don't gzip content of cache files cause of several problems with this in some browsers.
But you can easy add gzipping on your web server, according to client-side request.

For Apache, you can use this configuration:

    # css, js gzip compression
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/x-javascript

    # max compression
    DeflateCompressionLevel 9
    DeflateWindowSize 15
    DeflateBufferSize 32768

    # Expires, static content will be cached on client side for 10 year
    ExpiresActive On
    ExpiresDefault "access plus 10 years"

    <FilesMatch \.(html|xhtml|xml|shtml|phtml|php)$>
        ExpiresActive Off
    </FilesMatch>

    # ETag for images, js, css
    FileETag none
    <FilesMatch \.(js|css|gif|png|jpg|swf)$>
        FileETag MTime Size
    </FilesMatch>

For using gzcompressed files with browser, which supported gzipping, you can add following lines in .htaccess files:

_(example given by Andrei Fedarenchyk)_

    <files *.js.gz>
        AddType "text/javascript" .gz
        AddEncoding gzip .gz
    </files>
    <files *.css.gz>
        AddType "text/css" .gz
        AddEncoding gzip .gz
    </files>
    # Check to see if browser can accept gzip files.
    RewriteCond %{HTTP:accept-encoding} gzip
    RewriteCond %{HTTP_USER_AGENT} !Safari

    # Make sure there's no trailing .gz on the url
    RewriteCond %{REQUEST_FILENAME} !^.+\.gz$

    # Check to see if a .gz version of the file exists.
    RewriteCond %{REQUEST_FILENAME}.gz -f

    # All conditions met so add .gz to URL filename (invisibly)
    RewriteRule ^(.+) $1.gz [QSA,L]


For Nginx, use this:

    gzip on;

    # For using gzip_static module you have to configure nginx with special key
    # to get more information on this, pls, don't hesitate to use google
    gzip_static on;
    gzip_http_version 1.1;
    gzip_disable "MSIE [1-6]\.";
    gzip_types text/plain text/html text/css application/x-javascript text/javascript;
    gzip_vary on;
    gzip_comp_level 9;
    gzip_proxied any;


For other web servers you can quickly find information in Internet.