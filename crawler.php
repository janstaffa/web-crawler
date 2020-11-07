<?php
    //starting seed ex.:http://janstaffa.cz/
    $start = "//START_SEED//";
    
    //connect to DB
    $pdo = new PDO('mysql:host=//SERVER//;dbname=//DB_NAME//','//USER//','//PASSWORD//');
    
    
    $crawled = array();
    $crawling = array();
    //script starts at function 'crawl'
    function crawl_details($url){
        //context options
        $options = array('http'=>array('method'=>"GET", 'headers'=>"User-Agent: janstaffa.cz"));
        $context = stream_context_create($options);
        
        //create DOMDocument from url(seed)
        $doc = new DOMDocument();
        @$doc->loadHTML(@file_get_contents($url, false, $context));
        
        //get the value of 'title'
        $title = $doc->getElementsByTagName("title");
        $title = $title->item(0)->nodeValue;
        
        //get description, keywords from metas
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
        
        //return object with values
        return '{"Title": "'.str_replace("\n", "", $title).'", "Description": "'.str_replace("\n", "", $description).'", "Keywords": "'.str_replace("\n", "", $keywords).'", "URL": "'.$url.'"}';
    }
    



  function extractCommonWords($url){
      $text = $pars = $text = $par = "";
      $string = $matchWords = $key = $item = $wordCountArr = $val = "";

      //create DOMDocument from url(seed)
      $doc = new DOMDocument();
      @$doc->loadHTML(file_get_contents($url));
      
      //get all 'p' tags
      $pars = $doc->getElementsByTagName("p");
      //iterate through them
      foreach($pars as $par){
          $par = $par->nodeValue;
          //remove all unwanted symbols(.,!?())
          strtolower(trim($par, ' .,!?()'));
          //add to '$text'
          $text .= $par;
      }
      //remove whitespaces
      $text = preg_replace('/\s\s+/i', '', $text);
      //remove remaining unwanted symbols
      $text = preg_replace('/[^a-zA-Z0-9 -]/', '', $text);
      //trim and convert to lower
      $text = strtolower(trim($text, ' .,!?()'));
      
      //words not to be included as common
      $stopWords = array('i','a','about','an','and','are','as','at','be','by','com','de','en','for','from','how','in','is','it','la','of','on','or','that','the','this','to','was','what','when','where','who','will','with','und','the','www');


      $text = preg_replace('/\s\s+/i', '', $text);
      $text = trim($text);
      $text = preg_replace('/[^a-zA-Z0-9 -]/', '', $text);
      $text = strtolower($text);
      //match all words and put into array
      preg_match_all('/\b.*?\b/i', $text, $matchWords);
      $matchWords = $matchWords[0];

      //iterate '$matchWords'
      foreach($matchWords as $key=>$item) {
          //exclude null values, stopwords and words shorter than 3 chars
          if($item == '' || in_array(strtolower($item), $stopWords) || strlen($item) <= 3) {
              unset($matchWords[$key]);
          }
      }   
      $wordCountArr = array();
      if(is_array($matchWords)){
          //iterate again
          foreach ($matchWords as $key=>$val){
              $val = strtolower($val);
              //if the word is already in the array add 1 else add it to the array
              if(isset($wordCountArr[$val])){
                  $wordCountArr[$val]++;
              } else {
                  $wordCountArr[$val] = 1;
              }
          }
      }
      //sort '$wordCountArr' by '$val'
      arsort($wordCountArr);
      //get top 5
      $wordCountArr = array_slice($wordCountArr, 0, 5);
      //get the keys(words)
      $wordCountArr = array_keys($wordCountArr);
      
      //optional - when using console to run the sript it echos the common words it found on the page
      //echo implode(", ", $wordCountArr)."\n";

      //return top 5 words separated by ','
      return implode(", ", $wordCountArr);
  }
      



    function crawl($url){
        
        global $crawled;
        global $crawling;
        global $pdo;
        
        //context options
        $options = array('http'=>array('method'=>"GET", 'headers'=>"User-Agent: search.g6.cz"));
        $context = stream_context_create($options);
        
        //create DOMDocument from url(seed)
        $doc = new DOMDocument();
        @$doc->loadHTML(@file_get_contents($url, false, $context));
        
        //locate all links on the page
        $links = $doc->getElementsByTagName("a");
        
        //iterate through the links
        foreach($links as $link){
            //get href attribute
            $l = $link->getAttribute("href");
            
            //complete/exclude all weird links
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

            //check if the link was already crawled
            if(!in_array($l, $crawled)){
              $crawled[] = $l;
              $crawling[] = $l;
              
              //get page details(meta tags, tile, description,...)
              @$details = json_decode(crawl_details($l));
              
              //call 'extractCommonWords' function -> get all commonly repeated words from the page
              extractCommonWords($details->URL);

              //optional - when using console to run the sript it echos the current page that is being crawled
              //echo $details->URL."\n";
              
               //check if DB already contains this url
               $rows = $pdo->query("SELECT * FROM `searchdata` WHERE url_hash = '".md5($details->URL)."'");
               $rows = $rows->fetchColumn();

               //bind variables
               $params = array(':title' => $details->Title, ':description' => $details->Description, ':keywords' => $details->Keywords ." ". extractCommonWords($l), ':url' => $details->URL, ':url_hash' => md5($details->URL));
              
               //UPDATE or INSERT into DB
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
        //remove the link from '$crawling' array - the link was already crawled so don't return to it
        array_shift($crawling);
        //crawl the rest of the links
        foreach($crawling as $site){
            crawl($site);
        }
        
    }
    //start the script
    crawl($start);
    
    $pdo=null;
?>
