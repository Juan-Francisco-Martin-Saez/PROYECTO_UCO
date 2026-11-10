<?php
// Exclusivo para pruebas locales: NO desplegar como sustituto de mbstring.
if(!function_exists('mb_strlen')) { function mb_strlen($s,$encoding='UTF-8'){return strlen(iconv('UTF-8','UTF-32LE',$s))/4;} }
if(!function_exists('mb_strtolower')) { function mb_strtolower($s,$encoding='UTF-8'){return strtolower(strtr($s,['Á'=>'á','É'=>'é','Í'=>'í','Ó'=>'ó','Ú'=>'ú','Ñ'=>'ñ','Ü'=>'ü']));} }
if(!function_exists('mb_substr')) { function mb_substr($s,$start,$length=null,$encoding='UTF-8'){return implode('',array_slice(preg_split('//u',$s,-1,PREG_SPLIT_NO_EMPTY),$start,$length));} }
