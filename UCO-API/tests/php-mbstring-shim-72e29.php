<?php
// SOLO PARA PRUEBAS LOCALES: NO instalar en producción.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $encoding=null): int { return strlen(iconv('UTF-8','UTF-32LE',$s))/4; }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s,int $start,?int $length=null,?string $encoding=null): string {
        $arr=preg_split('//u',$s,-1,PREG_SPLIT_NO_EMPTY);
        return implode('',array_slice($arr,$start,$length));
    }
}
