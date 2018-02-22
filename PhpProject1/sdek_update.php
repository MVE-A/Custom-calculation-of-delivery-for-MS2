<?php
define('MODX_API_MODE', true);
require dirname(dirname(__FILE__)).'/index.php';

$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_FATAL);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');
$modx->error->message = null;

    $table = $modx->getOption('table_prefix').'_sdek_cities';

    function getRemoteData() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://integration.cdek.ru/pvzlist.php');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch); 
		curl_close($ch); 
		return $result;
	}

    function xml2array($xml) {
        $data = array();
        foreach ($xml->children() as $r)  {
			$name=$r->getName();
            $attr=$r->attributes();
			$data[] = $attr['CityCode'].'#'.$attr['City'].'#'.$attr['WorkTime'].'#'.$attr['Address'].'#'.$attr['Phone'];
		}
		return $data;
    }
    
    $response = getRemoteData();
    $xml = simplexml_load_string($response);
    $data = xml2array($xml);

    $count = $close = $new = $update = 0;
    if(isset($data)) {
        #массив проверенных
        $ckd = array();
        foreach ($data as $k=>$v) {
            $arr = explode('#',$v);
            $ckd[] = $arr[0].$arr[3];
            $count++;

            $q=$modx->prepare("SELECT * FROM ".$table." WHERE code=? AND address=?");
            $q->execute(array(''.$arr[0].'',''.$arr[3].''));
            $rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows) == 0)  {
                $new++;
                $q=$modx->prepare("INSERT INTO ".$table." (id,country,city,address,phones,time,code,upd,active) VALUES (?,?,?,?,?,?,?,?,?)"); # вот эти 2 строки точно с ошибкой )
                $q->execute(array('','',''.$arr[1].'',''.$arr[3].'',''.$arr[4].'',''.$arr[2].'',''.$arr[0].'','','',));  # ибо вырваны из старого скрипта 
            }
            else {
                $update++;
                $q=$modx->prepare("UPDATE ".$table." SET phones=?,active=1");
                $q->execute(array(''.$arr[4].''));
            }
        }
    }
    
    $q=$modx->prepare("SELECT * FROM ".$table." WHERE active=1");
    $q->execute(array());
    $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $k=>$v) {
        if(!in_array($v['code'].$v['address'],$ckd)) {
            $close++;
            $q=$modx->prepare("UPDATE ".$table." SET active=0 WHERE code=? AND address=?");
            $q->execute(array(''.$v['code'].'',''.$v['address'].''));
        }
    }

?>