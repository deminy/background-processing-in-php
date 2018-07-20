# Background Processing in PHP [![Build Status](https://travis-ci.com/deminy/background-processing-in-php.svg?branch=master)](https://travis-ci.com/deminy/background-processing-in-php) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/deminy/background-processing-in-php/blob/master/LICENSE)

Technical discussion on background processing in PHP web applications with test code included.

## Table of Contents

- [Assumptions](#assumptions)
- [Common Background Processing Techniques in PHP](#common-background-processing-techniques-in-php)
- [Execution Order of PHP Code](#execution-order-of-php-code)
- [How Does Function fastcgi_finish_request() Affect HTTP Responses in PHP-FPM](#how-does-function-fastcgi_finish_request-affect-http-responses-in-php-fpm)
    - [When Function fastcgi_finish_request() Not Used](#when-function-fastcgi_finish_request-in-use)
    - [When Function fastcgi_finish_request() in Use](#when-function-fastcgi_finish_request-not-used)
- [Run Our Test Code](#run-our-test-code)
    - [Prepare Test Environment](#prepare-test-environment)
    - [Test 1: How Does Function register_shutdown_function() Affect HTTP Responses?](#test-1-how-does-function-register_shutdown_function-affect-http-responses)
    - [Test 2: How Does Function fastcgi_finish_request() Affect HTTP Responses?](#test-2-how-does-function-fastcgi_finish_request-affect-http-responses)
    - [Test 3: What Happens When Function register_shutdown_function() and fastcgi_finish_request() Both in Use?](#test-3-what-happens-when-function-register_shutdown_function-and-fastcgi_finish_request-both-in-use)
- [Conclusion](#conclusion)
- [Footnotes](#footnotes)

## Assumptions

Our discussion is mostly about PHP microservices and web applications, especially under PHP-FPM. PHP CLI won't be discussed.

Also, we won't cover edge cases during the discussion, like call _exit()_ within registered shutdown functions.

## Common Background Processing Techniques in PHP

__1.__ Execute an external program in background.

External programs can be executed in background typically like following:

```php
<?php
exec('curl example.com > /dev/null 2>&1 &');
?>
```

This approach is not recommended due to lack of visibility and control over external programs, although it's a common solution in many places.

__2.__ Execute in a child process.

Not an option for web applications since [the _PCNTL_ extension](http://php.net/manual/en/book.pcntl.php) is meant to be used under CLI (and early CGI) only.<sup>1</sup>

__3.__ In destructor methods. 

According to [php.net](http://php.net/manual/en/language.oop5.decon.php#language.oop5.decon.destructor):

> The destructor method will be called as soon as there are no other references to a particular object, or in any order during the shutdown sequence.

This approach is unreliable and shouldn't be considered in practice.

__4.__ Through registered shutdown functions.

This is to register one or more background processing functions through _register_shutdown_function()_. It is a popular
solution for error handling and logging in PHP, as you can see from many PHP libraries and tools like _Laravel_/_Lumen_,
_Symfony_, _Monolog_, _Bugsnag_, _Blackfire_, etc.
 
There are two drawbacks to this approach. First, data printed out from registered shutdown functions will be
included in HTTP responses; secondly, it could slow down HTTP responses. In the following sections, we will show how they
may happen, and how to use registered shutdown functions in PHP-FPM without worrying about these side effects.

__5.__ Through a queue server or a job server.

This is a popular solution, especially for heavy tasks. However, same as #4, this could still slow down HTTP responses.
One typical example is that when the queue server is connected through TCP directly and the PHP web server has terrible
network connection at the time. This side effect could also be avoided in PHP-FPM, as mentioned in #4 and discussed in
following sections.

__6.__ Use function [fastcgi_finish_request()](http://php.net/fastcgi_finish_request) in PHP-FPM.

This is our favorite approach for lightweight background tasks, and we use package [crowdstar/background-processing](https://github.com/Crowdstar/background-processing)
for that. Please check [the README file](https://packagist.org/packages/crowdstar/background-processing) in that package about possible side effects.

If you choose #4 and #5 as you solution, you may still consider to call function _fastcgi_finish_request()_ or use
package _crowdstar/background-processing_ at the end of your PHP application just to fasten HTTP responses.

## Execution Order of PHP Code

1. Generic PHP code.
2. Function call _exit()_. If not called explicitly, you may assume it's called at the end of the PHP code.
3. PHP shutdown functions registered through _register_shutdown_function()_.
4. Destructor methods of non-destroyed objects during the shutdown sequence.

## How Does Function fastcgi_finish_request() Affect HTTP Responses in PHP-FPM

We use following code piece to run under PHP-FPM as an example for discussion.

```php
<?php
echo 1;

register_shutdown_function(function () {echo 3;});
$a = new class {public function __destruct() {echo 4;}};

fastcgi_finish_request(); // This line will be commented out for discussion purpose.

// NOTE: any code starting from here still gets executed no matter if function fastcgi_finish_request() is called or not.

echo 2;
exit();
echo 5; // Unreachable code.
?>
```

### When Function fastcgi_finish_request() in Use

In this case, Only data printed out before first function call to _fastcgi_finish_request()_ will be sent back in HTTP
response. So the HTTP response is "__1__".

However, rest code still runs as usual. So PHP shutdown functions and destructor methods of non-destroyed objects (_$a_
in this case) always executed although data they print out won't be included in HTTP response. We will prove it with test
code discussed below.

### When Function fastcgi_finish_request() Not Used

Here is what will be printed out and send back in HTTP response:

1. Anything before function call _exit()_. If not called explicitly, you may assume it's called at the end of the PHP code.
2. PHP shutdown functions registered through _register_shutdown_function()_.
3. Destructor methods of non-destroyed objects during the shutdown sequence.

So the HTTP response is "__1234__".

## Run Our Test Code

### Prepare Test Environment

Please run following commands to have test environment prepared:

```bash
# Use Docker to launch web server at URL http://127.0.0.1 with web root pointing to folder ./www
docker-compose up -d
# Now run composer update to load 3rd-party library "crowdstar/background-processing" for testing purpose.
composer update --no-dev # You may run command "composer update" instead
```

We have three tests discussed below, and each test includes two HTTP calls. One is to try to write same data to HTTP response
and to a disk file, and the other one is to send disk file content to HTTP response after first HTTP call. Source code of
those PHP endpoints can be found under folder _./www_.

### Test 1: How Does Function register_shutdown_function() Affect HTTP Responses?

First, please run command _curl 127.0.0.1/write1_ to write same data to HTTP response and a disk file. Here is
what showed up in HTTP response:

```text
Executed when function exit() is called.
Executed in a function registered through register_shutdown_function().
Executed in the destruct method of an object during the shutdown sequence.
```

Next, please run command _curl 127.0.0.1/read_ to print out what has been written in the disk file during previous
HTTP request. The output should look like this:

```text
Executed when function exit() is called.
Executed in a function registered through register_shutdown_function().
Executed in the destruct method of an object during the shutdown sequence.
```

What have we observed from the PHP code and the output?

> Data explicitly printed out during PHP shutdown sequence (registered shutdown functions and destructor methods
> of non-destroyed objects) are included in HTTP response, and your PHP code has to complete PHP shutdown
> sequence first before sending back HTTP response to the client.

### Test 2: How Does Function fastcgi_finish_request() Affect HTTP Responses?

First, please run command _curl 127.0.0.1/write2_ to write same data to HTTP response and a disk file. This
HTTP call should return an empty response back (nothing printed out).

Next, please run command _curl 127.0.0.1/read_ to print out what has been written in the disk file during previous
HTTP request. The output should look like this:

```text
Executed after function fastcgi_finish_request() is called.
Executed when function exit() is called.
Executed in the destruct method of an object during the shutdown sequence.
```

What have we observed from the PHP code and the output?

> Data explicitly printed out before function call fastcgi_finish_request() will be in your HTTP response,
> and anything printed out after unction call fastcgi_finish_request() (especially those printed out during
> PHP shutdown sequence) won't be send back to HTTP client.

### Test 3: What Happens When Function register_shutdown_function() and fastcgi_finish_request() Both in Use?

First, please run command _curl 127.0.0.1/write3_ to write same data to HTTP response and a disk file. This
HTTP call should return an empty response back (nothing printed out).

Next, please run command _curl 127.0.0.1/read_ to print out what has been written in the disk file during previous
HTTP request. The output should look like this:

```text
Executed after function fastcgi_finish_request() is called.
Executed when function exit() is called.
Executed in a function registered through register_shutdown_function().
Executed in the destruct method of an object during the shutdown sequence.
```

What have we observed from the PHP code and the output?

> We observed similar results as test 2, and we noticed that by calling function fastcgi_finish_request(),
> we don't have to wait registered shutdown functions and destructor methods to finish during the PHP
> shutdown sequence first before sending back HTTP response to the client. Because of this, calling function
> fastcgi_finish_request() at the end of your PHP application could fasten your web application, typically
> when you use shutdown functions to handle something like error handling.

## Conclusion

1. Using registered shutdown functions may slow down your HTTP request, especially when it takes time to run those
shutdown functions.
2. Under PHP-FPM, we recommend using package [crowdstar/background-processing](https://github.com/Crowdstar/background-processing)
for simple background processing, although you should be aware of certain limitations and side effects with this approach.
3. When using error monitoring/reporting libraries like [Bugsnag](https://github.com/bugsnag/bugsnag-php) (which makes HTTP calls to
report errors), you may consider calling function _fastcgi_finish_request()_ properly at the end of your PHP application for performance reason.
Because of this, we use package [crowdstar/background-processing](https://github.com/Crowdstar/background-processing) in
our microservices even we don't have anything to process in the background.

## Footnotes

<sup>1</sup>PHP CLI uses a single process model while PHP-FPM not, which means duplicated resources (file/socket handles)
cannot be appropriately managed in the child process. You may find a more detailed discussion on this by Joe Watkins from [here](https://stackoverflow.com/a/35029409/2752269).
