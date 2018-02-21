<?php
# класс рассчёта стоимости доставки - ПРИМЕР
# используется СДЭК , почта России (через post-api.ru)

/* Особенности
    Тут описываем ключевые моменты 
*/


class DC {
    
    public $modx;
    private $px; #префикс таблиц modx
    private $cart; #массив корзины MS2
    private $goods; #массив - состав корзины с расчётом веса и объёма по каждой позиции
    private $order; #массив заказа MS2
    private $delivery; # объект выбранного метода доставки MS2
    private $payment; # объект выбранного метода доставки MS2
    
    private $errorMessage = 'Не удалось рассчитать стоимость доставки по заданному направлению в автоматическом режиме.<br> 
            Наш менеджер сообщит Вам стоимость доставки после отправки данного заказа.';
    
    private $config = array(
        'SDEK' => array (
            'version' => '1.0'
	        ,'dateExecute' => '' 
	        ,'authLogin' => 'xxxxxxxxxxxxxxxxxxx'       
	        ,'Secure_password' => 'xxxxxxxxxxxxxxxxxx'
	        ,'secure' => ''
	        ,'senderCityId' => 245 # Тверь
	        ,'receiverCityId' => ''
	        ,'goods' => array( # дефолтные данные товаров, если нужно (например в MS не указаны параметры для товара)
	            array(
	                'weight' => 0.5
	                ,'volume' => 0.001
	                ,'length' => 25
	                ,'width' => 25
	                ,'height' => 20
	            )
	        )
	        ,'tariffId' => 137 # 137 - тариф по умолчанию - "Посылка склад-дверь" (курьер); 136 - "Посылка склад-склад"(самовывоз из пункта доставки)
	        ,'markup' => 6 # %
        ),
        'POST_OFFICE'  => array (
            'active' => 1 # показывать или нет доставку почтой
            ,'key' => 'xxxxxxxxxxxxxxxx'
            ,'maxLengthEnd' => 2.7 # метры. Максимальная длина посылки. Если превышена - почта РФ исключается.
            ,'addPriceLength' => 0.53 # метры. При длине посылке >= указанной, прирасчёте цены используется коэффициент kpl
            ,'kpl' => 1.4
        )
    );
    
    
    private $dimensions = array(
        'maxLenght' => 0.2
        ,'weight' => 0
        ,'volume' => 0
        ,'cost' => 0
        ,'goods' => array()
    ); 
    
        
    private $result;
    
    
    
    function __construct(modX &$modx, $cart, $order, $delivery) {
        $this->modx =& $modx;
        $this->px = $this->modx->getOption('table_prefix');
        
        $this->cart = $cart;
        $this->order = $order;
        $this->delivery = $delivery;
        $this->payment = $this->modx->getObject('msPayment', array('id' => $this->order['payment']));
        
        $this->config['SDEK']['dateExecute'] = date('Y-m-d');
        $this->config['SDEK']['secure'] = md5($this->config['SDEK']['dateExecute'].'&'.$this->config['SDEK']['Secure_password']);
        #$this->config['SDEK']['receiverCityPostCode'] = $this->order['index'];

        $this->result = array(
            'delivery_cost' => 0
            ,'delivery_time' => '-'
            ,'delivery_payment' => 'payment_'.$this->order['payment']
            ,'error' => ''
            ,'cost_comment' => ''
            ,'time_comment' => 'Комплектация заказов по будням с 9 до 18'
            ,'terminals' => ''
            ,'exclude_delivery' => 'delivery_4' # для исключения определённых способов доставки, например: delivery_3
            ,'info' => ''
        );
        
        $this->getDimensions();
    }
    
    
    # расчёт стоимости доставки по СДЭК на странице товара
    public function dpc() {
        $this->result['terminals'] = $this->getTerminalList();
        $this->sdek(); 
        $result['door'] = $this->result;
        
		$this->config['SDEK']['tariffId'] = 136;
		$this->sdek(); 
        $result['point'] = $this->result;
        
        return $result;
    }
    
    
    
    public function run() {
        
        # для самомвывоза получем список пунктов выдачи
        if($this->order['delivery'] != 3) {
            $this->result['terminals'] = $this->getTerminalList();
        }
        
        # самовывоз для Твери - бесплатно
        if($this->order['city'] == 'Тверь' && $this->order['delivery'] == 1) {
            $this->result['error'] = '';
            $this->result['cost_comment'] = 'Самовывоз из г. Твери — бесплатно';
            return $this->result;
        }
        
        # любая страна, кроме России -> доставка id = 4
        if($this->order['country'] != 'Россия') {
            if($c = $this->modx->getObject('msDelivery',4)) {
                $this->result['delivery_cost'] = $c->get('price');
                $this->result['time_comment'] = 'Срок доставки сообщит менеджер при оформлении заказа.';
            }
            $this->result['exclude_delivery'] = 'delivery_1,delivery_2,delivery_3';
            return $this->result;
        }
        
        
        if($this->order['delivery'] == 2) {
            $this->result['cost_comment'] = 'При предоплате заказа. Стоимость доставки увеличится, если Вы оплачиваете заказ наличными при получении';
            $this->result['time_comment'] = 'С момента комплектации. Комплектация заказов по будням с 9 до 18';
        }
        
        
        if($this->order['delivery'] == 3) {
            $this->postOffice();
        }
        
        if($this->order['delivery'] == 1) {
            $this->config['SDEK']['tariffId'] = 136;
            $this->sdek();
        }
        if($this->order['delivery'] == 2) {
            $this->sdek();
        }
        
        if($this->result['delivery_cost'] == 0) {
            $this->result['error'] = $this->errorMessage;
        }
        
        $this->setOrderInfo();
        
        return $this->result;
    }
    
    




    # СДЭК
    private function sdek() {
        $cost = 0;
        
        if(empty($this->config['SDEK']['receiverCityId'])) return;
		
        $this->config['SDEK']['goods'][0] = array(
	       'weight' => ''.$this->dimensions['weight'].''
	       ,'volume' => ''.$this->dimensions['volume'].''
	    );
	       
        $sdek_response = $this->getSDEK($this->config['SDEK']);
        #print_r($sdek_response);

        if(isset($sdek_response['result']['price'])) {
            $cost = $sdek_response['result']['price'];
    
            # добавить наценку
            $cost = $cost + $cost * $this->config['SDEK']['markup'] / 100;
            
            if($this->order['payment'] != 3) {
                $X = $cost;
                $Y =  $cost + $this->dimensions['price'];
                $md = $Y + ($Y * 0.03) - $this->dimensions['price'];
                $cost = round($md);
            }


            $period = $sdek_response['result']['deliveryPeriodMin'].' — '.$sdek_response['result']['deliveryPeriodMax'];
            if ($sdek_response['result']['deliveryPeriodMin'] == $sdek_response['result']['deliveryPeriodMax']) {
                $period = $sdek_response['result']['deliveryPeriodMin'];
            }
            $this->result['delivery_cost'] = round($cost,0);
            $this->result['delivery_time'] = $period;
            $this->result['time_comment'] = 'Рабочие дни с момента комплектации. Комплектация заказов по будням с 9 до 18';
        }
        return;
    }
    
    




    # доставка Почтой РФ
    private function postOffice() {
        $cost = 0;

        # рассчитываем габариты коробки по максимальной длине
        $h_de = round(sqrt($this->dimensions['volume'] / $this->dimensions['maxLength']),0,PHP_ROUND_HALF_UP);
        
        $result = json_decode(
            file_get_contents(
                'http://post-api.ru/api/delivcost.php?&apikey='.$this->config['POST_OFFICE']['key'].'&i='.$this->order['index'].'&c='.$this->dimensions['cost'].'&ac=0&we='.$this->dimensions['weight'].'&w='.$this->dimensions['maxLength'].'&h='.$h_de.'&de='.$h_de.'&in=0&war=0&a=0'
            ), 
        true);

        if (isset($result['content']['delivery_cost'])) {
            $cost = $result['content']['delivery_cost'];
            if($this->dimensions['maxLength'] >= $this->config['POST_OFFICE']['addPriceLength']) {
                $cost = $cost * $this->config['POST_OFFICE']['kpl'];
            }
        }
    
        $this->result['delivery_cost'] = $cost;
        $this->result['time_comment'] = 'Срок доставки сообщит менеджер при оформлении заказа.';
    }
    
    
    
    
    
    
    
    
    private function setOrderInfo() {
        $info = '';
        $address = '<p>Адрес: {address}</p>';
        $receiver = '<p>Получатель: {receiver}</p>';
        $dimensions = '<p>Масса и габариты: {dimensions}</p>';
        
        $info .= '<p>'.$this->modx->lexicon('ms2_frontend_delivery').': '.$this->delivery->get('name').'</p>';
        $info .= '<p>'.$this->modx->lexicon('ms2_frontend_payment').': '.$this->payment->get('name').'</p>';

        $addressData = array('country','city','street','building','room');
        $receiverData = array('receiver','email','phone');
        $str = '';
        foreach ($addressData as $item) {
            if(isset($this->order[$item])) {
                $str .= $this->order[$item].', ';
            }
        }
        $info .= str_replace('{address}',substr($str,0,-2),$address);
        $str = '';
        foreach ($receiverData as $item) {
            if(isset($this->order[$item]) && !empty($this->order[$item])) {
                $str .= $this->order[$item].', ';
            }
        }
        $info .= str_replace('{receiver}',substr($str,0,-2),$receiver);
        if(isset($this->order['terminal']) && !empty($this->order['terminal'])) {
            $info .= '<p>'.$this->modx->lexicon('ms2_frontend_terminal').': '.$this->order['terminal'].'</p>';
        }
        $str = '';
        
        $dms = array('weight','volume');
        foreach ($this->dimensions as $k => $v) {
            if(in_array($k,$dms)) {
                $str .= $this->modx->lexicon('ms2_frontend_'.$k).': '.$v.', ';
            }
        }
        $info .= str_replace('{dimensions}',substr($str,0,-2),$dimensions);
    
        
        $this->result['info'] = $info;
        return;
    }
    
    
    

    
    # возвращает габариты и вес товаров в корзине    
    private function getDimensions() {
        $goods = array();
        
        foreach ($this->cart as $k => $v) {
            if($product = $this->modx->getObject('msProduct',array('id'=>$v['id']))) {
                                
                $weight = $product->get('weight') * $v['count'];

                if($length > $this->dimensions['maxLenght']) {
                    $this->dimensions['maxLenght'] = $length;
                }
                        
                $this->dimensions['weight'] += $weight/1000;
                $this->dimensions['cost'] += $v['price'] * $v['count'];
                $this->dimensions['volume'] += $volume;
                $this->dimensions['goods'][] = array(
                    'priduct' => $type
                    ,'volume' => $volume
                    ,'weight' => $weight
                );
            }
        }
        
        if($this->dimensions['weight'] < 1) $this->dimensions['weight'] = 1;
        if($this->dimensions['maxLenght'] >= $this->config['POST_OFFICE']['maxLengthEnd']) {
            $this->result['exclude_delivery'] .= 'delivery_3'; 
        }
        
        $this->dimensions['volume'] = round($this->dimensions['volume'],2);
                    
        #print_r($this->dimensions);
        return;
    }
	
	
	
	
	# пункты выдачи
	private function getTerminalList() {
		$points = '';
		$q = $this->modx->prepare("SELECT code,address,phones FROM ".$this->px."_sdek_cities WHERE city = ? AND active = 1");
		$q->execute(array(''.$this->order['city'].''));
		$res = $q->fetchAll(PDO::FETCH_ASSOC);
		if(count($res) != 0)  {
		    $this->config['SDEK']['receiverCityId'] = $res[0]['code'];
		    if($this->order['delivery'] == 1) {
    			foreach ($res as $item) {
    				$points .= '<option value="'.$this->order['city'].', '.$item['address'].', тел.: '.$item['phones'].'">'.$this->order['city'].', '.$item['address'].', тел.: '.$item['phones'].'</option>';
    			}
		    }
		}
		if($this->order['delivery'] == 1) {
		    return $points;
		}
		return '';
	}
	
	
	
	
	
	private function getSDEK($data) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://api.cdek.ru/calculator/calculate_price_by_json.php');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		$result = curl_exec($ch); 
		curl_close($ch); 
		return json_decode($result, true);
	}

	
}
?>