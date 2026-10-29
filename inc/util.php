<?php
// /inc/util.php
date_default_timezone_set('Asia/Seoul');

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function read_posts($dir){
    $files = glob($dir.'/*.json');
    $posts = [];
    foreach ($files as $file){
        $json = file_get_contents($file);
        $row = json_decode($json, true);
        if (!$row) continue;
        $row['__file'] = basename($file, '.json');
        $posts[] = $row;
    }
    usort($posts, function($a,$b){
        return ($b['created_ts'] ?? 0) <=> ($a['created_ts'] ?? 0);
    });
    return $posts;
}

function make_id(){
    return date('Ymd_His').'_'.bin2hex(random_bytes(4)); // e.g., 20250812_173020_ab12cd34
}
