<?php

class RSSFeed
{
  public $Articles = array();

  // Channel info
  public $title = '';
  public $link = '';
  public $description = '';
  public $optional = array();
  public $image = array('url' => '', 'title' => '', 'link' => '', 'description' => '', 'w' => 0, 'h' => 0);

  public function __construct($title, $link, $description, $optional = '')
  {
    $this->title = $title;
    $this->link = $link;
    $this->description = $description;

    if (is_array($optional) && count($optional)) {
      $this->optional = $optional;
    }
  }

  function AddOptional($key, $value)
  {
    $this->optional[$key] = $value;
  }

  function AddImage($title, $url, $link, $description = '')
  {
    $this->image['title'] = $title;
    $this->image['url'] = $url;
    $this->image['link'] = $link;
    $this->image['description'] = $description;

    if( $tmp = @getimagesize($url) ){
      $this->image['w'] = ($tmp[0] > 144) ? 144 : $tmp[0];
      $this->image['h'] = ($tmp[1] > 400) ? 400 : $tmp[1];
    }
  }

function AddArticle($title, $link, $description, $optional = '')
    {
    // inserisce un nuovo articolo
    $i = array_push($this->Articles, array('title' => $title, 'link' => $link, 'description' => $description));

    // aggiunge le coppie chiave valore opzionali
    // all'articolo appena inserito
    if (is_array($optional) && count($optional))
        {
        --$i;
        foreach($optional as $k=>$v)
            {
            $this->Articles[$i][$k] = $v;
            }
        }
    }

  function Output($save = false, $path = '')
  {
    $out = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n" . 
        '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n" . 
        '<channel>' . "\n";
    global $rss_ttl,$baseurl;
    $parameters=array();
    foreach ($_GET as $key=>$value){
        $parameters[$key]=$value;
    }
    $parameters=http_build_query($parameters,'','&amp;');
    global $baseurl;
    
    $out .= "<title>$this->title</title>\n" .
        "<link>$this->link</link>\n" .
        "<description>$this->description</description>\n".
        "<ttl>".$rss_ttl."</ttl>\n".
        '<atom:link href="'.$baseurl.'/plugins/rss2/pages/rssfilter.php?'.$parameters.'" rel="self" type="application/rss+xml" />';

    // Channel optionals
    if (is_array($this->optional) && count($this->optional))
    {
      foreach($this->optional as $k => $v) {
        $out .= "<$k>$v</$k>\n";
      }
    }

    // Image
    if ($this->image['title'] && $this->image['url'] && $this->image['link'])
    {
      $out .= "<image>\n" .
      "<title>" . $this->image['title'] . "</title>\n" .
      "<url>" . $this->image['url'] . "</url>\n" .
      "<link>" . $this->image['link'] . "</link>\n";

      if( $this->image['description'] ) {
        $out .= "<description>" . $this->image['description'] . "</description>\n";
      }

      if ($this->image['w'] && $this->image['h']) {
        $out .= "<width>" . $this->image['w'] . "</width>\n" .
            "<height>" . $this->image['h'] . "</height>\n";
      }
      $out .= "</image>\n";
    }

    // per ogni item stampa tutte le coppie chiave valore
    for ($i = 0, $c = count($this->Articles); $i < $c; $i++)
        {
        $out .= "<item>\n";
        foreach($this -> Articles[$i] as $k=>$v)
            {
            $k1=$k;
            if ($k=="guid"){$k1='guid isPermaLink="false"';}
            $out .= "<".$k1.">".$v."</".$k.">\n";
            }
        $out .= "</item>\n";
        }

    $out .= "</channel>\n</rss>";

    // True output
    if (!$save || !$path ) {
      header("Content-type: application/xml");
      echo trim($out);
      return true;
    }
    else
    {
      $fh = fopen($path, 'w');
      if($fh) {
        fwrite($fh, $out);
        fclose($fh); 
        return true;
      }
      return false;
    }
  }
}

