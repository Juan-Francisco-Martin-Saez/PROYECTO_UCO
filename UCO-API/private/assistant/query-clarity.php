<?php
declare(strict_types=1);
/** Pure detection: ambiguity does not authorize a query or a data change. */
function uco_query_clarity(string $question):array {
 $n=uco_norm($question);
 $hasOr=(bool)preg_match('/(?:^| )(?:o|u)(?: |$)/u',$n);
 $hasExclusion=(bool)preg_match('/(?:^| )(?:sin|excepto|excluyendo|menos)(?: |$)|(?:^| )no quiero(?: |$)/u',$n);
 $hasComparison=(bool)preg_match('/(?:^| )(?:comparar|comparame|diferencia|versus|vs|mejor)(?: |$)/u',$n);
 $hasCondition=(bool)preg_match('/(?:^| )(?:con|para|de)(?: |$)/u',$n);
 $hasMultiRequest=(bool)preg_match('/(?:^| )(?:y tambien|ademas|por otro lado)(?: |$)/u',$n);
 return ['alternative'=>$hasOr,'exclusion'=>$hasExclusion,'comparison'=>$hasComparison,
  'condition'=>$hasCondition,'multiple_requests'=>$hasMultiRequest];
}
