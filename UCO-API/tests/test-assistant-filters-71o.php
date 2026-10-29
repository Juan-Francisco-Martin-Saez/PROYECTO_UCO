<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/private/assistant/dictionary.php';
require_once dirname(__DIR__).'/private/assistant/catalog-filters.php';
function verify71o(bool $v,string $m):void {if(!$v)throw new RuntimeException($m);}
$r=uco_catalog_filter_request('Tomates excepto cherry');
verify71o($r['active']&&$r['positive']==='tomates'&&in_array('cherry',$r['exclude'],true),'parse');
verify71o(uco_catalog_excluded(['name'=>'Tomate cherry'],$r['exclude']),'exclude cherry');
verify71o(!uco_catalog_excluded(['name'=>'Tomate pera'],$r['exclude']),'keep pera');
verify71o(!uco_catalog_filter_request('Patatas sin gluten')['active'],'allergen safety');
verify71o(!uco_catalog_filter_request('Patatas para freír')['active'],'condition');
echo "7.1O OK\n";
