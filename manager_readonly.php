<?php
declare(strict_types=1);
/* A display-only rendering of the actual dashboard. No session impersonation. */
function mg_readonly_html(string $html,string $nickname): string {
    if(!class_exists('DOMDocument'))return '<!doctype html><meta charset="utf-8"><p>서버의 PHP DOM 확장이 필요합니다.</p>';
    $doc=new DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
    try{$ok=$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    if(!$ok)return '<!doctype html><meta charset="utf-8"><p>화면을 불러오지 못했습니다.</p>';
    $xpath=new DOMXPath($doc);
    foreach($xpath->query('//script|//iframe|//object|//embed|//base|//input[@type="hidden"]|//meta[@http-equiv]|//link[not(@rel="stylesheet")]') as $el){$el->parentNode?->removeChild($el);}
    foreach($xpath->query('//*') as $el){
        foreach(iterator_to_array($el->attributes??[]) as $attribute){
            $name=strtolower($attribute->name);
            if(str_starts_with($name,'on')||in_array($name,['action','formaction','target','srcdoc','contenteditable','autofocus'],true))$el->removeAttribute($attribute->name);
        }
        if(strtolower($el->tagName)==='a'){$el->removeAttribute('href');$el->setAttribute('aria-disabled','true');$el->setAttribute('tabindex','-1');}
        if(in_array(strtolower($el->tagName),['button','input','textarea','select'],true))$el->setAttribute('disabled','disabled');
    }
    // Strip the XML encoding hint (not content) from the final HTML.
    foreach(iterator_to_array($doc->childNodes) as $child)if($child->nodeType===XML_PI_NODE)$doc->removeChild($child);
    $body=$doc->getElementsByTagName('body')->item(0);$head=$doc->getElementsByTagName('head')->item(0);
    if(!$body||!$head)return '<p>화면 구조를 확인하지 못했습니다.</p>';
    $style=$doc->createElement('style');$style->appendChild($doc->createTextNode('.manager-readonly-bar{position:sticky;top:0;z-index:2147483647;background:#174a39;color:#fff;padding:12px 20px;display:flex;align-items:center;gap:15px;font:14px/1.6 system-ui,sans-serif}.manager-readonly-bar span{flex:1}.manager-readonly-bar a{color:#fff!important;text-decoration:underline!important;white-space:nowrap}a[aria-disabled=true]{cursor:default!important}button:disabled,input:disabled,select:disabled{cursor:default!important}'));$head->appendChild($style);
    $bar=$doc->createElement('div');$bar->setAttribute('class','manager-readonly-bar');
    $text=$doc->createElement('span');$text->appendChild($doc->createTextNode($nickname.' · 건물관리 화면 (읽기 전용)'));$bar->appendChild($text);
    $back=$doc->createElement('a','지도로 돌아가기');$back->setAttribute('href','/clients_mini.php');$bar->appendChild($back);$body->insertBefore($bar,$body->firstChild);
    return $doc->saveHTML();
}
