<?php

namespace Gbox;

/**
 *
 */
class Smackdown {

  private $regex;       // array  | of regex strings for parsing markdown files
  private $config;      // array  | configuration store for all Smackdown settings/overrides
  private $aliases;     // array  | a collection of alias strings for block contents
  private $collections; // array  |

  private $cache;       // string | determines whether and where to cache our parsed file contents
  private $state;       // string | keeps state of what previously occured


  // this is the weight system...
  // when we finally get around to iterating over the line-by-line breakdown
  // we'll use these to guage what should actually happen with said line
  const CODE_FENCE    = 10;
  const HTML_BLOCK    = 10;
  const HTML_INLINE   = 0;
  const HTML_COMMENT  = 0;
  const LINK          = 5;
  const RULE          = 3;
  const CODE          = 2;
  const TEXT          = 1;
  const BLANK_LINE    = 0;


  /**
   * Initializer for new Smackdown instances
   * @param array $overrides Array of overrides
   */
  function __construct($overrides=[]) {

    // define base instance configuration
    $this->config = [
      'ext'             => '.md'
    , 'directory'       => ''
    , 'front_matter'    => false

    , 'delimiters' => [
        'code_fence'    => '```'
      , 'code'          => '`'
      , 'numlist'       => '\.'
      , 'emphasis'      => '_'
      , 'strong'        => '__'
      , 'attr'          => ','    // when parsing [link](/extras)[they="separate"ATTR_DELIMITERthese="values"]
      ]
    ];

    // update configuration with $overrides
    $this->config = array_replace_recursive($this->config, $overrides);


    // configure our regex strings
    $this->regex = [

    // block level content
      'code_fence'    => '/^'.$this->config['delimiters']['code_fence'].'([\w\d]*)?$/i'
    , 'preformat'     => '/^(?:\ {4})([\s\S]+?)$/im'
                      // | ----     (content)
                      // | (4 spaces max)
    , 'blockquote'    => '/^>\s*([\s\S]+?)$/i'
                      // |  > (content here)
    , 'header'        => '/^(\#{1,6})\s*([\s\S]+?)(?:\[([\s\S]+?)\])?$/i'
                      // |      ######       alphanum  \r\n
                      // |      (6max)
    , 'list_item'     => '/^(\s*)[-+]\s*([\s\S]+?)$/i'
                      // |      (-*+)         (alphanum) \r\n
    , 'numlist_item'  => '/^(\s*)([a-z\d]+)'.$this->config['delimiters']['numlist'].'\s*([\s\S]+?)$/i'
                      // |     (ai1.)            (alphanum) \r\n
    , 'rule'          => '/^[-+=_*]{3,}$/i'
                      // |      ---/===/+++/***/___ \r\n

    // inline markdown
    , 'em'            => '/'.$this->config['delimiters']['emphasis'].'([\s\S]+?)'.$this->config['delimiters']['emphasis'].'/i'
    , 'strong'        => '/'.$this->config['delimiters']['strong'].'([\s\S]+?)'.$this->config['delimiters']['strong'].'/i'
    , 'code'          => '/'.$this->config['delimiters']['code'].'([\s\S]+?)'.$this->config['delimiters']['code'].'/i'
                      // | `(codesample)`
    , 'image'         => '/!\[([\s\S]+?)\]\(([\s\S]+?)\)(?:\[([\s\S]+?)\])?/i'
                      // | ![alt text](src /url)[attributes]
    , 'link'          => '/\[([\s\S]+?)\]\(([\s\S]+?)\)(?:\[([\s\S]+?)\])?/i'
                      // | [link text](src /url)[attributes]


    // html components
    , 'html_block'    => '/^(\s*)(<[^!][\s\S]+?>)$/i'
    , 'html_inline'   => '/(<[^!][\s\S]+?>)/i'
    , 'html_comment'  => '/^<!--[\s\S]+?-->$/i'


    // primitive text components
    , 'blank_line'    => '/^\s*$/i'
    , 'text'          => '/^(\s*)([\s\S]+)$/i'

    ];

  }



  //--------------------------------------------------
  // CONTENT RENDER METHODS
  //--------------------------------------------------

  /**
   * Renders a Markdown string passed into this method
   * @return [type] [description]
   */
  public function render($content) {

    // lets not muckup the original content
    $results = $content;

    // break up results into lines
    $results = explode(PHP_EOL, $results);

    // run the content through our line parser
    $results = $this->_parseLines($results);
    print_r($results);
    // Return final render
    return $results;
  }


  /**
   * [renderFile description]
   * @param  [type] $absFilePath [description]
   * @return [type]              [description]
   */
  public function renderFile($absFilePath) {
    $results = file_get_contents($absFilePath);
    return $this->render($results);
  }



  private function _parseLines($lines) {
    $results = [];

    // iterate over lines to classify them
    foreach ($lines as $line => $content) {

      $current = [
        'content' => $content
      ];

      // bump zero-index value
      $line += 1;

      if (preg_match($this->regex['blank_line'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'blank_line'
        , 'weight'  => 1
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['text'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'text'
        , 'weight'  => 1
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['code_fence'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'code_fence'
        , 'weight'  => 5
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['header'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'header'
        , 'weight'  => 5
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['rule'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'rule'
        , 'weight'  => 5
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['blockquote'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'blockquote'
        , 'weight'  => 5
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['list_item'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'list_item'
        , 'weight'  => 5
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['numlist_item'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'numlist_item'
        , 'weight'  => 5
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['link'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'link'
        // , 'weight'  => $content
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['strong'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'strong'
        // , 'weight'  =>
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['em'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'em'
        // , 'weight'  => 0
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['html_block'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'html_block'
        , 'weight'  => HTML_BLOCK
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['html_inline'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'html_inline'
        , 'weight'  => HTML_INLINE
        , 'matches' => $matches
        ];
      }

      if (preg_match($this->regex['html_comment'], $content, $matches)) {
        $current['parse'][] = [
          'type'    => 'html_comment'
        , 'weight'  => HTML_COMMENT
        , 'matches' => $matches
        ];
      }
      $results[$line][] = $current;
    }

    return $results;

  }




}
