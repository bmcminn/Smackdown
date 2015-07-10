# Smackdown
version: `0.0.1`

---

An opinionated, non-standard Markdown parser for [Throwdown](https://github.com/bmcminn/throwdown) built using PEGs (parsing expression grammers).

## Status
- Incomplete...

## Requirements

- PHP >= 5.4

## Config

The basic config is just initializing a basic Smackdown instance. This will have the default config outlined below:

```php
<?php
  require "vendor/autoload.php";

  $defaults = [
      'ext'             => '.md'  // file extension used by Smackdown->renderFile
    , 'directory'       => ''     // folder where Smackdown can find your content files
    , 'front_matter'    => false  // let Smackdown parse out your front_matter content for you

    , 'delimiters' => [
        'code_fence'    => '```'  //
      , 'code'          => '`'    //
      , 'numlist'       => '\.'   //
      , 'emphasis'      => '_'    //
      , 'strong'        => '__'   //
      , 'attr'          => ','    // when parsing [link](/extras)[they="separate"ATTR_DELIMITERthese="values"]
      ]
    ];

  $smackdown = new \Gbox\Smackdown($defaults);
?>
```
