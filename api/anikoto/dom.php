<?php
function domLoad($html) {
  $doc = new DOMDocument();
  libxml_use_internal_errors(true);
  // Avoid deprecated mb_convert_encoding HTML-ENTITIES handling; use XML prolog for UTF-8
  $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_PARSEHUGE);
  libxml_clear_errors();
  return $doc;
}

function cssToXPath($selector) {
  $selectors = explode(',', $selector);
  $parts = [];
  foreach ($selectors as $s) {
    $s = trim($s);
    if ($s === '') continue;
    $parts[] = _cssSingleToXPath($s);
  }
  return implode(' | ', $parts);
}

function _cssSingleToXPath($selector) {
  $selector = trim(preg_replace('/\s+/', ' ', $selector));
  $xpath = '//';
  $segments = preg_split('/\s*(?=>)\s*|\s+/', $selector);

  $isChild = false;
  $first = true;
  foreach ($segments as $seg) {
    if ($seg === '') continue;
    if ($seg === '>') { $isChild = true; continue; }

    if (!$first) {
      $xpath .= $isChild ? '/' : '//';
    }
    $first = false;
    $isChild = false;

    $xpath .= _parseSegment($seg);
  }

  return $xpath;
}

function _parseSegment($seg) {
  $tag = '*';
  $conditions = [];

  preg_match('/^([a-zA-Z0-9_-]+|\*)/', $seg, $m);
  if (!empty($m)) {
    $tag = $m[1];
    $seg = substr($seg, strlen($m[0]));
  }

  while ($seg !== '') {
    if ($seg[0] === '#') {
      preg_match('/^#([a-zA-Z0-9_-]+)/', $seg, $m);
      if (!empty($m)) {
        $conditions[] = "@id='" . $m[1] . "'";
        $seg = substr($seg, strlen($m[0]));
      }
    } elseif ($seg[0] === '.') {
      preg_match('/^\.([a-zA-Z0-9_-]+)/', $seg, $m);
      if (!empty($m)) {
        $conditions[] = "contains(concat(' ',normalize-space(@class),' '),' " . $m[1] . " ')";
        $seg = substr($seg, strlen($m[0]));
      }
    } elseif ($seg[0] === '[') {
      preg_match('/^\[([a-zA-Z0-9_-]+)([~|^$*]?=)?["\']?([^\]"\']*)["\']?\]/', $seg, $m);
      if (!empty($m)) {
        $attr = $m[1];
        $op = $m[2] ?? '';
        $val = $m[3] ?? '';
        if ($op === '') {
          $conditions[] = "@$attr";
        } else {
          $val = str_replace("'", "&apos;", $val);
          switch ($op) {
            case '=': $conditions[] = "@$attr='$val'"; break;
            case '~=': $conditions[] = "contains(concat(' ',normalize-space(@$attr),' '),' $val ')"; break;
            case '|=': $conditions[] = "(@$attr='$val' or starts-with(@$attr,'$val-'))"; break;
            case '^=': $conditions[] = "starts-with(@$attr,'$val')"; break;
            case '$=': $conditions[] = "substring(@$val,string-length(@$attr)-string-length('$val')+1)='$val'"; break;
            case '*=': $conditions[] = "contains(@$attr,'$val')"; break;
          }
        }
        $seg = substr($seg, strlen($m[0]));
      } else {
        break;
      }
    } elseif ($seg[0] === ':') {
      preg_match('/^:([a-zA-Z-]+)(?:\(([^)]*)\))?/', $seg, $m);
      if (!empty($m)) {
        $pseudo = $m[1];
        $arg = $m[2] ?? '';
        switch ($pseudo) {
          case 'first-child': $conditions[] = 'position()=1'; break;
          case 'last-child': $conditions[] = 'position()=last()'; break;
          case 'first-of-type': $conditions[] = 'position()=1'; break;
          case 'last-of-type': $conditions[] = 'position()=last()'; break;
          case 'only-child': $conditions[] = 'last()=1'; break;
          case 'empty': $conditions[] = 'not(node())'; break;
          case 'not':
            $arg = trim($arg);
            if (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $arg, $am)) {
              $conditions[] = "not(contains(concat(' ',normalize-space(@class),' '),' {$am[1]} '))";
            } elseif (preg_match('/^#([a-zA-Z0-9_-]+)$/', $arg, $am)) {
              $conditions[] = "not(@id='{$am[1]}')";
            } else {
              $conditions[] = "not(self::$arg)";
            }
            break;
          case 'nth-child':
            if ($arg === 'n') break;
            if ($arg === '2n' || $arg === 'even') { $conditions[] = 'position() mod 2=0'; break; }
            if ($arg === '2n+1' || $arg === 'odd') { $conditions[] = 'position() mod 2=1'; break; }
            if (is_numeric($arg)) { $conditions[] = "position()=$arg"; break; }
            if (preg_match('/^(\d*)n(?:\+(\d+))?$/', $arg, $am)) {
              $a = $am[1] === '' ? 1 : (int)$am[1];
              $b = isset($am[2]) ? (int)$am[2] : 0;
              $conditions[] = "(position() - $b) mod $a = 0 and position() >= $b";
            }
            break;
        }
        $seg = substr($seg, strlen($m[0]));
      } else {
        break;
      }
    } else {
      break;
    }
  }

  $result = $tag;
  if (!empty($conditions)) {
    $result .= '[' . implode(' and ', $conditions) . ']';
  }
  return $result;
}

function domText($doc, $selector) {
  $xpath = new DOMXPath($doc);
  $nodes = $xpath->query(cssToXPath($selector));
  if (!$nodes || $nodes->length === 0) return null;
  return trim($nodes->item(0)->textContent);
}

function domAttr($doc, $selector, $attrName) {
  $xpath = new DOMXPath($doc);
  $nodes = $xpath->query(cssToXPath($selector));
  if (!$nodes || $nodes->length === 0) return null;
  $el = $nodes->item(0);
  if (!$el->hasAttribute($attrName)) return null;
  return $el->getAttribute($attrName);
}

function domEach($doc, $selector, $fn) {
  $xpath = new DOMXPath($doc);
  $nodes = $xpath->query(cssToXPath($selector));
  $results = [];
  if ($nodes) {
    $i = 0;
    foreach ($nodes as $node) {
      $wrapper = new DOMNodeWrapper($node, $doc);
      $results[] = $fn($wrapper, $i);
      $i++;
    }
  }
  return $results;
}

function domFirst($doc, $selector) {
  $xpath = new DOMXPath($doc);
  $nodes = $xpath->query(cssToXPath($selector));
  if (!$nodes || $nodes->length === 0) return null;
  return new DOMNodeWrapper($nodes->item(0), $doc);
}

class DOMNodeWrapper {
  private $node;
  private $doc;

  public function __construct($node, $doc) {
    $this->node = $node;
    $this->doc = $doc;
  }

  public function attr($name) {
    return $this->node->hasAttribute($name) ? $this->node->getAttribute($name) : null;
  }

  public function text() {
    return trim($this->node->textContent);
  }

  public function html() {
    return $this->node->ownerDocument->saveHTML($this->node);
  }

  public function find($selector) {
    $xpath = new DOMXPath($this->doc);
    $expr = cssToXPath($selector);
    $context = $this->node;
    $parts = explode(' | ', $expr);
    foreach ($parts as &$part) {
      if (strpos($part, '//') === 0) {
        $part = '.' . $part;
      } elseif (strpos($part, '/') !== 0) {
        $part = './/' . $part;
      }
    }
    $expr = implode(' | ', $parts);
    $nodes = $xpath->query($expr, $context);
    $results = [];
    if ($nodes) {
      foreach ($nodes as $node) {
        $results[] = new DOMNodeWrapper($node, $this->doc);
      }
    }
    return $results;
  }

  public function children() {
    $results = [];
    foreach ($this->node->childNodes as $child) {
      if ($child->nodeType === XML_ELEMENT_NODE) {
        $results[] = new DOMNodeWrapper($child, $this->doc);
      }
    }
    return $results;
  }

  public function parent() {
    if ($this->node->parentNode) {
      return new DOMNodeWrapper($this->node->parentNode, $this->doc);
    }
    return null;
  }

  public function hasClass($className) {
    $class = $this->node->getAttribute('class');
    return preg_match('/\b' . preg_quote($className, '/') . '\b/', $class);
  }

  public function getNode() {
    return $this->node;
  }

  public function __toString() {
    return $this->text();
  }
}

function domNum($str) {
  if ($str === null || $str === '') return null;
  $n = (int)trim($str);
  return $n;
}

function domFindText($doc, $baseSelector, $label) {
  $xpath = new DOMXPath($doc);
  $bases = $xpath->query(cssToXPath($baseSelector));
  if (!$bases || $bases->length === 0) return null;
  foreach ($bases as $base) {
    foreach ($base->childNodes as $child) {
      if ($child->nodeType === XML_ELEMENT_NODE) {
        $text = trim($child->textContent);
        if (strpos($text, $label) === 0) {
          $spans = $child->getElementsByTagName('span');
          if ($spans->length > 0) {
            $clone = $spans->item(0)->cloneNode(true);
            $tmp = new DOMDocument();
            $tmp->appendChild($tmp->importNode($clone, true));
            $x = new DOMXPath($tmp);
            foreach ($x->query('//a') as $a) $a->parentNode->removeChild($a);
            $result = trim($tmp->textContent);
            if ($result !== '') return $result;
            $as = $spans->item(0)->getElementsByTagName('a');
            if ($as->length > 0) return trim($as->item(0)->textContent);
          }
          return trim(str_replace($label, '', $text));
        }
      }
    }
  }
  return null;
}

function domFindLinks($doc, $baseSelector, $label) {
  $xpath = new DOMXPath($doc);
  $bases = $xpath->query(cssToXPath($baseSelector));
  $results = [];
  if (!$bases || $bases->length === 0) return $results;
  foreach ($bases as $base) {
    foreach ($base->childNodes as $child) {
      if ($child->nodeType === XML_ELEMENT_NODE) {
        $text = trim($child->textContent);
        if (strpos($text, $label) === 0) {
          $as = $child->getElementsByTagName('a');
          foreach ($as as $a) {
            $results[] = trim($a->textContent);
          }
          return $results;
        }
      }
    }
  }
  return $results;
}
