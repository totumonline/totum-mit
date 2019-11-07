<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 27.10.17
 * Time: 14:46
 */

namespace totum\common;

use totum\config\Conf;
use totum\fieldTypes\File;


class Mail
{
    static function send($to, $title, $body, $from=null, $attachmentsIn = [], $system=false)
    {
        if(method_exists(Conf::class, 'mail')){
            $attachments=[];
            foreach ($attachmentsIn as $k=>$v){
                if(!preg_match('/.+\.[a-zA-Z]{2,5}$/', $k)){
                    $attachments[preg_replace('`.*?/([^/]+\.[^/]+)$`', '$1', $v)]=$v;
                }else{
                    $attachments[$k]=$v;
                }
            }
            $body=preg_replace_callback('~src\s*=\s*([\'"]?)(?:http(?:s?)://'.Conf::getFullHostName().')?/fls/(.*?)\1~', function ($matches) use(&$attachments){
                if(!empty($matches[2]) && $file = File::getFile($matches[2])){
                    $md5=md5($matches[2]).'.'.preg_replace('/.*\.([a-zA-Z]{2,5})$/', '$1', $matches[2]);
                    $attachments[$md5]=$file;
                    return 'src="cid:'.$md5.'"';
                }
            }, $body);

           return Conf::mail($to, $title, $body, $attachments, $from, $system);
        }
    }

}