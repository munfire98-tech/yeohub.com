<?php
declare(strict_types=1);
require_once __DIR__.'/building_dong_names.php';
/** Only the applicability answers are recorded. They do not certify installed products. */
function frgp_uses():array {
 return ['neighborhood'=>['의원·체력단련장 등','제30조제1호'],'assembly'=>['옥내 문화·집회·종교시설','제30조제2호가목·나목'],'sports'=>['옥내 운동시설(수영장 제외)','제30조제2호다목'],'medical'=>['의료시설','제30조제3호'],'dorm'=>['교육연구시설 중 합숙소','제30조제4호'],'welfare'=>['노유자시설','제30조제5호'],'lodging'=>['숙박시설·숙박 가능한 수련시설','제30조제6호·제7호'],'broadcast'=>['방송국·촬영소','제30조제8호']];
}
function frgp_validate(array $raw):array {
 $uses=$raw['uses']??null;$allowed=array_merge(array_keys(frgp_uses()),['none','unknown']);
 if(!is_array($uses)||!array_is_list($uses)||!$uses||count($uses)>8)throw new InvalidArgumentException('실제 사용 용도를 선택해 주세요.');
 foreach($uses as $use)if(!is_string($use)||!in_array($use,$allowed,true))throw new InvalidArgumentException('용도 선택을 다시 확인해 주세요.');
 $uses=array_values(array_unique($uses));
 if((in_array('none',$uses,true)||in_array('unknown',$uses,true))&&count($uses)!==1)throw new InvalidArgumentException('해당 없음·모르겠어요는 다른 용도와 함께 선택할 수 없습니다.');
 $out=['uses'=>$uses];
 foreach(['multi'=>['','yes','no','unknown'],'height'=>['','low','high','unknown'],'apartment'=>['','yes','no','unknown']] as $key=>$values){$v=$raw[$key]??'';if(!is_string($v)||!in_array($v,$values,true))throw new InvalidArgumentException('추가 조건을 다시 확인해 주세요.');$out[$key]=$v;}
 $known=array_intersect($uses,array_keys(frgp_uses()));
 if(!$known&&$out['multi']!=='yes'&&($out['multi']===''||$out['height']===''||($out['height']!=='low'&&$out['apartment']==='')))throw new InvalidArgumentException('추가 조건을 선택하거나 모르겠어요를 눌러 주세요.');
 // Irrelevant old answers must not be retained after a changed route.
 if($known){$out['multi']='';$out['height']='';$out['apartment']='';}
 elseif($out['height']==='low')$out['apartment']='';
 return $out;
}
function frgp_assess(array $answer):array {
 $reasons=[];$missing=[];
 foreach(frgp_uses() as $id=>$use)if(in_array($id,$answer['uses'],true))$reasons[]=$use[0].' · '.$use[1];
 if($answer['multi']==='yes')$reasons[]='다중이용업소 · 제30조제9호';
 if($answer['height']==='high'&&$answer['apartment']==='no')$reasons[]='11층 이상이며 아파트등에 해당하지 않음 · 제30조제10호';
 if($reasons)return ['state'=>'applies','reasons'=>$reasons,'missing'=>[]];
 if($answer['uses']!==['none'])$missing[]='실제 사용 용도';
 if($answer['multi']!=='no')$missing[]='다중이용업소 해당 여부';
 if($answer['height']!=='low'&&$answer['apartment']!=='yes')$missing[]=$answer['height']==='high'?'아파트등 해당 여부':'건물 층수·아파트등 해당 여부';
 return ['state'=>$missing?'uncertain':'outside','reasons'=>[],'missing'=>$missing];
}
function frgp_number($value):int {return preg_match('/\d+/',(string)$value,$m)?(int)$m[0]:0;}
function frgp_dongs(array $bi):array {
 $out=[];
 foreach(bdn_rows($bi) as $entry)$out[]=['id'=>$entry['key'],'name'=>$entry['name'],'use'=>(string)($entry['row']['use']??''),'floors'=>frgp_number($entry['row']['floor_a']??'')];
 if(!$out&&trim((string)($bi['bd_dongs']??''))!=='')foreach(preg_split('/\R/u',trim($bi['bd_dongs']))?:[] as $line){
  if(!preg_match('/^\s*(.*?)\s*:\s*지상\s*(\d+)\s*\/\s*지하\s*(\d+)\s*층/u',$line,$m))continue;
  $out[]=['id'=>'legacy_'.substr(hash('sha256',($bi['address']??'').'|'.$line.'|'.count($out)),0,24),'name'=>trim($m[1])?:'동명 미상','use'=>'','floors'=>(int)$m[2]];
 }
 if(!$out)$out[]=['id'=>'base_'.substr(hash('sha256',(string)($bi['address']??'')),0,24),'name'=>(string)($bi['name']??'내 건물'),'use'=>(string)($bi['use']??$bi['bd_use_main']??''),'floors'=>frgp_number($bi['floor_a']??'')];
 return $out;
}
function frgp_building_revision(array $bi):string {return hash('sha256',json_encode([(string)($bi['name']??''),(string)($bi['address']??''),frgp_dongs($bi)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
function frgp_checks(array $data):array {return array_values(array_filter((array)($data['_flame_guide']['checks']??[]),'is_array'));}
function frgp_apply(array $plan,array $dong,array $answers,string $checkedAt):array {
 $result=frgp_assess($answers);$data=(array)($plan['sections']['6']['data']??[]);$checks=frgp_checks($data);
 $record=['dong'=>$dong,'answers'=>$answers,'result'=>$result,'checked_at'=>$checkedAt,'rule_date'=>'2026-10-15'];
 $found=false;foreach($checks as &$check)if(($check['dong']['id']??'')===$dong['id']){$check=$record;$found=true;break;}unset($check);
 if(!$found)$checks[]=$record;
 if(count($checks)>300)throw new RuntimeException('저장된 동별 확인 내용이 너무 많습니다. 관리자에게 문의해 주세요.');
 $data['_flame_guide']=['version'=>1,'checks'=>$checks];
 $plan['sections']['6']=array_merge(['is_done'=>0,'is_skipped'=>0],(array)($plan['sections']['6']??[]),['data'=>$data]);
 $plan['status']='draft';$plan['updated_at']=$checkedAt;unset($plan['final_review']);
 return $plan;
}
function frgp_label(string $state):string {return ['applies'=>'선택 조건상 방염 적용 대상','outside'=>'선택 조건상 제30조 대상에 해당하지 않음','uncertain'=>'추가 확인 필요'][$state]??'추가 확인 필요';}
function frgp_text(array $check):string {
 $r=(array)($check['result']??[]);$a=(array)($check['answers']??[]);$d=(array)($check['dong']??[]);
 $lines=['['.(string)($d['name']??'확인한 동').'] '.frgp_label((string)($r['state']??''))];
 $context=array_filter([(string)($d['use']??''),!empty($d['floors'])?'지상 '.(int)$d['floors'].'층':'']);
 if($context)$lines[]='등록 정보: '.implode(' · ',$context);
 $conditions=[];$uses=frgp_uses();foreach((array)($a['uses']??[]) as $use)$conditions[]=$uses[$use][0]??(['none'=>'제시된 대상 용도에 해당 없음','unknown'=>'용도 미확인'][$use]??'미확인');
 foreach(['multi'=>['yes'=>'다중이용업소 해당','no'=>'다중이용업소 해당 없음','unknown'=>'다중이용업소 여부 미확인'],'height'=>['low'=>'10층 이하','high'=>'11층 이상','unknown'=>'층수 미확인'],'apartment'=>['yes'=>'아파트등 해당','no'=>'아파트등 해당 없음','unknown'=>'아파트등 여부 미확인']] as $k=>$labels)if(isset($labels[$a[$k]??'']))$conditions[]=$labels[$a[$k]];
 if($conditions)$lines[]='확인 조건: '.implode(' / ',$conditions);
 if(!empty($r['reasons']))$lines[]='판단 근거: '.implode(' / ',$r['reasons']);
 if(!empty($r['missing']))$lines[]='추가 확인할 사항: '.implode(' / ',$r['missing']);
 $lines[]='확인일시: '.(string)($check['checked_at']??'').' · 기준: 소방시설법 시행령 제30조 ('.(string)($check['rule_date']??'').' 확인)';
 return implode("\n",$lines);
}
