
# Getting Started with Konbit Work Events API

## Introduction

Send proof of work (a merged pull request, a client's approval email, a finished
calendar session, an Upwork or Notion update) to your Konbit workspace. Claude
reviews it, matches it to an open milestone and proposes a PayPal payout. The
server then applies your signed mandate: the payout is sent at once, waits for
your approval, or is blocked.

Evidence is treated as untrusted text. It can never add a payee or raise a limit.

Use the generated SDKs from Zapier steps, GitHub Actions or your own scripts.

## Building

The generated code has dependencies over external libraries like UniRest and JsonMapper. JsonMapper requires docblock annotations like `@var`, `@maps`, and `@factory` to map JSON responses with our class definitions. Hence the docblocks in generated code cannot be disabled by deactivating the PHP configurations like `opcache.save_comments`. These dependencies are defined in the `composer.json` file that comes with the SDK. To resolve these dependencies, we use the Composer package manager which requires PHP greater than or equal to 7.2 installed in your system. Visit [https://getcomposer.org/download/](https://getcomposer.org/download/) to download the installer file for Composer and run it in your system. Open command prompt and type `composer --version`. This should display the current version of the Composer installed if the installation was successful.

* Using command line, navigate to the directory containing the generated files (including `composer.json`) for the SDK.
* Run the command `composer install`. This should install all the required dependencies and create the `vendor` directory in your project directory.

![Building SDK - Step 1](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=installDependencies)

### Configuring CURL Certificate Path in php.ini

:information_source: **Note** This is for Windows users only.

CURL used to include a list of accepted CAs, but no longer bundles ANY CA certs. So by default it will reject all SSL certificates as unverifiable. You will have to get your CA's cert and point curl at it. The steps are as follows:

1. Download the certificate bundle (.pem file) from [https://curl.haxx.se/docs/caextract.html](https://curl.haxx.se/docs/caextract.html) on to your system.
2. Add curl.cainfo = "PATH_TO/cacert.pem" to your php.ini file located in your php installation. “PATH_TO” must be an absolute path containing the .pem file.

```
[curl]; A default value for the CURLOPT_CAINFO option. This is required to be an
; absolute path.
curl.cainfo = PATH_TO/cacert.pem
```

## Installation

The following section explains how to use the KonbitWorkEventsAPILib library in a new project.

### 1. Open Project in an IDE

Open an IDE for PHP like PhpStorm. The basic workflow presented here is also applicable if you prefer using a different editor or IDE.

![Open project in PHPStorm - Step 1](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=openIDE)

Click on `Open` in PhpStorm to browse to your generated SDK directory and then click `OK`.

![Open project in PHPStorm - Step 2](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=openProject0)

### 2. Add a new Test Project

Create a new directory by right clicking on the solution name as shown below:

![Add a new project in PHPStorm - Step 1](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=createDirectory)

Name the directory as "test".

![Add a new project in PHPStorm - Step 2](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=nameDirectory)

Add a PHP file to this project.

![Add a new project in PHPStorm - Step 3](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=createFile)

Name it "testSDK".

![Add a new project in PHPStorm - Step 4](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=nameFile)

Depending on your project setup, you might need to include composer's autoloader in your PHP code to enable auto loading of classes.

```php
require_once "vendor/autoload.php";
```

It is important that the path inside require_once correctly points to the file `autoload.php` inside the vendor directory created during dependency installations.

![Add a new project in PHPStorm - Step 5](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=projectFiles)

After this you can add code to initialize the client library and acquire the instance of a Controller class. Sample code to initialize the client library and use the Controller methods is given in the subsequent sections.

### 3. Run the Test Project

To run your project you must set the Interpreter for your project. Interpreter is the PHP engine installed on your computer.

Open `Settings` from `File` menu.

![Run Test Project - Step 1](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=openSettings)

Select `PHP` from within `Languages & Frameworks`.

![Run Test Project - Step 2](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=setInterpreter0)

Browse for Interpreters near the `Interpreter` option and choose your interpreter.

![Run Test Project - Step 3](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=setInterpreter1)

Once the interpreter is selected, click `OK`.

![Run Test Project - Step 4](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=setInterpreter2)

To run your project, right click on your PHP file inside your Test project and click on `Run`.

![Run Test Project - Step 5](https://apidocs.io/illustration/php?workspaceFolder=KonbitWorkEventsAPI&step=runProject)

## Test the SDK

Unit tests in this SDK can be run using PHPUnit.

1. First install the dependencies using composer including the `require-dev` dependencies.
2. Run `vendor\bin\phpunit --verbose` from commandline to execute tests. If you have installed PHPUnit globally, run tests using `phpunit --verbose` instead.

You can change the PHPUnit test configuration in the `phpunit.xml` file.

## Initialize the API Client

**_Note:_** Documentation for the client can be found [here.](doc/client.md)

The following parameters are configurable for the API Client:

| Parameter | Type | Description |
|  --- | --- | --- |
| timeout | `int` | Timeout for API calls in seconds.<br>*Default*: `0` |
| enableRetries | `bool` | Whether to enable retries and backoff feature.<br>*Default*: `false` |
| numberOfRetries | `int` | The number of retries to make.<br>*Default*: `0` |
| retryInterval | `float` | The retry time interval between the endpoint calls.<br>*Default*: `1` |
| backOffFactor | `float` | Exponential backoff factor to increase interval between retries.<br>*Default*: `2` |
| maximumRetryWaitTime | `int` | The maximum wait time in seconds for overall retrying requests.<br>*Default*: `0` |
| retryOnTimeout | `bool` | Whether to retry on request timeout.<br>*Default*: `true` |
| httpStatusCodesToRetry | `array` | Http status codes to retry against.<br>*Default*: `408, 413, 429, 500, 502, 503, 504, 521, 522, 524, 408, 413, 429, 500, 502, 503, 504, 521, 522, 524` |
| httpMethodsToRetry | `array` | Http methods to retry against.<br>*Default*: `'GET', 'PUT', 'GET', 'PUT'` |
| proxyConfiguration | [`ProxyConfigurationBuilder`](doc/proxy-configuration-builder.md) | Represents the proxy configurations for API calls |
| customHeaderAuthenticationCredentials | [`CustomHeaderAuthenticationCredentials`](doc/auth/custom-header-signature.md) | The Credentials Setter for Custom Header Signature |

The API client can be initialized as follows:

```php
use KonbitWorkEventsAPILib\Authentication\CustomHeaderAuthenticationCredentialsBuilder;
use KonbitWorkEventsAPILib\KonbitWorkEventsAPIClientBuilder;

$client = KonbitWorkEventsAPIClientBuilder::init()
    ->customHeaderAuthenticationCredentials(
        CustomHeaderAuthenticationCredentialsBuilder::init(
            'X-Konbit-Key'
        )
    )
    ->build();
```

## Authorization

This API uses the following authentication schemes.

* [`konbitKey (Custom Header Signature)`](doc/auth/custom-header-signature.md)

## List of APIs

* [Workevents](doc/controllers/workevents.md)

## SDK Infrastructure

### Configuration

* [ProxyConfigurationBuilder](doc/proxy-configuration-builder.md)

### HTTP

* [HttpRequest](doc/http-request.md)
* [HttpResponse](doc/http-response.md)

### Utilities

* [ApiException](doc/api-exception.md)

