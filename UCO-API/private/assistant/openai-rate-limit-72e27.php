<?php
declare(strict_types=1);
/** Limitador por IP del servidor (sin confiar en X-Forwarded-For). Devuelve false si no puede proteger. */
function ucoOpenAIRateAllow72e27(string $directory, string $ip, int $limit=10, int $window=60): bool {
    if ($ip==='' || $limit<1 || $window<1) return false;
    if (!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory)) return false;
    $filename=rtrim($directory,'/').'/'.hash('sha256',$ip).'.json';
    $handle=@fopen($filename,'c+');
    if ($handle===false) return false;
    try {
        if (!flock($handle,LOCK_EX)) return false;
        @chmod($filename,0600);
        rewind($handle);
        $raw=stream_get_contents($handle,16384);
        $history=json_decode(is_string($raw)?$raw:'',true);
        if (!is_array($history)) $history=[];
        $now=time();
        $recent=[];
        foreach($history as $t) if(is_int($t) && $t>$now-$window && $t<=$now) $recent[]=$t;
        $allowed=count($recent)<$limit;
        if($allowed) $recent[]=$now;
        rewind($handle);
        if(!ftruncate($handle,0))return false;
        $encoded=json_encode($recent);
        if(!is_string($encoded)||fwrite($handle,$encoded)!==strlen($encoded))return false;
        fflush($handle);
        return $allowed;
    } finally {
        flock($handle,LOCK_UN);
        fclose($handle);
    }
}
