<?php


function get_news($ref="",$recent="",$findtext="")
    {
    # Returns a list of all news items.
    # If $find is specified a search is performed across title and body fields
    
    $findtext=trim($findtext);
    debug ($recent);
    $sql="news n ";
    $params = [];
    if ($ref!="" || $findtext!=""){
        $sql.=" where (";
    }
    
    if ($ref!=""){$sql.="ref= ?"; $params[] = 'i'; $params[] = $ref;}
    
    if ($findtext!="") {
        $findtextarray=explode(" ",$findtext);
        if ($ref!=""){$sql.=" and (";}
        for ($n=0;$n<count($findtextarray);$n++){
          $sql.=' body like ?'; $params[] = 's'; $params[] = '%'.$findtextarray[$n].'%';
          if ($n+1!=count($findtextarray)){$sql.=" and ";}
        }
        $sql.=") or (";
        for ($n=0;$n<count($findtextarray);$n++){
          $sql.=' title like ?'; $params[] = 's'; $params[] = '%'.$findtextarray[$n].'%';
          if ($n+1!=count($findtextarray)){$sql.=" and ";}
        }
        if ($ref!=""){$sql.=")";}
        }
        
    if ($ref!="" || $findtext!=""){
        $sql.=" ) ";
    }
    
    $sql.=" order by date desc, ref desc";
    if ($recent!="")
        {
        $sql.=" limit 0, ?";
        $params = array_merge($params, ['i', $recent]);
        }
    
    return ps_query ("select distinct ref, date, title, body from $sql", $params);
    }
    
function get_news_headlines($ref="",$recent="")
    {
    # Returns a list of news headlines. 
    $sql="news n ";
    $params = [];
    if ($ref!=""){
        $sql.=" where ref= ?";
        $params = ['i', $ref];
    }
                    
    $sql.=" order by date desc, ref desc";
    if ($recent!="")
        {
        $sql.=" limit 0, ?";
        $params = array_merge($params, ['i', $recent]);
        }   

    return ps_query ("select distinct ref, date, title, body from $sql", $params);
    }
    
    
function get_news_ref($maxmin)
    {
    # Returns a reference to the latest or oldest news headline.    
    if(strtolower($maxmin) != 'min' && strtolower($maxmin) != 'max'){return;}
    return ps_query ("select " . $maxmin ."(ref) from news n");
    }
    
function delete_news($ref)
    {
    # Deletes the news item with reference $ref
    ps_query("delete from news where ref= ?", ['i', $ref]);
    }
    
function add_news($date,$title,$body)
    {
    # Saves the news item with reference $ref
    ps_query("insert into news (title,body,date) values (?, ?, ?)", ['s', $title, 's', $body, 's', $date]);
    }
    
function update_news($ref,$date,$title,$body)
    {
    # Updates the news item with reference $ref
    ps_query("update news set title= ?, body= ?, date= ? where ref= ?", ['s', $title, 's', $body, 's', $date, 'i', $ref]);
    }
