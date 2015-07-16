<?php

namespace Gbox;

/**
 *
 */
class Smackdown {

  private $_regex;        // array  | of regex strings for parsing markdown files
  private $_config;       // array  | configuration store for all Smackdown settings/overrides
  private $_aliases;      // array  | a collection of alias strings for block contents
  private $_collections;  // array  |

  private $_cache;        // string | determines whether and where to cache our parsed file contents
  private $_weight;       // string | keeps state of what previously occured

  private $_lines;        // array  |
  private $_prints;       // array  |


  /**
   * this is the weight system...
   * when we finally get around to iterating over the line-by-line breakdown
   * we'll use these to guage what should actually happen with said line
   */
  const W_CODE_FENCE    = 10;
  const W_HTML_BLOCK    = 10;

  const W_BLANK_LINE    = 0;
  const W_HTML_INLINE   = 0;
  const W_HTML_COMMENT  = 0;

  const W_HEADER        = 5;
  const W_RULE          = 5;
  const W_IMAGE         = 5;

  const W_LINK          = 1;
  const W_CODE          = 1;
  const W_ABBR          = 1;
  const W_TEXT          = 1;


  /**
   *
   */
  const T_CODE_FENCE    = "CODE_FENCE";
  const T_HTML_BLOCK    = "HTML_BLOCK";
  const T_HTML_INLINE   = "HTML_INLINE";
  const T_HTML_COMMENT  = "HTML_COMMENT";
  const T_HEADER        = "HEADER";
  const T_ABBR          = "ABBR";
  const T_IMAGE         = "IMAGE";
  const T_LINK          = "LINK";
  const T_RULE          = "RULE";
  const T_CODE          = "CODE";
  const T_TEXT          = "TEXT";
  const T_BLANK_LINE    = "BLANK_LINE";


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

    // primitive text components
      'blank_line'    => '/^\s*$/i'

    // block level content
    , 'code_fence'    => '/^'.$this->config['delimiters']['code_fence'].'([\w\d]*)?$/i'
    , 'preformat'     => '/^(?:\ {4})([\s\S]+?)$/im'
                      // | ----     (content)
                      // | (4 spaces max)
    , 'blockquote'    => '/^>\s*([\s\S]+?)$/i'
                      // |  > (content here)
    , 'rule'          => '/^[-+=_*]{3,}$/i'
                      // |      ---/===/+++/***/___ \r\n
    , 'header'        => '/^(\#{1,6})\s*([\s\S]+?)(?:\[([\s\S]+?)\])?$/i'
                      // |      ######       alphanum  \r\n
                      // |      (6max)
    , 'list_item'     => '/^(\s*)[-+]\s*([\s\S]+?)$/i'
                      // |      (-*+)         (alphanum) \r\n
    , 'numlist_item'  => '/^(\s*)([a-z\d]+)'.$this->config['delimiters']['numlist'].'\s*([\s\S]+?)$/i'
                      // |     (ai1.)            (alphanum) \r\n


    // html components
    , 'html_block'    => '/^(\s*)(<[^!][\s\S]+?>)$/i'
    , 'html_comment'  => '/^<!--[\s\S]+?-->$/i'


    // inline markdown
    , 'text'          => '/^(\s*)([\s\S]+)$/i'

    , 'strong'        => '/'.$this->config['delimiters']['strong'].'([\s\S]+?)'.$this->config['delimiters']['strong'].'/i'
    , 'em'            => '/'.$this->config['delimiters']['emphasis'].'([\s\S]+?)'.$this->config['delimiters']['emphasis'].'/i'
    , 'code'          => '/'.$this->config['delimiters']['code'].'([\s\S]+?)'.$this->config['delimiters']['code'].'/i'
                      // | `(codesample)`
    , 'image'         => '/!\[([\s\S]+?)\]\(([\s\S]+?)\)(?:\[([\s\S]+?)\])?/i'
                      // | ![alt text](src /url)[attributes]
    , 'abbr'          => '/\?\[([\s\S]+?)\]\(([\s\S]+?)\)/i'
                      // | ?[alt text](src /url)[attributes]
    , 'link'          => '/\[([\s\S]+?)\]\(([\s\S]+?)\)(?:\[([\s\S]+?)\])?/i'
                      // | [link text](src /url)[attributes]

    , 'html_inline'   => '/(<[^!][\s\S]+?>)/i'

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

    // run the content through our line parser
    $this->_parseLines($content);

    // run the resulting array through the printer
    $this->_printer();

    print_r($this->_prints);

    // Return final render
    return $this->_lines;
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


  /**
   * [_parseLines description]
   * @param  [type] $lines [description]
   * @return [type]        [description]
   */
  private function _parseLines($content) {

    $results = [];

    // break up results into lines
    $lines = explode(PHP_EOL, $content);

    // iterate over lines and determine what the line says
    foreach ($lines as $line => $content) {
      $parse = [];

      // for each regex we defined
      foreach ($this->regex as $key => $regexp) {
        // parse the current line with the regex and pass $parse by reference
        $this->_scanline($key, $content, $parse);
      }

      $results[$line]['id'] = $line;
      $results[$line]['data'] = $parse;
    }

    $this->_lines = $results;
  }


  /**
   * [_scanline description]
   * @param  (string) $regex   Regex string to be passed into preg_match
   * @param  (string) $content Content we wish to parse with our Regex
   * @param  (array)  &$target A reference to the target container
   * @return n/a
   */
  private function _scanline($regex, $content, array &$target) {
    if (preg_match($this->regex[$regex], $content, $matches)) {
      $target[] = [
        'matches' => $matches
      , 'type'    => strtoupper($regex)
      ];
    }
  }




  private function _printer() {

    $results = [];
    $lines = $this->_lines;

    // parse each line definition and run through some usecases
    foreach ($lines as $lkey => $line) {

      // reset all of our testing values
      $weight = 0;
      $state  = null;
      $string = '';

      // iterate over each definition discovered in our $lines collection
      foreach ($line['data'] as $dkey => $match) {

        // Cache the type info
        $type     = $match['type'];
        $matches  = $match['matches'];
        $attrs    = '';

        // BLANK_LINE
        if ($type == self::T_BLANK_LINE){
          $string = "";
        }


        // TEXT
        if ($type == self::T_TEXT) {
          if ($weight < self::W_TEXT) {
            $weight = self::W_TEXT;
            $string = $matches[2];
          }
        }


        // RULE
        if ($type == self::T_RULE) {
          if ($weight < self::W_RULE) {
            $weight = self::W_RULE;
            $string = "<hr>";
          }
        }


        // HEADER
        if ($type == self::T_HEADER) {
          if ($weight < self::W_HEADER) {
            $weight = self::W_HEADER;

            // get the size of header
            $hsize = strlen($matches[1]);

            // check if we have attributes
            if (isset($matches[3])) {
              $attrs = " {$matches[3]}";
            } else {
              $id = strtolower(preg_replace('/\s/', '-', $matches[2]));
              $id = preg_replace('/[+=:?!]/', '', $id);
              $attrs = " id=\"{$id}\"";
            }

            // construct the header string
            $string = "<h{$hsize}{$attrs}>{$matches[2]}</h{$hsize}>";
          }
        }



        // RULE
        if ($type == self::T_CODE) {
          // TODO: CODE is only grabbing the first instance of a code string
          // TODO: CODE isn't replacing the ``'s
          $string = preg_replace('/'.$matches[1].'/i', "<code>{$matches[1]}</code>", $string);
        }


        // update the current line in our _prints property
        $this->_prints[$line['id']] = $string;

      }

    }

  }


}
