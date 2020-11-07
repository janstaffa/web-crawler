<?php
    
    $start = "https://www.w3schools.com/";

    $pdo = new PDO('mysql:host=127.0.0.1;dbname=search','root','');
    
    
    $crawled = array();
    $crawling = array();
    
    function crawl_details($url){
        $options = array('http'=>array('method'=>"GET", 'headers'=>"User-Agent: janstaffa.cz"));
        $context = stream_context_create($options);
        
        $doc = new DOMDocument();
        @$doc->loadHTML(@file_get_contents($url, false, $context));
        
        $title = $doc->getElementsByTagName("title");
        $title = $title->item(0)->nodeValue;
        
        $description = "";
        $keywords = "";
        $metas = $doc->getElementsByTagName("meta");
        for($i=0;$i<$metas->length;$i++){
            $meta = $metas->item($i);
            
            if($meta->getAttribute("name") == strtolower("description")){
              $description = $meta->getAttribute("content");
            }
            if($meta->getAttribute("name") == strtolower("keywords")){
              $keywords = $meta->getAttribute("content");
            }
        }
        

        return '{"Title": "'.str_replace("\n", "", $title).'", "Description": "'.str_replace("\n", "", $description).'", "Keywords": "'.str_replace("\n", "", $keywords).'", "URL": "'.$url.'"}';
    }
    



  function extractCommonWords($url){
      $text = $pars = $text = $par = "";
      $string = $matchWords = $key = $item = $wordCountArr = $val = "";

      $doc = new DOMDocument();
      @$doc->loadHTML(file_get_contents($url));
      
      $pars = $doc->getElementsByTagName("p");
      foreach($pars as $par){
          $par = $par->nodeValue;
          strtolower(trim($par, ' .,!?()'));
          $text .= $par;
      }
      $text = preg_replace('/\s\s+/i', '', $text);
      $text = preg_replace('/[^a-zA-Z0-9 -]/', '', $text);
      $text = strtolower(trim($text, ' .,!?()'));
      

      $stopWords = array('i','a','about','an','and','are','as','at','be','by','com','de','en','for','from','how','in','is','it','la','of','on','or','that','the','this','to','was','what','when','where','who','will','with','und','the','www');

      $text = preg_replace('/\s\s+/i', '', $text); // replace whitespace
      $text = trim($text); // trim the string
      $text = preg_replace('/[^a-zA-Z0-9 -]/', '', $text); // only take alphanumerical characters, but keep the spaces and dashes too…
      $text = strtolower($text); // make it lowercase
      preg_match_all('/\b.*?\b/i', $text, $matchWords);
      $matchWords = $matchWords[0];

      foreach($matchWords as $key=>$item) {
          if($item == '' || in_array(strtolower($item), $stopWords) || strlen($item) <= 3) {
              unset($matchWords[$key]);
          }
      }   
      $wordCountArr = array();
      if(is_array($matchWords)){
          foreach ($matchWords as $key => $val){
              $val = strtolower($val);
              if(isset($wordCountArr[$val])){
                  $wordCountArr[$val]++;
              } else {
                  $wordCountArr[$val] = 1;
              }
          }
      }
      arsort($wordCountArr);
      $wordCountArr = array_slice($wordCountArr, 0, 5);
      $wordCountArr = array_keys($wordCountArr);
      
      echo implode(", ", $wordCountArr)."\n";

      return implode(", ", $wordCountArr);
  }
      



    function crawl($url){
        
        global $crawled;
        global $crawling;
        global $pdo;
        
        $options = array('http'=>array('method'=>"GET", 'headers'=>"User-Agent: search.g6.cz"));
        $context = stream_context_create($options);
        
        $doc = new DOMDocument();
        @$doc->loadHTML(@file_get_contents($url, false, $context));
        
        $links = $doc->getElementsByTagName("a");
        
        foreach($links as $link){
            $l = $link->getAttribute("href");
            
            if(strpos($l, '.jpg') || strpos($l, '.png') || strpos($l, '.gif') || strpos($l, '.jpeg') || strpos($l, '.ico') || strpos($l, '.mp4') || strpos($l, '.mp3') || strpos($l, '.svg')){
              continue;
            }else if(substr($l, 0, 1) == "/" && substr($l, 0, 2) != "//"){
              $l = parse_url($url)["scheme"]."://".parse_url($url)["host"].$l;  
            }else if(substr($l, 0, 2) == "//"){
              $l = parse_url($url)["scheme"].":".$l;  
            }else if(substr($l, 0, 2) == "./"){
              $l = parse_url($url)["scheme"]."://".parse_url($url)["host"].dirname(parse_url($url)["path"]).substr($l, 1);  
            }else if(substr($l, 0, 1) == "#"){
              continue;
            }else if(substr($l, 0, 3) == "../"){
              $l = parse_url($url)["scheme"]."://".parse_url($url)["host"]."/".$l;  
            }else if(substr($l, 0, 11) == "javascript:"){
              continue;
            }else if(substr($l, 0, 5) != "https" && substr($l, 0, 4) != "http"){
              $l = parse_url($url)["scheme"]."//:".parse_url($url)["host"]."/".$l;  
            }

            
            if(!in_array($l, $crawled)){
              $crawled[] = $l;
              $crawling[] = $l;
              //echo crawl_details($l);
              
              @$details = json_decode(crawl_details($l));
              extractCommonWords($details->URL);

              echo $details->URL."\n";
              //print_r($details) . "\n";
              

               $rows = $pdo->query("SELECT * FROM `searchdata` WHERE url_hash = '".md5($details->URL)."'");
               $rows = $rows->fetchColumn();

               
               $params = array(':title' => $details->Title, ':description' => $details->Description, ':keywords' => $details->Keywords ." ". extractCommonWords($l), ':url' => $details->URL, ':url_hash' => md5($details->URL));
              
               if($rows > 0){
                    if(!is_null($params[':title']) && !is_null($params[':description']) && $params[':title'] != ''){
                        $result = $pdo->prepare("UPDATE `searchdata` SET title=:title, description=:description, keywords=:keywords, url=:url, url_hash=:url_hash WHERE url_hash = :url_hash");
                        $result = $result->execute($params);           
                    }
               }else{
                    if(!is_null($params[':title']) && !is_null($params[':description']) && $params[':title'] != ''){
                        $result = $pdo->prepare("INSERT INTO `searchdata` VALUES ('', :title, :description, :keywords, :url, :url_hash)");
                        $result = $result->execute($params);           
                    }
               }
            }
        }
        array_shift($crawling);
        foreach($crawling as $site){
            crawl($site);
        }
        
    }
    crawl($start);
    
?>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Search engine</title>
    </head>
</html>